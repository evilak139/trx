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
$table = $db->table('admins');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf'] ?? null)) {
        flash_set('error', 'CSRF 校验失败，请重试');
        header('Location: accounts.php');
        exit;
    }

    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm'] ?? '');
    $errors = [];

    if (!preg_match('/^[A-Za-z0-9_]{3,50}$/', $username)) {
        $errors[] = '用户名需为 3-50 位字母、数字或下划线';
    }
    if (strlen($password) < 8) {
        $errors[] = '密码长度至少 8 位';
    }
    if ($password !== $confirm) {
        $errors[] = '两次输入的密码不一致';
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO `{$table}` (username, password_hash, created_at) VALUES (?, ?, NOW())");
            $stmt->execute([$username, password_hash($password, PASSWORD_BCRYPT)]);
            flash_set('success', '管理员账号已创建');
        } catch (\PDOException $e) {
            $errors[] = str_contains($e->getMessage(), 'uniq_username') ? '该用户名已存在' : ('创建失败：' . $e->getMessage());
        }
    }

    if (!empty($errors)) {
        flash_set('error', implode('；', $errors));
    }
    header('Location: accounts.php');
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
        $newPassword = (string) ($data['new_password'] ?? '');
        if (strlen($newPassword) < 8) {
            echo json_encode(['success' => false, 'message' => '新密码长度至少 8 位']);
            exit;
        }

        // 修改自己的密码需要校验当前密码；重置他人密码由已登录管理员直接操作
        if ($id === Auth::id()) {
            $currentPassword = (string) ($data['current_password'] ?? '');
            if (!Auth::verifyPassword($currentPassword)) {
                echo json_encode(['success' => false, 'message' => '当前密码不正确']);
                exit;
            }
        }

        $stmt = $pdo->prepare("UPDATE `{$table}` SET password_hash = ? WHERE id = ?");
        $stmt->execute([password_hash($newPassword, PASSWORD_BCRYPT), $id]);
        echo json_encode(['success' => true, 'message' => '密码已更新']);
        exit;
    }

    // DELETE
    $countStmt = $pdo->query("SELECT COUNT(*) FROM `{$table}`");
    $total = (int) $countStmt->fetchColumn();
    if ($total <= 1) {
        echo json_encode(['success' => false, 'message' => '至少需要保留一个管理员账号，无法删除']);
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM `{$table}` WHERE id = ?");
    $stmt->execute([$id]);

    if ($id === Auth::id()) {
        Auth::logout();
        echo json_encode(['success' => true, 'message' => '账号已删除，即将退出登录', 'redirect' => '/admin/login.php']);
        exit;
    }

    echo json_encode(['success' => true, 'message' => '账号已删除']);
    exit;
}

$admins = $pdo->query("SELECT id, username, created_at, last_login_at FROM `{$table}` ORDER BY id ASC")->fetchAll();
$flash = flash_get();

require __DIR__ . '/includes/layout.php';
admin_head('账号管理', 'accounts');
$csrf = Auth::csrfToken();
$myId = Auth::id();
?>

<?php if ($flash): ?>
<div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
<?php endif; ?>

<div class="card">
  <table class="data-table">
    <thead>
      <tr><th>用户名</th><th>创建时间</th><th>最后登录时间</th><th>操作</th></tr>
    </thead>
    <tbody>
      <?php foreach ($admins as $admin): ?>
      <tr>
        <td><?= e($admin['username']) ?><?= (int) $admin['id'] === $myId ? '（当前登录）' : '' ?></td>
        <td><?= e((string) $admin['created_at']) ?></td>
        <td><?= e((string) ($admin['last_login_at'] ?? '从未登录')) ?></td>
        <td>
          <button type="button" class="btn secondary btn-sm" onclick="changePassword(<?= (int) $admin['id'] ?>, <?= (int) $admin['id'] === $myId ? 'true' : 'false' ?>)">改密码</button>
          <button type="button" class="btn danger btn-sm" onclick="deleteAccount(<?= (int) $admin['id'] ?>)">删除</button>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="card">
  <h2 style="margin-top:0;font-size:16px;">新增管理员</h2>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <div class="form-group">
      <label>用户名</label>
      <input type="text" name="username" required>
    </div>
    <div class="form-group">
      <label>密码（至少 8 位）</label>
      <input type="password" name="password" required minlength="8">
    </div>
    <div class="form-group">
      <label>确认密码</label>
      <input type="password" name="confirm" required minlength="8">
    </div>
    <button type="submit" class="btn">创建账号</button>
  </form>
</div>

<script>
function apiCall(method, body) {
  return fetch('accounts.php', {
    method: method,
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  }).then(function (r) { return r.json(); });
}

function changePassword(id, isSelf) {
  var newPassword = prompt('请输入新密码（至少 8 位）：');
  if (!newPassword) return;
  var payload = { csrf: adminCsrfToken(), id: id, new_password: newPassword };
  if (isSelf) {
    payload.current_password = prompt('请输入当前密码以确认：') || '';
  }
  apiCall('PUT', payload).then(function (res) {
    alert(res.message);
    if (res.success) location.reload();
  });
}

function deleteAccount(id) {
  if (!confirm('确定要删除该管理员账号吗？')) return;
  apiCall('DELETE', { csrf: adminCsrfToken(), id: id }).then(function (res) {
    alert(res.message);
    if (res.success) {
      location.href = res.redirect || 'accounts.php';
    }
  });
}
</script>

<?php
admin_foot();
