<?php
// members.php - 会員マスタ UPSERT（ダッシュボード統一UI＋戻るボタン）

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/helpers.php';
require_login();

$admin = current_admin();
$pdo   = db();
$BASE  = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';

$stores = $pdo->query('SELECT store_id, name FROM stores WHERE is_deleted=0 ORDER BY store_id')->fetchAll();
$cards  = $pdo->query('SELECT card_type_id, label FROM card_types ORDER BY card_type_id')->fetchAll();

$err = ''; $msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id  = trim($_POST['user_id'] ?? '');
    $mem_no   = trim($_POST['membership_no'] ?? '');
    $store_id = (int)($_POST['store_id'] ?? 0);
    $stamps   = (int)($_POST['stamp_count'] ?? 0);
    $nick     = trim($_POST['nickname'] ?? '');
    $card     = (int)($_POST['card_type_id'] ?? 1);

    if ($user_id === '' || $mem_no === '' || !$store_id || $nick === '') $err = '必須項目が未入力です。';
    if (!$err && ($stamps < 0 || $stamps > 1000)) $err = '保有スタンプ数は 0〜1000 の範囲で入力してください。';

    if ($err === '') {
        $pdo->prepare('INSERT INTO members (user_id, membership_no, store_id, stamp_count, nickname, card_type_id)
                       VALUES (?,?,?,?,?,?)
                       ON DUPLICATE KEY UPDATE membership_no=VALUES(membership_no), store_id=VALUES(store_id),
                                               stamp_count=VALUES(stamp_count), nickname=VALUES(nickname),
                                               card_type_id=VALUES(card_type_id)')
            ->execute([$user_id, $mem_no, $store_id, $stamps, $nick, $card]);
        op_log('upsert','members',$user_id,['nickname'=>$nick]);
        $msg = '保存しました。';
    }
}

// 一覧（直近200件）
$rows = $pdo->query('SELECT m.*, s.name AS store_name, c.label AS card_label
                     FROM members m
                     JOIN stores s ON s.store_id=m.store_id
                     JOIN card_types c ON c.card_type_id=m.card_type_id
                     ORDER BY m.created_at DESC LIMIT 200')->fetchAll();

?>
<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<title>会員マスタ | 管理ダッシュボード</title>
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
  .grid{display:grid;grid-template-columns:1fr 1.3fr;gap:16px}
  .card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:14px}
  .card h2{font-size:1rem;margin:0 0 10px}
  label{display:block;margin:.6rem 0 .2rem;font-weight:600}
  input[type="text"], input[type="number"], select {width:100%;padding:.6rem;border:1px solid #d1d5db;border-radius:10px;font-size:1rem}
  .row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
  .btns{display:flex;gap:8px;margin-top:12px}
  .btn{display:inline-flex;align-items:center;justify-content:center;padding:.6rem .9rem;border-radius:10px;border:1px solid var(--line);text-decoration:none;cursor:pointer}
  .btn-primary{background:var(--brand);color:#fff;border-color:transparent}
  .btn-ghost{background:#fff;color:#111827}
  table{width:100%;border-collapse:collapse}
  th,td{padding:10px 12px;border-top:1px solid var(--line);text-align:left;font-size:.92rem}
  th{background:#fafafa}
  .muted{color:var(--muted)}
  @media (max-width: 960px){ .grid{grid-template-columns:1fr} }
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
    <h1>会員マスタ</h1>
    <p class="sub">ユーザーID（キー）、会員番号、所属店舗、保有スタンプ数、ニックネーム、カード種別を管理します。</p>

    <?php if ($msg): ?><div style="background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46;padding:.6rem .8rem;border-radius:10px;margin:10px 0"><?= j($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div style="background:#fee2e2;border:1px solid #fecaca;color:#7f1d1d;padding:.6rem .8rem;border-radius:10px;margin:10px 0"><?= j($err) ?></div><?php endif; ?>

    <div class="grid">
      <div class="card">
        <h2>新規登録 / 更新（UPSERT）</h2>
        <form method="post" autocomplete="off">
          <label>ユーザーID（キー）</label>
          <input type="text" name="user_id" required placeholder="例: user_001">

          <div class="row">
            <div>
              <label>会員番号</label>
              <input type="text" name="membership_no" required>
            </div>
            <div>
              <label>所属店舗</label>
              <select name="store_id" required>
                <option value="">選択</option>
                <?php foreach($stores as $s): ?>
                  <option value="<?= j($s['store_id']) ?>"><?= j($s['store_id'].' : '.$s['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="row">
            <div>
              <label>保有スタンプ数（0〜1000）</label>
              <input type="number" name="stamp_count" min="0" max="1000" value="0" required>
            </div>
            <div>
              <label>カード種別</label>
              <select name="card_type_id">
                <?php foreach($cards as $c): ?>
                  <option value="<?= j($c['card_type_id']) ?>"><?= j($c['label']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <label>ニックネーム</label>
          <input type="text" name="nickname" required>

          <div class="btns">
            <button class="btn btn-primary" type="submit">保存</button>
            <a class="btn btn-ghost" href="<?= j($BASE.'index.php') ?>">← ダッシュボードへ戻る</a>
          </div>
        </form>
      </div>

      <div class="card">
        <h2>会員一覧（最新200）</h2>
        <div style="overflow:auto">
          <table>
            <tr><th>UserID</th><th>会員番号</th><th>店舗</th><th>スタンプ</th><th>ニックネーム</th><th>カード</th><th>登録日時</th></tr>
            <?php if (empty($rows)): ?>
              <tr><td colspan="7" class="muted">会員がまだ登録されていません。</td></tr>
            <?php else: ?>
              <?php foreach($rows as $r): ?>
                <tr>
                  <td><?= j($r['user_id']) ?></td>
                  <td><?= j($r['membership_no']) ?></td>
                  <td><?= j($r['store_name']) ?></td>
                  <td><?= j($r['stamp_count']) ?></td>
                  <td><?= j($r['nickname']) ?></td>
                  <td><?= j($r['card_label']) ?></td>
                  <td><?= j($r['created_at']) ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </table>
        </div>
      </div>
    </div>
  </main>
</body>
</html>
