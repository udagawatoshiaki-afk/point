<?php
// slider.php - スライダー画像管理（左側の店舗選択に連動／右側は一覧＋D&D並べ替え／非推測パスで保存）
// 制約対応: CHECK(width=1600 & height=900), CHECK(position 1..5), UNIQUE(store_id, position), FK(store_id→stores)

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/helpers.php';
require_login();

$admin = current_admin();
$pdo   = db();

$BASE  = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/'; // /pointcard/admin/public/
$APP   = rtrim(dirname($BASE), '/\\') . '/';                   // /pointcard/admin/
$UPLOAD_URL_BASE = $APP . 'uploads';

$LOG_DIR  = __DIR__ . '/../runtime';
$LOG_FILE = $LOG_DIR . '/slider_debug.log';
if (!is_dir($LOG_DIR)) { @mkdir($LOG_DIR, 0775, true); }
function slog($msg){ global $LOG_FILE; @file_put_contents($LOG_FILE,'['.date('c')."] $msg\n", FILE_APPEND); }

// ========= ユーティリティ =========
function hasColumn(PDO $pdo, $table, $col){
  $db = $pdo->query('SELECT DATABASE()')->fetchColumn();
  $st = $pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?');
  $st->execute([$db,$table,$col]); return (bool)$st->fetch();
}
function pickCol(PDO $pdo, $table, array $candidates, $required=false){
  foreach ($candidates as $c) if (hasColumn($pdo,$table,$c)) return $c;
  if ($required) throw new RuntimeException("Missing required column in $table: ".implode('|',$candidates));
  return null;
}
function getColumns(PDO $pdo, $table){
  $db = $pdo->query('SELECT DATABASE()')->fetchColumn();
  $st = $pdo->prepare('SELECT COLUMN_NAME, IS_NULLABLE, COLUMN_DEFAULT, DATA_TYPE, COLUMN_TYPE, EXTRA
                       FROM information_schema.COLUMNS
                       WHERE TABLE_SCHEMA=? AND TABLE_NAME=?
                       ORDER BY ORDINAL_POSITION');
  $st->execute([$db,$table]);
  $cols = [];
  foreach ($st->fetchAll() as $r) {
    $r['IS_NULLABLE'] = strtoupper($r['IS_NULLABLE']) === 'YES';
    $cols[$r['COLUMN_NAME']] = $r;
  }
  return $cols;
}
function enumFirstValue($column_type){
  if (preg_match("/^enum\\((.+)\\)$/i", $column_type, $m)) {
    if (preg_match("/'((?:[^'\\\\]|\\\\.)*)'/", $m[1], $m2)) return stripcslashes($m2[1]);
  }
  return '';
}
function defaultByType($data_type, $column_type){
  $t = strtolower($data_type);
  if (in_array($t, ['tinyint','smallint','mediumint','int','bigint','decimal','numeric','float','double','real'])) return 0;
  if ($t === 'date') return date('Y-m-d');
  if ($t === 'time') return date('H:i:s');
  if ($t === 'datetime' || $t === 'timestamp') return date('Y-m-d H:i:s');
  if ($t === 'enum') return enumFirstValue($column_type);
  return ''; // 文字列系は空文字
}
function ensure_uploads_guard(){
  $ht = UPLOAD_DIR.'/.htaccess';
  if (!is_file($ht)) @file_put_contents($ht,"Options -Indexes\n");
}
function new_random_subdir(){ return 'sliders/'.date('Y/m').'/'.bin2hex(random_bytes(16)); } // 非推測化
function vimg($file,&$mime,&$w,&$h,&$err){
  if (empty($file['tmp_name'])){ $err='画像ファイルを選択してください。'; return false; }
  if ($file['size'] > MAX_UPLOAD_BYTES){ $err='画像が大きすぎます。'; return false; }
  $info = @getimagesize($file['tmp_name']);
  if (!$info){ $err='画像ではありません。'; return false; }
  $mime = $info['mime'] ?? '';
  if (!in_array($mime,['image/jpeg','image/png'],true)){ $err='JPEG/PNG のみアップロード可能です。'; return false; }
  $w = (int)$info[0]; $h = (int)$info[1]; return true;
}
function save_image_random($kind,$file,$mime,$old_path=null){
  ensure_uploads_guard();
  $sub = new_random_subdir();
  $dir = UPLOAD_DIR.'/'.$sub;
  if (!is_dir($dir)) @mkdir($dir,0775,true);
  $ext = ($mime==='image/png')?'.png':'.jpg';
  $fs  = $dir.'/'.$kind.$ext;
  if (!@move_uploaded_file($file['tmp_name'],$fs)) return [false,null,'ファイル保存に失敗しました。'];
  if ($old_path){
    $rel = preg_replace('~^.*/uploads/~','',$old_path);
    if ($rel && strpos($rel,'..')===false) @unlink(UPLOAD_DIR.'/'.$rel);
  }
  $url = $GLOBALS['UPLOAD_URL_BASE'].'/'.$sub.'/'.$kind.$ext;
  return [true,$url,null];
}

// === 並べ替え用：毎回新規の「一時店舗」を作る（FK対策で実在IDを確保）
function create_temp_store(PDO $pdo){
  $storesTable = 'stores';
  $idCol   = pickCol($pdo, $storesTable, ['store_id','id'], true);
  $nameCol = pickCol($pdo, $storesTable, ['name','store_name','title'], false);
  $delCol  = pickCol($pdo, $storesTable, ['is_deleted','deleted','is_active'], false);

  $tmpName = '__SLIDER_TMP__'.bin2hex(random_bytes(6));

  $colsMeta = getColumns($pdo, $storesTable);
  $cols = []; $vals = []; $params = [];
  foreach($colsMeta as $cn=>$m){
    $auto = stripos($m['EXTRA'] ?? '', 'auto_increment') !== false;
    if ($auto) continue;
    if ($nameCol && $cn===$nameCol){ $cols[]=$cn; $vals[]='?'; $params[]=$tmpName; continue; }
    if ($delCol  && $cn===$delCol ){ $cols[]=$cn; $vals[]='?'; $params[]=1;       continue; }
    $isNN = !$m['IS_NULLABLE']; $hasDefault = !is_null($m['COLUMN_DEFAULT']);
    if ($isNN && !$hasDefault){ $cols[]=$cn; $vals[]='?'; $params[]=defaultByType($m['DATA_TYPE'],$m['COLUMN_TYPE']); }
  }
  $sql = "INSERT INTO `$storesTable` (".implode(',',array_map(fn($c)=>"`$c`",$cols)).") VALUES (".implode(',',$vals).")";
  $pdo->prepare($sql)->execute($params);
  return (int)$pdo->lastInsertId();
}
// === 一時店舗を空なら削除
function cleanup_temp_store(PDO $pdo, int $tmpStoreId){
  try{
    $has = (int)$pdo->query("SELECT COUNT(*) FROM slider_images WHERE store_id = ".(int)$tmpStoreId)->fetchColumn();
    if ($has===0){
      $idCol = pickCol($pdo,'stores',['store_id','id'],true);
      $pdo->prepare("DELETE FROM stores WHERE `$idCol` = ?")->execute([$tmpStoreId]);
    }
  }catch(Throwable $_){ /* 後片付けなので無視 */ }
}

// ========= スキーマ検出 =========
$table = 'slider_images';
try {
  $idCol     = pickCol($pdo,$table,['image_id','slider_image_id','slider_id','id'], true);
  $pathCol   = pickCol($pdo,$table,['image_path','path','file_path','url'],       true);
  $storeCol  = pickCol($pdo,$table,['store_id'], false);
  $posCol    = pickCol($pdo,$table,['position','pos'], false);
  $orderCol  = pickCol($pdo,$table,['sort_order','display_order','order_no','ord'], false);
  $wCol      = pickCol($pdo,$table,['width','img_width','image_width','w','px_width'], false);
  $hCol      = pickCol($pdo,$table,['height','img_height','image_height','h','px_height'], false);
  $dimCol    = pickCol($pdo,$table,['dimension','dimensions','resolution','size_label'], false);
  $arCol     = pickCol($pdo,$table,['aspect_ratio','ratio','ar'], false);
  $createdCol= pickCol($pdo,$table,['created_at','created','createdOn'], false);
} catch(Throwable $e){
  slog('SCHEMA ERR: '.$e->getMessage());
  http_response_code(500);
  echo 'slider_images テーブルの必須列が見つかりません：'.$e->getMessage();
  exit;
}
$HAS_STORE = (bool)$storeCol;
$HAS_POS   = (bool)$posCol;
$HAS_ORDER = (bool)$orderCol;

// 店舗フィルタ（左の選択のみで連動）
$filter_store = ($HAS_STORE && isset($_GET['store_id']) && $_GET['store_id']!=='') ? (int)$_GET['store_id'] : null;

$err=''; $msg='';
$MAX_PER = 5; // 1店舗あたり最大5

// ========= position の空き番号（1..5） =========
function next_position(PDO $pdo, $table, $posCol, $storeCol, $sid){
  if (!$posCol) return null;
  if ($storeCol && $sid){
    $st = $pdo->prepare("SELECT `$posCol` AS p FROM `$table` WHERE `$storeCol`=?");
    $st->execute([$sid]);
  } else {
    $st = $pdo->query("SELECT `$posCol` AS p FROM `$table`");
  }
  $used = [];
  foreach (($st instanceof PDOStatement ? $st->fetchAll() : []) as $r){
    $p = (int)($r['p'] ?? 0);
    if ($p >= 1 && $p <= 5) $used[$p] = true;
  }
  for ($i=1; $i<=5; $i++){
    if (empty($used[$i])) return $i;
  }
  return 5;
}

/**
 * 店舗ごとに position を 1..5 に正規化し、6枚目以降は削除（任意でバックアップに変更可）
 * - 前提: $table, $idCol, $storeCol, $posCol が有効
 */
function normalize_slider_positions(PDO $pdo, string $table, string $idCol, ?string $storeCol, ?string $posCol): void {
    if (!$storeCol || !$posCol) return; // store/positionなし構成は対象外

    // 1) 6枚を超える店舗の「6枚目以降」を削除（必要なら backup テーブルへ事前INSERT）
    //    MySQL5.7対応: ユーザー変数で通し番号を振る
    $pdo->beginTransaction();
    try {
        // 6枚超の store だけ対象
        $sqlMany = "SELECT `$storeCol` AS sid FROM `$table` GROUP BY `$storeCol` HAVING COUNT(*) > 5";
        $many = $pdo->query($sqlMany)->fetchAll(PDO::FETCH_COLUMN);

        if ($many) {
            foreach ($many as $sid) {
                // 並べ順は position, id 固定。rn>5 を削除
                // まず対象IDを配列で取る
                $st = $pdo->prepare("SELECT `$idCol` FROM `$table` WHERE `$storeCol`=? ORDER BY `$posCol`, `$idCol`");
                $st->execute([$sid]);
                $ids = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN, 0));
                if (count($ids) > 5) {
                    $extra = array_slice($ids, 5); // 6枚目以降
                    // ★退避したい場合はここで backup へ INSERT ... SELECT してください
                    // 削除
                    $in = implode(',', array_fill(0, count($extra), '?'));
                    $pdo->prepare("DELETE FROM `$table` WHERE `$idCol` IN ($in)")->execute($extra);
                }
            }
        }

        // 2) すべての店舗について position を 1..5 へ再採番（重複解消）
        $sqlStores = "SELECT DISTINCT `$storeCol` FROM `$table`";
        $stores = $pdo->query($sqlStores)->fetchAll(PDO::FETCH_COLUMN);

        foreach ($stores as $sid) {
            $st = $pdo->prepare("SELECT `$idCol` FROM `$table` WHERE `$storeCol`=? ORDER BY `$posCol`, `$idCol`");
            $st->execute([$sid]);
            $ids = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN, 0));
            if (!$ids) continue;

            // 1..min(5,件数) を付与
            $N = min(count($ids), 5);
            $caseParts = []; $params = [];
            for ($i = 0; $i < $N; $i++) {
                $caseParts[] = 'WHEN ? THEN ?';
                $params[] = $ids[$i];      // id
                $params[] = $i + 1;        // new position
            }
            $inMarks = implode(',', array_fill(0, count($ids), '?'));
            $sql = 'UPDATE `'.$table.'`
                    SET `'.$posCol.'` = CASE `'.$idCol.'` '.implode(' ', $caseParts).' ELSE `'.$posCol.'` END
                    WHERE `'.$storeCol.'` = ? AND `'.$idCol.'` IN ('.$inMarks.')';
            $params2 = array_merge($params, [$sid], $ids);
            $pdo->prepare($sql)->execute($params2);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        slog('NORMALIZE EX: '.$e->getMessage());
        throw $e;
    }
}

