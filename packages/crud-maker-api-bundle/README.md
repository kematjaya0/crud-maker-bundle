# kematjaya/crud-maker-api-bundle

Symfony MakerBundle command that generates the write-side of an
[API Platform](https://api-platform.com/) resource (Input DTO, Service, WriteProcessor, CSV
export endpoint) for an *existing* Doctrine entity, plus a `crud-specs/{Entity}.json` sidecar
that [`@kematjaya/crud-ui-generator`](https://www.npmjs.com/package/@kematjaya/crud-ui-generator)
reads to generate a matching Next.js frontend (list/create/edit pages, table with
search/pagination/bulk-delete/CSV export, form, BFF proxy routes).

Separate code path from [`kematjaya/crud-maker-twig-bundle`](https://github.com/kematjaya0/crud-maker-twig-bundle) — use
that one instead if you're rendering server-side Twig views, not an API Platform + Next.js app.

## 1. Install

```
composer require kematjaya/crud-maker-api-bundle
```

Register the bundle in `config/bundles.php`:

```php
Kematjaya\CrudMakerBundle\Api\CrudMakerApiBundle::class => ['all' => true],
```

This bundle does **not** require `symfony/twig-bundle`, `symfony/form`, or
`symfony/security-csrf` — only what API Platform CRUD generation actually needs. You'll need
`api-platform/symfony` in your project separately (the maker checks for it and warns if it's
missing, but doesn't force it as a hard composer dependency).

## 2. Have an entity

The entity must already exist (`make:entity` or hand-written) with a repository class. Any of
these shapes work — the maker detects which one your entity uses and generates matching code:

- **constructor + `update()`**: `new Entity($field1, $field2)` at create time, `$entity->update($field1, $field2)` at edit time (params in the same order as the entity's Doctrine field mapping).
- **setters** (what a plain `make:entity`-scaffolded entity looks like): `new Entity()` then `$entity->setField1(...)->setField2(...)`, no constructor/`update()` needed.

If neither pattern is viable (e.g. a required-arg constructor with no matching `update()`, or a
setter missing for one of the fields), the command fails with a clear error instead of silently
generating broken code.

## 3. Run the maker

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

## 4. What gets written

- `src/Dto/{Entity}Input.php`, `src/Service/{Entity}Service(Interface).php`, `src/State/{Entity}WriteProcessor.php`
- `src/Controller/{Entity}ExportDataController.php` — CSV-export-data endpoint (`/api/{prefix}/export-data`, rate-limited, returns JSON — the frontend turns it into an actual CSV download)
- `src/State/CurrentUser{Entity}Extension.php` (only if `owner-property` is set) — scopes `GetCollection`/`Get` to the current user's rows
- `tests/Unit/Service/{Entity}ServiceTest.php` (only if `with-tests`)
- `crud-specs/{Entity}.json` — the frontend generator's input
- **The entity file itself**, if `write-entity-attributes` was confirmed: `#[ApiResource(operations: [...])]` and (if there are searchable fields) `#[ApiFilter(SearchFilter::class, ...)]`, added via AST manipulation ([`nikic/php-parser`](https://github.com/nikic/PHP-Parser)'s format-preserving printer — the same technique `make:entity` uses internally) so the rest of the file is untouched. If the entity already has one of these attributes, or `write-entity-attributes` was declined, the block is printed instead for you to paste in by hand.
- **`config/packages/framework.yaml`**: a `framework.rate_limiter.{limiter}` entry for the export endpoint, inserted via targeted text insertion (comments/formatting elsewhere in the file are left untouched). Idempotent — a limiter that already exists by that name is left alone.
- **`config/permissions/default.yaml`** (only if `with-access-control`): the permission item for this resource (`gated: true` + `actions: { create, edit, delete, bulk_delete, export_selected, export_all }`), added to (or created in) a `Master` section. If an item with that `key` already exists, only its missing pieces (`gated: true` and/or missing action keys) are filled in — existing fields, including custom action labels, are left untouched. A brand-new item gets a placeholder `href`/`icon` you'll want to review.

If either config file is missing, or its structure isn't recognized, the writer falls back to
printing the block for you to paste in by hand instead of failing the whole command — check the
printed next-steps either way.

## 5. Remaining manual steps (printed after generation)

- After the permission key is in place, run `bin/console kematjaya:access-control:sync` (if `with-access-control`).
- Review the Service if the entity's field order might not match the Input DTO's.

## 6. Generate the frontend (`@kematjaya/crud-ui-generator`)

The `crud-specs/{Entity}.json` sidecar from step 4 is consumed by
[`@kematjaya/crud-ui-generator`](https://www.npmjs.com/package/@kematjaya/crud-ui-generator), a
Next.js CRUD frontend generator published to npm — source lives in
[`js/`](https://github.com/kematjaya0/crud-maker-bundle/tree/master/js) of the
`kematjaya/crud-maker-bundle` monorepo (a separate npm package, not composer/PHP — this bundle
only produces the spec JSON it reads). It's a dev-time code generator (like Plop/Hygen), not a
runtime component library.

### Prerequisites

It targets projects that already follow this ecosystem's Next.js conventions — running it
against a project missing these pieces produces files that don't compile until you add them:

- `@kematjaya/bootstrap-ui-kit` for `ListPageCard`/`TextField`/`Button`/etc.
- `@kematjaya/access-control-ui` for `usePermissions()`
- `src/lib/http.ts`, `src/lib/bff.ts` (BFF proxy helpers — `authedBackend`, `validateOrigin`, `parseJson`, `jsonProblem`)
- `src/lib/permissions.ts` exporting `requirePermission()`
- `src/types/api.ts` + `src/types/api.generated.ts` (OpenAPI types via `openapi-typescript`)

### Install & run

No separate install needed for one-off use — `npx` fetches it on demand:

```
cd ../frontend   # or wherever the Next.js project lives

# 1. regenerate OpenAPI types — must run AFTER step 5's #[ApiResource]/#[ApiFilter] are in
#    place on the backend entity, so the new resource's paths/schemas are included
npm run api:types

# 2. generate the frontend CRUD (list/create/edit pages, table, form, BFF proxy routes)
npx @kematjaya/crud-ui-generator ../backend/crud-specs/{Entity}.json --src src

# 3. format the generated files (not run through Prettier automatically)
npm run format
```

If you'd rather install it as a persistent dev dependency instead of using `npx` each time:

```
npm install --save-dev @kematjaya/crud-ui-generator
npx crud-ui-generate ../backend/crud-specs/{Entity}.json --src src
```

### What gets generated

Per entity (skipped if the file already exists — safe to re-run):

- `app/dashboard/{entities}/page.tsx`, `new/page.tsx`, `[id]/edit/page.tsx`
- `components/{entities}/{Entity}Table.tsx`, `{Entity}Form.tsx`, `use{Entities}Export.ts`
- `lib/{entities}-query.ts`, `lib/{entities}-csv.ts`
- `app/api/{entities}/route.ts`, `[id]/route.ts`, `export/route.ts` (BFF proxy)

Shared, entity-agnostic UI primitives (written once, reused by every entity):
`components/crud/DeleteConfirmModal.tsx`, `BulkActionsBar.tsx`, `PaginationBar.tsx`,
`SearchPanel.tsx`, `ExportAllButton.tsx`.

Appended to (multi-entity, idempotent — each entity gets one marker-guarded block):
`lib/api-shapes.ts`, `lib/schemas.ts` (a Zod schema per entity), `types/api.ts`.

### Caveats

- **Id type** comes from the spec's `idType` (`uuid`/`int`/`string`, read off the entity's actual id column) — older spec files without it default to `uuid`.
- **List search only wires up the first `searchable` field** even if several are marked searchable in the spec (the export endpoint itself ORs across all of them).
- **`textarea` fields are skipped** from the table/CSV export (long text) — everything else (`text`/`number`/`boolean`) becomes a column.
- **Getters are assumed** on the entity/generated types, in the conventional `get{Field}()` shape.

See [`js/README.md`](https://github.com/kematjaya0/crud-maker-bundle/tree/master/js) in the
monorepo (or the [npm page](https://www.npmjs.com/package/@kematjaya/crud-ui-generator)) for the
full, up-to-date reference — this section may drift from a later published npm version.

## Overriding the generator templates

```yaml
# config/packages/crud_generator.yaml
crud_maker_api:
    templates:
        path: '%kernel.project_dir%/generator'
```

Custom templates are looked up first, falling back to the bundle's own skeletons — you only
need to override the ones you actually want to change.
