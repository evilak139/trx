<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
Auth::requireLogin();

try {
    $db = Database::getInstance();
} catch (\RuntimeException $e) {
    header('Location: /install/');
    exit;
}

$pdo = $db->pdo();
$configStmt = $pdo->query("SELECT * FROM `{$db->table('config')}` WHERE id = 1 LIMIT 1");
$config = $configStmt->fetch() ?: [];
$address = trim((string) ($config['receive_address'] ?? ''));

$stats = [
    'copy_total' => 0, 'copy_yesterday' => 0, 'copy_today' => 0,
    'transfer_total' => 0, 'transfer_yesterday' => 0, 'transfer_today' => 0,
];

if ($address !== '') {
    $copyTable = $db->table('copy_events');
    $transferTable = $db->table('transfer_records');
    $historyTable = $db->table('address_history');

    $stmt = $pdo->prepare("SELECT enabled_at FROM `{$historyTable}` WHERE address = ? ORDER BY enabled_at DESC LIMIT 1");
    $stmt->execute([$address]);
    $enabledAt = $stmt->fetchColumn() ?: null;

    if ($enabledAt) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `{$copyTable}` WHERE address = ? AND created_at >= ?");
        $stmt->execute([$address, $enabledAt]);
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `{$copyTable}` WHERE address = ?");
        $stmt->execute([$address]);
    }
    $stats['copy_total'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `{$copyTable}` WHERE address = ? AND DATE(created_at) = CURDATE()");
    $stmt->execute([$address]);
    $stats['copy_today'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `{$copyTable}` WHERE address = ? AND DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)");
    $stmt->execute([$address]);
    $stats['copy_yesterday'] = (int) $stmt->fetchColumn();

    if ($enabledAt) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `{$transferTable}` WHERE address = ? AND tx_timestamp >= ?");
        $stmt->execute([$address, $enabledAt]);
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `{$transferTable}` WHERE address = ?");
        $stmt->execute([$address]);
    }
    $stats['transfer_total'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `{$transferTable}` WHERE address = ? AND DATE(tx_timestamp) = CURDATE()");
    $stmt->execute([$address]);
    $stats['transfer_today'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `{$transferTable}` WHERE address = ? AND DATE(tx_timestamp) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)");
    $stmt->execute([$address]);
    $stats['transfer_yesterday'] = (int) $stmt->fetchColumn();
}

$conversionTotal = $stats['copy_total'] > 0 ? round($stats['transfer_total'] / $stats['copy_total'] * 100, 1) : 0.0;
$conversionToday = $stats['copy_today'] > 0 ? round($stats['transfer_today'] / $stats['copy_today'] * 100, 1) : 0.0;

require __DIR__ . '/includes/layout.php';
admin_head('数据面板', 'dashboard');
?>

<?php if ($address === ''): ?>
<div class="alert alert-warn">尚未配置收款地址，统计数据暂不可用，请前往"系统配置"完成设置。</div>
<?php else: ?>
<div class="card">当前生效收款地址：<code><?= e($address) ?></code></div>
<?php endif; ?>

<div class="stat-grid">
  <div class="stat-card"><div class="stat-label">地址复制次数（累计）</div><div class="stat-value"><?= $stats['copy_total'] ?></div></div>
  <div class="stat-card"><div class="stat-label">今日复制次数</div><div class="stat-value"><?= $stats['copy_today'] ?></div></div>
  <div class="stat-card"><div class="stat-label">昨日复制次数</div><div class="stat-value"><?= $stats['copy_yesterday'] ?></div></div>
  <div class="stat-card"><div class="stat-label">地址转账次数（累计）</div><div class="stat-value"><?= $stats['transfer_total'] ?></div></div>
  <div class="stat-card"><div class="stat-label">今日转账次数</div><div class="stat-value"><?= $stats['transfer_today'] ?></div></div>
  <div class="stat-card"><div class="stat-label">昨日转账次数</div><div class="stat-value"><?= $stats['transfer_yesterday'] ?></div></div>
  <div class="stat-card"><div class="stat-label">累计转化率</div><div class="stat-value"><?= $conversionTotal ?>%</div></div>
  <div class="stat-card"><div class="stat-label">今日转化率</div><div class="stat-value"><?= $conversionToday ?>%</div></div>
</div>

<div class="card" style="font-size:13px;color:#7a8ba3;">
  转化率为参考性指标：复制事件与链上转账之间无法建立精确的一一对应关系（同一访客可能多次复制、不点复制直接转账、或复制后并未转账），该数字只反映"复制行为总量"与"实际转账总量"的比例关系，不代表逐笔追踪的转化路径。
</div>

<?php
admin_foot();
