# CLAUDE.md

Laravel package `jponchiroli/laravel-base` (PHP 8.3+, Laravel 13). It ships layered base classes, standard JSON responses and errors, and the Artisan commands `laravel-base:install`, `make:domain` and `domain:docs`.

## Commands

- Tests: `composer test` (PHPUnit 12 with Orchestra Testbench, sqlite).
- One test: `php vendor/bin/phpunit --filter name`.
- Lint: `composer lint` (Pint, `--test` only checks). Run `php vendor/bin/pint` to fix.

## Layout

- `src/Base/`: base classes that generated code extends (service, repository, DTOs, request, resources).
- `src/Console/`: `InstallCommand`, `MakeDomainCommand`, `DomainDocsCommand`.
- `src/Docs/`: `OpenApiBuilder` and `DocsRenderer`, which build the swagger-php attribute classes (`app/Docs/SchemaDocs.php` and `app/Docs/{Name}/{Name}Docs.php`) and `storage/api-docs/openapi.json`.
- `src/Exceptions/`: `ApiExceptionHandler`.
- `src/LaravelBaseServiceProvider.php`: registers commands, config, lang and the Swagger UI routes (`/docs`, `/docs/openapi.json`).
- `stubs/`: templates used by `make:domain`.
- `lang/`: `en` and `pt_BR` messages.
- `docs/`: technical documentation. `README.md` and `README.pt-BR.md` are the entry points.

## Rules

- Generated domain classes are empty subclasses of the base classes. Put behaviour in the base classes, not in the stubs.
- All code, comments, docs and lang keys are in English. Only `lang/pt_BR` and `README.pt-BR.md` hold Portuguese (`EnglishOnlyTest`, `LangParityTest`).
- Every lang key must exist in both `en` and `pt_BR`.
- `DocsTest` ties the docs to the code: `README.md` stays at 120 lines or fewer and mentions Swagger, and every config key and command option must be documented. Change docs together with code.
- Record user-visible changes in `docs/changelog.md` under Unreleased.
- A known limit in code gets a comment: `shortcut: <the limit>, <when to upgrade>`.

## OpenAPI generation

- `domain:docs {name?}` rewrites the whole `{Name}Docs.php`, so manual edits are lost. Without a name it covers every domain found by `app/Http/Requests/*/*StoreRequest.php` and existing Docs files.
- A domain without a model or table prints an error and is skipped. Nothing generic is generated.
- `make:domain` also runs `make:migration` at the end. Options: `--no-docs`, `--no-migration`, `--no-routes`.
- Request payloads come from `rules()`. Rule objects, nested and wildcard fields are ignored.

## Testing notes

- `tests/TestCase.php` removes `app/Docs`, `storage/api-docs` and new migration files after each test.
- PHP cannot redefine a loaded class in one process. Docs tests that need fresh content assert on the generated source text, not on the route JSON.
- `composer.lock` is gitignored.
