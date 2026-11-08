<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();
$pdo = getDB();
require_role(['ADMINISTRATOR','MANAGER']);

// Cancel reservation
if (isset($_GET['cancel']) && is_numeric($_GET['cancel'])) {
    $resId = (int)$_GET['cancel'];
    try {
        $pdo->beginTransaction();
        $res = $pdo->prepare("SELECT bed_id FROM bed_reservations WHERE reservation_id=? AND status='ACTIVE' FOR UPDATE");
        $res->execute([$resId]); $r = $res->fetch();
        if ($r) {
            $pdo->prepare("UPDATE bed_reservations SET status='CANCELLED' WHERE reservation_id=?")->execute([$resId]);
            $chk = $pdo->prepare("SELECT current_status FROM beds WHERE bed_id=?"); $chk->execute([$r['bed_id']]);
            if ($chk->fetchColumn() === 'RESERVED') set_bed_status($pdo, $r['bed_id'], 'AVAILABLE', current_user_id(), 'Reservation cancelled');
            audit($pdo, current_user_id(), 'CANCEL_RESERVATION', 'bed_reservations', $resId);
            $pdo->commit();
            set_flash('success','Reservation cancelled. Bed is now available.');
        } else { $pdo->rollBack(); set_flash('warning','Reservation not found or already cancelled.'); }
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); set_flash('danger', $e->getMessage()); }
    header('Location: /kghbms/admissions/reserve.php'); exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $patientId    = (int)($_POST['patient_id'] ?? 0);
    $bedId        = (int)($_POST['bed_id'] ?? 0);
    $reservedFrom = $_POST['reserved_from'] ?? date('Y-m-d\TH:i');
    $reservedUntil= $_POST['reserved_until'] ?? '';
    $notes        = trim($_POST['notes'] ?? '');

    if (!$patientId)     $errors[] = 'Patient is required.';
    if (!$bedId)         $errors[] = 'Bed is required.';
    if (!$reservedUntil) $errors[] = 'Reservation expiry time is required.';
    elseif (str_replace('T',' ',$reservedUntil) <= str_replace('T',' ',$reservedFrom)) $errors[] = 'Expiry must be after the start time.';

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            $chk = $pdo->prepare("SELECT current_status FROM beds WHERE bed_id=? AND is_active=1 FOR UPDATE");
            $chk->execute([$bedId]); $status = $chk->fetchColumn();
            if ($status !== 'AVAILABLE') throw new RuntimeException("Bed is $status — cannot reserve.");
            $ol = $pdo->prepare("SELECT 1 FROM bed_reservations WHERE bed_id=? AND status='ACTIVE' AND reserved_until>NOW()");
            $ol->execute([$bedId]);
            if ($ol->fetch()) throw new RuntimeException('Bed already has an active reservation.');
            $pdo->prepare('INSERT INTO bed_reservations (patient_id,bed_id,reserved_by,reserved_from,reserved_until,notes) VALUES (?,?,?,?,?,?)')
                ->execute([$patientId,$bedId,current_user_id(),str_replace('T',' ',$reservedFrom),str_replace('T',' ',$reservedUntil),$notes?:null]);
            $resId = (int)$pdo->lastInsertId();
            set_bed_status($pdo, $bedId, 'RESERVED', current_user_id(), 'Reservation created');
            audit($pdo, current_user_id(), 'CREATE_RESERVATION', 'bed_reservations', $resId);
            $pdo->commit();
            set_flash('success','Bed reserved successfully.');
            header('Location: /kghbms/admissions/reserve.php'); exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = $e->getMessage();
        }
    }
}

