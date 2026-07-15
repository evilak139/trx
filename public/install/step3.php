<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
install_guard();

$db = install_get('db');
if (!$db || !install_get('db_tested')) {
    header('Location: step2.php');
    exit;
}

$errors = [];
$results = [];
$done = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!install_verify_csrf($_POST['csrf'] ?? null)) {
        $errors[] = 'CSRF 校验失败，请刷新页面重试';
    } else {
        try {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], (int) $db['port'], $db['dbname']);
            $pdo = new PDO($dsn, $db['user'], $db['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);

            $schema = require __DIR__ . '/schema.php';
            foreach ($schema as $table => $sqlTemplate) {
                $sql = str_replace('{prefix}', $db['prefix'], $sqlTemplate);
                $pdo->exec($sql);
                $results[] = $db['prefix'] . $table;
            }

            install_set('tables_created', true);
            $done = true;
        } catch (\PDOException $e) {
            $errors[] = '建表失败：' . $e->getMessage();
        }
    }
}

require __DIR__ . '/includes/layout.php';
install_head('创建数据表', 3);
$csrf = install_csrf_token();
?>
<h2>创建数据表</h2>

<?php foreach ($errors as $err): ?>
<div class="alert alert-error"><?= e($err) ?></div>
<?php endforeach; ?>

<?php if ($done): ?>
  <div class="alert alert-success">数据表创建成功。</div>
  <table class="check-table">
    <?php foreach ($results as $t): ?>
    <tr><td><?= e($t) ?></td><td><span class="badge-ok">已创建</span></td></tr>
    <?php endforeach; ?>
  </table>
  <div class="actions">
    <span></span>
    <a class="btn" href="step4.php">下一步：创建管理员账号</a>
  </div>
<?php else: ?>
  <p>即将连接数据库 <code><?= e($db['dbname']) ?></code> 并创建以下数据表：<code>config</code>、<code>admins</code>、<code>address_history</code>、<code>copy_events</code>、<code>transfer_records</code>。</p>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <div class="actions">
      <a class="btn secondary" href="step2.php">上一步</a>
      <button type="submit" class="btn">执行建表</button>
    </div>
  </form>
<?php endif; ?>
<?php
install_foot();
