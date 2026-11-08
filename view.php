<?php
$pageTitle = 'Admission Details';
require_once __DIR__ . '/../includes/header.php';
require_role(['ADMINISTRATOR','RECEPTION','NURSE','DOCTOR','MANAGER']);

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /kghbms/admissions/list.php'); exit; }
if (has_role(['NURSE']) && !is_admission_assigned_to_nurse($pdo, $id, current_user_id())) {
    http_response_code(403);
    include __DIR__ . '/../includes/403.php';
    exit;
}

$stmt = $pdo->prepare('
    SELECT a.*, p.full_name, p.patient_number, p.gender, p.date_of_birth, p.phone,
           d.name AS dept_name,
           doc.full_name AS doctor_name,
           adm_user.full_name AS admitted_by_name,
           ba.allocation_id, ba.allocated_at, ba.bed_id AS active_bed_id,
           b.bed_number, r.room_number, w.name AS ward_name, w.ward_id
    FROM admissions a
    JOIN patients p ON p.patient_id = a.patient_id
    JOIN departments d ON d.department_id = a.department_id
    LEFT JOIN users doc ON doc.user_id = a.attending_doctor_id
    LEFT JOIN users adm_user ON adm_user.user_id = a.admitted_by
    LEFT JOIN bed_allocations ba ON ba.admission_id = a.admission_id AND ba.is_active = 1
    LEFT JOIN beds b ON b.bed_id = ba.bed_id
    LEFT JOIN rooms r ON r.room_id = b.room_id
    LEFT JOIN wards w ON w.ward_id = r.ward_id
    WHERE a.admission_id = ?
');
$stmt->execute([$id]);
$adm = $stmt->fetch();
if (!$adm) { set_flash('danger','Admission not found.'); header('Location: /kghbms/admissions/list.php'); exit; }

$assignmentErrors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $formAction = $_POST['form_action'] ?? '';
    if ($formAction === 'manage_nurses') {
        require_role(['ADMINISTRATOR','MANAGER']);
        if ($adm['status'] !== 'ACTIVE') {
            $assignmentErrors[] = 'Nurses can only be assigned to active admissions.';
        } else {
            $submittedNurseIds = $_POST['nurse_user_ids'] ?? [];
            if (!is_array($submittedNurseIds)) {
                $assignmentErrors[] = 'Invalid nurse selection.';
            } else {
                $nurseIds = array_values(array_unique(array_filter(array_map('intval', $submittedNurseIds))));
                try {
                    if ($nurseIds) {
                        $placeholders = implode(',', array_fill(0, count($nurseIds), '?'));
                        $validNurses = $pdo->prepare("SELECT COUNT(*) FROM users u
                            JOIN roles r ON r.role_id=u.role_id
                            WHERE u.user_id IN ($placeholders) AND r.role_name='NURSE' AND u.status='ACTIVE'");
                        $validNurses->execute($nurseIds);
                        if ((int)$validNurses->fetchColumn() !== count($nurseIds)) {
                            throw new RuntimeException('One or more selected nurses are inactive or invalid.');
                        }
                    }

                    $pdo->beginTransaction();
                    $pdo->prepare('DELETE FROM admission_nurse_assignments WHERE admission_id=?')->execute([$id]);
                    $insertAssignment = $pdo->prepare('INSERT INTO admission_nurse_assignments
                        (admission_id,nurse_user_id,assigned_by) VALUES (?,?,?)');
                    foreach ($nurseIds as $nurseId) {
                        $insertAssignment->execute([$id, $nurseId, current_user_id()]);
                    }
                    audit($pdo, current_user_id(), 'ASSIGN_NURSES_TO_ADMISSION', 'admissions', $id,
                        'Assigned nurse user IDs: ' . implode(',', $nurseIds));
                    $pdo->commit();
                    set_flash('success', 'Nurse assignments updated.');
                    header("Location: /kghbms/admissions/view.php?id=$id");
                    exit;
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    $assignmentErrors[] = $e->getMessage();
                }
            }
        }
    } elseif ($formAction === 'update_clinical') {
        require_role(['ADMINISTRATOR','DOCTOR']);
        if ($adm['status'] !== 'ACTIVE') {
            $assignmentErrors[] = 'Clinical admission details can only be updated for active admissions.';
        } else {
            $reason = trim($_POST['reason'] ?? '');
            $doctorId = (int)($_POST['attending_doctor_id'] ?? 0);
            if ($doctorId > 0) {
                $doctorCheck = $pdo->prepare("SELECT 1 FROM users u JOIN roles r ON r.role_id=u.role_id
                    WHERE u.user_id=? AND r.role_name='DOCTOR' AND u.status='ACTIVE' LIMIT 1");
                $doctorCheck->execute([$doctorId]);
                if (!$doctorCheck->fetchColumn()) $assignmentErrors[] = 'Selected attending doctor is inactive or invalid.';
            }
            if (!$assignmentErrors) {
                try {
                    $pdo->prepare('UPDATE admissions SET reason=?, attending_doctor_id=? WHERE admission_id=?')
                        ->execute([$reason ?: null, $doctorId ?: null, $id]);
                    audit($pdo, current_user_id(), 'UPDATE_ADMISSION_CLINICAL', 'admissions', $id,
                        'Updated clinical admission details.');
                    set_flash('success', 'Clinical admission details updated.');
                    header("Location: /kghbms/admissions/view.php?id=$id");
                    exit;
                } catch (Throwable $e) {
                    $assignmentErrors[] = $e->getMessage();
                }
            }
        }
    } else {
        http_response_code(400);
        exit('Invalid request.');
    }
}

$activeDoctors = $pdo->query("SELECT u.user_id, u.full_name FROM users u
    JOIN roles r ON r.role_id=u.role_id
    WHERE r.role_name='DOCTOR' AND u.status='ACTIVE' ORDER BY u.full_name")->fetchAll();
$activeNurses = $pdo->query("SELECT u.user_id, u.full_name FROM users u
    JOIN roles r ON r.role_id=u.role_id
    WHERE r.role_name='NURSE' AND u.status='ACTIVE' ORDER BY u.full_name")->fetchAll();
$assignedNursesStmt = $pdo->prepare('SELECT ana.nurse_user_id, u.full_name
    FROM admission_nurse_assignments ana JOIN users u ON u.user_id=ana.nurse_user_id
    WHERE ana.admission_id=? ORDER BY u.full_name');
$assignedNursesStmt->execute([$id]);
$assignedNurses = $assignedNursesStmt->fetchAll();
$assignedNurseIds = array_map(static fn($nurse) => (int)$nurse['nurse_user_id'], $assignedNurses);

// Allocation history
$allocs = $pdo->prepare('SELECT ba.*, b.bed_number, r.room_number, w.name AS ward_name, u.full_name AS by_name
    FROM bed_allocations ba JOIN beds b ON b.bed_id=ba.bed_id JOIN rooms r ON r.room_id=b.room_id
    JOIN wards w ON w.ward_id=r.ward_id JOIN users u ON u.user_id=ba.allocated_by
    WHERE ba.admission_id=? ORDER BY ba.allocated_at DESC');
$allocs->execute([$id]);
$allocHistory = $allocs->fetchAll();

// Transfers
$transfers = $pdo->prepare('SELECT bt.*, bf.bed_number AS from_bed, bt2.bed_number AS to_bed,
    wf.name AS from_ward, wt.name AS to_ward, u.full_name AS by_name
    FROM bed_transfers bt
    JOIN beds bf ON bf.bed_id=bt.from_bed_id JOIN rooms rf ON rf.room_id=bf.room_id JOIN wards wf ON wf.ward_id=rf.ward_id
    JOIN beds bt2 ON bt2.bed_id=bt.to_bed_id JOIN rooms rt ON rt.room_id=bt2.room_id JOIN wards wt ON wt.ward_id=rt.ward_id
    JOIN users u ON u.user_id=bt.transferred_by
    WHERE bt.admission_id=? ORDER BY bt.transfer_date DESC');
$transfers->execute([$id]);
$transferList = $transfers->fetchAll();

// Discharge
$dis = $pdo->prepare('SELECT dis.*, u.full_name AS by_name FROM discharges dis JOIN users u ON u.user_id=dis.discharged_by WHERE dis.admission_id=?');
$dis->execute([$id]);
$discharge = $dis->fetch();
?>
<div class="page-header">
    <div>
        <h1><i class="fas fa-procedures me-2 text-primary"></i>Admission #<?= $adm['admission_id'] ?></h1>
        <nav aria-label="breadcrumb"><ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/kghbms/admissions/list.php">Admissions</a></li>
            <li class="breadcrumb-item active">#<?= $adm['admission_id'] ?></li>
        </ol></nav>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if ($adm['status']==='ACTIVE'): ?>
            <?php if (!$adm['active_bed_id'] && has_role(['ADMINISTRATOR','MANAGER'])): ?>
            <a href="/kghbms/admissions/allocate.php?admission_id=<?= $id ?>" class="btn btn-success"><i class="fas fa-bed me-1"></i>Allocate Bed</a>
            <?php endif; ?>
            <?php if ($adm['active_bed_id'] && has_role(['ADMINISTRATOR','NURSE','MANAGER'])): ?>
            <a href="/kghbms/admissions/transfer.php?admission_id=<?= $id ?>" class="btn btn-warning"><i class="fas fa-exchange-alt me-1"></i>Transfer</a>
            <?php endif; ?>
            <?php if (has_role(['ADMINISTRATOR','RECEPTION','DOCTOR','MANAGER'])): ?>
            <a href="/kghbms/discharges/discharge.php?admission_id=<?= $id ?>" class="btn btn-danger"><i class="fas fa-sign-out-alt me-1"></i>Discharge</a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php if ($assignmentErrors): ?>
<div class="alert alert-danger"><ul class="mb-0"><?php foreach ($assignmentErrors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<?php if (has_role(['ADMINISTRATOR','MANAGER']) && $adm['status'] === 'ACTIVE'): ?>
<div class="card mb-4">
    <div class="card-header"><i class="fas fa-user-nurse me-2"></i>Assigned Nurses</div>
    <div class="card-body">
        <form method="post" class="d-flex flex-column flex-md-row gap-3 align-items-md-end" data-loading>
            <?= csrf_field() ?>
            <input type="hidden" name="form_action" value="manage_nurses">
            <div class="flex-grow-1">
                <label class="form-label fw-semibold">Nurses responsible for this patient</label>
                <select name="nurse_user_ids[]" class="form-select" multiple size="4">
                    <?php foreach ($activeNurses as $nurse): ?>
                    <option value="<?= (int)$nurse['user_id'] ?>" <?= in_array((int)$nurse['user_id'], $assignedNurseIds, true) ? 'selected' : '' ?>><?= e($nurse['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Use Ctrl or Command to select multiple nurses. Nurses can view and update only patients assigned to them.</div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Assignments</button>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if (has_role(['ADMINISTRATOR','DOCTOR']) && $adm['status'] === 'ACTIVE'): ?>
<div class="card mb-4">
    <div class="card-header"><i class="fas fa-notes-medical me-2"></i>Clinical Admission Details</div>
    <div class="card-body">
        <form method="post" class="row g-3" data-loading>
            <?= csrf_field() ?>
            <input type="hidden" name="form_action" value="update_clinical">
            <div class="col-12 col-md-6">
                <label class="form-label fw-semibold">Attending Doctor</label>
                <select name="attending_doctor_id" class="form-select">
                    <option value="">Not assigned</option>
                    <?php foreach ($activeDoctors as $doctor): ?>
                    <option value="<?= (int)$doctor['user_id'] ?>" <?= (int)$adm['attending_doctor_id'] === (int)$doctor['user_id'] ? 'selected' : '' ?>><?= e($doctor['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label fw-semibold">Clinical Input / Reason</label>
                <textarea name="reason" class="form-control" rows="3"><?= e($adm['reason'] ?? '') ?></textarea>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Clinical Details</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-12 col-md-5">
        <div class="card mb-3">
            <div class="card-header"><i class="fas fa-info-circle me-2"></i>Admission Details</div>
            <div class="card-body">
                <table class="table table-sm table-borderless mb-0">
                    <tr><td class="text-muted fw-semibold" style="width:42%">Status</td><td><?= status_badge($adm['status']) ?></td></tr>
                    <tr><td class="text-muted fw-semibold">Patient</td><td><a href="/kghbms/patients/view.php?id=<?= $adm['patient_id'] ?>"><?= e($adm['full_name']) ?></a><br><small class="text-muted"><?= e($adm['patient_number']) ?></small></td></tr>
                    <tr><td class="text-muted fw-semibold">Type</td><td><span class="badge bg-<?= $adm['admission_type']==='EMERGENCY'?'danger':'primary' ?>"><?= e($adm['admission_type']) ?></span></td></tr>
                    <tr><td class="text-muted fw-semibold">Department</td><td><?= e($adm['dept_name']) ?></td></tr>
                    <tr><td class="text-muted fw-semibold">Doctor</td><td><?= e($adm['doctor_name'] ?? '—') ?></td></tr>
                    <tr><td class="text-muted fw-semibold">Admitted</td><td><?= e($adm['admission_date']) ?></td></tr>
                    <tr><td class="text-muted fw-semibold">Admitted by</td><td><?= e($adm['admitted_by_name']) ?></td></tr>
                    <tr><td class="text-muted fw-semibold">Nurse(s)</td><td><?= $assignedNurses ? e(implode(', ', array_column($assignedNurses, 'full_name'))) : '—' ?></td></tr>
                    <tr><td class="text-muted fw-semibold">Reason</td><td><?= e($adm['reason'] ?? '—') ?></td></tr>
                </table>
            </div>
        </div>
        <!-- Current Bed -->
        <div class="card">
            <div class="card-header"><i class="fas fa-bed me-2"></i>Current Bed</div>
            <div class="card-body text-center py-3">
                <?php if ($adm['active_bed_id']): ?>
                <div class="display-6 text-danger mb-1"><i class="fas fa-bed"></i></div>
                <h4 class="text-danger"><?= e($adm['bed_number']) ?></h4>
                <p class="text-muted mb-1"><?= e($adm['room_number']) ?> · <?= e($adm['ward_name']) ?></p>
                <p class="text-muted small">Allocated: <?= e($adm['allocated_at']) ?></p>
                <?php elseif ($adm['status']==='ACTIVE'): ?>
                <div class="display-6 text-warning mb-1"><i class="fas fa-exclamation-circle"></i></div>
                <p class="text-warning fw-semibold">No bed allocated yet</p>
                <?php if (has_role(['ADMINISTRATOR','MANAGER'])): ?>
                <a href="/kghbms/admissions/allocate.php?admission_id=<?= $id ?>" class="btn btn-sm btn-success">Allocate Now</a>
                <?php endif; ?>
                <?php else: ?>
                <div class="text-muted"><i class="fas fa-check-circle text-success me-1"></i>Admission closed</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-7">
        <!-- Allocation History -->
        <div class="card mb-3">
            <div class="card-header"><i class="fas fa-history me-2"></i>Bed Allocation History</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Bed</th><th>Ward</th><th>Allocated</th><th>Released</th><th>Reason</th></tr></thead>
                    <tbody>
                        <?php foreach ($allocHistory as $al): ?>
                        <tr class="<?= $al['is_active'] ? 'table-success' : '' ?>">
                            <td class="fw-semibold"><?= e($al['bed_number']) ?> <?= $al['is_active'] ? '<span class="badge bg-success">Active</span>' : '' ?></td>
                            <td class="small"><?= e($al['ward_name']) ?></td>
                            <td class="small text-muted"><?= e($al['allocated_at']) ?></td>
                            <td class="small text-muted"><?= $al['released_at'] ? e($al['released_at']) : '—' ?></td>
                            <td class="small"><?= e($al['release_reason'] ?? '—') ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($allocHistory)): ?><tr><td colspan="5" class="text-muted text-center py-2">No allocations.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <!-- Transfers -->
        <div class="card mb-3">
            <div class="card-header"><i class="fas fa-exchange-alt me-2"></i>Transfer History</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>From</th><th>To</th><th>Date</th><th>Reason</th><th>By</th></tr></thead>
                    <tbody>
                        <?php foreach ($transferList as $t): ?>
                        <tr>
                            <td><?= e($t['from_bed']) ?> <small class="text-muted"><?= e($t['from_ward']) ?></small></td>
                            <td><?= e($t['to_bed']) ?> <small class="text-muted"><?= e($t['to_ward']) ?></small></td>
                            <td class="small text-muted"><?= e($t['transfer_date']) ?></td>
                            <td class="small"><?= e($t['reason']) ?></td>
                            <td class="small"><?= e($t['by_name']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($transferList)): ?><tr><td colspan="5" class="text-muted text-center py-2">No transfers.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <!-- Discharge -->
        <?php if ($discharge): ?>
        <div class="card border-secondary">
            <div class="card-header bg-secondary text-white"><i class="fas fa-sign-out-alt me-2"></i>Discharge Record</div>
            <div class="card-body">
                <table class="table table-sm table-borderless mb-0">
                    <tr><td class="fw-semibold text-muted" style="width:35%">Type</td><td><?= e($discharge['discharge_type']) ?></td></tr>
                    <tr><td class="fw-semibold text-muted">Date</td><td><?= e($discharge['discharge_date']) ?></td></tr>
                    <tr><td class="fw-semibold text-muted">By</td><td><?= e($discharge['by_name']) ?></td></tr>
                    <tr><td class="fw-semibold text-muted">Summary</td><td><?= e($discharge['summary'] ?? '—') ?></td></tr>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
