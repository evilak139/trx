<?php

declare(strict_types=1);

require __DIR__ . '/../../includes/Database.php';
require __DIR__ . '/../../includes/functions.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_error('Method Not Allowed', 405);
}

try {
    $db = Database::getInstance();
} catch (\RuntimeException $e) {
    json_error('系统尚未安装', 503);
}

$pdo = $db->pdo();

// 记录的地址始终来自服务端当前生效配置，不信任客户端传入的任何地址值，
// 防止被用来向 copy_events 写入虚假地址污染统计数据。
$stmt = $pdo->query("SELECT receive_address FROM `{$db->table('config')}` WHERE id = 1 LIMIT 1");
$row = $stmt->fetch();
$address = trim((string) ($row['receive_address'] ?? ''));

if ($address === '') {
    json_error('收款地址未配置', 400);
}

$insert = $pdo->prepare(
    "INSERT INTO `{$db->table('copy_events')}` (address, ip, user_agent, created_at) VALUES (?, ?, ?, NOW())"
);
$insert->execute([$address, client_ip(), client_user_agent()]);

json_success();
