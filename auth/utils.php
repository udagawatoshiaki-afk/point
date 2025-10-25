<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php'; // SESSION_NAME / MAIL_* など

/* セッション開始（安全設定） */
if (!function_exists('start_secure_session')) {
  function start_secure_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $secure = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';

    if (defined('SESSION_NAME') && SESSION_NAME !== '') {
      @session_name(SESSION_NAME);
    }
    @session_set_cookie_params([
      'lifetime' => 0,
      'path'     => '/',                          // ルート固定
      'domain'   => $_SERVER['HTTP_HOST'] ?? '',
      'secure'   => $secure,
      'httponly' => true,
      'samesite' => 'Lax',
    ]);
    @session_start();
  }
}

/* JSONレスポンス出力＋終了 */
if (!function_exists('json_out')) {
  function json_out(array $payload, int $status = 200, array $extraHeaders = []): void {
    if (!headers_sent()) {
      header('Content-Type: application/json; charset=UTF-8', true, $status);
      foreach ($extraHeaders as $h) { header($h, false); }
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }
}

/* JSON POST 必須のAPI入力取得（不正時は json_out で終了） */
if (!function_exists('require_json_post')) {
  function require_json_post(): array {
    $method = $_SERVER['REQUEST_METHOD'] ?? '';
    if (strcasecmp($method, 'POST') !== 0) {
      json_out(['ok'=>false, 'error'=>'method_not_allowed'], 405, ['Allow: POST']);
    }

    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
      json_out(['ok'=>false, 'error'=>'invalid_json'], 400);
    }
    return $data;
  }
}
