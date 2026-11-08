<?php
$pageTitle = 'Admissions';
require_once __DIR__ . '/../includes/header.php';
require_role(['ADMINISTRATOR','RECEPTION','NURSE','DOCTOR','MANAGER']);

$status  = $_GET['status'] ?? 'ACTIVE';
$q       = trim($_GET['q'] ?? '');
$page    = max(1,(int)($_GET['page'] ?? 1));
$perPage = 15;
$offset  = ($page-1)*$perPage;

$validStatus = ['ACTIVE','DISCHARGED','CANCELLED','ALL'];
if (!in_array($status, $validStatus)) $status = 'ACTIVE';

$where  = $status === 'ALL' ? '1=1' : 'a.status = ?';
$params = $status === 'ALL' ? [] : [$status];

if ($q !== '') {
    $where .= ' AND (p.full_name LIKE ? OR p.patient_number = ?)';
    $params[] = "%$q%";
    $params[] = $q;
}
if (has_role(['NURSE'])) {
    $where .= ' AND EXISTS (SELECT 1 FROM admission_nurse_assignments ana
        WHERE ana.admission_id=a.admission_id AND ana.nurse_user_id=?)';
    $params[] = current_user_id();
}

$total = $pdo->prepare("SELECT COUNT(*) FROM admissions a JOIN patients p ON p.patient_id=a.patient_id WHERE $where");
$total->execute($params);
$totalCount = (int)$total->fetchColumn();

$stmt = $pdo->prepare("
    SELECT a.*, p.full_name, p.patient_number, p.gender,
           d.name AS dept_name,
           ba.bed_id, b.bed_number, w.name AS ward_name,
           doc.full_name AS doctor_name
    FROM admissions a
    JOIN patients p ON p.patient_id = a.patient_id
    JOIN departments d ON d.department_id = a.department_id
    LEFT JOIN bed_allocations ba ON ba.admission_id = a.admission_id AND ba.is_active = 1
    LEFT JOIN beds b ON b.bed_id = ba.bed_id
    LEFT JOIN rooms r ON r.room_id = b.room_id
    LEFT JOIN wards w ON w.ward_id = r.ward_id
    LEFT JOIN users doc ON doc.user_id = a.attending_doctor_id
    WHERE $where
    ORDER BY a.admission_date DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$admissions = $stmt->fetchAll();
$pag = paginate($totalCount, $perPage, $page);

$statusCounts = [];
foreach (['ACTIVE','DISCHARGED','CANCELLED'] as $s) {
    $statusCountSql = "SELECT COUNT(*) FROM admissions a WHERE a.status=?";
    $statusCountParams = [$s];
    if (has_role(['NURSE'])) {
        $statusCountSql .= ' AND EXISTS (SELECT 1 FROM admission_nurse_assignments ana
            WHERE ana.admission_id=a.admission_id AND ana.nurse_user_id=?)';
        $statusCountParams[] = current_user_id();
    }
    $c = $pdo->prepare($statusCountSql); $c->execute($statusCountParams);
    $statusCounts[$s] = (int)$c->fetchColumn();
}
?>
<div class="page-header">
    <div>
        <h1><i class="fas fa-clipboard-list me-2 text-primary"></i>Admissions</h1>
        <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item active">Admissions</li></ol></nav>
    </div>
    <?php if (has_role(['ADMINISTRATOR','RECEPTION','DOCTOR','MANAGER'])): ?>
    <a href="/kghbms/admissions/admit.php" class="btn btn-primary"><i class="fas fa-plus me-1"></i>New Admission</a>
    <?php endif; ?>
</div>

