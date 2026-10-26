# Getting started

A complete walkthrough: from an installed package to a working CRUD for a `Product` entity (fields `name` and `price`). Install first (see [install-and-configuration.md](install-and-configuration.md)).

## 1. Generate the classes

```bash
php artisan make:domain Product
```

This creates the model, controller, service, repository, DTOs and Requests of the entity, registers the routes in `routes/api.php`, writes the Swagger/OpenAPI docs and creates the migration of the table. The full list of files, the stub placeholders and the route registration are in [make-domain.md](make-domain.md). Use `--no-routes`, `--no-docs` and `--no-migration` to skip each of them.

The service, repository, DTOs and `ProductDeleteRequest` work as generated. Only steps 2 and 3 are manual.

## 2. Create the table and configure the model

`make:domain` already created the migration (`create_products_table`, run with `--no-migration` to skip it). Open it in `database/migrations` and define the columns:

```php
// database/migrations/..._create_products_table.php
$table->id();
$table->string('name');
$table->decimal('price', 10, 2)->nullable();
$table->timestamps();
```

```php
// app/Models/Product.php
protected $fillable = ['name', 'price'];
```

```bash
php artisan migrate
```

## 3. Declare the validation rules

The Request rules are the only place where the entity's fields are declared: fields that are not in the rules never reach the DTO or the database (see [dtos.md](dtos.md)).

```php
// app/Http/Requests/Product/ProductStoreRequest.php
public function rules(): array
{
    return [
        'name' => ['required', 'string', 'max:100'],
        'price' => ['nullable', 'numeric', 'min:0'],
    ];
}
```

- `ProductUpdateRequest`: the same rules as Store.
- `ProductPatchRequest`: `sometimes` on each field, for example `['sometimes', 'string', 'max:100']`.
- `ProductIndexRequest`: the fields accepted as equality filters, plus `per_page`:

```php
return [
    'name' => ['sometimes', 'string'],
    'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
];
```

Error messages are derived from the rules and translated automatically (see [validation-and-i18n.md](validation-and-i18n.md)).

## 4. Try the API

```bash
php artisan route:list --path=products
```

```bash
curl -X POST http://localhost:8000/api/v1/products \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"name": "Pen", "price": 3.5}'
# 201 {"message": "Record created successfully.", "data": {"id": 1, "name": "Pen", ...}}

curl "http://localhost:8000/api/v1/products?name=Pen&per_page=10"
curl -X PATCH http://localhost:8000/api/v1/products/1 -H "Accept: application/json" -H "Content-Type: application/json" -d '{"price": 4}'
curl -X DELETE http://localhost:8000/api/v1/products/1 -H "Accept: application/json"
```

Always send `Accept: application/json`; without it Laravel may answer some errors with HTML. Response shapes and error bodies are described in [responses-and-errors.md](responses-and-errors.md).

## Conventions to keep

- The route parameter must be named `{id}`.
- Keep Requests and DTOs in the domain folders the generator creates.
- Return Resources from controllers, never raw models.

## Going further

| I want to... | Read |
|---|---|
| Understand what happens to a request | [request-lifecycle.md](request-lifecycle.md) |
| Add a custom query or business rule | [layers.md](layers.md) |
| Use typed DTOs | [dtos.md](dtos.md) |
| Change a message or add a language | [validation-and-i18n.md](validation-and-i18n.md) |
| Change the generated code | [make-domain.md](make-domain.md) |
| Let an AI agent do all this | [ai-agent-guide.md](ai-agent-guide.md) |
