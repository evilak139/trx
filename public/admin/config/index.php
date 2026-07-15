<?php

declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();

try {
    $db = Database::getInstance();
} catch (\RuntimeException $e) {
    header('Location: /install/');
    exit;
}

$pdo = $db->pdo();
$stmt = $pdo->query("SELECT * FROM `{$db->table('config')}` WHERE id = 1 LIMIT 1");
$config = $stmt->fetch() ?: [];

$flash = flash_get();

require __DIR__ . '/../includes/layout.php';
admin_head('系统配置', 'config');
$csrf = Auth::csrfToken();
?>

<?php if ($flash): ?>
<div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
<?php endif; ?>

<div class="card">
  <h2 style="margin-top:0;font-size:16px;">Logo</h2>
  <?php if (!empty($config['logo_path'])): ?>
    <img class="logo-preview" src="<?= e('/uploads/' . ltrim((string) $config['logo_path'], '/')) ?>" alt="logo">
  <?php endif; ?>
  <form method="post" action="upload_logo.php" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <div class="form-group">
      <input type="file" name="logo" accept=".jpg,.jpeg,.png,.svg,image/jpeg,image/png,image/svg+xml" required>
      <div class="hint">支持 jpg/png/svg，大小不超过 2MB</div>
    </div>
    <button type="submit" class="btn secondary">上传 Logo</button>
  </form>
</div>

<form method="post" action="save.php">
  <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

  <div class="card">
    <h2 style="margin-top:0;font-size:16px;">基础信息</h2>
    <div class="form-group">
      <label>网站标题</label>
      <input type="text" name="site_title" value="<?= e((string) ($config['site_title'] ?? '')) ?>" required>
    </div>
    <div class="form-group">
      <label>客服链接</label>
      <input type="url" name="customer_service_url" value="<?= e((string) ($config['customer_service_url'] ?? '')) ?>" placeholder="https://...">
    </div>
    <div class="form-group">
      <label>复制提示文案</label>
      <textarea name="copy_tip_text" rows="3"><?= e((string) ($config['copy_tip_text'] ?? '')) ?></textarea>
      <div class="hint">留空则使用默认文案</div>
    </div>
    <div class="form-group">
      <label>TronGrid API Key（选填）</label>
      <input type="text" name="trongrid_api_key" value="<?= e((string) ($config['trongrid_api_key'] ?? '')) ?>">
    </div>
  </div>

  <div class="card">
    <h2 style="margin-top:0;font-size:16px;">收款地址</h2>
    <div class="form-group">
      <label>收款地址</label>
      <input type="text" name="receive_address" id="receive_address" value="<?= e((string) ($config['receive_address'] ?? '')) ?>" placeholder="T 开头的 34 位 TRON 地址">
    </div>
    <div class="form-group">
      <label>二次确认密码</label>
      <input type="password" name="address_confirm_password" autocomplete="off">
      <div class="hint">仅当修改收款地址时需要填写，这是安装时生成、独立于登录密码的确认密码（保存在服务器 .env 文件中）</div>
    </div>
  </div>

  <button type="submit" class="btn" id="save-btn">保存配置</button>
</form>

<?php
admin_foot();
