<?php
declare(strict_types=1);
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../session.php';
start_secure_session();

$data = require_json_post();
$email = strtolower(trim(strval($data['email'] ?? '')));
$token = trim(strval($data['reset_token'] ?? ''));
$pass  = strval($data['new_password'] ?? '');

$e=[];
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $e['resetEmail']='メールアドレスが不正です';
if (!preg_match('/^[a-f0-9]{64}$/', $token)) $e['resetCode']='確認コードの手順からやり直してください';
if (strlen($pass) < 8) $e['resetNewPass']='8文字以上にしてください';
if ($e){ json_out(['ok'=>false,'error'=>'入力が不正です','errors'=>$e], 400); }


$pdo = pdo();

// ユーザー確認
$stmt = $pdo->prepare('SELECT id FROM users WHERE email=? LIMIT 1');
$stmt->execute([$email]);
$user = $stmt->fetch();
if (!$user) json_out(['ok'=>false,'error'=>'無効なトークン'], 400);

// トークン検証
$stmt = $pdo->prepare('SELECT id, reset_expires_at, used_at FROM password_resets WHERE user_id=? AND reset_token=? AND kind="reset" ORDER BY id DESC LIMIT 1');
$stmt->execute([(int)$user['id'], $token]);
$pr = $stmt->fetch();
if (!$pr || $pr['used_at'] !== null || strtotime($pr['reset_expires_at']) < time()) {
  json_out(['ok'=>false,'error'=>'トークンの有効期限切れ、または無効です'], 400);
}

// パスワード更新
$pdo->prepare('UPDATE users SET pass_hash=?, updated_at=NOW() WHERE id=?')
    ->execute([password_hash($pass, PASSWORD_DEFAULT), (int)$user['id']]);

// トークン使用済みに
$pdo->prepare('UPDATE password_resets SET used_at=NOW() WHERE id=?')->execute([(int)$pr['id']]);

json_out(['ok'=>true]);
