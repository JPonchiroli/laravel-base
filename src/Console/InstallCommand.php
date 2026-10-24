<?php

namespace Jponchiroli\LaravelBase\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class InstallCommand extends Command
{
    protected $signature = 'laravel-base:install
        {--locale=pt_BR : Default application locale}
        {--fallback=en : Fallback locale}
        {--with-lang : Publish the translation files to lang/vendor/laravel-base}
        {--with-agents : Publish the AI agent guide to AGENTS.md}
        {--force : Overwrite already published files}';

    protected $description = 'Prepare the project: config, locale, routes/api.php and the base Controller';

    private const LOCALE_PATTERN = '/^[A-Za-z]{2,3}([_-][A-Za-z0-9]+)?$/';

    public function handle(Filesystem $files): int
    {
        foreach (['locale', 'fallback'] as $option) {
            if (! preg_match(self::LOCALE_PATTERN, (string) $this->option($option))) {
                $this->error("Invalid --{$option} value: {$this->option($option)}");

                return self::FAILURE;
            }
        }

        $this->call('vendor:publish', array_filter([
            '--tag' => 'laravel-base-config',
            '--force' => $this->option('force'),
        ]));

        if ($this->option('with-lang')) {
            $this->call('vendor:publish', array_filter([
                '--tag' => 'laravel-base-lang',
                '--force' => $this->option('force'),
            ]));
        }

        if ($this->option('with-agents')) {
            $this->call('vendor:publish', array_filter([
                '--tag' => 'laravel-base-agents',
                '--force' => $this->option('force'),
            ]));
        }

        $this->ensureApiRoutes($files);
        $this->ensureBaseController($files);

        foreach (['.env', '.env.example'] as $env) {
            $this->setEnv($files, base_path($env), [
                'APP_LOCALE' => (string) $this->option('locale'),
                'APP_FALLBACK_LOCALE' => (string) $this->option('fallback'),
            ]);
        }

        $this->info('Done. Next step: php artisan make:domain EntityName');

        return self::SUCCESS;
    }

    protected function ensureApiRoutes(Filesystem $files): void
    {
        if ($files->exists(base_path('routes/api.php'))) {
            return;
        }

        $this->call('install:api', ['--no-interaction' => true]);

        if (! $files->exists(base_path('routes/api.php'))) {
            $this->warn('routes/api.php was not created. Run php artisan install:api manually.');
        }
    }

    protected function ensureBaseController(Filesystem $files): void
    {
        $path = app_path('Http/Controllers/Controller.php');

        if ($files->exists($path)) {
            return;
        }

        $namespace = rtrim($this->laravel->getNamespace(), '\\');

        $files->ensureDirectoryExists(dirname($path));
        $files->put($path, "<?php\n\nnamespace {$namespace}\\Http\\Controllers;\n\nabstract class Controller\n{\n    //\n}\n");

        $this->info("Created: {$path}");
    }

    /**
     * @param  array<string, string>  $values
     */
    protected function setEnv(Filesystem $files, string $path, array $values): void
    {
        if (! $files->exists($path)) {
            return;
        }

        $content = $files->get($path);

        foreach ($values as $key => $value) {
            $line = "{$key}={$value}";

            $content = preg_match("/^{$key}=.*$/m", $content)
                ? preg_replace_callback("/^{$key}=.*$/m", fn () => $line, $content)
                : rtrim($content)."\n{$line}\n";
        }

        $files->put($path, $content);
    }
}
