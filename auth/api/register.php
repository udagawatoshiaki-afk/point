<?php
declare(strict_types=1);
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../session.php';
start_secure_session();

$data = require_json_post();
$email = strtolower(trim(strval($data['email'] ?? '')));
$pass  = strval($data['password'] ?? '');
$nick  = trim(strval($data['nickname'] ?? 'あなた'));

$errors = [];
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  $errors['signEmail'] = '正しいメールアドレス形式ではありません';
}
if (strlen($pass) < 8) {
  $errors['signPass'] = 'パスワードは8文字以上にしてください';
}
if (mb_strlen($nick) > 64) {
  $errors['signNick'] = 'ニックネームは64文字以内にしてください';
}
if ($errors) {
  json_out(['ok'=>false,'error'=>'入力内容に不備があります','errors'=>$errors], 400);
}

$pdo = pdo();
try {
  $stmt = $pdo->prepare('INSERT INTO users (email, pass_hash, nickname) VALUES (?, ?, ?)');
  $stmt->execute([$email, password_hash($pass, PASSWORD_DEFAULT), $nick]);
  json_out(['ok'=>true]);
} catch (PDOException $e) {
  // 1062: duplicate
  if (($e->errorInfo[1] ?? null) === 1062) {
    json_out(['ok'=>false,'error'=>'このメールアドレスは既に登録されています','errors'=>['signEmail'=>'このメールは既に使われています']], 409);
  }
  // それ以外はログへ（権限エラー/テーブル存在しない/別DBなど）
  error_log('[REGISTER ERR] '.$e->getMessage());
  json_out(['ok'=>false,'error'=>'登録に失敗しました（サーバーエラー）'], 500);
}
