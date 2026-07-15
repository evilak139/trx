<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
install_guard();

$db = install_get('db');
if (!$db || !install_get('config_created')) {
    header('Location: step5.php');
    exit;
}

$errors = [];
$secret = install_get('address_confirm_password');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$secret) {
    if (!install_verify_csrf($_POST['csrf'] ?? null)) {
        $errors[] = 'CSRF 校验失败，请刷新页面重试';
    } else {
        try {
            $configPath = PROJECT_ROOT . '/config/database.php';
            $configContent = "<?php\n\nreturn [\n"
                . "    'host' => " . var_export($db['host'], true) . ",\n"
                . "    'port' => " . (int) $db['port'] . ",\n"
                . "    'dbname' => " . var_export($db['dbname'], true) . ",\n"
                . "    'user' => " . var_export($db['user'], true) . ",\n"
                . "    'password' => " . var_export($db['password'], true) . ",\n"
                . "    'prefix' => " . var_export($db['prefix'], true) . ",\n"
                . "];\n";

            if (file_put_contents($configPath, $configContent) === false) {
                throw new RuntimeException('无法写入 config/database.php，请检查目录写权限');
            }
            @chmod($configPath, 0640);

            $secret = bin2hex(random_bytes(16));
            $envPath = PROJECT_ROOT . '/.env';
            $envContent = "# 收款地址二次确认密码，安装时自动生成，独立于管理员登录密码\n"
                . "# 后台修改收款地址时需要输入此密码；请妥善保管，不要提交到版本控制\n"
                . "ADDRESS_CONFIRM_PASSWORD={$secret}\n";
            if (file_put_contents($envPath, $envContent) === false) {
                throw new RuntimeException('无法写入 .env，请检查目录写权限');
            }
            @chmod($envPath, 0640);

            if (file_put_contents(INSTALL_LOCK_FILE, 'installed at ' . date('c') . PHP_EOL) === false) {
                throw new RuntimeException('无法写入 install.lock，请检查 install/ 目录写权限');
            }

            install_set('address_confirm_password', $secret);
        } catch (\Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}

require __DIR__ . '/includes/layout.php';
install_head('完成安装', 6);
?>
<h2>完成安装</h2>

<?php foreach ($errors as $err): ?>
<div class="alert alert-error"><?= e($err) ?></div>
<?php endforeach; ?>

<?php if ($secret): ?>
  <div class="alert alert-success">安装完成！</div>
  <p>以下是<strong>收款地址二次确认密码</strong>（用于后台修改收款地址时的安全确认，独立于管理员登录密码，仅显示这一次）：</p>
  <code class="secret"><?= e($secret) ?></code>
  <div class="alert alert-warn">请立即将此密码保存到安全的地方（如密码管理器）。它同时也保存在服务器的 <code>.env</code> 文件中，请勿将该文件提交到版本控制或对外暴露。</div>
  <p>为了安全，请尽快手动删除或重命名 <code>install/</code> 目录。</p>
  <div class="actions">
    <span></span>
    <a class="btn" href="/admin/login.php">前往后台登录</a>
  </div>
<?php elseif (empty($errors)): ?>
  <p>点击下方按钮完成最后的写入操作：生成 <code>config/database.php</code>、<code>.env</code> 与 <code>install.lock</code>。</p>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= e(install_csrf_token()) ?>">
    <div class="actions">
      <a class="btn secondary" href="step5.php">上一步</a>
      <button type="submit" class="btn">完成安装</button>
    </div>
  </form>
<?php else: ?>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= e(install_csrf_token()) ?>">
    <div class="actions">
      <a class="btn secondary" href="step5.php">上一步</a>
      <button type="submit" class="btn">重试</button>
    </div>
  </form>
<?php endif; ?>
<?php
install_foot();
