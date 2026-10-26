<?php

namespace Jponchiroli\LaravelBase\Docs;

/**
 * Prints a node tree as the source of an OpenApi\Attributes call.
 * A node is an array whose "@" key names the attribute class and whose other keys are its named arguments.
 */
class DocsRenderer
{
    /**
     * @param  array<string, mixed>  $node
     */
    public function render(array $node, int $level = 0): string
    {
        return substr($this->value($node, $level), 4);
    }

    protected function value(mixed $value, int $level): string
    {
        if (! is_array($value)) {
            return var_export($value, true);
        }

        $pad = str_repeat('    ', $level + 1);
        $close = str_repeat('    ', $level);

        if (isset($value['@'])) {
            $class = array_shift($value);
            $lines = array_map(fn ($key, $item) => "{$pad}{$key}: {$this->value($item, $level + 1)},\n", array_keys($value), $value);

            return "new OA\\{$class}(\n".implode('', $lines).$close.')';
        }

        if ($value === []) {
            return '[]';
        }

        $isList = array_is_list($value);
        $lines = array_map(
            fn ($key, $item) => $pad.($isList ? '' : var_export($key, true).' => ').$this->value($item, $level + 1).",\n",
            array_keys($value), $value,
        );

        return "[\n".implode('', $lines).$close.']';
    }
}
