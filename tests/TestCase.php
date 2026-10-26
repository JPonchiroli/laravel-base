<?php

namespace Jponchiroli\LaravelBase\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Jponchiroli\LaravelBase\LaravelBaseServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Expected pt_BR text, read from the lang files so tests hold no Portuguese literals.
     * Example: pt('messages', 'success.store') or pt('validation', 'max.string', ['attribute' => 'name', 'max' => 5]).
     *
     * @param  array<string, string|int>  $replace
     */
    protected function pt(string $file, string $key, array $replace = []): string
    {
        $text = Arr::get(require __DIR__."/../lang/pt_BR/$file.php", $key);

        foreach ($replace as $name => $value) {
            $text = str_replace(":$name", (string) $value, $text);
        }

        return $text;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrations = glob(database_path('migrations/*.php')) ?: [];

        Schema::create('widgets', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('color')->nullable();
            $table->unsignedInteger('qty')->nullable();
            $table->timestamps();
        });
    }

    /**
     * @var list<string>
     */
    private array $migrations = [];

    protected function tearDown(): void
    {
        $files = new Filesystem;

        $files->deleteDirectory(app_path('Docs'));
        $files->deleteDirectory(storage_path('api-docs'));
        $files->delete(array_diff(glob(database_path('migrations/*.php')) ?: [], $this->migrations));

        parent::tearDown();
    }

    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [LaravelBaseServiceProvider::class];
    }
}
