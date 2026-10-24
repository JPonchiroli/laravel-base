<?php

namespace Jponchiroli\LaravelBase\Tests\Feature;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Router;
use Jponchiroli\LaravelBase\Base\BaseMessageResource;
use Jponchiroli\LaravelBase\Base\BaseRequest;
use Jponchiroli\LaravelBase\Base\BaseResource;
use Jponchiroli\LaravelBase\Base\BaseStoreResource;
use Jponchiroli\LaravelBase\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

class ApiResponseTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        /** @var Router $router */
        $router->get('api/show', fn () => new BaseResource(['a' => 1]));
        $router->put('api/update', fn () => new BaseResource(['a' => 1], 'update'));
        $router->post('api/store', fn () => new BaseStoreResource(['a' => 1]));
        $router->delete('api/destroy', fn () => new BaseMessageResource);
        $router->get('api/index', fn () => BaseResource::collection(new LengthAwarePaginator([['a' => 1]], 1, 10)));
        $router->get('api/validation', fn (ApiTestRequest $request) => $request->validated());
        $router->get('api/missing-model', fn () => throw new ModelNotFoundException);
        $router->get('api/unauthenticated', fn () => throw new AuthenticationException);
        $router->get('api/forbidden', fn () => throw new AuthorizationException);
        $router->get('api/boom', fn () => throw new RuntimeException('secret'));
        $router->get('web/boom', fn () => throw new RuntimeException('secret'));
    }

    #[Test]
    public function success_responses_carry_a_translated_message(): void
    {
        app()->setLocale('en');

        $this->getJson('api/show')->assertOk()->assertJson(['message' => 'Record retrieved successfully.', 'data' => ['a' => 1]]);
        $this->putJson('api/update')->assertOk()->assertJson(['message' => 'Record updated successfully.']);
        $this->postJson('api/store')->assertCreated()->assertJson(['message' => 'Record created successfully.']);
        $this->getJson('api/index')->assertOk()->assertJson(['message' => 'Records retrieved successfully.'])->assertJsonMissingPath('links')->assertJsonStructure(['message', 'data', 'meta' => ['to', 'from', 'total', 'per_page', 'last_page', 'current_page']]);
        $this->deleteJson('api/destroy')->assertOk()->assertExactJson(['message' => 'Record deleted successfully.']);
    }

    #[Test]
    public function messages_follow_the_locale(): void
    {
        app()->setLocale('pt_BR');

        $this->postJson('api/store')->assertJson(['message' => $this->pt('messages', 'success.store')]);
    }

    #[Test]
    public function validation_errors_use_the_request_messages_in_both_locales(): void
    {
        app()->setLocale('pt_BR');
        $this->getJson('api/validation?age=5')
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', $this->pt('messages', 'error.validation'))
            ->assertJsonPath('errors.name.0', $this->pt('validation', 'required', ['attribute' => 'name']))
            ->assertJsonPath('errors.age.0', $this->pt('validation', 'min.numeric', ['attribute' => 'age', 'min' => 18]));

        app()->setLocale('en');
        $this->getJson('api/validation?age=5')
            ->assertStatus(422)
            ->assertJsonPath('errors.name.0', 'The name field is required.')
            ->assertJsonPath('errors.age.0', 'The age field must be at least 18.');
    }

    #[Test]
    public function errors_are_standardized(): void
    {
        app()->setLocale('en');
        config(['app.debug' => false]);

        $this->getJson('api/missing-model')->assertNotFound()->assertExactJson(['success' => false, 'message' => 'Record not found.']);
        $this->getJson('api/unknown-route')->assertNotFound()->assertJson(['success' => false]);
        $this->getJson('api/unauthenticated')->assertUnauthorized()->assertJson(['success' => false, 'message' => 'Unauthenticated.']);
        $this->getJson('api/forbidden')->assertForbidden()->assertJson(['success' => false]);
        $this->postJson('api/show')->assertStatus(405)->assertJson(['success' => false, 'message' => 'Method not allowed.']);
        $this->getJson('api/boom')->assertStatus(500)->assertExactJson(['success' => false, 'message' => 'Internal server error.']);
    }

    #[Test]
    public function server_errors_are_not_masked_in_debug(): void
    {
        config(['app.debug' => true]);

        $this->getJson('api/boom')->assertStatus(500)->assertJsonPath('message', 'secret');
    }

    #[Test]
    public function web_routes_keep_the_default_rendering(): void
    {
        config(['app.debug' => false]);

        $this->get('web/boom')->assertStatus(500)->assertHeaderMissing('X-Standardized');

        $this->assertStringNotContainsString('"success"', $this->get('web/boom')->getContent());
    }
}

class ApiTestRequest extends BaseRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string'],
            'age' => ['sometimes', 'integer', 'min:18'],
        ];
    }
}
