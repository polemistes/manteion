<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A push subscription's endpoint must be an https URL of a browser's push
 * service (config webpush.allowed_endpoint_hosts). The server POSTs to it
 * whenever the user gets a notification, so anything else would let a user
 * make the server call an address of their choosing.
 */
class PushServiceEndpoint implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::allows($value)) {
            $fail(__('validation.url', ['attribute' => $attribute]));
        }
    }

    public static function allows(string $endpoint): bool
    {
        $parts = parse_url($endpoint);

        if (($parts['scheme'] ?? null) !== 'https' || ! isset($parts['host']) || isset($parts['user']) || isset($parts['port'])) {
            return false;
        }

        $host = strtolower($parts['host']);

        foreach ((array) config('webpush.allowed_endpoint_hosts', []) as $allowed) {
            $allowed = strtolower((string) $allowed);

            // A leading dot allows the subdomains of that host.
            if ($allowed !== '' && ($host === $allowed || (str_starts_with($allowed, '.') && str_ends_with($host, $allowed)))) {
                return true;
            }
        }

        return false;
    }
}
