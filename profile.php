<?php
$pageTitle = 'My Profile';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();

$pdo = getDB();
$uid = current_user_id();
$errors = [];
$uploadDir = __DIR__ . '/../uploads/profiles';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $userStmt = $pdo->prepare('SELECT profile_photo FROM users WHERE user_id=?');
    $userStmt->execute([$uid]);
    $oldPhoto = $userStmt->fetchColumn();

    if ($action === 'remove_photo') {
        $pdo->prepare('UPDATE users SET profile_photo=NULL WHERE user_id=?')->execute([$uid]);
        if ($oldPhoto && preg_match('/\A[a-f0-9]{32}\.(?:jpg|png|gif|webp)\z/', $oldPhoto)) {
            $oldPath = $uploadDir . DIRECTORY_SEPARATOR . $oldPhoto;
            if (is_file($oldPath) && !unlink($oldPath)) {
                error_log('Could not remove profile photo: ' . $oldPath);
            }
        }
        audit($pdo, $uid, 'REMOVE_PROFILE_PHOTO', 'users', $uid);
        set_flash('success', 'Profile photo removed.');
        header('Location: /kghbms/users/profile.php');
        exit;
    }

    if ($action !== 'upload_photo') {
        $errors[] = 'Invalid profile photo action.';
    } elseif (!isset($_FILES['profile_photo']) || !is_array($_FILES['profile_photo'])) {
        $errors[] = 'Choose an image file to upload.';
    } else {
        $file = $_FILES['profile_photo'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = match ($file['error']) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The image must be no larger than 3 MB.',
                UPLOAD_ERR_NO_FILE => 'Choose an image file to upload.',
                default => 'The image could not be uploaded. Please try again.',
            };
        } elseif ($file['size'] > 3 * 1024 * 1024) {
            $errors[] = 'The image must be no larger than 3 MB.';
        } elseif (!is_uploaded_file($file['tmp_name'])) {
            $errors[] = 'The uploaded file could not be verified.';
        } else {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);
            $extensions = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp',
            ];
            $imageInfo = @getimagesize($file['tmp_name']);
            if (!isset($extensions[$mime]) || $imageInfo === false || $imageInfo['mime'] !== $mime) {
                $errors[] = 'Choose a valid JPEG, PNG, GIF, or WebP image.';
            } elseif ($imageInfo[0] > 4096 || $imageInfo[1] > 4096) {
                $errors[] = 'The image dimensions must not exceed 4096 by 4096 pixels.';
            } elseif (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                $errors[] = 'The profile photo directory could not be created.';
            } else {
                $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
                $destination = $uploadDir . DIRECTORY_SEPARATOR . $filename;
                if (!move_uploaded_file($file['tmp_name'], $destination)) {
                    $errors[] = 'The image could not be saved. Check the server upload permissions.';
                } else {
                    $pdo->prepare('UPDATE users SET profile_photo=? WHERE user_id=?')->execute([$filename, $uid]);
                    if ($oldPhoto && preg_match('/\A[a-f0-9]{32}\.(?:jpg|png|gif|webp)\z/', $oldPhoto)) {
                        $oldPath = $uploadDir . DIRECTORY_SEPARATOR . $oldPhoto;
                        if (is_file($oldPath) && !unlink($oldPath)) {
                            error_log('Could not replace profile photo: ' . $oldPath);
                        }
                    }
                    audit($pdo, $uid, 'UPDATE_PROFILE_PHOTO', 'users', $uid);
                    set_flash('success', 'Profile photo updated.');
                    header('Location: /kghbms/users/profile.php');
                    exit;
                }
            }
        }
    }
}

$user = $pdo->prepare('SELECT u.*, r.role_name FROM users u JOIN roles r ON r.role_id=u.role_id WHERE u.user_id=?');
$user->execute([$uid]);
$u = $user->fetch();
$photoUrl = $u['profile_photo']
    ? '/kghbms/uploads/profiles/' . rawurlencode($u['profile_photo'])
    : null;

