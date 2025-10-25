<?php
declare(strict_types=1);

@ini_set('display_errors','0');
@ini_set('log_errors','1');
@ini_set('error_log', dirname(__DIR__) . '/error.log'); // /pointcard/error.log

$RID = substr(md5(uniqid('', true)), 0, 8);
@header('Content-Type: application/json; charset=utf-8');
@header('X-Pointcard-Debug: whoami-' . $RID);

require_once __DIR__ . '/session.php';
start_secure_session();

$out = ['ok' => true, 'logged_in' => false, 'uid' => 0, 'nickname' => null];

try {
  $uid = isset($_SESSION['uid']) ? (int)$_SESSION['uid'] : 0;
  if ($uid > 0) {
    $out['logged_in'] = true;
    $out['uid'] = $uid;

    // 1) セッションにあれば最優先
    if (!empty($_SESSION['nickname'])) {
      $out['nickname'] = (string)$_SESSION['nickname'];
    } else {
      // 2) DBから取得（なければ null のまま）
      require_once __DIR__ . '/db.php';
      $pdo = pdo();
      $stmt = $pdo->prepare('SELECT nickname FROM users WHERE id = ? LIMIT 1');
      $stmt->execute([$uid]);
      if ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
        if (isset($row['nickname'])) $out['nickname'] = (string)$row['nickname'];
      }
    }
  }

  echo json_encode($out, JSON_UNESCAPED_UNICODE);
  exit;

} catch (\Throwable $e) {
  @error_log("[whoami][$RID] EXC " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
  http_response_code(500);
  echo json_encode(['ok'=>false, 'error'=>'whoami-failed'], JSON_UNESCAPED_UNICODE);
  exit;
}
