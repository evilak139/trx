<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

define('INSTALL_LOCK_FILE', __DIR__ . '/../install.lock');
define('PROJECT_ROOT', dirname(__DIR__, 3));

function install_is_locked(): bool
{
    return is_file(INSTALL_LOCK_FILE);
}

function install_guard(): void
{
    if (install_is_locked()) {
        header('Location: locked.php');
        exit;
    }
}

function install_csrf_token(): string
{
    if (empty($_SESSION['install_csrf'])) {
        $_SESSION['install_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['install_csrf'];
}

function install_verify_csrf(?string $token): bool
{
    return !empty($_SESSION['install_csrf']) && is_string($token) && hash_equals($_SESSION['install_csrf'], $token);
}

function install_state(): array
{
    return $_SESSION['install'] ?? [];
}

function install_set(string $key, $value): void
{
    $_SESSION['install'][$key] = $value;
}

function install_get(string $key, $default = null)
{
    return $_SESSION['install'][$key] ?? $default;
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
