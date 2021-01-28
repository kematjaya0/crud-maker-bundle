# Bootstrap CRUD Generator
CRUD generator base on Symfony Maker Bundle
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
