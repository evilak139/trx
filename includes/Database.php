<?php

declare(strict_types=1);

/**
 * PDO 单例封装 + 表前缀管理。
 * 连接信息来自 config/database.php（由安装向导生成）。
 */
class Database
{
    private static ?Database $instance = null;

    private PDO $pdo;
    private string $prefix;

    private function __construct()
    {
        $configFile = __DIR__ . '/../config/database.php';
        if (!is_file($configFile)) {
            throw new RuntimeException('数据库尚未配置，请先完成安装向导');
        }

        $config = require $configFile;
        $this->prefix = (string) ($config['prefix'] ?? '');

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $config['host'],
            (int) $config['port'],
            $config['dbname']
        );

        $this->pdo = new PDO($dsn, $config['user'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    /** 返回带前缀的表名，用于拼接 SQL 中的表名部分（不涉及用户输入，安全） */
    public function table(string $name): string
    {
        return $this->prefix . $name;
    }
}
