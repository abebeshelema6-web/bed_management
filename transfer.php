<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();
$pdo = getDB();
require_role(['ADMINISTRATOR','NURSE','MANAGER','DOCTOR','RECEPTION']);
$canTransfer = has_role(['ADMINISTRATOR','NURSE','MANAGER']);

$admissionId = (int)($_GET['admission_id'] ?? 0);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    require_role(['ADMINISTRATOR','NURSE','MANAGER']);
    $admId  = (int)($_POST['admission_id'] ?? 0);
    $toBed  = (int)($_POST['to_bed_id'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');
    if (!$admId)  $errors[] = 'Please select a patient/admission.';
    if ($admId && has_role(['NURSE']) && !is_admission_assigned_to_nurse($pdo, $admId, current_user_id())) {
        $errors[] = 'You may only transfer patients assigned to you.';
    }
    if (!$toBed)  $errors[] = 'Please select a destination bed.';
    if (!$reason) $errors[] = 'Transfer reason is required.';
    if (empty($errors)) {
        [$ok, $msg] = transfer_patient($pdo, $admId, $toBed, $reason, current_user_id());
        if ($ok) {
            set_flash('success', $msg);
            header("Location: /kghbms/admissions/view.php?id=$admId");
            exit;
        } else {
            $errors[] = $msg;
        }
    }
}

$withBedsSql = "SELECT a.admission_id, p.full_name, p.patient_number,
    b.bed_id AS cur_bed_id, b.bed_number AS cur_bed, w.name AS cur_ward
    FROM admissions a JOIN patients p ON p.patient_id=a.patient_id
    JOIN bed_allocations ba ON ba.admission_id=a.admission_id AND ba.is_active=1
    JOIN beds b ON b.bed_id=ba.bed_id JOIN rooms r ON r.room_id=b.room_id
    JOIN wards w ON w.ward_id=r.ward_id
    WHERE a.status='ACTIVE'";
$withBedsParams = [];
if (has_role(['NURSE'])) {
    $withBedsSql .= " AND EXISTS (SELECT 1 FROM admission_nurse_assignments ana
        WHERE ana.admission_id=a.admission_id AND ana.nurse_user_id=?)";
    $withBedsParams[] = current_user_id();
}
$withBedsSql .= ' ORDER BY p.full_name';
$withBedsStmt = $pdo->prepare($withBedsSql);
$withBedsStmt->execute($withBedsParams);
$withBeds = $withBedsStmt->fetchAll();

$availBeds = $pdo->query("SELECT b.bed_id, b.bed_number, b.bed_type, r.room_number, w.name AS ward_name, w.ward_id
    FROM beds b JOIN rooms r ON r.room_id=b.room_id AND r.status='ACTIVE'
    JOIN wards w ON w.ward_id=r.ward_id AND w.status='ACTIVE'
    WHERE b.is_active=1 AND b.current_status='AVAILABLE' ORDER BY w.name, b.bed_number")->fetchAll();

$historySql = "SELECT bt.*, p.full_name, p.patient_number,
    bf.bed_number AS from_bed, wf.name AS from_ward, bt2.bed_number AS to_bed, wt.name AS to_ward, u.full_name AS by_name
    FROM bed_transfers bt JOIN patients p ON p.patient_id=bt.patient_id
    JOIN beds bf ON bf.bed_id=bt.from_bed_id JOIN rooms rf ON rf.room_id=bf.room_id JOIN wards wf ON wf.ward_id=rf.ward_id
    JOIN beds bt2 ON bt2.bed_id=bt.to_bed_id JOIN rooms rt ON rt.room_id=bt2.room_id JOIN wards wt ON wt.ward_id=rt.ward_id
    JOIN users u ON u.user_id=bt.transferred_by";
$historyParams = [];
if (has_role(['NURSE'])) {
    $historySql .= " WHERE EXISTS (SELECT 1 FROM admission_nurse_assignments ana
        WHERE ana.admission_id=bt.admission_id AND ana.nurse_user_id=?)";
    $historyParams[] = current_user_id();
}
$historySql .= ' ORDER BY bt.transfer_date DESC LIMIT 30';
$historyStmt = $pdo->prepare($historySql);
$historyStmt->execute($historyParams);
$history = $historyStmt->fetchAll();

