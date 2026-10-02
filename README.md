# Bootstrap CRUD Generator
CRUD generator base on Symfony Maker Bundle

Branch `6.4`: Symfony 6.4, PHP >= 8.1, symfony/maker-bundle >= 1.50,
kematjaya/base-controller-bundle 6.4 (SpiriitLabs form filter bundle, KnpPaginator).

1. installation
```
composer require --dev kematjaya/crud-maker-bundle
```
2. Generate Filter Form
```
php bin/console make:kmj-filter
```
3. Generate CRUD include form, filter and pagination
```
php bin/console make:kmj-crud
# non interaktif: entity, namespace ("-" = tanpa namespace), filter, modal, theme
php bin/console make:kmj-crud Product - yes no bootstrap-5
```
   - theme: `bootstrap-3`, `bootstrap-4` (default), `bootstrap-5`
   - namespace tambahan (mis. `Admin`) ikut di nama route (`app_admin_product_*`) dan path (`/admin/product`)
   - filter dibuat dari tipe kolom doctrine: text, number, boolean, date range, relasi (EntityFilterType);
     kolom json/array dilewati
4. Generate functional test
```
php bin/console make:kmj-functional-test
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

- configuration
```
# config/packages/crud_maker.yaml
crud_maker:
    entity:
        namespace_prefix: 'Entity\'
        suffix: ''
    filter:
        namespace_prefix: 'Filter\'
        suffix: 'FilterType'
    templates:
        # template generator sendiri, mis. root-project/generator/crud/views/my-theme/index.tpl.php
        path: '%kernel.project_dir%/generator'
        theme: bootstrap-4
```

## Test
```
LOCAL_PACKAGES="base-controller-bundle@6.4.0" sh ../test.sh crud-maker-bundle all
```

thank to:
- Filter type provide by https://github.com/SpiriitLabs/form-filter-bundle
- pagination provide by https://github.com/KnpLabs/KnpPaginatorBundle
- Base CRUD by: https://github.com/symfony/maker-bundle
