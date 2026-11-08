<?php
$pageTitle = 'Patients';
require_once __DIR__ . '/../includes/header.php';
require_role(['ADMINISTRATOR','RECEPTION','NURSE','DOCTOR','MANAGER']);

$q      = trim($_GET['q'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset  = ($page - 1) * $perPage;

$params = [];
$where  = '1=1';
if (has_role(['NURSE'])) {
    $where .= ' AND EXISTS (SELECT 1 FROM admissions a
        JOIN admission_nurse_assignments ana ON ana.admission_id=a.admission_id
        WHERE a.patient_id=p.patient_id AND ana.nurse_user_id=?)';
    $params[] = current_user_id();
}
if ($q !== '') {
    $where .= " AND (p.full_name LIKE ? OR p.patient_number = ? OR p.phone = ?)";
    $like  = "%$q%";
    array_push($params, $like, $q, $q);
}

$total = $pdo->prepare("SELECT COUNT(*) FROM patients p WHERE $where");
$total->execute($params);
$totalCount = (int)$total->fetchColumn();

$stmt = $pdo->prepare("
    SELECT p.*, u.full_name AS registered_by,
           (SELECT COUNT(*) FROM admissions a WHERE a.patient_id = p.patient_id) AS total_admissions,
           (SELECT COUNT(*) FROM admissions a WHERE a.patient_id = p.patient_id AND a.status='ACTIVE') AS active_adm
    FROM patients p
    LEFT JOIN users u ON u.user_id = p.created_by
    WHERE $where
    ORDER BY p.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$patients = $stmt->fetchAll();
$pag = paginate($totalCount, $perPage, $page);
?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-users me-2 text-primary"></i>Patients</h1>
        <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item active">Patients</li></ol></nav>
    </div>
    <?php if (has_role(['ADMINISTRATOR','RECEPTION','MANAGER'])): ?>
    <a href="/kghbms/patients/register.php" class="btn btn-primary"><i class="fas fa-user-plus me-1"></i>Register Patient</a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-header">
        <form class="d-flex gap-2 flex-wrap" method="get">
            <input type="text" name="q" class="form-control" style="max-width:300px;"
                   placeholder="Search name, patient No., phone…" value="<?= e($q) ?>">
            <button type="submit" class="btn btn-primary"><i class="fas fa-search me-1"></i>Search</button>
            <?php if ($q): ?><a href="?" class="btn btn-outline-secondary">Clear</a><?php endif; ?>
            <span class="ms-auto text-muted small d-flex align-items-center"><?= $totalCount ?> patient(s)</span>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Patient No.</th>
                    <th>Full Name</th>
                    <th>Gender</th>
                    <th>DOB</th>
                    <th>Phone</th>
                    <th>Admissions</th>
                    <th>Registered</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($patients as $p): ?>
                <tr>
                    <td><span class="badge bg-secondary"><?= e($p['patient_number']) ?></span></td>
                    <td>
                        <a href="/kghbms/patients/view.php?id=<?= $p['patient_id'] ?>" class="fw-semibold text-decoration-none">
                            <?= e($p['full_name']) ?>
                        </a>
                        <?php if ($p['active_adm'] > 0): ?>
                        <span class="badge bg-success ms-1 small">Admitted</span>
                        <?php endif; ?>
                    </td>
                    <td><?= e($p['gender']) ?></td>
                    <td><?= e($p['date_of_birth']) ?> <span class="text-muted small">(<?= (int)((time() - strtotime($p['date_of_birth']))/31536000) ?>y)</span></td>
                    <td><?= e($p['phone'] ?? '—') ?></td>
                    <td class="text-center">
                        <span class="badge bg-primary"><?= $p['total_admissions'] ?></span>
                    </td>
                    <td class="text-muted small"><?= date('d/m/Y', strtotime($p['created_at'])) ?></td>
                    <td>
                        <div class="d-flex gap-1">
                            <a href="/kghbms/patients/view.php?id=<?= $p['patient_id'] ?>" class="btn btn-sm btn-outline-primary" title="View"><i class="fas fa-eye"></i></a>
                            <?php if (has_role(['ADMINISTRATOR','RECEPTION','MANAGER','NURSE'])): ?>
                            <a href="/kghbms/patients/edit.php?id=<?= $p['patient_id'] ?>" class="btn btn-sm btn-outline-warning" title="Edit"><i class="fas fa-edit"></i></a>
                            <?php endif; ?>
                            <?php if ($p['active_adm'] == 0 && has_role(['ADMINISTRATOR','RECEPTION','DOCTOR','MANAGER'])): ?>
                            <a href="/kghbms/admissions/admit.php?patient_id=<?= $p['patient_id'] ?>" class="btn btn-sm btn-outline-success" title="Admit"><i class="fas fa-procedures"></i></a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($patients)): ?>
                <tr><td colspan="8" class="text-center text-muted py-4"><i class="fas fa-user-slash fa-2x mb-2 d-block"></i>No patients found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pag['pages'] > 1): ?>
    <div class="card-footer d-flex justify-content-between align-items-center">
        <div class="text-muted small">Showing <?= $offset+1 ?>–<?= min($offset+$perPage,$totalCount) ?> of <?= $totalCount ?></div>
        <?= pagination_links($pag, '?q='.urlencode($q)) ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
