<?php

namespace App\Support;

/**
 * Interface languages. Text is keyed by the English source, so English needs no file and an
 * untranslated string simply shows in English. Laravel's own messages live in lang/{code}/*.php.
 */
class Locales
{
    public const ALL = [
        'en' => 'English',
        'ms' => 'Bahasa Melayu',
        'zh' => '中文',
    ];

    public const DEFAULT = 'en';

    public static function valid(mixed $code): bool
    {
        return is_string($code) && array_key_exists($code, self::ALL);
    }

    /**
     * @return array<string, string>
     */
    public static function lines(string $locale): array
    {
        static $cache = [];
        if ($locale === self::DEFAULT) {
            return [];
        }

        return $cache[$locale] ??= (function () use ($locale) {
            $path = lang_path("{$locale}.json");
            $lines = is_file($path) ? json_decode((string) file_get_contents($path), true) : [];

            return is_array($lines) ? $lines : [];
        })();
    }
}
