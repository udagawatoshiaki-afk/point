<?php
// _migrate_menus_v3.php - menus の挿入エラーを解消するための最終補正
// 1) created_at / updated_at を適正な DEFAULT へ
// 2) アプリが値を入れないのに NOT NULL & DEFAULTなしの列を NULL化
// 実行後は必ず削除してください。

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/auth.php';
require_login();
header('Content-Type: text/plain; charset=UTF-8');

$pdo = db();

function say($m){ echo $m, "\n"; }
function col(PDO $pdo,$t,$c){
  $db=$pdo->query('SELECT DATABASE()')->fetchColumn();
  $st=$pdo->prepare('SELECT COLUMN_NAME, IS_NULLABLE, COLUMN_DEFAULT, COLUMN_TYPE, EXTRA, DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?');
  $st->execute([$db,$t,$c]); return $st->fetch();
}
function columns(PDO $pdo,$t){
  $db=$pdo->query('SELECT DATABASE()')->fetchColumn();
  $st=$pdo->prepare('SELECT COLUMN_NAME, IS_NULLABLE, COLUMN_DEFAULT, COLUMN_TYPE, EXTRA, DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? ORDER BY ORDINAL_POSITION');
  $st->execute([$db,$t]); return $st->fetchAll();
}

try {
  // 1) created_at / updated_at を安全な定義に
  $c = col($pdo,'menus','created_at');
  if ($c) {
    // DEFAULTがない/NULL許容などバラつきを揃える
    say('ALTER created_at -> DEFAULT CURRENT_TIMESTAMP');
    $pdo->exec('ALTER TABLE menus MODIFY created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');
  } else {
    say('ADD created_at');
    $pdo->exec('ALTER TABLE menus ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');
  }

  $u = col($pdo,'menus','updated_at');
  if ($u) {
    say('ALTER updated_at -> DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
    $pdo->exec('ALTER TABLE menus MODIFY updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
  } else {
    say('ADD updated_at');
    $pdo->exec('ALTER TABLE menus ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
  }

  // 2) アプリが直接セットしない列で、NOT NULL & DEFAULTなし は挿入エラー要因 → NULL許容へ
  //   アプリで値を入れる可能性がある列は除外
  $app_cols = [
    'menu_id','store_id','display_order','name','comment',
    'thumb_path','detail_path','price_tax_incl','is_visible',
    'created_at','updated_at'
  ];

  foreach (columns($pdo,'menus') as $r) {
    $cn = $r['COLUMN_NAME'];
    if (in_array($cn, $app_cols, true)) continue;
    $nullable = strtoupper($r['IS_NULLABLE']) === 'YES';
    $hasDefault = !is_null($r['COLUMN_DEFAULT']);
    $auto = stripos($r['EXTRA'] ?? '', 'auto_increment') !== false;

    if (!$nullable && !$hasDefault && !$auto) {
      $ctype = $r['COLUMN_TYPE']; // 例: varchar(255), int(10) unsigned, text など
      say("RELAX NOT NULL -> NULL: $cn $ctype");
      $pdo->exec("ALTER TABLE menus MODIFY `$cn` $ctype NULL");
    }
  }

  $cnt = (int)$pdo->query('SELECT COUNT(*) FROM menus')->fetchColumn();
  say("DONE. rows in menus: $cnt");
} catch (Throwable $e) {
  http_response_code(500);
  echo "ERROR: ".$e->getMessage()."\n";
}
