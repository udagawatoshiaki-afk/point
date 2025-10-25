<?php
// /api/slider.php  --- 公開API（CMSのuploadsをリレーAPI経由で返す版）
declare(strict_types=1);

// ====== 依存ファイル（パスは環境に合わせて） ======
require_once __DIR__ . '/../admin/app/config.php';   // DB, 定数（UPLOAD_DIR等）
require_once __DIR__ . '/../admin/app/helpers.php';  // db() など

header('Content-Type: application/json; charset=UTF-8');

// ====== ユーティリティ ======
function Q(string $c): string { return '`'.str_replace('`','``',$c).'`'; } // バッククォート囲み

function hasColumn(PDO $pdo, string $table, string $col): bool {
  $db = $pdo->query('SELECT DATABASE()')->fetchColumn();
  $st = $pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?');
  $st->execute([$db,$table,$col]);
  return (bool)$st->fetch();
}
function pickCol(PDO $pdo, string $table, array $candidates, bool $required=false): ?string {
  foreach ($candidates as $c) if (hasColumn($pdo,$table,$c)) return $c;
  if ($required) throw new RuntimeException("Required column not found in {$table}: ".implode('|',$candidates));
  return null;
}

/**
 * DBの file_path（/pointcard/admin/uploads/... または http(s)://.../pointcard/admin/uploads/...）
 * → /api/serve_upload.php?path=... に変換して外部公開できるURLにする
 */
function to_public_url_via_relay(string $src): string {
  $src = trim($src);
  if ($src === '') return $src;

  // /pointcard/admin/uploads/ 以下を抽出 → /pointcard/serve_upload.php に中継
  if (preg_match('~^https?://[^/]+/pointcard/admin/uploads/(.+)$~', $src, $m) ||
      preg_match('~^/pointcard/admin/uploads/(.+)$~', $src, $m)) {
    return '/pointcard/serve_upload.php?path=' . rawurlencode($m[1]);
  }

  // すでに http(s) or ルート相対ならそのまま
  if (preg_match('~^https?://|^/~', $src)) return $src;

  // 相対パスで来たときのフォールバック（基本は上で拾える想定）
  return '/pointcard/serve_upload.php?path=' . rawurlencode($src);
}


// ====== 入力 ======
$storeId = isset($_GET['store_id']) ? (int)$_GET['store_id'] : 0;
if ($storeId <= 0) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'store_id is required (positive integer)']);
  exit;
}

// 一時上書き（列名カスタム用・任意）
$storeColOverride = isset($_GET['store_col']) ? (string)$_GET['store_col'] : null;
$srcColOverride   = isset($_GET['src_col'])   ? (string)$_GET['src_col']   : null;

// ====== 構成 ======
$pdo   = db();
$table = 'slider_images';

// 列名の自動検出（必要に応じて候補を増やしてください）
try {
  $col_id     = pickCol($pdo, $table, ['image_id','slider_image_id','slider_id','id'], true);
  $col_store  = $storeColOverride ?: pickCol($pdo, $table, ['store_id'], true);
  $col_src    = $srcColOverride   ?: pickCol($pdo, $table, ['file_path','image_path','path','url'], true);
  $col_pos    = pickCol($pdo, $table, ['position','pos'], false);
  $col_order  = pickCol($pdo, $table, ['sort_order','display_order','order_no','ord'], false);
  $col_href   = pickCol($pdo, $table, ['href','link_url','url_href'], false);
  $col_title  = pickCol($pdo, $table, ['title','caption'], false);
  $col_alt    = pickCol($pdo, $table, ['alt','alt_text'], false);
  $col_active = pickCol($pdo, $table, ['is_active','active','enabled'], false);
  $col_start  = pickCol($pdo, $table, ['start_at','starts_at','publish_from'], false);
  $col_end    = pickCol($pdo, $table, ['end_at','ends_at','publish_to'], false);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    'ok'=>false,
    'error'=>'Required columns not found',
    'hint'=>'Pass ?store_col=<name>&src_col=<name> to override if needed',
    'message'=>$e->getMessage()
  ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  exit;
}

// ====== 取得 ======
// SELECT 句（存在する列だけ AS で内部名に寄せる）
$parts = [];
$parts[] = Q($col_src) . ' AS _src';
$parts[] = $col_href   ? Q($col_href)  . ' AS _href'  : "NULL AS _href";
$parts[] = $col_title  ? Q($col_title) . ' AS _title' : "NULL AS _title";
$parts[] = $col_alt    ? Q($col_alt)   . ' AS _alt'   : "NULL AS _alt";
$parts[] = $col_pos    ? Q($col_pos)   . ' AS _pos'   : "0 AS _pos";
$select = implode(', ', $parts);

// WHERE（公開条件）
$where = Q($col_store) . ' = :sid';
if ($col_active) $where .= ' AND ' . Q($col_active) . ' = 1';
if ($col_start)  $where .= ' AND (' . Q($col_start) . ' IS NULL OR ' . Q($col_start) . ' <= NOW())';
if ($col_end)    $where .= ' AND (' . Q($col_end)   . ' IS NULL OR ' . Q($col_end)   . ' >= NOW())';

// ORDER
$order = [];
if ($col_pos)   $order[] = Q($col_pos) . ' ASC';
if ($col_order) $order[] = Q($col_order) . ' ASC';
$order[] = Q($col_id) . ' ASC';
$orderBy = implode(', ', $order);

// 実行（最大5件）
$sql = "SELECT {$select} FROM ".Q($table)." WHERE {$where} ORDER BY {$orderBy} LIMIT 5";
$st  = $pdo->prepare($sql);
$st->execute([':sid'=>$storeId]);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

// ====== 整形（公開URLへ変換） ======
$slides = [];
foreach ($rows as $i => $r) {
  $raw = (string)($r['_src'] ?? '');
  $src = to_public_url_via_relay($raw);

  $slides[] = [
    'src'  => $src,
    'alt'  => ($r['_alt'] ?: ($r['_title'] ?: ('Slide '.($i+1)))),
    'href' => $r['_href'] ?: null,
    'pos'  => (int)($r['_pos'] ?? 0),
  ];
}

// ====== 返却 ======
echo json_encode([
  'ok'       => true,
  'store_id' => $storeId,
  'slides'   => $slides,
  // デバッグが必要なら &debug=1 を付けてください
  'debug'    => (isset($_GET['debug']) ? [
      'table'       => $table,
      'used_cols'   => [
        'col_store'=>$col_store,'col_src'=>$col_src,'col_pos'=>$col_pos,
        'col_order'=>$col_order,'col_href'=>$col_href,'col_title'=>$col_title,
        'col_alt'=>$col_alt,'col_active'=>$col_active,'col_start'=>$col_start,'col_end'=>$col_end
      ],
      'sql'         => $sql
    ] : null)
], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
