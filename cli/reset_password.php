<?php

declare(strict_types=1);

/**
 * 应急命令行密码重置工具，仅限服务器本地/SSH 环境执行，不对外暴露 HTTP 入口。
 * 用法：php cli/reset_password.php --username=admin --password=NewPassw0rd
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require __DIR__ . '/../includes/Database.php';

$options = getopt('', ['username:', 'password:']);
$username = trim((string) ($options['username'] ?? ''));
$password = (string) ($options['password'] ?? '');

if ($username === '' || $password === '') {
    fwrite(STDERR, "用法：php cli/reset_password.php --username=admin --password=NewPassw0rd\n");
    exit(1);
}

if (strlen($password) < 8) {
    fwrite(STDERR, "密码长度至少 8 位\n");
    exit(1);
}

try {
    $db = Database::getInstance();
} catch (\RuntimeException $e) {
    fwrite(STDERR, "系统尚未安装：{$e->getMessage()}\n");
    exit(1);
}

$pdo = $db->pdo();
$table = $db->table('admins');

$stmt = $pdo->prepare("SELECT id FROM `{$table}` WHERE username = ? LIMIT 1");
$stmt->execute([$username]);
$admin = $stmt->fetch();

if (!$admin) {
    fwrite(STDERR, "未找到用户名为 \"{$username}\" 的管理员账号\n");
    exit(1);
}

$update = $pdo->prepare(
    "UPDATE `{$table}` SET password_hash = ?, failed_attempts = 0, locked_until = NULL WHERE id = ?"
);
$update->execute([password_hash($password, PASSWORD_BCRYPT), $admin['id']]);

echo "管理员 \"{$username}\" 的密码已重置成功，并已解除登录锁定。\n";
exit(0);
