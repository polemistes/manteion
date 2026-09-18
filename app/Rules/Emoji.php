<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Exactly one emoji: a single grapheme cluster that contains a pictograph,
 * a flag (regional indicator pair) or a keycap. Any Unicode emoji passes,
 * including skin tones and ZWJ sequences; plain letters and words do not.
 */
class Emoji implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)
            || preg_match('/^\X$/u', $value) !== 1
            || preg_match('/[\p{Extended_Pictographic}\p{Regional_Indicator}\x{20E3}]/u', $value) !== 1) {
            $fail(__('messages.reaction.not_an_emoji'));
        }
    }
}
