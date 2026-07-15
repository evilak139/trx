<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
install_guard();

// 每次重新访问安装向导入口都视为一次全新的安装流程，清空上一次残留的会话状态，
// 避免"删库重装"时因为旧会话里的 tables_created/admin_created 等标记仍为 true
// 而跳过实际的建表/建管理员/写配置步骤，导致新数据库里缺数据、旧配置文件未更新。
unset($_SESSION['install']);

require __DIR__ . '/includes/layout.php';

$checks = [];

$phpOk = version_compare(PHP_VERSION, '8.0.0', '>=');
$checks[] = ['label' => 'PHP 版本 >= 8.0（当前 ' . PHP_VERSION . '）', 'ok' => $phpOk];

foreach (['pdo_mysql', 'curl', 'json', 'bcmath'] as $ext) {
    $checks[] = ['label' => "PHP 扩展：{$ext}", 'ok' => extension_loaded($ext)];
}
$checks[] = ['label' => 'PHP 扩展：gd 或 fileinfo（Logo 上传校验）', 'ok' => extension_loaded('gd') || extension_loaded('fileinfo')];

$writableDirs = [
    'uploads' => PROJECT_ROOT . '/public/uploads',
    'logs' => PROJECT_ROOT . '/logs',
    'config' => PROJECT_ROOT . '/config',
];
foreach ($writableDirs as $label => $path) {
    $ok = is_dir($path) && is_writable($path);
    $checks[] = ['label' => "目录可写：{$label}/", 'ok' => $ok];
}

$allOk = true;
foreach ($checks as $c) {
    if (!$c['ok']) {
        $allOk = false;
    }
}

install_head('环境检测', 1);
?>
<h2>环境检测</h2>
<table class="check-table">
  <?php foreach ($checks as $c): ?>
  <tr>
    <td><?= e($c['label']) ?></td>
    <td><?= $c['ok'] ? '<span class="badge-ok">通过</span>' : '<span class="badge-fail">未通过</span>' ?></td>
  </tr>
  <?php endforeach; ?>
</table>

<?php if (!$allOk): ?>
<div class="alert alert-error">存在未通过的检测项，请先解决后刷新本页面。</div>
<?php else: ?>
<div class="alert alert-success">环境检测全部通过，可以进入下一步。</div>
<?php endif; ?>

<div class="actions">
  <span></span>
  <a class="btn <?= $allOk ? '' : 'disabled' ?>" href="<?= $allOk ? 'step2.php' : '#' ?>" <?= $allOk ? '' : 'aria-disabled="true" onclick="return false;"' ?>>下一步：数据库配置</a>
</div>
<?php
install_foot();
