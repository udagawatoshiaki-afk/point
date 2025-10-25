<?php
// auth.php
require_once __DIR__ . '/db.php';

$secure = !empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off';
session_name(SESSION_NAME);
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',   // サブディレクトリでもOK
    'domain'   => '',
    'secure'   => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

function current_admin(): ?array {
    return $_SESSION['admin'] ?? null;
}

// 現在の実行スクリプトのディレクトリから login.php を組み立て
function login_url(): string {
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\') . '/';
    return $base . 'login.php';
}

function require_login(): void {
    if (!current_admin()) {
        header('Location: ' . login_url());
        exit;
    }
}

function login(string $username, string $password, string $ip, string $ua): bool {
    $pdo = db(); // ここでDB接続失敗なら例外→login.phpのtry/catchで捕捉

    $stmt = $pdo->prepare('SELECT * FROM admin_users WHERE username = ?');
    $stmt->execute([$username]);
    $u = $stmt->fetch();

    $ok = $u && password_verify($password, $u['password_hash']);

    // ログイン履歴
    $pdo->prepare('INSERT INTO login_history(admin_user_id, username, success, ip_address, user_agent)
                   VALUES (?,?,?,?,?)')
        ->execute([
            $u['admin_user_id'] ?? null,
            $username,
            $ok ? 1 : 0,
            $ip,
            mb_substr($ua ?? '', 0, 255),
        ]);

    if ($ok) {
        $_SESSION['admin'] = [
            'admin_user_id' => $u['admin_user_id'],
            'username'      => $u['username'],
            'role'          => $u['role'],
        ];
        $pdo->prepare('UPDATE admin_users SET last_login_at = NOW() WHERE admin_user_id = ?')
            ->execute([$u['admin_user_id']]);
    }
    return $ok;
}

function logout(): void {
    $_SESSION = [];
    if (session_id() !== '') {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
