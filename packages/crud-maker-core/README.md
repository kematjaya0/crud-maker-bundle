# kematjaya/crud-maker-core

Shared base class (`Kematjaya\CrudMakerBundle\Renderer\AbstractRenderer`) used by
[`kematjaya/crud-maker-twig-bundle`](https://github.com/kematjaya0/crud-maker-twig-bundle) and
[`kematjaya/crud-maker-api-bundle`](https://github.com/kematjaya0/crud-maker-api-bundle) to
resolve skeleton template paths (bundled skeletons + an optional app-level override directory).

## Install

You normally don't require this directly — it's pulled in automatically as a dependency of
whichever maker bundle you install:

```
composer require kematjaya/crud-maker-twig-bundle
# and/or
composer require kematjaya/crud-maker-api-bundle
```

This package has no Symfony bundle of its own (no class to register in `config/bundles.php`)
and defines no console commands — it's a plain library dependency, not something you interact
with directly.
