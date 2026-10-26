<?php

namespace Jponchiroli\LaravelBase\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Schema;
use Jponchiroli\LaravelBase\Docs\OpenApiBuilder;
use Jponchiroli\LaravelBase\Tests\TestCase;
use OpenApi\Generator;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\AbstractLogger;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

class DomainDocsTest extends TestCase
{
    private Filesystem $files;

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem;
        $this->cleanup();

        $this->files->ensureDirectoryExists(app_path('Http/Controllers'));
        $this->files->put(app_path('Http/Controllers/Controller.php'), "<?php\n\nnamespace App\\Http\\Controllers;\n\nabstract class Controller {}\n");

        $this->artisan('make:domain', ['name' => 'Sprocket', '--no-routes' => true])->assertSuccessful();

        $this->files->put(app_path('Http/Requests/Sprocket/SprocketStoreRequest.php'), <<<'PHP'
            <?php

            namespace App\Http\Requests\Sprocket;

            use Jponchiroli\LaravelBase\Base\BaseRequest;

            class SprocketStoreRequest extends BaseRequest
            {
                public function rules(): array
                {
                    return [
                        'name' => ['required', 'string', 'max:50'],
                        'color' => ['nullable', 'string', 'in:red,blue'],
                        'qty' => 'sometimes|integer|min:1',
                    ];
                }
            }
            PHP);

        // Loaded classes cannot be redefined, so route-based assertions only use content that never changes between tests.
        spl_autoload_register(function (string $class): void {
            $file = app_path(str_replace(['App\\', '\\'], ['', '/'], $class).'.php');

            if (str_starts_with($class, 'App\\Docs\\') && file_exists($file)) {
                require_once $file;
            }
        });

