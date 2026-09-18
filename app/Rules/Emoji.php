<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Exactly one emoji. The shape is spelled out rather than left to PCRE's
 * grapheme matching (\X), which differs between PCRE versions on whether
 * two adjacent emoji count as one cluster:
 *
 *  - a flag: two regional indicators, or the black-flag tag sequence
 *  - a keycap: digit, # or *, then the keycap mark
 *  - a pictograph with optional presentation selector and skin tone,
 *    joined to further pictographs only with zero-width joiners
 */
class Emoji implements ValidationRule
{
    private const PATTERN = '/^(?:
        \p{Regional_Indicator}{2}
        | \x{1F3F4}[\x{E0020}-\x{E007E}]+\x{E007F}
        | [0-9#*]\x{FE0F}?\x{20E3}
        | \p{Extended_Pictographic}\x{FE0F}?[\x{1F3FB}-\x{1F3FF}]?\x{FE0F}?
          (?:\x{200D}\p{Extended_Pictographic}\x{FE0F}?[\x{1F3FB}-\x{1F3FF}]?\x{FE0F}?)*
    )$/ux';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match(self::PATTERN, $value) !== 1) {
            $fail(__('messages.reaction.not_an_emoji'));
        }
    }
}