$pageTitle = 'Transfer Patient';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div><h1><i class="fas fa-exchange-alt me-2 text-warning"></i>Transfer Patient</h1>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item active">Transfers</li></ol></nav></div>
</div>

<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<div class="row g-4">
    <?php if ($canTransfer): ?>
    <div class="col-12 col-lg-5">
        <div class="card">
            <div class="card-header"><i class="fas fa-exchange-alt me-2"></i>New Transfer</div>
            <div class="card-body">
                <form method="post" data-loading>
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Patient (with active bed) <span class="required-mark">*</span></label>
                        <select name="admission_id" id="trAdm" class="form-select" required>
                            <option value="">Select patient…</option>
                            <?php foreach ($withBeds as $w): ?>
                            <option value="<?= $w['admission_id'] ?>"
                                    data-cur="Current: <?= e(addslashes($w['cur_bed'])) ?> (<?= e(addslashes($w['cur_ward'])) ?>)"
                                    <?= $admissionId==$w['admission_id']?'selected':'' ?>>
                                <?= e($w['full_name']) ?> (<?= e($w['patient_number']) ?>) — <?= e($w['cur_bed']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div id="curBedInfo" class="text-muted small mt-1"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Destination Bed <span class="required-mark">*</span></label>
                        <select name="to_bed_id" class="form-select" required>
                            <option value="">Select available bed…</option>
                            <?php $grouped=[]; foreach ($availBeds as $b) $grouped[$b['ward_name']][]=$b; foreach ($grouped as $wName=>$wBeds): ?>
                            <optgroup label="<?= e($wName) ?>">
                                <?php foreach ($wBeds as $b): ?><option value="<?= $b['bed_id'] ?>"><?= e($b['bed_number']) ?> — <?= e($b['ward_name']) ?> (<?= e($b['bed_type']) ?>)</option><?php endforeach; ?>
                            </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Reason <span class="required-mark">*</span></label>
                        <textarea name="reason" class="form-control" rows="3" required placeholder="Clinical or operational reason…"></textarea>
                    </div>
                    <button type="submit" class="btn btn-warning w-100"><i class="fas fa-exchange-alt me-1"></i>Execute Transfer</button>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <div class="col-12 <?= $canTransfer ? 'col-lg-7' : '' ?>">
        <div class="card">
            <div class="card-header"><i class="fas fa-history me-2"></i>Recent Transfers</div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead><tr><th>Patient</th><th>From</th><th>To</th><th>Date</th><th>Reason</th><th>By</th></tr></thead>
                    <tbody>
                        <?php foreach ($history as $t): ?>
                        <tr>
                            <td class="small"><a href="/kghbms/patients/view.php?id=<?= $t['patient_id'] ?>"><?= e($t['full_name']) ?></a></td>
                            <td class="small"><?= e($t['from_bed']) ?><br><span class="text-muted"><?= e($t['from_ward']) ?></span></td>
                            <td class="small"><?= e($t['to_bed']) ?><br><span class="text-muted"><?= e($t['to_ward']) ?></span></td>
                            <td class="small text-muted"><?= date('d/m/Y H:i',strtotime($t['transfer_date'])) ?></td>
                            <td class="small" style="max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($t['reason']) ?></td>
                            <td class="small"><?= e($t['by_name']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($history)): ?><tr><td colspan="6" class="text-muted text-center py-3">No transfers yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script>
document.getElementById('trAdm').addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    document.getElementById('curBedInfo').textContent = opt.dataset.cur || '';
});
<?php if ($admissionId): ?>
document.getElementById('trAdm').value = <?= $admissionId ?>;
document.getElementById('trAdm').dispatchEvent(new Event('change'));
<?php endif; ?>
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
