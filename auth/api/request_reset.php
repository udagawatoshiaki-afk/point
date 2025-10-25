<?php
declare(strict_types=1);

/* ==== API: /pointcard/auth/api/request_reset.php ==== */
$RID  = bin2hex(random_bytes(4));
$ROOT = dirname(__DIR__, 2);   // /pointcard
$APP  = dirname(__DIR__, 1);   // /pointcard/auth
$LOG  = $ROOT . '/error.log';

@header('Content-Type: application/json; charset=utf-8');
@header('X-Pointcard-Debug: ' . $RID);
if (function_exists('header_remove')) { @header_remove('Link'); }

function pc_log($msg, $logfile) { @error_log($msg . "\n", 3, $logfile); }
pc_log("[api.req_reset][$RID] BOOT", $LOG);

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
  @include_once $APP . '/session.php';
  if (function_exists('start_secure_session')) { start_secure_session(); }

  @include_once $APP . '/db.php';      // pdo()
  @include_once $APP . '/mailer.php';  // send_mail($to,$subj,$body)
  if (!function_exists('pdo')) {
    pc_log("[api.req_reset][$RID] FATAL: pdo() not found", $LOG);
    out(['ok'=>true], 200); // 情報漏えい防止のため常にOK
  }
  if (!defined('OTP_TTL_MIN')) { define('OTP_TTL_MIN', 15); }

  $j = json_in();
  $email = strtolower(trim(strval($j['email'] ?? '')));
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    pc_log("[api.req_reset][$RID] invalid email format", $LOG);
    out(['ok'=>true], 200);
  }

  $pdo = pdo();
  $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
  $stmt->execute([$email]);
  $user = $stmt->fetch(\PDO::FETCH_ASSOC);
  if (!$user) {
    pc_log("[api.req_reset][$RID] user not found (masked ok)", $LOG);
    out(['ok'=>true], 200);
  }

  $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
  $hash = password_hash($code, PASSWORD_DEFAULT);
  $ttl = (int)OTP_TTL_MIN;

  $ins = $pdo->prepare('INSERT INTO password_resets (user_id, kind, otp_hash, otp_expires_at) VALUES (?,?,?,DATE_ADD(NOW(), INTERVAL ? MINUTE))');
  $ins->execute([(int)$user['id'], 'reset', $hash, $ttl]);
  pc_log("[api.req_reset][$RID] OTP stored for uid=".(int)$user['id'], $LOG);

  $subject = '【ポイントカード】パスワード再設定用 6桁コード';
  $body = "パスワード再設定の確認コードは次の通りです。\n\n【{$code}】\n\n有効期限：{$ttl}分\nこのコードを画面に入力してください。";
  if (function_exists('send_mail')) {
    $ok = @send_mail($email, $subject, $body);
    pc_log("[api.req_reset][$RID] send_mail=" . (is_bool($ok) ? ($ok ? 'OK' : 'NG') : 'CALLED'), $LOG);
  } else {
    pc_log("[api.req_reset][$RID] send_mail() not found", $LOG);
  }

  out(['ok'=>true], 200); // 常にOK

} catch (Throwable $e) {
  pc_log("[api.req_reset][$RID] EXC " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine(), $LOG);
  out(['ok'=>true], 200); // 例外時も列挙対策のためOKで返す
}
