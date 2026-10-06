<?php

namespace LucaCermenati\NovaSettings\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates the JSON posted by a Nova KeyValue field holding the choices of a
 * Select / MultiSelect / BooleanGroup: at least one row, every key filled in.
 */
class ChoicesNotEmpty implements ValidationRule
{
    /** Run even when the value is empty, so a missing table fails too. */
    public bool $implicit = true;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $choices = is_string($value) ? json_decode($value, true) : $value;

        if (! is_array($choices) || $choices === []) {
            $fail(__('Add at least one choice.'));

            return;
        }

        foreach (array_keys($choices) as $key) {
            if (trim((string) $key) === '') {
                $fail(__('Every choice needs a value.'));

                return;
            }
        }
    }
}