// ========= 並べ替え（D&D保存用エンドポイント） =========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $isJson = isset($_SERVER['CONTENT_TYPE']) && stripos($_SERVER['CONTENT_TYPE'], 'application/json') === 0;
  $action = $_POST['action'] ?? null;

  if ($isJson || $action === 'reorder') {
    header('Content-Type: application/json; charset=UTF-8');
    try {
      // ---- 入力 ----
      $ids = null; $sid = null;
      if ($isJson) {
        $j = json_decode(file_get_contents('php://input'), true);
        if (is_array($j)) { $ids = $j['ids'] ?? null; $sid = isset($j['store_id']) ? (int)$j['store_id'] : null; }
      } else {
        $ids = isset($_POST['ids']) ? (is_array($_POST['ids']) ? $_POST['ids'] : explode(',', (string)$_POST['ids'])) : null;
        $sid = isset($_POST['store_id']) ? (int)$_POST['store_id'] : null;
      }
      if (!is_array($ids) || empty($ids)) { echo json_encode(['ok'=>false,'error'=>'idsがありません']); exit; }
      $ids = array_values(array_unique(array_map('intval',$ids)));

      if (!$HAS_POS) { echo json_encode(['ok'=>false,'error'=>'position 列がありません']); exit; }
      if ($HAS_STORE && !$sid) { echo json_encode(['ok'=>false,'error'=>'store_idがありません']); exit; }

      // （任意）同時実行を完全直列化
      if ($HAS_STORE && function_exists('lock_store')) { lock_store($pdo, $sid); }

      // ---- トランザクション開始 ----
      $pdo->beginTransaction();

      // 対象店舗の行をロックして取得（この集合だけで処理する）
      $st = $pdo->prepare("SELECT `$idCol` FROM `$table` WHERE `$storeCol`=? FOR UPDATE");
      $st->execute([$sid]);
      $lockedIds = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN, 0));
      if (!$lockedIds) { $pdo->commit(); if ($HAS_STORE && function_exists('unlock_store')) unlock_store($pdo,$sid); echo json_encode(['ok'=>true]); exit; }

      // 要求IDが同一店舗内か確認
      $lockedSet = array_fill_keys($lockedIds, true);
      foreach ($ids as $x) { if (!isset($lockedSet[$x])) { $pdo->rollBack(); if ($HAS_STORE && function_exists('unlock_store')) unlock_store($pdo,$sid); echo json_encode(['ok'=>false,'error'=>'異なる店舗の画像が含まれています']); exit; } }

      // 最終順: 先頭=ids、後ろ=残り → K枚（K<=5）
      $rest  = array_values(array_diff($lockedIds, $ids));
      $final = array_values(array_unique(array_merge($ids, $rest)));
      $K = min(count($final), 5);
      $final = array_slice($final, 0, $K);

      // ---- 一時テーブル（TEMPORARY TABLE）を作成（インデックス/制約なし）----
      $pdo->exec("DROP TEMPORARY TABLE IF EXISTS tmp_slider_images");
      $pdo->exec("CREATE TEMPORARY TABLE tmp_slider_images AS SELECT * FROM `$table` WHERE 1=0"); // 空の構造だけ

      // 対象店舗の元データを一時にコピー
      $pdo->prepare("INSERT INTO tmp_slider_images SELECT * FROM `$table` WHERE `$storeCol`=?")->execute([$sid]);

      // 一時側で「保持するK件」以外は削除（6枚目以降や余剰は捨てる）
      $inMarksKeep = implode(',', array_fill(0, $K, '?'));
      $pdo->prepare("DELETE FROM tmp_slider_images WHERE `$storeCol` = ? AND `$idCol` NOT IN ($inMarksKeep)")
          ->execute(array_merge([$sid], $final));

      // 一時側で K件に 1..K を一括採番（CASE）
      $caseParts = []; $params = [];
      for ($i=0;$i<$K;$i++){ $caseParts[]='WHEN ? THEN ?'; $params[]=$final[$i]; $params[]=$i+1; }
      $inMarksK = implode(',', array_fill(0, $K, '?'));
      $sqlCase = 'UPDATE tmp_slider_images
                  SET `'.$posCol.'` = CASE `'.$idCol.'` '.implode(' ', $caseParts).' ELSE `'.$posCol.'` END
                  WHERE `'.$storeCol.'` = ? AND `'.$idCol.'` IN ('.$inMarksK.')';
      $pdo->prepare($sqlCase)->execute(array_merge($params, [$sid], $final));

      // ★ 元テーブルからこの店舗の行を一旦ぜんぶ削除（ここで衝突の可能性は消滅）
      $pdo->prepare("DELETE FROM `$table` WHERE `$storeCol`=?")->execute([$sid]);

      // 一時から元テーブルへ「挿入し直し」
      // 列名を動的に組み立て（余計なDEFAULT/トリガー差異に備える）
      $cols = array_keys(getColumns($pdo, $table));
      $colList = implode('`,`', $cols);
      $pdo->exec("INSERT INTO `$table` (`$colList`) SELECT `$colList` FROM tmp_slider_images WHERE `$storeCol` = ".(int)$sid);

      $pdo->commit();

      if ($HAS_STORE && function_exists('unlock_store')) unlock_store($pdo, $sid);
      echo json_encode(['ok'=>true]);

    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      slog('REORDER EX: '.$e->getMessage());
      if ($HAS_STORE && isset($sid) && function_exists('unlock_store')) { try { unlock_store($pdo, $sid); } catch(Throwable $_){} }
      echo json_encode(['ok'=>false,'error'=>'内部エラー（SL-REORDER）']);
    }
    exit;
  }
}



