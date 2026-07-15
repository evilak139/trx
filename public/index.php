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

/** 内联线性图标，跟随文字颜色（currentColor），不依赖任何图标字体/外部资源 */
function icon(string $name): string
{
    $icons = [
        'wallet' => '<path d="M3 7.5A2.5 2.5 0 0 1 5.5 5h11A2.5 2.5 0 0 1 19 7.5V8H5.5A2.5 2.5 0 0 1 3 5.5v2z"/><path d="M3 8h15a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8z"/><circle cx="16.5" cy="13.5" r="1.2" fill="currentColor" stroke="none"/>',
        'info' => '<circle cx="12" cy="12" r="9"/><line x1="12" y1="11" x2="12" y2="16"/><circle cx="12" cy="7.5" r="0.9" fill="currentColor" stroke="none"/>',
        'steps' => '<path d="M9.5 6h10"/><path d="M9.5 12h10"/><path d="M9.5 18h10"/><path d="M4 6l1.3 1.3L7.5 5"/><path d="M4 12l1.3 1.3L7.5 11"/><path d="M4 18l1.3 1.3L7.5 16"/>',
        'warning' => '<path d="M12 3.5l9.3 16H2.7L12 3.5z"/><line x1="12" y1="9.5" x2="12" y2="14"/><circle cx="12" cy="17" r="0.9" fill="currentColor" stroke="none"/>',
        'help' => '<circle cx="12" cy="12" r="9"/><path d="M9.3 9.2a2.7 2.7 0 1 1 3.7 2.5c-.8.35-1.5 1-1.5 2.1"/><circle cx="12" cy="16.7" r="0.9" fill="currentColor" stroke="none"/>',
        'shield' => '<path d="M12 3.2l7.2 2.9v5.6c0 4.6-3 7.9-7.2 9.6-4.2-1.7-7.2-5-7.2-9.6V6.1L12 3.2z"/>',
        'copy' => '<rect x="8.5" y="8.5" width="11" height="11" rx="2"/><path d="M5.5 15.5h-1a2 2 0 0 1-2-2v-9a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',
    ];
    $paths = $icons[$name] ?? '';
    return '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" '
        . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths . '</svg>';
}
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
  <section class="address-card fade-in">
    <div class="address-label"><?= icon('wallet') ?><span>收款地址</span></div>
    <div class="address-box">
      <div class="address-text" id="address-text"><?= e($address) ?></div>
    </div>
    <button type="button" class="copy-btn" id="copy-btn" data-address="<?= e($address) ?>">
      <?= icon('copy') ?>
      <span class="copy-btn-label">复制地址</span>
    </button>
  </section>
  <?php else: ?>
  <section class="address-card address-card-empty fade-in">
    <p>站点尚未配置收款地址，请联系管理员完成配置。</p>
  </section>
  <?php endif; ?>

  <?php if ($serviceHtml !== ''): ?>
  <section class="content-section fade-in">
    <h2 class="section-title"><?= icon('info') ?><span>服务说明</span></h2>
    <div class="section-body"><?= $serviceHtml ?></div>
  </section>
  <?php endif; ?>

  <?php if ($stepsHtml !== ''): ?>
  <section class="content-section fade-in">
    <h2 class="section-title"><?= icon('steps') ?><span>使用步骤</span></h2>
    <div class="section-body section-steps"><?= $stepsHtml ?></div>
  </section>
  <?php endif; ?>

  <?php if ($noticeHtml !== ''): ?>
  <section class="content-section section-notice fade-in">
    <h2 class="section-title"><?= icon('warning') ?><span>重要提示</span></h2>
    <div class="section-body"><?= $noticeHtml ?></div>
  </section>
  <?php endif; ?>

  <?php if (!empty($faqs)): ?>
  <section class="content-section fade-in">
    <h2 class="section-title"><?= icon('help') ?><span>常见问题</span></h2>
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
  <section class="content-section section-disclaimer fade-in">
    <h2 class="section-title"><?= icon('shield') ?><span>免责声明</span></h2>
    <div class="section-body"><?= $disclaimerHtml ?></div>
  </section>
  <?php endif; ?>
</main>

<div class="toast" id="toast" role="status" aria-live="polite"></div>

<script>window.__COPY_TIP__ = <?= json_encode($copyTip, JSON_UNESCAPED_UNICODE) ?>;</script>
<script src="assets/js/main.js"></script>
</body>
</html>
