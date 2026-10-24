# Testing

The package is tested with PHPUnit on top of [Orchestra Testbench](https://packages.tools/testbench), which boots a minimal Laravel application with the service provider registered. Tests use an in-memory SQLite database.

## Running

```bash
composer install
composer test     # vendor/bin/phpunit
composer lint     # vendor/bin/pint --test
```

Coverage needs Xdebug or PCOV:

```bash
XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-text
```

The suite keeps 100% line coverage of `src/`.

## Layout

| Path | Purpose |
|---|---|
| `tests/Unit` | Pure unit tests: DTOs, `BaseService` (mocked repository), documentation guards, the English-only rule |
| `tests/Feature` | Tests that boot the application: repository against SQLite, resources, exception handler, `BaseRequest` messages, both commands, language parity |
| `tests/Fixtures` | `Widget` model, `WidgetRepository` and typed DTOs used by repository tests |
| `tests/TestCase.php` | Base class: registers the provider, creates the `widgets` table, and offers `pt()` |

### Notable suites

| Test | Guards |
|---|---|
| `GeneratedDomainCrudTest` | Runs `make:domain`, fills in the model `$fillable` and the Request rules, then drives the generated controller through every endpoint (success, validation, 404, locales) |
| `MakeDomainCommandTest` | Generated files, namespaces, stub overrides, name normalization, idempotency |
| `InstallCommandTest` | `.env` handling, publishing, route registration and import ordering |
| `ApiResponseTest`, `ResourcesTest` | Response shapes, key order, pagination `meta`, error mapping |
| `RequestMessagesTest` | Message derivation per rule and per field type |
| `LangParityTest` | `en` and `pt_BR` have the same keys and placeholders |
| `EnglishOnlyTest` | No Portuguese outside `lang/pt_BR` and `README.pt-BR.md` |
| `DocsTest` | Documentation links, diagrams and the agent guide stay in sync with the code |

### Portuguese in tests

Tests contain no Portuguese literals. Expected `pt_BR` text is read from the language files with `$this->pt('messages', 'success.store')` and similar calls.

## Continuous integration

`.github/workflows/tests.yml` runs on every push and pull request:

| Job | What it does |
|---|---|
| `tests` | `phpunit` on PHP 8.3, 8.4 and 8.5 |
| `style` | `pint --test` |
| `coverage` | `phpunit --coverage-text` with Xdebug (informational) |
