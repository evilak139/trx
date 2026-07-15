<?php

declare(strict_types=1);

function install_head(string $title, int $step = 0): void
{
    $steps = ['环境检测', '数据库配置', '创建数据表', '管理员账号', '站点信息', '完成'];
    ?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> - 安装向导</title>
<link rel="stylesheet" href="assets/install.css">
</head>
<body>
<div class="install-wrap">
  <h1 class="install-logo">TRX 能量兑换 · 安装向导</h1>
  <?php if ($step > 0): ?>
  <ol class="install-steps">
    <?php foreach ($steps as $i => $label): $n = $i + 1; ?>
    <li class="<?= $n === $step ? 'active' : ($n < $step ? 'done' : '') ?>"><?= $n ?>. <?= e($label) ?></li>
    <?php endforeach; ?>
  </ol>
  <?php endif; ?>
  <div class="install-card">
<?php
}

function install_foot(): void
{
?>
  </div>
</div>
</body>
</html>
<?php
}
