<?php

declare(strict_types=1);

namespace FilamentManager\Core;

final class View
{
    private static ?App $app = null;
    public static function setApp(App $app): void { self::$app = $app; }

    public static function render(string $view, array $data = [], string $layout = 'layout'): void
    {
        $app = self::$app;
        extract($data, EXTR_SKIP);
        ob_start();
        require FM_ROOT . '/resources/views/' . $view . '.php';
        $content = (string) ob_get_clean();
        if ($layout === '') { echo $content; return; }
        require FM_ROOT . '/resources/views/' . $layout . '.php';
    }

    public static function e(mixed $value): string { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
    public static function t(string $key, array $replace = []): string { return self::$app?->translator()->get($key, $replace) ?? $key; }
    public static function csrf(): string { return '<input type="hidden" name="_csrf" value="' . self::e(Csrf::token()) . '">'; }
}
