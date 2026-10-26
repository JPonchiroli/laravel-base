<?php

namespace Jponchiroli\LaravelBase\Docs;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use OpenApi\Generator;
use Psr\Log\NullLogger;
use RuntimeException;
use Throwable;

/**
 * Builds the swagger-php (OA attributes) classes that document a domain.
 * A node is an array with an "@" key naming the OA attribute class, see DocsRenderer.
 */
class OpenApiBuilder
{
    /**
     * Reasons why the generated documentation is less precise than it could be.
     *
     * @var list<string>
     */
    public array $warnings = [];

    public static function domainPath(string $name): string
    {
        return app_path("Docs/{$name}/{$name}Docs.php");
    }

    public static function basePath(): string
    {
        return app_path('Docs/SchemaDocs.php');
    }

    public static function jsonPath(): string
    {
        return storage_path('api-docs/openapi.json');
    }

    protected function hasGenerator(): bool
    {
        return class_exists(Generator::class);
    }

    public function openApiJson(): string
    {
        if (! $this->hasGenerator()) {
            throw new RuntimeException('zircote/swagger-php is not installed. Run: composer update jponchiroli/laravel-base');
        }

        $generator = new Generator(new NullLogger);

        return $generator->generate(is_dir(app_path('Docs')) ? [app_path('Docs')] : [], validate: false)?->toJson() ?? '{}';
    }

    /**
     * Scans app/Docs and writes the merged document, the file served by the docs route.
     * A failure becomes a warning: the docs must never break the command that generated them.
     */
    public function writeJson(Filesystem $files): bool
    {
        try {
            $files->ensureDirectoryExists(dirname(self::jsonPath()));
            $files->put(self::jsonPath(), $this->openApiJson());

            return true;
        } catch (Throwable $e) {
            $this->warnings[] = 'Could not write openapi.json: '.$e->getMessage();

            return false;
        }
    }

    /**
     * Creates SchemaDocs when missing and registers the tag of the domain in it.
     */
    public function ensureBase(Filesystem $files, string $name): void
    {
        $path = self::basePath();

        if (! $files->exists($path)) {
            $files->ensureDirectoryExists(dirname($path));
            $files->put($path, $this->baseSource());
        }

        $contents = $files->get($path);
        $tag = "#[OA\\Tag(name: '{$name}')]";

        if (str_contains($contents, "OA\\Tag(name: '{$name}'")) {
            return;
        }

        $eol = str_contains($contents, "\r\n") ? "\r\n" : "\n";

        $updated = preg_replace('/^class SchemaDocs\b/m', str_replace('\\', '\\\\', $tag).$eol.'class SchemaDocs', $contents, 1, $count);

        if (! $count) {
            $this->warnings[] = "Could not add the tag '{$name}' to SchemaDocs: no 'class SchemaDocs' line found. Add {$tag} by hand.";

            return;
        }

        $files->put($path, $updated);
    }

    public function baseSource(): string
    {
        $nodes = [
            ['@' => 'Info', 'title' => (string) config('laravel-base.swagger.title'), 'version' => '1.0.0'],
            ...array_map(fn (string $name, array $schema) => $this->schemaNode($name, $schema), array_keys($this->sharedSchemas()), $this->sharedSchemas()),
        ];

        return $this->file('App\\Docs', 'SchemaDocs', $nodes, []);
    }

