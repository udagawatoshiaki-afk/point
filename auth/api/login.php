<?php
declare(strict_types=1);

/* ==== API: /pointcard/auth/api/login.php ==== */
$RID  = bin2hex(random_bytes(4));
$ROOT = dirname(__DIR__, 2);   // /pointcard
$APP  = dirname(__DIR__, 1);   // /pointcard/auth
$LOG  = $ROOT . '/error.log';

@header('Content-Type: application/json; charset=utf-8');
@header('X-Pointcard-Debug: ' . $RID);
if (function_exists('header_remove')) { @header_remove('Link'); }

function pc_log($msg, $logfile) { @error_log($msg . "\n", 3, $logfile); }
pc_log("[api.login][$RID] BOOT", $LOG);

/* helpers */
function json_in(): array {
  $raw = file_get_contents('php://input');
  $j = json_decode($raw ?: '[]', true);
  return is_array($j) ? $j : [];
}
function out($arr, int $code = 200): void {
  http_response_code($code);
  echo json_encode($arr, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  exit;
}

try {
  /* session */
  @include_once $APP . '/session.php';
  if (function_exists('start_secure_session')) {
    start_secure_session();
    pc_log("[api.login][$RID] start_secure_session OK", $LOG);
  } else {
    if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
    pc_log("[api.login][$RID] session_start fallback OK", $LOG);
  }

  /* parse */
  $j = json_in();
  $email = strtolower(trim(strval($j['email'] ?? '')));
  $password = strval($j['password'] ?? '');
  if ($email === '' || $password === '') {
    pc_log("[api.login][$RID] 400 invalid params", $LOG);
    out(['ok'=>false, 'error'=>'メールアドレスまたはパスワードが違います'], 200);
  }

  /* db */
  @include_once $APP . '/db.php'; // expects pdo()
  if (!function_exists('pdo')) {
    pc_log("[api.login][$RID] FATAL: pdo() not found", $LOG);
    out(['ok'=>false, 'error'=>'内部エラー'], 500);
  }
  $pdo = pdo();
  $stmt = $pdo->prepare('SELECT id, pass_hash, nickname FROM users WHERE email = ? LIMIT 1');
  $stmt->execute([$email]);
  $u = $stmt->fetch(\PDO::FETCH_ASSOC);
  if (!$u || empty($u['pass_hash']) || !password_verify($password, $u['pass_hash'])) {
    pc_log("[api.login][$RID] AUTH NG for " . $email, $LOG);
    out(['ok'=>false, 'error'=>'メールアドレスまたはパスワードが違います'], 200);
  }

  /* success -> session / whoami */
  @session_regenerate_id(true);
  $_SESSION['uid'] = (int)$u['id'];
  if (!empty($u['nickname'])) { $_SESSION['nickname'] = $u['nickname']; }
  pc_log("[api.login][$RID] AUTH OK uid=" . $_SESSION['uid'], $LOG);
  out(['ok'=>true]);

} catch (Throwable $e) {
  pc_log("[api.login][$RID] EXC " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine(), $LOG);
  out(['ok'=>false, 'error'=>'内部エラー'], 500);
}
