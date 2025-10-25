<?php
// menus.php - メニューマスタ CRUD（店舗選択対応・保存先ランダム化・戻るボタン・白画面防止ログ）

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/helpers.php';
require_login();

$admin = current_admin();
$pdo   = db();
$BASE  = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/'; // /pointcard/admin/public/
$APP   = rtrim(dirname($BASE), '/\\') . '/';                   // /pointcard/admin/
$UPLOAD_URL_BASE = $APP . 'uploads';                           // /pointcard/admin/uploads

// --- デバッグログ ---
$LOG_DIR  = __DIR__ . '/../runtime';
$LOG_FILE = $LOG_DIR . '/menus_debug.log';
if (!is_dir($LOG_DIR)) { @mkdir($LOG_DIR, 0775, true); }
function mlog($msg){ global $LOG_FILE; @file_put_contents($LOG_FILE, '['.date('c')."] $msg\n", FILE_APPEND); }

// フォールバック
if (!function_exists('validate_zenkaku_len')) {
  function validate_zenkaku_len($s, $max){ return mb_strlen($s, 'UTF-8') <= $max; }
}

// ===== 画像関連（非推測化 保存ロジック） =====
function ensure_uploads_guard() {
  // ディレクトリ一覧抑止（Apache想定）: .htaccess が無ければ作る
  $ht = UPLOAD_DIR.'/.htaccess';
  if (!is_file($ht)) {
    @file_put_contents($ht, "Options -Indexes\n");
  }
}
function new_random_subdir(): string {
  // 年/月配下に 32hex のランダムディレクトリを切る（menu_idは含めない）
  $token = bin2hex(random_bytes(16));          // 32 hex
  return 'menus/'.date('Y/m').'/'.$token;      // 例) menus/2025/10/3f1a.../
}
function validate_image($file, &$mime, &$w, &$h, &$err) {
  if (empty($file['tmp_name'])) { $err = '画像ファイルを選択してください。'; return false; }
  if ($file['size'] > MAX_UPLOAD_BYTES) { $err = '画像が大きすぎます。'; return false; }
  $info = @getimagesize($file['tmp_name']);
  if (!$info) { $err = '画像ではありません。'; return false; }
  $mime = $info['mime'] ?? '';
  if (!in_array($mime, ['image/jpeg', 'image/png'], true)) { $err = 'JPEG/PNG のみアップロード可能です。'; return false; }
  $w = (int)$info[0]; $h = (int)$info[1];
  return true;
}
/**
 * 画像保存：ランダムサブディレクトリに {kind}.{ext} で保存し、そのURLを返す。
 * $old_path があれば古いファイルを削除して置き換える。
 */
function save_image_random($kind, $file, $mime, $old_path = null) {
  ensure_uploads_guard();

  $subdir = new_random_subdir();                                // 非推測化
  $dir    = UPLOAD_DIR . '/' . $subdir;
  if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
  $ext = ($mime === 'image/png') ? '.png' : '.jpg';
  $fs  = $dir . '/' . $kind . $ext;                             // 例) .../menus/2025/10/<token>/thumb.jpg
  if (!@move_uploaded_file($file['tmp_name'], $fs)) {
    return [false, null, 'ファイル保存に失敗しました。'];
  }

  // 古いファイルを削除（パスが uploads/ 配下なら削除）
  if ($old_path) {
    $rel = preg_replace('~^.*/uploads/~','', $old_path);
    if ($rel && strpos($rel, '..') === false) {
      @unlink(UPLOAD_DIR . '/' . $rel);
    }
  }

  $url = $GLOBALS['UPLOAD_URL_BASE'] . '/' . $subdir . '/' . $kind . $ext;
  return [true, $url, null];
}

// ===== 画面用変数 =====
$err = ''; $msg = '';

// 店舗一覧＆フィルタ
try {
  $stores = $pdo->query('SELECT store_id, name FROM stores WHERE is_deleted=0 ORDER BY store_id')->fetchAll();
} catch (Throwable $e) {
  $stores = [];
  mlog('STORES EX: '.$e->getMessage());
}
$filter_store = isset($_GET['store_id']) && $_GET['store_id'] !== '' ? (int)$_GET['store_id'] : null;

