<?php

namespace Jponchiroli\LaravelBase\Tests\Feature;

use App\Http\Controllers\GadgetController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Schema;
use Jponchiroli\LaravelBase\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Runs make:domain, fills in the empty DTOs/Requests the way a developer would,
 * and drives the generated classes through the whole HTTP cycle.
 */
class GeneratedDomainCrudTest extends TestCase
{
    private Filesystem $files;

    /**
     * @var list<string>
     */
    private array $load = [
        'Models/Gadget.php',
        'DTOs/Gadget/GadgetCreateDTO.php',
        'DTOs/Gadget/GadgetUpdateDTO.php',
        'DTOs/Gadget/GadgetPatchDTO.php',
        'DTOs/Gadget/GadgetIndexDTO.php',
        'DTOs/Gadget/GadgetIdDTO.php',
        'Repositories/Gadget/GadgetRepository.php',
        'Services/Gadget/GadgetService.php',
        'Http/Requests/Gadget/GadgetIndexRequest.php',
        'Http/Requests/Gadget/GadgetStoreRequest.php',
        'Http/Requests/Gadget/GadgetUpdateRequest.php',
        'Http/Requests/Gadget/GadgetPatchRequest.php',
        'Http/Requests/Gadget/GadgetDeleteRequest.php',
        'Http/Controllers/Controller.php',
        'Http/Controllers/GadgetController.php',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem;
        $this->cleanup();

        $this->files->ensureDirectoryExists(app_path('Http/Controllers'));
        $this->files->put(app_path('Http/Controllers/Controller.php'), "<?php\n\nnamespace App\\Http\\Controllers;\n\nabstract class Controller {}\n");

        $this->artisan('make:domain', ['name' => 'Gadget', '--no-routes' => true])->assertSuccessful();
        $this->fillIn();

        foreach ($this->load as $path) {
            require_once app_path($path);
        }

        Schema::create('gadgets', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('color')->nullable();
            $table->timestamps();
        });

