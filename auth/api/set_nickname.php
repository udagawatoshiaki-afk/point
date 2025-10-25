<?php
declare(strict_types=1);
require_once __DIR__ . '/../session.php';
require_once __DIR__ . '/../db.php';
start_secure_session();

if (empty($_SESSION['uid'])) {
  json_out(['ok'=>false,'error'=>'unauthorized'], 401);
}
$data = require_json_post();
$nickname = trim(strval($data['nickname'] ?? ''));
if ($nickname === '') $nickname = 'あなた';
if (mb_strlen($nickname) > 64) {
  json_out(['ok'=>false,'error'=>'ニックネームは64文字以内にしてください','errors'=>['nicknameInput'=>'64文字以内にしてください']], 400);
}

$pdo = pdo();
$st = $pdo->prepare('UPDATE users SET nickname=? WHERE id=?');
$st->execute([$nickname, (int)$_SESSION['uid']]);
$_SESSION['nickname'] = $nickname;

json_out(['ok'=>true, 'nickname'=>$nickname]);
