<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();
$pdo = getDB();
require_role(['ADMINISTRATOR','MANAGER']);

$errors = [];
$form   = ['bed_type' => 'STANDARD'];
$rooms  = $pdo->query("SELECT r.room_id, r.room_number, r.room_type, w.name AS ward_name, d.name AS dept_name
    FROM rooms r JOIN wards w ON w.ward_id=r.ward_id AND w.status='ACTIVE'
    JOIN departments d ON d.department_id=w.department_id
    WHERE r.status='ACTIVE' ORDER BY d.name, w.name, r.room_number")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $form = [
        'room_id'    => (int)($_POST['room_id'] ?? 0),
        'bed_number' => trim($_POST['bed_number'] ?? ''),
        'bed_type'   => $_POST['bed_type'] ?? 'STANDARD',
        'notes'      => trim($_POST['notes'] ?? ''),
    ];
    if (!$form['room_id'])    $errors[] = 'Room is required.';
    if (!$form['bed_number']) $errors[] = 'Bed number is required.';
    if (!in_array($form['bed_type'], ['STANDARD','ICU','PEDIATRIC','MATERNITY','ELECTRIC'])) $errors[] = 'Invalid bed type.';

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            $pdo->prepare('INSERT INTO beds (room_id,bed_number,bed_type,current_status,notes) VALUES (?,?,?,?,?)')
                ->execute([$form['room_id'], $form['bed_number'], $form['bed_type'], 'AVAILABLE', $form['notes'] ?: null]);
            $newId = (int)$pdo->lastInsertId();
            $pdo->prepare('INSERT INTO bed_status_history (bed_id,old_status,new_status,changed_by,reason) VALUES (?,NULL,?,?,?)')
                ->execute([$newId, 'AVAILABLE', current_user_id(), 'Initial registration']);
            audit($pdo, current_user_id(), 'ADD_BED', 'beds', $newId, "Bed: {$form['bed_number']}");
            $pdo->commit();
            set_flash('success', "Bed <strong>{$form['bed_number']}</strong> added successfully.");
            header('Location: /kghbms/beds/list.php');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = str_contains($e->getMessage(), 'Duplicate') ? 'Bed number already exists in this room.' : $e->getMessage();
        }
    }
}

$pageTitle = 'Add Bed';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div><h1><i class="fas fa-plus me-2 text-primary"></i>Add Bed</h1>
    <nav aria-label="breadcrumb"><ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/kghbms/beds/list.php">Beds</a></li>
        <li class="breadcrumb-item active">Add</li>
    </ol></nav></div>
</div>
<div class="row justify-content-center">
<div class="col-12 col-md-6">
<div class="card">
    <div class="card-header"><i class="fas fa-bed me-2"></i>New Bed</div>
    <div class="card-body">
        <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
        <form method="post" data-loading>
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label fw-semibold">Room <span class="required-mark">*</span></label>
                <select name="room_id" class="form-select" required>
                    <option value="">Select room…</option>
                    <?php foreach ($rooms as $r): ?>
                    <option value="<?= $r['room_id'] ?>" <?= ($form['room_id']??0)==$r['room_id']?'selected':'' ?>>
                        <?= e($r['dept_name'].' › '.$r['ward_name'].' › Room '.$r['room_number'].' ('.$r['room_type'].')') ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Bed Number <span class="required-mark">*</span></label>
                <input type="text" name="bed_number" class="form-control" required maxlength="20" placeholder="e.g. MA-101-A" value="<?= e($form['bed_number']??'') ?>">
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Bed Type</label>
                <select name="bed_type" class="form-select">
                    <?php foreach (['STANDARD','ICU','PEDIATRIC','MATERNITY','ELECTRIC'] as $t): ?>
                    <option value="<?= $t ?>" <?= ($form['bed_type']??'STANDARD')===$t?'selected':'' ?>><?= $t ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-4">
                <label class="form-label fw-semibold">Notes</label>
                <textarea name="notes" class="form-control" rows="2" maxlength="255"><?= e($form['notes']??'') ?></textarea>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Add Bed</button>
                <a href="/kghbms/beds/list.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
</div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
