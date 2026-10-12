# Tessera Exports

The report export engine: Excel (streamed) and PDF (Arabic and English), small exports while the user waits, big ones in the background, and a page that lists finished files.

A module for [Tessera](https://github.com/Ahmad-Chebbo/tessera-kit). It needs a project made from the [Tessera starter](https://github.com/Ahmad-Chebbo/tessera-starter) (the engine, the core and the interface kit).

## Install

```bash
php artisan module:add exports
bun run build
```

`module:add` requires `ahmad-chebbo/tessera-exports`, runs the migrations and gives the roles of every company the new permissions. Without it:

```bash
composer config repositories.tessera composer https://ahmad-chebbo.github.io/tessera-kit
composer require ahmad-chebbo/tessera-exports
php artisan migrate
php artisan modules:sync
bun run build
```

## What it needs

- Other Tessera modules: none besides the core.
- Composer packages: `mpdf/mpdf`, `openspout/openspout`.

## What it adds

| Plan feature | Permissions |
|---|---|
| Report exports (Excel & PDF) | export reports |

Menu entries: Exports.

- Other modules offer reports by returning report classes from their provider's `exports()` hook; this module finds and runs them. It contains no report of its own.
- Big exports use the `exports` queue, so run a worker: `php artisan queue:work --queue=exports,default`.

## Notes

- Limits are in `config.php` and can be changed with `EXPORTS_SYNC_LIMIT`, `EXPORTS_PDF_SYNC_LIMIT`, `EXPORTS_PDF_MAX_ROWS`, `EXPORTS_XLSX_MAX_ROWS` and `EXPORTS_CHUNK_SIZE`. Finished files are deleted after a number of days set in the same file.

## Turn it off without removing it

Set `MODULES_ENABLED` in `.env` to the modules you want, for example `MODULES_ENABLED=catalog,sales`. Core modules are always on. A module that is left out disappears from the menu, the routes and the plan features.

## Develop

The module lives in `packages/modules/exports` of the monorepo, with its tests in `Tests/Feature`. Run `php artisan module:check` after changing it: it verifies that the module only uses what it declares.

## Part of Tessera

This repository is a read-only copy of a folder in the [tessera-kit](https://github.com/Ahmad-Chebbo/tessera-kit) monorepo. Open issues and pull requests there, not here.

## License

All rights reserved until a license is added to the monorepo.
