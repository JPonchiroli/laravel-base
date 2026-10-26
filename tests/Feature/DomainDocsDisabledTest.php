<?php

namespace Jponchiroli\LaravelBase\Tests\Feature;

use Jponchiroli\LaravelBase\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class DomainDocsDisabledTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        $app['config']->set('laravel-base.swagger.enabled', false);
    }

    #[Test]
    public function the_docs_routes_are_not_registered(): void
    {
        $this->get('docs')->assertNotFound();
        $this->get('docs/openapi.json')->assertNotFound();
    }
}
