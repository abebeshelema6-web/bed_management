<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
$pdo = getDB();
require_role(['ADMINISTRATOR','MANAGER','DOCTOR','NURSE','RECEPTION']);

$id = (int)($_GET['id'] ?? 0);
if (!$id) die('Invalid bed.');

$bed = $pdo->prepare('
    SELECT b.*, r.room_number, r.room_type, w.name AS ward_name, w.ward_id, w.ward_type, d.name AS dept_name,
           p.full_name AS patient_name, p.patient_number, p.patient_id,
           adm.admission_id, adm.admission_type, adm.admission_date,
           ba.allocated_at
    FROM beds b
    JOIN rooms r ON r.room_id=b.room_id
    JOIN wards w ON w.ward_id=r.ward_id
    JOIN departments d ON d.department_id=w.department_id
    LEFT JOIN bed_allocations ba ON ba.bed_id=b.bed_id AND ba.is_active=1
    LEFT JOIN admissions adm ON adm.admission_id=ba.admission_id
    LEFT JOIN patients p ON p.patient_id=adm.patient_id
    WHERE b.bed_id=?
');
$bed->execute([$id]);
$b = $bed->fetch();
if (!$b) die('Bed not found.');

$showPatientDetails = $b['patient_id'] && (!has_role(['NURSE']) || ($b['admission_id'] && is_admission_assigned_to_nurse($pdo, (int)$b['admission_id'], current_user_id())));
$canEdit = has_role(['ADMINISTRATOR','MANAGER','NURSE']);
?>
<div class="modal-header bg-<?= BED_STATUS_COLORS[$b['current_status']] ?> text-white">
    <h5 class="modal-title"><i class="fas <?= BED_STATUS_ICONS[$b['current_status']] ?> me-2"></i>Bed <?= e($b['bed_number']) ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
    <table class="table table-sm table-borderless mb-0">
        <tr><td class="text-muted fw-semibold" style="width:35%">Status</td><td><?= status_badge($b['current_status']) ?></td></tr>
        <tr><td class="text-muted fw-semibold">Ward</td><td><?= e($b['ward_name']) ?> (<?= e($b['ward_type']) ?>)</td></tr>
        <tr><td class="text-muted fw-semibold">Room</td><td><?= e($b['room_number']) ?> (<?= e($b['room_type']) ?>)</td></tr>
        <tr><td class="text-muted fw-semibold">Bed Type</td><td><?= e($b['bed_type']) ?></td></tr>
        <?php if ($showPatientDetails): ?>
        <tr><td class="text-muted fw-semibold">Patient</td><td><a href="/kghbms/patients/view.php?id=<?= $b['patient_id'] ?>" target="_blank"><?= e($b['patient_name']) ?></a><br><small class="text-muted"><?= e($b['patient_number']) ?></small></td></tr>
        <tr><td class="text-muted fw-semibold">Admission</td><td><span class="badge bg-<?= $b['admission_type']==='EMERGENCY'?'danger':'primary' ?>"><?= e($b['admission_type']) ?></span><br><small class="text-muted"><?= e($b['admission_date']) ?></small></td></tr>
        <tr><td class="text-muted fw-semibold">Allocated</td><td><?= e($b['allocated_at']) ?></td></tr>
        <?php endif; ?>
        <tr><td class="text-muted fw-semibold">Notes</td><td><?= e($b['notes'] ?? '—') ?></td></tr>
    </table>
</div>
<div class="modal-footer d-flex justify-content-between flex-wrap gap-2">
    <?php if ($canEdit): ?>
    <div class="btn-group btn-group-sm">
        <?php if ($b['current_status'] === 'CLEANING'): ?>
        <form method="post" action="/kghbms/beds/change_status.php" class="d-inline" data-loading>
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="bed_id" value="<?= $id ?>">
            <input type="hidden" name="new_status" value="AVAILABLE">
            <input type="hidden" name="reason" value="Cleaning completed">
            <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-check me-1"></i>Mark Clean</button>
        </form>
        <?php endif; ?>
        <?php if ($b['current_status'] === 'AVAILABLE'): ?>
        <a href="/kghbms/beds/change_status.php?bed_id=<?= $id ?>" class="btn btn-sm btn-outline-secondary">Change Status</a>
        <?php endif; ?>
        <?php if ($b['current_status'] === 'OCCUPIED' && $b['admission_id'] && has_role(['ADMINISTRATOR','MANAGER','NURSE']) && (!has_role(['NURSE']) || is_admission_assigned_to_nurse($pdo, (int)$b['admission_id'], current_user_id()))): ?>
        <a href="/kghbms/admissions/transfer.php?admission_id=<?= $b['admission_id'] ?>" class="btn btn-sm btn-warning"><i class="fas fa-exchange-alt me-1"></i>Transfer</a>
        <?php endif; ?>
        <?php if ($b['current_status'] === 'OCCUPIED' && $b['admission_id'] && has_role(['ADMINISTRATOR','MANAGER','DOCTOR','RECEPTION'])): ?>
        <a href="/kghbms/discharges/discharge.php?admission_id=<?= $b['admission_id'] ?>" class="btn btn-sm btn-danger"><i class="fas fa-sign-out-alt me-1"></i>Discharge</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <button type="button" class="btn btn-sm btn-secondary ms-auto" data-bs-dismiss="modal">Close</button>
</div>
