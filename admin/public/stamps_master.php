<?php
// stamps_master.php - スタンプマスタ CRUD（統一UI＋戻るボタン）

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/helpers.php';
require_login();

$admin = current_admin();
$pdo   = db();
$BASE  = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';

$err = ''; $msg = '';

// 追加・更新
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stamp_id = isset($_POST['stamp_id']) && $_POST['stamp_id'] !== '' ? (int)$_POST['stamp_id'] : null;
    $kind     = strtoupper(trim($_POST['kind'] ?? 'NORMAL'));
    $color    = strtoupper(trim($_POST['color_hex'] ?? ''));
    $label    = trim($_POST['label'] ?? '');

    if (!in_array($kind, ['NORMAL','GAME'], true)) $err = 'スタンプ種類を選択してください。';
    if (!$err && !preg_match('/^#[0-9A-F]{6}$/i', $color)) $err = '色は #RRGGBB 形式で入力してください。';
    if (!$err && $label === '') $err = 'スタンプ文字を入力してください。';
    if (!$err && mb_strlen($label) > 16) $err = 'スタンプ文字は16文字以内にしてください。';

    if ($err === '') {
        if ($stamp_id) {
            $pdo->prepare('UPDATE stamps_master SET kind=?, color_hex=?, label=? WHERE stamp_id=?')
                ->execute([$kind, $color, $label, $stamp_id]);
            op_log('update','stamps_master',$stamp_id,['label'=>$label]);
            $msg = '更新しました。';
        } else {
            $pdo->prepare('INSERT INTO stamps_master (kind, color_hex, label) VALUES (?,?,?)')
                ->execute([$kind, $color, $label]);
            $newId = $pdo->lastInsertId();
            op_log('create','stamps_master',$newId,['label'=>$label]);
            $msg = '登録しました。';
        }
    }
}

// 削除（参照があると制約で失敗）
if (isset($_GET['del']) && $_GET['del'] !== '') {
    $del_id = (int)$_GET['del'];
    try {
        $pdo->prepare('DELETE FROM stamps_master WHERE stamp_id=?')->execute([$del_id]);
        op_log('delete','stamps_master',$del_id,null);
        header('Location: '.$BASE.'stamps_master.php');
        exit;
    } catch (Throwable $e) {
        $err = 'このスタンプは使用履歴があり削除できません。';
    }
}

// 編集対象
$edit = null;
if (isset($_GET['edit']) && $_GET['edit'] !== '') {
    $eid = (int)$_GET['edit'];
    $st  = $pdo->prepare('SELECT * FROM stamps_master WHERE stamp_id=?');
    $st->execute([$eid]);
    $edit = $st->fetch();
}

$rows = $pdo->query('SELECT * FROM stamps_master ORDER BY stamp_id DESC')->fetchAll();
$total = count($rows);
?>
<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<title>スタンプマスタ | 管理ダッシュボード</title>
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
  input[type="text"], select, input[type="color"]{width:100%;padding:.6rem;border:1px solid #d1d5db;border-radius:10px;font-size:1rem}
  .row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
  .btns{display:flex;gap:8px;margin-top:12px}
  .btn{display:inline-flex;align-items:center;justify-content:center;padding:.6rem .9rem;border-radius:10px;border:1px solid var(--line);text-decoration:none;cursor:pointer}
  .btn-primary{background:var(--brand);color:#fff;border-color:transparent}
  .btn-ghost{background:#fff;color:#111827}
  .btn-danger{background:#fee2e2;border-color:#fecaca;color:#991b1b}
  table{width:100%;border-collapse:collapse}
  th,td{padding:10px 12px;border-top:1px solid var(--line);text-align:left;font-size:.92rem}
  th{background:#fafafa}
  .muted{color:var(--muted)}
  .swatch{display:inline-block;width:20px;height:20px;border-radius:6px;border:1px solid #ccc;vertical-align:middle;margin-right:6px}
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
    <h1>スタンプマスタ</h1>
    <p class="sub">スタンプ種類（通常/ゲーム）、色（#RRGGBB）、スタンプ文字を管理します。現在：<?= j((string)$total) ?>件</p>

    <?php if ($msg): ?><div style="background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46;padding:.6rem .8rem;border-radius:10px;margin:10px 0"><?= j($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div style="background:#fee2e2;border:1px solid #fecaca;color:#7f1d1d;padding:.6rem .8rem;border-radius:10px;margin:10px 0"><?= j($err) ?></div><?php endif; ?>

    <div class="grid">
      <div class="card">
        <h2><?= $edit ? ('編集: ID '.j($edit['stamp_id'])) : '新規作成' ?></h2>
        <form method="post" autocomplete="off">
          <input type="hidden" name="stamp_id" value="<?= j($edit['stamp_id'] ?? '') ?>">

          <label>スタンプ種類</label>
          <select name="kind" required>
            <?php $k = $edit['kind'] ?? 'NORMAL'; ?>
            <option value="NORMAL" <?= $k==='NORMAL'?'selected':'' ?>>通常スタンプ</option>
            <option value="GAME"   <?= $k==='GAME'?'selected':'' ?>>ゲームスタンプ</option>
          </select>

          <div class="row">
            <div>
              <label>スタンプ色</label>
              <input type="color" name="color_hex" value="<?= j($edit['color_hex'] ?? '#FF0000') ?>">
            </div>
            <div>
              <label>スタンプ文字（16文字以内）</label>
              <input type="text" name="label" value="<?= j($edit['label'] ?? '') ?>" maxlength="16" required>
            </div>
          </div>

          <div class="btns">
            <button class="btn btn-primary" type="submit"><?= $edit ? '更新する' : '登録する' ?></button>
            <?php if ($edit): ?><a class="btn" href="<?= j($BASE.'stamps_master.php') ?>">新規作成に戻る</a><?php endif; ?>
            <a class="btn btn-ghost" href="<?= j($BASE.'index.php') ?>">← ダッシュボードへ戻る</a>
          </div>
        </form>
      </div>

      <div class="card">
        <h2>スタンプ一覧</h2>
        <div style="overflow:auto">
          <table>
            <tr><th>ID</th><th>種類</th><th>色</th><th>スタンプ文字</th><th>作成日時</th><th>操作</th></tr>
            <?php if (empty($rows)): ?>
              <tr><td colspan="6" class="muted">スタンプはまだ登録されていません。</td></tr>
            <?php else: ?>
              <?php foreach($rows as $r): ?>
                <tr>
                  <td><?= j($r['stamp_id']) ?></td>
                  <td><?= $r['kind']==='NORMAL'?'通常':'ゲーム' ?></td>
                  <td><span class="swatch" style="background:<?= j($r['color_hex']) ?>"></span><?= j($r['color_hex']) ?></td>
                  <td><?= j($r['label']) ?></td>
                  <td><?= j($r['created_at']) ?></td>
                  <td style="white-space:nowrap">
                    <a class="btn" href="<?= j($BASE.'stamps_master.php?edit='.$r['stamp_id']) ?>">編集</a>
                    <a class="btn btn-danger" href="<?= j($BASE.'stamps_master.php?del='.$r['stamp_id']) ?>"
                       onclick="return confirm('ID <?= j($r['stamp_id']) ?> を削除しますか？（発行履歴がある場合は削除できません）')">削除</a>
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