// ========= 画像削除 =========
if (isset($_GET['del']) && ctype_digit($_GET['del'])) {
  try{
    $del_id = (int)$_GET['del'];
    $st = $pdo->prepare("SELECT `$pathCol`".($HAS_STORE? ", `$storeCol`":'')." FROM `$table` WHERE `$idCol`=?");
    $st->execute([$del_id]); $row = $st->fetch();
    if ($row){
      $rel = preg_replace('~^.*/uploads/~','',$row[$pathCol]);
      if ($rel && strpos($rel,'..')===false) @unlink(UPLOAD_DIR.'/'.$rel);
      $pdo->prepare("DELETE FROM `$table` WHERE `$idCol`=?")->execute([$del_id]);
      op_log('delete',$table,$del_id,null);
    }
    $redir = $BASE.'slider.php'.($HAS_STORE && isset($row[$storeCol]) ? ('?store_id='.$row[$storeCol]) : '');
    header('Location: '.$redir); exit;
  }catch(Throwable $e){ slog('DEL EX: '.$e->getMessage()); $err='内部エラー（SL-DEL）。'; }
}

// ========= 登録（アップロード） =========
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '')==='upload') {
  try{
    $mime=''; $w=0; $h=0;
    $sid = $HAS_STORE ? (int)($_POST['store_id'] ?? 0) : null;
    if ($HAS_STORE && !$sid) $err='店舗を選択してください。';

    if (!$err){
      if (!vimg($_FILES['image'] ?? [],$mime,$w,$h,$err)) {}
      elseif (!($w===1600 && $h===900)) { $err='画像サイズは 1600×900 ピクセルにしてください。'; }
    }

    // 5件制限
    if (!$err){
      if ($HAS_STORE){
        $st=$pdo->prepare("SELECT COUNT(*) FROM `$table` WHERE `$storeCol`=?");
        $st->execute([$sid]); $cnt = (int)$st->fetchColumn();
        if ($cnt >= $MAX_PER) $err='この店舗のスライダーは最大5件までです。';
      } else {
        $cnt = (int)$pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
        if ($cnt >= $MAX_PER) $err='スライダーは最大5件までです。';
      }
    }

    if (!$err){
      [$ok,$url,$e] = save_image_random('slide',$_FILES['image'],$mime,null);
      if (!$ok) { $err=$e ?: '保存に失敗しました。'; }
      else {
        $colsMap = getColumns($pdo,$table);
        $insertCols = []; $insertVals = []; $params = [];
        if ($HAS_STORE){ $insertCols[]=$storeCol; $insertVals[]='?'; $params[]=$sid; }
        $insertCols[]=$pathCol; $insertVals[]='?'; $params[]=$url;

        if ($HAS_POS){ $pos = next_position($pdo,$table,$posCol,$storeCol,$sid); $insertCols[]=$posCol; $insertVals[]='?'; $params[]=$pos; }
        if ($HAS_ORDER){
          if ($HAS_STORE){ $st=$pdo->prepare("SELECT COALESCE(MAX(`$orderCol`),0)+1 FROM `$table` WHERE `$storeCol`=?"); $st->execute([$sid]); $next=(int)$st->fetchColumn(); }
          else { $st=$pdo->query("SELECT COALESCE(MAX(`$orderCol`),0)+1 FROM `$table`"); $next=(int)$st->fetchColumn(); }
          $insertCols[]=$orderCol; $insertVals[]='?'; $params[]=$next ?: 1;
        }

        if ($wCol){ $insertCols[]=$wCol; $insertVals[]='?'; $params[]=$w; }
        if ($hCol){ $insertCols[]=$hCol; $insertVals[]='?'; $params[]=$h; }
        if ($dimCol){ $insertCols[]=$dimCol; $insertVals[]='?'; $params[]=$w.'x'.$h; }
        if ($arCol){  $insertCols[]=$arCol;  $insertVals[]='?'; $params[]=round($w/$h, 6); }

        $known = array_filter([$storeCol,$pathCol,$posCol,$orderCol,$wCol,$hCol,$dimCol,$arCol,$createdCol,'updated_at']);
        foreach (getColumns($pdo,$table) as $cn=>$meta){
          if (in_array($cn, $known, true)) continue;
          $auto = stripos($meta['EXTRA'] ?? '', 'auto_increment') !== false; if ($auto) continue;
          $isNN = !$meta['IS_NULLABLE']; $hasDefault = !is_null($meta['COLUMN_DEFAULT']);
          if ($isNN && !$hasDefault){ $insertCols[]=$cn; $insertVals[]='?'; $params[]=defaultByType($meta['DATA_TYPE'], $meta['COLUMN_TYPE']); }
        }

        $sql = "INSERT INTO `$table` (".implode(',',array_map(fn($c)=>"`$c`",$insertCols)).") VALUES (".implode(',',$insertVals).")";
        $pdo->prepare($sql)->execute($params);

        $newId = (int)$pdo->lastInsertId();
        op_log('create',$table,$newId,['path'=>$url,'w'=>$w,'h'=>$h, 'pos'=>$pos ?? null]);
        $qs = ($HAS_STORE ? ('?store_id='.$sid) : '');
        header('Location: '.$BASE.'slider.php'.$qs); exit;
      }
    }
  }catch(Throwable $e){
    slog('UPLOAD EX: '.$e->getMessage());
    $err='内部エラー（SL-UP）。';
  }
}

