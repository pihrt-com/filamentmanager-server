<?php

declare(strict_types=1);

namespace FilamentManager\Core;

final class Csrf
{
    public static function token(): string
    {
        $token = Session::get('_csrf');
        if (!is_string($token) || strlen($token) < 32) {
            $token = bin2hex(random_bytes(32));
            Session::put('_csrf', $token);
        }
        return $token;
    }

    public static function verify(Request $request): void
    {
        $given = (string) ($request->input('_csrf') ?? $request->header('X-CSRF-Token') ?? '');
        if ($given === '' || !hash_equals(self::token(), $given)) {
            throw new HttpException('Invalid or expired security token.', 419);
        }
    }
}
