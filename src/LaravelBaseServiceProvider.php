<?php

namespace Jponchiroli\LaravelBase;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\ServiceProvider;
use Jponchiroli\LaravelBase\Console\InstallCommand;
use Jponchiroli\LaravelBase\Console\MakeDomainCommand;
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

        if ($this->app->runningInConsole()) {
            $this->commands([
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
}