// ========= 一覧取得（左の店舗選択に連動） =========
$rows=[]; $total=0;
try{
  if ($HAS_STORE){
    if ($filter_store){
      $orderByParts = [];
      if ($HAS_POS)   $orderByParts[] = "`$posCol` ASC";
      if ($HAS_ORDER) $orderByParts[] = "`$orderCol` ASC";
      $orderByParts[] = "`$idCol` ASC";
      $orderBy = implode(', ', $orderByParts);
      $sql = "SELECT m.* , s.name AS store_name
              FROM `$table` m LEFT JOIN stores s ON s.store_id = m.`$storeCol`
              WHERE m.`$storeCol` = ?
              ORDER BY $orderBy";
      $st=$pdo->prepare($sql); $st->execute([$filter_store]); $rows = $st->fetchAll();
    } else {
      $orderByParts = [];
      $orderByParts[] = "m.`$storeCol` ASC";
      if ($HAS_POS)   $orderByParts[] = "`$posCol` ASC";
      if ($HAS_ORDER) $orderByParts[] = "`$orderCol` ASC";
      $orderByParts[] = "m.`$idCol` ASC";
      $orderBy = implode(', ', $orderByParts);

      $rows = $pdo->query("SELECT m.* , s.name AS store_name
                           FROM `$table` m LEFT JOIN stores s ON s.store_id = m.`$storeCol`
                           ORDER BY $orderBy")->fetchAll();
    }
  } else {
    $orderByParts = [];
    if ($HAS_POS)   $orderByParts[] = "`$posCol` ASC";
    if ($HAS_ORDER) $orderByParts[] = "`$orderCol` ASC";
    $orderByParts[] = "`$idCol` ASC";
    $orderBy = implode(', ', $orderByParts);

    $rows = $pdo->query("SELECT * FROM `$table` ORDER BY $orderBy")->fetchAll();
  }
  $total = count($rows);
} catch(Throwable $e){
  slog('LIST EX: '.$e->getMessage());
  $err = $err ?: '一覧取得でエラーが発生しました。';
}

// 店舗一覧（左フォーム用）
$stores=[];
if ($HAS_STORE){
  try { $stores = $pdo->query('SELECT store_id, name FROM stores WHERE is_deleted=0 ORDER BY store_id')->fetchAll(); }
  catch(Throwable $e){ slog('STORES EX: '.$e->getMessage()); }
}
?>
<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<title>スライダー画像 | 管理ダッシュボード</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  :root{ --bg:#f7f7fb; --card:#fff; --ink:#111827; --muted:#6b7280; --brand:#2563eb; --line:#e5e7eb }
  *{box-sizing:border-box}
  body{margin:0;background:var(--bg);color:var(--ink);font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial}
  header{display:flex;gap:12px;align-items:center;justify-content:space-between;padding:16px 20px;background:#fff;border-bottom:1px solid var(--line);position:sticky;top:0}
  .brand{font-weight:700}
  .user{color:var(--muted);font-size:.95rem}
  .logout{color:#fff;background:#111827;padding:.5rem .8rem;border-radius:10px}
  main{max-width:1200px;margin:24px auto;padding:0 16px}
  h1{font-size:1.25rem;margin:0 0 8px}
  .sub{color:var(--muted);margin:0 0 16px}
  .layout{display:grid;grid-template-columns:1fr 1.4fr;gap:16px}
  .card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:14px}
  label{display:block;margin:.6rem 0 .2rem;font-weight:600}
  input[type="file"], select{width:100%;padding:.6rem;border:1px solid #d1d5db;border-radius:10px;font-size:1rem}
  .btn{display:inline-flex;align-items:center;justify-content:center;padding:.6rem .9rem;border-radius:10px;border:1px solid var(--line);text-decoration:none;cursor:pointer}
  .btn-primary{background:var(--brand);color:#fff;border-color:transparent}
  .btn-danger{background:#fee2e2;border-color:#fecaca;color:#991b1b}
  .btn-ghost{background:#fff;color:#111827}
  .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px}
  .item{border:1px solid var(--line);border-radius:12px;padding:8px;background:#fff; cursor:move}
  .item.dragging{opacity:.5; transform:scale(.98)}
  .item img{width:100%;height:auto;display:block;border-radius:8px;border:1px solid var(--line)}
  .muted{color:var(--muted)}
  .hint{font-size:.85rem;color:#6b7280;margin:6px 0}
  @media (max-width: 1000px){ .layout{grid-template-columns:1fr} }
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
    <h1>スライダー画像</h1>
    <p class="sub">
      <?= $HAS_STORE ? '店舗ごとに' : '' ?>最大 <?= $MAX_PER ?> 件まで登録できます（推奨サイズ：1600×900px）。現在：<?= j((string)$total) ?> 件表示。
    </p>

    <?php if ($msg): ?><div style="background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46;padding:.6rem .8rem;border-radius:10px;margin:10px 0"><?= j($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div style="background:#fee2e2;border:1px solid #fecaca;color:#7f1d1d;padding:.6rem .8rem;border-radius:10px;margin:10px 0"><?= j($err) ?></div><?php endif; ?>

    <div class="layout">
      <!-- 左：登録フォーム（※この選択のみで右の一覧が切り替わる） -->
      <div class="card">
        <h2 style="font-size:1rem;margin:0 0 10px">画像登録</h2>
        <form method="post" enctype="multipart/form-data">
          <input type="hidden" name="action" value="upload">
          <?php if ($HAS_STORE): ?>
            <label>店舗</label>
            <select name="store_id" id="storeSelectLeft" required>
              <option value="">選択してください</option>
              <?php
                $sel = $filter_store ?? '';
                foreach($stores as $s):
                  $selected = ((string)$sel === (string)$s['store_id']) ? 'selected' : '';
              ?>
                <option value="<?= j($s['store_id']) ?>" <?= $selected ?>>
                  <?= j($s['store_id'].' : '.$s['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          <?php endif; ?>
          <label>画像（1600×900／JPEG・PNG）</label>
          <input type="file" name="image" accept="image/jpeg,image/png" required>
          <div class="hint">※ 店舗を切り替えると、右側一覧も同じ店舗に更新されます。</div>
          <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap">
            <button class="btn btn-primary" type="submit">登録する</button>
            <a class="btn btn-ghost" href="<?= j($BASE.'index.php') ?>">← ダッシュボードへ戻る</a>
          </div>
        </form>
      </div>

      <!-- 右：登録済み一覧（位置は表示のみ／D&Dで保存） -->
      <div class="card">
        <h2 style="font-size:1rem;margin:0 0 10px">登録済み一覧</h2>

        <?php if ($HAS_STORE && !$filter_store): ?>
          <div class="hint">※ 店舗を選択すると、この店舗の画像だけが表示され、ドラッグで位置を入れ替えできます。</div>
        <?php elseif ($HAS_STORE && $filter_store): ?>
          <div class="hint">※ 並べ替え：画像をドラッグ＆ドロップ → 自動保存</div>
        <?php else: ?>
          <div class="hint">※ 並べ替え：画像をドラッグ＆ドロップ → 自動保存（全体で1..5）</div>
        <?php endif; ?>

        <div id="sliderGrid"
             class="grid"
             data-store-id="<?= $HAS_STORE && $filter_store ? (int)$filter_store : '' ?>"
             data-can-dnd="<?= (!$HAS_STORE || $filter_store) ? '1' : '0' ?>">
          <?php if (empty($rows)): ?>
            <div class="muted">登録された画像はありません。</div>
          <?php else: ?>
            <?php foreach($rows as $r): ?>
              <div class="item" draggable="<?= (!$HAS_STORE || $filter_store) ? 'true':'false' ?>"
                   data-id="<?= j($r[$idCol]) ?>">
                <img src="<?= j($r[$pathCol]) ?>" alt="slide">
                <div style="margin-top:6px;display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap">
                  <div class="muted" style="font-size:.85rem">
                    ID: <?= j($r[$idCol]) ?>
                    <?php if ($HAS_STORE): ?> /
                      店舗: <?= j((string)$r[$storeCol]) ?><?= isset($r['store_name']) && $r['store_name'] ? ' : '.j($r['store_name']) : '' ?>
                    <?php endif; ?>
                    <?php if ($HAS_POS): ?> / 位置: <strong><?= j((string)$r[$posCol]) ?></strong><?php endif; ?>
                    <?php if (!$HAS_POS && $HAS_ORDER): ?> / 順序: <strong><?= j((string)$r[$orderCol]) ?></strong><?php endif; ?>
                    <?php if ($wCol && $hCol): ?> / 寸法: <?= j((string)$r[$wCol]) ?>×<?= j((string)$r[$hCol]) ?><?php endif; ?>
                  </div>
                  <div>
                    <a class="btn btn-danger" href="<?= j($BASE.'slider.php?del='.$r[$idCol].($HAS_STORE && $filter_store? '&store_id='.$filter_store:'')) ?>"
                       onclick="return confirm('この画像（ID: <?= j($r[$idCol]) ?>）を削除しますか？')">削除</a>
                  </div>
                </div>
                <?php if ($createdCol && isset($r[$createdCol])): ?>
                  <div class="muted" style="margin-top:4px;font-size:.8rem">登録日時: <?= j((string)$r[$createdCol]) ?></div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </main>

  <script>
  // 左の店舗セレクト変更で右の一覧も同じ店舗に即時切替
  document.addEventListener('DOMContentLoaded', function () {
    var left = document.getElementById('storeSelectLeft');
    if (left) {
      left.addEventListener('change', function () {
        var v = this.value || '';
        var base = '<?= j($BASE . "slider.php") ?>';
        if (v) location.href = base + '?store_id=' + encodeURIComponent(v);
        else   location.href = base;
      });
    }

    // ドラッグ＆ドロップ並べ替え
    var grid = document.getElementById('sliderGrid');
    if (!grid) return;

    var canDnd = grid.getAttribute('data-can-dnd') === '1';
    if (!canDnd) return; // 店舗未選択時はD&D無効

    var draggingEl = null;

    function onDragStart(e){
      draggingEl = this;
      this.classList.add('dragging');
      e.dataTransfer.effectAllowed = 'move';
      try { e.dataTransfer.setData('text/plain', this.dataset.id); } catch(_) {}
    }
    function onDragEnd(){
      if (draggingEl) draggingEl.classList.remove('dragging');
      draggingEl = null;
      saveOrder(); // ドロップ後に保存
    }
    function getDragAfterElement(container, y){
      const els = [...container.querySelectorAll('.item:not(.dragging)')];
      let closest = {offset: Number.NEGATIVE_INFINITY, element: null};
      els.forEach(el=>{
        const box = el.getBoundingClientRect();
        const offset = y - box.top - box.height / 2;
        if (offset < 0 && offset > closest.offset) closest = {offset, element: el};
      });
      return closest.element;
    }
    function onDragOver(e){
      e.preventDefault();
      const after = getDragAfterElement(grid, e.clientY);
      if (!draggingEl) return;
      if (after == null) { grid.appendChild(draggingEl); }
      else { grid.insertBefore(draggingEl, after); }
    }

    function idsFromGrid(){
      return Array.from(grid.querySelectorAll('.item')).map(el=>el.dataset.id);
    }

    async function saveOrder(){
      const ids = idsFromGrid().slice(0,5);
      const payload = {
        action: 'reorder',
        ids: ids,
        store_id: grid.getAttribute('data-store-id') ? parseInt(grid.getAttribute('data-store-id'),10) : undefined
      };
      try{
        const res = await fetch('<?= j($BASE."slider.php") ?>', {
          method: 'POST',
          headers: {'Content-Type':'application/json'},
          body: JSON.stringify(payload)
        });
        const j = await res.json();
        if (!j.ok) {
          console.error(j);
          alert('並べ替えの保存に失敗しました。' + (j.error ? '\n' + j.error : ''));
          location.reload();
          return;
        }
        location.reload(); // 表示中の position を最新に
      }catch(err){
        console.error(err);
        alert('通信エラーにより保存できませんでした。');
        location.reload();
      }
    }

    grid.querySelectorAll('.item[draggable="true"]').forEach(function(item){
      item.addEventListener('dragstart', onDragStart);
      item.addEventListener('dragend', onDragEnd);
    });
    grid.addEventListener('dragover', onDragOver);
  });
  </script>
</body>
</html>
