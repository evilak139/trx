<?php

declare(strict_types=1);

/** 轻量文件日志，按日期分文件写入 logs/ 目录 */
class Logger
{
    public static function write(string $channel, string $message): void
    {
        $dir = __DIR__ . '/../logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $file = $dir . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '', $channel) . '-' . date('Y-m-d') . '.log';
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }
}
