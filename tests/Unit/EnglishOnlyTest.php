<?php

namespace Jponchiroli\LaravelBase\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Portuguese is allowed only in lang/pt_BR (and the translated README).
 * Everything else, including comments, messages and tests, must be English.
 */
class EnglishOnlyTest extends TestCase
{
    /**
     * Accented letters used in Portuguese.
     */
    private const ACCENTS = '/[\x{00C0}-\x{00FF}]/u';

    #[Test]
    public function no_portuguese_outside_the_pt_br_lang_files(): void
    {
        $root = dirname(__DIR__, 2);
        $offenders = [];

        foreach ($this->files($root) as $file) {
            foreach (file($file) as $number => $line) {
                if (preg_match(self::ACCENTS, $line)) {
                    $offenders[] = substr($file, strlen($root) + 1).':'.($number + 1);
                }
            }
        }

        $this->assertSame([], $offenders, 'Non-English text found; keep Portuguese in lang/pt_BR only.');
    }

    /**
     * @return list<string>
     */
    private function files(string $root): array
    {
        $files = [$root.'/README.md', $root.'/composer.json'];

        foreach (['src', 'stubs', 'config', 'docs', 'lang/en', 'tests'] as $directory) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$root/$directory", RecursiveDirectoryIterator::SKIP_DOTS));

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getRealPath() !== __FILE__) {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }
}
