<?php

namespace Jponchiroli\LaravelBase\Tests\Unit;

use Jponchiroli\LaravelBase\Console\MakeDomainCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Keeps the documentation honest: links resolve, diagrams are well formed
 * and the guides mention what the code actually does.
 */
class DocsTest extends TestCase
{
    private const ROOT = __DIR__.'/../..';

    /**
     * @return list<string>
     */
    private static function markdownFiles(): array
    {
        return array_merge(
            [self::ROOT.'/README.md', self::ROOT.'/README.pt-BR.md'],
            glob(self::ROOT.'/docs/*.md') ?: [],
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function files(): array
    {
        $cases = [];

        foreach (self::markdownFiles() as $file) {
            $cases[substr(realpath($file), strlen(realpath(self::ROOT)) + 1)] = [$file];
        }

        return $cases;
    }

    #[Test]
    #[DataProvider('files')]
    public function relative_links_point_to_existing_files(string $file): void
    {
        preg_match_all('/\[[^\]]*\]\(([^)\s]+)\)/', file_get_contents($file), $matches);

        foreach ($matches[1] as $target) {
            if (preg_match('#^(https?:|mailto:|\#)#', $target)) {
                continue;
            }

            $path = explode('#', $target)[0];

            $this->assertFileExists(dirname($file).'/'.$path, "Broken link '$target' in $file");
        }

        $this->addToAssertionCount(1);
    }

    #[Test]
    #[DataProvider('files')]
    public function mermaid_blocks_are_closed_and_start_with_a_known_diagram(string $file): void
    {
        $text = file_get_contents($file);

        $this->assertSame(0, preg_match_all('/^```/m', $text) % 2, "Unbalanced code fences in $file");

        preg_match_all('/```mermaid\R(.*?)\R```/s', $text, $blocks);

        foreach ($blocks[1] as $block) {
            $this->assertMatchesRegularExpression('/^\s*(flowchart|sequenceDiagram|classDiagram)\b/', $block, "Unknown diagram type in $file");
        }
    }

    #[Test]
    public function the_docs_index_lists_every_topic(): void
    {
        $index = file_get_contents(self::ROOT.'/docs/README.md');

        foreach (glob(self::ROOT.'/docs/*.md') as $file) {
            if (basename($file) !== 'README.md') {
                $this->assertStringContainsString('('.basename($file).')', $index, basename($file).' is missing from docs/README.md');
            }
        }
    }

    #[Test]
    public function every_topic_is_linked_from_both_readmes(): void
    {
        foreach (['README.md', 'README.pt-BR.md'] as $readme) {
            $this->assertStringContainsString('docs/README.md', file_get_contents(self::ROOT.'/'.$readme), $readme);
        }
    }

    #[Test]
    public function the_readmes_stay_short_and_point_to_the_docs(): void
    {
        foreach (['README.md', 'README.pt-BR.md'] as $readme) {
            $text = file_get_contents(self::ROOT.'/'.$readme);

            $this->assertLessThanOrEqual(120, substr_count($text, '
'), "$readme should be an overview; put technical detail in docs/");
            $this->assertStringContainsString('Swagger', $text, "$readme must mention the planned Swagger generation");
            $this->assertStringContainsString('laravel-base-agents', $text, "$readme must explain the AI agent guide");
            $this->assertStringContainsString('composer require jponchiroli/laravel-base', $text);
        }
    }

    #[Test]
    public function the_agent_guide_has_no_relative_links_because_it_is_copied_to_the_project_root(): void
    {
        preg_match_all('/\]\(([^)\s]+)\)/', file_get_contents(self::ROOT.'/docs/ai-agent-guide.md'), $links);

        foreach ($links[1] as $link) {
            $this->assertMatchesRegularExpression('#^(https://|\#)#', $link, "Relative link '$link' would break in AGENTS.md");
        }
    }

    #[Test]
    public function the_agent_guide_lists_every_file_the_generator_creates(): void
    {
        $guide = file_get_contents(self::ROOT.'/docs/ai-agent-guide.md');
        $targets = (new ReflectionMethod(MakeDomainCommand::class, 'targets'))->invoke(new MakeDomainCommand, 'Product');

        foreach ($targets as $path) {
            $this->assertStringContainsString("app/$path", $guide, "The agent guide does not mention app/$path");
        }
    }

    #[Test]
    public function the_make_domain_doc_lists_every_generated_file(): void
    {
        $doc = file_get_contents(self::ROOT.'/docs/make-domain.md');
        $targets = (new ReflectionMethod(MakeDomainCommand::class, 'targets'))->invoke(new MakeDomainCommand, 'Product');

        foreach ($targets as $path) {
            $this->assertStringContainsString("`$path`", $doc, "docs/make-domain.md does not mention $path");
        }
    }

    #[Test]
    public function the_install_doc_covers_every_publish_tag_and_config_key(): void
    {
        $doc = file_get_contents(self::ROOT.'/docs/install-and-configuration.md');

        preg_match_all("/'(laravel-base-[a-z]+)'/", file_get_contents(self::ROOT.'/src/LaravelBaseServiceProvider.php'), $tags);
        $this->assertNotEmpty($tags[1]);

        foreach (array_unique($tags[1]) as $tag) {
            $this->assertStringContainsString("`$tag`", $doc, "Publish tag $tag is not documented");
        }

        foreach (array_keys(require self::ROOT.'/config/laravel-base.php') as $key) {
            $this->assertStringContainsString("`$key`", $doc, "Config key $key is not documented");
        }
    }

    #[Test]
    public function the_install_doc_covers_every_install_option(): void
    {
        $doc = file_get_contents(self::ROOT.'/docs/install-and-configuration.md');

        preg_match_all('/\{--([a-z-]+)/', file_get_contents(self::ROOT.'/src/Console/InstallCommand.php'), $options);

        foreach ($options[1] as $option) {
            $this->assertStringContainsString("`--$option", $doc, "Option --$option is not documented");
        }
    }
}
