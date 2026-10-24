<?php

namespace Jponchiroli\LaravelBase\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class MakeDomainCommand extends Command
{
    protected $signature = 'make:domain {name : Entity name, e.g. Post}
        {--no-routes : Do not register the routes in routes/api.php}';

    protected $description = 'Generate the Model, Controller, Service, Repository, DTOs and Requests of a domain';

    public function handle(Filesystem $files): int
    {
        $name = Str::studly((string) $this->argument('name'));

        if (! preg_match('/^[A-Z][A-Za-z0-9]*$/', $name)) {
            $this->error("Invalid name: {$this->argument('name')}");

            return self::FAILURE;
        }

        $replacements = [
            '{{ class }}' => $name,
            '{{ plural }}' => Str::camel(Str::plural($name)),
            '{{ namespace }}' => rtrim($this->laravel->getNamespace(), '\\'),
        ];

        foreach ($this->targets($name) as $stub => $path) {
            $path = app_path($path);

            if ($files->exists($path)) {
                $this->warn("Already exists: {$path}");

                continue;
            }

            $content = strtr($files->get($this->stubPath("{$stub}.stub")), $replacements);

            $files->ensureDirectoryExists(dirname($path));
            $files->put($path, $content);

            $this->info("Created: {$path}");
        }

        if (! $this->option('no-routes') && config('laravel-base.generate_routes')) {
            $this->registerRoutes($files, $name, $replacements);
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, string>  $replacements
     */
    protected function registerRoutes(Filesystem $files, string $name, array $replacements): void
    {
        $path = base_path('routes/api.php');

        if (! $files->exists($path)) {
            $this->warn('routes/api.php not found. Run php artisan laravel-base:install.');

            return;
        }

        $controller = "{$replacements['{{ namespace }}']}\\Http\\Controllers\\{$name}Controller";

        if (str_contains($files->get($path), $controller)) {
            $this->warn("Routes already registered: {$name}");

            return;
        }

        $replacements += [
            '{{ prefix }}' => trim((string) config('laravel-base.route_prefix'), '/'),
            '{{ route }}' => Str::kebab(Str::plural($name)),
        ];

        $files->put($path, $this->addImport($files->get($path), $controller));
        $files->append($path, strtr($files->get($this->stubPath('routes.stub')), $replacements));

        $this->info('Routes registered in routes/api.php');
    }

    /**
     * Add `use {$class};` to the import block at the top of a PHP file, keeping it alphabetical.
     */
    protected function addImport(string $contents, string $class): string
    {
        $import = "use {$class};";

        $eol = str_contains($contents, "\r\n") ? "\r\n" : "\n";
        $lines = explode($eol, $contents);
        $uses = [];

        foreach ($lines as $index => $line) {
            if (preg_match('/^use\s+(?!function\b|const\b)([^;{]+);\s*$/', $line, $match)) {
                $uses[$index] = trim($match[1]);
            } elseif (trim($line) !== '' && ! preg_match('/^(<\?php|namespace\s|declare\s*\()/', $line)) {
                break;
            }
        }

        if ($uses === []) {
            array_splice($lines, 1, 0, ['', $import]);

            if (($lines[3] ?? '') !== '') {
                array_splice($lines, 3, 0, ['']);
            }

            return implode($eol, $lines);
        }

        $position = array_key_last($uses) + 1;

        foreach ($uses as $index => $name) {
            if (strcasecmp($class, $name) < 0) {
                $position = $index;

                break;
            }
        }

        array_splice($lines, $position, 0, [$import]);

        return implode($eol, $lines);
    }

    /**
     * @return array<string, string>
     */
    protected function targets(string $name): array
    {
        return [
            'model' => "Models/{$name}.php",
            'controller' => "Http/Controllers/{$name}Controller.php",
            'service' => "Services/{$name}/{$name}Service.php",
            'repository' => "Repositories/{$name}/{$name}Repository.php",
            'dto.create' => "DTOs/{$name}/{$name}CreateDTO.php",
            'dto.update' => "DTOs/{$name}/{$name}UpdateDTO.php",
            'dto.index' => "DTOs/{$name}/{$name}IndexDTO.php",
            'dto.patch' => "DTOs/{$name}/{$name}PatchDTO.php",
            'dto.id' => "DTOs/{$name}/{$name}IdDTO.php",
            'request.index' => "Http/Requests/{$name}/{$name}IndexRequest.php",
            'request.store' => "Http/Requests/{$name}/{$name}StoreRequest.php",
            'request.update' => "Http/Requests/{$name}/{$name}UpdateRequest.php",
            'request.patch' => "Http/Requests/{$name}/{$name}PatchRequest.php",
            'request.delete' => "Http/Requests/{$name}/{$name}DeleteRequest.php",
        ];
    }

    protected function stubPath(string $file): string
    {
        $custom = base_path("stubs/laravel-base/{$file}");

        return file_exists($custom)
            ? $custom
            : __DIR__.'/../../stubs/'.$file;
    }
}
