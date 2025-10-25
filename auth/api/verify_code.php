<?php
declare(strict_types=1);
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../session.php';
start_secure_session();

$data = require_json_post();
$email = strtolower(trim(strval($data['email'] ?? '')));
$code  = trim(strval($data['code'] ?? ''));

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^\d{6}$/', $code)) {
  $e=[];
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $e['resetEmail']='メールアドレスが不正です';
  if (!preg_match('/^\d{6}$/', $code)) $e['resetCode']='6桁の数字を入力してください';
  json_out(['ok'=>false,'error'=>'入力が不正です','errors'=>$e], 400);
}
// 期限切れ・不一致なども errors を付けると親切
// 例）json_out(['ok'=>false,'error'=>'コードの有効期限切れ、または無効です','errors'=>['resetCode'=>'期限切れか不一致です']], 400);


$pdo = pdo();
$stmt = $pdo->prepare('SELECT id FROM users WHERE email=? LIMIT 1');
$stmt->execute([$email]);
$user = $stmt->fetch();
if (!$user) json_out(['ok'=>false,'error'=>'コードが無効です'], 400);

$stmt = $pdo->prepare('SELECT id, otp_hash, otp_expires_at, used_at FROM password_resets WHERE user_id=? AND kind="reset" ORDER BY id DESC LIMIT 1');
$stmt->execute([(int)$user['id']]);
$pr = $stmt->fetch();
if (!$pr || $pr['used_at'] !== null || strtotime($pr['otp_expires_at']) < time()) {
  json_out(['ok'=>false,'error'=>'コードの有効期限切れ、または無効です'], 400);
}
if (!password_verify($code, $pr['otp_hash'])) {
  json_out(['ok'=>false,'error'=>'コードが一致しません'], 400);
}

$token = bin2hex(random_bytes(32));
$ttl = RT_TTL_MIN;

$pdo->prepare('UPDATE password_resets SET reset_token=?, reset_expires_at=DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE id=?')
    ->execute([$token, $ttl, (int)$pr['id']]);

json_out(['ok'=>true,'reset_token'=>$token,'ttl_min'=>$ttl]);
