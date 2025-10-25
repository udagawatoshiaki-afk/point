<?php
declare(strict_types=1);
require_once __DIR__ . '/../session.php';
require_once __DIR__ . '/../db.php';
start_secure_session();

if (empty($_SESSION['uid'])) {
  json_out(['ok'=>false, 'error'=>'unauthorized'], 401);
}

$pdo = pdo();
$st = $pdo->prepare('SELECT id, email, nickname FROM users WHERE id=? LIMIT 1');
$st->execute([(int)$_SESSION['uid']]);
if (!$row = $st->fetch()) {
  json_out(['ok'=>false, 'error'=>'user not found'], 404);
}

$member_no = str_pad((string)$row['id'], 4, '0', STR_PAD_LEFT);
json_out([
  'ok'        => true,
  'id'        => (int)$row['id'],
  'email'     => $row['email'],
  'nickname'  => $row['nickname'] ?: 'あなた',
  'member_no' => $member_no
]);
