<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();
$pdo = getDB();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $userRow = $pdo->prepare('SELECT password_hash FROM users WHERE user_id=?');
    $userRow->execute([current_user_id()]); $userRow = $userRow->fetch();

    if (!password_verify($current, $userRow['password_hash'])) $errors[] = 'Current password is incorrect.';
    if (strlen($new) < 8)  $errors[] = 'New password must be at least 8 characters.';
    if (!preg_match('/[A-Za-z]/', $new))    $errors[] = 'New password must contain a letter.';
    if (!preg_match('/[0-9@#$!_]/', $new))  $errors[] = 'New password must contain a number or symbol.';
    if ($new !== $confirm) $errors[] = 'New password and confirmation do not match.';

    if (empty($errors)) {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $pdo->prepare('UPDATE users SET password_hash=? WHERE user_id=?')->execute([$hash, current_user_id()]);
        audit($pdo, current_user_id(), 'CHANGE_PASSWORD', 'users', current_user_id());
        set_flash('success', 'Password changed successfully.');
        header('Location: /kghbms/users/profile.php'); exit;
    }
}

$pageTitle = 'Change Password';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div><h1><i class="fas fa-key me-2 text-warning"></i>Change Password</h1>
    <nav aria-label="breadcrumb"><ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/kghbms/users/profile.php">Profile</a></li>
        <li class="breadcrumb-item active">Change Password</li>
    </ol></nav></div>
</div>
<div class="row justify-content-center">
<div class="col-12 col-md-5">
<div class="card">
    <div class="card-header"><i class="fas fa-lock me-2"></i>Update Your Password</div>
    <div class="card-body">
        <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
        <form method="post" data-loading>
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label fw-semibold">Current Password <span class="required-mark">*</span></label>
                <input type="password" name="current_password" class="form-control" required autocomplete="current-password">
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">New Password <span class="required-mark">*</span></label>
                <input type="password" name="new_password" class="form-control" required minlength="8" autocomplete="new-password">
                <div class="form-text">Min 8 characters with at least one letter and one number/symbol.</div>
            </div>
            <div class="mb-4">
                <label class="form-label fw-semibold">Confirm New Password <span class="required-mark">*</span></label>
                <input type="password" name="confirm_password" class="form-control" required minlength="8" autocomplete="new-password">
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-warning"><i class="fas fa-save me-1"></i>Update Password</button>
                <a href="/kghbms/users/profile.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
</div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
