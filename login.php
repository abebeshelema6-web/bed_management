<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// Already logged in?
if (!empty($_SESSION['user_id'])) {
    header('Location: /kghbms/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter your username and password.';
    } else {
        try {
            $pdo = getDB();
            $st = $pdo->prepare('SELECT u.user_id, u.password_hash, u.status, u.failed_attempts,
                                        u.full_name, r.role_name
                                 FROM users u JOIN roles r ON r.role_id = u.role_id
                                 WHERE u.username = ?');
            $st->execute([$username]);
            $u = $st->fetch();

            if ($u) {
                if ($u['status'] === 'LOCKED') {
                    $error = 'Your account is locked. Please contact the administrator.';
                    audit($pdo, $u['user_id'], 'LOGIN_BLOCKED', 'users', $u['user_id']);
                } elseif ($u['status'] === 'INACTIVE') {
                    $error = 'Your account is inactive. Please contact the administrator.';
                } elseif (password_verify($password, $u['password_hash'])) {
                    // Success
                    session_regenerate_id(true);
                    $_SESSION['user_id']   = $u['user_id'];
                    $_SESSION['role']      = $u['role_name'];
                    $_SESSION['username']  = $username;
                    $_SESSION['full_name'] = $u['full_name'];
                    $_SESSION['last_seen'] = time();

                    // Reset failed attempts
                    $pdo->prepare("UPDATE users SET failed_attempts = 0, last_login = NOW() WHERE user_id = ?")
                        ->execute([$u['user_id']]);

                    audit($pdo, $u['user_id'], 'LOGIN', 'users', $u['user_id']);
                    header('Location: /kghbms/dashboard.php');
                    exit;
                } else {
                    // Wrong password
                    $attempts = (int)$u['failed_attempts'] + 1;
                    $newStatus = $attempts >= 5 ? 'LOCKED' : $u['status'];
                    $pdo->prepare("UPDATE users SET failed_attempts = ?, status = ? WHERE user_id = ?")
                        ->execute([$attempts, $newStatus, $u['user_id']]);
                    audit($pdo, $u['user_id'], 'LOGIN_FAILED', 'users', $u['user_id'], "Attempt $attempts");
                    $error = 'Invalid username or password.';
                    if ($newStatus === 'LOCKED') {
                        $error = 'Too many failed attempts. Account locked. Contact the administrator.';
                    }
                }
            } else {
                audit($pdo, null, 'LOGIN_FAILED', 'users', null, "Unknown user: $username");
                $error = 'Invalid username or password.';
            }
        } catch (Throwable $e) {
            error_log($e->getMessage());
            $error = 'A system error occurred. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login — Kuyu HBMS</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/kghbms/assets/css/style.css">
</head>
<body>
<div class="login-wrapper">
    <div class="login-card">
        <div class="login-header">
            <div class="mb-3">
                <img src="/kghbms/assets/images/Logos.png" alt="Kuyu General Hospital logo" class="login-logo">
            </div>
            <h2>Kuyu General Hospital</h2>
            <p>Bed Management System</p>
        </div>
        <div class="login-body">
            <?php if (!empty($_GET['expired'])): ?>
            <div class="alert alert-warning py-2 small"><i class="fas fa-clock me-1"></i>Your session expired. Please log in again.</div>
            <?php endif; ?>
            <?php if ($error): ?>
            <div class="alert alert-danger py-2 small"><i class="fas fa-exclamation-triangle me-1"></i><?= e($error) ?></div>
            <?php endif; ?>
            <form method="post" data-loading>
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label fw-semibold"><i class="fas fa-user me-1 text-muted"></i>Username</label>
                    <input type="text" name="username" class="form-control form-control-lg"
                           placeholder="Enter your username"
                           value="<?= e($_POST['username'] ?? '') ?>"
                           autocomplete="username" required autofocus>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold"><i class="fas fa-lock me-1 text-muted"></i>Password</label>
                    <div class="input-group">
                        <input type="password" name="password" id="pwdInput" class="form-control form-control-lg"
                               placeholder="Enter your password" autocomplete="current-password" required>
                        <button type="button" class="btn btn-outline-secondary" onclick="togglePwd()">
                            <i class="fas fa-eye" id="pwdEye"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-lg w-100">
                    <i class="fas fa-sign-in-alt me-2"></i>Sign In
                </button>
            </form>
            <div class="text-center mt-3 small text-muted">
                <?= APP_NAME ?> v<?= APP_VERSION ?><br>
                &copy; <?= date('Y') ?> Kuyu General Hospital Bed Management System
            </div>
        </div>
    </div>
</div>
<script>
function togglePwd() {
    const i = document.getElementById('pwdInput');
    const e = document.getElementById('pwdEye');
    if (i.type === 'password') { i.type = 'text'; e.className = 'fas fa-eye-slash'; }
    else { i.type = 'password'; e.className = 'fas fa-eye'; }
}
</script>
</body>
</html>
