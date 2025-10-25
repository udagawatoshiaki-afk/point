<?php
require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/db.php';

header('Content-Type: text/plain; charset=UTF-8');

echo "== DB Connectivity Test ==\n";
try {
    $pdo = db();
    echo "PDO connect: OK\n";
    // どのDBに繋がっているか
    $db = $pdo->query('select database() as db')->fetch();
    echo "database(): " . ($db['db'] ?? '(null)') . "\n\n";

    $tables = ['admin_users', 'login_history', 'op_logs', 'stores', 'slider_images'];
    foreach ($tables as $t) {
        try {
            $pdo->query("DESCRIBE `$t`");
            echo "table `$t`: OK\n";
        } catch (Throwable $e) {
            echo "table `$t`: MISSING -> " . $e->getMessage() . "\n";
        }
    }
    echo "\nDONE.\n";
} catch (Throwable $e) {
    echo "CONNECT ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