        app()->setLocale('en');
    }

    protected function tearDown(): void
    {
        $this->cleanup();

        parent::tearDown();
    }

    protected function defineRoutes($router): void
    {
        $router->prefix('api/gadgets')->group(function ($router): void {
            $controller = GadgetController::class;
            $router->get('/', [$controller, 'index']);
            $router->get('{id}', [$controller, 'show']);
            $router->post('/', [$controller, 'store']);
            $router->put('{id}', [$controller, 'update']);
            $router->patch('{id}', [$controller, 'patch']);
            $router->delete('{id}', [$controller, 'destroy']);
        });
    }

    #[Test]
    public function store_validates_and_creates(): void
    {
        $this->postJson('api/gadgets', [])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.name.0', 'The name field is required.');

        $this->postJson('api/gadgets', ['name' => 'Bolt', 'color' => 'red'])
            ->assertCreated()
            ->assertJsonPath('message', 'Record created successfully.')
            ->assertJsonPath('data.name', 'Bolt');

        $this->assertDatabaseHas('gadgets', ['name' => 'Bolt', 'color' => 'red']);
    }

    #[Test]
    public function index_paginates_and_filters(): void
    {
        foreach (['A' => 'red', 'B' => 'blue', 'C' => 'red'] as $name => $color) {
            $this->postJson('api/gadgets', ['name' => $name, 'color' => $color])->assertCreated();
        }

        $this->getJson('api/gadgets')->assertOk()->assertJsonPath('meta.total', 3)->assertJsonPath('message', 'Records retrieved successfully.');
        $this->getJson('api/gadgets?color=red')->assertOk()->assertJsonPath('meta.total', 2);
        $this->getJson('api/gadgets?per_page=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.last_page', 3);
        $this->getJson('api/gadgets?per_page=0')->assertStatus(422)->assertJsonPath('errors.per_page.0', 'The items per page field must be at least 1.');
    }

    #[Test]
    public function show_returns_the_record_or_404(): void
    {
        $id = $this->postJson('api/gadgets', ['name' => 'Bolt'])->json('data.id');

        $this->getJson("api/gadgets/$id")->assertOk()->assertJsonPath('data.name', 'Bolt');
        $this->getJson('api/gadgets/999')->assertNotFound()->assertExactJson(['success' => false, 'message' => 'Record not found.']);
    }

    #[Test]
    public function update_changes_the_record_or_404(): void
    {
        $id = $this->postJson('api/gadgets', ['name' => 'Bolt', 'color' => 'red'])->json('data.id');

        $this->putJson("api/gadgets/$id", ['name' => 'Nut'])
            ->assertOk()
            ->assertJsonPath('message', 'Record updated successfully.')
            ->assertJsonPath('data.name', 'Nut');

        $this->assertDatabaseHas('gadgets', ['id' => $id, 'name' => 'Nut', 'color' => 'red']);

        $this->putJson("api/gadgets/$id", ['name' => 'Nut', 'color' => null])->assertOk();
        $this->assertDatabaseHas('gadgets', ['id' => $id, 'name' => 'Nut', 'color' => null]);
        $this->putJson("api/gadgets/$id", [])->assertStatus(422);
        $this->putJson('api/gadgets/999', ['name' => 'Nut'])->assertNotFound();
    }

    #[Test]
    public function patch_changes_only_the_sent_fields(): void
    {
        $id = $this->postJson('api/gadgets', ['name' => 'Bolt', 'color' => 'red'])->json('data.id');

        $this->patchJson("api/gadgets/$id", ['name' => 'Nut'])
            ->assertOk()
            ->assertJsonPath('message', 'Record updated successfully.')
            ->assertJsonPath('data.name', 'Nut')
            ->assertJsonPath('data.color', 'red');

        $this->assertDatabaseHas('gadgets', ['id' => $id, 'name' => 'Nut', 'color' => 'red']);

        $this->patchJson("api/gadgets/$id", ['color' => 'blue'])->assertOk();
        $this->assertDatabaseHas('gadgets', ['id' => $id, 'name' => 'Nut', 'color' => 'blue']);
    }

    #[Test]
    public function patch_works_with_the_generated_dto_without_any_code(): void
    {
        $this->assertStringContainsString(
            'class GadgetPatchDTO extends BasePatchDTO {}',
            $this->files->get(app_path('DTOs/Gadget/GadgetPatchDTO.php')),
        );

        $id = $this->postJson('api/gadgets', ['name' => 'Bolt', 'color' => 'red'])->json('data.id');

        $this->patchJson("api/gadgets/$id", ['name' => 'Nut', 'color' => 'blue'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Nut')
            ->assertJsonPath('data.color', 'blue');

        $this->assertDatabaseHas('gadgets', ['id' => $id, 'name' => 'Nut', 'color' => 'blue']);
    }

    #[Test]
    public function every_success_response_puts_the_message_before_the_data(): void
    {
        $id = $this->postJson('api/gadgets', ['name' => 'Bolt'])->json('data.id');

        $responses = [
            $this->postJson('api/gadgets', ['name' => 'Nut']),
            $this->getJson("api/gadgets/$id"),
            $this->putJson("api/gadgets/$id", ['name' => 'Bolt 2']),
            $this->patchJson("api/gadgets/$id", ['color' => 'red']),
            $this->getJson('api/gadgets'),
        ];

        foreach ($responses as $response) {
            $this->assertSame(['message', 'data'], array_slice(array_keys($response->json()), 0, 2));
        }

        $this->assertSame(['message', 'data', 'meta'], array_keys($responses[4]->json()));
        $this->deleteJson("api/gadgets/$id")->assertExactJson(['message' => 'Record deleted successfully.']);
    }

    #[Test]
    public function patch_ignores_null_values_and_empty_bodies(): void
    {
        $id = $this->postJson('api/gadgets', ['name' => 'Bolt', 'color' => 'red'])->json('data.id');

        $this->patchJson("api/gadgets/$id", ['color' => null])->assertOk()->assertJsonPath('data.color', 'red');
        $this->patchJson("api/gadgets/$id", [])->assertOk()->assertJsonPath('data.name', 'Bolt');
        $this->assertDatabaseHas('gadgets', ['id' => $id, 'name' => 'Bolt', 'color' => 'red']);
    }

    #[Test]
    public function patch_validates_the_sent_fields(): void
    {
        $id = $this->postJson('api/gadgets', ['name' => 'Bolt'])->json('data.id');

        $this->patchJson("api/gadgets/$id", ['name' => str_repeat('x', 51)])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.name.0', 'The name field must not be longer than 50 characters.');

        $this->assertDatabaseHas('gadgets', ['id' => $id, 'name' => 'Bolt']);
    }

    #[Test]
    public function patch_answers_404_for_a_missing_record(): void
    {
        $this->patchJson('api/gadgets/999', ['name' => 'Nut'])
            ->assertNotFound()
            ->assertExactJson(['success' => false, 'message' => 'Record not found.']);
    }

    #[Test]
    public function patch_is_translated_to_portuguese(): void
    {
        $id = $this->postJson('api/gadgets', ['name' => 'Bolt'])->json('data.id');
        app()->setLocale('pt_BR');

        $this->patchJson("api/gadgets/$id", ['name' => 'Nut'])->assertJsonPath('message', $this->pt('messages', 'success.update'));
        $this->patchJson("api/gadgets/$id", ['name' => str_repeat('x', 51)])
            ->assertJsonPath('errors.name.0', $this->pt('validation', 'max.string', ['attribute' => 'name', 'max' => 50]));
        $this->patchJson('api/gadgets/999', [])->assertNotFound()->assertJsonPath('message', $this->pt('messages', 'error.not_found'));
    }

    #[Test]
    public function destroy_deletes_the_record_or_404(): void
    {
        $id = $this->postJson('api/gadgets', ['name' => 'Bolt'])->json('data.id');

        $this->deleteJson("api/gadgets/$id")->assertOk()->assertExactJson(['message' => 'Record deleted successfully.']);
        $this->assertDatabaseMissing('gadgets', ['id' => $id]);
        $this->deleteJson("api/gadgets/$id")->assertNotFound()->assertJsonPath('success', false);
    }

    #[Test]
    public function responses_are_translated_to_portuguese(): void
    {
        app()->setLocale('pt_BR');

        $this->postJson('api/gadgets', [])->assertStatus(422)
            ->assertJsonPath('message', $this->pt('messages', 'error.validation'))
            ->assertJsonPath('errors.name.0', $this->pt('validation', 'required', ['attribute' => 'name']));
        $this->postJson('api/gadgets', ['name' => 'Bolt'])->assertJsonPath('message', $this->pt('messages', 'success.store'));
        $this->getJson('api/gadgets/999')->assertNotFound()->assertJsonPath('message', $this->pt('messages', 'error.not_found'));
    }

    private function fillIn(): void
    {
        $put = fn (string $path, string $content) => $this->files->put(app_path($path), $content);

        $put('Models/Gadget.php', <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Gadget extends Model
{
    protected $fillable = ['name', 'color'];
}
PHP);

        $put('Http/Requests/Gadget/GadgetPatchRequest.php', <<<'PHP'
<?php

namespace App\Http\Requests\Gadget;

use Jponchiroli\LaravelBase\Base\BaseRequest;

class GadgetPatchRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:50'],
            'color' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
PHP);

        $put('Http/Requests/Gadget/GadgetIndexRequest.php', <<<'PHP'
<?php

namespace App\Http\Requests\Gadget;

use Jponchiroli\LaravelBase\Base\BaseRequest;

class GadgetIndexRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'color' => ['sometimes', 'string'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function attributes(): array
    {
        return ['per_page' => 'items per page'];
    }
}
PHP);

        foreach (['Store', 'Update'] as $action) {
            $put("Http/Requests/Gadget/Gadget{$action}Request.php", <<<PHP
<?php

namespace App\Http\Requests\Gadget;

use Jponchiroli\LaravelBase\Base\BaseRequest;

class Gadget{$action}Request extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'color' => ['nullable', 'string'],
        ];
    }
}
PHP);
        }
    }

    private function cleanup(): void
    {
        $this->files->delete(app_path('Http/Controllers/Controller.php'));
        $this->files->delete(app_path('Http/Controllers/GadgetController.php'));

        foreach (['Models', 'Services', 'Repositories', 'DTOs'] as $directory) {
            $this->files->deleteDirectory(app_path($directory));
        }

        $this->files->deleteDirectory(app_path('Http/Requests'));
    }
}
