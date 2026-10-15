<?php
declare(strict_types=1);

namespace Ternwire;

final class Webhooks
{
    public static function verify(string $header, string $body, string $secret, int $toleranceSeconds = 300): bool
    {
        if ($secret === '' || $toleranceSeconds < 0
            || !preg_match('/\A\s*t=([1-9][0-9]{0,11})\s*,\s*v1=([a-f0-9]{64})\s*\z/', $header, $match)
            || abs(time() - (int) $match[1]) > $toleranceSeconds) {
            return false;
        }
        return hash_equals(hash_hmac('sha256', $match[1] . '.' . $body, $secret), $match[2]);
    }
}
