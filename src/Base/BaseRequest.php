<?php

namespace Jponchiroli\LaravelBase\Base;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\In;
use Illuminate\Validation\Rules\NotIn;
use Illuminate\Validation\Rules\Unique;

class BaseRequest extends FormRequest
{
    private const SIZE_RULES = ['between', 'gt', 'gte', 'lt', 'lte', 'max', 'min', 'size'];

    private const NUMERIC_RULES = ['integer', 'numeric', 'decimal'];

    private const FILE_RULES = ['file', 'image', 'mimes', 'mimetypes'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Builds "field.rule" messages from the package lang files, so each
     * request gets its own message without declaring them by hand.
     * Entries in "validation.custom.{field}.{rule}" take precedence.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $messages = [];

        foreach ($this->rules() as $field => $rules) {
            $rules = is_string($rules) ? explode('|', $rules) : $rules;
            $names = array_map(fn ($rule) => $this->ruleName($rule), $rules);
            $type = match (true) {
                (bool) array_intersect($names, self::NUMERIC_RULES) => 'numeric',
                in_array('array', $names, true) => 'array',
                (bool) array_intersect($names, self::FILE_RULES) => 'file',
                default => 'string',
            };

            foreach ($names as $name) {
                $key = in_array($name, self::SIZE_RULES, true) ? "$name.$type" : $name;

                $custom = trans("laravel-base::validation.custom.$field.$name");
                $generic = trans("laravel-base::validation.$key");

                if (is_string($custom) && $custom !== "laravel-base::validation.custom.$field.$name") {
                    $messages["$field.$name"] = $custom;
                } elseif (is_string($generic) && $generic !== "laravel-base::validation.$key") {
                    $messages["$field.$name"] = $generic;
                }
            }
        }

        return $messages;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = trans('laravel-base::validation.attributes');

        return is_array($attributes) ? $attributes : [];
    }

    private function ruleName(mixed $rule): string
    {
        return match (true) {
            $rule instanceof Enum => 'enum',
            $rule instanceof Exists => 'exists',
            $rule instanceof In => 'in',
            $rule instanceof NotIn => 'not_in',
            $rule instanceof Unique => 'unique',
            is_string($rule) => explode(':', $rule)[0],
            default => '',
        };
    }
}
