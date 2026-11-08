<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();
$pdo = getDB();
require_role(['ADMINISTRATOR','MANAGER','DOCTOR','NURSE','RECEPTION']);

$errors = [];
$form   = [];
$editDepartmentId = (int)($_GET['edit'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    require_role(['ADMINISTRATOR']);

    $editDepartmentId = (int)($_POST['edit_department_id'] ?? 0);
    $deleteDepartmentId = (int)($_POST['delete_department_id'] ?? 0);
    if ($deleteDepartmentId > 0) {
        try {
            $pdo->beginTransaction();
            $deptName = $pdo->prepare('SELECT name FROM departments WHERE department_id = ? AND status = "ACTIVE" LIMIT 1');
            $deptName->execute([$deleteDepartmentId]);
            $department = $deptName->fetch();
            if (!$department) {
                throw new RuntimeException('Department not found or already inactive.');
            }

            $pdo->prepare('UPDATE departments SET status = "INACTIVE" WHERE department_id = ?')->execute([$deleteDepartmentId]);
            $pdo->prepare('UPDATE wards SET status = "INACTIVE" WHERE department_id = ? AND status = "ACTIVE"')->execute([$deleteDepartmentId]);
            $pdo->prepare('UPDATE rooms r JOIN wards w ON w.ward_id = r.ward_id SET r.status = "INACTIVE" WHERE w.department_id = ? AND r.status = "ACTIVE"')->execute([$deleteDepartmentId]);
            $pdo->prepare('UPDATE beds b JOIN rooms r ON r.room_id = b.room_id JOIN wards w ON w.ward_id = r.ward_id SET b.is_active = 0 WHERE w.department_id = ? AND b.is_active = 1')->execute([$deleteDepartmentId]);

            audit($pdo, current_user_id(), 'DELETE_DEPARTMENT', 'departments', $deleteDepartmentId, "Deleted: {$department['name']}");
            $pdo->commit();
            set_flash('success', "Department <strong>{$department['name']}</strong> archived.");
            header('Location: /kghbms/beds/departments.php');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = $e->getMessage();
        }
    } elseif ($editDepartmentId > 0) {
        $form = [
            'name'        => trim($_POST['name'] ?? ''),
            'code'        => strtoupper(trim($_POST['code'] ?? '')),
            'description' => trim($_POST['description'] ?? ''),
        ];
        if (!$form['name']) $errors[] = 'Department name is required.';
        if (!$form['code']) $errors[] = 'Department code is required.';
        if (empty($errors)) {
            try {
                $pdo->prepare('UPDATE departments SET name=?, code=?, description=? WHERE department_id=?')
                    ->execute([$form['name'], $form['code'], $form['description'] ?: null, $editDepartmentId]);
                audit($pdo, current_user_id(), 'EDIT_DEPARTMENT', 'departments', $editDepartmentId, "Updated: {$form['name']}");
                set_flash('success', "Department <strong>{$form['name']}</strong> updated.");
                header('Location: /kghbms/beds/departments.php');
                exit;
            } catch (PDOException $e) {
                $errors[] = str_contains($e->getMessage(),'Duplicate') ? 'Department name or code already exists.' : $e->getMessage();
            }
        }
    } else {
        $form = [
            'name'        => trim($_POST['name'] ?? ''),
            'code'        => strtoupper(trim($_POST['code'] ?? '')),
            'description' => trim($_POST['description'] ?? ''),
        ];
        if (!$form['name']) $errors[] = 'Department name is required.';
        if (!$form['code']) $errors[] = 'Department code is required.';
        if (empty($errors)) {
            try {
                $pdo->prepare('INSERT INTO departments (name,code,description) VALUES (?,?,?)')
                    ->execute([$form['name'], $form['code'], $form['description'] ?: null]);
                audit($pdo, current_user_id(), 'ADD_DEPARTMENT', 'departments', (int)$pdo->lastInsertId(), "Added: {$form['name']}");
                set_flash('success', "Department <strong>{$form['name']}</strong> added.");
                header('Location: /kghbms/beds/departments.php');
                exit;
            } catch (PDOException $e) {
                $errors[] = str_contains($e->getMessage(),'Duplicate') ? 'Department name or code already exists.' : $e->getMessage();
            }
        }
    }
}

if ($editDepartmentId > 0 && empty($_POST)) {
    $existingDepartment = $pdo->prepare('SELECT * FROM departments WHERE department_id = ? LIMIT 1');
    $existingDepartment->execute([$editDepartmentId]);
    $department = $existingDepartment->fetch();
    if ($department) {
        $form = [
            'name' => $department['name'],
            'code' => $department['code'],
            'description' => $department['description'] ?? '',
        ];
    }
}

$depts = $pdo->query("SELECT d.*, COUNT(w.ward_id) AS wards FROM departments d LEFT JOIN wards w ON w.department_id=d.department_id GROUP BY d.department_id ORDER BY d.name")->fetchAll();

$pageTitle = 'Departments';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div><h1><i class="fas fa-building me-2 text-primary"></i>Departments</h1>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item active">Departments</li></ol></nav></div>
</div>
<div class="row g-4">
    <?php if (has_role(['ADMINISTRATOR'])): ?>
    <div class="col-12 col-md-4">
        <div class="card">
            <div class="card-header"><i class="fas fa-<?= $editDepartmentId ? 'edit' : 'plus' ?> me-2"></i><?= $editDepartmentId ? 'Edit Department' : 'Add Department' ?></div>
            <div class="card-body">
                <?php if ($errors): ?><div class="alert alert-danger small"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
                <form method="post" data-loading><?= csrf_field() ?>
                    <?php if ($editDepartmentId): ?>
                    <input type="hidden" name="edit_department_id" value="<?= (int)$editDepartmentId ?>">
                    <?php endif; ?>
                    <div class="mb-2"><label class="form-label small fw-semibold">Name <span class="required-mark">*</span></label>
                    <input type="text" name="name" class="form-control form-control-sm" required maxlength="100" value="<?= e($form['name']??'') ?>"></div>
                    <div class="mb-2"><label class="form-label small fw-semibold">Code <span class="required-mark">*</span></label>
                    <input type="text" name="code" class="form-control form-control-sm" required maxlength="20" placeholder="e.g. MED" value="<?= e($form['code']??'') ?>"></div>
                    <div class="mb-3"><label class="form-label small fw-semibold">Description</label>
                    <textarea name="description" class="form-control form-control-sm" rows="2"><?= e($form['description']??'') ?></textarea></div>
                    <button type="submit" class="btn btn-<?= $editDepartmentId ? 'warning' : 'primary' ?> btn-sm w-100"><i class="fas fa-<?= $editDepartmentId ? 'save' : 'plus' ?> me-1"></i><?= $editDepartmentId ? 'Save Changes' : 'Add Department' ?></button>
                    <?php if ($editDepartmentId): ?>
                    <a href="/kghbms/beds/departments.php" class="btn btn-outline-secondary btn-sm w-100 mt-2">Cancel</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <div class="col-12 <?= has_role(['ADMINISTRATOR']) ? 'col-md-8' : '' ?>">
        <div class="card">
            <div class="card-header"><i class="fas fa-list me-2"></i>Departments (<?= count($depts) ?>)</div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Name</th><th>Code</th><th>Description</th><th>Wards</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($depts as $d): ?>
                        <tr>
                            <td class="fw-semibold"><?= e($d['name']) ?></td>
                            <td><span class="badge bg-secondary"><?= e($d['code']) ?></span></td>
                            <td class="small text-muted"><?= e($d['description'] ?? '—') ?></td>
                            <td class="text-center"><span class="badge bg-info"><?= $d['wards'] ?></span></td>
                            <td><?= status_badge($d['status']) ?></td>
                            <td>
                                <?php if (has_role(['ADMINISTRATOR'])): ?>
                                <div class="d-flex gap-1">
                                    <a href="/kghbms/beds/departments.php?edit=<?= (int)$d['department_id'] ?>" class="btn btn-sm btn-outline-warning" title="Edit department"><i class="fas fa-edit"></i></a>
                                    <form method="post" style="display:inline;" data-loading>
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="delete_department_id" value="<?= (int)$d['department_id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Archive this department and all its wards, rooms, and beds?" title="Delete department"><i class="fas fa-trash"></i></button>
                                    </form>
                                </div>
                                <?php else: ?><span class="text-muted small">View only</span><?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($depts)): ?><tr><td colspan="6" class="text-muted text-center py-3">No departments yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
