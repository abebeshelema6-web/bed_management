<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (!empty($_SESSION['user_id'])) {
    try {
        $pdo = getDB();
        audit($pdo, current_user_id(), 'LOGOUT', 'users', current_user_id());
    } catch (Throwable $e) { /* silent */ }
}

session_unset();
session_destroy();
setcookie(session_name(), '', time() - 3600, '/');
header('Location: /kghbms/login.php');
exit;
