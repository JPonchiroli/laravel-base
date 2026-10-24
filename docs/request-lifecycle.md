# Request lifecycle

How one HTTP request travels through the layers. The example is `POST /api/v1/products`.

## Success path

```mermaid
sequenceDiagram
    autonumber
    participant Client
    participant Route as routes/api.php
    participant Request as ProductStoreRequest (BaseRequest)
    participant Controller as ProductController
    participant DTO as ProductCreateDTO
    participant Service as ProductService (BaseService)
    participant Repo as ProductRepository (BaseRepositoryImpl)
    participant Model as Product (Eloquent)
    participant Resource as BaseStoreResource

    Client->>Route: POST /api/v1/products
    Route->>Request: resolve and validate
    Request-->>Controller: validated data
    Controller->>DTO: fromArray(validated)
    Controller->>Service: store(dto)
    Service->>Repo: create(dto)
    Repo->>Model: newQuery()->create(dto->toArray())
    Model-->>Repo: Product
    Repo-->>Service: Product
    Service-->>Controller: Product
    Controller->>Resource: new BaseStoreResource(product)
    Resource-->>Client: 201 { message, data }
```

1. The route group (`v1/products`) points at a controller method.
2. Laravel resolves the typed `FormRequest`. `BaseRequest::authorize()` returns `true`; `rules()` come from the generated Request; the messages are derived from the rules.
3. If validation fails, the request never reaches the controller (see the error paths below).
4. The controller builds a DTO from `$request->validated()` and calls the service.
5. The service delegates to the repository, which is the only layer that touches Eloquent.
6. The controller wraps the model in a Resource. The Resource adds the translated `message` and puts it before `data`.

## Read and delete paths

| Endpoint | Controller | Service call | Repository behaviour | Result |
|---|---|---|---|---|
| `GET /products` | `index` | `getPaginate(IndexDTO)` | equality `where` per filled filter, then `paginate(per_page)` | 200 `{ message, data, meta }` |
| `GET /products/{id}` | `show` | `getById(IdDTO)` | `find(id)` | 200, or 404 when `null` |
| `PUT /products/{id}` | `update` | `update(UpdateDTO)` | `find(id)` then `update(attributes)` | 200, or 404 when `null` |
| `PATCH /products/{id}` | `patch` | `patch(PatchDTO)` | same as update but `null` values are dropped | 200, or 404 when `null` |
| `DELETE /products/{id}` | `destroy` | `delete(IdDTO)` | `find(id)?->delete()` | 200 `{ message }`, or 404 when `false` |

## Error paths

```mermaid
flowchart TD
    A[Request] --> B{Valid?}
    B -- no --> C[ValidationException]
    C --> R422["422 { success:false, message, errors }"]
    B -- yes --> D[Controller / Service / Repository]
    D --> E{Record found?}
    E -- no --> F["abort(404)"]
    F --> R404["404 { success:false, message }"]
    E -- yes --> G{Unexpected exception?}
    G -- yes --> H[ApiExceptionHandler]
    H --> R500["500 { success:false, message } (framework page when APP_DEBUG=true)"]
    G -- no --> OK["2xx { message, data }"]
```

The exception handler only formats errors for `api/*` URLs or requests that expect JSON (`Accept: application/json`). Details in [responses-and-errors.md](responses-and-errors.md).
