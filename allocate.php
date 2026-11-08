<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();
$pdo = getDB();
require_role(['ADMINISTRATOR','MANAGER','RECEPTION','NURSE','DOCTOR']);
$canAllocate = has_role(['ADMINISTRATOR','MANAGER']);

$admissionId = (int)($_GET['admission_id'] ?? 0);
$filterWard  = (int)($_GET['ward'] ?? 0);
$filterType  = $_GET['bed_type'] ?? '';
$errors      = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    require_role(['ADMINISTRATOR','MANAGER']);
    $admId = (int)($_POST['admission_id'] ?? 0);
    $bedId = (int)($_POST['bed_id'] ?? 0);
    if (!$admId) $errors[] = 'Please select an admission.';
    if (!$bedId) $errors[] = 'Please select a bed.';
    if (empty($errors)) {
        [$ok, $msg] = allocate_bed($pdo, $admId, $bedId, current_user_id());
        if ($ok) {
            set_flash('success', $msg);
            header("Location: /kghbms/admissions/view.php?id=$admId");
            exit;
        } else {
            $errors[] = $msg;
        }
    }
}

// Load admission info
$adm = null;
if ($admissionId) {
    $st = $pdo->prepare("SELECT a.*, p.full_name, p.patient_number, d.name AS dept_name
                         FROM admissions a JOIN patients p ON p.patient_id=a.patient_id
                         JOIN departments d ON d.department_id=a.department_id
                         WHERE a.admission_id=? AND a.status='ACTIVE'");
    $st->execute([$admissionId]); $adm = $st->fetch();
}

$activeAdm = $pdo->query("
    SELECT a.admission_id, p.full_name, p.patient_number, d.name AS dept_name
    FROM admissions a JOIN patients p ON p.patient_id=a.patient_id
    JOIN departments d ON d.department_id=a.department_id
    WHERE a.status='ACTIVE'
      AND NOT EXISTS(SELECT 1 FROM bed_allocations ba WHERE ba.admission_id=a.admission_id AND ba.is_active=1)
    ORDER BY a.admission_date DESC
")->fetchAll();

$wards = $pdo->query("SELECT w.ward_id, w.name, d.name AS dept_name FROM wards w
    JOIN departments d ON d.department_id=w.department_id
    WHERE w.status='ACTIVE' ORDER BY d.name, w.name")->fetchAll();

$bedWhere  = "b.is_active=1 AND b.current_status='AVAILABLE'";
$bedParams = [];
if ($filterWard) { $bedWhere .= ' AND w.ward_id=?'; $bedParams[] = $filterWard; }
if ($filterType) { $bedWhere .= ' AND b.bed_type=?'; $bedParams[] = $filterType; }

$availBeds = $pdo->prepare("SELECT b.*, r.room_number, w.name AS ward_name, w.ward_id, d.name AS dept_name
    FROM beds b JOIN rooms r ON r.room_id=b.room_id AND r.status='ACTIVE'
    JOIN wards w ON w.ward_id=r.ward_id AND w.status='ACTIVE'
    JOIN departments d ON d.department_id=w.department_id
    WHERE $bedWhere ORDER BY w.name, r.room_number, b.bed_number");
$availBeds->execute($bedParams); $beds = $availBeds->fetchAll();

$allocationSql = "SELECT a.admission_id, p.patient_id, p.full_name, p.patient_number,
        b.bed_number, r.room_number, w.name AS ward_name, a.admission_date
    FROM admissions a JOIN patients p ON p.patient_id=a.patient_id
    JOIN bed_allocations ba ON ba.admission_id=a.admission_id AND ba.is_active=1
    JOIN beds b ON b.bed_id=ba.bed_id
    JOIN rooms r ON r.room_id=b.room_id
    JOIN wards w ON w.ward_id=r.ward_id
    WHERE a.status='ACTIVE'";
$allocationParams = [];
if (has_role(['NURSE'])) {
    $allocationSql .= ' AND EXISTS (SELECT 1 FROM admission_nurse_assignments ana
        WHERE ana.admission_id=a.admission_id AND ana.nurse_user_id=?)';
    $allocationParams[] = current_user_id();
}
$allocationSql .= ' ORDER BY a.admission_date DESC';
$allocationStmt = $pdo->prepare($allocationSql);
$allocationStmt->execute($allocationParams);
$currentAllocations = $allocationStmt->fetchAll();

$pageTitle = 'Allocate Bed';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div><h1><i class="fas fa-bed me-2 text-success"></i>Allocate Bed</h1>
    <nav aria-label="breadcrumb"><ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/kghbms/admissions/list.php">Admissions</a></li>
        <li class="breadcrumb-item active">Allocate Bed</li>
    </ol></nav></div>
</div>

<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<div class="row g-4">
    <?php if ($canAllocate): ?>
    <div class="col-12 col-lg-4">
        <div class="card">
            <div class="card-header"><i class="fas fa-procedures me-2"></i>Select Admission &amp; Filters</div>
            <div class="card-body">
                <form method="post" id="allocForm" data-loading>
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Patient / Admission <span class="required-mark">*</span></label>
                        <select name="admission_id" id="admission_id" class="form-select" required>
                            <option value="">Select patient without bed…</option>
                            <?php foreach ($activeAdm as $a): ?>
                            <option value="<?= $a['admission_id'] ?>"
                                    data-patient="<?= e($a['full_name'].' ('.$a['patient_number'].') — '.$a['dept_name']) ?>"
                                    <?= $admissionId==$a['admission_id']?'selected':'' ?>>
                                <?= e($a['full_name']) ?> — <?= e($a['patient_number']) ?> (<?= e($a['dept_name']) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div id="patientInfo" class="alert alert-info mt-2 py-2 small <?= $adm?'':'d-none' ?>">
                            <?= $adm ? '<i class="fas fa-user me-1"></i>'.e($adm['full_name']).' — '.e($adm['admission_type']).' — '.e($adm['dept_name']) : '' ?>
                        </div>
                    </div>
                    <input type="hidden" name="bed_id" id="selectedBedId">
                    <div id="selectedBedDisplay" class="alert alert-success py-2 small d-none">
                        <i class="fas fa-check me-1"></i><span id="selectedBedText"></span>
                    </div>
                    <div class="section-divider"></div>
                    <h6 class="text-muted mb-2 small">Filters</h6>
                    <div class="mb-2">
                        <label class="form-label small">Ward</label>
                        <select id="filterWard" class="form-select form-select-sm" onchange="applyFilter()">
                            <option value="">All Wards</option>
                            <?php foreach ($wards as $w): ?>
                            <option value="<?= $w['ward_id'] ?>" <?= $filterWard==$w['ward_id']?'selected':'' ?>><?= e($w['dept_name'].' › '.$w['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Bed Type</label>
                        <select id="filterType" class="form-select form-select-sm" onchange="applyFilter()">
                            <option value="">All Types</option>
                            <?php foreach (['STANDARD','ICU','PEDIATRIC','MATERNITY','ELECTRIC'] as $t): ?>
                            <option value="<?= $t ?>" <?= $filterType===$t?'selected':'' ?>><?= $t ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-success w-100 btn-lg" id="allocBtn" disabled>
                        <i class="fas fa-check me-1"></i>Confirm Allocation
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="col-12">
        <div class="card">
            <div class="card-header"><i class="fas fa-clipboard-list me-2"></i>Current Bed Allocations</div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Patient</th><th>Bed</th><th>Room</th><th>Ward</th><th>Admitted</th></tr></thead>
                    <tbody>
                        <?php foreach ($currentAllocations as $allocation): ?>
                        <tr>
                            <td><a href="/kghbms/admissions/view.php?id=<?= (int)$allocation['admission_id'] ?>"><?= e($allocation['full_name']) ?></a><br><small class="text-muted"><?= e($allocation['patient_number']) ?></small></td>
                            <td><?= e($allocation['bed_number']) ?></td>
                            <td><?= e($allocation['room_number']) ?></td>
                            <td><?= e($allocation['ward_name']) ?></td>
                            <td class="small text-muted"><?= e($allocation['admission_date']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (!$currentAllocations): ?><tr><td colspan="5" class="text-center text-muted py-3">No current allocations to view.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <div class="col-12 <?= $canAllocate ? 'col-lg-8' : '' ?>">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-th me-2 text-success"></i>Available Beds</span>
                <span class="badge bg-success"><?= count($beds) ?> available</span>
            </div>
            <div class="card-body">
                <?php if (empty($beds)): ?>
                <div class="text-center text-muted py-5"><i class="fas fa-bed fa-3x mb-3 d-block"></i>No available beds match the filter.</div>
                <?php else: ?>
                <?php $grouped=[]; foreach ($beds as $bed) $grouped[$bed['ward_name']][]=$bed; ?>
                <?php foreach ($grouped as $wardName => $wardBeds): ?>
                <h6 class="text-primary mb-2 mt-3"><i class="fas fa-layer-group me-1"></i><?= e($wardName) ?></h6>
                <div class="d-flex flex-wrap gap-2 mb-3">
                <?php foreach ($wardBeds as $bed): ?>
                <div class="bed-tile bt-AVAILABLE" style="<?= $canAllocate ? 'cursor:pointer;' : '' ?>width:90px;height:80px;"
                     <?php if ($canAllocate): ?>onclick="selectBed(<?= $bed['bed_id'] ?>,'<?= e(addslashes($bed['bed_number'])) ?>','<?= e(addslashes($bed['ward_name'])) ?> / <?= e(addslashes($bed['room_number'])) ?>')" id="bed-<?= $bed['bed_id'] ?>"<?php endif; ?> title="<?= e($bed['bed_type']) ?>">
                        <i class="fas fa-bed"></i>
                        <span><?= e($bed['bed_number']) ?></span>
                        <span style="font-size:.6rem;opacity:.85"><?= e($bed['bed_type']) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php if ($canAllocate): ?>
<script>
function selectBed(id, num, loc) {
    document.querySelectorAll('.bed-tile').forEach(t => t.style.outline='');
    const tile = document.getElementById('bed-' + id);
    if (tile) tile.style.outline = '3px solid #fff';
    document.getElementById('selectedBedId').value = id;
    document.getElementById('selectedBedText').textContent = num + ' — ' + loc;
    document.getElementById('selectedBedDisplay').classList.remove('d-none');
    const admSel = document.getElementById('admission_id');
    document.getElementById('allocBtn').disabled = !admSel.value;
}
document.getElementById('admission_id').addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    const info = document.getElementById('patientInfo');
    if (opt.value && opt.dataset.patient) {
        info.innerHTML = '<i class="fas fa-user me-1"></i>' + opt.dataset.patient;
        info.classList.remove('d-none');
    } else { info.classList.add('d-none'); }
    document.getElementById('allocBtn').disabled = !this.value || !document.getElementById('selectedBedId').value;
});
function applyFilter() {
    const aid = document.getElementById('admission_id').value;
    window.location.href = '?admission_id=' + aid
        + '&ward=' + document.getElementById('filterWard').value
        + '&bed_type=' + document.getElementById('filterType').value;
}
<?php if ($admissionId && $adm): ?>
document.getElementById('admission_id').value = <?= $admissionId ?>;
document.getElementById('patientInfo').classList.remove('d-none');
<?php endif; ?>
</script>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