        foreach (['Models/Sprocket.php', 'Http/Requests/Sprocket/SprocketIndexRequest.php', 'Http/Requests/Sprocket/SprocketStoreRequest.php'] as $path) {
            require_once app_path($path);
        }
    }

    protected function tearDown(): void
    {
        $this->cleanup();

        parent::tearDown();
    }

    #[Test]
    public function make_domain_creates_the_base_and_the_domain_classes(): void
    {
        $this->assertFileExists(app_path('Docs/SchemaDocs.php'));
        $this->assertFileExists(app_path('Docs/Sprocket/SprocketDocs.php'));
        $this->assertStringContainsString("#[OA\\Tag(name: 'Sprocket')]\nclass SchemaDocs", file_get_contents(app_path('Docs/SchemaDocs.php')));
    }

    #[Test]
    public function the_generated_files_are_valid_php(): void
    {
        $php = (new PhpExecutableFinder)->find();

        foreach ([app_path('Docs/SchemaDocs.php'), app_path('Docs/Sprocket/SprocketDocs.php')] as $file) {
            $process = new Process([$php, '-l', $file]);
            $process->run();

            $this->assertTrue($process->isSuccessful(), $process->getOutput().$process->getErrorOutput());
        }
    }

    #[Test]
    public function the_base_keeps_one_tag_per_domain_and_the_shared_schemas(): void
    {
        $this->artisan('make:domain', ['name' => 'Cog', '--no-routes' => true])->assertSuccessful();
        $this->artisan('make:domain', ['name' => 'Cog', '--no-routes' => true])->assertSuccessful();

        $base = file_get_contents(app_path('Docs/SchemaDocs.php'));

        $this->assertSame(1, substr_count($base, "OA\\Tag(name: 'Cog')"));
        $this->assertSame(1, substr_count($base, "OA\\Tag(name: 'Sprocket')"));

        $this->assertStringContainsString('class CogDocs', file_get_contents(app_path('Docs/Cog/CogDocs.php')));

        $schemas = $this->document()['components']['schemas'];

        $this->assertArrayHasKey('Error', $schemas);
        $this->assertArrayHasKey('ValidationError', $schemas);
        $this->assertArrayHasKey('Message', $schemas);
    }

    #[Test]
    public function the_document_has_the_six_endpoints_and_the_payloads_of_the_request_rules(): void
    {
        $document = $this->document();

        $this->assertSame(['get', 'post'], array_keys($document['paths']['/api/v1/sprockets']));
        $this->assertEqualsCanonicalizing(['get', 'put', 'patch', 'delete'], array_keys($document['paths']['/api/v1/sprockets/{id}']));

        $body = $document['components']['schemas']['SprocketStoreRequest'];

        $this->assertSame('Create Sprocket', $body['title']);
        $this->assertSame('Sprocket resource', $document['components']['schemas']['SprocketResource']['title']);
        $this->assertSame('Sprocket collection', $document['components']['schemas']['SprocketCollection']['title']);
        $this->assertSame('Validation error', $document['components']['schemas']['ValidationError']['title']);
        $this->assertSame(['name'], $body['required']);
        $this->assertSame('string', $body['properties']['name']['type']);
        $this->assertSame(50, $body['properties']['name']['maxLength']);
        $this->assertSame(['red', 'blue'], $body['properties']['color']['enum']);
        $this->assertSame(1, $body['properties']['qty']['minimum']);
        $this->assertSame(
            '#/components/schemas/SprocketStoreRequest',
            $document['paths']['/api/v1/sprockets']['post']['requestBody']['content']['application/json']['schema']['$ref'],
        );

        $query = $document['paths']['/api/v1/sprockets']['get']['parameters'][0];
        $this->assertSame(['per_page', 'query', 100], [$query['name'], $query['in'], $query['schema']['maximum']]);
        $this->assertSame('#/components/schemas/ValidationError', $document['paths']['/api/v1/sprockets']['post']['responses']['422']['content']['application/json']['schema']['$ref']);
    }

    #[Test]
    public function domain_docs_updates_the_model_schema_after_the_table_exists(): void
    {
        $source = fn () => file_get_contents(app_path('Docs/Sprocket/SprocketDocs.php'));

        $this->assertStringContainsString("property: 'created_at'", $source());
        $this->assertStringNotContainsString("property: 'active'", $source());

        $this->createTable();

        $this->artisan('domain:docs', ['name' => 'sprocket'])->assertSuccessful();

        $this->assertStringContainsString("property: 'active'", $source());
        $this->assertStringContainsString("property: 'name'", $source());
    }

    #[Test]
    public function domain_docs_fails_without_writing_when_the_table_is_missing(): void
    {
        file_put_contents(app_path('Docs/Sprocket/SprocketDocs.php'), 'untouched');

        $this->artisan('domain:docs', ['name' => 'Sprocket'])
            ->expectsOutputToContain("Table 'sprockets' not found")
            ->assertFailed();

        $this->assertSame('untouched', file_get_contents(app_path('Docs/Sprocket/SprocketDocs.php')));
    }

    #[Test]
    public function domain_docs_fails_without_writing_when_the_domain_does_not_exist(): void
    {
        $this->artisan('domain:docs', ['name' => 'Ghost'])
            ->expectsOutputToContain('Domain Ghost not found')
            ->assertFailed();

        $this->assertDirectoryDoesNotExist(app_path('Docs/Ghost'));
        $this->assertStringNotContainsString('Ghost', file_get_contents(app_path('Docs/SchemaDocs.php')));
    }

    #[Test]
    public function the_openapi_json_file_is_written_by_make_domain_and_domain_docs(): void
    {
        $path = storage_path('api-docs/openapi.json');

        $this->assertFileExists($path);

        unlink($path);
        $this->createTable();
        $this->artisan('domain:docs', ['name' => 'Sprocket'])->assertSuccessful();

        $this->assertIsArray(json_decode(file_get_contents($path), true));
    }

    #[Test]
    public function no_docs_does_not_write_the_openapi_json(): void
    {
        unlink(storage_path('api-docs/openapi.json'));

        $this->artisan('make:domain', ['name' => 'Gear', '--no-routes' => true, '--no-docs' => true])->assertSuccessful();

        $this->assertFileDoesNotExist(storage_path('api-docs/openapi.json'));
    }

    #[Test]
    public function the_route_serves_the_written_file_and_generates_when_it_is_missing(): void
    {
        file_put_contents(storage_path('api-docs/openapi.json'), '{"marker":true}');

        $this->getJson('docs/openapi.json')->assertExactJson(['marker' => true]);

        unlink(storage_path('api-docs/openapi.json'));

        $this->getJson('docs/openapi.json')->assertOk()->assertJsonPath('openapi', '3.0.0');
    }

    #[Test]
    public function no_docs_skips_the_classes(): void
    {
        $this->artisan('make:domain', ['name' => 'Gear', '--no-routes' => true, '--no-docs' => true])->assertSuccessful();

        $this->assertFileDoesNotExist(app_path('Docs/Gear/GearDocs.php'));
        $this->assertStringNotContainsString('Gear', file_get_contents(app_path('Docs/SchemaDocs.php')));
    }

    #[Test]
    public function make_domain_never_overwrites_an_existing_class(): void
    {
        file_put_contents(app_path('Docs/Sprocket/SprocketDocs.php'), 'custom');

        $this->artisan('make:domain', ['name' => 'Sprocket', '--no-routes' => true])->assertSuccessful();

        $this->assertSame('custom', file_get_contents(app_path('Docs/Sprocket/SprocketDocs.php')));
    }

    #[Test]
    public function domain_docs_without_a_name_regenerates_every_class(): void
    {
        file_put_contents(app_path('Docs/Sprocket/SprocketDocs.php'), 'stale');
        $this->createTable();

        $this->artisan('domain:docs')->assertSuccessful();

        $this->assertStringContainsString('class SprocketDocs', file_get_contents(app_path('Docs/Sprocket/SprocketDocs.php')));
    }

    #[Test]
    public function domain_docs_without_a_name_creates_the_docs_of_every_available_domain(): void
    {
        $this->createTable();
        $this->files->deleteDirectory(app_path('Docs'));

        $this->artisan('domain:docs')->assertSuccessful();

        $this->assertFileExists(app_path('Docs/Sprocket/SprocketDocs.php'));
        $this->assertStringContainsString("OA\Tag(name: 'Sprocket')", file_get_contents(app_path('Docs/SchemaDocs.php')));
        $this->assertArrayHasKey('/api/v1/sprockets', $this->document()['paths']);
    }

    #[Test]
    public function domain_docs_without_a_name_keeps_going_after_a_domain_that_cannot_be_documented(): void
    {
        $this->createTable();
        $this->artisan('make:domain', ['name' => 'Cog', '--no-routes' => true, '--no-migration' => true])->assertSuccessful();
        file_put_contents(app_path('Docs/Sprocket/SprocketDocs.php'), 'stale');

        $this->artisan('domain:docs')
            ->expectsOutputToContain('Domain Cog not found')
            ->assertFailed();

        $this->assertStringContainsString('class SprocketDocs', file_get_contents(app_path('Docs/Sprocket/SprocketDocs.php')));
    }

    #[Test]
    public function a_base_without_the_class_line_warns_instead_of_losing_the_tag(): void
    {
        file_put_contents(app_path('Docs/SchemaDocs.php'), "<?php\n\nfinal class SchemaDocs {}\n");

        $this->artisan('make:domain', ['name' => 'Cog', '--no-routes' => true, '--no-migration' => true])
            ->expectsOutputToContain("Could not add the tag 'Cog' to SchemaDocs")
            ->assertSuccessful();

        $this->assertStringNotContainsString('Cog', file_get_contents(app_path('Docs/SchemaDocs.php')));
    }

    #[Test]
    public function a_json_that_cannot_be_written_only_warns(): void
    {
        $this->createTable();
        $this->files->deleteDirectory(storage_path('api-docs'));
        file_put_contents(storage_path('api-docs'), 'not a directory');

        try {
            $this->artisan('domain:docs', ['name' => 'Sprocket'])
                ->expectsOutputToContain('Could not write openapi.json')
                ->assertSuccessful();
        } finally {
            unlink(storage_path('api-docs'));
        }
    }

    #[Test]
    public function the_generated_document_is_a_valid_openapi_document(): void
    {
        $this->createTable();
        $this->artisan('domain:docs', ['name' => 'Sprocket'])->assertSuccessful();

        $errors = [];
        $logger = new class($errors) extends AbstractLogger
        {
            public function __construct(public array &$errors) {}

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                if ($level !== 'debug') {
                    $this->errors[] = "{$level}: {$message}";
                }
            }
        };

        $openApi = (new Generator($logger))->generate([app_path('Docs')], validate: true);

        $this->assertNotNull($openApi);
        $this->assertSame([], $errors);
        $this->assertTrue($openApi->validate());
    }

    #[Test]
    public function rules_that_throw_warn_and_nested_fields_are_skipped(): void
    {
        $this->artisan('make:domain', ['name' => 'Rotor', '--no-routes' => true, '--no-migration' => true])->assertSuccessful();

        $request = fn (string $action, string $body) => file_put_contents(app_path("Http/Requests/Rotor/Rotor{$action}Request.php"), <<<PHP2
            <?php

            namespace App\Http\Requests\Rotor;

            use Jponchiroli\LaravelBase\Base\BaseRequest;

            class Rotor{$action}Request extends BaseRequest
            {
                public function rules(): array
                {
                    {$body}
                }
            }
            PHP2);

        $request('Store', "return ['name' => 'string', 'items.*' => 'string'];");
        $request('Update', "throw new \RuntimeException('needs the live request');");

        foreach (['Models/Rotor.php', 'Http/Requests/Rotor/RotorStoreRequest.php', 'Http/Requests/Rotor/RotorUpdateRequest.php'] as $path) {
            require_once app_path($path);
        }

        Schema::create('rotors', function (Blueprint $table): void {
            $table->id();
        });

        $this->artisan('domain:docs', ['name' => 'Rotor'])
            ->expectsOutputToContain('Could not read the rules of App\Http\Requests\Rotor\RotorUpdateRequest')
            ->assertSuccessful();

        $source = file_get_contents(app_path('Docs/Rotor/RotorDocs.php'));

        $this->assertStringContainsString("property: 'name'", $source);
        $this->assertStringNotContainsString("property: 'items", $source);
    }

    #[Test]
    public function an_unreachable_connection_is_reported_instead_of_thrown(): void
    {
        file_put_contents(app_path('Models/Valve.php'), "<?php\n\nnamespace App\Models;\n\nuse Illuminate\Database\Eloquent\Model;\n\nclass Valve extends Model\n{\n    protected \$connection = 'nowhere';\n}\n");
        require_once app_path('Models/Valve.php');

        $this->artisan('domain:docs', ['name' => 'Valve'])
            ->expectsOutputToContain("Could not read the table 'valves'")
            ->assertFailed();

        $builder = new OpenApiBuilder;
        $builder->domainSource('Valve');

        $this->assertStringContainsString("Could not read the columns of 'valves'", implode("\n", $builder->warnings));
    }

    #[Test]
    public function domain_docs_rejects_an_invalid_name(): void
    {
        $this->artisan('domain:docs', ['name' => '9'])->assertFailed();
    }

    #[Test]
    public function swagger_ui_page_points_to_the_json(): void
    {
        $this->get('docs')->assertOk()->assertSee('swagger-ui')->assertSee('/docs/openapi.json', false)->assertSee('integrity="sha384-', false);
    }

    private function createTable(): void
    {
        Schema::create('sprockets', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->boolean('active')->nullable();
            $table->timestamps();
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function document(): array
    {
        $response = $this->getJson('docs/openapi.json')->assertOk();

        return json_decode($response->getContent(), true);
    }

    private function cleanup(): void
    {
        $this->files->delete(app_path('Http/Controllers/Controller.php'));
        $this->files->deleteDirectory(app_path('Docs'));

        foreach (['Models', 'Services', 'Repositories', 'DTOs', 'Http/Requests'] as $directory) {
            $this->files->deleteDirectory(app_path($directory));
        }
    }
}
