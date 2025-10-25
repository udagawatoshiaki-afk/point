<?php
// stores.php - 店舗マスタ（ダッシュボードと統一デザイン／戻るボタン付き）

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/helpers.php';
require_login();

$admin = current_admin();
$pdo   = db();
$BASE  = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/'; // 配置に依存しないリンク生成

$err = '';
$msg = '';

// 作成/更新
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id   = isset($_POST['store_id']) && $_POST['store_id'] !== '' ? (int)$_POST['store_id'] : null;
    $name = trim($_POST['name'] ?? '');
    $pc   = preg_replace('/[^0-9]/', '', $_POST['postal_code'] ?? '');  // 数字のみ保持
    $addr = trim($_POST['address'] ?? '');
    $tel  = preg_replace('/[^0-9\-]/', '', $_POST['phone'] ?? '');

    if ($name === '' || $pc === '' || $addr === '' || $tel === '') {
        $err = '必須項目が未入力です。';
    } elseif (!preg_match('/^\d{7}$/', $pc)) {
        $err = '郵便番号はハイフンなし7桁で入力してください（例：3710844）。';
    } elseif (!preg_match('/^\d[\d\-]{8,}$/', $tel)) {
        $err = '電話番号の形式を確認してください（数字とハイフンのみ、9桁以上）。';
    }

    if ($err === '') {
        if ($id) {
            $pdo->prepare('UPDATE stores SET name=?, postal_code=?, address=?, phone=? WHERE store_id=? AND is_deleted=0')
                ->execute([$name, $pc, $addr, $tel, $id]);
            op_log('update', 'stores', $id, ['name' => $name]);
            $msg = '更新しました。';
        } else {
            $pdo->prepare('INSERT INTO stores (name, postal_code, address, phone) VALUES (?,?,?,?)')
                ->execute([$name, $pc, $addr, $tel]);
            $newId = $pdo->lastInsertId();
            op_log('create', 'stores', $newId, ['name' => $name]);
            $msg = '登録しました。';
        }
    }
}

// 削除（ソフトデリート）
if (isset($_GET['del']) && $_GET['del'] !== '') {
    $did = (int)$_GET['del'];
    $pdo->prepare('UPDATE stores SET is_deleted=1 WHERE store_id=?')->execute([$did]);
    op_log('delete', 'stores', $did, null);
    // 一度GETパラメータをクリア
    header('Location: ' . $BASE . 'stores.php');
    exit;
}

// 編集対象の読み込み
$edit = null;
if (isset($_GET['edit']) && $_GET['edit'] !== '') {
    $eid = (int)$_GET['edit'];
    $st = $pdo->prepare('SELECT * FROM stores WHERE store_id=? AND is_deleted=0');
    $st->execute([$eid]);
    $edit = $st->fetch();
}

// 一覧
$rows = $pdo->query('SELECT * FROM stores WHERE is_deleted=0 ORDER BY store_id DESC')->fetchAll();
$storeCount = count($rows);

