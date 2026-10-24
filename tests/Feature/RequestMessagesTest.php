<?php

namespace Jponchiroli\LaravelBase\Tests\Feature;

use Illuminate\Validation\Rule;
use Jponchiroli\LaravelBase\Base\BaseRequest;
use Jponchiroli\LaravelBase\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class RequestMessagesTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $rules
     */
    private function request(array $rules): BaseRequest
    {
        return new class($rules) extends BaseRequest
        {
            /**
             * @param  array<string, mixed>  $definition
             */
            public function __construct(private array $definition)
            {
                parent::__construct();
            }

            /**
             * @return array<string, mixed>
             */
            public function rules(): array
            {
                return $this->definition;
            }
        };
    }

    #[Test]
    public function it_builds_one_message_per_known_rule(): void
    {
        app()->setLocale('pt_BR');

        $messages = $this->request(['name' => 'required|string|max:5'])->messages();

        $this->assertSame($this->pt('validation', 'required'), $messages['name.required']);
        $this->assertSame($this->pt('validation', 'max.string'), $messages['name.max']);
    }

    #[Test]
    public function size_rules_follow_the_field_type(): void
    {
        app()->setLocale('en');

        $messages = $this->request([
            'n' => ['integer', 'min:1'],
            'list' => ['array', 'max:3'],
            'file' => ['file', 'max:100'],
            'text' => ['string', 'between:1,5'],
        ])->messages();

        $this->assertStringContainsString('at least :min.', $messages['n.min']);
        $this->assertStringContainsString('items', $messages['list.max']);
        $this->assertStringContainsString('kilobytes', $messages['file.max']);
        $this->assertStringContainsString('characters', $messages['text.between']);
    }

    #[Test]
    public function it_handles_rule_objects_wildcards_and_unknown_rules(): void
    {
        app()->setLocale('en');

        $messages = $this->request([
            'items.*.id' => ['required', Rule::in([1, 2]), Rule::exists('x', 'id'), fn () => true, 'unknown_rule', 'nullable'],
        ])->messages();

        $this->assertArrayHasKey('items.*.id.required', $messages);
        $this->assertArrayHasKey('items.*.id.in', $messages);
        $this->assertArrayHasKey('items.*.id.exists', $messages);
        $this->assertArrayNotHasKey('items.*.id.unknown_rule', $messages);
        $this->assertArrayNotHasKey('items.*.id.nullable', $messages);
    }

    #[Test]
    public function custom_messages_take_precedence_over_the_generic_ones(): void
    {
        $rules = ['code' => ['required', 'digits:11']];

        foreach (['en', 'pt_BR'] as $locale) {
            app()->setLocale($locale);
            // addLines() marks the group as loaded, so load the real file first.
            app('translator')->load('laravel-base', 'validation', $locale);
            app('translator')->addLines(['validation.custom.code.digits' => "custom $locale"], $locale, 'laravel-base');

            $messages = $this->request($rules)->messages();

            $this->assertSame("custom $locale", $messages['code.digits']);
            $this->assertSame(
                trans('laravel-base::validation.required', [], $locale),
                $messages['code.required'],
            );
        }
    }

    #[Test]
    public function the_same_rule_without_a_custom_entry_uses_the_generic_message(): void
    {
        app()->setLocale('en');

        $messages = $this->request(['code' => ['digits:11']])->messages();

        $this->assertSame('The :attribute field must have :digits digits.', $messages['code.digits']);
    }
}
