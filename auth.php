<?php
/**
 * Authentication & Authorization - Kuyu HBMS
 */
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once __DIR__ . '/../config/database.php';

function require_login(): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: /kghbms/login.php');
        exit;
    }
    $timeout = defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : 1200;
    if (isset($_SESSION['last_seen']) && (time() - $_SESSION['last_seen']) > $timeout) {
        session_unset();
        session_destroy();
        header('Location: /kghbms/login.php?expired=1');
        exit;
    }
    $_SESSION['last_seen'] = time();
}

function require_role(array $roles): void {
    require_login();
    if (!in_array($_SESSION['role'] ?? '', $roles, true)) {
        http_response_code(403);
        include __DIR__ . '/../includes/403.php';
        exit;
    }
}

function has_role(array $roles): bool {
    return in_array($_SESSION['role'] ?? '', $roles, true);
}

function current_user_id(): int {
    return (int)($_SESSION['user_id'] ?? 0);
}

function current_role(): string {
    return $_SESSION['role'] ?? '';
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function verify_csrf(): void {
    $submitted = $_POST['csrf'] ?? '';
    $stored    = $_SESSION['csrf'] ?? '';
    if (!hash_equals($stored, $submitted)) {
        http_response_code(400);
        die('Invalid request token. Please go back and try again.');
    }
}
