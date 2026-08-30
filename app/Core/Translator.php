<?php

declare(strict_types=1);

namespace FilamentManager\Core;

final class Translator
{
    private array $messages = [];
    public function __construct(private readonly App $app) {}

    public function get(string $key, array $replace = []): string
    {
        $locale = $this->app->auth()->user()['locale'] ?? $this->app->config('locale', $this->app->config('default_locale', 'cs'));
        return $this->getForLocale((string) $locale, $key, $replace);
    }

    public function getForLocale(string $locale, string $key, array $replace = []): string
    {
        $locale = in_array($locale, ['cs', 'en'], true) ? $locale : (string) $this->app->config('default_locale', 'cs');
        if (!isset($this->messages[$locale])) {
            $file = FM_ROOT . '/resources/lang/' . $locale . '/messages.php';
            $this->messages[$locale] = is_file($file) ? require $file : [];
        }
        $message = (string) ($this->messages[$locale][$key] ?? $key);
        foreach ($replace as $name => $value) $message = str_replace(':' . $name, (string) $value, $message);
        return $message;
    }
}
