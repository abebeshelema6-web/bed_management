<?php
$pageTitle = 'Patient Details';
require_once __DIR__ . '/../includes/header.php';
require_role(['ADMINISTRATOR','RECEPTION','NURSE','DOCTOR','MANAGER']);

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /kghbms/patients/list.php'); exit; }
if (has_role(['NURSE']) && !is_patient_assigned_to_nurse($pdo, $id, current_user_id())) {
    http_response_code(403);
    include __DIR__ . '/../includes/403.php';
    exit;
}

$patient = $pdo->prepare('SELECT p.*, u.full_name AS registered_by FROM patients p LEFT JOIN users u ON u.user_id = p.created_by WHERE p.patient_id = ?');
$patient->execute([$id]);
$p = $patient->fetch();
if (!$p) { set_flash('danger','Patient not found.'); header('Location: /kghbms/patients/list.php'); exit; }

// Admission history
$admissions = $pdo->prepare('
    SELECT a.*, d.name AS dept_name,
           ba.bed_id, b.bed_number, w.name AS ward_name,
           dis.discharge_type, dis.discharge_date,
           doc.full_name AS doctor_name
    FROM admissions a
    JOIN departments d ON d.department_id = a.department_id
    LEFT JOIN bed_allocations ba ON ba.admission_id = a.admission_id AND ba.is_active = 1
    LEFT JOIN beds b ON b.bed_id = ba.bed_id
    LEFT JOIN rooms r ON r.room_id = b.room_id
    LEFT JOIN wards w ON w.ward_id = r.ward_id
    LEFT JOIN discharges dis ON dis.admission_id = a.admission_id
    LEFT JOIN users doc ON doc.user_id = a.attending_doctor_id
    WHERE a.patient_id = ?
    ' . (has_role(['NURSE']) ? 'AND EXISTS (SELECT 1 FROM admission_nurse_assignments ana WHERE ana.admission_id=a.admission_id AND ana.nurse_user_id=?)' : '') . '
    ORDER BY a.admission_date DESC
');
$admissionParams = [$id];
if (has_role(['NURSE'])) $admissionParams[] = current_user_id();
$admissions->execute($admissionParams);
$adms = $admissions->fetchAll();

$pageTitle = 'Patient: ' . $p['full_name'];
?>
<div class="page-header">
    <div>
        <h1><i class="fas fa-user me-2 text-primary"></i><?= e($p['full_name']) ?></h1>
        <nav aria-label="breadcrumb"><ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/kghbms/patients/list.php">Patients</a></li>
            <li class="breadcrumb-item active"><?= e($p['patient_number']) ?></li>
        </ol></nav>
    </div>
    <div class="d-flex gap-2">
        <?php if (has_role(['ADMINISTRATOR','RECEPTION','MANAGER']) || (has_role(['NURSE']) && is_patient_assigned_to_nurse($pdo, $id, current_user_id()))): ?>
        <a href="/kghbms/patients/edit.php?id=<?= $id ?>" class="btn btn-outline-warning"><i class="fas fa-edit me-1"></i>Edit</a>
        <?php endif; ?>
        <?php if (has_role(['ADMINISTRATOR','RECEPTION','MANAGER','DOCTOR'])): ?>
        <a href="/kghbms/admissions/admit.php?patient_id=<?= $id ?>" class="btn btn-primary"><i class="fas fa-procedures me-1"></i>Admit</a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-4">
    <div class="col-12 col-md-5">
        <div class="card">
            <div class="card-header"><i class="fas fa-id-card me-2"></i>Patient Profile</div>
            <div class="card-body">
                <div class="text-center mb-3">
                    <div class="user-avatar mx-auto mb-2" style="width:60px;height:60px;font-size:1.6rem;">
                        <?= strtoupper(substr($p['full_name'],0,1)) ?>
                    </div>
                    <h5 class="mb-1"><?= e($p['full_name']) ?></h5>
                    <span class="badge bg-secondary"><?= e($p['patient_number']) ?></span>
                </div>
                <table class="table table-sm table-borderless mb-0">
                    <tr><td class="text-muted fw-semibold" style="width:40%">Gender</td><td><?= e($p['gender']) ?></td></tr>
                    <tr><td class="text-muted fw-semibold">Date of Birth</td><td><?= e($p['date_of_birth']) ?> (<?= (int)((time()-strtotime($p['date_of_birth']))/31536000) ?> yrs)</td></tr>
                    <tr><td class="text-muted fw-semibold">Phone</td><td><?= e($p['phone'] ?? '—') ?></td></tr>
                    <tr><td class="text-muted fw-semibold">Address</td><td><?= e($p['address'] ?? '—') ?></td></tr>
                    <tr><td class="text-muted fw-semibold">Emergency</td><td><?= e($p['emergency_contact_name'] ?? '—') ?><br><small class="text-muted"><?= e($p['emergency_contact_phone'] ?? '') ?></small></td></tr>
                    <tr><td class="text-muted fw-semibold">Registered</td><td><?= date('d/m/Y', strtotime($p['created_at'])) ?><br><small class="text-muted">by <?= e($p['registered_by'] ?? '—') ?></small></td></tr>
                </table>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-7">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-history me-2"></i>Admission History</span>
                <span class="badge bg-primary"><?= count($adms) ?> admission(s)</span>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead><tr><th>#</th><th>Type</th><th>Dept</th><th>Bed</th><th>Admitted</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($adms as $a): ?>
                        <tr>
                            <td class="text-muted small"><?= $a['admission_id'] ?></td>
                            <td><span class="badge bg-<?= $a['admission_type']==='EMERGENCY'?'danger':($a['admission_type']==='PLANNED'?'primary':'warning') ?> small"><?= e($a['admission_type']) ?></span></td>
                            <td class="small"><?= e($a['dept_name']) ?></td>
                            <td class="small"><?= $a['bed_number'] ? e($a['bed_number']).' <span class="text-muted">'.e($a['ward_name']).'</span>' : '—' ?></td>
                            <td class="small text-muted"><?= date('d/m/Y', strtotime($a['admission_date'])) ?></td>
                            <td><?= status_badge($a['status']) ?></td>
                            <td><a href="/kghbms/admissions/view.php?id=<?= $a['admission_id'] ?>" class="btn btn-xs btn-outline-secondary p-1 small"><i class="fas fa-eye"></i></a></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($adms)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-3">No admissions yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
