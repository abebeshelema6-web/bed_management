<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();
$pdo = getDB();
require_role(['ADMINISTRATOR','MANAGER','NURSE']);

$bedId  = (int)($_GET['bed_id'] ?? $_POST['bed_id'] ?? 0);
$errors = [];
if (!$bedId) { header('Location: /kghbms/beds/list.php'); exit; }

$bedRow = $pdo->prepare('SELECT b.*, r.room_number, w.name AS ward_name
    FROM beds b JOIN rooms r ON r.room_id=b.room_id JOIN wards w ON w.ward_id=r.ward_id WHERE b.bed_id=?');
$bedRow->execute([$bedId]); $b = $bedRow->fetch();
if (!$b) { set_flash('danger','Bed not found.'); header('Location: /kghbms/beds/list.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $newStatus = $_POST['new_status'] ?? '';
    $reason    = trim($_POST['reason'] ?? '');
    $allowed   = BED_TRANSITIONS[$b['current_status']] ?? [];
    if (!in_array($newStatus, $allowed)) $errors[] = 'Invalid status transition.';
    if (!$reason)                        $errors[] = 'Reason is required.';
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            $pdo->prepare('SELECT bed_id FROM beds WHERE bed_id=? FOR UPDATE')->execute([$bedId]);
            set_bed_status($pdo, $bedId, $newStatus, current_user_id(), $reason);
            audit($pdo, current_user_id(), 'CHANGE_BED_STATUS', 'beds', $bedId, "{$b['current_status']} → $newStatus: $reason");
            $pdo->commit();
            set_flash('success', "Bed <strong>{$b['bed_number']}</strong> status changed to <strong>$newStatus</strong>.");
            header('Location: /kghbms/beds/list.php');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = $e->getMessage();
        }
    }
}

$allowed = BED_TRANSITIONS[$b['current_status']] ?? [];
$pageTitle = 'Change Bed Status';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div><h1><i class="fas fa-cog me-2 text-warning"></i>Change Bed Status</h1>
    <nav aria-label="breadcrumb"><ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/kghbms/beds/list.php">Beds</a></li>
        <li class="breadcrumb-item active">Change Status</li>
    </ol></nav></div>
</div>
<div class="row justify-content-center">
<div class="col-12 col-md-5">
<div class="card">
    <div class="card-header"><i class="fas fa-bed me-2"></i><?= e($b['bed_number']) ?> — <?= e($b['ward_name']) ?> / <?= e($b['room_number']) ?></div>
    <div class="card-body">
        <div class="text-center mb-3 p-3 rounded bg-light">
            <div class="small text-muted mb-1">Current Status</div>
            <?= status_badge($b['current_status']) ?>
        </div>
        <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
        <?php if (empty($allowed)): ?>
        <div class="alert alert-warning">No status transitions available from <strong><?= e($b['current_status']) ?></strong>.</div>
        <?php else: ?>
        <form method="post" data-loading>
            <?= csrf_field() ?>
            <input type="hidden" name="bed_id" value="<?= $bedId ?>">
            <div class="mb-3">
                <label class="form-label fw-semibold">New Status <span class="required-mark">*</span></label>
                <select name="new_status" class="form-select" required>
                    <option value="">Select new status…</option>
                    <?php foreach ($allowed as $s): ?>
                    <option value="<?= $s ?>"><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-4">
                <label class="form-label fw-semibold">Reason <span class="required-mark">*</span></label>
                <textarea name="reason" class="form-control" rows="3" required placeholder="Reason for status change…"></textarea>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-warning"><i class="fas fa-save me-1"></i>Update Status</button>
                <a href="/kghbms/beds/list.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>
</div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
