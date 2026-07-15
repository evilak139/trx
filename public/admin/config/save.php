<?php

declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: index.php');
    exit;
}

if (!Auth::verifyCsrf($_POST['csrf'] ?? null)) {
    flash_set('error', 'CSRF 校验失败，请重试');
    header('Location: index.php');
    exit;
}

try {
    $db = Database::getInstance();
} catch (\RuntimeException $e) {
    header('Location: /install/');
    exit;
}

$pdo = $db->pdo();
$configTable = $db->table('config');
$historyTable = $db->table('address_history');

$stmt = $pdo->query("SELECT * FROM `{$configTable}` WHERE id = 1 LIMIT 1");
$current = $stmt->fetch() ?: [];
$oldAddress = trim((string) ($current['receive_address'] ?? ''));

$siteTitle = trim((string) ($_POST['site_title'] ?? ''));
$serviceUrl = trim((string) ($_POST['customer_service_url'] ?? ''));
$newAddress = trim((string) ($_POST['receive_address'] ?? ''));
$copyTip = trim((string) ($_POST['copy_tip_text'] ?? ''));
$apiKey = trim((string) ($_POST['trongrid_api_key'] ?? ''));
$confirmPassword = (string) ($_POST['address_confirm_password'] ?? '');

$errors = [];

if ($siteTitle === '') {
    $errors[] = '网站标题不能为空';
}
if (!is_valid_service_url($serviceUrl)) {
    $errors[] = '客服链接格式不正确';
}
if ($newAddress !== '' && !TronAddress::isValid($newAddress)) {
    $errors[] = '收款地址格式不正确（需为 T 开头的 34 位 TRON 地址）';
}

$addressChanged = $newAddress !== $oldAddress;

if ($addressChanged) {
    $expected = Env::get('ADDRESS_CONFIRM_PASSWORD');
    if (!$expected) {
        $errors[] = '服务器未配置收款地址二次确认密码（.env 缺失 ADDRESS_CONFIRM_PASSWORD），已拒绝修改地址';
    } elseif ($confirmPassword === '' || !hash_equals($expected, $confirmPassword)) {
        $errors[] = '二次确认密码错误，收款地址未修改';
    }
}

if (!empty($errors)) {
    flash_set('error', implode('；', $errors));
    header('Location: index.php');
    exit;
}

$pdo->beginTransaction();
try {
    $update = $pdo->prepare(
        "UPDATE `{$configTable}` SET site_title = ?, customer_service_url = ?, receive_address = ?, copy_tip_text = ?, trongrid_api_key = ? WHERE id = 1"
    );
    $update->execute([$siteTitle, $serviceUrl, $newAddress, $copyTip, $apiKey ?: null]);

    if ($addressChanged) {
        if ($oldAddress !== '') {
            $disable = $pdo->prepare(
                "UPDATE `{$historyTable}` SET disabled_at = NOW() WHERE address = ? AND disabled_at IS NULL"
            );
            $disable->execute([$oldAddress]);
        }
        if ($newAddress !== '') {
            $insert = $pdo->prepare(
                "INSERT INTO `{$historyTable}` (address, enabled_at, operator) VALUES (?, NOW(), ?)"
            );
            $insert->execute([$newAddress, Auth::username()]);
        }
        Logger::write('config', sprintf(
            '管理员 %s 修改收款地址：%s -> %s',
            (string) Auth::username(),
            $oldAddress !== '' ? $oldAddress : '(空)',
            $newAddress !== '' ? $newAddress : '(空)'
        ));
    }

    $pdo->commit();
} catch (\Throwable $e) {
    $pdo->rollBack();
    flash_set('error', '保存失败：' . $e->getMessage());
    header('Location: index.php');
    exit;
}

flash_set('success', '配置已保存');
header('Location: index.php');
