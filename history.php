<?php
$pageTitle = 'Discharge History';
require_once __DIR__ . '/../includes/header.php';
require_login();

$q    = trim($_GET['q'] ?? '');
$type = $_GET['type'] ?? '';
$from = $_GET['from'] ?? '';
$to   = $_GET['to']   ?? '';
$page = max(1,(int)($_GET['page'] ?? 1));
$perPage = 20; $offset = ($page-1)*$perPage;

$where = '1=1'; $params = [];
if (has_role(['NURSE'])) {
    $where .= ' AND EXISTS (SELECT 1 FROM admission_nurse_assignments ana
        WHERE ana.admission_id=dis.admission_id AND ana.nurse_user_id=?)';
    $params[] = current_user_id();
}
if ($q)    { $where .= ' AND (p.full_name LIKE ? OR p.patient_number=?)'; $params[]="%$q%"; $params[]=$q; }
if ($type) { $where .= ' AND dis.discharge_type=?'; $params[]=$type; }
if ($from) { $where .= ' AND DATE(dis.discharge_date)>=?'; $params[]=$from; }
if ($to)   { $where .= ' AND DATE(dis.discharge_date)<=?'; $params[]=$to; }

$total = $pdo->prepare("SELECT COUNT(*) FROM discharges dis JOIN patients p ON p.patient_id=dis.patient_id WHERE $where");
$total->execute($params); $totalCount = (int)$total->fetchColumn();

$stmt = $pdo->prepare("
    SELECT dis.*, p.full_name, p.patient_number, p.gender,
           a.admission_date, a.admission_type, d.name AS dept_name,
           u.full_name AS by_name,
           GREATEST(0, TIMESTAMPDIFF(DAY, a.admission_date, dis.discharge_date)) AS los_days
    FROM discharges dis
    JOIN patients p ON p.patient_id=dis.patient_id
    JOIN admissions a ON a.admission_id=dis.admission_id
    JOIN departments d ON d.department_id=a.department_id
    JOIN users u ON u.user_id=dis.discharged_by
    WHERE $where
    ORDER BY dis.discharge_date DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$discharges = $stmt->fetchAll();
$pag = paginate($totalCount, $perPage, $page);
$types = ['RECOVERED','REFERRED','LEFT_AGAINST_ADVICE','TRANSFERRED_OUT','DECEASED','OTHER'];
?>
<div class="page-header">
    <div>
        <h1><i class="fas fa-history me-2 text-secondary"></i>Discharge History</h1>
        <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item active">Discharge History</li></ol></nav>
    </div>
    <?php if (has_role(['ADMINISTRATOR','RECEPTION','DOCTOR','MANAGER'])): ?>
    <a href="/kghbms/discharges/discharge.php" class="btn btn-danger"><i class="fas fa-sign-out-alt me-1"></i>New Discharge</a>
    <?php endif; ?>
</div>

<div class="card mb-3">
    <div class="card-body py-2">
        <form class="row g-2 align-items-end" method="get">
            <div class="col-12 col-md-3">
                <input type="text" name="q" class="form-control form-control-sm" placeholder="Name or patient number…" value="<?= e($q) ?>">
            </div>
            <div class="col-12 col-md-2">
                <select name="type" class="form-select form-select-sm">
                    <option value="">All Types</option>
                    <?php foreach ($types as $t): ?>
                    <option value="<?= $t ?>" <?= $type===$t?'selected':'' ?>><?= str_replace('_',' ',$t) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto"><input type="date" name="from" class="form-control form-control-sm" value="<?= e($from) ?>"></div>
            <div class="col-auto"><input type="date" name="to" class="form-control form-control-sm" value="<?= e($to) ?>"></div>
            <div class="col-auto d-flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search me-1"></i>Search</button>
                <a href="?" class="btn btn-outline-secondary btn-sm">Clear</a>
            </div>
            <div class="col-auto ms-auto text-muted small d-flex align-items-center"><?= $totalCount ?> record(s)</div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr>
                <th>Patient</th><th>Gender</th><th>Discharge Type</th><th>Department</th>
                <th>Admitted</th><th>Discharged</th><th>LOS</th><th>Discharged By</th>
            </tr></thead>
            <tbody>
                <?php foreach ($discharges as $d): ?>
                <tr>
                    <td>
                        <a href="/kghbms/patients/view.php?id=<?= $d['patient_id'] ?>" class="fw-semibold text-decoration-none"><?= e($d['full_name']) ?></a>
                        <div class="text-muted" style="font-size:.72rem"><?= e($d['patient_number']) ?></div>
                    </td>
                    <td class="small"><?= e($d['gender']) ?></td>
                    <td>
                        <span class="badge bg-<?= $d['discharge_type']==='DECEASED'?'dark':($d['discharge_type']==='RECOVERED'?'success':'secondary') ?>">
                            <?= str_replace('_',' ',e($d['discharge_type'])) ?>
                        </span>
                    </td>
                    <td class="small"><?= e($d['dept_name']) ?></td>
                    <td class="small text-muted"><?= date('d/m/Y',strtotime($d['admission_date'])) ?></td>
                    <td class="small text-muted"><?= date('d/m/Y H:i',strtotime($d['discharge_date'])) ?></td>
                    <td class="text-center"><span class="badge bg-light text-dark border"><?= (int)$d['los_days'] ?>d</span></td>
                    <td class="small"><?= e($d['by_name']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($discharges)): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">
                    <i class="fas fa-sign-out-alt fa-2x mb-2 d-block"></i>No discharges found.
                </td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pag['pages'] > 1): ?>
    <div class="card-footer d-flex justify-content-between align-items-center">
        <div class="text-muted small">Showing <?= $offset+1 ?>–<?= min($offset+$perPage,$totalCount) ?> of <?= $totalCount ?></div>
        <?= pagination_links($pag,'?q='.urlencode($q).'&type='.urlencode($type).'&from='.urlencode($from).'&to='.urlencode($to)) ?>
    </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
