<?php
require_once __DIR__.'/db.php';

function ensure_dir($path) { if (!is_dir($path)) { mkdir($path, 0775, true); } }

function j($s){ return htmlspecialchars($s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }

function validate_zenkaku_len($s, $max){
  // 目安として可視幅で制限（全角=2幅換算）
  return (mb_strwidth($s, 'UTF-8') <= $max*2);
}

function op_log($action, $entity, $entity_id, $detail){
  $admin = current_admin();
  $pdo = db();
  $pdo->prepare('INSERT INTO op_logs(admin_user_id, action, entity, entity_id, detail_json, ip_address, user_agent) VALUES (?,?,?,?,?,?,?)')
      ->execute([
        $admin['admin_user_id'], $action, $entity, (string)$entity_id,
        $detail ? json_encode($detail, JSON_UNESCAPED_UNICODE) : null,
        $_SERVER['REMOTE_ADDR'] ?? '', mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '',0,255)
      ]);
}
