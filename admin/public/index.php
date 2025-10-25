<?php
// index.php - 管理ダッシュボード（機能メニュー付き）

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/helpers.php'; // j()
require_login();
$admin = current_admin();

$BASE = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/'; // 配置に依存しないリンク生成
$pdo  = db();

// ------ 統計の取得（失敗してもダッシュボードは表示されるように try/catch） ------
$stats = [
  'stores'          => 0,
  'members'         => 0,
  'coupons_active'  => 0,
  'coupons_issued'  => 0,
  'stamps_master'   => 0,
  'stamps_issued'   => 0,
  'menus_visible'   => 0,
  'slider_images'   => 0,
];

try {
  $stats['stores']         = (int)$pdo->query("SELECT COUNT(*) FROM stores WHERE is_deleted=0")->fetchColumn();
  $stats['members']        = (int)$pdo->query("SELECT COUNT(*) FROM members")->fetchColumn();
  $stats['coupons_active'] = (int)$pdo->query("SELECT COUNT(*) FROM coupons_master WHERE is_active=1")->fetchColumn();
  $stats['coupons_issued'] = (int)$pdo->query("SELECT COUNT(*) FROM coupons_issued")->fetchColumn();
  $stats['stamps_master']  = (int)$pdo->query("SELECT COUNT(*) FROM stamps_master")->fetchColumn();
  $stats['stamps_issued']  = (int)$pdo->query("SELECT COUNT(*) FROM stamps_ledger")->fetchColumn();
  $stats['menus_visible']  = (int)$pdo->query("SELECT COUNT(*) FROM menus WHERE is_visible=1")->fetchColumn();
  $stats['slider_images']  = (int)$pdo->query("SELECT COUNT(*) FROM slider_images")->fetchColumn();
} catch (Throwable $e) {
  // 必要ならログ出力（ここでは画面崩れ防止のため握りつぶし）
}