$patients  = $pdo->query("SELECT patient_id, patient_number, full_name FROM patients ORDER BY full_name")->fetchAll();
$availBeds = $pdo->query("SELECT b.bed_id, b.bed_number, b.bed_type, r.room_number, w.name AS ward_name
    FROM beds b JOIN rooms r ON r.room_id=b.room_id AND r.status='ACTIVE'
    JOIN wards w ON w.ward_id=r.ward_id AND w.status='ACTIVE'
    WHERE b.is_active=1 AND b.current_status='AVAILABLE' ORDER BY w.name, b.bed_number")->fetchAll();
$reservations = $pdo->query("SELECT br.*, p.full_name, p.patient_number, b.bed_number, r.room_number, w.name AS ward_name, u.full_name AS by_name
    FROM bed_reservations br JOIN patients p ON p.patient_id=br.patient_id
    JOIN beds b ON b.bed_id=br.bed_id JOIN rooms r ON r.room_id=b.room_id
    JOIN wards w ON w.ward_id=r.ward_id JOIN users u ON u.user_id=br.reserved_by
    ORDER BY br.created_at DESC LIMIT 50")->fetchAll();

$pageTitle = 'Bed Reservations';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div><h1><i class="fas fa-bookmark me-2 text-warning"></i>Bed Reservations</h1>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item active">Reservations</li></ol></nav></div>
</div>
<div class="row g-4">
    <div class="col-12 col-lg-4">
        <div class="card">
            <div class="card-header"><i class="fas fa-plus me-2"></i>New Reservation</div>
            <div class="card-body">
                <?php if ($errors): ?><div class="alert alert-danger small"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
                <form method="post" data-loading><?= csrf_field() ?>
                    <div class="mb-2"><label class="form-label small fw-semibold">Patient <span class="required-mark">*</span></label>
                    <select name="patient_id" class="form-select form-select-sm" required>
                        <option value="">Select patient…</option>
                        <?php foreach ($patients as $p): ?><option value="<?= $p['patient_id'] ?>"><?= e($p['full_name']) ?> (<?= e($p['patient_number']) ?>)</option><?php endforeach; ?>
                    </select></div>
                    <div class="mb-2"><label class="form-label small fw-semibold">Bed <span class="required-mark">*</span></label>
                    <select name="bed_id" class="form-select form-select-sm" required>
                        <option value="">Select available bed…</option>
                        <?php foreach ($availBeds as $b): ?><option value="<?= $b['bed_id'] ?>"><?= e($b['bed_number']) ?> — <?= e($b['ward_name']) ?>/<?= e($b['room_number']) ?> (<?= e($b['bed_type']) ?>)</option><?php endforeach; ?>
                    </select></div>
                    <div class="mb-2"><label class="form-label small fw-semibold">From</label>
                    <input type="datetime-local" name="reserved_from" class="form-control form-control-sm" value="<?= date('Y-m-d\TH:i') ?>"></div>
                    <div class="mb-2"><label class="form-label small fw-semibold">Until (Expiry) <span class="required-mark">*</span></label>
                    <input type="datetime-local" name="reserved_until" class="form-control form-control-sm" required></div>
                    <div class="mb-3"><label class="form-label small fw-semibold">Notes</label>
                    <textarea name="notes" class="form-control form-control-sm" rows="2"></textarea></div>
                    <button type="submit" class="btn btn-warning w-100"><i class="fas fa-bookmark me-1"></i>Reserve Bed</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-8">
        <div class="card">
            <div class="card-header"><i class="fas fa-list me-2"></i>Reservation History</div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead><tr><th>Patient</th><th>Bed</th><th>From</th><th>Until</th><th>Status</th><th>By</th><th></th></tr></thead>
                    <tbody>
                        <?php $badgeMap=['ACTIVE'=>'warning','FULFILLED'=>'success','CANCELLED'=>'secondary','EXPIRED'=>'danger'];
                        foreach ($reservations as $r): ?>
                        <tr>
                            <td class="small"><a href="/kghbms/patients/view.php?id=<?= $r['patient_id'] ?>"><?= e($r['full_name']) ?></a><br><span class="text-muted"><?= e($r['patient_number']) ?></span></td>
                            <td class="small"><?= e($r['bed_number']) ?><br><span class="text-muted"><?= e($r['ward_name']) ?></span></td>
                            <td class="small text-muted"><?= date('d/m H:i',strtotime($r['reserved_from'])) ?></td>
                            <td class="small text-muted"><?= date('d/m H:i',strtotime($r['reserved_until'])) ?></td>
                            <td><span class="badge bg-<?= $badgeMap[$r['status']]??'secondary' ?>"><?= e($r['status']) ?></span></td>
                            <td class="small"><?= e($r['by_name']) ?></td>
                            <td><?php if ($r['status']==='ACTIVE'): ?><a href="?cancel=<?= $r['reservation_id'] ?>" class="btn btn-xs btn-outline-danger p-1" data-confirm="Cancel reservation?"><i class="fas fa-times"></i></a><?php endif; ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($reservations)): ?><tr><td colspan="7" class="text-muted text-center py-3">No reservations.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
