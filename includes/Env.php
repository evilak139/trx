<?php

declare(strict_types=1);

/**
 * 极简 .env 解析器，仅支持 KEY=VALUE 格式，用于加载收款地址二次确认密码等
 * 不放在数据库中的敏感配置。
 */
class Env
{
    private static array $vars = [];
    private static bool $loaded = false;

    public static function load(?string $path = null): void
    {
        if (self::$loaded && $path === null) {
            return;
        }
        self::$loaded = true;

        $path = $path ?? __DIR__ . '/../.env';
        if (!is_file($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim(trim($value), "\"'");
            self::$vars[$key] = $value;
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        self::load();
        return self::$vars[$key] ?? $default;
    }
}
