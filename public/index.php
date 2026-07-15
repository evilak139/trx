<?php

declare(strict_types=1);

require __DIR__ . '/../includes/Database.php';
require __DIR__ . '/../includes/functions.php';

try {
    $db = Database::getInstance();
} catch (\RuntimeException $e) {
    header('Location: /install/');
    exit;
}

$pdo = $db->pdo();
$table = $db->table('config');
$stmt = $pdo->query("SELECT * FROM `{$table}` WHERE id = 1 LIMIT 1");
$config = $stmt->fetch() ?: [];

$siteTitle = (string) ($config['site_title'] ?? 'TRX能量兑换');
$logoPath = (string) ($config['logo_path'] ?? '');
$serviceUrl = (string) ($config['customer_service_url'] ?? '');
$address = trim((string) ($config['receive_address'] ?? ''));
$serviceHtml = sanitize_html((string) ($config['service_html'] ?? ''));
$stepsHtml = sanitize_html((string) ($config['steps_html'] ?? ''));
$noticeHtml = sanitize_html((string) ($config['notice_html'] ?? ''));
$disclaimerHtml = sanitize_html((string) ($config['disclaimer_html'] ?? ''));
$copyTipRaw = trim((string) ($config['copy_tip_text'] ?? ''));
$copyTip = $copyTipRaw !== ''
    ? $copyTipRaw
    : '地址已复制，请使用你的钱包向该地址转账1TRX，系统将自动为转账地址分配转账能量，如未及时收到能量，请联系在线客服';

$hasAddress = $address !== '';
$hasLogo = $logoPath !== '';
$hasService = is_valid_service_url($serviceUrl);

$faqs = $pdo->query("SELECT question, answer FROM `{$db->table('faqs')}` ORDER BY sort_order ASC, id ASC")->fetchAll();
?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($siteTitle) ?></title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<?php if ($hasService): ?>
<a class="service-fab" href="<?= e($serviceUrl) ?>" target="_blank" rel="noopener noreferrer" aria-label="在线客服">
  <span>客服</span>
</a>
<?php endif; ?>

<main class="page">
  <div class="logo-wrap">
    <?php if ($hasLogo): ?>
      <img class="logo" src="<?= e('/uploads/' . ltrim($logoPath, '/')) ?>" alt="<?= e($siteTitle) ?>">
    <?php else: ?>
      <div class="logo logo-placeholder"><?= e(mb_substr($siteTitle, 0, 2)) ?></div>
    <?php endif; ?>
  </div>

  <h1 class="site-title"><?= e($siteTitle) ?></h1>

  <?php if ($hasAddress): ?>
  <section class="address-card">
    <div class="address-box">
      <div class="address-text" id="address-text"><?= e($address) ?></div>
    </div>
    <button type="button" class="copy-btn" id="copy-btn" data-address="<?= e($address) ?>">
      <span class="copy-btn-label">复制地址</span>
    </button>
  </section>
  <?php else: ?>
  <section class="address-card address-card-empty">
    <p>站点尚未配置收款地址，请联系管理员完成配置。</p>
  </section>
  <?php endif; ?>

  <?php if ($serviceHtml !== ''): ?>
  <section class="content-section">
    <h2 class="section-title">服务说明</h2>
    <div class="section-body"><?= $serviceHtml ?></div>
  </section>
  <?php endif; ?>

  <?php if ($stepsHtml !== ''): ?>
  <section class="content-section">
    <h2 class="section-title">使用步骤</h2>
    <div class="section-body"><?= $stepsHtml ?></div>
  </section>
  <?php endif; ?>

  <?php if ($noticeHtml !== ''): ?>
  <section class="content-section section-notice">
    <h2 class="section-title">重要提示</h2>
    <div class="section-body"><?= $noticeHtml ?></div>
  </section>
  <?php endif; ?>

  <?php if (!empty($faqs)): ?>
  <section class="content-section">
    <h2 class="section-title">常见问题</h2>
    <div class="faq-list">
      <?php foreach ($faqs as $faq): ?>
      <details class="faq-item">
        <summary><?= e((string) $faq['question']) ?></summary>
        <div class="faq-answer"><?= nl2br(e((string) $faq['answer'])) ?></div>
      </details>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($disclaimerHtml !== ''): ?>
  <section class="content-section section-disclaimer">
    <h2 class="section-title">免责声明</h2>
    <div class="section-body"><?= $disclaimerHtml ?></div>
  </section>
  <?php endif; ?>
</main>

<div class="toast" id="toast" role="status" aria-live="polite"></div>

<script>window.__COPY_TIP__ = <?= json_encode($copyTip, JSON_UNESCAPED_UNICODE) ?>;</script>
<script src="assets/js/main.js"></script>
</body>
</html>

