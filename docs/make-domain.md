# The `make:domain` command

```bash
php artisan make:domain Product            # classes + routes
php artisan make:domain Product --no-routes # classes only
```

The name is normalized with `Str::studly` (`product`, `blog_post`, `blog-post` and `blogPost` all work) and must then match `^[A-Z][A-Za-z0-9]*$`, otherwise the command prints `Invalid name: ...` and fails.

## What happens

```mermaid
flowchart TD
    Start["make:domain Name"] --> Normalize["studly case + validate name"]
    Normalize -- invalid --> Fail["error, exit 1"]
    Normalize -- valid --> Loop["for each target file"]
    Loop --> Exists{"file exists?"}
    Exists -- yes --> Warn["warn: Already exists, skip"]
    Exists -- no --> Stub["load stub (published override first)"]
    Stub --> Render["replace placeholders"]
    Render --> Write["create directories and write file"]
    Warn --> Next
    Write --> Next["next target"]
    Next -- more --> Loop
    Next -- done --> Routes{"--no-routes or generate_routes=false?"}
    Routes -- yes --> End["done"]
    Routes -- no --> RoutesFile{"routes/api.php exists?"}
    RoutesFile -- no --> WarnRoutes["warn: run laravel-base:install"]
    RoutesFile -- yes --> Registered{"controller already referenced?"}
    Registered -- yes --> WarnReg["warn: Routes already registered"]
    Registered -- no --> Import["add use import (alphabetical)"]
    Import --> Append["append route block"]
```

## Generated files

For `Product` (paths relative to `app/`):

| Stub | File | Extends |
|---|---|---|
| `model` | `Models/Product.php` | `Illuminate\Database\Eloquent\Model` |
| `controller` | `Http/Controllers/ProductController.php` | `Controller` |
| `service` | `Services/Product/ProductService.php` | `BaseService` |
| `repository` | `Repositories/Product/ProductRepository.php` | `BaseRepositoryImpl` |
| `dto.create` | `DTOs/Product/ProductCreateDTO.php` | `BaseCreateDTO` |
| `dto.update` | `DTOs/Product/ProductUpdateDTO.php` | `BaseUpdateDTO` |
| `dto.patch` | `DTOs/Product/ProductPatchDTO.php` | `BasePatchDTO` |
| `dto.id` | `DTOs/Product/ProductIdDTO.php` | `BaseDTO` |
| `dto.index` | `DTOs/Product/ProductIndexDTO.php` | `BaseIndexDTO` |
| `request.index` | `Http/Requests/Product/ProductIndexRequest.php` | `BaseRequest` |
| `request.store` | `Http/Requests/Product/ProductStoreRequest.php` | `BaseRequest` |
| `request.update` | `Http/Requests/Product/ProductUpdateRequest.php` | `BaseRequest` |
| `request.patch` | `Http/Requests/Product/ProductPatchRequest.php` | `BaseRequest` |
| `request.delete` | `Http/Requests/Product/ProductDeleteRequest.php` | `BaseRequest` |

Requests and DTOs live in one folder per domain, with the folder name as the last namespace segment (`App\Http\Requests\Product`, `App\DTOs\Product`). The root namespace comes from the application (`App` by default).

## Routes

When `routes/api.php` exists and the controller is not referenced yet, the command:

1. adds `use App\Http\Controllers\ProductController;` to the import block at the top of the file, in alphabetical position (or right after `<?php` when the file has no imports), keeping the file's line endings;
2. appends the route block from `routes.stub`: prefix `{config route_prefix}/{kebab-case plural}`, six routes (`index`, `show`, `store`, `update`, `patch`, `destroy`) written as `ProductController::class`.

Running the command twice never duplicates the import or the routes.

## Stubs and placeholders

Stubs are in `stubs/*.stub`. To change generated code project-wide, publish them:

```bash
php artisan vendor:publish --tag=laravel-base-stubs
```

Files in `stubs/laravel-base/` (in your project) win over the package ones, stub by stub, so you can publish and keep only the stubs you changed.

| Placeholder | Value | Example |
|---|---|---|
| `{{ namespace }}` | Application root namespace | `App` |
| `{{ class }}` | Normalized entity name | `Product` |
| `{{ plural }}` | camelCase plural, used for the list variable | `products` |
| `{{ prefix }}` | `route_prefix` config (routes stub only) | `v1` |
| `{{ route }}` | kebab-case plural (routes stub only) | `products` |

## Idempotency and re-running

Existing files are never touched. To regenerate one file, delete it and run the command again; the other files are skipped with `Already exists: <path>`.

## Upgrading from flat Requests

Earlier versions generated Requests directly in `app/Http/Requests/`. They are now grouped per domain, like DTOs. To migrate an entity, move its five Requests into `app/Http/Requests/{Name}/`, change their namespace to `App\Http\Requests\{Name}` and update the imports in the controller. Re-running `make:domain` for an entity that still has flat Requests would generate a second set in the new folder.

## After generating

Three manual steps remain: the migration and `$fillable`, the Request rules, and a smoke test. [getting-started.md](getting-started.md) walks through them and [ai-agent-guide.md](ai-agent-guide.md) has the checklist.
