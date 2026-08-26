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

## 2. Have an entity

The entity must already exist (`make:entity` or hand-written) with a repository class.

## 3. Generate a filter form (optional, before step 4 if you want `include-filter`)

```
php bin/console make:kmj-filter
```

| Argument | Meaning |
|---|---|
| `name` | Filter class name (e.g. `NoteFilterType`) |
| `bound-class` | Entity/model the filter form binds to — autocompletes, blank for none |

Uses [SpiriitLabs form filter bundle](https://github.com/spiriitlabs/form-filter-bundle) filter
types under the hood.

## 4. Generate the CRUD

```
php bin/console make:kmj-crud
```

| Argument | Meaning |
|---|---|
| `entity-class` | The entity to generate CRUD for — autocompletes |
| `namespace` | Additional controller namespace segment — blank for default |
| `include-filter` | Wire in the filter type generated in step 3? (y/n) |
| `modal-form` | Render create/edit as a modal instead of a full page? (y/n) |
| `theme` | View theme: `bootstrap-3`, `bootstrap-4`, or `bootstrap-5` (default) |

Non-interactively:

```
php bin/console make:kmj-crud Note "" y n bootstrap-5 --no-interaction
```

### What gets written

- `src/Controller/{Entity}Controller.php` — index/create/edit/show/delete actions with
  pagination ([KnpPaginatorBundle](https://github.com/KnpLabs/KnpPaginatorBundle))
- `templates/{entity}/index.html.twig`, `form.html.twig`, `show.html.twig`,
  `_delete_form.html.twig`, `_filters.html.twig`, `_max_per_page.html.twig` — matching the
  chosen theme

## 5. If using `modal-form`

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
