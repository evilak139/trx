<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';

/**
 * 管理员登录态 / CSRF / 登录失败限流。
 * 限流状态存储在 admins 表的 failed_attempts / locked_until 字段。
 */
class Auth
{
    private const MAX_ATTEMPTS = 5;
    private const LOCK_MINUTES = 10;

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => self::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    private static function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }
        return isset($_SERVER['HTTP_X_FORWARDED_PROTO'])
            && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https';
    }

    public static function check(): bool
    {
        self::start();
        return !empty($_SESSION['admin_id']);
    }

    public static function requireLogin(): void
    {
        self::start();
        if (!self::check()) {
            header('Location: /admin/login.php');
            exit;
        }
    }

    public static function id(): ?int
    {
        self::start();
        return isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null;
    }

    public static function username(): ?string
    {
        self::start();
        return $_SESSION['admin_username'] ?? null;
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    public static function attempt(string $username, string $password): array
    {
        $db = Database::getInstance();
        $pdo = $db->pdo();
        $table = $db->table('admins');

        $stmt = $pdo->prepare("SELECT * FROM `{$table}` WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        if (!$admin) {
            // 即使用户名不存在也执行一次哈希校验，避免通过响应耗时枚举用户名
            password_verify($password, '$2y$10$og3PyMYhoJp2FTqPdrIXG.fDW1gdHdWnn5brap2orvxRvgnwPZh2m');
            return ['ok' => false, 'error' => '用户名或密码错误'];
        }

        if (!empty($admin['locked_until']) && strtotime((string) $admin['locked_until']) > time()) {
            $remain = (int) ceil((strtotime((string) $admin['locked_until']) - time()) / 60);
            return ['ok' => false, 'error' => "登录失败次数过多，请 {$remain} 分钟后重试"];
        }

        if (!password_verify($password, $admin['password_hash'])) {
            $attempts = (int) $admin['failed_attempts'] + 1;
            if ($attempts >= self::MAX_ATTEMPTS) {
                $lockedUntil = date('Y-m-d H:i:s', time() + self::LOCK_MINUTES * 60);
                $upd = $pdo->prepare("UPDATE `{$table}` SET failed_attempts = 0, locked_until = ? WHERE id = ?");
                $upd->execute([$lockedUntil, $admin['id']]);
                return ['ok' => false, 'error' => '登录失败次数过多，账号已锁定 ' . self::LOCK_MINUTES . ' 分钟'];
            }
            $upd = $pdo->prepare("UPDATE `{$table}` SET failed_attempts = ? WHERE id = ?");
            $upd->execute([$attempts, $admin['id']]);
            return ['ok' => false, 'error' => '用户名或密码错误'];
        }

        $upd = $pdo->prepare(
            "UPDATE `{$table}` SET failed_attempts = 0, locked_until = NULL, last_login_at = NOW() WHERE id = ?"
        );
        $upd->execute([$admin['id']]);

        self::start();
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int) $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];

        return ['ok' => true];
    }

    /** 校验当前登录管理员的密码（用于账号管理里的改密等场景） */
    public static function verifyPassword(string $password): bool
    {
        self::start();
        if (!self::check()) {
            return false;
        }

        $db = Database::getInstance();
        $pdo = $db->pdo();
        $table = $db->table('admins');
        $stmt = $pdo->prepare("SELECT password_hash FROM `{$table}` WHERE id = ?");
        $stmt->execute([self::id()]);
        $row = $stmt->fetch();

        return $row && password_verify($password, $row['password_hash']);
    }

    public static function logout(): void
    {
        self::start();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
    }

    public static function csrfToken(): string
    {
        self::start();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function verifyCsrf(?string $token): bool
    {
        self::start();
        return !empty($_SESSION['csrf_token']) && is_string($token) && hash_equals($_SESSION['csrf_token'], $token);
    }
}
