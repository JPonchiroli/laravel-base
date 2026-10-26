<?php

namespace Jponchiroli\LaravelBase\Tests\Unit;

use Jponchiroli\LaravelBase\Docs\DocsRenderer;
use Jponchiroli\LaravelBase\Docs\OpenApiBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OpenApiBuilderTest extends TestCase
{
    /**
     * @return array<string, array{0: mixed, 1: array<string, mixed>}>
     */
    public static function fields(): array
    {
        return [
            'string with limits' => ['required|string|min:2|max:9', ['type' => 'string', 'minLength' => 2, 'maxLength' => 9]],
            'integer enum is typed' => ['integer|in:1,2', ['type' => 'integer', 'enum' => [1, 2]]],
            'numeric enum is typed' => ['numeric|in:1.5,2', ['type' => 'number', 'enum' => [1.5, 2]]],
            'string enum' => [['string', 'in:red,blue'], ['type' => 'string', 'enum' => ['red', 'blue']]],
            'decimal' => ['decimal:2|min:1', ['type' => 'number', 'minimum' => 1]],
            'boolean' => ['boolean', ['type' => 'boolean']],
            'array with limits' => ['array|min:1|max:3', ['type' => 'array', 'items' => ['type' => 'string'], 'minItems' => 1, 'maxItems' => 3]],
            'file' => ['file', ['type' => 'string', 'format' => 'binary']],
            'email' => ['email', ['type' => 'string', 'format' => 'email']],
            'uuid' => ['uuid', ['type' => 'string', 'format' => 'uuid']],
            'url' => ['url', ['type' => 'string', 'format' => 'uri']],
            'date' => ['date', ['type' => 'string', 'format' => 'date']],
            'nullable' => ['nullable|integer', ['type' => 'integer', 'nullable' => true]],
            'rule objects are ignored' => [[new \stdClass, 'integer'], ['type' => 'integer']],
            'non numeric limit is ignored' => ['string|max:abc', ['type' => 'string']],
        ];
    }

    /**
     * @param  array<string, mixed>  $expected
     */
    #[Test]
    #[DataProvider('fields')]
    public function a_rule_becomes_the_matching_field_schema(mixed $rules, array $expected): void
    {
        $this->assertEquals($expected, $this->builder()->field($rules));
    }

    /**
     * @return array<string, array{0: string, 1: array<string, string>}>
     */
    public static function columns(): array
    {
        return [
            'integer' => ['bigint', ['type' => 'integer']],
            'boolean' => ['boolean', ['type' => 'boolean']],
            'float' => ['float', ['type' => 'number']],
            'decimal' => ['decimal', ['type' => 'number']],
            'datetime' => ['datetime', ['type' => 'string', 'format' => 'date-time']],
            'timestamp' => ['TIMESTAMP', ['type' => 'string', 'format' => 'date-time']],
            'date' => ['date', ['type' => 'string', 'format' => 'date']],
            'json' => ['json', ['type' => 'object']],
            'text' => ['varchar', ['type' => 'string']],
        ];
    }

    /**
     * @param  array<string, string>  $expected
     */
    #[Test]
    #[DataProvider('columns')]
    public function a_column_type_becomes_the_matching_schema(string $type, array $expected): void
    {
        $this->assertSame($expected, $this->builder()->column($type));
    }

    #[Test]
    public function rules_of_a_missing_request_are_empty_and_of_a_throwing_request_warn(): void
    {
        $builder = $this->builder();

        $this->assertSame([], $builder->readRules('Missing\\Request'));
        $this->assertSame([], $builder->warnings);

        $this->assertSame([], $builder->readRules(ThrowingRulesStub::class));
        $this->assertSame(['Could not read the rules of '.ThrowingRulesStub::class.'.'], $builder->warnings);
    }

    #[Test]
    public function a_missing_generator_asks_for_the_dependency(): void
    {
        $builder = new class extends OpenApiBuilder
        {
            protected function hasGenerator(): bool
            {
                return false;
            }
        };

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('composer update jponchiroli/laravel-base');

        $builder->openApiJson();
    }

    #[Test]
    public function the_renderer_prints_nested_nodes_and_escapes_strings(): void
    {
        $source = (new DocsRenderer)->render([
            '@' => 'Schema',
            'schema' => "It's",
            'properties' => [['@' => 'Property', 'property' => 'a\\b', 'type' => 'string']],
            'enum' => ['x', 'y'],
            'example' => [],
        ]);

        $this->assertStringStartsWith("OA\\Schema(\n", $source);
        $this->assertStringContainsString("schema: 'It\\'s',", $source);
        $this->assertStringContainsString("new OA\\Property(\n", $source);
        $this->assertStringContainsString("property: 'a\\\\b',", $source);
        $this->assertStringContainsString("enum: [\n        'x',\n        'y',\n    ],", $source);
        $this->assertStringContainsString('example: [],', $source);
    }

    private function builder(): object
    {
        return new class extends OpenApiBuilder
        {
            public function field(mixed $rules): array
            {
                return $this->fieldSchema($rules);
            }

            public function column(string $type): array
            {
                return $this->columnSchema($type);
            }

            public function readRules(string $request): array
            {
                return $this->rules($request);
            }
        };
    }
}

class ThrowingRulesStub
{
    public function rules(): array
    {
        throw new \RuntimeException('needs the live request');
    }
}
