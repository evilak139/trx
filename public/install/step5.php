<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/../../includes/TronAddress.php';
install_guard();

$db = install_get('db');
if (!$db || !install_get('admin_created')) {
    header('Location: step4.php');
    exit;
}

if (install_get('config_created')) {
    header('Location: finish.php');
    exit;
}

function install_db_connect_5(array $db): PDO
{
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], (int) $db['port'], $db['dbname']);
    return new PDO($dsn, $db['user'], $db['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

$errors = [];
$siteTitle = install_get('site_title', 'TRX能量兑换');
$receiveAddress = install_get('receive_address', '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!install_verify_csrf($_POST['csrf'] ?? null)) {
        $errors[] = 'CSRF 校验失败，请刷新页面重试';
    } else {
        $siteTitle = trim((string) ($_POST['site_title'] ?? '')) ?: 'TRX能量兑换';
        $receiveAddress = trim((string) ($_POST['receive_address'] ?? ''));

        if ($receiveAddress !== '' && !TronAddress::isValid($receiveAddress)) {
            $errors[] = '收款地址格式不正确（需为 T 开头的 34 位 TRON 地址）';
        }

        if (empty($errors)) {
            try {
                $pdo = install_db_connect_5($db);
                $configTable = $db['prefix'] . 'config';
                $historyTable = $db['prefix'] . 'address_history';

                $stmt = $pdo->prepare(
                    "INSERT INTO `{$configTable}` (id, site_title, receive_address) VALUES (1, ?, ?)"
                );
                $stmt->execute([$siteTitle, $receiveAddress]);

                if ($receiveAddress !== '') {
                    $stmt = $pdo->prepare(
                        "INSERT INTO `{$historyTable}` (address, enabled_at, operator) VALUES (?, NOW(), ?)"
                    );
                    $stmt->execute([$receiveAddress, install_get('admin_username')]);
                }

                install_set('site_title', $siteTitle);
                install_set('receive_address', $receiveAddress);
                install_set('config_created', true);
                header('Location: finish.php');
                exit;
            } catch (\PDOException $e) {
                $errors[] = '保存站点信息失败：' . $e->getMessage();
            }
        }
    }
}

require __DIR__ . '/includes/layout.php';
install_head('站点信息', 5);
$csrf = install_csrf_token();
?>
<h2>基础站点信息（可选）</h2>
<p class="hint">此步骤可以留空，之后在后台"系统配置"中补充。</p>

<?php foreach ($errors as $err): ?>
<div class="alert alert-error"><?= e($err) ?></div>
<?php endforeach; ?>

<form method="post">
  <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
  <div class="form-group">
    <label>网站标题</label>
    <input type="text" name="site_title" value="<?= e($siteTitle) ?>">
  </div>
  <div class="form-group">
    <label>初始收款地址</label>
    <input type="text" name="receive_address" value="<?= e($receiveAddress) ?>" placeholder="T 开头的 34 位 TRON 地址，留空可稍后配置">
  </div>
  <div class="actions">
    <a class="btn secondary" href="step4.php">上一步</a>
    <button type="submit" class="btn">下一步：完成安装</button>
  </div>
</form>
<?php
install_foot();
