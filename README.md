# Bootstrap CRUD Generator

CRUD generator based on Symfony MakerBundle, split into two independent flavors with unrelated
dependency needs:

- **[`kematjaya/crud-maker-twig-bundle`](packages/crud-maker-twig-bundle)** — `make:kmj-crud` /
  `make:kmj-filter`: server-rendered CRUD (Symfony Form + Twig + Bootstrap) for an existing
  Doctrine entity.
- **[`kematjaya/crud-maker-api-bundle`](packages/crud-maker-api-bundle)** — `make:kmj-api-crud`:
  the write-side of an API Platform resource (DTO, service, state processor) plus a frontend
  spec sidecar consumed by [`@kematjaya/crud-ui-generator`](js/) to scaffold a matching Next.js
  frontend.

Install and read the README of whichever one matches your project — full install steps, Symfony
project setup (bundle registration), command arguments/prompts, and generated-file breakdowns
are documented there, not duplicated here.

## Package layout

This repo is a monorepo of three composer packages, split by dependency — see `packages/`:

- [`kematjaya/crud-maker-core`](packages/crud-maker-core) — shared renderer base class, no Symfony bundle of its own, no direct install needed.
- [`kematjaya/crud-maker-twig-bundle`](packages/crud-maker-twig-bundle) — Twig+Form flavor.
- [`kematjaya/crud-maker-api-bundle`](packages/crud-maker-api-bundle) — API Platform+Next.js flavor.

`composer require kematjaya/crud-maker-bundle` (this root package) still works and pulls in both
flavors, for backward compatibility — new projects should require only the flavor they need.

Versioning: the umbrella package above is on its own version track (continuing its pre-split
7.x/8.x history); the three `packages/*` are versioned independently from each other's shared
`v1.x` track. See `scripts/release.sh` for the release process.

## Rilis / update tag

Ada dua jalur versioning yang terpisah, jadi cara tag-nya juga beda:

### Tiga package hasil split (`packages/*`)

`scripts/release.sh` men-subtree-split tiap package di `packages/` lalu push tag yang **sama**
ke ketiga mirror repo-nya sekaligus (core, twig-bundle, api-bundle), supaya versi yang tayang di
Packagist selalu match satu sama lain.

Syarat sebelum jalan: working tree bersih (commit/stash dulu perubahan), dan posisi di branch
`master`.

```bash
# jalankan dulu dengan --dry-run untuk lihat command apa saja yang akan dieksekusi
scripts/release.sh v1.2.0 --dry-run

# baru jalankan beneran kalau sudah yakin
scripts/release.sh v1.2.0
```

Format versi wajib `vX.Y.Z`. Script otomatis berhenti kalau tag tersebut sudah pernah dipakai di
salah satu mirror repo.

### Package umbrella (root repo ini)

Repo root (`kematjaya/crud-maker-bundle`, remote `origin`) **tidak** disentuh oleh
`scripts/release.sh` — dia punya track versi sendiri (lanjutan 7.x/8.x sebelum split). Tag manual
saja, hanya kalau `composer.json` di root ini memang berubah require-range-nya:

```bash
git tag 8.5.0
git push origin 8.5.0
```

## Credits

- Filter type provided by [LexikFormFilterBundle](https://github.com/lexik/LexikFormFilterBundle) / [SpiriitLabs](https://github.com/spiriitlabs/form-filter-bundle)
- Pagination provided by [KnpPaginatorBundle](https://github.com/KnpLabs/KnpPaginatorBundle)
- Base CRUD generation by [symfony/maker-bundle](https://github.com/symfony/maker-bundle)
