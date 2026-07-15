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
$configTable = $db->table('config');
$faqsTable = $db->table('faqs');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST') {
    $formAction = $_POST['form_action'] ?? '';

    if (!Auth::verifyCsrf($_POST['csrf'] ?? null)) {
        flash_set('error', 'CSRF 校验失败，请重试');
        header('Location: index.php');
        exit;
    }

    if ($formAction === 'save_content') {
        $serviceHtml = sanitize_html((string) ($_POST['service_html'] ?? ''));
        $stepsHtml = sanitize_html((string) ($_POST['steps_html'] ?? ''));
        $noticeHtml = sanitize_html((string) ($_POST['notice_html'] ?? ''));
        $disclaimerHtml = sanitize_html((string) ($_POST['disclaimer_html'] ?? ''));

        $update = $pdo->prepare(
            "UPDATE `{$configTable}` SET service_html = ?, steps_html = ?, notice_html = ?, disclaimer_html = ? WHERE id = 1"
        );
        $update->execute([$serviceHtml, $stepsHtml, $noticeHtml, $disclaimerHtml]);

        flash_set('success', '文案已保存');
        header('Location: index.php');
        exit;
    }

    if ($formAction === 'add_faq') {
        $question = trim((string) ($_POST['question'] ?? ''));
        $answer = trim((string) ($_POST['answer'] ?? ''));
        $sortOrder = (int) ($_POST['sort_order'] ?? 0);
        $errors = [];

        if ($question === '' || mb_strlen($question) > 255) {
            $errors[] = '问题标题不能为空，且不超过 255 字';
        }
        if ($answer === '') {
            $errors[] = '答案不能为空';
        }

        if (empty($errors)) {
            $insert = $pdo->prepare(
                "INSERT INTO `{$faqsTable}` (question, answer, sort_order) VALUES (?, ?, ?)"
            );
            $insert->execute([$question, $answer, $sortOrder]);
            flash_set('success', '常见问题已新增');
        } else {
            flash_set('error', implode('；', $errors));
        }
        header('Location: index.php');
        exit;
    }

    header('Location: index.php');
    exit;
}

