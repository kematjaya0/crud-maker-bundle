# kematjaya/crud-maker-twig-bundle

Symfony MakerBundle commands that generate a server-rendered CRUD (Symfony Form + Twig +
Bootstrap: controller, views, filter type) for an *existing* Doctrine entity.

Older, server-rendered code path — see [`kematjaya/crud-maker-api-bundle`](https://github.com/kematjaya0/crud-maker-api-bundle)
instead if you're building an API Platform + Next.js frontend.

## 1. Install

```
composer require kematjaya/crud-maker-twig-bundle
```

Register the bundle in `config/bundles.php`:

```php
Kematjaya\CrudMakerBundle\Twig\CrudMakerTwigBundle::class => ['all' => true],
```

Requires `symfony/twig-bundle`, `symfony/form`, `symfony/security-csrf` and
`doctrine/doctrine-bundle` in your project (all declared as dependencies, so a plain
`composer require` pulls them in if missing).

## 2. One-time setup

```
php bin/console make:kmj-install
```

| Argument | Meaning |
|---|---|
| `theme` | View theme, decides the KnpPaginatorBundle template and Twig form theme: `bootstrap-3`, `bootstrap-4`, or `bootstrap-5` (default) |

Idempotent — safe to re-run (e.g. after switching theme). It:

- Creates `config/packages/knp_paginator.yaml` if it doesn't exist yet, pointed at the
  pagination template matching your theme.
- Checks `config/packages/twig.yaml`'s `form_themes` against your theme and fixes it if it's
  missing or set to a different bootstrap version (rewritten via the YAML component when a
  change is needed — comments in that file are not preserved; left alone otherwise).
- Registers `SpiriitFormFilterBundle` and `KnpPaginatorBundle` in `config/bundles.php` as
  `['all' => true]` if they're missing or scoped to `dev`/`test` only — the controllers this
  bundle generates use them at runtime, in every environment, not just as dev tooling.

## 3. Have an entity

The entity must already exist (`make:entity` or hand-written) with a repository class.

## 4. Generate a filter form (optional, before step 5 if you want `include-filter`)

```
php bin/console make:kmj-filter
```

| Argument | Meaning |
|---|---|
| `name` | Filter class name (e.g. `NoteFilterType`) |
| `bound-class` | Entity/model the filter form binds to — autocompletes, blank for none |

Uses [SpiriitLabs form filter bundle](https://github.com/spiriitlabs/form-filter-bundle) filter
types under the hood. Each field's filter type is inferred from its Doctrine column type
(`boolean` → `BooleanFilterType`, `integer`/`decimal`/`float` → `NumberFilterType`,
`date`/`datetime` → `DateFilterType`/`DateTimeFilterType`, associations → `EntityFilterType`,
everything else → `TextFilterType`). If `friendsofphp/php-cs-fixer` is installed in your
project, the generated file is auto-formatted right after it's written.

## 5. Generate the CRUD

```
php bin/console make:kmj-crud
```

| Argument | Meaning |
|---|---|
| `entity-class` | The entity to generate CRUD for — autocompletes |
| `namespace` | Additional controller namespace segment — blank for default |
| `include-filter` | Wire in the filter type generated in step 4? (y/n) |
| `modal-form` | Render create/edit as a modal instead of a full page? (y/n) |
| `theme` | View theme: `bootstrap-3`, `bootstrap-4`, or `bootstrap-5` (default) |

Non-interactively:

```
php bin/console make:kmj-crud Note "" y n bootstrap-5 --no-interaction
```

### What gets written

- `src/Controller/{Entity}Controller.php` — index/create/edit/show/delete actions with
  pagination ([KnpPaginatorBundle](https://github.com/KnpLabs/KnpPaginatorBundle)), plus a
  `bulk-execute` action (bulk delete and CSV export for the rows selected in the index table —
  same UI pattern as the filter form: a checkbox column, an action dropdown, and a CSV export
  auto-formatted for Excel — semicolon-delimited with a UTF-8 BOM, see step 4). If
  `friendsofphp/php-cs-fixer` is installed in your project, the generated controller is
  auto-formatted right after it's written.
- `templates/{entity}/index.html.twig`, `form.html.twig`, `show.html.twig`,
  `_delete_form.html.twig`, `_filters.html.twig`, `_max_per_page.html.twig` — matching the
  chosen theme (`bootstrap-4`/`bootstrap-5` include the bulk-action toolbar and its JS;
  `bootstrap-3` doesn't yet)

## 6. If using `modal-form`

Add this to your base template:

```twig
<div class="modal fade" id="myModal">
    <div class="modal-content" id="modal-dialog">
        <div style="text-align: center"><img src="{{ asset('bundles/basecontroller/images/loading.gif') }}" style="width: 20px"/></div>
    </div>
</div>
```

...and make sure jQuery is loaded on the page.

## Overriding the generator templates

```yaml
# config/packages/crud_generator.yaml
# assume your custom templates live in root-project/generator
crud_maker_twig:
    templates:
        path: '%kernel.project_dir%/generator'
```

Custom templates are looked up first, falling back to the bundle's own skeletons — you only
need to override the ones you actually want to change.

## Credits

- Filter type provided by [LexikFormFilterBundle](https://github.com/lexik/LexikFormFilterBundle) / [SpiriitLabs](https://github.com/spiriitlabs/form-filter-bundle)
- Pagination provided by [KnpPaginatorBundle](https://github.com/KnpLabs/KnpPaginatorBundle)
- Base CRUD generation by [symfony/maker-bundle](https://github.com/symfony/maker-bundle)
