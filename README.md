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

## Credits

- Filter type provided by [LexikFormFilterBundle](https://github.com/lexik/LexikFormFilterBundle) / [SpiriitLabs](https://github.com/spiriitlabs/form-filter-bundle)
- Pagination provided by [KnpPaginatorBundle](https://github.com/KnpLabs/KnpPaginatorBundle)
- Base CRUD generation by [symfony/maker-bundle](https://github.com/symfony/maker-bundle)
