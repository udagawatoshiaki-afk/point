<?php
// stamps_issued.php - スタンプ照会（ユーザー別／統一UI＋戻るボタン）

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/helpers.php';
require_login();

$admin = current_admin();
$pdo   = db();
$BASE  = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';

$user_id = trim($_GET['user_id'] ?? '');
$filter  = $_GET['status'] ?? 'all'; // all / 0 / 1 / 2
$validStatus = ['all','0','1','2'];
if (!in_array($filter, $validStatus, true)) $filter = 'all';

$rows = [];
$counts = ['0'=>0,'1'=>0,'2'=>0,'total'=>0];

if ($user_id !== '') {
    // 件数（ステータス別）
    $stc = $pdo->prepare('SELECT status, COUNT(*) c FROM stamps_ledger WHERE user_id=? GROUP BY status');
    $stc->execute([$user_id]);
    foreach ($stc->fetchAll() as $c) {
        $counts[(string)$c['status']] = (int)$c['c'];
        $counts['total'] += (int)$c['c'];
    }

    // 照会
    $sql = 'SELECT l.stamp_issue_id, l.status, l.issued_at, l.used_at,
                   s.label, s.kind, s.color_hex
            FROM stamps_ledger l
            JOIN stamps_master s ON s.stamp_id=l.stamp_id
            WHERE l.user_id = ?';
    $params = [$user_id];
    if ($filter !== 'all') {
        $sql .= ' AND l.status = ?';
        $params[] = (int)$filter;
    }
    $sql .= ' ORDER BY l.status ASC, l.issued_at DESC LIMIT 1000';

    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll();
}

function status_label($s) {
    switch ((int)$s) {
        case 0: return '空欄';
        case 1: return '押印済';
        case 2: return '使用済';
        default: return (string)$s;
    }
}
?>
<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<title>スタンプ照会 | 管理ダッシュボード</title>
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
  input[type="text"], select{width:100%;padding:.6rem;border:1px solid #d1d5db;border-radius:10px;font-size:1rem}
  .btn{display:inline-flex;align-items:center;justify-content:center;padding:.6rem .9rem;border-radius:10px;border:1px solid var(--line);text-decoration:none;cursor:pointer}
  .btn-primary{background:var(--brand);color:#fff;border-color:transparent}
  .btn-ghost{background:#fff;color:#111827}
  table{width:100%;border-collapse:collapse}
  th,td{padding:10px 12px;border-top:1px solid var(--line);text-align:left;font-size:.92rem}
  th{background:#fafafa}
  .muted{color:var(--muted)}
  .swatch{display:inline-block;width:18px;height:18px;border-radius:6px;border:1px solid #ccc;vertical-align:middle;margin-right:6px}
  .stats{display:flex;gap:10px;flex-wrap:wrap;color:#374151}
  .badge{display:inline-block;background:#eef2ff;color:#3730a3;border:1px solid #c7d2fe;border-radius:999px;padding:.15rem .5rem;font-size:.78rem}
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
    <h1>スタンプ照会</h1>
    <p class="sub">ユーザーIDごとに発行されたスタンプを表示します（最大1000件）。</p>

    <div class="card">
      <form method="get">
        <div style="display:grid;grid-template-columns:2fr 1fr auto;gap:10px">
          <div>
            <label>ユーザーID</label>
            <input type="text" name="user_id" value="<?= j($user_id) ?>" placeholder="例: user_001" required>
          </div>
          <div>
            <label>ステータス</label>
            <select name="status">
              <option value="all" <?= $filter==='all'?'selected':'' ?>>すべて</option>
              <option value="0"   <?= $filter==='0'  ?'selected':'' ?>>0：空欄</option>
              <option value="1"   <?= $filter==='1'  ?'selected':'' ?>>1：押印済</option>
              <option value="2"   <?= $filter==='2'  ?'selected':'' ?>>2：使用済</option>
            </select>
          </div>
          <div style="display:flex;align-items:end">
            <button class="btn btn-primary" type="submit" style="width:120px">検索</button>
          </div>
        </div>
      </form>

      <?php if ($user_id !== ''): ?>
        <div class="stats" style="margin-top:10px">
          <span class="badge">合計: <?= j((string)$counts['total']) ?></span>
          <span class="badge">空欄: <?= j((string)$counts['0']) ?></span>
          <span class="badge">押印済: <?= j((string)$counts['1']) ?></span>
          <span class="badge">使用済: <?= j((string)$counts['2']) ?></span>
        </div>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2 style="font-size:1rem;margin:0 0 10px">検索結果</h2>
      <div style="overflow:auto">
        <table>
          <tr><th>発行ID</th><th>状態</th><th>スタンプ</th><th>種類</th><th>色</th><th>発行時刻</th><th>使用時刻</th></tr>
          <?php if ($user_id === ''): ?>
            <tr><td colspan="7" class="muted">ユーザーIDを入力して検索してください。</td></tr>
          <?php elseif (empty($rows)): ?>
            <tr><td colspan="7" class="muted">対象データはありません。</td></tr>
          <?php else: ?>
            <?php foreach($rows as $r): ?>
              <tr>
                <td><?= j($r['stamp_issue_id']) ?></td>
                <td><?= j(status_label($r['status'])) ?></td>
                <td><?= j($r['label']) ?></td>
                <td><?= $r['kind']==='NORMAL'?'通常':'ゲーム' ?></td>
                <td><span class="swatch" style="background:<?= j($r['color_hex']) ?>"></span><?= j($r['color_hex']) ?></td>
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
