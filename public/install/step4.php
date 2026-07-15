<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
install_guard();

$db = install_get('db');
if (!$db || !install_get('tables_created')) {
    header('Location: step3.php');
    exit;
}

function install_db_connect(array $db): PDO
{
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], (int) $db['port'], $db['dbname']);
    return new PDO($dsn, $db['user'], $db['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

$errors = [];
$username = install_get('admin_username', '');

if (install_get('admin_created')) {
    header('Location: step5.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!install_verify_csrf($_POST['csrf'] ?? null)) {
        $errors[] = 'CSRF 校验失败，请刷新页面重试';
    } else {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['confirm'] ?? '');

        if (!preg_match('/^[A-Za-z0-9_]{3,50}$/', $username)) {
            $errors[] = '用户名需为 3-50 位字母、数字或下划线';
        }
        if (strlen($password) < 8) {
            $errors[] = '密码长度至少 8 位';
        }
        if ($password !== $confirm) {
            $errors[] = '两次输入的密码不一致';
        }

        if (empty($errors)) {
            try {
                $pdo = install_db_connect($db);
                $table = $db['prefix'] . 'admins';
                $stmt = $pdo->prepare("INSERT INTO `{$table}` (username, password_hash, created_at) VALUES (?, ?, NOW())");
                $stmt->execute([$username, password_hash($password, PASSWORD_BCRYPT)]);

                install_set('admin_username', $username);
                install_set('admin_created', true);
                header('Location: step5.php');
                exit;
            } catch (\PDOException $e) {
                if ((int) $e->getCode() === 23000 || str_contains($e->getMessage(), 'uniq_username')) {
                    $errors[] = '该用户名已存在，请更换';
                } else {
                    $errors[] = '创建管理员账号失败：' . $e->getMessage();
                }
            }
        }
    }
}

require __DIR__ . '/includes/layout.php';
install_head('创建管理员账号', 4);
$csrf = install_csrf_token();
?>
<h2>创建超级管理员账号</h2>

<?php foreach ($errors as $err): ?>
<div class="alert alert-error"><?= e($err) ?></div>
<?php endforeach; ?>

<form method="post">
  <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
  <div class="form-group">
    <label>用户名</label>
    <input type="text" name="username" value="<?= e($username) ?>" required>
  </div>
  <div class="form-group">
    <label>密码（至少 8 位）</label>
    <input type="password" name="password" required minlength="8">
  </div>
  <div class="form-group">
    <label>确认密码</label>
    <input type="password" name="confirm" required minlength="8">
  </div>
  <div class="actions">
    <span></span>
    <button type="submit" class="btn">下一步：站点信息</button>
  </div>
</form>
<?php
install_foot();
