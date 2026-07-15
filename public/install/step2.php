<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
install_guard();

function install_try_connect(array $db): array
{
    try {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], (int) $db['port'], $db['dbname']);
        new PDO($dsn, $db['user'], $db['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ]);
        return ['ok' => true];
    } catch (\PDOException $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

$errors = [];
$default = install_get('db', [
    'host' => 'localhost',
    'port' => '3306',
    'dbname' => '',
    'user' => '',
    'password' => '',
    'prefix' => '',
]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isTest = ($_POST['action'] ?? '') === 'test';

    if (!install_verify_csrf($_POST['csrf'] ?? null)) {
        if ($isTest) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'CSRF 校验失败，请刷新页面重试']);
            exit;
        }
        $errors[] = 'CSRF 校验失败，请刷新页面重试';
    } else {
        $db = [
            'host' => trim((string) ($_POST['host'] ?? '')),
            'port' => trim((string) ($_POST['port'] ?? '3306')),
            'dbname' => trim((string) ($_POST['dbname'] ?? '')),
            'user' => trim((string) ($_POST['user'] ?? '')),
            'password' => (string) ($_POST['password'] ?? ''),
            'prefix' => trim((string) ($_POST['prefix'] ?? '')),
        ];

        if ($db['host'] === '' || $db['dbname'] === '' || $db['user'] === '') {
            $errors[] = '请完整填写数据库主机、库名、用户名';
        }
        if (!ctype_digit($db['port'])) {
            $errors[] = '端口必须为数字';
        }
        if ($db['prefix'] !== '' && !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $db['prefix'])) {
            $errors[] = '表前缀只能包含字母、数字、下划线，且不能以数字开头';
        }

        if ($isTest) {
            header('Content-Type: application/json; charset=utf-8');
            if ($errors) {
                echo json_encode(['ok' => false, 'error' => implode('；', $errors)]);
                exit;
            }
            echo json_encode(install_try_connect($db));
            exit;
        }

        if (empty($errors)) {
            $result = install_try_connect($db);
            if (!$result['ok']) {
                $errors[] = '数据库连接失败：' . $result['error'];
            } else {
                install_set('db', $db);
                install_set('db_tested', true);
                header('Location: step3.php');
                exit;
            }
        }
        $default = $db;
    }
}

require __DIR__ . '/includes/layout.php';
install_head('数据库配置', 2);
$csrf = install_csrf_token();
?>
<h2>数据库配置</h2>

<?php foreach ($errors as $err): ?>
<div class="alert alert-error"><?= e($err) ?></div>
<?php endforeach; ?>

<div id="test-result"></div>

<form method="post" id="db-form">
  <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
  <div class="form-group">
    <label>数据库主机</label>
    <input type="text" name="host" value="<?= e($default['host']) ?>" required>
  </div>
  <div class="form-group">
    <label>端口</label>
    <input type="number" name="port" value="<?= e((string) $default['port']) ?>" required>
  </div>
  <div class="form-group">
    <label>数据库名</label>
    <input type="text" name="dbname" value="<?= e($default['dbname']) ?>" required>
  </div>
  <div class="form-group">
    <label>用户名</label>
    <input type="text" name="user" value="<?= e($default['user']) ?>" required>
  </div>
  <div class="form-group">
    <label>密码</label>
    <input type="password" name="password" value="<?= e($default['password']) ?>">
  </div>
  <div class="form-group">
    <label>表前缀（可选）</label>
    <input type="text" name="prefix" value="<?= e($default['prefix']) ?>" placeholder="例如 trx_">
  </div>
  <div class="actions">
    <button type="button" class="btn secondary" id="test-btn">测试连接</button>
    <button type="submit" class="btn">下一步：创建数据表</button>
  </div>
</form>

<script>
document.getElementById('test-btn').addEventListener('click', async function () {
  const form = document.getElementById('db-form');
  const data = new FormData(form);
  data.set('action', 'test');
  const resultBox = document.getElementById('test-result');
  resultBox.innerHTML = '<div class="alert alert-warn">正在测试连接…</div>';
  try {
    const resp = await fetch(location.href, { method: 'POST', body: data });
    const json = await resp.json();
    resultBox.innerHTML = json.ok
      ? '<div class="alert alert-success">连接成功</div>'
      : '<div class="alert alert-error">连接失败：' + (json.error || '未知错误') + '</div>';
  } catch (e) {
    resultBox.innerHTML = '<div class="alert alert-error">请求失败，请重试</div>';
  }
});
</script>
<?php
install_foot();
