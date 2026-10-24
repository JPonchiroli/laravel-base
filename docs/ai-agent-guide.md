# Guide for AI agents (copy into your project as `AGENTS.md`)

> This file is a template. Publish it with `php artisan vendor:publish --tag=laravel-base-agents` (or `php artisan laravel-base:install --with-agents`). It is copied to `AGENTS.md` in your project root and never overwrites an existing `AGENTS.md`. Claude Code users can add `@AGENTS.md` to their `CLAUDE.md`.

This project uses the `jponchiroli/laravel-base` package. It generates the whole API layer of an entity with one command. **Run the command; do not write these files by hand.** Do not open `vendor/` to learn how the package works: everything you need is below.

## Golden rule

New entity, resource or CRUD endpoint set:

```bash
php artisan make:domain Product
```

The command is idempotent: it never overwrites an existing file (it prints `Already exists: <path>` and moves on) and never registers the same routes twice. Use `--no-routes` to skip `routes/api.php`.

## What the command creates (example: `Product`)

| File | Notes |
|---|---|
| `app/Models/Product.php` | `$fillable` is empty: fill it |
| `app/Http/Controllers/ProductController.php` | `index show store update patch destroy`, ready |
| `app/Services/Product/ProductService.php` | extends `BaseService`, ready |
| `app/Repositories/Product/ProductRepository.php` | extends `BaseRepositoryImpl`, ready |
| `app/DTOs/Product/ProductCreateDTO.php` | empty body, carries the validated fields |
| `app/DTOs/Product/ProductUpdateDTO.php` | empty body, `id` plus validated fields |
| `app/DTOs/Product/ProductPatchDTO.php` | empty body, `id` plus validated fields |
| `app/DTOs/Product/ProductIdDTO.php` | ready |
| `app/DTOs/Product/ProductIndexDTO.php` | ready |
| `app/Http/Requests/Product/ProductIndexRequest.php` | rules: list the filters you accept |
| `app/Http/Requests/Product/ProductStoreRequest.php` | rules: empty, fill them |
| `app/Http/Requests/Product/ProductUpdateRequest.php` | rules: empty, fill them |
| `app/Http/Requests/Product/ProductPatchRequest.php` | rules: empty, fill them |
| `app/Http/Requests/Product/ProductDeleteRequest.php` | ready |

It also appends to `routes/api.php` an import (`use App\Http\Controllers\ProductController;`) and this block (prefix `v1`, route name is the kebab-case plural):

```php
Route::prefix('v1/products')->group(function () {
    Route::get('/', [ProductController::class, 'index']);
    Route::get('{id}', [ProductController::class, 'show']);
    Route::post('/', [ProductController::class, 'store']);
    Route::put('{id}', [ProductController::class, 'update']);
    Route::patch('{id}', [ProductController::class, 'patch']);
    Route::delete('{id}', [ProductController::class, 'destroy']);
});
```

## The only manual edits (3)

1. **Migration and model.** `php artisan make:migration create_products_table --create=products`, define the columns, run `php artisan migrate`, then set `protected $fillable = ['name', 'price'];` in the model.
2. **Request rules.** Rules are the only place where fields are declared; fields not in the rules never reach the database.
   - `ProductStoreRequest` / `ProductUpdateRequest`: `'name' => ['required', 'string', 'max:100']`, `'price' => ['nullable', 'numeric', 'min:0']`.
   - `ProductPatchRequest`: same fields with `sometimes` instead of `required`.
   - `ProductIndexRequest`: the fields accepted as equality filters, plus `'per_page' => ['sometimes', 'integer', 'min:1', 'max:100']`.
3. **Verify.** See the checklist below.

DTOs need no code. Do not add properties to them unless a task explicitly asks for typed DTOs.

## Recipes

| Task | Do |
|---|---|
| New CRUD entity | `make:domain Name`, then the 3 manual edits |
| Add a field | migration `add_x_to_names_table`, add to `$fillable`, add to Store/Update/Patch rules (and Index rules if it is a filter) |
| Add a list filter | add the field to the Index request rules (equality filter, applied automatically when present) |
| Custom query | method in `app/Repositories/Name/NameRepository.php` (use `$this->model->newQuery()`), thin method in the service, call it from a controller method |
| Action that is not CRUD | new method in the existing controller, new route in `routes/api.php` (keep the `{id}` parameter name), return a `BaseResource` / `BaseMessageResource` |
| Different success message | add the key to a published `lang/vendor/laravel-base/*/messages.php` (`php artisan vendor:publish --tag=laravel-base-lang`) and pass it: `new BaseResource($model, 'key')` |
| Typed DTO | replace the DTO body with typed properties and a static `fromArray(array $data): self`; keep the property names equal to the column names |
| Change the generated code style | `php artisan vendor:publish --tag=laravel-base-stubs`, edit `stubs/laravel-base/*.stub` |

## Conventions (already handled by the package, do not reimplement)

- Success responses are `{ "message": "...", "data": ... }`; lists add `"meta": { to, from, total, per_page, last_page, current_page }` and have no `links`. Create answers 201, delete answers `{ "message": "..." }` with 200.
- Errors are `{ "success": false, "message": "...", "errors": {...} }` for 401, 403, 404, 405, 422 and 500 on `api/*` routes or JSON requests. Do not catch exceptions to build error JSON.
- Missing records: `abort(404)` (the generated controller already does it for show, update, patch and delete).
- Validation messages are generated from the rules and translated (`en`, `pt_BR`) from `APP_LOCALE`. Do not write `messages()` in Requests and do not hard-code user-facing text.
- Always return Resources (`BaseResource`, `BaseStoreResource`, `BaseMessageResource`), never raw models or `response()->json()`.
- `update` writes every sent field (including `null`); `patch` ignores `null` values.
- Requests and DTOs live in a folder per domain (`Http/Requests/{Name}/`, `DTOs/{Name}/`).

## Do not

- Do not create or edit controller, service, repository, DTO, Request or route files by hand for a new entity.
- Do not edit anything under `vendor/`.
- Do not create flat Requests in `app/Http/Requests/`.
- Do not re-run `make:domain` hoping to regenerate an existing file; delete the file first if you really want it recreated.
- Do not add custom envelope keys to responses.

## Verify checklist

1. `php artisan route:list --path=products` lists six routes.
2. Call the endpoint with `Accept: application/json` (store with an empty body must answer 422 with the rule messages; a valid body must answer 201).
3. Run the project tests (`php artisan test`) and the formatter (`vendor/bin/pint`).

## Saving tokens

- Prefer the command output over reading generated files back: the table above is the contract.
- Only open the topic docs when the task needs them: [architecture](https://github.com/JPonchiroli/laravel-base/blob/main/docs/architecture.md), [layers](https://github.com/JPonchiroli/laravel-base/blob/main/docs/layers.md), [DTOs](https://github.com/JPonchiroli/laravel-base/blob/main/docs/dtos.md), [responses and errors](https://github.com/JPonchiroli/laravel-base/blob/main/docs/responses-and-errors.md), [validation and i18n](https://github.com/JPonchiroli/laravel-base/blob/main/docs/validation-and-i18n.md), [make:domain](https://github.com/JPonchiroli/laravel-base/blob/main/docs/make-domain.md).
