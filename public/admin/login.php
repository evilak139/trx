<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

if (Auth::check()) {
    header('Location: /admin/dashboard.php');
    exit;
}

$error = null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf'] ?? null)) {
        $error = 'CSRF 校验失败，请刷新页面重试';
    } else {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        try {
            $result = Auth::attempt($username, $password);
        } catch (\RuntimeException $e) {
            header('Location: /install/');
            exit;
        }

        if ($result['ok']) {
            header('Location: /admin/dashboard.php');
            exit;
        }
        $error = $result['error'];
    }
}

$csrf = Auth::csrfToken();
?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>后台登录</title>
<link rel="stylesheet" href="/admin/assets/css/admin.css">
</head>
<body>
<div class="login-page">
  <div class="login-card">
    <h1>TRX 能量兑换 · 后台登录</h1>
    <?php if ($error): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
      <div class="form-group">
        <label>用户名</label>
        <input type="text" name="username" required autofocus>
      </div>
      <div class="form-group">
        <label>密码</label>
        <input type="password" name="password" required>
      </div>
      <button type="submit" class="btn" style="width:100%">登录</button>
    </form>
  </div>
</div>
</body>
</html>
