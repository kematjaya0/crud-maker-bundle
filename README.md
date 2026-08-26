# Bootstrap CRUD Generator
CRUD generator base on Symfony Maker Bundle

## API Platform + Next.js CRUD generator (`make:kmj-api-crud`)

Generates the write-side of an [API Platform](https://api-platform.com/) resource (Input DTO,
Service, WriteProcessor, CSV export endpoint) for an *existing* Doctrine entity, plus a
`crud-specs/{Entity}.json` sidecar that [`@kematjaya/crud-ui-generator`](js/) reads to generate a
matching Next.js frontend (list/create/edit pages, table with search/pagination/bulk-delete/CSV
export, form, BFF proxy routes).

### 1. Install

```
composer require --dev kematjaya/crud-maker-bundle
```

### 2. Have an entity

The entity must already exist (`make:entity` or hand-written) with a repository class. Any of
these shapes work — the maker detects which one your entity uses and generates matching code:

- **constructor + `update()`**: `new Entity($field1, $field2)` at create time, `$entity->update($field1, $field2)` at edit time (params in the same order as the entity's Doctrine field mapping).
- **setters** (what a plain `make:entity`-scaffolded entity looks like): `new Entity()` then `$entity->setField1(...)->setField2(...)`, no constructor/`update()` needed.

If neither pattern is viable (e.g. a required-arg constructor with no matching `update()`, or a
setter missing for one of the fields), the command fails with a clear error instead of silently
generating broken code.

### 3. Run the maker

```
php bin/console make:kmj-api-crud
```

It asks for:

| Prompt | Meaning |
|---|---|
| `entity-class` | The entity to generate CRUD for (autocompletes) |
| `owner-property` | Association property scoping rows to the current user (e.g. `owner`) — `-` for none |
| `permission-prefix` | Prefix for permission keys / URL segment (e.g. `notes` → `notes.create`, `/api/notes`) — `-` to guess from the entity name |
| `with-access-control` | Gate create/edit/delete/export via [`kematjaya/access-control-bundle`](https://github.com/kematjaya0/access-control-bundle)'s `isGranted()`? |
| `with-tests` | Generate a PHPUnit test for the Service? |
| `searchable-fields` | Comma-separated field names searchable from the frontend (e.g. `title`) — `-` for none |
| `write-entity-attributes` | Add `#[ApiResource]`/`#[ApiFilter]` to the entity file automatically? (see below) |

Non-interactively:

```
php bin/console make:kmj-api-crud Note owner notes true true title true --no-interaction
```

### 4. What gets written

- `src/Dto/{Entity}Input.php`, `src/Service/{Entity}Service(Interface).php`, `src/State/{Entity}WriteProcessor.php`
- `src/Controller/{Entity}ExportDataController.php` — CSV-export-data endpoint (`/api/{prefix}/export-data`, rate-limited, returns JSON — the frontend turns it into an actual CSV download)
- `src/State/CurrentUser{Entity}Extension.php` (only if `owner-property` is set) — scopes `GetCollection`/`Get` to the current user's rows
- `tests/Unit/Service/{Entity}ServiceTest.php` (only if `with-tests`)
- `crud-specs/{Entity}.json` — the frontend generator's input
- **The entity file itself**, if `write-entity-attributes` was confirmed: `#[ApiResource(operations: [...])]` and (if there are searchable fields) `#[ApiFilter(SearchFilter::class, ...)]`, added via AST manipulation ([`nikic/php-parser`](https://github.com/nikic/PHP-Parser)'s format-preserving printer — the same technique `make:entity` uses internally) so the rest of the file is untouched. If the entity already has one of these attributes, or `write-entity-attributes` was declined, the block is printed instead for you to paste in by hand.
- **`config/packages/framework.yaml`**: a `framework.rate_limiter.{limiter}` entry for the export endpoint, inserted via targeted text insertion (comments/formatting elsewhere in the file are left untouched). Idempotent — a limiter that already exists by that name is left alone.
- **`config/permissions/default.yaml`** (only if `with-access-control`): the permission item for this resource (`gated: true` + `actions: { create, edit, delete, bulk_delete, export_selected, export_all }`), added to (or created in) a `Master` section. If an item with that `key` already exists, only its missing pieces (`gated: true` and/or missing action keys) are filled in — existing fields, including custom action labels, are left untouched. A brand-new item gets a placeholder `href`/`icon` you'll want to review.

If either config file is missing, or its structure isn't recognized, the writer falls back to printing the block for you to paste in by hand instead of failing the whole command — check the printed next-steps either way.

### 5. Remaining manual steps (printed after generation)

- After the permission key is in place, run `bin/console kematjaya:access-control:sync` (if `with-access-control`).
- Review the Service if the entity's field order might not match the Input DTO's.

### 6. Generate the frontend

```
cd ../frontend   # or wherever the Next.js project lives
npm run api:types
npx @kematjaya/crud-ui-generator ../backend/crud-specs/{Entity}.json --src src
npm run format
```

`npm run api:types` must run *after* step 5's `#[ApiResource]`/`#[ApiFilter]` are in place, so the
regenerated OpenAPI types include the new resource — the frontend generator's output imports
types from it. See [`js/README.md`](js/) for what gets generated, assumptions (id type, single
search field, etc.), and how to install `@kematjaya/crud-ui-generator` before it's on your `PATH`
via `npx`.

## Twig CRUD generator (`make:kmj-crud`, `make:kmj-filter`)

Older, server-rendered (Symfony Form + Twig + Bootstrap) generator — a separate code path from
`make:kmj-api-crud` above, still maintained for apps that use it.

1. installation
```
composer require kematjaya/crud-maker-bundle
```
2. Generate Filter Form
```
php bin/console make:kmj-filter
```
3. Generate CRUD include form, filter and pagination
```
php bin/console make:kmj-crud
```

if use modal add to base template
```
<div class="modal fade" id="myModal">
    <div class="modal-content" id="modal-dialog">
        <div style="text-align: center"><img src="{{ asset('bundles/basecontroller/images/loading.gif') }}" style="width: 20px"/></div>
    </div>
</div>
```
and add jquery.js

- if you want to change generator template, you can set template path in config
```
# config/packages/crud_generator.yaml
# assume your template in root-project/generator
crud_maker:
    templates:
        path: '%kernel.project_dir%/generator'

```
thank to:
- Filter type provide by https://github.com/lexik/LexikFormFilterBundle
- pagination provide by https://github.com/KnpLabs/KnpPaginatorBundle
- Base CRUD by: https://github.com/symfony/maker-bundle
