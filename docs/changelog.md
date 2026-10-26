# Changelog

All notable changes to this project are documented in this file.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added
- `make:domain` writes swagger-php (`OA` attributes) classes in `app/Docs/`: `SchemaDocs` (tags and the shared `Error`, `ValidationError` and `Message` schemas) and one `{Name}Docs` per domain (endpoints, model, payload and response schemas; payloads from the Request rules, model from the table columns); `--no-docs` skips it.
- `domain:docs` command: rewrites the `{Name}Docs` class of one or every domain, reading the table through the model connection and warning when the table is missing.
- `make:domain` writes `storage/api-docs/openapi.json` (also refreshed by `domain:docs`), the file served by `/docs/openapi.json`.
- `make:domain` creates the migration of the domain (`make:migration create_{table}_table`); `--no-migration` skips it.
- `domain:docs` fails with an error, without writing anything, when the model or the table of the domain does not exist.
- `domain:docs` without a name generates the docs of every domain created by `make:domain`.
- Swagger UI at `/docs` (configurable through the `swagger` config key). It is off when `APP_ENV` is `production`, accepts a `middleware` list, and loads the CDN assets with SRI hashes.
- Enum values of `in:` rules follow the field type (`integer|in:1,2` documents `[1, 2]`), and a `SchemaDocs` without a `class SchemaDocs` line warns instead of silently skipping the tag.

## [0.1.0] - 2026-10-24

### Added
- `make:domain` command: generates the Model, Controller, Service, Repository, DTOs and Requests of an entity, with Requests and DTOs grouped in one folder per domain, and registers the routes in `routes/api.php` (controller imported with `use`).
- `laravel-base:install` command: publishes the config, sets the locale in `.env` / `.env.example`, creates `routes/api.php` and the base `Controller` when missing.
- Base classes: `BaseService`, `BaseRepository`, `BaseRepositoryImpl`, `BaseDTO`, `BaseIndexDTO`, `BaseCreateDTO`, `BaseUpdateDTO`, `BasePatchDTO` and `BaseRequest`.
- Resources with a translated success message (`message` before `data`): `BaseResource`, `BaseStoreResource`, `BaseMessageResource` and `BaseCollection` (compact pagination `meta`, no `links`).
- Standardized JSON error responses for 401, 403, 404, 405, 422 and 500, configurable through `config/laravel-base.php`.
- `BaseRequest::messages()` builds one translated message per validation rule.
- English and Brazilian Portuguese translations (`lang/en`, `lang/pt_BR`).
- Publishable stubs (`laravel-base-stubs`), config (`laravel-base-config`), translations (`laravel-base-lang`) and AI agent guide (`laravel-base-agents`).
- AI agent guide template (`docs/ai-agent-guide.md`), published to `AGENTS.md` with the `laravel-base-agents` tag or `laravel-base:install --with-agents`.
- `docs/` folder: getting started, architecture, request lifecycle, layers, DTOs, responses and errors, validation and i18n, `make:domain`, installation and configuration, testing (with Mermaid diagrams).
- Test suite with 100% line coverage, `DocsTest` to keep the documentation in sync with the code, and a GitHub Actions workflow (PHP 8.3, 8.4, 8.5).