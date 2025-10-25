<?php
declare(strict_types=1);
header('Content-Type: text/plain; charset=UTF-8');
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

echo "URI: " . ($_SERVER['REQUEST_URI'] ?? '') . "\n";
echo "SESSION_ID: " . session_id() . "\n";
echo "COOKIE names: " . implode(', ', array_keys($_COOKIE)) . "\n\n";

echo "_SESSION keys & values:\n";
var_export($_SESSION);

// 判定に使っていそうな代表キーをざっと確認
$keys = ['user_id','uid','member_id','account_id','admin_id','login_user_id','email','nickname','display_name','name'];
$present = [];
foreach ($keys as $k) if (isset($_SESSION[$k])) $present[$k] = $_SESSION[$k];
echo "\n\nPRESENT_KEYS:\n";
var_export($present);