// 直近ログ
$op_logs = [];
$login_logs = [];
try {
  $stmt = $pdo->query("SELECT o.created_at, a.username, o.action, o.entity, o.entity_id 
                       FROM op_logs o JOIN admin_users a ON a.admin_user_id=o.admin_user_id
                       ORDER BY o.id DESC LIMIT 10");
  $op_logs = $stmt->fetchAll();

  $stmt = $pdo->query("SELECT created_at, username, success, ip_address 
                       FROM login_history ORDER BY id DESC LIMIT 8");
  $login_logs = $stmt->fetchAll();
} catch (Throwable $e) {}

// メニュー定義（タイトル / 説明 / リンク / バッジ数）
$menus = [
  [
    'title' => '店舗マスタ',
    'desc'  => '店舗情報の登録・更新',
    'href'  => $BASE . 'stores.php',
    'badge' => $stats['stores']
  ],
  [
    'title' => 'スライダー画像',
    'desc'  => '1600×900 画像を最大5件/店舗',
    'href'  => $BASE . 'slider.php',
    'badge' => $stats['slider_images']
  ],
  [
    'title' => 'クーポンマスタ',
    'desc'  => '名称・説明文・有効/無効を管理',
    'href'  => $BASE . 'coupons_master.php',
    'badge' => $stats['coupons_active'] . ' 有効'
  ],
  [
    'title' => '発行済クーポン照会',
    'desc'  => 'ユーザーIDで検索',
    'href'  => $BASE . 'coupons_issued.php',
    'badge' => $stats['coupons_issued']
  ],
  [
    'title' => 'スタンプマスタ',
    'desc'  => 'スタンプ種類/色/ラベル',
    'href'  => $BASE . 'stamps_master.php',
    'badge' => $stats['stamps_master']
  ],
  [
    'title' => 'スタンプ照会',
    'desc'  => 'ユーザーごとの台帳を確認',
    'href'  => $BASE . 'stamps_issued.php',
    'badge' => $stats['stamps_issued']
  ],
  [
    'title' => '会員マスタ',
    'desc'  => '会員情報・所属店舗・カード種別',
    'href'  => $BASE . 'members.php',
    'badge' => $stats['members']
  ],
  [
    'title' => 'メニューマスタ',
    'desc'  => '価格/表示/画像の管理',
    'href'  => $BASE . 'menus.php',
    'badge' => $stats['menus_visible'] . ' 表示中'
  ],
  [
    'title' => '操作ログ',
    'desc'  => '監査ログを確認',
    'href'  => $BASE . 'logs.php',
    'badge' => count($op_logs)
  ],
];

?>
<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<title>CMSダッシュボード</title>
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
  .cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:14px;margin:18px 0 26px}
  .card{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:14px;display:flex;flex-direction:column;gap:10px}
  .card h3{font-size:1rem;margin:0}
  .card p{font-size:.9rem;color:var(--muted);margin:0}
  .card a{display:inline-flex;align-items:center;gap:6px;text-decoration:none;background:var(--brand);color:#fff;padding:.5rem .7rem;border-radius:10px;margin-top:6px}
  .badge{display:inline-block;background:#eef2ff;color:#3730a3;border:1px solid #c7d2fe;border-radius:999px;padding:.15rem .5rem;font-size:.78rem}
  section{background:var(--card);border:1px solid var(--line);border-radius:14px;margin-bottom:16px}
  section header{display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--line);padding:10px 14px;background:transparent}
  section h2{font-size:1rem;margin:0}
  table{width:100%;border-collapse:collapse}
  th,td{padding:10px 12px;border-top:1px solid var(--line);text-align:left;font-size:.92rem}
  th{background:#fafafa}
  .muted{color:var(--muted)}
  footer{margin:28px 0;color:var(--muted);text-align:center;font-size:.9rem}
  @media (hover:hover){
    .card:hover{box-shadow:0 8px 28px rgba(0,0,0,.06);transform:translateY(-1px);transition:.2s}
  }
</style>
</head>
<body>
  <header>
    <div class="brand">ポイントアプリ 管理ダッシュボード</div>
    <div style="display:flex;align-items:center;gap:12px;">
      <div class="user">こんにちは、<?= j($admin['username']) ?> さん</div>
      <a class="logout" href="<?= j($BASE.'logout.php') ?>">ログアウト</a>
    </div>
  </header>

  <main>
    <h1>概要</h1>
    <p class="sub">主要マスタの件数と機能メニューです。各カードから管理画面へ移動できます。</p>

    <div class="cards">
      <?php foreach($menus as $m): ?>
        <div class="card">
          <h3><?= j($m['title']) ?> <span class="badge"><?= j((string)$m['badge']) ?></span></h3>
          <p><?= j($m['desc']) ?></p>
          <div>
            <a href="<?= j($m['href']) ?>">開く</a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <section>
      <header><h2>最近の操作ログ（最新10件）</h2></header>
      <div style="overflow:auto">
        <table>
          <tr><th>日時</th><th>ユーザー</th><th>アクション</th><th>エンティティ</th><th>ID</th></tr>
          <?php if(empty($op_logs)): ?>
            <tr><td colspan="5" class="muted">ログはまだありません。</td></tr>
          <?php else: ?>
            <?php foreach($op_logs as $r): ?>
              <tr>
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
    </section>

    <section>
      <header><h2>最近のログイン履歴（最新8件）</h2></header>
      <div style="overflow:auto">
        <table>
          <tr><th>日時</th><th>ユーザー名</th><th>結果</th><th>IP</th></tr>
          <?php if(empty($login_logs)): ?>
            <tr><td colspan="4" class="muted">履歴はまだありません。</td></tr>
          <?php else: ?>
            <?php foreach($login_logs as $r): ?>
              <tr>
                <td><?= j($r['created_at']) ?></td>
                <td><?= j($r['username'] ?? '') ?></td>
                <td><?= ($r['success'] ? '成功' : '失敗') ?></td>
                <td class="muted"><?= j($r['ip_address']) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </table>
      </div>
    </section>

    <footer>© <?= date('Y') ?> Point CMS</footer>
  </main>
</body>
</html>