// 表示トグル
if (isset($_GET['toggle']) && ctype_digit($_GET['toggle'])) {
  try {
    $tid = (int)$_GET['toggle'];
    $pdo->prepare('UPDATE menus SET is_visible = 1 - is_visible WHERE menu_id=?')->execute([$tid]);
    op_log('update','menus',$tid,['toggle_visible'=>true]);
    header('Location: '.$BASE.'menus.php'.($filter_store?'?store_id='.$filter_store:'')); exit;
  } catch (Throwable $e) {
    mlog('TOGGLE EX: '.$e->getMessage()); $err = '内部エラー（M-TGL）。';
  }
}

// 画像削除
if (isset($_GET['delimg'])) {
  try {
    [$mid, $kind] = explode(':', $_GET['delimg'].':');
    $mid = (int)$mid; $kind = $kind === 'detail' ? 'detail' : 'thumb';
    $st = $pdo->prepare('SELECT thumb_path, detail_path FROM menus WHERE menu_id=?');
    $st->execute([$mid]);
    if ($row = $st->fetch()) {
      $col = ($kind === 'thumb') ? 'thumb_path' : 'detail_path';
      if (!empty($row[$col])) {
        $rel = preg_replace('~^.*/uploads/~','',$row[$col]);
        if ($rel && strpos($rel, '..') === false) @unlink(UPLOAD_DIR . '/' . $rel);
        $pdo->prepare("UPDATE menus SET $col=NULL WHERE menu_id=?")->execute([$mid]);
        op_log('update','menus',$mid,["delete_$col"=>true]);
      }
    }
    header('Location: '.$BASE.'menus.php?edit='.$mid.($filter_store?'&store_id='.$filter_store:'')); exit;
  } catch (Throwable $e) {
    mlog('DELIMG EX: '.$e->getMessage()); $err = '内部エラー（M-DELIMG）。';
  }
}

// 保存（新規/更新）— store_id 必須
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    $menu_id = isset($_POST['menu_id']) && $_POST['menu_id'] !== '' ? (int)$_POST['menu_id'] : null;
    $store_id= (int)($_POST['store_id'] ?? 0);
    $order   = (int)($_POST['display_order'] ?? 0);
    $name    = trim($_POST['name'] ?? '');
    $comment = trim($_POST['comment'] ?? '');
    $price   = trim($_POST['price_tax_incl'] ?? '');
    $visible = (int)($_POST['is_visible'] ?? 1);

    if (!$store_id) $err = '店舗を選択してください。';
    if (!$err && ($name === '' || $comment === '' || $price === '')) $err = '必須項目が未入力です。';
    if (!$err && !validate_zenkaku_len($name, 10)) $err = 'メニュー名は全角10文字以内にしてください。';
    if (!$err && !validate_zenkaku_len($comment, 10)) $err = 'メニューコメントは全角10文字以内にしてください。';
    if (!$err && !preg_match('/^\d+(?:\.\d{1,2})?$/', $price)) $err = '税込価格は数値で入力してください。';

    if ($err === '') {
      if ($menu_id) {
        $pdo->prepare('UPDATE menus
                       SET store_id=?, display_order=?, name=?, comment=?, price_tax_incl=?, is_visible=?
                       WHERE menu_id=?')
            ->execute([$store_id, $order, $name, $comment, $price, $visible, $menu_id]);
        op_log('update','menus',$menu_id,['name'=>$name]);
        $msg = '更新しました。';
      } else {
        $pdo->prepare('INSERT INTO menus
                       (store_id, display_order, name, comment, price_tax_incl, is_visible)
                       VALUES (?,?,?,?,?,?)')
            ->execute([$store_id, $order, $name, $comment, $price, $visible]);
        $menu_id = (int)$pdo->lastInsertId();
        op_log('create','menus',$menu_id,['name'=>$name]);
        $msg = '登録しました。';
      }

      // 現在の保存済みパス（上書き時の削除に使う）
      $old = ['thumb_path'=>null,'detail_path'=>null];
      $stOld = $pdo->prepare('SELECT thumb_path, detail_path FROM menus WHERE menu_id=?');
      $stOld->execute([$menu_id]); $oldRow = $stOld->fetch();
      if ($oldRow) { $old['thumb_path'] = $oldRow['thumb_path']; $old['detail_path'] = $oldRow['detail_path']; }

      // 画像アップロード（任意）— ランダムなサブディレクトリに保存
      if (!empty($_FILES['thumb']['tmp_name'])) {
        $mime=''; $w=0; $h=0;
        if (validate_image($_FILES['thumb'],$mime,$w,$h,$err)) {
          [$ok,$url,$e] = save_image_random('thumb',$_FILES['thumb'],$mime,$old['thumb_path']);
          if ($ok) $pdo->prepare('UPDATE menus SET thumb_path=? WHERE menu_id=?')->execute([$url,$menu_id]);
          else { $err = $e; mlog('SAVE THUMB ERR: '.$e); }
        }
      }
      if ($err==='') if (!empty($_FILES['detail']['tmp_name'])) {
        $mime=''; $w=0; $h=0;
        if (validate_image($_FILES['detail'],$mime,$w,$h,$err)) {
          [$ok,$url,$e] = save_image_random('detail',$_FILES['detail'],$mime,$old['detail_path']);
          if ($ok) $pdo->prepare('UPDATE menus SET detail_path=? WHERE menu_id=?')->execute([$url,$menu_id]);
          else { $err = $e; mlog('SAVE DETAIL ERR: '.$e); }
        }
      }

      // 保存後は現在フィルタを維持
      header('Location: '.$BASE.'menus.php'.($store_id?('?store_id='.$store_id):'')); exit;
    }
  } catch (Throwable $e) {
    mlog('POST EX: '.$e->getMessage());
    $err = '内部エラー（M-SAVE）';
  }
}

