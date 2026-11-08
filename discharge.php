<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();
$pdo = getDB();
require_role(['ADMINISTRATOR','RECEPTION','DOCTOR','MANAGER']);

$admissionId = (int)($_GET['admission_id'] ?? 0);
$errors = [];

$activeAdm = $pdo->query("
    SELECT a.admission_id, p.full_name, p.patient_number,
           d.name AS dept_name, a.admission_date, a.admission_type,
           b.bed_number, w.name AS ward_name
    FROM admissions a
    JOIN patients p ON p.patient_id=a.patient_id
    JOIN departments d ON d.department_id=a.department_id
    LEFT JOIN bed_allocations ba ON ba.admission_id=a.admission_id AND ba.is_active=1
    LEFT JOIN beds b ON b.bed_id=ba.bed_id
    LEFT JOIN rooms r ON r.room_id=b.room_id
    LEFT JOIN wards w ON w.ward_id=r.ward_id
    WHERE a.status='ACTIVE'
    ORDER BY a.admission_date DESC
")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $admId   = (int)($_POST['admission_id'] ?? 0);
    $type    = $_POST['discharge_type'] ?? '';
    $date    = $_POST['discharge_date'] ?? '';
    $summary = trim($_POST['summary'] ?? '');
    $validTypes = ['RECOVERED','REFERRED','LEFT_AGAINST_ADVICE','TRANSFERRED_OUT','DECEASED','OTHER'];
    if (!$admId)                      $errors[] = 'Please select an admission.';
    if (!in_array($type,$validTypes)) $errors[] = 'Please select a valid discharge type.';
    if (empty($errors)) {
        $dateFormatted = !empty($date) ? str_replace('T',' ',$date) : date('Y-m-d H:i:s');
        [$ok, $msg] = discharge_patient($pdo, $admId, $type, $dateFormatted, $summary, current_user_id());
        if ($ok) {
            set_flash('success', $msg);
            header("Location: /kghbms/admissions/view.php?id=$admId");
            exit;
        } else {
            $errors[] = $msg;
        }
    }
}

$pageTitle = 'Discharge Patient';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div>
        <h1><i class="fas fa-sign-out-alt me-2 text-danger"></i>Discharge Patient</h1>
        <nav aria-label="breadcrumb"><ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/kghbms/admissions/list.php">Admissions</a></li>
            <li class="breadcrumb-item active">Discharge</li>
        </ol></nav>
    </div>
    <a href="/kghbms/discharges/history.php" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-history me-1"></i>History
    </a>
</div>
<div class="row justify-content-center">
<div class="col-12 col-lg-7">
<div class="card">
    <div class="card-header bg-danger text-white"><i class="fas fa-sign-out-alt me-2"></i>Discharge Form</div>
    <div class="card-body">
        <?php if ($errors): ?>
        <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>
        <div class="alert alert-warning py-2 small mb-3">
            <i class="fas fa-exclamation-triangle me-1"></i>
            Discharging closes the admission and moves the allocated bed to <strong>CLEANING</strong> status.
        </div>
        <form method="post" data-loading>
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label fw-semibold">Select Admission <span class="required-mark">*</span></label>
                <select name="admission_id" id="disAdm" class="form-select" required>
                    <option value="">Select active admission…</option>
                    <?php foreach ($activeAdm as $a): ?>
                    <option value="<?= $a['admission_id'] ?>"
                            data-info="<?= e($a['full_name'].' ('.$a['patient_number'].') — '.($a['bed_number'] ?? 'No bed').($a['ward_name'] ? ' / '.$a['ward_name'] : '')) ?>"
                            <?= $admissionId==$a['admission_id'] ? 'selected' : '' ?>>
                        <?= e($a['full_name']) ?> (<?= e($a['patient_number']) ?>) —
                        <?= e($a['admission_type']) ?> —
                        <?= $a['bed_number'] ? e($a['bed_number'].' / '.$a['ward_name']) : 'No bed' ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <div id="admInfo" class="alert alert-info mt-2 py-2 small d-none"></div>
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Discharge Type <span class="required-mark">*</span></label>
                    <select name="discharge_type" class="form-select" required>
                        <option value="">Select type…</option>
                        <?php foreach (['RECOVERED','REFERRED','LEFT_AGAINST_ADVICE','TRANSFERRED_OUT','DECEASED','OTHER'] as $t): ?>
                        <option value="<?= $t ?>"><?= str_replace('_',' ',$t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Discharge Date &amp; Time</label>
                    <input type="datetime-local" name="discharge_date" class="form-control"
                           value="<?= date('Y-m-d\TH:i') ?>" max="<?= date('Y-m-d\TH:i') ?>">
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Discharge Summary / Notes</label>
                    <textarea name="summary" class="form-control" rows="4" placeholder="Brief summary…"></textarea>
                </div>
            </div>
            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-danger btn-lg"
                        data-confirm="Discharge this patient? This cannot be undone.">
                    <i class="fas fa-sign-out-alt me-2"></i>Confirm Discharge
                </button>
                <a href="/kghbms/admissions/list.php" class="btn btn-outline-secondary btn-lg">Cancel</a>
            </div>
        </form>
    </div>
</div>
</div>
</div>
<script>
const disAdm = document.getElementById('disAdm');
disAdm.addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    const box = document.getElementById('admInfo');
    if (opt.value && opt.dataset.info) {
        box.innerHTML = '<i class="fas fa-user me-1"></i>' + opt.dataset.info;
        box.classList.remove('d-none');
    } else { box.classList.add('d-none'); }
});
<?php if ($admissionId): ?>
disAdm.value = <?= $admissionId ?>;
disAdm.dispatchEvent(new Event('change'));
<?php endif; ?>
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
