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
- PHPUnit ^12.0 (dev)
- No phpstan, no cs-fixer config in repo

## Dirs

```
src/
├── DependencyInjection/
│   ├── Configuration.php          # crud_maker config tree
│   └── CrudMakerExtension.php     # DI extension loader
├── Maker/
│   ├── CRUDMaker.php              # make:kmj-crud
│   ├── FilterMaker.php            # make:kmj-filter
│   └── CRUDUnitTestMaker.php      # make:kmj-functional-test
├── Renderer/
│   ├── AbstractRenderer.php       # base path resolver
│   ├── ControllerRenderer.php     # generates controller + twig views
│   └── FilterTypeRenderer.php     # generates filter form class
├── Resources/
│   ├── config/services.yaml       # service wiring (maker.command tags)
│   └── skeleton/                  # template files (.tpl.php)
│       ├── crud/controller/
│       ├── crud/views/
│       │   ├── bootstrap-3/
│       │   ├── bootstrap-4/
│       │   └── bootstrap-5/
│       ├── filter/
│       └── test/
└── CrudMakerBundle.php
tests/
├── AppKernelTest.php
├── CrudMakerBundleTest.php
├── Entity/TestEntity.php
└── config/ (bundle.yml, config.yml, services_test.yml)
```

## CLI Commands

| Command | Class | Description |
|---|---|---|
| `make:kmj-crud` | CRUDMaker | Generate CRUD controller + 7 twig views + form + optional filter |
| `make:kmj-filter` | FilterMaker | Generate filter form class (SpiriitLabs FormFilterBundle) |
| `make:kmj-functional-test` | CRUDUnitTestMaker | Generate functional test for CRUD controller |

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
