# Installation and configuration

## Install

```bash
composer require jponchiroli/laravel-base
php artisan laravel-base:install
```

Composer does not run Artisan commands for dependencies, so the second command is manual. The provider itself is discovered automatically.

## `laravel-base:install`

Idempotent: running it again changes nothing that is already in place.

| Step | Action |
|---|---|
| 1 | Publishes `config/laravel-base.php` (tag `laravel-base-config`) |
| 2 | With `--with-lang`, publishes the translations to `lang/vendor/laravel-base` |
| 3 | With `--with-agents`, publishes the AI agent guide to `AGENTS.md` (never overwrites an existing one) |
| 4 | If `routes/api.php` is missing, runs `install:api` (Laravel Sanctum and its migration); warns if the file is still missing |
| 5 | Creates `app/Http/Controllers/Controller.php` (abstract) when missing |
| 6 | Sets `APP_LOCALE` and `APP_FALLBACK_LOCALE` in `.env` and `.env.example` (whichever exist), replacing existing values |

| Option | Default | Meaning |
|---|---|---|
| `--locale=` | `pt_BR` | Value for `APP_LOCALE` |
| `--fallback=` | `en` | Value for `APP_FALLBACK_LOCALE` |
| `--with-lang` | off | Publish translations |
| `--with-agents` | off | Publish the AI agent guide |
| `--force` | off | Overwrite already published files |

Locale values must look like a locale code (`en`, `pt_BR`, `es-MX`); anything else is rejected before any file is touched.

## Configuration

`config/laravel-base.php`:

| Key | Default | Effect |
|---|---|---|
| `exception_handler` | `true` | Registers the standardized JSON error responses for `api/*` and JSON requests |
| `route_prefix` | `v1` | Prefix used by generated routes (`v1/products`) |
| `generate_routes` | `true` | `make:domain` registers routes unless `--no-routes` is passed |

The config is merged with the package defaults, so the published file can contain only the keys you change.

## Publish tags

| Tag | Source | Destination |
|---|---|---|
| `laravel-base-config` | `config/laravel-base.php` | `config/laravel-base.php` |
| `laravel-base-stubs` | `stubs/` | `stubs/laravel-base/` |
| `laravel-base-lang` | `lang/` | `lang/vendor/laravel-base/` |
| `laravel-base-agents` | `docs/ai-agent-guide.md` | `AGENTS.md` |

```bash
php artisan vendor:publish --tag=laravel-base-stubs
```

`vendor:publish` skips files that already exist unless `--force` is given.

## Requirements

PHP 8.3 or newer and Laravel 13. A database connection is only needed by your own entities.

## Local development of the package

To use a working copy in another project, add a path repository to that project's `composer.json`:

```json
"repositories": [
    { "type": "path", "url": "../path/to/laravel-base" }
]
```

then `composer require jponchiroli/laravel-base:@dev`. See [testing.md](testing.md) for running the package's own tests.