// Recent audit activity
$activity = $pdo->prepare('SELECT action, created_at, ip_address FROM audit_logs WHERE user_id=? ORDER BY audit_id DESC LIMIT 10');
$activity->execute([$uid]); $acts = $activity->fetchAll();
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div>
        <h1><i class="fas fa-user-cog me-2 text-primary"></i>My Profile</h1>
        <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item active">Profile</li></ol></nav>
    </div>
    <a href="/kghbms/users/change_password.php" class="btn btn-outline-warning">
        <i class="fas fa-key me-1"></i>Change Password
    </a>
</div>

<div class="row g-4">
    <div class="col-12 col-md-4">
        <div class="card text-center">
            <div class="card-body py-4">
                <?php if ($errors): ?>
                <div class="alert alert-danger text-start"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
                <?php endif; ?>
                <?php if ($photoUrl): ?>
                <img src="<?= e($photoUrl) ?>" alt="<?= e($u['full_name']) ?> profile photo" class="user-avatar user-avatar-image mx-auto mb-3" style="width:70px;height:70px;">
                <?php else: ?>
                <div class="user-avatar mx-auto mb-3" style="width:70px;height:70px;font-size:2rem;">
                    <?= e(strtoupper(substr($u['full_name'],0,1))) ?>
                </div>
                <?php endif; ?>
                <h4 class="mb-1"><?= e($u['full_name']) ?></h4>
                <span class="badge bg-primary mb-2"><?= e($u['role_name']) ?></span>
                <p class="text-muted small mb-0"><code><?= e($u['username']) ?></code></p>
            </div>
            <div class="card-body border-top">
                <h6 class="text-start mb-3">Profile Photo</h6>
                <form method="post" enctype="multipart/form-data" class="text-start">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="upload_photo">
                    <label for="profile_photo" class="form-label small">Choose an image (JPEG, PNG, GIF, or WebP; max 3 MB)</label>
                    <input type="file" id="profile_photo" name="profile_photo" class="form-control mb-3" accept="image/jpeg,image/png,image/gif,image/webp" required>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-upload me-1"></i>Upload Photo</button>
                </form>
                <?php if ($photoUrl): ?>
                <form method="post" class="text-start mt-2">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="remove_photo">
                    <button type="submit" class="btn btn-outline-danger btn-sm"><i class="fas fa-trash me-1"></i>Remove Photo</button>
                </form>
                <?php endif; ?>
            </div>
            <div class="card-footer">
                <table class="table table-sm table-borderless mb-0 text-start">
                    <tr><td class="text-muted small">Email</td><td class="small"><?= e($u['email']??'—') ?></td></tr>
                    <tr><td class="text-muted small">Phone</td><td class="small"><?= e($u['phone']??'—') ?></td></tr>
                    <tr><td class="text-muted small">Status</td><td><?= status_badge($u['status']) ?></td></tr>
                    <tr><td class="text-muted small">Joined</td><td class="small"><?= date('d/m/Y',strtotime($u['created_at'])) ?></td></tr>
                    <tr><td class="text-muted small">Last Login</td><td class="small"><?= $u['last_login']?date('d/m/Y H:i',strtotime($u['last_login'])):'—' ?></td></tr>
                </table>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-8">
        <div class="card">
            <div class="card-header"><i class="fas fa-history me-2"></i>Recent Activity</div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead><tr><th>Action</th><th>Date &amp; Time</th><th>IP Address</th></tr></thead>
                    <tbody>
                        <?php foreach ($acts as $a): ?>
                        <tr>
                            <td><span class="badge bg-light text-dark border"><?= e($a['action']) ?></span></td>
                            <td class="small text-muted"><?= e($a['created_at']) ?></td>
                            <td class="small text-muted"><?= e($a['ip_address'] ?? '—') ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($acts)): ?><tr><td colspan="3" class="text-muted text-center py-2">No activity.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
