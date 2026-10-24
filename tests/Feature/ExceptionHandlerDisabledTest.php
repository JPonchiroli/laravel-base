<?php

namespace Jponchiroli\LaravelBase\Tests\Feature;

use Jponchiroli\LaravelBase\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ExceptionHandlerDisabledTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        $app['config']->set('laravel-base.exception_handler', false);
        $app['config']->set('app.debug', false);
    }

    protected function defineRoutes($router): void
    {
        $router->get('api/validation', fn () => request()->validate(['name' => 'required']));
    }

    #[Test]
    public function errors_keep_the_framework_format_when_the_handler_is_disabled(): void
    {
        app()->setLocale('en');

        $this->getJson('api/unknown')
            ->assertNotFound()
            ->assertJsonMissingPath('success')
            ->assertJsonPath('message', 'The route api/unknown could not be found.');

        $this->getJson('api/validation')
            ->assertStatus(422)
            ->assertJsonMissingPath('success')
            ->assertJsonStructure(['message', 'errors' => ['name']]);
    }
}
