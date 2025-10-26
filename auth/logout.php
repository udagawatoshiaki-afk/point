<?php
declare(strict_types=1);
require_once __DIR__ . '/session.php';
start_secure_session();

$_SESSION = [];
if (ini_get('session.use_cookies')) {
  $p = session_get_cookie_params();
  setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
}
session_destroy();

header('Location: /pointcard/login.php');
exit;
