<?php
// coupons_master.php - クーポンマスタ CRUD（ダッシュボード統一UI＋戻るボタン）

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/helpers.php';
require_login();

$admin = current_admin();
$pdo   = db();
$BASE  = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';

$cards = $pdo->query('SELECT card_type_id, label FROM card_types ORDER BY card_type_id')->fetchAll();

$err = ''; $msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $coupon_id = (int)($_POST['coupon_id'] ?? 0);
    $name      = trim($_POST['name'] ?? '');
    $desc      = trim($_POST['description'] ?? '');
    $expiry    = trim($_POST['expiry_text'] ?? '');
    $active    = isset($_POST['is_active']) ? 1 : 0;
    $req_card  = ($_POST['required_card_type'] ?? '') !== '' ? (int)$_POST['required_card_type'] : null;

    if ($name === '' || $desc === '' || $expiry === '') $err = '必須項目が未入力です。';
    if (!$err && !validate_zenkaku_len($name, 10)) $err = 'クーポン名は全角10文字以内にしてください。';
    if (!$err && !validate_zenkaku_len($desc, 20)) $err = '説明文は全角20文字以内にしてください。';

    if ($err === '') {
        if ($coupon_id) {
            $pdo->prepare('UPDATE coupons_master SET name=?, description=?, expiry_text=?, is_active=?, required_card_type=? WHERE coupon_id=?')
                ->execute([$name, $desc, $expiry, $active, $req_card, $coupon_id]);
            op_log('update','coupons_master',$coupon_id,['name'=>$name]);
            $msg = '更新しました。';
        } else {
            $pdo->prepare('INSERT INTO coupons_master (name, description, expiry_text, is_active, required_card_type) VALUES (?,?,?,?,?)')
                ->execute([$name, $desc, $expiry, $active, $req_card]);
            $newId = $pdo->lastInsertId();
            op_log('create','coupons_master',$newId,['name'=>$name]);
            $msg = '登録しました。';
        }
    }
}

// 編集対象
$edit = null;
if (isset($_GET['edit']) && $_GET['edit'] !== '') {
    $eid = (int)$_GET['edit'];
    $st = $pdo->prepare('SELECT * FROM coupons_master WHERE coupon_id=?');
    $st->execute([$eid]);
    $edit = $st->fetch();
}

$rows = $pdo->query('SELECT c.*, ct.label AS card_label
                     FROM coupons_master c
                     LEFT JOIN card_types ct ON ct.card_type_id=c.required_card_type
                     ORDER BY coupon_id DESC')->fetchAll();
?>
<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<title>クーポンマスタ | 管理ダッシュボード</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  :root{ --bg:#f7f7fb; --card:#fff; --ink:#111827; --muted:#6b7280; --brand:#2563eb; --line:#e5e7eb; --err:#b91c1c }
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
  input[type="text"], select {width:100%;padding:.6rem;border:1px solid #d1d5db;border-radius:10px;font-size:1rem}
  .btns{display:flex;gap:8px;margin-top:12px}
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
    <h1>クーポンマスタ</h1>
    <p class="sub">名称・説明文・有効期限テキスト・有効/無効・必要カード種別を管理します。</p>

    <?php if ($msg): ?><div style="background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46;padding:.6rem .8rem;border-radius:10px;margin:10px 0"><?= j($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div style="background:#fee2e2;border:1px solid #fecaca;color:#7f1d1d;padding:.6rem .8rem;border-radius:10px;margin:10px 0"><?= j($err) ?></div><?php endif; ?>

    <div class="grid">
      <div class="card">
        <h2><?= $edit ? '編集: ID '.j($edit['coupon_id']) : '新規作成' ?></h2>
        <form method="post" autocomplete="off">
          <input type="hidden" name="coupon_id" value="<?= j($edit['coupon_id'] ?? '') ?>">

          <label>クーポン名（全角10）</label>
          <input type="text" name="name" value="<?= j($edit['name'] ?? '') ?>" required>

          <label>説明文（全角20）</label>
          <input type="text" name="description" value="<?= j($edit['description'] ?? '') ?>" required>

          <label>有効期限（テキスト）</label>
          <input type="text" name="expiry_text" value="<?= j($edit['expiry_text'] ?? '') ?>" required>

          <label>状態</label>
          <select name="is_active">
            <option value="1" <?= (!empty($edit) ? ((int)$edit['is_active']===1?'selected':'') : 'selected') ?>>有効</option>
            <option value="0" <?= (!empty($edit) ? ((int)$edit['is_active']===0?'selected':'') : '') ?>>無効</option>
          </select>

          <label>必要カード種別（任意）</label>
          <select name="required_card_type">
            <option value="">指定なし</option>
            <?php foreach($cards as $c): $sel = (!empty($edit) && (int)$edit['required_card_type']===(int)$c['card_type_id']) ? 'selected':''; ?>
              <option value="<?= j($c['card_type_id']) ?>" <?= $sel ?>><?= j($c['label']) ?></option>
            <?php endforeach; ?>
          </select>

          <div class="btns">
            <button class="btn btn-primary" type="submit"><?= $edit ? '更新する' : '登録する' ?></button>
            <?php if ($edit): ?><a class="btn" href="<?= j($BASE.'coupons_master.php') ?>">新規作成に戻る</a><?php endif; ?>
            <a class="btn btn-ghost" href="<?= j($BASE.'index.php') ?>">← ダッシュボードへ戻る</a>
          </div>
        </form>
      </div>

      <div class="card">
        <h2>クーポン一覧</h2>
        <div style="overflow:auto">
          <table>
            <tr><th>ID</th><th>名称</th><th>説明</th><th>有効期限</th><th>状態</th><th>必要カード</th><th>編集</th></tr>
            <?php if (empty($rows)): ?>
              <tr><td colspan="7" class="muted">登録されたクーポンはありません。</td></tr>
            <?php else: ?>
              <?php foreach($rows as $r): ?>
                <tr style="background:<?= $r['is_active'] ? '#fff' : '#eee' ?>">
                  <td><?= j($r['coupon_id']) ?></td>
                  <td><?= j($r['name']) ?></td>
                  <td><?= j($r['description']) ?></td>
                  <td><?= j($r['expiry_text']) ?></td>
                  <td><?= $r['is_active'] ? '有効' : '無効' ?></td>
                  <td><?= j($r['card_label'] ?? '—') ?></td>
                  <td><a class="btn" href="<?= j($BASE.'coupons_master.php?edit='.$r['coupon_id']) ?>">編集</a></td>
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