    public function domainSource(string $name): string
    {
        $base = '/'.implode('/', array_filter([
            'api',
            trim((string) config('laravel-base.route_prefix'), '/'),
            Str::kebab(Str::plural($name)),
        ]));

        $schemas = [
            $name => $this->modelSchema($name),
            "{$name}StoreRequest" => $this->requestSchema($this->request($name, 'Store'), "Create {$name}"),
            "{$name}UpdateRequest" => $this->requestSchema($this->request($name, 'Update'), "Update {$name}"),
            "{$name}PatchRequest" => $this->requestSchema($this->request($name, 'Patch'), "Partially update {$name}"),
            "{$name}Resource" => $this->envelope(['data' => $this->ref($name)], "{$name} resource"),
            "{$name}Collection" => $this->envelope([
                'data' => ['type' => 'array', 'items' => $this->ref($name)],
                'meta' => ['type' => 'object', 'properties' => array_fill_keys(
                    ['to', 'from', 'total', 'per_page', 'last_page', 'current_page'], ['type' => 'integer'],
                )],
            ], "{$name} collection"),
        ];

        $id = [['@' => 'Parameter', 'name' => 'id', 'in' => 'path', 'required' => true, 'schema' => $this->node('Schema', ['type' => 'string'])]];
        $indexRules = $this->rules($this->request($name, 'Index'));

        $operations = [
            'index' => $this->operation('Get', $base, "List {$name}", $name, $this->queryParameters($indexRules), null,
                ['200' => ['Paginated list', "{$name}Collection"]], [401, 403, 422, 500]),
            'store' => $this->operation('Post', $base, "Create {$name}", $name, [], "{$name}StoreRequest",
                ['201' => ['Created', "{$name}Resource"]], [401, 403, 422, 500]),
            'show' => $this->operation('Get', "{$base}/{id}", "Show {$name}", $name, $id, null,
                ['200' => ['Found', "{$name}Resource"]], [401, 403, 404, 500]),
            'update' => $this->operation('Put', "{$base}/{id}", "Update {$name}", $name, $id, "{$name}UpdateRequest",
                ['200' => ['Updated', "{$name}Resource"]], [401, 403, 404, 422, 500]),
            'patch' => $this->operation('Patch', "{$base}/{id}", "Partially update {$name}", $name, $id, "{$name}PatchRequest",
                ['200' => ['Updated', "{$name}Resource"]], [401, 403, 404, 422, 500]),
            'destroy' => $this->operation('Delete', "{$base}/{id}", "Delete {$name}", $name, $id, null,
                ['200' => ['Deleted', 'Message']], [401, 403, 404, 500]),
        ];

        $nodes = array_map(fn (string $schema, array $definition) => $this->schemaNode($schema, $definition), array_keys($schemas), $schemas);

        return $this->file('App\\Docs\\'.$name, "{$name}Docs", $nodes, $operations);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function sharedSchemas(): array
    {
        return [
            'Message' => $this->envelope(),
            'Error' => ['type' => 'object', 'properties' => [
                'success' => ['type' => 'boolean', 'example' => false],
                'message' => ['type' => 'string'],
            ]],
            'ValidationError' => ['title' => 'Validation error', 'type' => 'object', 'properties' => [
                'success' => ['type' => 'boolean', 'example' => false],
                'message' => ['type' => 'string'],
                'errors' => ['type' => 'object', 'additionalProperties' => ['type' => 'array', 'items' => ['type' => 'string']]],
            ]],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $nodes  class-level attributes
     * @param  array<string, array<string, mixed>>  $operations  method name => attribute node
     */
    protected function file(string $namespace, string $class, array $nodes, array $operations): string
    {
        $renderer = new DocsRenderer;
        $source = "<?php\n\nnamespace {$namespace};\n\nuse OpenApi\\Attributes as OA;\n\n";

        foreach ($nodes as $node) {
            $source .= '#['.$renderer->render($node)."]\n";
        }

        $source .= "class {$class}\n{";

        foreach ($operations as $method => $node) {
            $source .= "\n    #[".$renderer->render($node, 1)."]\n    public function {$method}(): void\n    {\n    }\n";
        }

        return $source.($operations ? '' : "\n")."}\n";
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    protected function schemaNode(string $name, array $schema): array
    {
        return $this->node('Schema', ['schema' => $name] + $schema);
    }

    /**
     * Turns an OpenAPI schema array into the named arguments of an OA attribute.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    protected function node(string $type, array $arguments): array
    {
        $node = ['@' => $type];

        foreach ($arguments as $key => $value) {
            $node += match ($key) {
                '$ref' => ['ref' => $value],
                'properties' => ['properties' => array_map(
                    fn (string $property, array $schema) => $this->node('Property', ['property' => $property] + $schema),
                    array_keys($value), $value,
                )],
                'items' => ['items' => $this->node('Items', $value)],
                'additionalProperties' => ['additionalProperties' => $this->node('AdditionalProperties', $value)],
                default => [$key => $value],
            };
        }

        return $node;
    }

    /**
     * @param  list<array<string, mixed>>  $parameters
     * @param  array<string, array{0: string, 1: string}>  $success  status => [description, schema]
     * @param  list<int>  $errors
     * @return array<string, mixed>
     */
    protected function operation(string $verb, string $path, string $summary, string $tag, array $parameters, ?string $body, array $success, array $errors): array
    {
        $descriptions = [401 => 'Unauthenticated', 403 => 'Forbidden', 404 => 'Record not found', 422 => 'Validation failed', 500 => 'Server error'];
        $responses = [];

        foreach ($success as $status => [$description, $schema]) {
            $responses[] = $this->response((string) $status, $description, $schema);
        }

        foreach ($errors as $status) {
            $responses[] = $this->response((string) $status, $descriptions[$status], $status === 422 ? 'ValidationError' : 'Error');
        }

        return ['@' => $verb, 'path' => $path, 'summary' => $summary, 'tags' => [$tag]]
            + ($parameters ? ['parameters' => $parameters] : [])
            + ($body ? ['requestBody' => ['@' => 'RequestBody', 'required' => true, 'content' => ['@' => 'JsonContent', 'ref' => $this->refPath($body)]]] : [])
            + ['responses' => $responses];
    }

    /**
     * @return array<string, mixed>
     */
    protected function response(string $status, string $description, string $schema): array
    {
        return ['@' => 'Response', 'response' => $status, 'description' => $description,
            'content' => ['@' => 'JsonContent', 'ref' => $this->refPath($schema)]];
    }

    protected function refPath(string $schema): string
    {
        return "#/components/schemas/{$schema}";
    }

    /**
     * @return array<string, string>
     */
    protected function ref(string $schema): array
    {
        return ['$ref' => $this->refPath($schema)];
    }

    /**
     * @param  array<string, array<string, mixed>>  $properties
     * @return array<string, mixed>
     */
    protected function envelope(array $properties = [], ?string $title = null): array
    {
        return ($title ? ['title' => $title] : []) + ['type' => 'object', 'properties' => ['message' => ['type' => 'string']] + $properties];
    }

    protected function request(string $name, string $action): string
    {
        $namespace = rtrim(app()->getNamespace(), '\\');

        return "{$namespace}\\Http\\Requests\\{$name}\\{$name}{$action}Request";
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(string $request): array
    {
        if (! class_exists($request)) {
            return [];
        }

        try {
            return (new $request)->rules();
        } catch (Throwable) {
            // shortcut: rules() that need the live request (route(), input()) are skipped, document them by hand
            $this->warnings[] = "Could not read the rules of {$request}.";

            return [];
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function requestSchema(string $request, string $title): array
    {
        $properties = [];
        $required = [];

        foreach ($this->rules($request) as $field => $rules) {
            // shortcut: nested and wildcard fields are not expanded
            if (str_contains($field, '.')) {
                continue;
            }

            $properties[$field] = $this->fieldSchema($rules);

            if (in_array('required', $this->ruleNames($rules), true)) {
                $required[] = $field;
            }
        }

        return ['title' => $title, 'type' => 'object']
            + ($required ? ['required' => $required] : [])
            + ($properties ? ['properties' => $properties] : []);
    }

    /**
     * @param  array<string, mixed>  $rules
     * @return list<array<string, mixed>>
     */
    protected function queryParameters(array $rules): array
    {
        $parameters = [];

        foreach ($rules as $field => $fieldRules) {
            $parameters[] = [
                '@' => 'Parameter',
                'name' => $field,
                'in' => 'query',
                'required' => in_array('required', $this->ruleNames($fieldRules), true),
                'schema' => $this->node('Schema', $this->fieldSchema($fieldRules)),
            ];
        }

        return $parameters;
    }

    /**
     * @return list<string>
     */
    protected function ruleNames(mixed $rules): array
    {
        return array_map(fn (array $rule) => $rule[0], $this->parseRules($rules));
    }

    /**
     * Rule objects (Enum, Rule::in()...) are ignored: only string rules are mapped.
     *
     * @return list<array{0: string, 1: ?string}>
     */
    protected function parseRules(mixed $rules): array
    {
        $rules = is_string($rules) ? explode('|', $rules) : (array) $rules;

        return array_map(
            fn (string $rule) => array_pad(explode(':', $rule, 2), 2, null),
            array_values(array_filter($rules, 'is_string')),
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function fieldSchema(mixed $rules): array
    {
        $parsed = $this->parseRules($rules);
        $names = array_column($parsed, 0);
        $args = array_column($parsed, 1, 0);

        $schema = match (true) {
            in_array('integer', $names, true) => ['type' => 'integer'],
            (bool) array_intersect($names, ['numeric', 'decimal']) => ['type' => 'number'],
            (bool) array_intersect($names, ['boolean', 'bool', 'accepted']) => ['type' => 'boolean'],
            in_array('array', $names, true) => ['type' => 'array', 'items' => ['type' => 'string']],
            (bool) array_intersect($names, ['file', 'image']) => ['type' => 'string', 'format' => 'binary'],
            in_array('email', $names, true) => ['type' => 'string', 'format' => 'email'],
            in_array('uuid', $names, true) => ['type' => 'string', 'format' => 'uuid'],
            in_array('url', $names, true) => ['type' => 'string', 'format' => 'uri'],
            in_array('date', $names, true) => ['type' => 'string', 'format' => 'date'],
            default => ['type' => 'string'],
        };

        [$min, $max] = match ($schema['type']) {
            'integer', 'number' => ['minimum', 'maximum'],
            'array' => ['minItems', 'maxItems'],
            default => ['minLength', 'maxLength'],
        };

        foreach (['min' => $min, 'max' => $max] as $rule => $keyword) {
            if (isset($args[$rule]) && is_numeric($args[$rule])) {
                $schema[$keyword] = $args[$rule] + 0;
            }
        }

        if (isset($args['in'])) {
            $schema['enum'] = array_map(
                fn (string $value) => match ($schema['type']) {
                    'integer' => (int) $value,
                    'number' => $value + 0,
                    default => $value,
                },
                explode(',', $args['in']),
            );
        }

        if (in_array('nullable', $names, true)) {
            $schema['nullable'] = true;
        }

        return $schema;
    }

    /**
     * Why docs cannot be built for the domain (no model, no table), or null when they can.
     */
    public function domainError(string $name): ?string
    {
        $class = rtrim(app()->getNamespace(), '\\')."\\Models\\{$name}";

        if (! class_exists($class)) {
            return "Domain {$name} not found: model {$class} does not exist.";
        }

        $model = new $class;

        try {
            if ($model->getConnection()->getSchemaBuilder()->hasTable($model->getTable())) {
                return null;
            }
        } catch (Throwable $e) {
            return "Could not read the table '{$model->getTable()}': {$e->getMessage()}";
        }

        return "Table '{$model->getTable()}' not found: run the migration first.";
    }

    /**
     * Properties come from the columns of the model table, read through the connection of the model.
     *
     * @return array<string, mixed>
     */
    protected function modelSchema(string $name): array
    {
        $class = rtrim(app()->getNamespace(), '\\')."\\Models\\{$name}";
        $model = class_exists($class) ? new $class : null;
        $table = $model?->getTable() ?? Str::snake(Str::plural($name));
        $properties = [];

        try {
            $schema = ($model?->getConnection() ?? app('db')->connection())->getSchemaBuilder();

            foreach ($schema->hasTable($table) ? $schema->getColumns($table) : [] as $column) {
                $properties[$column['name']] = $this->columnSchema((string) $column['type_name'])
                    + ($column['nullable'] ? ['nullable' => true] : []);
            }

            if (! $properties) {
                $this->warnings[] = "Table '{$table}' not found: run the migration and then php artisan domain:docs {$name}.";
            }
        } catch (Throwable $e) {
            $this->warnings[] = "Could not read the columns of '{$table}': {$e->getMessage()}";
        }

        return ['type' => 'object', 'properties' => $properties ?: [
            'id' => ['type' => 'integer'],
            'created_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
            'updated_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
        ]];
    }

    /**
     * @return array<string, string>
     */
    protected function columnSchema(string $type): array
    {
        $type = strtolower($type);

        return match (true) {
            in_array($type, ['bool', 'boolean'], true) => ['type' => 'boolean'],
            str_contains($type, 'int') => ['type' => 'integer'],
            (bool) preg_match('/float|double|decimal|numeric|real/', $type) => ['type' => 'number'],
            str_contains($type, 'datetime'), str_contains($type, 'timestamp') => ['type' => 'string', 'format' => 'date-time'],
            $type === 'date' => ['type' => 'string', 'format' => 'date'],
            str_contains($type, 'json') => ['type' => 'object'],
            default => ['type' => 'string'],
        };
    }
}