// 編集対象
$edit = null;
if (isset($_GET['edit']) && ctype_digit($_GET['edit'])) {
  try {
    $eid = (int)$_GET['edit'];
    $st = $pdo->prepare('SELECT * FROM menus WHERE menu_id=?');
    $st->execute([$eid]);
    $edit = $st->fetch();
    if ($edit && !$filter_store) $filter_store = (int)$edit['store_id'];
  } catch (Throwable $e) {
    mlog('EDIT EX: '.$e->getMessage()); $err = '内部エラー（M-EDIT）。';
  }
}

// 一覧取得（store_idで任意フィルタ）
$rows = []; $total = 0;
try {
  if ($filter_store) {
    $st = $pdo->prepare('SELECT m.*, s.name AS store_name
                         FROM menus m JOIN stores s ON s.store_id=m.store_id
                         WHERE m.store_id=? ORDER BY m.display_order ASC, m.menu_id ASC');
    $st->execute([$filter_store]); $rows = $st->fetchAll();
  } else {
    $rows = $pdo->query('SELECT m.*, s.name AS store_name
                         FROM menus m JOIN stores s ON s.store_id=m.store_id
                         ORDER BY m.store_id ASC, m.display_order ASC, m.menu_id ASC')->fetchAll();
  }
  $total = count($rows);
} catch (Throwable $e) {
  mlog('LIST EX: '.$e->getMessage());
  $err = $err ?: 'メニューテーブルの列が不足している可能性があります。';
}
?>
<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<title>メニューマスタ | 管理ダッシュボード</title>
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
  .grid{display:grid;grid-template-columns:1fr 1.5fr;gap:16px}
  .card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:14px}
  .card h2{font-size:1rem;margin:0 0 10px}
  label{display:block;margin:.6rem 0 .2rem;font-weight:600}
  input[type="text"], input[type="number"], input[type="file"], select{width:100%;padding:.6rem;border:1px solid #d1d5db;border-radius:10px;font-size:1rem}
  .row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
  .btns{display:flex;gap:8px;margin-top:12px;flex-wrap:wrap}
  .btn{display:inline-flex;align-items:center;justify-content:center;padding:.6rem .9rem;border-radius:10px;border:1px solid var(--line);text-decoration:none;cursor:pointer}
  .btn-primary{background:var(--brand);color:#fff;border-color:transparent}
  .btn-ghost{background:#fff;color:#111827}
  table{width:100%;border-collapse:collapse}
  th,td{padding:10px 12px;border-top:1px solid var(--line);text-align:left;font-size:.92rem;vertical-align:top}
  th{background:#fafafa}
  .muted{color:var(--muted)}
  .preview img{display:block;border:1px solid var(--line);border-radius:8px}
  @media (max-width: 1024px){ .grid{grid-template-columns:1fr} }
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
    <h1>メニューマスタ</h1>
    <p class="sub">店舗ごとのメニューを管理します。全 <?= j((string)$total) ?> 件（<?= $filter_store ? '店舗ID: '.j((string)$filter_store) : '全店舗' ?>）。</p>

    <?php if ($msg): ?><div style="background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46;padding:.6rem .8rem;border-radius:10px;margin:10px 0"><?= j($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div style="background:#fee2e2;border:1px solid #fecaca;color:#7f1d1d;padding:.6rem .8rem;border-radius:10px;margin:10px 0"><?= j($err) ?></div><?php endif; ?>

    <div class="grid">
      <!-- 入力フォーム -->
      <div class="card">
        <h2><?= $edit ? '編集: ID '.j($edit['menu_id']) : '新規作成' ?></h2>
        <form method="post" enctype="multipart/form-data" autocomplete="off">
          <input type="hidden" name="menu_id" value="<?= j($edit['menu_id'] ?? '') ?>">

          <label>店舗</label>
          <select name="store_id" required>
            <option value="">選択してください</option>
            <?php
              $sel_store = $edit['store_id'] ?? ($filter_store ?? '');
              foreach($stores as $s){
                $sel = ((string)$sel_store === (string)$s['store_id']) ? 'selected' : '';
                echo '<option value="',j($s['store_id']),'" ', $sel,'>',j($s['store_id'].' : '.$s['name']),'</option>';
              }
            ?>
          </select>

          <div class="row">
            <div>
              <label>表示順（数値）</label>
              <input type="number" name="display_order" value="<?= j($edit['display_order'] ?? 0) ?>" required>
            </div>
            <div>
              <label>表示フラグ</label>
              <select name="is_visible">
                <?php $vis = isset($edit)? (int)$edit['is_visible'] : 1; ?>
                <option value="1" <?= $vis===1?'selected':''; ?>>1：表示する</option>
                <option value="0" <?= $vis===0?'selected':''; ?>>0：表示しない</option>
              </select>
            </div>
          </div>

          <label>メニュー名（全角10）</label>
          <input type="text" name="name" value="<?= j($edit['name'] ?? '') ?>" required>

          <label>メニューコメント（全角10）</label>
          <input type="text" name="comment" value="<?= j($edit['comment'] ?? '') ?>" required>

          <label>税込み価格（例：980 / 980.00）</label>
          <input type="text" name="price_tax_incl" value="<?= j($edit['price_tax_incl'] ?? '') ?>" required>

          <div class="row">
            <div>
              <label>サムネ画像（任意／JPEG/PNG）</label>
              <input type="file" name="thumb" accept="image/jpeg,image/png">
              <?php if (!empty($edit['thumb_path'])): ?>
                <div class="preview" style="margin-top:6px">
                  <img src="<?= j($edit['thumb_path']) ?>" width="160" alt="thumb">
                  <a class="btn" href="<?= j($BASE.'menus.php?delimg='.$edit['menu_id'].':thumb'.($filter_store?'&store_id='.$filter_store:'')) ?>" onclick="return confirm('サムネ画像を削除しますか？')">削除</a>
                </div>
              <?php endif; ?>
            </div>
            <div>
              <label>詳細画像（任意／JPEG/PNG）</label>
              <input type="file" name="detail" accept="image/jpeg,image/png">
              <?php if (!empty($edit['detail_path'])): ?>
                <div class="preview" style="margin-top:6px">
                  <img src="<?= j($edit['detail_path']) ?>" width="160" alt="detail">
                  <a class="btn" href="<?= j($BASE.'menus.php?delimg='.$edit['menu_id'].':detail'.($filter_store?'&store_id='.$filter_store:'')) ?>" onclick="return confirm('詳細画像を削除しますか？')">削除</a>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <div class="btns">
            <button class="btn btn-primary" type="submit"><?= $edit ? '更新する' : '登録する' ?></button>
            <?php if ($edit): ?><a class="btn" href="<?= j($BASE.'menus.php'.($filter_store?'?store_id='.$filter_store:'')) ?>">新規作成に戻る</a><?php endif; ?>
            <a class="btn btn-ghost" href="<?= j($BASE.'index.php') ?>">← ダッシュボードへ戻る</a>
          </div>
        </form>
      </div>

      <!-- 一覧 -->
      <div class="card">
        <h2>メニュー一覧</h2>
        <form method="get" style="margin-bottom:10px">
          <label>店舗で絞り込み</label>
          <div style="display:grid;grid-template-columns:1fr auto;gap:8px">
            <select name="store_id">
              <option value="">全店舗</option>
              <?php foreach($stores as $s): ?>
                <option value="<?= j($s['store_id']) ?>" <?= ($filter_store && (int)$filter_store===(int)$s['store_id'])?'selected':'' ?>>
                  <?= j($s['store_id'].' : '.$s['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <button class="btn" type="submit">適用</button>
          </div>
        </form>

        <div style="overflow:auto
