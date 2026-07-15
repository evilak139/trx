<?php

declare(strict_types=1);

function admin_head(string $title, string $active = ''): void
{
    $nav = [
        'dashboard' => ['label' => '数据面板', 'href' => '/admin/dashboard.php'],
        'config' => ['label' => '系统配置', 'href' => '/admin/config/index.php'],
        'accounts' => ['label' => '账号管理', 'href' => '/admin/accounts.php'],
    ];
    $username = Auth::username();
    ?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> - 后台管理</title>
<meta name="csrf-token" content="<?= e(Auth::csrfToken()) ?>">
<link rel="stylesheet" href="/admin/assets/css/admin.css">
</head>
<body>
<div class="admin-shell">
  <aside class="admin-sidebar">
    <div class="admin-brand">TRX 能量兑换</div>
    <nav>
      <?php foreach ($nav as $key => $item): ?>
      <a class="nav-item <?= $key === $active ? 'active' : '' ?>" href="<?= e($item['href']) ?>"><?= e($item['label']) ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="admin-user">
      <div><?= e($username ?? '') ?></div>
      <form method="post" action="/admin/logout.php">
        <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
        <button type="submit" class="link-btn">退出登录</button>
      </form>
    </div>
  </aside>
  <main class="admin-main">
    <h1 class="admin-title"><?= e($title) ?></h1>
<?php
}

function admin_foot(): void
{
?>
  </main>
</div>
<script src="/admin/assets/js/admin.js"></script>
</body>
</html>
<?php
}
