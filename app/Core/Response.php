<?php

declare(strict_types=1);

namespace FilamentManager\Core;

final class Response
{
    public static function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        exit;
    }

    public static function redirect(string $url, int $status = 303): never
    {
        header('Location: ' . $url, true, $status);
        exit;
    }

    public static function error(Request $request, string $message, int $status): never
    {
        if ($request->isApi()) {
            self::json(['error' => ['code' => 'http_' . $status, 'message' => $message, 'requestId' => Logger::requestId()]], $status);
        }
        http_response_code($status);
        View::render('error', ['status' => $status, 'message' => $message]);
        exit;
    }
}