function fmt_zip($pc) { return preg_match('/^\d{7}$/', $pc) ? substr($pc,0,3) . '-' . substr($pc,3) : $pc; }
?>
<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<title>店舗マスタ | 管理ダッシュボード</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  :root{
    --bg:#f7f7fb; --card:#fff; --ink:#111827; --muted:#6b7280;
    --brand:#2563eb; --ok:#16a34a; --warn:#ca8a04; --err:#b91c1c; --line:#e5e7eb;
  }
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
  .card{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:14px}
  .card h2{font-size:1rem;margin:0 0 10px}
  label{display:block;margin:.6rem 0 .2rem;font-weight:600}
  input[type="text"], input[type="tel"]{width:100%;padding:.6rem;border:1px solid #d1d5db;border-radius:10px;font-size:1rem}
  .row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
  .btns{display:flex;gap:8px;margin-top:12px}
  .btn{display:inline-flex;align-items:center;justify-content:center;padding:.6rem .9rem;border-radius:10px;border:1px solid var(--line);text-decoration:none;cursor:pointer}
  .btn-primary{background:var(--brand);color:#fff;border-color:transparent}
  .btn-ghost{background:#fff;color:#111827}
  .btn-danger{background:#fee2e2;border-color:#fecaca;color:#991b1b}
  .flash{margin:10px 0;padding:.6rem .8rem;border-radius:10px}
  .flash.ok{background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46}
  .flash.err{background:#fee2e2;border:1px solid #fecaca;color:#7f1d1d}
  table{width:100%;border-collapse:collapse}
  th,td{padding:10px 12px;border-top:1px solid var(--line);text-align:left;font-size:.92rem}
  th{background:#fafafa}
  .muted{color:var(--muted)}
  .toolbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;gap:10px}
  @media (max-width: 960px){
    .grid{grid-template-columns:1fr}
  }
  @media (hover:hover){
    .btn:hover{box-shadow:0 8px 28px rgba(0,0,0,.06);transform:translateY(-1px);transition:.2s}
  }
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
    <h1>店舗マスタ</h1>
    <p class="sub">店舗情報の登録・編集・削除（削除は非表示扱い）。現在の店舗数：<?= j((string)$storeCount) ?>件</p>

    <?php if ($msg): ?><div class="flash ok"><?= j($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="flash err"><?= j($err) ?></div><?php endif; ?>

    <div class="grid">
      <!-- 入力フォーム -->
      <div class="card">
        <h2><?= $edit ? '店舗を編集: ID '.j($edit['store_id']) : '新規店舗を登録' ?></h2>
        <form method="post" autocomplete="off">
          <input type="hidden" name="store_id" value="<?= j($edit['store_id'] ?? '') ?>">

          <label>店舗名</label>
          <input type="text" name="name" value="<?= j($edit['name'] ?? '') ?>" required>

          <div class="row">
            <div>
              <label>郵便番号（ハイフンなし7桁）</label>
              <input type="text" name="postal_code" value="<?= j($edit['postal_code'] ?? '') ?>" required>
            </div>
            <div>
              <label>電話番号（数字と-）</label>
              <input type="tel" name="phone" value="<?= j($edit['phone'] ?? '') ?>" required>
            </div>
          </div>

          <label>住所</label>
          <input type="text" name="address" value="<?= j($edit['address'] ?? '') ?>" required>

          <div class="btns">
            <button class="btn btn-primary" type="submit"><?= $edit ? '更新する' : '登録する' ?></button>
            <?php if ($edit): ?>
              <a class="btn btn-ghost" href="<?= j($BASE.'stores.php') ?>">新規作成に戻る</a>
            <?php endif; ?>
            <a class="btn btn-ghost" href="<?= j($BASE.'index.php') ?>">← ダッシュボードへ戻る</a>
          </div>
        </form>
      </div>

      <!-- 一覧 -->
      <div class="card">
        <div class="toolbar">
          <h2>店舗一覧</h2>
          <span class="muted">全 <?= j((string)$storeCount) ?> 件</span>
        </div>
        <div style="overflow:auto">
          <table>
            <tr>
              <th>ID</th>
              <th>店舗名</th>
              <th>郵便番号</th>
              <th>住所</th>
              <th>電話番号</th>
              <th>操作</th>
            </tr>
            <?php if (empty($rows)): ?>
              <tr><td colspan="6" class="muted">登録されている店舗はありません。</td></tr>
            <?php else: ?>
              <?php foreach ($rows as $r): ?>
                <tr>
                  <td><?= j($r['store_id']) ?></td>
                  <td><?= j($r['name']) ?></td>
                  <td><?= j(fmt_zip($r['postal_code'])) ?></td>
                  <td><?= j($r['address']) ?></td>
                  <td><?= j($r['phone']) ?></td>
                  <td style="white-space:nowrap">
                    <a class="btn btn-ghost" href="<?= j($BASE.'stores.php?edit='.$r['store_id']) ?>">編集</a>
                    <a class="btn btn-danger" href="<?= j($BASE.'stores.php?del='.$r['store_id']) ?>" onclick="return confirm('店舗ID: <?= j($r['store_id']) ?> を削除（非表示）しますか？')">削除</a>
                  </td>
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
