<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Validation only. No DNS resolution, scraping, embedding or remote server-side requests. */
class PublicHttpsUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! filter_var($value, FILTER_VALIDATE_URL)) {
            $fail('Use a complete public HTTPS URL.');

            return;
        }
        $u = parse_url($value);
        $host = strtolower(rtrim($u['host'] ?? '', '.'));
        if (($u['scheme'] ?? '') !== 'https' || isset($u['user']) || isset($u['pass']) || isset($u['port']) || filter_var($host, FILTER_VALIDATE_IP) || ! preg_match('/^[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?\.(?:[a-z]{2,63}|xn--[a-z0-9-]{2,59})$/i', $host) || preg_match('/(^|\.)(localhost|local|internal|lan|home|corp|test|invalid|onion)$/', $host) || preg_match('/[\x00-\x20\x7f]/', $value)) {
            $fail('Use a public HTTPS URL without credentials, a custom port or a local/IP address.');
        }
    }
}
