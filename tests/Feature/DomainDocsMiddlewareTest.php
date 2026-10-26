<?php

namespace Jponchiroli\LaravelBase\Tests\Feature;

use Jponchiroli\LaravelBase\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class DomainDocsMiddlewareTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        $app['config']->set('laravel-base.swagger.middleware', ['auth']);
    }

    #[Test]
    public function the_docs_routes_go_through_the_configured_middleware(): void
    {
        $this->getJson('docs/openapi.json')->assertUnauthorized();
        $this->getJson('docs')->assertUnauthorized();
    }
}
