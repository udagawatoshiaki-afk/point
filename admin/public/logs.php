<?php
// logs.php - 操作ログビューア（統一UI＋戻るボタン）
// ・操作ログ（op_logs）とログイン履歴（login_history）を表示
// ・簡易フィルタ（日時・ユーザー・アクション・エンティティ・成功/失敗）

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/helpers.php';
require_login();

$admin = current_admin();
$pdo   = db();
$BASE  = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';

// 受け取り（GET）
$q_user   = trim($_GET['user']   ?? '');
$q_from   = trim($_GET['from']   ?? ''); // YYYY-MM-DD
$q_to     = trim($_GET['to']     ?? '');
$q_action = trim($_GET['action'] ?? '');
$q_entity = trim($_GET['entity'] ?? '');
$q_result = trim($_GET['result'] ?? 'all'); // all / 1 / 0

// ===== 操作ログ =====
$sql = "SELECT o.id, o.created_at, a.username, o.action, o.entity, o.entity_id
        FROM op_logs o JOIN admin_users a ON a.admin_user_id=o.admin_user_id
        WHERE 1=1";
$param = [];
if ($q_user !== '')   { $sql .= " AND a.username LIKE ?"; $param[] = "%$q_user%"; }
if ($q_action !== '') { $sql .= " AND o.action = ?";      $param[] = $q_action; }
if ($q_entity !== '') { $sql .= " AND o.entity = ?";      $param[] = $q_entity; }
if ($q_from !== '')   { $sql .= " AND o.created_at >= ?"; $param[] = $q_from.' 00:00:00'; }
if ($q_to !== '')     { $sql .= " AND o.created_at <= ?"; $param[] = $q_to.' 23:59:59'; }
$sql .= " ORDER BY o.id DESC LIMIT 200";
$st = $pdo->prepare($sql);
$st->execute($param);
$op_rows = $st->fetchAll();

// ===== ログイン履歴 =====
$sql2 = "SELECT id, created_at, username, success, ip_address, user_agent
         FROM login_history WHERE 1=1";
$param2 = [];
if ($q_user !== '') { $sql2 .= " AND username LIKE ?"; $param2[] = "%$q_user%"; }
if ($q_result === '0' || $q_result === '1') { $sql2 .= " AND success = ?"; $param2[] = (int)$q_result; }
if ($q_from !== '') { $sql2 .= " AND created_at >= ?"; $param2[] = $q_from.' 00:00:00'; }
if ($q_to   !== '') { $sql2 .= " AND created_at <= ?"; $param2[] = $q_to.' 23:59:59'; }
$sql2 .= " ORDER BY id DESC LIMIT 200";
$st2 = $pdo->prepare($sql2);
$st2->execute($param2);
$login_rows = $st2->fetchAll();

?>
<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<title>操作ログビューア | 管理ダッシュボード</title>
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
  input[type="text"], input[type="date"], select{width:100%;padding:.6rem;border:1px solid #d1d5db;border-radius:10px;font-size:1rem}
  table{width:100%;border-collapse:collapse}
  th,td{padding:10px 12px;border-top:1px solid var(--line);text-align:left;font-size:.92rem;vertical-align:top}
  th{background:#fafafa}
  .muted{color:var(--muted)}
  .btn{display:inline-flex;align-items:center;justify-content:center;padding:.6rem .9rem;border-radius:10px;border:1px solid var(--line);text-decoration:none;cursor:pointer}
  .btn-primary{background:var(--brand);color:#fff;border-color:transparent}
  .btn-ghost{background:#fff;color:#111827}
  .filters{display:grid;grid-template-columns:repeat(6,1fr);gap:10px}
  @media (max-width: 1100px){ .filters{grid-template-columns:1fr 1fr 1fr} }
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
    <h1>操作ログビューア</h1>
    <p class="sub">最新200件まで表示。上部の条件で絞り込みできます。</p>

    <div class="card">
      <form method="get">
        <div class="filters">
          <div>
            <label>ユーザー名（部分一致）</label>
            <input type="text" name="user" value="<?= j($q_user) ?>" placeholder="例: admin">
          </div>
          <div>
            <label>アクション</label>
            <input type="text" name="action" value="<?= j($q_action) ?>" placeholder="例: create/update/delete/upsert">
          </div>
          <div>
            <label>エンティティ</label>
            <input type="text" name="entity" value="<?= j($q_entity) ?>" placeholder="例: stores/menus/...">
          </div>
          <div>
            <label>開始日</label>
            <input type="date" name="from" value="<?= j($q_from) ?>">
          </div>
          <div>
            <label>終了日</label>
            <input type="date" name="to" value="<?= j($q_to) ?>">
          </div>
          <div>
            <label>ログイン結果</label>
            <select name="result">
              <option value="all" <?= $q_result==='all'?'selected':'' ?>>すべて</option>
              <option value="1" <?= $q_result==='1'?'selected':'' ?>>成功のみ</option>
              <option value="0" <?= $q_result==='0'?'selected':'' ?>>失敗のみ</option>
            </select>
          </div>
        </div>
        <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap">
          <button class="btn btn-primary" type="submit">絞り込み</button>
          <a class="btn" href="<?= j($BASE.'logs.php') ?>">クリア</a>
          <a class="btn btn-ghost" href="<?= j($BASE.'index.php') ?>">← ダッシュボードへ戻る</a>
        </div>
      </form>
    </div>

    <div class="card">
      <h2 style="font-size:1rem;margin:0 0 10px">操作ログ（最新200）</h2>
      <div style="overflow:auto">
        <table>
          <tr><th>ID</th><th>日時</th><th>ユーザー</th><th>アクション</th><th>エンティティ</th><th>対象ID</th></tr>
          <?php if (empty($op_rows)): ?>
            <tr><td colspan="6" class="muted">該当する操作ログはありません。</td></tr>
          <?php else: ?>
            <?php foreach($op_rows as $r): ?>
              <tr>
                <td><?= j($r['id']) ?></td>
                <td><?= j($r['created_at']) ?></td>
                <td><?= j($r['username']) ?></td>
                <td><?= j($r['action']) ?></td>
                <td><?= j($r['entity']) ?></td>
                <td><?= j((string)$r['entity_id']) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </table>
      </div>
    </div>

    <div class="card">
      <h2 style="font-size:1rem;margin:0 0 10px">ログイン履歴（最新200）</h2>
      <div style="overflow:auto">
        <table>
          <tr><th>ID</th><th>日時</th><th>ユーザー名</th><th>結果</th><th>IP</th><th>User-Agent</th></tr>
          <?php if (empty($login_rows)): ?>
            <tr><td colspan="6" class="muted">該当するログイン履歴はありません。</td></tr>
          <?php else: ?>
            <?php foreach($login_rows as $r): ?>
              <tr>
                <td><?= j($r['id']) ?></td>
                <td><?= j($r['created_at']) ?></td>
                <td><?= j($r['username'] ?? '') ?></td>
                <td><?= ((int)$r['success'] === 1) ? '成功' : '失敗' ?></td>
                <td><?= j($r['ip_address']) ?></td>
                <td class="muted"><?= j($r['user_agent']) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </table>
      </div>
    </div>
  </main>
</body>
</html>
