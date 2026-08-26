# kematjaya/crud-maker-bundle (monorepo)

CRUD generator berbasis Symfony MakerBundle. Generate controller CRUD + filter class + functional
test untuk Doctrine entity (Twig flavor), atau API Platform write-side + Next.js frontend spec
(API flavor).

**Ini monorepo composer**, bukan satu package. Root `composer.json` cuma metapackage (BC pointer,
tidak ada kode sendiri). Kode sebenarnya ada di tiga package terpisah di `packages/`, masing-masing
punya `composer.json` sendiri dan (untuk twig/api) di-*subtree-split* ke repo GitHub mandiri saat
rilis — lihat `scripts/release.sh`.

## Package layout

| Package | Path | Namespace | Isi |
|---|---|---|---|
| `kematjaya/crud-maker-core` | `packages/crud-maker-core/` | `Kematjaya\CrudMakerBundle\Renderer\` | Cuma `AbstractRenderer` (base class). Bukan Symfony bundle, tidak punya command. |
| `kematjaya/crud-maker-twig-bundle` | `packages/crud-maker-twig-bundle/` | `Kematjaya\CrudMakerBundle\Twig\` | `make:kmj-crud`, `make:kmj-filter`, `make:kmj-functional-test` |
| `kematjaya/crud-maker-api-bundle` | `packages/crud-maker-api-bundle/` | `Kematjaya\CrudMakerBundle\Api\` | `make:kmj-api-crud` |
| `kematjaya/crud-maker-bundle` (root) | `.` | — | Metapackage, `require`-only, tidak ada `src/` |

**Kenapa namespace-nya punya segmen `Twig\`/`Api\`** (bukan langsung `Kematjaya\CrudMakerBundle\Maker\...`
seperti sebelum split): supaya kalau ada consumer yang install kedua bundle sekaligus, class
`Configuration`/`Extension`/dst di dua package itu tidak collide di bawah PSR-4 prefix yang sama.
`AbstractRenderer` di core sengaja TIDAK dapat segmen tambahan (tetap bare `Renderer\`) karena cuma
satu class, tidak ada resiko collide.

Repo terpisah untuk `js/` (npm package `@kematjaya/crud-ui-generator`, Next.js frontend generator)
— tetap di monorepo yang sama (bukan `packages/`), publish via GitHub Actions `Publish` workflow
saat release di-buat, bukan via `scripts/release.sh`.

## PSR-4 per package

- `Kematjaya\CrudMakerBundle\Renderer\` → `packages/crud-maker-core/src/Renderer/`
- `Kematjaya\CrudMakerBundle\Twig\` → `packages/crud-maker-twig-bundle/src/`
- `Kematjaya\CrudMakerBundle\Api\` → `packages/crud-maker-api-bundle/src/`
- `Kematjaya\CrudMakerBundle\Tests\` → `tests/` (root, autoload-dev — test suite belum dipecah per package, masih di root, meng-cover ketiga package sekaligus)

## Dev workflow (monorepo)

Root `composer.json` punya `"repositories": [{"type": "path", "url": "packages/*"}]` — `composer
update` di root symlink ketiga package ke `vendor/`, jadi satu `vendor/bin/phpunit` jalan lintas
package. Root `require` pakai constraint asli (`^1.0`) terhadap ketiga package, dipenuhi lewat
`extra.branch-alias: {"dev-master": "1.0.x-dev"}` di tiap `packages/*/composer.json` — JANGAN
ganti balik ke hardcode `"version": "x.y.z"` di composer.json package (pernah dicoba, salah —
lihat gotcha #11).

## Stack (gabungan, per package beda subset)

- Symfony ^7.0|^8.0 — core cuma butuh `dependency-injection`; twig-bundle tambah
  `twig-bundle`/`form`/`security-csrf`; api-bundle tambah `http-foundation`/`translation`/`yaml`,
  TIDAK butuh twig/form/csrf sama sekali (itu justru alasan split-nya)
- Doctrine ORM ^3.5 + doctrine-bundle ^3.2 (twig & api)
- `symfony/maker-bundle` ^1.60 (parent framework, twig & api)
- `kematjaya/base-controller-bundle` ^8.0, `kematjaya/url-bundle` — twig-bundle only
- `nikic/php-parser` ^5.0 — api-bundle only (AST-based entity attribute injection)
- `api-platform/symfony` — soft dependency di api-bundle (DependencyBuilder check only, not in
  composer.json require) dipakai OUTPUT `make:kmj-api-crud`, bukan bundle-nya sendiri
- PHPUnit ^12.0 (dev, root)
- phpstan level 6 (`composer phpstan`) — `phpstan.neon.dist` scan ketiga `packages/*/src/`,
  exclude `packages/{twig,api}-bundle/src/Resources/skeleton/`. `includes:
  vendor/phpstan/phpstan-doctrine/extension.neon` — JANGAN dihapus, Doctrine-aware types akan
  salah infer tanpa ini.

## Dirs (per package, pola sama)

```
packages/crud-maker-core/
├── composer.json
└── src/Renderer/AbstractRenderer.php   # base path resolver + shared pluralize()/singularize()
                                            # constructor: (string $ownSkeletonPath, ?string $customPath)
                                            # — TIDAK baca ContainerBagInterface sendiri, itu
                                            # tanggung jawab subclass (lihat gotcha #12)

