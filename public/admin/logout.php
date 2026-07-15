<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && Auth::verifyCsrf($_POST['csrf'] ?? null)) {
    Auth::logout();
}

header('Location: /admin/login.php');
exit;
