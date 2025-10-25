<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/session.php';
start_secure_session();

header('Content-Type: text/plain; charset=UTF-8');

try {
  $pdo = pdo();
  $ver = $pdo->query('SELECT VERSION()')->fetchColumn();
  echo "[OK] DB VERSION: {$ver}\n";

  // users件数
  $cnt = $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
  echo "[OK] users COUNT : {$cnt}\n";

  // 最近の1件
  $row = $pdo->query('SELECT id, email, created_at, updated_at FROM users ORDER BY id DESC LIMIT 1')->fetch();
  if ($row) {
    echo "[OK] users LAST : id={$row['id']}, email={$row['email']}, created_at={$row['created_at']}\n";
  } else {
    echo "[INFO] users is empty\n";
  }

  // セッション確認
  $uid = $_SESSION['uid'] ?? null;
  echo "[SESSION] uid=" . var_export($uid, true) . "\n";

} catch (Throwable $e) {
  http_response_code(500);
  echo "[ERR] " . $e->getMessage() . "\n";
}
