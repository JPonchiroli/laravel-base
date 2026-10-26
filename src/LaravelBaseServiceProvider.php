<?php

namespace Jponchiroli\LaravelBase;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Jponchiroli\LaravelBase\Console\DomainDocsCommand;
use Jponchiroli\LaravelBase\Console\InstallCommand;
use Jponchiroli\LaravelBase\Console\MakeDomainCommand;
use Jponchiroli\LaravelBase\Docs\OpenApiBuilder;
use Jponchiroli\LaravelBase\Exceptions\ApiExceptionHandler;

class LaravelBaseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/laravel-base.php', 'laravel-base');
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'laravel-base');

        if (config('laravel-base.exception_handler')) {
            $this->callAfterResolving(ExceptionHandler::class, function ($handler): void {
                if (method_exists($handler, 'renderable')) {
                    ApiExceptionHandler::register($handler);
                }
            });
        }

        if (config('laravel-base.swagger.enabled')) {
            $this->registerDocsRoutes();
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                DomainDocsCommand::class,
                InstallCommand::class,
                MakeDomainCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/laravel-base.php' => config_path('laravel-base.php'),
            ], 'laravel-base-config');

            $this->publishes([
                __DIR__.'/../stubs' => base_path('stubs/laravel-base'),
            ], 'laravel-base-stubs');

            $this->publishes([
                __DIR__.'/../lang' => lang_path('vendor/laravel-base'),
            ], 'laravel-base-lang');

            $this->publishes([
                __DIR__.'/../docs/ai-agent-guide.md' => base_path('AGENTS.md'),
            ], 'laravel-base-agents');
        }
    }

    protected function registerDocsRoutes(): void
    {
        $route = trim((string) config('laravel-base.swagger.route'), '/');

        Route::middleware((array) config('laravel-base.swagger.middleware'))->group(function () use ($route): void {
            // shortcut: the file is only refreshed by make:domain and domain:docs, run domain:docs after editing app/Docs by hand
            Route::get("{$route}/openapi.json", fn () => response(
                file_exists(OpenApiBuilder::jsonPath()) ? file_get_contents(OpenApiBuilder::jsonPath()) : (new OpenApiBuilder)->openApiJson(),
                200,
                ['Content-Type' => 'application/json'],
            ));

            Route::get($route, fn () => response(
                '<!doctype html><html><head><meta charset="utf-8"><title>'.e(config('laravel-base.swagger.title')).'</title>'
                .'<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5.17.14/swagger-ui.css" integrity="sha384-wxLW6kwyHktdDGr6Pv1zgm/VGJh99lfUbzSn6HNHBENZlCN7W602k9VkGdxuFvPn" crossorigin="anonymous"></head>'
                .'<body><div id="swagger-ui"></div>'
                .'<script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5.17.14/swagger-ui-bundle.js" integrity="sha384-wmyclcVGX/WhUkdkATwhaK1X1JtiNrr2EoYJ+diV3vj4v6OC5yCeSu+yW13SYJep" crossorigin="anonymous"></script>'
                .'<script>SwaggerUIBundle({url: '.json_encode(url("{$route}/openapi.json"), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG).', dom_id: "#swagger-ui"});</script>'
                .'</body></html>'
            ));
        });
    }
}
