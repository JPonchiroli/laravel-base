<?php

namespace Jponchiroli\LaravelBase\Tests\Feature;

use Illuminate\Support\Arr;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class LangParityTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function files(): array
    {
        return ['messages' => ['messages'], 'validation' => ['validation']];
    }

    /**
     * @return array<string, string>
     */
    private function load(string $locale, string $file): array
    {
        // Empty groups (e.g. "custom" => []) have no text to compare.
        return array_filter(Arr::dot(require __DIR__."/../../lang/$locale/$file.php"), is_string(...));
    }

    /**
     * @return list<string>
     */
    private function placeholders(string $text): array
    {
        preg_match_all('/:[a-z_]+/', $text, $matches);
        sort($matches[0]);

        return $matches[0];
    }

    #[Test]
    #[DataProvider('files')]
    public function both_locales_define_the_same_keys(string $file): void
    {
        $en = array_keys($this->load('en', $file));
        $pt = array_keys($this->load('pt_BR', $file));

        $this->assertSame([], array_values(array_diff($en, $pt)), "Missing in pt_BR/$file.php");
        $this->assertSame([], array_values(array_diff($pt, $en)), "Missing in en/$file.php");
    }

    #[Test]
    #[DataProvider('files')]
    public function no_translation_is_empty(string $file): void
    {
        foreach (['en', 'pt_BR'] as $locale) {
            foreach ($this->load($locale, $file) as $key => $text) {
                $this->assertNotSame('', trim((string) $text), "$locale/$file.php: $key is empty");
            }
        }
    }

    #[Test]
    #[DataProvider('files')]
    public function placeholders_match_between_locales(string $file): void
    {
        $pt = $this->load('pt_BR', $file);

        foreach ($this->load('en', $file) as $key => $text) {
            if (! isset($pt[$key])) {
                continue;
            }

            $this->assertSame($this->placeholders((string) $text), $this->placeholders((string) $pt[$key]), "$file.php: $key");
        }
    }
}
