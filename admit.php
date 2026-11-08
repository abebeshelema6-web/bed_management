<?php
// ── Process before any output ─────────────────────────────────────────────────
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();

$pdo = getDB();
require_role(['ADMINISTRATOR','RECEPTION','DOCTOR','MANAGER']);

$errors = [];
$form   = ['admission_type' => 'EMERGENCY', 'patient_id' => (int)($_GET['patient_id'] ?? 0)];

$prePatient = null;
if ($form['patient_id']) {
    $ps = $pdo->prepare('SELECT * FROM patients WHERE patient_id = ?');
    $ps->execute([$form['patient_id']]);
    $prePatient = $ps->fetch();
}

$departments = $pdo->query("SELECT * FROM departments WHERE status='ACTIVE' ORDER BY name")->fetchAll();
$doctors     = $pdo->query("SELECT u.user_id, u.full_name FROM users u JOIN roles r ON r.role_id=u.role_id WHERE r.role_name='DOCTOR' AND u.status='ACTIVE' ORDER BY u.full_name")->fetchAll();

function create_admission(PDO $pdo, array $d, int $userId): array {
    try {
        $pdo->beginTransaction();
        $st = $pdo->prepare("SELECT admission_id FROM admissions WHERE patient_id=? AND status='ACTIVE' FOR UPDATE");
        $st->execute([$d['patient_id']]);
        if ($st->fetch()) { $pdo->rollBack(); return [false, 'Patient already has an active admission.']; }
        $ins = $pdo->prepare('INSERT INTO admissions (patient_id,department_id,admitted_by,attending_doctor_id,admission_type,reason) VALUES (?,?,?,?,?,?)');
        $ins->execute([$d['patient_id'],$d['department_id'],$userId,$d['attending_doctor_id'],$d['admission_type'],$d['reason']?:null]);
        $id = (int)$pdo->lastInsertId();
        audit($pdo, $userId, 'CREATE_ADMISSION', 'admissions', $id, "Type: {$d['admission_type']}");
        $pdo->commit();
        return [true, $id];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return [false, $e->getMessage()];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $form = [
        'patient_id'          => (int)($_POST['patient_id'] ?? 0),
        'department_id'       => (int)($_POST['department_id'] ?? 0),
        'admission_type'      => $_POST['admission_type'] ?? '',
        'attending_doctor_id' => (int)($_POST['attending_doctor_id'] ?? 0) ?: null,
        'reason'              => trim($_POST['reason'] ?? ''),
    ];
    if (!$form['patient_id'])    $errors[] = 'Please select a patient.';
    if (!$form['department_id']) $errors[] = 'Department is required.';
    if (!in_array($form['admission_type'], ['EMERGENCY','PLANNED','TRANSFER_IN','OBSERVATION']))
        $errors[] = 'Valid admission type required.';

    if ($form['patient_id'] && empty($errors)) {
        $ck = $pdo->prepare("SELECT admission_id FROM admissions WHERE patient_id=? AND status='ACTIVE'");
        $ck->execute([$form['patient_id']]);
        if ($ck->fetch()) $errors[] = 'This patient already has an active admission. Please discharge first.';
    }

    if (empty($errors)) {
        [$ok, $result] = create_admission($pdo, $form, current_user_id());
        if ($ok) {
            set_flash('success', 'Admission created. You can now allocate a bed.');
            header("Location: /kghbms/admissions/view.php?id=$result");
            exit;
        } else {
            $errors[] = $result;
        }
    }
}

// ── Now output ────────────────────────────────────────────────────────────────
$pageTitle = 'New Admission';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div>
        <h1><i class="fas fa-procedures me-2 text-primary"></i>New Admission</h1>
        <nav aria-label="breadcrumb"><ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/kghbms/admissions/list.php">Admissions</a></li>
            <li class="breadcrumb-item active">New</li>
        </ol></nav>
    </div>
</div>
<div class="row justify-content-center">
<div class="col-12 col-xl-9">
<div class="card">
    <div class="card-header"><i class="fas fa-clipboard-plus me-2"></i>Admission Form</div>
    <div class="card-body">
        <?php if ($errors): ?>
        <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>
        <form method="post" data-loading>
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">Patient <span class="required-mark">*</span></label>
                    <div class="input-group">
                        <input type="hidden" name="patient_id" id="patient_id" value="<?= (int)$form['patient_id'] ?>">
                        <input type="text" id="patientSearch" class="form-control"
                               placeholder="Type name or patient number to search…"
                               value="<?= $prePatient ? e($prePatient['full_name'].' ('.$prePatient['patient_number'].')') : '' ?>"
                               autocomplete="off">
                        <button type="button" class="btn btn-outline-secondary"
                                data-bs-toggle="modal" data-bs-target="#patientModal">
                            <i class="fas fa-search"></i> Browse
                        </button>
                    </div>
                    <div id="patientInfo" class="alert alert-info mt-2 py-2 small <?= $prePatient ? '' : 'd-none' ?>">
                        <?php if ($prePatient): ?>
                        <i class="fas fa-user me-1"></i>
                        <strong><?= e($prePatient['full_name']) ?></strong> —
                        <?= e($prePatient['gender']) ?>, DOB: <?= e($prePatient['date_of_birth']) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Admission Type <span class="required-mark">*</span></label>
                    <select name="admission_type" class="form-select" required>
                        <?php foreach (['EMERGENCY','PLANNED','TRANSFER_IN','OBSERVATION'] as $t): ?>
                        <option value="<?= $t ?>" <?= ($form['admission_type'] ?? '') === $t ? 'selected' : '' ?>><?= $t ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Department <span class="required-mark">*</span></label>
                    <select name="department_id" class="form-select" required>
                        <option value="">Select department…</option>
                        <?php foreach ($departments as $dept): ?>
                        <option value="<?= $dept['department_id'] ?>" <?= ($form['department_id'] ?? 0) == $dept['department_id'] ? 'selected' : '' ?>><?= e($dept['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Attending Doctor</label>
                    <select name="attending_doctor_id" class="form-select">
                        <option value="">Select doctor (optional)…</option>
                        <?php foreach ($doctors as $doc): ?>
                        <option value="<?= $doc['user_id'] ?>" <?= ($form['attending_doctor_id'] ?? 0) == $doc['user_id'] ? 'selected' : '' ?>><?= e($doc['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Reason / Chief Complaint</label>
                    <textarea name="reason" class="form-control" rows="3" placeholder="Brief reason for admission…"><?= e($form['reason'] ?? '') ?></textarea>
                </div>
                <div class="col-12 d-flex gap-2 mt-2">
                    <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-check me-1"></i>Create Admission</button>
                    <a href="/kghbms/admissions/list.php" class="btn btn-outline-secondary btn-lg">Cancel</a>
                </div>
            </div>
        </form>
    </div>
</div>
</div>
</div>

<!-- Patient Search Modal -->
<div class="modal fade" id="patientModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-search me-2"></i>Search Patient</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="input-group mb-3">
                    <input type="text" id="modalSearch" class="form-control"
                           placeholder="Search by name, patient number, or phone…">
                    <button class="btn btn-primary" onclick="searchPatients()">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
                <div id="searchResults"></div>
            </div>
        </div>
    </div>
</div>

<script>
function searchPatients() {
    const q = document.getElementById('modalSearch').value;
    fetch('/kghbms/patients/search_ajax.php?q=' + encodeURIComponent(q))
        .then(r => r.json())
        .then(data => {
            let html = '<table class="table table-sm table-hover"><thead><tr><th>No.</th><th>Name</th><th>Gender</th><th>DOB</th><th></th></tr></thead><tbody>';
            if (!data.length) html += '<tr><td colspan="5" class="text-center text-muted py-3">No patients found.</td></tr>';
            data.forEach(p => {
                html += `<tr>
                    <td><span class="badge bg-secondary">${p.patient_number}</span></td>
                    <td>${p.full_name}</td><td>${p.gender}</td><td>${p.date_of_birth}</td>
                    <td><button class="btn btn-sm btn-primary"
                        onclick="selectPatient(${p.patient_id},'${p.full_name.replace(/'/g,"\\'")}','${p.patient_number}','${p.gender}','${p.date_of_birth}')">
                        Select</button></td></tr>`;
            });
            html += '</tbody></table>';
            document.getElementById('searchResults').innerHTML = html;
        });
}
function selectPatient(id, name, num, gender, dob) {
    document.getElementById('patient_id').value = id;
    document.getElementById('patientSearch').value = name + ' (' + num + ')';
    const info = document.getElementById('patientInfo');
    info.innerHTML = `<i class="fas fa-user me-1"></i> <strong>${name}</strong> — ${gender}, DOB: ${dob}`;
    info.classList.remove('d-none');
    bootstrap.Modal.getInstance(document.getElementById('patientModal')).hide();
}
document.getElementById('modalSearch').addEventListener('keypress', e => {
    if (e.key === 'Enter') searchPatients();
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