<!-- Status Tabs -->
<div class="d-flex gap-2 mb-3 flex-wrap">
    <?php foreach (['ACTIVE'=>'success','DISCHARGED'=>'secondary','CANCELLED'=>'danger','ALL'=>'dark'] as $s => $c): ?>
    <a href="?status=<?= $s ?>&q=<?= urlencode($q) ?>"
       class="btn btn-sm btn-<?= $status===$s ? $c : 'outline-'.$c ?>">
        <?= $s ?> <?php if ($s !== 'ALL' && isset($statusCounts[$s])): ?><span class="badge bg-white text-<?= $c ?>"><?= $statusCounts[$s] ?></span><?php endif; ?>
    </a>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="card-header">
        <form class="d-flex gap-2" method="get">
            <input type="hidden" name="status" value="<?= e($status) ?>">
            <input type="text" name="q" class="form-control" style="max-width:280px;" placeholder="Search patient name or number…" value="<?= e($q) ?>">
            <button type="submit" class="btn btn-primary"><i class="fas fa-search me-1"></i>Search</button>
            <?php if ($q): ?><a href="?status=<?= $status ?>" class="btn btn-outline-secondary">Clear</a><?php endif; ?>
            <span class="ms-auto text-muted small d-flex align-items-center"><?= $totalCount ?> record(s)</span>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr>
                <th>#</th><th>Patient</th><th>Type</th><th>Department</th>
                <th>Current Bed</th><th>Doctor</th><th>Admitted</th><th>Status</th><th>Actions</th>
            </tr></thead>
            <tbody>
                <?php foreach ($admissions as $a): ?>
                <tr>
                    <td class="text-muted small"><?= $a['admission_id'] ?></td>
                    <td>
                        <div class="fw-semibold small"><a href="/kghbms/patients/view.php?id=<?= $a['patient_id'] ?>" class="text-decoration-none"><?= e($a['full_name']) ?></a></div>
                        <div class="text-muted" style="font-size:0.73rem;"><?= e($a['patient_number']) ?></div>
                    </td>
                    <td><span class="badge bg-<?= $a['admission_type']==='EMERGENCY'?'danger':($a['admission_type']==='PLANNED'?'primary':'warning') ?>"><?= e($a['admission_type']) ?></span></td>
                    <td class="small"><?= e($a['dept_name']) ?></td>
                    <td class="small">
                        <?php if ($a['bed_number']): ?>
                            <i class="fas fa-bed text-danger me-1"></i><?= e($a['bed_number']) ?>
                            <div class="text-muted" style="font-size:0.72rem;"><?= e($a['ward_name']) ?></div>
                        <?php elseif ($a['status']==='ACTIVE'): ?>
                            <span class="text-warning"><i class="fas fa-exclamation-circle me-1"></i>No bed</span>
                        <?php else: echo '—'; endif; ?>
                    </td>
                    <td class="small"><?= e($a['doctor_name'] ?? '—') ?></td>
                    <td class="text-muted small"><?= date('d/m/Y H:i', strtotime($a['admission_date'])) ?></td>
                    <td><?= status_badge($a['status']) ?></td>
                    <td>
                        <div class="d-flex gap-1">
                            <a href="/kghbms/admissions/view.php?id=<?= $a['admission_id'] ?>" class="btn btn-sm btn-outline-primary" title="View"><i class="fas fa-eye"></i></a>
                            <?php if ($a['status']==='ACTIVE' && has_role(['ADMINISTRATOR','MANAGER'])): ?>
                                <?php if (!$a['bed_id']): ?>
                                <a href="/kghbms/admissions/allocate.php?admission_id=<?= $a['admission_id'] ?>" class="btn btn-sm btn-outline-success" title="Allocate Bed"><i class="fas fa-bed"></i></a>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?php if ($a['status']==='ACTIVE' && has_role(['ADMINISTRATOR','RECEPTION','DOCTOR','MANAGER'])): ?>
                            <a href="/kghbms/discharges/discharge.php?admission_id=<?= $a['admission_id'] ?>" class="btn btn-sm btn-outline-danger" title="Discharge"><i class="fas fa-sign-out-alt"></i></a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($admissions)): ?>
                <tr><td colspan="9" class="text-center text-muted py-4"><i class="fas fa-clipboard fa-2x mb-2 d-block"></i>No admissions found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pag['pages'] > 1): ?>
    <div class="card-footer d-flex justify-content-between align-items-center">
        <div class="text-muted small">Showing <?= $offset+1 ?>–<?= min($offset+$perPage,$totalCount) ?> of <?= $totalCount ?></div>
        <?= pagination_links($pag, '?status='.urlencode($status).'&q='.urlencode($q)) ?>
    </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
