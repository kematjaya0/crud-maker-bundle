# @kematjaya/crud-ui-generator

Next.js CRUD frontend generator that reads the `crud-specs/{Entity}.json` sidecar written by
[kematjaya/crud-maker-bundle](https://github.com/kematjaya0/crud-maker-bundle)'s
`make:kmj-api-crud`, and generates pages, components, and BFF routes matching the shape of a
hand-written CRUD feature (list/create/edit pages, table with search/pagination/bulk-delete/CSV
export, form, BFF proxy routes).

This is a dev-time code generator (like Plop/Hygen), not a runtime component library. It targets
projects that already follow this monorepo's Next.js conventions:

- `@kematjaya/bootstrap-ui-kit` for `ListPageCard`/`TextField`/`Button`/etc.
- `@kematjaya/access-control-ui` for `usePermissions()`
- `src/lib/http.ts`, `src/lib/bff.ts` (BFF proxy helpers — `authedBackend`, `validateOrigin`, `parseJson`, `jsonProblem`)
- `src/lib/permissions.ts` exporting `requirePermission()`
- `src/types/api.ts` + `src/types/api.generated.ts` (OpenAPI types via `openapi-typescript`)

It is not a general-purpose Next.js scaffolder — running it against a project that doesn't already
have those pieces will produce files that don't compile until you add them.

## Usage

```
npx @kematjaya/crud-ui-generator <spec-path> [--src <dir>]
```

- `<spec-path>` — path to the `crud-specs/{Entity}.json` file written by `make:kmj-api-crud`.
- `--src <dir>` — the frontend project's `src/` directory to generate into (default: `src`).

Example, run from the frontend project root, with the backend as a sibling directory:

```
npx @kematjaya/crud-ui-generator ../backend/crud-specs/Note.json --src src
```

## What it generates

Per entity (skipped if the file already exists — safe to re-run):

- `app/dashboard/{entities}/page.tsx`, `new/page.tsx`, `[id]/edit/page.tsx`
- `components/{entities}/{Entity}Table.tsx`, `{Entity}Form.tsx`, `use{Entities}Export.ts`
- `lib/{entities}-query.ts`, `lib/{entities}-csv.ts`
- `app/api/{entities}/route.ts`, `[id]/route.ts`, `export/route.ts` (BFF proxy)

Shared, entity-agnostic UI primitives (written once, reused by every entity):

- `components/crud/DeleteConfirmModal.tsx`, `BulkActionsBar.tsx`, `PaginationBar.tsx`,
  `SearchPanel.tsx`, `ExportAllButton.tsx`

Appended to (multi-entity, idempotent — each entity gets one marker-guarded block):

- `lib/api-shapes.ts` — `is{Entity}`/`is{Entity}Collection` type guards
- `lib/schemas.ts` — a Zod schema per entity
- `types/api.ts` — types derived from the OpenAPI-generated `paths`/`components`

## Assumptions / caveats

- **Id type comes from the spec's `idType`** (`uuid`/`int`/`string`, written by `ApiCrudRenderer::detectIdType()` off the entity's actual id column) — `validId()` in the generated `[id]/route.ts` and the `is{Entity}` type guard in `api-shapes.ts` are generated to match. Older spec files without `idType` default to `uuid`.
- **The list search box only searches one field.** If more than one field is marked
  `searchable` in the spec, the list view (backed by ApiPlatform's `SearchFilter`, one query
  param per property) only wires up the first one. The export endpoint ORs across all of them.
- **The table/CSV export skip `textarea` fields** (long text), mirroring the hand-written Notes
  feature (shows `title`, not `body`). Everything else (`text`/`number`/`boolean`) becomes a
  column.
- **Getters are assumed on the entity/generated types** in the conventional `get{Field}()` /
  camelCase-property shape used throughout this boilerplate.
- **`npm run api:types` must be run first** (after adding the backend's `#[ApiResource]`/
  `#[ApiFilter]` attributes — see `make:kmj-api-crud`'s printed next-steps) so
  `src/types/api.ts`'s `paths['/api/{entities}']` / `components['schemas'][...]` lookups resolve.
  If the entity's `#[ApiResource]` uses a custom `uriTemplate` that doesn't match
  `permissionPrefix`, fix those lookups by hand.
- Generated files aren't run through Prettier — run `npm run format` afterwards.

## Development

```
npm install
npm run build   # tsc -> dist/
```
