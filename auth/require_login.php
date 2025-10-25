<?php
declare(strict_types=1);

// ==== Log to /pointcard/error.log (append, no new files) ====
$PC_ERROR_LOG = dirname(__DIR__) . '/error.log'; // /pointcard/auth -> /pointcard/error.log
$RID = bin2hex(random_bytes(4));
@error_log("[require_login][$RID] BOOT\n", 3, $PC_ERROR_LOG);

// ==== Load session helper non-fatally ====
$session_php = __DIR__ . '/session.php';
if (is_file($session_php)) {
  @include_once $session_php;
  @error_log("[require_login][$RID] session.php included\n", 3, $PC_ERROR_LOG);
} else {
  @error_log("[require_login][$RID] session.php missing -> fallback\n", 3, $PC_ERROR_LOG);
}

// ==== Start secure session (prefer project helper) ====
try {
  if (function_exists('start_secure_session')) {
    start_secure_session();
    @error_log("[require_login][$RID] start_secure_session OK\n", 3, $PC_ERROR_LOG);
  } else {
    if (session_status() !== PHP_SESSION_ACTIVE) {
      // Cookie scope to /pointcard, secure/httponly/Lax
      @session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/pointcard',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Lax',
      ]);
      @session_start();
      @error_log("[require_login][$RID] fallback session_start OK\n", 3, $PC_ERROR_LOG);
    }
  }
} catch (Throwable $e) {
  // Even on failure, don't 500; just try plain session
  if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
  @error_log("[require_login][$RID] SESSION_EXC " . $e->getMessage() . "\n", 3, $PC_ERROR_LOG);
}

// ==== Gate ====
$uid = isset($_SESSION['uid']) ? (int)$_SESSION['uid'] : 0;
@error_log("[require_login][$RID] uid={$uid}\n", 3, $PC_ERROR_LOG);

if ($uid <= 0) {
  // Not logged in -> redirect to login with next param
  $next = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/pointcard/';
  $loc = '/pointcard/login.php?next=' . rawurlencode($next);
  @error_log("[require_login][$RID] REDIRECT {$loc}\n", 3, $PC_ERROR_LOG);
  header('Location: ' . $loc, true, 302);
  exit;
}

// Logged in -> allow script to continue
@error_log("[require_login][$RID] PASS\n", 3, $PC_ERROR_LOG);
