<?php
// coupons_issued.php - 発行済みクーポン照会（ダッシュボード統一UI＋戻るボタン）

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/helpers.php';
require_login();

$admin = current_admin();
$pdo   = db();
$BASE  = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';

$user_id = trim($_GET['user_id'] ?? '');
$rows = [];
if ($user_id !== '') {
    $st = $pdo->prepare('SELECT i.issued_id, i.coupon_id, m.name AS coupon_name, i.user_id, i.expiry_text,
                                i.used_flag, i.is_active, i.issued_at, i.used_at
                         FROM coupons_issued i
                         JOIN coupons_master m ON m.coupon_id=i.coupon_id
                         WHERE i.user_id = ?
                         ORDER BY i.issued_at DESC');
    $st->execute([$user_id]);
    $rows = $st->fetchAll();
}
?>
<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<title>発行済クーポン照会 | 管理ダッシュボード</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  :root{ --bg:#f7f7fb; --card:#fff; --ink:#111827; --muted:#6b7280; --brand:#2563eb; --line:#e5e7eb }
  *{box-sizing:border-box}
  body{margin:0;background:var(--bg);color:var(--ink);font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial}
  header{display:flex;gap:12px;align-items:center;justify-content:space-between;padding:16px 20px;background:#fff;border-bottom:1px solid var(--line);position:sticky;top:0}
  .brand{font-weight:700}
  .user{color:var(--muted);font-size:.95rem}
  .logout{color:#fff;background:#111827;padding:.5rem .8rem;border-radius:10px;text-decoration:none}
  main{max-width:1200px;margin:24px auto;padding:0 16px}
  h1{font-size:1.25rem;margin:0 0 8px}
  .sub{color:var(--muted);margin:0 0 16px}
  .card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:14px;margin-bottom:16px}
  label{display:block;margin:.6rem 0 .2rem;font-weight:600}
  input[type="text"]{width:100%;padding:.6rem;border:1px solid #d1d5db;border-radius:10px;font-size:1rem}
  .btn{display:inline-flex;align-items:center;justify-content:center;padding:.6rem .9rem;border-radius:10px;border:1px solid var(--line);text-decoration:none;cursor:pointer}
  .btn-primary{background:var(--brand);color:#fff;border-color:transparent}
  .btn-ghost{background:#fff;color:#111827}
  table{width:100%;border-collapse:collapse}
  th,td{padding:10px 12px;border-top:1px solid var(--line);text-align:left;font-size:.92rem}
  th{background:#fafafa}
  .muted{color:var(--muted)}
</style>
</head>
<body>
  <header>
    <div class="brand">ポイントアプリ 管理ダッシュボード</div>
    <div style="display:flex;align-items:center;gap:12px;">
      <a class="btn btn-ghost" href="<?= j($BASE.'index.php') ?>">← ダッシュボードへ戻る</a>
      <div class="user">こんにちは、<?= j($admin['username']) ?> さん</div>
      <a class="logout" href="<?= j($BASE.'logout.php') ?>">ログアウト</a>
    </div>
  </header>

  <main>
    <h1>発行済クーポン照会</h1>
    <p class="sub">ユーザーIDを入力して、そのユーザーに発行されたクーポンを確認します。</p>

    <div class="card">
      <form method="get">
        <label>ユーザーID</label>
        <div style="display:grid;grid-template-columns:1fr auto;gap:10px">
          <input type="text" name="user_id" value="<?= j($user_id) ?>" placeholder="例: user_001" required>
          <button class="btn btn-primary" type="submit">検索</button>
        </div>
      </form>
    </div>

    <div class="card">
      <h2 style="font-size:1rem;margin:0 0 10px">検索結果</h2>
      <div style="overflow:auto">
        <table>
          <tr><th>発行ID</th><th>クーポンID</th><th>名称</th><th>有効期限</th><th>使用</th><th>状態</th><th>発行時刻</th><th>使用時刻</th></tr>
          <?php if ($user_id === ''): ?>
            <tr><td colspan="8" class="muted">ユーザーIDを入力して検索してください。</td></tr>
          <?php elseif (empty($rows)): ?>
            <tr><td colspan="8" class="muted">対象ユーザーの発行済みクーポンはありません。</td></tr>
          <?php else: ?>
            <?php foreach($rows as $r): ?>
              <tr style="background:<?= $r['is_active']? '#fff' : '#eee' ?>">
                <td><?= j($r['issued_id']) ?></td>
                <td><?= j($r['coupon_id']) ?></td>
                <td><?= j($r['coupon_name']) ?></td>
                <td><?= j($r['expiry_text']) ?></td>
                <td><?= $r['used_flag'] ? '使用済' : '未使用' ?></td>
                <td><?= $r['is_active'] ? '有効' : '無効' ?></td>
                <td><?= j($r['issued_at']) ?></td>
                <td><?= j($r['used_at'] ?? '') ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </table>
      </div>
      <div style="margin-top:10px">
        <a class="btn btn-ghost" href="<?= j($BASE.'index.php') ?>">← ダッシュボードへ戻る</a>
      </div>
    </div>
  </main>
</body>
</html>
