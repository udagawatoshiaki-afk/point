<?php
declare(strict_types=1);

$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_name('PCSESSID');
  session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/pointcard',
    'secure'   => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
  ]);
  session_start();
}

// 既にログイン済みなら next へ
$next = $_GET['next'] ?? '/pointcard/';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // ★ここをあなたの認証ロジックに置き換え（例: DBでユーザー確認）
  $nick = trim($_POST['nickname'] ?? '');
  if ($nick === '') $nick = 'ゲスト';

  // 任意のユーザーIDを発行（本番はDBのIDなど）
  $_SESSION['user_id']  = $_SESSION['user_id'] ?? random_int(1000, 999999);
  $_SESSION['nickname'] = $nick;

  header('Location: ' . $next, true, 302);
  exit;
}
?>
<!DOCTYPE html><meta charset="UTF-8">
<title>ログイン</title>
<body style="font-family:sans-serif;max-width:480px;margin:40px auto">
  <h1>ログイン</h1>
  <form method="post" action="">
    <label>ニックネーム（任意）<br>
      <input name="nickname" maxlength="24" placeholder="あなた">
    </label>
    <div style="margin-top:12px">
      <button type="submit">入場する</button>
    </div>
  </form>
  <p style="margin-top:20px"><a href="<?php echo htmlspecialchars($next, ENT_QUOTES); ?>">戻る</a></p>
</body>
