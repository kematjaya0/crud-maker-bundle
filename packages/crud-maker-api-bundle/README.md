# kematjaya/crud-maker-api-bundle

Command Symfony MakerBundle yang men-generate sisi tulis (write-side) dari sebuah resource
[API Platform](https://api-platform.com/) (Input DTO, Service, WriteProcessor, endpoint export
CSV) untuk entity Doctrine yang *sudah ada*, plus sidecar `crud-specs/{Entity}.json` yang dibaca
[`@kematjaya/crud-ui-generator`](https://www.npmjs.com/package/@kematjaya/crud-ui-generator)
untuk men-generate frontend Next.js yang sepadan (halaman list/create/edit, tabel dengan
search/pagination/bulk-delete/export CSV, form, BFF proxy routes).

Jalur kode terpisah dari
[`kematjaya/crud-maker-twig-bundle`](https://github.com/kematjaya0/crud-maker-twig-bundle) —
pakai bundle itu kalau Anda merender view Twig sisi server, bukan aplikasi API Platform +
Next.js.

## 1. Instalasi

```
composer require kematjaya/crud-maker-api-bundle
```

Daftarkan bundle-nya di `config/bundles.php`:

```php
Kematjaya\CrudMakerBundle\Api\CrudMakerApiBundle::class => ['all' => true],
```

Bundle ini **tidak** membutuhkan `symfony/twig-bundle`, `symfony/form`, atau
`symfony/security-csrf` — hanya yang benar-benar dibutuhkan untuk generate CRUD API Platform.
Anda tetap perlu `api-platform/symfony` di project secara terpisah (maker akan mengecek dan
memberi peringatan kalau tidak ada, tapi tidak memaksanya sebagai dependency composer wajib).

## 2. Siapkan entity-nya

Entity-nya harus sudah ada (`make:entity` atau ditulis manual) beserta repository class-nya.
Kedua bentuk berikut didukung — maker mendeteksi bentuk mana yang dipakai entity Anda dan
men-generate kode yang sesuai:

- **constructor + `update()`**: `new Entity($field1, $field2)` saat create, lalu
  `$entity->update($field1, $field2)` saat edit (urutan parameter sama dengan urutan mapping
  field Doctrine di entity).
- **setter** (bentuk umum hasil `make:entity` polos): `new Entity()` lalu
  `$entity->setField1(...)->setField2(...)`, tidak perlu constructor/`update()`.

Kalau tidak ada pola yang cocok (misalnya constructor dengan argumen wajib tapi tanpa `update()`
yang sepadan, atau ada field yang tidak punya setter), command akan gagal dengan pesan error
yang jelas alih-alih diam-diam men-generate kode yang rusak.

## 3. Jalankan maker-nya

```
php bin/console make:kmj-api-crud
```

Command ini akan menanyakan:

| Pertanyaan | Maksud |
|---|---|
| `entity-class` | Entity yang mau dibuatkan CRUD-nya (ada autocomplete) |
| `owner-property` | Properti relasi yang membatasi baris data ke user saat ini (mis. `owner`) — isi `-` kalau tidak ada |
| `permission-prefix` | Prefix untuk permission key / segmen URL frontend (mis. `notes` → `notes.create`, `/dashboard/notes`) — isi `-` untuk ditebak dari nama entity. **Catatan: ini hanya label bebas untuk permission key dan route frontend — bukan URL API sebenarnya.** URL resource API Platform yang sesungguhnya selalu berupa `pluralize(tableize($shortName))` dan ditulis apa adanya ke field `apiResourcePath` di spec JSON, terlepas dari nilai `permission-prefix` yang Anda pilih di sini. |
| `with-access-control` | Gerbangi create/edit/delete/export lewat `isGranted()` dari [`kematjaya/access-control-bundle`](https://github.com/kematjaya0/access-control-bundle)? |
| `with-tests` | Generate test PHPUnit untuk Service-nya? |
| `searchable-fields` | Nama field, dipisah koma, yang bisa dicari dari frontend (mis. `title`) — isi `-` kalau tidak ada |
| `write-entity-attributes` | Tambahkan `#[ApiResource]`/`#[ApiFilter]` ke file entity secara otomatis? (lihat di bawah) |

Non-interaktif:

```
php bin/console make:kmj-api-crud Note owner notes true true title true --no-interaction
```

## 4. Apa saja yang ditulis

- `src/Dto/{Entity}Input.php`, `src/Service/{Entity}Service(Interface).php`, `src/State/{Entity}WriteProcessor.php`
- `src/Controller/{Entity}ExportDataController.php` — endpoint data export CSV
  (`/api/{prefix}/export-data`, dibatasi rate limiter, mengembalikan JSON — frontend yang
  mengubahnya jadi file CSV yang bisa diunduh)
- `src/State/CurrentUser{Entity}Extension.php` (hanya kalau `owner-property` diisi) — membatasi
  `GetCollection`/`Get` ke baris milik user saat ini
- `tests/Unit/Service/{Entity}ServiceTest.php` (hanya kalau `with-tests` dipilih)
- `crud-specs/{Entity}.json` — input untuk generator frontend, termasuk `apiResourcePath` (URI
  ApiPlatform sebenarnya untuk resource ini, mis. `/api/categories`) yang **wajib** dibaca apa
  adanya oleh `@kematjaya/crud-ui-generator`, bukan diturunkan/ditebak dari `permissionPrefix`
- **File entity itu sendiri**, kalau `write-entity-attributes` dikonfirmasi:
  `#[ApiResource(operations: [...])]` dan (kalau ada field searchable)
  `#[ApiFilter(SearchFilter::class, ...)]`, ditambahkan lewat manipulasi AST (printer
  format-preserving dari [`nikic/php-parser`](https://github.com/nikic/PHP-Parser) — teknik yang
  sama dipakai `make:entity` secara internal) sehingga bagian lain file tidak tersentuh. Kalau
  entity sudah punya salah satu atribut ini, atau `write-entity-attributes` ditolak, blok
  kodenya akan dicetak saja untuk Anda tempel manual.
- **`config/packages/framework.yaml`**: entri `framework.rate_limiter.{limiter}` untuk endpoint
  export, disisipkan lewat penyisipan teks bertarget (komentar/format lain di file tidak
  tersentuh). Idempotent — limiter dengan nama yang sudah ada dibiarkan apa adanya.
- **`config/permissions/default.yaml`** (hanya kalau `with-access-control`): item permission
  untuk resource ini (`gated: true` + `actions: { create, edit, delete, bulk_delete,
  export_selected, export_all }`), ditambahkan ke (atau dibuat di) bagian `Master`. Kalau item
  dengan `key` tersebut sudah ada, hanya bagian yang belum ada yang dilengkapi (`gated: true`
  dan/atau action key yang hilang) — field lain, termasuk label action custom, tidak tersentuh.
  Item baru mendapat `href`/`icon` placeholder yang perlu Anda tinjau ulang.

Kalau salah satu file config di atas tidak ditemukan, atau strukturnya tidak dikenali, writer
akan mencetak bloknya saja untuk Anda tempel manual, bukan menggagalkan seluruh command — tetap
cek next-steps yang dicetak di kedua kondisi.

## 5. Langkah manual yang tersisa (dicetak setelah generate)

- Setelah permission key ada, jalankan `bin/console kematjaya:access-control:sync` (kalau
  `with-access-control`).
- Tinjau ulang Service kalau urutan field entity mungkin tidak cocok dengan Input DTO.

## 6. Generate frontend-nya (`@kematjaya/crud-ui-generator`)

Sidecar `crud-specs/{Entity}.json` dari langkah 4 dikonsumsi oleh
[`@kematjaya/crud-ui-generator`](https://www.npmjs.com/package/@kematjaya/crud-ui-generator),
generator frontend CRUD untuk Next.js yang dipublikasikan ke npm — sumbernya ada di
[`js/`](https://github.com/kematjaya0/crud-maker-bundle/tree/master/js) di monorepo
`kematjaya/crud-maker-bundle` (paket npm terpisah, bukan composer/PHP — bundle ini hanya
menghasilkan spec JSON yang dibacanya). Generator ini dijalankan saat development (seperti
Plop/Hygen), bukan library komponen runtime.

### Prasyarat

Generator ini menyasar project yang sudah mengikuti konvensi Next.js di ekosistem ini —
menjalankannya pada project yang belum punya komponen-komponen berikut akan menghasilkan file
yang tidak bisa di-compile sampai Anda menambahkannya:

- `@kematjaya/bootstrap-ui-kit` untuk `ListPageCard`/`TextField`/`Button`/dll.
- `@kematjaya/access-control-ui` untuk `usePermissions()`
- `src/lib/http.ts`, `src/lib/bff.ts` (helper proxy BFF — `authedBackend`, `validateOrigin`, `parseJson`, `jsonProblem`)
- `src/lib/permissions.ts` yang meng-export `requirePermission()`
- `src/types/api.ts` + `src/types/api.generated.ts` (tipe dari OpenAPI via `openapi-typescript`)

### Instalasi & cara pakai

Tidak perlu instalasi terpisah untuk pemakaian sekali pakai — `npx` akan mengambilnya otomatis:

```
cd ../frontend   # atau lokasi project Next.js-nya

# 1. generate ulang tipe OpenAPI — HARUS dijalankan SETELAH #[ApiResource]/#[ApiFilter]
#    dari langkah 5 sudah terpasang di entity backend, supaya path/schema resource baru ikut masuk
npm run api:types

# 2. generate frontend CRUD-nya (halaman list/create/edit, tabel, form, BFF proxy routes)
npx @kematjaya/crud-ui-generator ../backend/crud-specs/{Entity}.json --src src

# 3. format file hasil generate (tidak otomatis lewat Prettier)
npm run format
```

Kalau lebih suka dipasang sebagai dev dependency permanen daripada `npx` setiap kali:

```
npm install --save-dev @kematjaya/crud-ui-generator
npx crud-ui-generate ../backend/crud-specs/{Entity}.json --src src
```

### Apa saja yang di-generate

Per entity (dilewati kalau file sudah ada — aman dijalankan ulang):

- `app/dashboard/{entities}/page.tsx`, `new/page.tsx`, `[id]/edit/page.tsx`
- `components/{entities}/{Entity}Table.tsx`, `{Entity}Form.tsx`, `use{Entities}Export.ts`
- `lib/{entities}-query.ts`, `lib/{entities}-csv.ts`
- `app/api/{entities}/route.ts`, `[id]/route.ts`, `export/route.ts` (BFF proxy)

Primitive UI bersama, tidak spesifik satu entity (ditulis sekali, dipakai ulang semua entity):
`components/crud/DeleteConfirmModal.tsx`, `BulkActionsBar.tsx`, `PaginationBar.tsx`,
`SearchPanel.tsx`, `ExportAllButton.tsx`.

Ditambahkan ke (multi-entity, idempotent — tiap entity dapat satu blok yang dijaga marker):
`lib/api-shapes.ts`, `lib/schemas.ts` (satu Zod schema per entity), `types/api.ts`.

### Catatan penting

- **Tipe id** diambil dari `idType` di spec (`uuid`/`int`/`string`, dibaca dari kolom id
  sebenarnya di entity) — spec lama tanpa field ini default ke `uuid`.
- **Search di halaman list hanya menyambungkan field `searchable` pertama** walaupun beberapa
  field ditandai searchable di spec (endpoint export sendiri meng-OR-kan semuanya).
- **Field `textarea` dilewati** dari tabel/export CSV (teks panjang) — selain itu
  (`text`/`number`/`boolean`) menjadi kolom.
- **Getter diasumsikan ada** pada entity/tipe hasil generate, dalam bentuk konvensional
  `get{Field}()`.
- **Panggilan BFF ke backend selalu memakai `apiResourcePath` dari spec, bukan
  `permissionPrefix`.** `permissionPrefix` cuma menamai route/folder frontend dan permission
  key — nilainya bebas dan bisa berbeda dari URI plural asli ApiPlatform (contoh nyata: entity
  `Category` yang di-generate dengan `permissionPrefix: "category"` tetap disajikan backend di
  `/api/categories`, bukan `/api/category`). Kalau ini tidak diikuti dengan benar, GET/POST/dst.
  dari frontend akan 404 walau kodenya terlihat benar — cek `apiResourcePath` di
  `crud-specs/{Entity}.json` kalau menemukan gejala ini.

Lihat [`js/README.md`](https://github.com/kematjaya0/crud-maker-bundle/tree/master/js) di
monorepo (atau [halaman npm](https://www.npmjs.com/package/@kematjaya/crud-ui-generator)) untuk
referensi lengkap dan paling baru — bagian ini bisa saja tertinggal dari versi npm yang lebih
baru.

## Meng-override template generator

```yaml
# config/packages/crud_generator.yaml
crud_maker_api:
    templates:
        path: '%kernel.project_dir%/generator'
```

Template custom dicari lebih dulu, baru jatuh kembali ke skeleton bawaan bundle — Anda hanya
perlu meng-override yang memang ingin diubah.
