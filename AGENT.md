# kematjaya/crud-maker-bundle

CRUD generator berbasis Symfony MakerBundle. Generate controller CRUD + filter class + functional test untuk Doctrine entity.

## PSR

- **PSR-4**: `Kematjaya\CrudMakerBundle\` → `src/`  
- **PSR-4 (test)**: `Kematjaya\CrudMakerBundle\Tests\` → `tests/`

## Stack

- Symfony ^7.0|^8.0 (http-kernel, di, config, console, form, validator, twig, routing, security-csrf, translation, yaml)
- Doctrine ORM ^3.5 + doctrine-bundle ^3.2
- `symfony/maker-bundle` ^1.60 (parent framework)
- `kematjaya/base-controller-bundle` ^8.0
- `kematjaya/url-bundle` ^7.0|^8.0
- `api-platform/symfony` — soft dependency (DependencyBuilder check only, not in composer.json
  require) used by make:kmj-api-crud's generated OUTPUT, not by this bundle itself
- PHPUnit ^12.0 (dev)
- phpstan level 6 (`composer phpstan`) — `includes: vendor/phpstan/phpstan-doctrine/extension.neon`
  in phpstan.neon.dist, do not remove or Doctrine-aware types silently go wrong
- No cs-fixer config in repo

## Dirs

```
src/
├── DependencyInjection/
│   ├── Configuration.php          # crud_maker config tree
│   └── CrudMakerExtension.php     # DI extension loader
├── Maker/
│   ├── CRUDMaker.php              # make:kmj-crud (Twig/Bootstrap CRUD, needs existing entity)
│   ├── FilterMaker.php            # make:kmj-filter
│   ├── CRUDUnitTestMaker.php      # make:kmj-functional-test (functional test for CRUDMaker's controller)
│   └── ApiCrudMaker.php           # make:kmj-api-crud (API Platform CRUD write-side, needs existing
│                                     entity+#[ApiResource] — see next-steps text it prints)
├── Renderer/
│   ├── AbstractRenderer.php       # base path resolver + shared pluralize()/singularize()
│   ├── ControllerRenderer.php     # generates controller + twig views (for CRUDMaker)
│   ├── FilterTypeRenderer.php     # generates filter form class
│   ├── ApiCrudRenderer.php        # generates Input DTO, Service(+Interface), WriteProcessor,
│   │                                 optional CurrentUser*Extension + unit test, + crud-specs/*.json
│   │                                 sidecar (for ApiCrudMaker)
│   └── ApiCrudResult.php          # value object: list<string> $nextSteps printed after generate()
├── Resources/
│   ├── config/services.yaml       # service wiring (maker.command tags)
│   └── skeleton/                  # template files (.tpl.php)
│       ├── crud/controller/
│       ├── crud/views/
│       │   ├── bootstrap-3/
│       │   ├── bootstrap-4/
│       │   └── bootstrap-5/
│       ├── filter/
│       ├── test/
│       └── api-crud/              # Input.tpl.php, ServiceInterface.tpl.php, Service.tpl.php,
│                                     WriteProcessor.tpl.php, QueryExtension.tpl.php, test/ServiceTest.tpl.php
└── CrudMakerBundle.php
tests/
├── AppKernelTest.php               # registers DoctrineBundle + tests/config/doctrine.yml
│                                      (pdo_sqlite :memory:, attribute mapping on tests/Entity)
├── CrudMakerBundleTest.php          # FilterMaker smoke test
├── ApiCrudMakerTest.php             # ApiCrudMaker end-to-end smoke test (real Doctrine metadata,
│                                      php -l's every generated file, checks crud-specs/*.json)
├── Entity/TestEntity.php            # plain (non-mapped) fixture, used by the ClassDetails-reflection
│                                      fallback path — NOT #[ORM\Entity]
├── Entity/TestOwner.php, TestArticle.php  # real #[ORM\Entity] fixtures for ApiCrudMakerTest
├── Repository/TestArticleRepository.php
└── config/ (bundle.yml, config.yml, doctrine.yml, services_test.yml)
```

## CLI Commands

| Command | Class | Description |
|---|---|---|
| `make:kmj-crud` | CRUDMaker | Generate CRUD controller + 7 twig views + form + optional filter |
| `make:kmj-filter` | FilterMaker | Generate filter form class (SpiriitLabs FormFilterBundle) |
| `make:kmj-functional-test` | CRUDUnitTestMaker | Generate functional test for CRUD controller |
| `make:kmj-api-crud` | ApiCrudMaker | Generate API Platform CRUD write-side (Input DTO, Service+Interface, WriteProcessor, optional owner-scoped query extension, a CSV-export-data Controller, optional unit test, `crud-specs/<Entity>.json` sidecar for `@kematjaya/crud-ui-generator`) for an *existing* entity. Args: `entity-class`, `owner-property` (`-` for none), `permission-prefix` (`-` to guess from entity name), `with-access-control` (bool — gates create/edit/delete/export_all via `kematjaya/access-control-bundle`'s `isGranted('<prefix>.<action>')`), `with-tests` (bool), `searchable-fields` (comma-separated field names, `-`/empty for none — drives both the export controller's search filter and the `searchable` flag per field in the spec JSON). Never edits the entity file — prints the `#[ApiResource(...)]` block and (if searchable fields given) the `#[ApiFilter(SearchFilter::class, ...)]` block to paste in by hand, since safely rewriting an existing hand-authored file's attributes isn't attempted. Assumes the entity's write-side constructor/`update()` method params are in the same order as its Doctrine field mapping order — adjust generated Service manually if not. The export controller assumes standard `get{Field}()` getters exist for every field it exports (including the detected timestamp field) — adjust manually if getter names differ. datetime/date/time fields are skipped from the Input DTO (treated as system-managed) — see `ApiCrudRenderer::SKIPPED_TYPES`; a field named like `created*` of one of those types is still detected separately as `timestampField` (spec JSON + export "Created" column), via `ApiCrudRenderer::findTimestampField()`. Prints a `rate_limiter` YAML block for the export endpoint to add to `config/packages/framework.yaml` by hand — never auto-edits config files. Export endpoint is a plain Symfony controller (not an ApiPlatform operation) returning JSON `{totalItems, member}`; the frontend generator (`@kematjaya/crud-ui-generator`) is the one that turns that into an actual CSV download, mirroring `boilerplate/frontend`'s hand-written Notes feature. `bulk_delete`/`export_selected` are frontend-only permission keys (gate buttons) — no dedicated backend endpoint exists for either; bulk delete just issues one `DELETE` per selected row. |

## Bundle Config (`config/packages/crud_generator.yaml`)

```yaml
crud_maker:
    entity:
        namespace_prefix: 'Entity\'   # default
        suffix: ''                     # default
    filter:
        namespace_prefix: 'Filter\'    # default
        suffix: 'FilterType'           # default
    templates:
        path: '%kernel.project_dir%/generator'  # null → fallback ke src/Resources/skeleton/
```

Custom template override: set `crud_maker.templates.path` ke dir yg punya struktur `crud/controller/`, `crud/views/`, `filter/`, `test/` → akan merge dengan skeleton bawaan.

## Test

```
phpunit -c phpunit.xml.dist
```

Atau `vendor/bin/phpunit -c phpunit.xml.dist`. Boot kernel test `AppKernelTest` via WebTestCase.

## Caveman (gotchas & pitfalls)

1. **AGENT.md is stale** — file ini dulunya copypaste dari `base-controller-bundle`. Semua info di sini adalah yang benar untuk repo ini.
2. **No phpstan/cs-fixer** — repo ini murni PHPUnit saja. Jangan coba `composer phpstan` atau `composer cs:check` — pasti gagal.
3. **Unit test butuh kernel boot** — `CrudMakerBundleTest::testGenerateFilter` depends pada `testInstanceMakerFilter`. Jangan ubah urutan test tanpa update `#[Depends]`.
4. **`make:kmj-crud` dan `make:kmj-filter` butuh Doctrine entity terdaftar** — command menggunakan `doctrineHelper->getEntitiesForAutocomplete()`. Jika entity belum ada di registry → akan error.
5. **Generate test membuat file** — `testGenerateFilter` bikin file `tests/Filter/TestEntityFilterType.php` lalu di-remove. Kalau test crash di tengah → file sisa akan tertinggal.
6. **Inflector fallback** — `ControllerRenderer` coba `InflectorFactory` (doctrine/inflector 2+) dulu; fallback ke `LegacyInflector`. Pastikan doctrine/inflector terinstall.
7. **Render path priority** — `templates.path` dari config diprioritaskan, lalu `src/Resources/skeleton/`. Jika file template ada di dua tempat → custom path menang.
8. **ApiCrudMaker asumsikan entity dan repository sudah ada** — `getRepositoryClass()` di-guard di awal `ApiCrudRenderer::generate()` (throw kalau null); JANGAN hapus guard itu dan JANGAN pakai `?->`/null-check lagi setelahnya — phpstan (level 6, dengan phpstan-doctrine include) akan correctly flag itu sebagai dead code ("always evaluates to true"), karena guard clause di awal sudah menjamin non-null untuk sisa method.
9. **Test kernel bundle ini sendiri root generator-nya di `Kematjaya\CrudMakerBundle\Tests`** (lihat `tests/config/services_test.yml`) — kalau maker generate class dengan prefix `Tests\...` (seperti `ApiCrudRenderer`'s unit-test output, prefix `Tests\Unit\Service\`), hasilnya numpuk jadi `tests/Tests/Unit/Service/...` (double "Tests") HANYA di test suite bundle ini sendiri. Ini bukan bug — di app consumer beneran (root generator `App\`), hasilnya normal `tests/Unit/Service/...`. Jangan "fix" double-nesting ini di kode maker, itu artefak testing-bundle-against-dirinya-sendiri.
10. **phpstan-doctrine WAJIB di-include** — `phpstan.neon.dist` harus punya `includes: [vendor/phpstan/phpstan-doctrine/extension.neon]`. Tanpa ini, phpstan salah infer beberapa method Doctrine-related jadi "always true"/"never null" yang sebenarnya valid nullable — gampang kejebak nge-suppress padahal itu instalasi config yang belum lengkap.

## Context7

Saat LLM perlu menyelesaikan task yg menyentuh API/syntax/config dari dependency berikut, **wajib** resolve + query Context7 sebelum coding:

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

Alur: resolve → dapat `/org/project` → `context7_query-docs` dengan query spesifik → baru tulis/ubah kode.
