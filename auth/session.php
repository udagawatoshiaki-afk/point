<?php
declare(strict_types=1);

/**
 * /pointcard/auth/session.php
 * 目的: セッション開始と API 用の軽い認可ヘルパー
 * - ここから utils.php を読み込み、start_secure_session / json_out / require_json_post を提供
 * - Cookie path は '/' に統一
 */

require_once __DIR__ . '/utils.php'; // ★ 追加：共通ユーティリティを読み込み

$https = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';

if (session_status() !== PHP_SESSION_ACTIVE) {
  if (defined('SESSION_NAME') && SESSION_NAME !== '') {
    @session_name(SESSION_NAME);
  }
  session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',                         // ルートに統一
    'domain'   => $_SERVER['HTTP_HOST'] ?? '',
    'secure'   => $https,
    'httponly' => true,
    'samesite' => 'Lax',
  ]);
  @session_start();
}

/** 現在ログイン中か（複数キーに対応） */
if (!function_exists('pc_is_logged_in')) {
  function pc_is_logged_in(): bool {
    return !empty($_SESSION['uid'])
        || !empty($_SESSION['user_id'])
        || !empty($_SESSION['id'])
        || !empty($_SESSION['userid']);
  }
}

/**
 * APIエンドポイント向け：未ログインなら JSON を返して終了
 * （HTMLでリダイレクトしたい場合は呼び出し側で制御してください）
 */
if (!function_exists('pc_require_login')) {
  function pc_require_login(): void {
    if (pc_is_logged_in()) return;
    header('Content-Type: application/json; charset=UTF-8');
    http_response_code(401);
    echo json_encode([
      'ok'         => false,
      'logged_in'  => false,
      'error'      => 'not_logged_in',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }
}
