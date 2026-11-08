<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();
$pdo = getDB();
require_role(['ADMINISTRATOR','MANAGER','DOCTOR','NURSE','RECEPTION']);
$canManage = has_role(['ADMINISTRATOR','MANAGER']);

$errors = [];
$form   = ['ward_type' => 'GENERAL', 'capacity' => 0];
$depts  = $pdo->query("SELECT * FROM departments WHERE status='ACTIVE' ORDER BY name")->fetchAll();
$editWardId = (int)($_GET['edit'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    require_role(['ADMINISTRATOR','MANAGER']);

    $editWardId = (int)($_POST['edit_ward_id'] ?? 0);
    $deleteWardId = (int)($_POST['delete_ward_id'] ?? 0);
    if ($deleteWardId > 0) {
        try {
            $pdo->beginTransaction();
            $wardName = $pdo->prepare('SELECT w.name FROM wards w WHERE w.ward_id = ? AND w.status = "ACTIVE" LIMIT 1');
            $wardName->execute([$deleteWardId]);
            $ward = $wardName->fetch();
            if (!$ward) {
                throw new RuntimeException('Ward not found or already inactive.');
            }

            $pdo->prepare('UPDATE wards SET status = "INACTIVE" WHERE ward_id = ?')->execute([$deleteWardId]);
            $pdo->prepare('UPDATE rooms SET status = "INACTIVE" WHERE ward_id = ? AND status = "ACTIVE"')->execute([$deleteWardId]);
            $pdo->prepare('UPDATE beds b JOIN rooms r ON r.room_id = b.room_id SET b.is_active = 0 WHERE r.ward_id = ? AND b.is_active = 1')->execute([$deleteWardId]);

            audit($pdo, current_user_id(), 'DELETE_WARD', 'wards', $deleteWardId, "Deleted: {$ward['name']}");
            $pdo->commit();
            set_flash('success', "Ward <strong>{$ward['name']}</strong> archived.");
            header('Location: /kghbms/beds/wards.php');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = $e->getMessage();
        }
    } elseif ($editWardId > 0) {
        $form = [
            'department_id' => (int)($_POST['department_id'] ?? 0),
            'name'          => trim($_POST['name'] ?? ''),
            'ward_type'     => $_POST['ward_type'] ?? 'GENERAL',
            'capacity'      => (int)($_POST['capacity'] ?? 0),
        ];
        if (!$form['department_id']) $errors[] = 'Department is required.';
        if (!$form['name'])          $errors[] = 'Ward name is required.';
        if ($form['capacity'] < 0)   $errors[] = 'Capacity cannot be negative.';
        if (empty($errors)) {
            try {
                $pdo->prepare('UPDATE wards SET department_id=?, name=?, ward_type=?, capacity=? WHERE ward_id=?')
                    ->execute([$form['department_id'], $form['name'], $form['ward_type'], $form['capacity'], $editWardId]);
                audit($pdo, current_user_id(), 'EDIT_WARD', 'wards', $editWardId, "Updated: {$form['name']}");
                set_flash('success', "Ward <strong>{$form['name']}</strong> updated.");
                header('Location: /kghbms/beds/wards.php');
                exit;
            } catch (PDOException $e) {
                $errors[] = str_contains($e->getMessage(), 'Duplicate') ? 'Ward name already exists in this department.' : $e->getMessage();
            }
        }
    } else {
        $form = [
            'department_id' => (int)($_POST['department_id'] ?? 0),
            'name'          => trim($_POST['name'] ?? ''),
            'ward_type'     => $_POST['ward_type'] ?? 'GENERAL',
            'capacity'      => (int)($_POST['capacity'] ?? 0),
        ];
        if (!$form['department_id']) $errors[] = 'Department is required.';
        if (!$form['name'])          $errors[] = 'Ward name is required.';
        if ($form['capacity'] < 0)   $errors[] = 'Capacity cannot be negative.';
        if (empty($errors)) {
            try {
                $st = $pdo->prepare('INSERT INTO wards (department_id,name,ward_type,capacity)
                                     SELECT department_id,?,?,? FROM departments WHERE department_id=? AND status="ACTIVE"');
                $st->execute([$form['name'], $form['ward_type'], $form['capacity'], $form['department_id']]);
                if ($st->rowCount() === 0) {
                    $errors[] = 'Department not found or inactive.';
                } else {
                    audit($pdo, current_user_id(), 'ADD_WARD', 'wards', (int)$pdo->lastInsertId(), "Added: {$form['name']}");
                    set_flash('success', "Ward <strong>{$form['name']}</strong> added.");
                    header('Location: /kghbms/beds/wards.php');
                    exit;
                }
            } catch (PDOException $e) {
                $errors[] = str_contains($e->getMessage(), 'Duplicate') ? 'Ward name already exists in this department.' : $e->getMessage();
            }
        }
    }
}

if ($editWardId > 0 && empty($_POST)) {
    $existingWard = $pdo->prepare('SELECT * FROM wards WHERE ward_id = ? LIMIT 1');
    $existingWard->execute([$editWardId]);
    $ward = $existingWard->fetch();
    if ($ward) {
        $form = [
            'department_id' => (int)$ward['department_id'],
            'name' => $ward['name'],
            'ward_type' => $ward['ward_type'],
            'capacity' => (int)$ward['capacity'],
        ];
    }
}

$wards = $pdo->query("SELECT w.*, d.name AS dept_name, COUNT(r.room_id) AS rooms
    FROM wards w JOIN departments d ON d.department_id=w.department_id
    LEFT JOIN rooms r ON r.ward_id=w.ward_id
    GROUP BY w.ward_id ORDER BY d.name, w.name")->fetchAll();

$pageTitle = 'Wards';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div><h1><i class="fas fa-layer-group me-2 text-primary"></i>Wards</h1>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item active">Wards</li></ol></nav></div>
</div>
<div class="row g-4">
    <?php if ($canManage): ?>
    <div class="col-12 col-md-4">
        <div class="card">
            <div class="card-header"><i class="fas fa-<?= $editWardId ? 'edit' : 'plus' ?> me-2"></i><?= $editWardId ? 'Edit Ward' : 'Add Ward' ?></div>
            <div class="card-body">
                <?php if ($errors): ?><div class="alert alert-danger small"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
                <form method="post" data-loading><?= csrf_field() ?>
                    <?php if ($editWardId): ?>
                    <input type="hidden" name="edit_ward_id" value="<?= (int)$editWardId ?>">
                    <?php endif; ?>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Department <span class="required-mark">*</span></label>
                        <select name="department_id" class="form-select form-select-sm" required>
                            <option value="">Select…</option>
                            <?php foreach ($depts as $d): ?>
                            <option value="<?= $d['department_id'] ?>" <?= ($form['department_id']??0)==$d['department_id']?'selected':'' ?>><?= e($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Ward Name <span class="required-mark">*</span></label>
                        <input type="text" name="name" class="form-control form-control-sm" required maxlength="100" value="<?= e($form['name']??'') ?>">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Ward Type</label>
                        <select name="ward_type" class="form-select form-select-sm">
                            <?php foreach (['GENERAL','ICU','MATERNITY','PEDIATRIC','SURGICAL','ISOLATION','EMERGENCY'] as $t): ?>
                            <option value="<?= $t ?>" <?= ($form['ward_type']??'GENERAL')===$t?'selected':'' ?>><?= $t ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Capacity</label>
                        <input type="number" name="capacity" class="form-control form-control-sm" min="0" max="999" value="<?= (int)($form['capacity']??0) ?>">
                    </div>
                    <button type="submit" class="btn btn-<?= $editWardId ? 'warning' : 'primary' ?> btn-sm w-100"><i class="fas fa-<?= $editWardId ? 'save' : 'plus' ?> me-1"></i><?= $editWardId ? 'Save Changes' : 'Add Ward' ?></button>
                    <?php if ($editWardId): ?>
                    <a href="/kghbms/beds/wards.php" class="btn btn-outline-secondary btn-sm w-100 mt-2">Cancel</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <div class="col-12 <?= $canManage ? 'col-md-8' : '' ?>">
        <div class="card">
            <div class="card-header"><i class="fas fa-list me-2"></i>Wards (<?= count($wards) ?>)</div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Ward Name</th><th>Department</th><th>Type</th><th>Capacity</th><th>Rooms</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($wards as $w): ?>
                        <tr>
                            <td class="fw-semibold"><?= e($w['name']) ?></td>
                            <td class="small"><?= e($w['dept_name']) ?></td>
                            <td><span class="badge bg-light text-dark border"><?= e($w['ward_type']) ?></span></td>
                            <td class="text-center"><?= $w['capacity'] ?></td>
                            <td class="text-center"><span class="badge bg-info"><?= $w['rooms'] ?></span></td>
                            <td><?= status_badge($w['status']) ?></td>
                            <td>
                                <?php if ($canManage): ?>
                                <div class="d-flex gap-1">
                                    <a href="/kghbms/beds/wards.php?edit=<?= (int)$w['ward_id'] ?>" class="btn btn-sm btn-outline-warning" title="Edit ward"><i class="fas fa-edit"></i></a>
                                    <form method="post" style="display:inline;" data-loading>
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="delete_ward_id" value="<?= (int)$w['ward_id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Archive this ward and all its rooms and beds?" title="Delete ward"><i class="fas fa-trash"></i></button>
                                    </form>
                                </div>
                                <?php else: ?><span class="text-muted small">View only</span><?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($wards)): ?><tr><td colspan="7" class="text-muted text-center py-3">No wards yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
