# @kematjaya/crud-ui-generator

Generator frontend CRUD untuk Next.js yang membaca sidecar `crud-specs/{Entity}.json` yang
ditulis oleh `make:kmj-api-crud` dari
[kematjaya/crud-maker-bundle](https://github.com/kematjaya0/crud-maker-bundle), lalu
menghasilkan halaman, komponen, dan BFF routes dengan bentuk yang sama seperti fitur CRUD yang
ditulis manual (halaman list/create/edit, tabel dengan search/pagination/bulk-delete/export CSV,
form, BFF proxy routes).

Ini adalah code generator yang dijalankan saat development (seperti Plop/Hygen), bukan library
komponen runtime. Generator ini menyasar project yang sudah mengikuti konvensi Next.js di
monorepo ini:

- `@kematjaya/bootstrap-ui-kit` untuk `ListPageCard`/`TextField`/`Button`/dll.
- `@kematjaya/access-control-ui` untuk `usePermissions()`
- `src/lib/http.ts`, `src/lib/bff.ts` (helper proxy BFF — `authedBackend`, `validateOrigin`, `parseJson`, `jsonProblem`)
- `src/lib/permissions.ts` yang meng-export `requirePermission()`
- `src/types/api.ts` + `src/types/api.generated.ts` (tipe dari OpenAPI via `openapi-typescript`)

Ini bukan scaffolder Next.js serba-guna — menjalankannya pada project yang belum punya
komponen-komponen di atas akan menghasilkan file yang tidak bisa di-compile sampai Anda
menambahkannya.

## Instalasi

Tidak perlu instalasi terpisah untuk pemakaian sekali pakai — `npx` akan mengambilnya otomatis:

```
npx @kematjaya/crud-ui-generator <spec-path> [--src <dir>]
```

Kalau ingin dipasang sebagai dev dependency permanen alih-alih `npx` setiap kali:

```
npm install --save-dev @kematjaya/crud-ui-generator
```

## Cara pakai

```
npx @kematjaya/crud-ui-generator <spec-path> [--src <dir>]
```

- `<spec-path>` — path ke file `crud-specs/{Entity}.json` yang ditulis oleh `make:kmj-api-crud`.
- `--src <dir>` — direktori `src/` project frontend tempat hasil generate ditulis (default: `src`).

Contoh, dijalankan dari root project frontend, dengan backend sebagai direktori bertetangga:

```
npx @kematjaya/crud-ui-generator ../backend/crud-specs/Note.json --src src
```

## Apa saja yang di-generate

Per entity (dilewati kalau file sudah ada — aman dijalankan ulang):

- `app/dashboard/{entities}/page.tsx`, `new/page.tsx`, `[id]/edit/page.tsx`
- `components/{entities}/{Entity}Table.tsx`, `{Entity}Form.tsx`, `use{Entities}Export.ts`
- `lib/{entities}-query.ts`, `lib/{entities}-csv.ts`
- `app/api/{entities}/route.ts`, `[id]/route.ts`, `export/route.ts` (BFF proxy)

Primitive UI yang dipakai bersama, tidak spesifik ke satu entity (ditulis sekali, dipakai ulang
oleh semua entity):

- `components/crud/DeleteConfirmModal.tsx`, `BulkActionsBar.tsx`, `PaginationBar.tsx`,
  `SearchPanel.tsx`, `ExportAllButton.tsx`

Ditambahkan ke (multi-entity, idempotent — tiap entity dapat satu blok yang dijaga marker):

- `lib/api-shapes.ts` — type guard `is{Entity}`/`is{Entity}Collection`
- `lib/schemas.ts` — satu Zod schema per entity
- `types/api.ts` — tipe hasil turunan dari `paths`/`components` OpenAPI

## Asumsi / catatan penting

- **Tipe id diambil dari `idType` di spec** (`uuid`/`int`/`string`, ditulis oleh
  `ApiCrudRenderer::detectIdType()` berdasarkan kolom id sebenarnya di entity) — `validId()` di
  `[id]/route.ts` hasil generate dan type guard `is{Entity}` di `api-shapes.ts` dibuat
  menyesuaikan. Spec lama tanpa `idType` default ke `uuid`.
- **Panel filter di halaman list mencakup semua field `searchable` kecuali `textarea`, `date`,
  dan `datetime`.** `filterableFields()` di `naming.ts` merender satu kontrol per field yang
  tersisa — input text/number (strategi ApiPlatform `SearchFilter` `'partial'`/`'exact'`) dan
  select boolean Any/Yes/No (`'exact'`) — semuanya digabung dengan AND dalam satu query list
  (satu query param per properti, didukung satu
  `#[ApiFilter(SearchFilter::class, properties: [...])]` di entity). Tombol Filter yang bisa
  dilipat di tabel membuka panel `crud-filter-collapse` berisi komponen `FilterPanel` hasil
  generate, dan hanya muncul kalau minimal satu field bisa difilter. Field `searchable` yang
  dikecualikan akan dapat catatan next-steps di `cli.ts` — belum ada kontrol filter rentang
  tanggal (iterasi berikutnya), dan teks panjang memang bukan input filter yang masuk akal.
- **Export CSV ("Export all") hanya mencerminkan nilai field filterable pertama saat ini**,
  dikirim sebagai satu parameter `search` (OR-search) yang di backend dicocokkan ke *semua*
  field `searchable` (termasuk textarea) — kontrak yang berbeda dan lebih longgar dari filter
  AND-per-properti yang presisi di halaman list. Field filter lain belum mempersempit hasil
  export.
- **Field `date`/`datetime` adalah field form dan kolom tabel/CSV sungguhan.** Didukung
  `DateField`/`DateTimeField` dari `ui-kit` (native `<input type="date">`/
  `type="datetime-local">`). Field `date` bolak-balik sebagai string `"Y-m-d"` polos — maker di
  sisi backend menulis mapping serializer Symfony `config/serializer/{Entity}.yaml` yang
  mengunci format itu (kalau tidak, `DateTimeNormalizer` default Symfony akan menghasilkan
  datetime RFC3339 lengkap dengan jam/timezone yang tidak seharusnya ada untuk kolom
  date-only). Field `datetime` tetap pakai format wire RFC3339 default; form hasil generate
  mengonversi ke/dari string local-wall-clock `<input type="datetime-local">` lewat
  `src/lib/datetime.ts` (ditulis sekali, dipakai bersama semua entity) di boundary
  `reset()`/`submit()` — parsing local-time dari constructor `Date` membuat konversi ini aman
  terhadap timezone.
- **Tabel/export CSV melewati field `textarea`** (teks panjang), meniru fitur Notes yang ditulis
  manual (menampilkan `title`, bukan `body`). Selain itu (`text`/`number`/`boolean`/`date`/
  `datetime`) menjadi kolom.
- **Getter diasumsikan ada** pada entity/tipe hasil generate, dalam bentuk konvensional
  `get{Field}()` / properti camelCase yang dipakai di seluruh boilerplate ini.
- **`npm run api:types` harus dijalankan lebih dulu** (setelah atribut `#[ApiResource]`/
  `#[ApiFilter]` backend ditambahkan — lihat next-steps yang dicetak `make:kmj-api-crud`) supaya
  lookup `paths['/api/{entities}']` / `components['schemas'][...]` di `src/types/api.ts`
  berhasil. Lookup ini berdasarkan `apiResourcePath` (catatan literal URI ApiPlatform
  sebenarnya milik entity, dari spec), **bukan** `permissionPrefix` — kalau `#[ApiResource]`
  entity memakai `uriTemplate` custom yang tidak cocok dengan `apiResourcePath`, atau project
  meng-override `api_platform.path_segment_name_generator` dari default-nya, perbaiki lookup itu
  secara manual.
- **Panggilan BFF ke backend (`app/api/{entities}/route.ts`, `[id]/route.ts`,
  `build{Entities}BackendPath` di `lib/{entities}-query.ts`) selalu memakai `apiResourcePath`
  dari spec, tidak pernah `permissionPrefix`.** `permissionPrefix` hanya menamai route/folder
  frontend sendiri dan permission key — nilainya bebas (free text) dan bisa berbeda dari URI
  plural asli ApiPlatform (misalnya entity `Category` yang di-generate dengan
  `permissionPrefix: "category"` tetap saja disajikan backend di `/api/categories`).
  `apiResourcePath` ditulis oleh `make:kmj-api-crud` sebagai `pluralize(tableize($shortName))`,
  meniru persis `path_segment_name_generator` default ApiPlatform — frontend wajib mengikuti
  nilai ini apa adanya, karena backend digenerate lebih dulu dan menjadi satu-satunya sumber
  kebenaran untuk URL miliknya sendiri. Spec lama dari sebelum field ini ada akan gagal dimuat
  dengan pesan error yang jelas; tambahkan `"apiResourcePath"` secara manual (atau generate
  ulang).
- File hasil generate tidak melewati Prettier — jalankan `npm run format` setelahnya.

## Development

```
npm install
npm run build   # tsc -> dist/
```
