<?php
// login.php - 管理ログインフォーム（ログイン成功で index.php に遷移）

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/auth.php';

// （必要なら一時的にデバッグ表示をON）
// ini_set('display_errors', '1');
// error_reporting(E_ALL);

$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

    // 認証
    $ok = login($username, $password, $ip, $ua);

    if ($ok) {
        // ← 成功したら同ディレクトリのダッシュボードへ（配置に依存しない相対遷移）
        header('Location: index.php');
        exit;
    } else {
        $err = 'ログインに失敗しました';
    }
}
?>
<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<title>管理ログイン</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,"Apple Color Emoji","Segoe UI Emoji";background:#f7f7fb;margin:0}
  .card{max-width:380px;margin:6rem auto;background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:24px;box-shadow:0 4px 20px rgba(0,0,0,.05)}
  h1{font-size:1.25rem;margin:0 0 1rem}
  label{display:block;margin:.6rem 0 .2rem}
  input{width:100%;padding:.6rem;border:1px solid #d1d5db;border-radius:8px;font-size:1rem}
  button{margin-top:1rem;width:100%;padding:.7rem;border:0;border-radius:8px;background:#2563eb;color:#fff;font-weight:600;cursor:pointer}
  .err{color:#b91c1c;background:#fee2e2;border:1px solid #fecaca;padding:.5rem;border-radius:8px;margin:.5rem 0}
</style>
</head>
<body>
  <div class="card">
    <h1>管理ログイン</h1>
    <?php if ($err !== ''): ?>
      <div class="err"><?= htmlspecialchars($err, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
    <?php endif; ?>
    <form method="post" autocomplete="username">
      <label>ユーザー名</label>
      <input name="username" required autofocus>

      <label>パスワード</label>
      <input name="password" type="password" required autocomplete="current-password">

      <button type="submit">ログイン</button>
    </form>
  </div>
</body>
</html>