packages/crud-maker-twig-bundle/
├── composer.json
└── src/
    ├── CrudMakerTwigBundle.php
    ├── DependencyInjection/
    │   ├── Configuration.php          # root node 'crud_maker_twig'
    │   └── CrudMakerTwigExtension.php
    ├── Maker/
    │   ├── CRUDMaker.php              # make:kmj-crud
    │   ├── FilterMaker.php            # make:kmj-filter
    │   └── CRUDUnitTestMaker.php      # make:kmj-functional-test
    ├── Renderer/
    │   ├── ControllerRenderer.php
    │   └── FilterTypeRenderer.php
    └── Resources/
        ├── config/services.yaml       # termasuk redeklarasi service "maker infra" (generator,
        │                                 maker.file_manager, dst) — lihat gotcha #13
        └── skeleton/{crud,filter,test}/

packages/crud-maker-api-bundle/
├── composer.json
└── src/
    ├── CrudMakerApiBundle.php
    ├── DependencyInjection/
    │   ├── Configuration.php          # root node 'crud_maker_api'
    │   └── CrudMakerApiExtension.php
    ├── Maker/ApiCrudMaker.php         # make:kmj-api-crud
    ├── Renderer/
    │   ├── ApiCrudRenderer.php        # generates Input DTO, Service(+Interface), WriteProcessor,
    │   │                                 optional CurrentUser*Extension + unit test, +
    │   │                                 crud-specs/*.json sidecar
    │   └── ApiCrudResult.php          # value object: list<string> $nextSteps
    ├── Util/
    │   ├── EntityAttributeWriter.php  # AST-based #[ApiResource]/#[ApiFilter] injection (nikic/php-parser)
    │   ├── RepositoryMethodWriter.php # AST-based method injection into repository class
    │   ├── RateLimiterConfigWriter.php    # targeted text insertion into config/packages/framework.yaml
    │   └── PermissionManifestWriter.php   # targeted text insertion into config/permissions/default.yaml
    └── Resources/
        ├── config/services.yaml
        └── skeleton/api-crud/

tests/                                  # ROOT-level, belum dipecah per package
├── AppKernelTest.php                   # registers TwigBundle + CrudMakerTwigBundle +
│                                          CrudMakerApiBundle + FrameworkBundle + DoctrineBundle
├── CrudMakerBundleTest.php             # FilterMaker smoke test (Twig)
├── ApiCrudMakerTest.php                # ApiCrudMaker end-to-end smoke test
├── Util/*Test.php                      # EntityAttributeWriter/PermissionManifestWriter/RateLimiterConfigWriter
├── Entity/, Repository/                # fixtures dipakai lintas package
└── config/ (bundle.yml, config.yml, doctrine.yml, services_test.yml)
```

## CLI Commands

| Command | Package | Class | Description |
|---|---|---|---|
| `make:kmj-crud` | twig-bundle | `Twig\Maker\CRUDMaker` | Generate CRUD controller + 7 twig views + form + optional filter |
| `make:kmj-filter` | twig-bundle | `Twig\Maker\FilterMaker` | Generate filter form class (SpiriitLabs FormFilterBundle) |
| `make:kmj-functional-test` | twig-bundle | `Twig\Maker\CRUDUnitTestMaker` | Generate functional test for CRUD controller |
| `make:kmj-api-crud` | api-bundle | `Api\Maker\ApiCrudMaker` | Generate API Platform CRUD write-side (Input DTO, Service+Interface, WriteProcessor, optional owner-scoped query extension, a CSV-export-data Controller, optional unit test, `crud-specs/<Entity>.json` sidecar for `@kematjaya/crud-ui-generator`) for an *existing* entity. Args: `entity-class`, `owner-property` (`-` for none), `permission-prefix` (`-` to guess from entity name), `with-access-control` (bool — gates create/edit/delete/export_all via `kematjaya/access-control-bundle`'s `isGranted('<prefix>.<action>')`), `with-tests` (bool), `searchable-fields` (comma-separated field names, `-`/empty for none — drives both the export controller's search filter and the `searchable` flag per field in the spec JSON), `write-entity-attributes` (bool, default asks/true — see below). By default now DOES edit the entity file: `Api\Util\EntityAttributeWriter` uses `nikic/php-parser`'s format-preserving pretty-printer (the same technique `symfony/maker-bundle`'s internal `ClassSourceManipulator` uses for `make:entity`, but that class can't build attribute *arguments* that are themselves expressions like `operations: [new GetCollection(), ...]`, so this writer parses the whole target attribute block as its own snippet and transplants the resulting `AttributeGroup` nodes onto the real class instead) to add `#[ApiResource(...)]` and (if there are searchable fields) `#[ApiFilter(SearchFilter::class, ...)]` plus their `use` statements directly to the entity, leaving everything else in the file byte-for-byte untouched. Refuses (throws, caught by the maker) if the entity already carries an attribute with a colliding short name — falls back to printing the block to paste in by hand in that case (or if `write-entity-attributes` is false). The generated Service's write style is picked automatically by `ApiCrudRenderer::detectWriteMode()` (via Reflection on the entity class, no entity file changes): if the entity has a constructor accepting >= the field count AND an `update()` method, it generates `new Entity(...fields)` / `$entity->update(...fields)` calls (Note/TestArticle style, params assumed in the same order as Doctrine field mapping order — adjust manually if not); otherwise, if the entity has a zero-arg-callable constructor and a `set{Field}()` for every field (+ `set{OwnerProperty}()` if there's an owner), it generates `new Entity()` + `$entity->setField(...)` calls instead (plain `make:entity`-scaffolded style). If neither pattern is viable, generation **fails with a `\RuntimeException`** naming the missing setters — deliberately loud instead of silently generating a Service that drops data (unmatched constructor args are silently discarded by PHP, not an error) or fatals on `update()`. The export controller assumes standard `get{Field}()` getters exist for every field it exports (including the detected timestamp field) — adjust manually if getter names differ. datetime/date/time fields are skipped from the Input DTO (treated as system-managed) — see `ApiCrudRenderer::SKIPPED_TYPES`; a field named like `created*` of one of those types is still detected separately as `timestampField` (spec JSON + export "Created" column), via `ApiCrudRenderer::findTimestampField()`. The spec JSON also carries `idType` (`uuid`/`int`/`string`, from `ApiCrudRenderer::detectIdType()` reading the id column's Doctrine type) — not every entity uses UUID ids, and the frontend generator reads this to pick a matching id validator instead of hard-assuming UUID. Also auto-edits two app config files, same resilience pattern as the entity-attribute writer above (falls back to printing a manual paste-in block on failure, never hard-fails the whole command): `Api\Util\RateLimiterConfigWriter` inserts a `framework.rate_limiter.<limiter>` entry into `config/packages/framework.yaml` for the export endpoint (idempotent — a no-op if that limiter name already exists), and, when `with-access-control` is set, `Api\Util\PermissionManifestWriter` adds/completes the resource's permission item (`gated: true` + `actions: {create, edit, delete, bulk_delete, export_selected, export_all}`) in `config/permissions/default.yaml` under a `Master` section (created if missing) — an item that already exists only gets its missing `gated`/`actions` pieces filled in, existing fields (incl. custom action labels) are left untouched; a brand-new item gets a placeholder `href`/`icon` that still needs manual review. Both writers use targeted line/indent-based text insertion rather than a full `Symfony\Component\Yaml\Yaml::parse()`+`dump()` round trip, so comments and formatting elsewhere in the file survive. Export endpoint is a plain Symfony controller (not an ApiPlatform operation) returning JSON `{totalItems, member}`; the frontend generator (`@kematjaya/crud-ui-generator`) is the one that turns that into an actual CSV download, mirroring `boilerplate/frontend`'s hand-written Notes feature. `bulk_delete`/`export_selected` are frontend-only permission keys (gate buttons) — no dedicated backend endpoint exists for either; bulk delete just issues one `DELETE` per selected row. |

## Bundle Config

Twig (`Configuration` root node `crud_maker_twig`):

```yaml
crud_maker_twig:
    entity:
        namespace_prefix: 'Entity\'   # default
        suffix: ''                     # default
    filter:
        namespace_prefix: 'Filter\'    # default
        suffix: 'FilterType'           # default
    templates:
        path: '%kernel.project_dir%/generator'  # null → fallback ke bundle's own Resources/skeleton/
```

Api (`Configuration` root node `crud_maker_api`, hanya `templates`):

```yaml
crud_maker_api:
    templates:
        path: '%kernel.project_dir%/generator'
```

Custom template override: dir yg dirujuk `templates.path` harus punya struktur yang sama dengan
`Resources/skeleton/` bundle terkait (`crud/controller/`, `crud/views/`, `filter/`, `test/` untuk
twig; `api-crud/` untuk api) → akan merge dengan skeleton bawaan, custom path menang kalau file
ada di dua tempat.

## Test

```
vendor/bin/phpunit
```

Boot kernel test `AppKernelTest` (root `tests/`) via WebTestCase — registers `TwigBundle`,
`CrudMakerTwigBundle`, `CrudMakerApiBundle`, `FrameworkBundle`, `DoctrineBundle` sekaligus (satu
kernel test untuk ketiga package).

## Release

Lihat `scripts/release.sh` (bash 3.2-compatible, no associative arrays — macOS default bash).
**Dua jalur versi independen, JANGAN disatukan:**

1. **Umbrella (root, `origin`, `kematjaya/crud-maker-bundle`)** — jalur versi lama, lanjutan
   7.x/8.x dari sebelum split (tag TANPA prefix `v`, mis. `8.4.0`). Di-tag manual
   (`git tag 8.5.0 && git push origin 8.5.0`), HANYA kalau `require`-range di root
   `composer.json` berubah. `scripts/release.sh` TIDAK menyentuh ini sama sekali.
2. **Tiga split package** (`core`/`twig-bundle`/`api-bundle`) — jalur versi baru, fresh mulai
   `v1.0.0` (WITH prefix `v`), lockstep SATU SAMA LAIN saja. `scripts/release.sh vX.Y.Z` tag +
   subtree-split + push ke tiga repo mirror sekaligus.

## Caveman (gotchas & pitfalls)

1. **Ini bekas single-package repo yang di-split jadi monorepo** — kalau nemu referensi ke
   `src/Maker/...`, `Kematjaya\CrudMakerBundle\Maker\...` (tanpa `Twig\`/`Api\`), atau config key
   `crud_maker:` (tanpa suffix) di dokumen/kode lain, itu SISA LAMA sebelum split — sudah tidak
   valid, jangan diikuti.
2. **phpstan ADA sekarang** (`phpstan.neon.dist`, level 6) — versi lama AGENT.md ini bilang "no
   phpstan", itu sudah tidak benar. `composer phpstan` valid dijalankan (butuh
   `memory_limit` besar di beberapa environment, lihat `php -d memory_limit=512M vendor/bin/phpstan`
   kalau exit code 255 karena kehabisan memory).
3. **Unit test butuh kernel boot** — `CrudMakerBundleTest::testGenerateFilter` depends pada
   `testInstanceMakerFilter`. Jangan ubah urutan test tanpa update `#[Depends]`.
4. **`make:kmj-crud` dan `make:kmj-filter` butuh Doctrine entity terdaftar** — command
   menggunakan `doctrineHelper->getEntitiesForAutocomplete()`. Jika entity belum ada di
   registry → akan error.
5. **Generate test membuat file** — `testGenerateFilter` bikin file
   `tests/Filter/TestEntityFilterType.php` lalu di-remove. Kalau test crash di tengah → file
   sisa akan tertinggal (sudah di-`.gitignore`).
6. **Inflector fallback** — `AbstractRenderer` (core) coba `InflectorFactory`
   (doctrine/inflector 2+) dulu; fallback ke `LegacyInflector`. Pastikan doctrine/inflector
   terinstall.
7. **Render path priority** — `templates.path` dari config diprioritaskan, lalu
   `Resources/skeleton/` bawaan bundle terkait. Jika file template ada di dua tempat → custom
   path menang.
8. **ApiCrudMaker asumsikan entity dan repository sudah ada** — `getRepositoryClass()` di-guard
   di awal `ApiCrudRenderer::generate()` (throw kalau null); JANGAN hapus guard itu dan JANGAN
   pakai `?->`/null-check lagi setelahnya — phpstan (level 6, dengan phpstan-doctrine include)
   akan correctly flag itu sebagai dead code ("always evaluates to true"), karena guard clause
   di awal sudah menjamin non-null untuk sisa method.
9. **Test kernel bundle ini sendiri root generator-nya di `Kematjaya\CrudMakerBundle\Tests`**
   (lihat `tests/config/services_test.yml`) — kalau maker generate class dengan prefix
   `Tests\...` (seperti `ApiCrudRenderer`'s unit-test output, prefix `Tests\Unit\Service\`),
   hasilnya numpuk jadi `tests/Tests/Unit/Service/...` (double "Tests") HANYA di test suite
   bundle ini sendiri. Ini bukan bug — di app consumer beneran (root generator `App\`), hasilnya
   normal `tests/Unit/Service/...`. Jangan "fix" double-nesting ini di kode maker, itu artefak
   testing-bundle-against-dirinya-sendiri.
10. **phpstan-doctrine WAJIB di-include** — `phpstan.neon.dist` harus punya `includes:
    [vendor/phpstan/phpstan-doctrine/extension.neon]`. Tanpa ini, phpstan salah infer beberapa
    method Doctrine-related jadi "always true"/"never null" yang sebenarnya valid nullable —
    gampang kejebak nge-suppress padahal itu instalasi config yang belum lengkap.
11. **JANGAN hardcode `"version"` di `packages/*/composer.json`** — pernah dicoba supaya path-repo
    lokal bisa resolve constraint `^1.0`, tapi itu salah: begitu package sungguhan ter-tag lewat
    `scripts/release.sh`, field `version` statis itu ikut ter-subtree-split ke repo published dan
    bisa membeku/membingungkan versi asli dari git tag. Solusinya `extra.branch-alias:
    {"dev-master": "1.0.x-dev"}` di tiap `packages/*/composer.json` — idiom composer standar
    (dipakai Symfony sendiri), cuma memengaruhi resolusi branch `dev-master` di path-repo, tidak
    ikut "membeku" ke tag rilis asli.
12. **`AbstractRenderer` (core) sengaja TIDAK baca `ContainerBagInterface` sendiri** — constructor-nya
    `(string $ownSkeletonPath, ?string $customPath)`, murni path resolution, tidak tahu apa-apa
    soal DI container atau config alias. Yang baca `ContainerBagInterface->get('crud_maker_twig'
    /'crud_maker_api')` dan hitung `dirname(__DIR__) . '/Resources/skeleton'` adalah SUBCLASS
    (`ControllerRenderer`/`FilterTypeRenderer`/`ApiCrudRenderer`) di constructor masing-masing —
    karena `dirname(__DIR__)` dari lokasi file `AbstractRenderer` sendiri (di package core) akan
    salah kalau dipakai buat resolve skeleton milik package lain. Jangan pindahkan logic itu balik
    ke `AbstractRenderer`.
13. **Service "maker infra" (`generator`, `maker.file_manager`, `maker.autoloader_util`, dst)
    DIDUPLIKASI di `services.yaml` twig-bundle DAN api-bundle** — bukan taruh di core, karena core
    bukan Symfony bundle (tidak ada Extension buat load services.yaml-nya). Kalau consumer install
    kedua bundle sekaligus, service id yang sama didefinisikan dua kali — tidak error (Symfony DI
    biarkan definisi terakhir menang, container builder tidak melempar exception untuk override
    non-private service macam ini), tapi kalau mau nambah service baru ke grup ini, ingat harus
    ditambahkan ke DUA `services.yaml`, bukan cuma satu.
14. **`packages/crud-maker-api-bundle/src/Resources/config/services.yaml`'s `maker.file_manager`
    pakai `~` (null) untuk argumen ke-5 (`$twigDefaultPath`)**, bukan `%twig.default_path%` seperti
    di twig-bundle — karena api-bundle sengaja TIDAK depend ke `symfony/twig-bundle`, parameter itu
    tidak akan ada kalau consumer cuma install api-bundle saja. Constructor `FileManager`
    (`vendor/symfony/maker-bundle/src/FileManager.php`) sudah nullable di situ, aman.

## Context7

Saat LLM perlu menyelesaikan task yg menyentuh API/syntax/config dari dependency berikut, **wajib**
resolve + query Context7 sebelum coding:

| Library | Context7 ID | Kapan dipakai |
|---|---|---|
| Symfony MakerBundle | `/symfony/maker-bundle` | AbstractMaker, Generator, Str, Validator, FormTypeRenderer API |
| Symfony Framework | `/symfony/symfony` | DI Extension, Configuration, Console, Form, Twig, Routing, Validator, Security CSRF |
| Doctrine ORM | `/doctrine/orm` | Entity mapping, Repository, QueryBuilder, DQL |
| Doctrine Bundle | `/doctrine/doctrine-bundle` | Registry, ManagerRegistry, bundle config |
| Twig | `/twig/twig` | Template syntax, `generateTemplate()` usage |
| SpiriitLabs FormFilterBundle | `/spiriitlabs/form-filter-bundle` | FilterType API, FilterOperands, BooleanFilterType, etc. |
| KNP Paginator | `/knplabs/knp-paginator-bundle` | Pagination usage in generated controllers |
| PHPUnit | `/phpunit/phpunit` | Test attributes ([Depends]), WebTestCase API |
| nikic/php-parser | `/nikic/php-parser` | AST manipulation in `EntityAttributeWriter`/`RepositoryMethodWriter` |

Alur: resolve → dapat `/org/project` → `context7_query-docs` dengan query spesifik → baru tulis/ubah kode.
