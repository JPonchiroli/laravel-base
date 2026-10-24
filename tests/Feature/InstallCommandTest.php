<?php

namespace Jponchiroli\LaravelBase\Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Jponchiroli\LaravelBase\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

class InstallCommandTest extends TestCase
{
    private Filesystem $files;

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem;
        $this->cleanup();
        $this->files->ensureDirectoryExists(base_path('routes'));
        $this->files->put(base_path('routes/api.php'), "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n");
        $this->files->put(base_path('.env'), "APP_NAME=Laravel\nAPP_LOCALE=en\n");
    }

    protected function tearDown(): void
    {
        $this->cleanup();

        parent::tearDown();
    }

    private function cleanup(): void
    {
        $this->files->delete([
            base_path('.env'),
            base_path('.env.example'),
            base_path('routes/api.php'),
            config_path('laravel-base.php'),
            base_path('AGENTS.md'),
        ]);
        $this->files->deleteDirectory(lang_path('vendor/laravel-base'));

        foreach (['Http', 'Models', 'Services', 'Repositories', 'DTOs'] as $directory) {
            $this->files->deleteDirectory(app_path($directory));
        }
    }

    #[Test]
    public function it_sets_up_a_fresh_project_and_is_idempotent(): void
    {
        $this->artisan('laravel-base:install')->assertSuccessful();
        $this->artisan('laravel-base:install')->assertSuccessful();

        $env = $this->files->get(base_path('.env'));

        $this->assertSame(1, substr_count($env, 'APP_LOCALE='));
        $this->assertStringContainsString('APP_LOCALE=pt_BR', $env);
        $this->assertStringContainsString('APP_FALLBACK_LOCALE=en', $env);
        $this->assertFileExists(config_path('laravel-base.php'));
        $this->assertFileExists(app_path('Http/Controllers/Controller.php'));
    }

    #[Test]
    public function it_honours_locale_and_fallback_options_in_both_env_files(): void
    {
        $this->files->put(base_path('.env.example'), "APP_LOCALE=en\nAPP_FALLBACK_LOCALE=en\n");

        $this->artisan('laravel-base:install', ['--locale' => 'es', '--fallback' => 'pt_BR'])->assertSuccessful();

        foreach (['.env', '.env.example'] as $file) {
            $env = $this->files->get(base_path($file));

            $this->assertStringContainsString('APP_LOCALE=es', $env, $file);
            $this->assertStringContainsString('APP_FALLBACK_LOCALE=pt_BR', $env, $file);
            $this->assertSame(1, substr_count($env, 'APP_LOCALE='), $file);
        }
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidLocales(): array
    {
        return [
            'regex reference' => ['--locale', 'pt$1'],
            'newline injection' => ['--locale', 'en
APP_KEY=stolen'],
            'path' => ['--fallback', '../en'],
            'empty' => ['--locale', ''],
            'too long' => ['--fallback', 'abcdefgh'],
        ];
    }

    #[Test]
    #[DataProvider('invalidLocales')]
    public function it_rejects_invalid_locale_values_without_touching_any_file(string $option, string $value): void
    {
        $before = $this->files->get(base_path('.env'));

        $this->artisan('laravel-base:install', [$option => $value])
            ->expectsOutputToContain('Invalid '.$option)
            ->assertFailed();

        $this->assertSame($before, $this->files->get(base_path('.env')));
        $this->assertFileDoesNotExist(config_path('laravel-base.php'));
    }

    #[Test]
    public function it_accepts_common_locale_codes(): void
    {
        foreach ([['en', 'pt_BR'], ['pt-BR', 'en'], ['es', 'es_MX'], ['fil', 'en']] as [$locale, $fallback]) {
            $this->artisan('laravel-base:install', ['--locale' => $locale, '--fallback' => $fallback])->assertSuccessful();

            $env = $this->files->get(base_path('.env'));
            $this->assertStringContainsString("APP_LOCALE={$locale}
", $env);
            $this->assertStringContainsString("APP_FALLBACK_LOCALE={$fallback}
", $env);
        }
    }

    #[Test]
    public function it_keeps_unrelated_env_entries(): void
    {
        $this->artisan('laravel-base:install')->assertSuccessful();

        $this->assertStringContainsString('APP_NAME=Laravel', $this->files->get(base_path('.env')));
    }

    #[Test]
    public function it_skips_missing_env_files(): void
    {
        $this->files->delete(base_path('.env'));

        $this->artisan('laravel-base:install')->assertSuccessful();

        $this->assertFileDoesNotExist(base_path('.env'));
        $this->assertFileDoesNotExist(base_path('.env.example'));
    }

    #[Test]
    public function with_lang_publishes_the_translations(): void
    {
        $this->artisan('laravel-base:install')->assertSuccessful();
        $this->assertDirectoryDoesNotExist(lang_path('vendor/laravel-base'));

        $this->artisan('laravel-base:install', ['--with-lang' => true])->assertSuccessful();

        $this->assertFileExists(lang_path('vendor/laravel-base/en/messages.php'));
        $this->assertFileExists(lang_path('vendor/laravel-base/pt_BR/validation.php'));
    }

    #[Test]
    public function with_agents_publishes_the_ai_guide_as_agents_md(): void
    {
        $this->artisan('laravel-base:install')->assertSuccessful();
        $this->assertFileDoesNotExist(base_path('AGENTS.md'));

        $this->artisan('laravel-base:install', ['--with-agents' => true])->assertSuccessful();

        $this->assertFileEquals(__DIR__.'/../../docs/ai-agent-guide.md', base_path('AGENTS.md'));
    }

    #[Test]
    public function the_agents_tag_never_overwrites_an_edited_agents_md(): void
    {
        $this->files->put(base_path('AGENTS.md'), 'my own rules');

        $this->artisan('vendor:publish', ['--tag' => 'laravel-base-agents'])->assertSuccessful();
        $this->assertSame('my own rules', $this->files->get(base_path('AGENTS.md')));

        $this->artisan('vendor:publish', ['--tag' => 'laravel-base-agents', '--force' => true])->assertSuccessful();
        $this->assertFileEquals(__DIR__.'/../../docs/ai-agent-guide.md', base_path('AGENTS.md'));
    }

    #[Test]
    public function force_overwrites_the_published_config(): void
    {
        $this->artisan('laravel-base:install')->assertSuccessful();
        $this->files->put(config_path('laravel-base.php'), '<?php return [];');

        $this->artisan('laravel-base:install')->assertSuccessful();
        $this->assertSame('<?php return [];', $this->files->get(config_path('laravel-base.php')));

        $this->artisan('laravel-base:install', ['--force' => true])->assertSuccessful();
        $this->assertStringContainsString('exception_handler', $this->files->get(config_path('laravel-base.php')));
    }

    #[Test]
    public function it_runs_install_api_when_routes_are_missing(): void
    {
        $this->files->delete(base_path('routes/api.php'));
        $files = $this->files;
        Artisan::command('install:api', fn () => $files->put(base_path('routes/api.php'), "<?php\n"));

        $this->artisan('laravel-base:install')->assertSuccessful();

        $this->assertFileExists(base_path('routes/api.php'));
    }

    #[Test]
    public function it_warns_when_install_api_does_not_create_the_routes(): void
    {
        $this->files->delete(base_path('routes/api.php'));
        Artisan::command('install:api', fn () => null);

        $this->artisan('laravel-base:install')
            ->expectsOutputToContain('routes/api.php was not created')
            ->assertSuccessful();
    }

    #[Test]
    public function make_domain_registers_routes_once(): void
    {
        $this->artisan('make:domain', ['name' => 'BlogPost'])->assertSuccessful();
        $this->artisan('make:domain', ['name' => 'BlogPost'])->assertSuccessful();

        $routes = $this->files->get(base_path('routes/api.php'));

        $this->assertSame(1, substr_count($routes, "Route::prefix('v1/blog-posts')"));
        $this->assertStringContainsString('BlogPostController::class', $routes);
        $this->assertStringContainsString("Route::patch('{id}'", $routes);
    }

    #[Test]
    public function routes_import_the_controller_instead_of_repeating_its_full_path(): void
    {
        $this->artisan('make:domain', ['name' => 'BlogPost'])->assertSuccessful();
        $this->artisan('make:domain', ['name' => 'BlogPost'])->assertSuccessful();

        $routes = $this->files->get(base_path('routes/api.php'));

        $this->assertSame(1, substr_count($routes, 'use App\Http\Controllers\BlogPostController;'));
        $this->assertSame(6, substr_count($routes, '[BlogPostController::class,'));
        $this->assertStringNotContainsString('\App\Http\Controllers\BlogPostController::class', $routes);
        $this->assertValidPhp(base_path('routes/api.php'));
    }

    #[Test]
    public function imports_stay_sorted_with_the_existing_ones(): void
    {
        $this->files->put(base_path('routes/api.php'), "<?php\n\nuse App\\Http\\Controllers\\ProductController;\nuse Illuminate\\Support\\Facades\\Route;\n\nRoute::get('/up', fn () => 'ok');\n");

        $this->artisan('make:domain', ['name' => 'BlogPost'])->assertSuccessful();
        $this->artisan('make:domain', ['name' => 'Author'])->assertSuccessful();

        $routes = $this->files->get(base_path('routes/api.php'));

        $this->assertStringContainsString(
            "use App\\Http\\Controllers\\AuthorController;\nuse App\\Http\\Controllers\\BlogPostController;\nuse App\\Http\\Controllers\\ProductController;\nuse Illuminate\\Support\\Facades\\Route;\n",
            $routes,
        );
        $this->assertValidPhp(base_path('routes/api.php'));
    }

    #[Test]
    public function the_import_is_added_after_the_php_tag_when_the_file_has_none(): void
    {
        $this->files->put(base_path('routes/api.php'), "<?php\n\nRoute::get('/up', fn () => 'ok');\n");

        $this->artisan('make:domain', ['name' => 'BlogPost'])->assertSuccessful();

        $routes = $this->files->get(base_path('routes/api.php'));

        $this->assertStringStartsWith("<?php\n\nuse App\\Http\\Controllers\\BlogPostController;\n\nRoute::get('/up'", $routes);
        $this->assertValidPhp(base_path('routes/api.php'));
    }

    #[Test]
    public function a_controller_that_is_already_imported_is_not_registered_again(): void
    {
        $original = "<?php\n\nuse App\\Http\\Controllers\\BlogPostController as Posts;\nuse Illuminate\\Support\\Facades\\Route;\n";
        $this->files->put(base_path('routes/api.php'), $original);

        $this->artisan('make:domain', ['name' => 'BlogPost'])
            ->expectsOutputToContain('Routes already registered: BlogPost')
            ->assertSuccessful();

        $this->assertSame($original, $this->files->get(base_path('routes/api.php')));
    }

    #[Test]
    public function the_import_is_added_even_when_the_php_tag_is_followed_directly_by_code(): void
    {
        $this->files->put(base_path('routes/api.php'), "<?php\nRoute::get('/up', fn () => 'ok');\n");

        $this->artisan('make:domain', ['name' => 'BlogPost'])->assertSuccessful();

        $this->assertStringStartsWith("<?php\n\nuse App\\Http\\Controllers\\BlogPostController;\n\nRoute::get('/up'", $this->files->get(base_path('routes/api.php')));
        $this->assertValidPhp(base_path('routes/api.php'));
    }

    #[Test]
    public function windows_line_endings_are_kept(): void
    {
        $this->files->put(base_path('routes/api.php'), "<?php\r\n\r\nuse Illuminate\\Support\\Facades\\Route;\r\n");

        $this->artisan('make:domain', ['name' => 'BlogPost'])->assertSuccessful();

        $routes = $this->files->get(base_path('routes/api.php'));

        $this->assertStringContainsString("use App\\Http\\Controllers\\BlogPostController;\r\nuse Illuminate\\Support\\Facades\\Route;\r\n", $routes);
    }

    private function assertValidPhp(string $path): void
    {
        $process = new Process([(new PhpExecutableFinder)->find(), '-l', $path]);
        $process->run();

        $this->assertTrue($process->isSuccessful(), $process->getOutput().$process->getErrorOutput());
    }

    #[Test]
    public function make_domain_can_skip_routes(): void
    {
        $this->artisan('make:domain', ['name' => 'Note', '--no-routes' => true])->assertSuccessful();

        $this->assertStringNotContainsString('NoteController', $this->files->get(base_path('routes/api.php')));
    }

    #[Test]
    public function make_domain_warns_when_the_routes_file_is_missing(): void
    {
        $this->files->delete(base_path('routes/api.php'));

        $this->artisan('make:domain', ['name' => 'Note'])
            ->expectsOutputToContain('routes/api.php not found')
            ->assertSuccessful();
    }
}
