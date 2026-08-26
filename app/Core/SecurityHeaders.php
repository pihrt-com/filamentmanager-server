<?php

declare(strict_types=1);

namespace FilamentManager\Core;

final class SecurityHeaders
{
    public static function send(bool $https): void
    {
        header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: same-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        header('X-Frame-Options: DENY');
        if ($https) header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}
