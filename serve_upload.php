<?php
declare(strict_types=1);

/* /pointcard/serve_upload.php — silent edition
 * - Serves: /admin/uploads/sliders/YYYY/MM/<hash>/slide.png
 * - Fallback: latest mtime under the same YYYY/MM (only if direct not found)
 * - Logs only on errors (4xx/5xx/exception)
 */

$ROOT = __DIR__;
$LOG  = $ROOT . '/error.log';
$RID  = substr(md5(uniqid('', true)), 0, 8);

@header('X-Pointcard-Debug: ' . $RID);
@header('Vary: Accept-Encoding, If-None-Match, If-Modified-Since');
if (function_exists('header_remove')) { @header_remove('Link'); } // anti-injected favicon header

function pc_err(string $rid, string $msg, string $logfile): void {
  @error_log("[serve][$rid] " . $msg . "\n", 3, $logfile);
}

try {
  $rel = isset($_GET['path']) ? (string)$_GET['path'] : '';
  $rel = str_replace("\0", '', $rel);
  $rel = ltrim($rel, "/");
  if ($rel === '') { pc_err($RID, "400 empty path", $LOG); http_response_code(400); exit; }
  if (preg_match('/\.\./', $rel) || preg_match('/[\\\\]/', $rel)) { pc_err($RID, "400 illegal path rel={$rel}", $LOG); http_response_code(400); exit; }

  $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
  $ALLOWED = ['png','jpg','jpeg','webp','gif','svg','avif'];
  if (!in_array($ext, $ALLOWED, true)) { pc_err($RID, "403 ext not allowed .{$ext} rel={$rel}", $LOG); http_response_code(403); exit; }

  $BASES = [];
  $b1 = $ROOT . '/admin/uploads';
  $b2 = $ROOT . '/admin/uploads/sliders';
  if (is_dir($b1)) { $rp = realpath($b1); if ($rp) $BASES[] = $rp; }
  if (is_dir($b2)) { $rp = realpath($b2); if ($rp) $BASES[] = $rp; }

  $found = null;

  // 1) direct
  foreach ($BASES as $baseReal) {
    $try = $baseReal . DIRECTORY_SEPARATOR . $rel;
    $rp  = realpath($try);
    if ($rp && is_file($rp) && strpos($rp, $baseReal . DIRECTORY_SEPARATOR) === 0) { $found = $rp; break; }
  }

  // 2) fallback latest in same YYYY/MM
  if (!$found) {
    if (preg_match('#^sliders/(\d{4})/(\d{2})/[^/]+/([^/]+\.(?:png|jpg|jpeg|webp|gif|svg|avif))$#i', $rel, $m)) {
      $yyyy = $m[1]; $mm = $m[2]; $fname = $m[3];
      $candidates = [];
      foreach ($BASES as $baseReal) {
        $dir = $baseReal . DIRECTORY_SEPARATOR . "sliders/$yyyy/$mm";
        $dir_rp = (is_dir($dir) ? realpath($dir) : false);
        if (!$dir_rp) continue;
        $pattern = $dir_rp . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . $fname;
        $list = glob($pattern, GLOB_NOSORT);
        if (is_array($list)) {
          foreach ($list as $f) {
            $rp = realpath($f);
            if ($rp && is_file($rp) && strpos($rp, $dir_rp . DIRECTORY_SEPARATOR) === 0) $candidates[] = $rp;
          }
        }
      }
      if (!empty($candidates)) {
        $latest = null; $latest_mtime = -1;
        foreach ($candidates as $f) {
          $mt = @filemtime($f);
          if ($mt !== false && $mt >= $latest_mtime) { $latest_mtime = (int)$mt; $latest = $f; }
        }
        if ($latest) $found = $latest;
      }
    }
  }

  if (!$found) { pc_err($RID, "404 NOT FOUND rel={$rel}", $LOG); http_response_code(404); exit; }

  // MIME
  $mime = 'application/octet-stream';
  if (function_exists('finfo_open')) {
    $fi = finfo_open(FILEINFO_MIME_TYPE);
    if ($fi) { $m = finfo_file($fi, $found); if (is_string($m) && $m !== '') $mime = $m; finfo_close($fi); }
  }
  $map = ['png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','webp'=>'image/webp','gif'=>'image/gif','svg'=>'image/svg+xml','avif'=>'image/avif'];
  if (isset($map[$ext])) $mime = $map[$ext];

  // Cache
  $mtime = @filemtime($found); if ($mtime === false) $mtime = time();
  $size  = @filesize($found);  if ($size === false) $size = 0;
  $etag  = '"' . substr(sha1($found . '|' . $mtime . '|' . (string)$size), 0, 16) . '"';
  $lm    = gmdate('D, d M Y H:i:s', $mtime) . ' GMT';

  @header('Content-Type: ' . $mime);
  @header('Last-Modified: ' . $lm);
  @header('ETag: ' . $etag);
  @header('Cache-Control: public, max-age=86400, immutable');

  $ims = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '';
  $inm = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
  if (($inm && trim($inm) === $etag) || ($ims && strtotime($ims) >= $mtime)) { http_response_code(304); exit; }

  $fp = @fopen($found, 'rb');
  if ($fp) {
    if (is_int($size) && $size >= 0) { @header('Content-Length: ' . (string)$size); }
    fpassthru($fp);
    fclose($fp);
  } else {
    pc_err($RID, "500 fopen fail file={$found}", $LOG);
    http_response_code(500);
  }
  exit;

} catch (Throwable $e) {
  pc_err($RID, "EXC " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine(), $LOG);
  http_response_code(500);
  exit;
}