if (in_array($method, ['PUT', 'DELETE'], true)) {
    header('Content-Type: application/json; charset=utf-8');
    $data = json_decode((string) file_get_contents('php://input'), true) ?: [];

    if (!Auth::verifyCsrf($data['csrf'] ?? null)) {
        echo json_encode(['success' => false, 'message' => 'CSRF 校验失败，请刷新页面重试']);
        exit;
    }

    $id = (int) ($data['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => '参数错误']);
        exit;
    }

    if ($method === 'PUT') {
        $question = trim((string) ($data['question'] ?? ''));
        $answer = trim((string) ($data['answer'] ?? ''));
        $sortOrder = (int) ($data['sort_order'] ?? 0);

        if ($question === '' || mb_strlen($question) > 255) {
            echo json_encode(['success' => false, 'message' => '问题标题不能为空，且不超过 255 字']);
            exit;
        }
        if ($answer === '') {
            echo json_encode(['success' => false, 'message' => '答案不能为空']);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE `{$faqsTable}` SET question = ?, answer = ?, sort_order = ? WHERE id = ?");
        $stmt->execute([$question, $answer, $sortOrder, $id]);
        echo json_encode(['success' => true, 'message' => '已保存']);
        exit;
    }

    // DELETE
    $stmt = $pdo->prepare("DELETE FROM `{$faqsTable}` WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['success' => true, 'message' => '已删除']);
    exit;
}

$stmt = $pdo->query("SELECT * FROM `{$configTable}` WHERE id = 1 LIMIT 1");
$config = $stmt->fetch() ?: [];
$faqs = $pdo->query("SELECT * FROM `{$faqsTable}` ORDER BY sort_order ASC, id ASC")->fetchAll();
$flash = flash_get();

require __DIR__ . '/../includes/layout.php';
admin_head('文案配置', 'content');
$csrf = Auth::csrfToken();
?>
<link rel="stylesheet" href="/admin/assets/vendor/quill/quill.snow.css">

<?php if ($flash): ?>
<div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
<?php endif; ?>

<form method="post" id="content-form">
  <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
  <input type="hidden" name="form_action" value="save_content">

  <div class="card">
    <h2 style="margin-top:0;font-size:16px;">服务说明</h2>
    <div class="quill-editor" id="editor-service"><?= $config['service_html'] ?? '' ?></div>
    <textarea name="service_html" id="input-service" style="display:none;"></textarea>
  </div>

  <div class="card">
    <h2 style="margin-top:0;font-size:16px;">使用步骤</h2>
    <div class="quill-editor" id="editor-steps"><?= $config['steps_html'] ?? '' ?></div>
    <textarea name="steps_html" id="input-steps" style="display:none;"></textarea>
  </div>

  <div class="card">
    <h2 style="margin-top:0;font-size:16px;">重要提示</h2>
    <div class="quill-editor" id="editor-notice"><?= $config['notice_html'] ?? '' ?></div>
    <textarea name="notice_html" id="input-notice" style="display:none;"></textarea>
  </div>

  <div class="card">
    <h2 style="margin-top:0;font-size:16px;">免责声明</h2>
    <div class="quill-editor" id="editor-disclaimer"><?= $config['disclaimer_html'] ?? '' ?></div>
    <textarea name="disclaimer_html" id="input-disclaimer" style="display:none;"></textarea>
  </div>

  <button type="submit" class="btn">保存文案</button>
</form>

<div class="card">
  <h2 style="margin-top:0;font-size:16px;">常见问题</h2>
  <div class="hint" style="margin-bottom:14px;">前台默认只显示问题标题，点击后展开显示答案；排序号越小越靠前</div>

  <?php foreach ($faqs as $faq): ?>
  <div class="faq-edit-card" data-id="<?= (int) $faq['id'] ?>">
    <div class="form-group">
      <label>问题标题</label>
      <input type="text" class="faq-question" value="<?= e((string) $faq['question']) ?>" maxlength="255">
    </div>
    <div class="form-group">
      <label>答案</label>
      <textarea class="faq-answer" rows="3"><?= e((string) $faq['answer']) ?></textarea>
    </div>
    <div class="form-group">
      <label>排序号</label>
      <input type="number" class="faq-sort" value="<?= (int) $faq['sort_order'] ?>" style="max-width:120px;">
    </div>
    <button type="button" class="btn secondary btn-sm" onclick="saveFaq(this)">保存</button>
    <button type="button" class="btn danger btn-sm" onclick="deleteFaq(this)">删除</button>
  </div>
  <?php endforeach; ?>

  <div class="faq-edit-card">
    <form method="post">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
      <input type="hidden" name="form_action" value="add_faq">
      <div class="form-group">
        <label>新增问题标题</label>
        <input type="text" name="question" maxlength="255" required>
      </div>
      <div class="form-group">
        <label>答案</label>
        <textarea name="answer" rows="3" required></textarea>
      </div>
      <div class="form-group">
        <label>排序号</label>
        <input type="number" name="sort_order" value="0" style="max-width:120px;">
      </div>
      <button type="submit" class="btn">新增问题</button>
    </form>
  </div>
</div>

<script src="/admin/assets/vendor/quill/quill.min.js"></script>
<script>
  function makeEditor(id) {
    return new Quill('#editor-' + id, {
      theme: 'snow',
      modules: {
        toolbar: [
          ['bold', 'italic', 'underline', 'strike'],
          [{ header: [1, 2, 3, false] }],
          [{ list: 'ordered' }, { list: 'bullet' }],
          ['link', 'image'],
          ['clean'],
        ],
      },
    });
  }

  var editors = {
    service: makeEditor('service'),
    steps: makeEditor('steps'),
    notice: makeEditor('notice'),
    disclaimer: makeEditor('disclaimer'),
  };

  document.getElementById('content-form').addEventListener('submit', function () {
    Object.keys(editors).forEach(function (key) {
      document.getElementById('input-' + key).value = editors[key].root.innerHTML;
    });
  });

  function saveFaq(btn) {
    var card = btn.closest('.faq-edit-card');
    var payload = {
      csrf: adminCsrfToken(),
      id: parseInt(card.getAttribute('data-id'), 10),
      question: card.querySelector('.faq-question').value,
      answer: card.querySelector('.faq-answer').value,
      sort_order: parseInt(card.querySelector('.faq-sort').value, 10) || 0,
    };
    fetch('index.php', {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    }).then(function (r) { return r.json(); }).then(function (res) {
      alert(res.message);
      if (res.success) location.reload();
    });
  }

  function deleteFaq(btn) {
    if (!confirm('确定要删除这条常见问题吗？')) return;
    var card = btn.closest('.faq-edit-card');
    fetch('index.php', {
      method: 'DELETE',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ csrf: adminCsrfToken(), id: parseInt(card.getAttribute('data-id'), 10) }),
    }).then(function (r) { return r.json(); }).then(function (res) {
      alert(res.message);
      if (res.success) location.reload();
    });
  }
</script>
<?php
admin_foot();
