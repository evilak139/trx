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

const MAX_LOGO_SIZE = 2 * 1024 * 1024;
const ALLOWED_MIME = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/svg+xml' => 'svg',
];

$file = $_FILES['logo'] ?? null;

if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    flash_set('error', '文件上传失败，请重试');
    header('Location: index.php');
    exit;
}

if ($file['size'] > MAX_LOGO_SIZE) {
    flash_set('error', 'Logo 文件不能超过 2MB');
    header('Location: index.php');
    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']) ?: '';

// SVG 常被识别为 text/plain 或 text/html，用扩展名 + 内容特征兜底判断
$originalExt = strtolower((string) pathinfo((string) $file['name'], PATHINFO_EXTENSION));
$looksLikeSvg = $originalExt === 'svg' && str_contains((string) file_get_contents($file['tmp_name'], false, null, 0, 512), '<svg');

if (isset(ALLOWED_MIME[$mime])) {
    $ext = ALLOWED_MIME[$mime];
} elseif ($looksLikeSvg) {
    $ext = 'svg';
} else {
    flash_set('error', '仅支持 jpg/png/svg 格式的图片');
    header('Location: index.php');
    exit;
}

if ($ext === 'svg') {
    $content = (string) file_get_contents($file['tmp_name']);
    if (stripos($content, '<script') !== false || stripos($content, 'onload=') !== false) {
        flash_set('error', 'SVG 文件包含不安全内容，已拒绝上传');
        header('Location: index.php');
        exit;
    }
}

try {
    $db = Database::getInstance();
} catch (\RuntimeException $e) {
    header('Location: /install/');
    exit;
}

$pdo = $db->pdo();
$configTable = $db->table('config');

$stmt = $pdo->query("SELECT logo_path FROM `{$configTable}` WHERE id = 1 LIMIT 1");
$oldLogoPath = (string) ($stmt->fetchColumn() ?: '');

$filename = bin2hex(random_bytes(16)) . '.' . $ext;
$uploadDir = __DIR__ . '/../../uploads';
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0755, true);
}
$destination = $uploadDir . '/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $destination)) {
    flash_set('error', '保存文件失败，请检查 uploads/ 目录权限');
    header('Location: index.php');
    exit;
}

$update = $pdo->prepare("UPDATE `{$configTable}` SET logo_path = ? WHERE id = 1");
$update->execute([$filename]);

if ($oldLogoPath !== '') {
    $oldFile = $uploadDir . '/' . basename($oldLogoPath);
    if (is_file($oldFile)) {
        @unlink($oldFile);
    }
}

flash_set('success', 'Logo 已更新');
header('Location: index.php');
