<?php

namespace Jponchiroli\LaravelBase\Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use Jponchiroli\LaravelBase\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

class MakeDomainCommandTest extends TestCase
{
    private Filesystem $files;

    /**
     * @var list<string>
     */
    private array $generated = [
        'Models',
        'Http/Controllers/NoteController.php',
        'Http/Controllers/BlogPostController.php',
        'Http/Requests',
        'Services',
        'Repositories',
        'DTOs',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem;
        $this->removeGenerated();
    }

    protected function tearDown(): void
    {
        $this->removeGenerated();
        $this->files->deleteDirectory(base_path('stubs/laravel-base'));

        parent::tearDown();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function expectedFiles(): array
    {
        return [
            'model' => ['Models/Note.php'],
            'controller' => ['Http/Controllers/NoteController.php'],
            'service' => ['Services/Note/NoteService.php'],
            'repository' => ['Repositories/Note/NoteRepository.php'],
            'create dto' => ['DTOs/Note/NoteCreateDTO.php'],
            'update dto' => ['DTOs/Note/NoteUpdateDTO.php'],
            'patch dto' => ['DTOs/Note/NotePatchDTO.php'],
            'index dto' => ['DTOs/Note/NoteIndexDTO.php'],
            'id dto' => ['DTOs/Note/NoteIdDTO.php'],
            'index request' => ['Http/Requests/Note/NoteIndexRequest.php'],
            'store request' => ['Http/Requests/Note/NoteStoreRequest.php'],
            'update request' => ['Http/Requests/Note/NoteUpdateRequest.php'],
            'patch request' => ['Http/Requests/Note/NotePatchRequest.php'],
            'delete request' => ['Http/Requests/Note/NoteDeleteRequest.php'],
        ];
    }

    #[Test]
    #[DataProvider('expectedFiles')]
    public function it_creates_each_domain_file(string $path): void
    {
        $this->artisan('make:domain', ['name' => 'Note'])->assertSuccessful();

        $this->assertFileExists(app_path($path));
    }

    #[Test]
    public function it_generates_valid_php_without_placeholders(): void
    {
        $this->artisan('make:domain', ['name' => 'Note'])->assertSuccessful();

        foreach (self::expectedFiles() as [$path]) {
            $this->assertStringNotContainsString('{{', $this->files->get(app_path($path)), $path);
            $this->assertValidPhp(app_path($path));
        }
    }

    #[Test]
    public function it_extends_the_base_classes(): void
    {
        $this->artisan('make:domain', ['name' => 'Note'])->assertSuccessful();

        $this->assertFileContains('Services/Note/NoteService.php', 'class NoteService extends BaseService');
        $this->assertFileContains('Repositories/Note/NoteRepository.php', 'class NoteRepository extends BaseRepositoryImpl');
        $this->assertFileContains('DTOs/Note/NoteCreateDTO.php', 'class NoteCreateDTO extends BaseCreateDTO');
        $this->assertFileContains('DTOs/Note/NoteUpdateDTO.php', 'class NoteUpdateDTO extends BaseUpdateDTO');
        $this->assertFileContains('DTOs/Note/NoteIndexDTO.php', 'class NoteIndexDTO extends BaseIndexDTO');
        $this->assertFileContains('Http/Requests/Note/NoteStoreRequest.php', 'class NoteStoreRequest extends BaseRequest');
        $this->assertFileContains('Http/Requests/Note/NoteDeleteRequest.php', 'class NoteDeleteRequest extends BaseRequest');
    }

    #[Test]
    public function controller_exposes_patch_backed_by_the_patch_dto_and_request(): void
    {
        $this->artisan('make:domain', ['name' => 'Note'])->assertSuccessful();

        $path = 'Http/Controllers/NoteController.php';

        $this->assertFileContains($path, 'public function patch(NotePatchRequest $request, string $id): BaseResource');
        $this->assertFileContains($path, 'NotePatchDTO::fromArray([...$request->validated(), \'id\' => $id])');
        $this->assertFileContains($path, '$this->service->patch($dto) ?? abort(404)');
        $this->assertFileContains('DTOs/Note/NotePatchDTO.php', 'class NotePatchDTO extends BasePatchDTO');
        $this->assertFileContains('Http/Requests/Note/NotePatchRequest.php', 'class NotePatchRequest extends BaseRequest');
    }

    #[Test]
    public function destroy_answers_404_when_the_record_is_missing(): void
    {
        $this->artisan('make:domain', ['name' => 'Note'])->assertSuccessful();

        $this->assertFileContains('Http/Controllers/NoteController.php', '|| abort(404);');
        $this->assertStringNotContainsString('exists', $this->files->get(app_path('Http/Requests/Note/NoteDeleteRequest.php')));
    }

    #[Test]
    public function it_creates_the_migration_of_the_domain_once(): void
    {
        $this->artisan('make:domain', ['name' => 'blog_post'])->assertSuccessful();
        $this->artisan('make:domain', ['name' => 'blog_post'])->expectsOutputToContain('Migration already exists: create_blog_posts_table')->assertSuccessful();

        $migrations = glob(database_path('migrations/*_create_blog_posts_table.php'));

        $this->assertCount(1, $migrations);
        $this->assertStringContainsString("Schema::create('blog_posts'", file_get_contents($migrations[0]));
    }

    #[Test]
    public function no_migration_skips_the_migration(): void
    {
        $this->artisan('make:domain', ['name' => 'Note', '--no-migration' => true])->assertSuccessful();

        $this->assertSame([], glob(database_path('migrations/*_create_notes_table.php')));
    }

    #[Test]
    public function it_normalizes_the_name_and_namespace(): void
    {
        $this->artisan('make:domain', ['name' => 'blog_post'])->assertSuccessful();

        $path = 'Http/Controllers/BlogPostController.php';

        $this->assertFileContains($path, 'namespace App\Http\Controllers;');
        $this->assertFileContains($path, 'class BlogPostController extends Controller');
        $this->assertFileContains($path, 'use App\Services\BlogPost\BlogPostService;');
        $this->assertFileContains($path, '$blogPosts = $this->service->getPaginate');
        $this->assertFileExists(app_path('Services/BlogPost/BlogPostService.php'));
    }

    #[Test]
    public function it_does_not_overwrite_existing_files(): void
    {
        $this->files->ensureDirectoryExists(app_path('Models'));
        $this->files->put(app_path('Models/Note.php'), 'original');

        $this->artisan('make:domain', ['name' => 'Note'])
            ->expectsOutputToContain('Already exists: '.app_path('Models/Note.php'))
            ->assertSuccessful();

        $this->assertSame('original', $this->files->get(app_path('Models/Note.php')));
        $this->assertFileExists(app_path('Services/Note/NoteService.php'));
    }

    #[Test]
    public function it_prefers_published_stubs(): void
    {
        $this->files->ensureDirectoryExists(base_path('stubs/laravel-base'));
        $this->files->put(base_path('stubs/laravel-base/model.stub'), '<?php // custom {{ class }}');

        $this->artisan('make:domain', ['name' => 'Note'])->assertSuccessful();

        $this->assertSame('<?php // custom Note', $this->files->get(app_path('Models/Note.php')));
        $this->assertFileContains('Services/Note/NoteService.php', 'class NoteService extends BaseService');
    }

    #[Test]
    public function it_publishes_the_stubs(): void
    {
        $this->artisan('vendor:publish', ['--tag' => 'laravel-base-stubs'])->assertSuccessful();

        $this->assertFileExists(base_path('stubs/laravel-base/controller.stub'));
    }

    #[Test]
    public function it_rejects_invalid_names(): void
    {
        $this->artisan('make:domain', ['name' => '123'])->expectsOutputToContain('Invalid name: 123')->assertFailed();

        $this->assertDirectoryDoesNotExist(app_path('Services'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function equivalentNames(): array
    {
        return ['snake case' => ['blog_post'], 'studly case' => ['BlogPost'], 'camel case' => ['blogPost'], 'kebab case' => ['blog-post']];
    }

    #[Test]
    #[DataProvider('equivalentNames')]
    public function equivalent_names_generate_the_same_files(string $name): void
    {
        $this->artisan('make:domain', ['name' => $name])->assertSuccessful();

        $this->assertFileExists(app_path('Models/BlogPost.php'));
        $this->assertFileExists(app_path('Services/BlogPost/BlogPostService.php'));
        $this->assertFileExists(app_path('Http/Requests/BlogPost/BlogPostPatchRequest.php'));
    }

    #[Test]
    public function it_is_idempotent(): void
    {
        $this->artisan('make:domain', ['name' => 'Note'])->assertSuccessful();

        $before = [];
        foreach (self::expectedFiles() as [$path]) {
            $before[$path] = $this->files->get(app_path($path));
            $this->files->append(app_path($path), "// edited\n");
        }

        $this->artisan('make:domain', ['name' => 'Note'])->assertSuccessful();

        foreach ($before as $path => $content) {
            $this->assertSame($content."// edited\n", $this->files->get(app_path($path)), $path);
        }
    }

    #[Test]
    public function an_existing_model_is_skipped_but_the_rest_is_generated(): void
    {
        $this->files->ensureDirectoryExists(app_path('Models'));
        $this->files->put(app_path('Models/Post.php'), '<?php // mine');

        $this->artisan('make:domain', ['name' => 'Post'])
            ->expectsOutputToContain('Already exists')
            ->assertSuccessful();

        $this->assertSame('<?php // mine', $this->files->get(app_path('Models/Post.php')));
        $this->assertFileExists(app_path('Http/Controllers/PostController.php'));
        $this->assertFileExists(app_path('DTOs/Post/PostIdDTO.php'));
    }

    #[Test]
    public function requests_are_grouped_in_a_domain_folder(): void
    {
        $this->artisan('make:domain', ['name' => 'Note'])->assertSuccessful();
        $this->artisan('make:domain', ['name' => 'BlogPost'])->assertSuccessful();

        foreach (['Note', 'BlogPost'] as $domain) {
            foreach (['Index', 'Store', 'Update', 'Patch', 'Delete'] as $action) {
                $path = "Http/Requests/$domain/$domain{$action}Request.php";

                $this->assertFileExists(app_path($path));
                $this->assertFileContains($path, "namespace App\\Http\\Requests\\$domain;");
                $this->assertFileDoesNotExist(app_path("Http/Requests/$domain{$action}Request.php"));
            }
        }

        $this->assertSame([], glob(app_path('Http/Requests/*.php')), 'No Request may sit directly in Http/Requests.');
    }

    #[Test]
    public function controller_imports_the_grouped_requests(): void
    {
        $this->artisan('make:domain', ['name' => 'Note'])->assertSuccessful();

        foreach (['Index', 'Store', 'Update', 'Patch', 'Delete'] as $action) {
            $this->assertFileContains('Http/Controllers/NoteController.php', "use App\\Http\\Requests\\Note\\Note{$action}Request;");
            $this->assertStringNotContainsString(
                "use App\\Http\\Requests\\Note{$action}Request;",
                $this->files->get(app_path('Http/Controllers/NoteController.php')),
            );
        }
    }

    #[Test]
    public function generated_dtos_need_no_code_to_carry_the_validated_fields(): void
    {
        $this->artisan('make:domain', ['name' => 'Note'])->assertSuccessful();

        $this->assertFileContains('DTOs/Note/NoteCreateDTO.php', 'class NoteCreateDTO extends BaseCreateDTO {}');
        $this->assertFileContains('DTOs/Note/NoteUpdateDTO.php', 'class NoteUpdateDTO extends BaseUpdateDTO {}');
        $this->assertFileContains('DTOs/Note/NotePatchDTO.php', 'class NotePatchDTO extends BasePatchDTO {}');
    }

    #[Test]
    public function every_stub_can_be_overridden_by_a_published_one(): void
    {
        $this->files->ensureDirectoryExists(base_path('stubs/laravel-base'));

        foreach (['controller', 'service', 'repository', 'dto.create', 'dto.update', 'dto.patch', 'dto.index', 'dto.id', 'request.index', 'request.store', 'request.update', 'request.patch', 'request.delete'] as $stub) {
            $this->files->put(base_path("stubs/laravel-base/$stub.stub"), "<?php // custom $stub {{ class }}");
        }

        $this->artisan('make:domain', ['name' => 'Note', '--no-routes' => true])->assertSuccessful();

        $this->assertFileContains('Http/Controllers/NoteController.php', '// custom controller Note');
        $this->assertFileContains('Http/Requests/Note/NotePatchRequest.php', '// custom request.patch Note');
        $this->assertFileContains('DTOs/Note/NoteCreateDTO.php', '// custom dto.create Note');
    }

    private function assertFileContains(string $path, string $needle): void
    {
        $this->assertStringContainsString($needle, $this->files->get(app_path($path)));
    }

    private function assertValidPhp(string $path): void
    {
        $process = new Process([(new PhpExecutableFinder)->find(), '-l', $path]);
        $process->run();

        $this->assertTrue($process->isSuccessful(), $process->getOutput().$process->getErrorOutput());
    }

    private function removeGenerated(): void
    {
        foreach ($this->generated as $path) {
            $this->files->isDirectory(app_path($path))
                ? $this->files->deleteDirectory(app_path($path))
                : $this->files->delete(app_path($path));
        }
    }
}
