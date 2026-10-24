<?php

namespace Jponchiroli\LaravelBase\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Jponchiroli\LaravelBase\Base\BaseCollection;
use Jponchiroli\LaravelBase\Base\BaseMessageResource;
use Jponchiroli\LaravelBase\Base\BaseRequest;
use Jponchiroli\LaravelBase\Base\BaseResource;
use Jponchiroli\LaravelBase\Base\BaseStoreResource;
use Jponchiroli\LaravelBase\Tests\Fixtures\Widget;
use Jponchiroli\LaravelBase\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ResourcesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('en');
    }

    /**
     * @return array<string, mixed>
     */
    private function body(mixed $resource): array
    {
        return json_decode($resource->toResponse(Request::create('/'))->getContent(), true);
    }

    #[Test]
    public function resource_wraps_the_model_with_the_default_show_message(): void
    {
        $widget = Widget::create(['name' => 'Bolt']);

        $body = $this->body(new BaseResource($widget));

        $this->assertSame('Bolt', $body['data']['name']);
        $this->assertSame('Record retrieved successfully.', $body['message']);
    }

    #[Test]
    public function resource_accepts_a_custom_message_key(): void
    {
        $this->assertSame('Record updated successfully.', $this->body(new BaseResource(['a' => 1], 'update'))['message']);
    }

    #[Test]
    public function unknown_message_key_falls_back_to_the_translation_key(): void
    {
        $this->assertSame('laravel-base::messages.success.nope', $this->body(new BaseResource(['a' => 1], 'nope'))['message']);
    }

    #[Test]
    public function store_resource_answers_201_with_the_store_message(): void
    {
        $response = (new BaseStoreResource(['a' => 1]))->toResponse(Request::create('/'));

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('Record created successfully.', json_decode($response->getContent(), true)['message']);
    }

    #[Test]
    public function message_resource_has_only_the_message(): void
    {
        $this->assertSame(['message' => 'Record deleted successfully.'], $this->body(new BaseMessageResource));
        $this->assertSame(['message' => 'Record created successfully.'], $this->body(new BaseMessageResource('store')));
    }

    #[Test]
    public function collection_keeps_pagination_and_adds_the_index_message(): void
    {
        $paginator = new LengthAwarePaginator([['a' => 1], ['a' => 2]], 2, 10);

        $collection = BaseResource::collection($paginator);
        $body = $this->body($collection);

        $this->assertInstanceOf(BaseCollection::class, $collection);
        $this->assertCount(2, $body['data']);
        $this->assertSame(2, $body['meta']['total']);
        $this->assertArrayNotHasKey('links', $body);
        $this->assertSame('Records retrieved successfully.', $body['message']);
    }

    #[Test]
    public function every_success_payload_starts_with_the_message(): void
    {
        $widget = Widget::create(['name' => 'Bolt']);

        foreach ([
            new BaseResource($widget),
            new BaseResource($widget, 'update'),
            new BaseStoreResource($widget),
            BaseResource::collection(new LengthAwarePaginator([$widget], 1, 10)),
            BaseResource::collection([$widget]),
        ] as $resource) {
            $keys = array_keys($this->body($resource));

            $this->assertSame(['message', 'data'], array_slice($keys, 0, 2), $resource::class);
        }
    }

    #[Test]
    public function paginated_collection_returns_only_message_data_and_a_compact_meta(): void
    {
        foreach (range(1, 25) as $i) {
            Widget::create(['name' => "W$i"]);
        }

        $body = $this->body(BaseResource::collection(Widget::query()->paginate(10, page: 2)));

        $this->assertSame(['message', 'data', 'meta'], array_keys($body));
        $this->assertSame(['to', 'from', 'total', 'per_page', 'last_page', 'current_page'], array_keys($body['meta']));
        $this->assertSame(['to' => 20, 'from' => 11, 'total' => 25, 'per_page' => 10, 'last_page' => 3, 'current_page' => 2], $body['meta']);
        $this->assertCount(10, $body['data']);
    }

    #[Test]
    public function empty_page_keeps_the_same_shape(): void
    {
        $body = $this->body(BaseResource::collection(Widget::query()->paginate(10)));

        $this->assertSame(['message', 'data', 'meta'], array_keys($body));
        $this->assertSame([], $body['data']);
        $this->assertNull($body['meta']['from']);
        $this->assertNull($body['meta']['to']);
        $this->assertSame(0, $body['meta']['total']);
    }

    #[Test]
    public function reordering_keeps_empty_objects_and_the_created_status(): void
    {
        $response = (new BaseStoreResource(['meta' => new \stdClass, 'list' => []]))->toResponse(Request::create('/'));

        $this->assertSame(201, $response->getStatusCode());
        $this->assertStringStartsWith('{"message":', $response->getContent());
        $this->assertStringContainsString('"meta":{}', $response->getContent());
        $this->assertStringContainsString('"list":[]', $response->getContent());
    }

    #[Test]
    public function messages_follow_the_locale(): void
    {
        app()->setLocale('pt_BR');

        $this->assertSame($this->pt('messages', 'success.store'), $this->body(new BaseStoreResource(['a' => 1]))['message']);
    }

    #[Test]
    public function base_request_authorizes_by_default(): void
    {
        $this->assertTrue((new BaseRequest)->authorize());
    }

    #[Test]
    public function base_request_attributes_come_from_the_lang_file_when_defined(): void
    {
        $this->assertSame([], (new BaseRequest)->attributes());

        app('translator')->addLines(['validation.attributes' => ['name' => 'nome']], 'en', 'laravel-base');

        $this->assertSame(['name' => 'nome'], (new BaseRequest)->attributes());
    }
}
