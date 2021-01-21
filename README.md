# helloworld
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

thank to:
- Filter type provide by https://github.com/lexik/LexikFormFilterBundle
- pagination provide by https://github.com/KnpLabs/KnpPaginatorBundle
- Base CRUD by: https://github.com/symfony/maker-bundle
