<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';

$stats = get_bed_stats($pdo);

// Active admissions by type
$admissionTypeStats = $pdo->query("
    SELECT admission_type, COUNT(*) AS total
    FROM admissions
    WHERE status = 'ACTIVE'
    GROUP BY admission_type
")->fetchAll(PDO::FETCH_KEY_PAIR);

// Ward occupancy
$wardStats = $pdo->query('
    SELECT w.name AS ward_name, w.ward_id,
           COUNT(b.bed_id) AS total,
           SUM(b.current_status = "OCCUPIED") AS occupied,
           SUM(b.current_status = "AVAILABLE") AS available,
           SUM(b.current_status NOT IN ("MAINTENANCE","BLOCKED","OUT_OF_SERVICE")) AS operational
    FROM wards w
    JOIN rooms r ON r.ward_id = w.ward_id
    JOIN beds b ON b.room_id = r.room_id AND b.is_active = 1
    WHERE w.status = "ACTIVE"
    GROUP BY w.ward_id, w.name
    ORDER BY occupied DESC
')->fetchAll();

// Recent admissions
$recentAdmissionsSql = '
    SELECT a.admission_id, p.full_name, p.patient_number, a.admission_type,
           a.admission_date, d.name AS dept,
           ba.bed_id, b.bed_number, w.name AS ward
    FROM admissions a
    JOIN patients p ON p.patient_id = a.patient_id
    JOIN departments d ON d.department_id = a.department_id
    LEFT JOIN bed_allocations ba ON ba.admission_id = a.admission_id AND ba.is_active = 1
    LEFT JOIN beds b ON b.bed_id = ba.bed_id
    LEFT JOIN rooms r ON r.room_id = b.room_id
    LEFT JOIN wards w ON w.ward_id = r.ward_id
    WHERE a.status = "ACTIVE"
';
$recentAdmissionParams = [];
if (has_role(['NURSE'])) {
    $recentAdmissionsSql .= ' AND EXISTS (SELECT 1 FROM admission_nurse_assignments ana
        WHERE ana.admission_id=a.admission_id AND ana.nurse_user_id=?)';
    $recentAdmissionParams[] = current_user_id();
}
$recentAdmissionsSql .= '
    ORDER BY a.admission_date DESC
    LIMIT 8
';
$recentAdmissions = $pdo->prepare($recentAdmissionsSql);
$recentAdmissions->execute($recentAdmissionParams);
$recentAdm = $recentAdmissions->fetchAll();

// Recent activity
$recentAudit = $pdo->query('
    SELECT al.action, al.created_at, al.details, u.full_name
    FROM audit_logs al
    LEFT JOIN users u ON u.user_id = al.user_id
    ORDER BY al.audit_id DESC LIMIT 10
')->fetchAll();

// Active admissions count
$totalActive = $pdo->query("SELECT COUNT(*) FROM admissions WHERE status = 'ACTIVE'")->fetchColumn();
$totalPatients = $pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();
$totalTransfers = $pdo->query("SELECT COUNT(*) FROM bed_transfers WHERE DATE(transfer_date) = CURDATE()")->fetchColumn();
?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-tachometer-alt me-2 text-primary"></i>Dashboard</h1>
        <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item active">Dashboard</li></ol></nav>
    </div>
    <div class="text-muted small">
        <i class="fas fa-clock me-1"></i><?= date('l, d F Y — H:i') ?>
        <span class="ms-2 badge bg-primary"><?= e($role) ?></span>
    </div>
</div>

<!-- Bed Status Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-lg-3">
        <div class="stat-card bg-available h-100">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= $stats['AVAILABLE'] ?></div>
                <div class="stat-label">Available Beds</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-3">
        <div class="stat-card bg-occupied h-100">
            <div class="stat-icon"><i class="fas fa-user"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= $stats['OCCUPIED'] ?></div>
                <div class="stat-label">Occupied Beds</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-3">
        <div class="stat-card bg-reserved h-100">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= $stats['RESERVED'] ?></div>
                <div class="stat-label">Reserved Beds</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-3">
        <div class="stat-card bg-cleaning h-100">
            <div class="stat-icon"><i class="fas fa-broom"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= $stats['CLEANING'] ?></div>
                <div class="stat-label">Being Cleaned</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-3">
        <div class="stat-card bg-maintenance h-100">
            <div class="stat-icon"><i class="fas fa-wrench"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= $stats['MAINTENANCE'] ?></div>
                <div class="stat-label">Maintenance</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-3">
        <div class="stat-card" style="background:linear-gradient(135deg,#1a5276,#2e86c1);">
            <div class="stat-icon"><i class="fas fa-procedures"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= $totalActive ?></div>
                <div class="stat-label">Active Admissions</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-3">
        <div class="stat-card" style="background:linear-gradient(135deg,#117a65,#1abc9c);">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= $totalPatients ?></div>
                <div class="stat-label">Total Patients</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-3">
        <div class="stat-card" style="background:linear-gradient(135deg,#6c3483,#9b59b6);">
            <div class="stat-icon"><i class="fas fa-exchange-alt"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= $totalTransfers ?></div>
                <div class="stat-label">Transfers Today</div>
            </div>
        </div>
    </div>
</div>

<!-- Occupancy Bar -->
<div class="card mb-4">
    <div class="card-body py-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="fw-semibold">Overall Bed Occupancy</span>
            <div class="d-flex gap-3 small">
                <span class="text-muted">Operational: <?= $stats['operational'] ?></span>
                <span class="fw-bold text-primary"><?= $stats['occupancy'] ?>%</span>
            </div>
        </div>
        <div class="progress" style="height:14px;border-radius:8px;">
            <div class="progress-bar bg-danger" style="width:<?= $stats['occupancy'] ?>%" title="Occupied <?= $stats['occupancy'] ?>%"></div>
            <?php $res = $stats['operational'] > 0 ? round($stats['RESERVED']/$stats['operational']*100,1) : 0; ?>
            <div class="progress-bar bg-warning" style="width:<?= $res ?>%" title="Reserved <?= $res ?>%"></div>
        </div>
        <div class="d-flex flex-wrap gap-3 mt-2 small text-muted">
            <span><span class="badge bg-danger me-1">&nbsp;</span>Occupied</span>
            <span><span class="badge bg-warning me-1">&nbsp;</span>Reserved</span>
            <span><span class="badge bg-success me-1">&nbsp;</span>Available</span>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Bed Distribution Chart -->
    <?php if (has_role(['ADMINISTRATOR','MANAGER'])): ?>
    <div class="col-12 col-lg-5">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-chart-pie me-2 text-primary"></i>Bed Status Distribution</span>
                <span class="badge bg-secondary"><?= $stats['total'] ?> Total</span>
            </div>
            <?php endif; ?>
            <div class="card-body d-flex align-items-center justify-content-center" style="height:260px;">
                <canvas id="bedPieChart"></canvas>
            </div>
        </div>
    </div>
    <!-- Ward Occupancy -->
    <div class="col-12 col-lg-7">
        <div class="card h-100">
            <div class="card-header">
                <i class="fas fa-layer-group me-2 text-primary"></i>Ward Occupancy
            </div>
            <div class="card-body" style="height:260px;">
                <?php if (!empty($wardStats)): ?>
                <canvas id="wardBarChart" style="height:100%;" aria-label="Occupied and available beds by ward" role="img"></canvas>

                <!-- Accessible fallback list for screen readers / no-JS -->
                <div class="mt-2 visually-hidden" id="wardList">
                    <ul class="list-unstyled mb-0">
                    <?php foreach ($wardStats as $w):
                        $occ = $w['operational'] > 0 ? round($w['occupied']/$w['operational']*100,1) : 0;
                    ?>
                        <li><?= e($w['ward_name']) ?>: <?= $w['occupied'] ?>/<?= $w['total'] ?> (<?= $occ ?>%)</li>
                    <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-muted text-center mt-3">No ward data available. Add wards and beds.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-12 col-lg-5">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-chart-pie me-2 text-primary"></i>Active Admissions by Type</span>
                <span class="badge bg-secondary"><?= $totalActive ?> Active</span>
            </div>
            <div class="card-body d-flex align-items-center justify-content-center" style="height:260px;">
                <?php if (!empty($admissionTypeStats)): ?>
                <canvas id="admissionTypeChart" aria-label="Active admissions by type" role="img"></canvas>
                <?php else: ?>
                <p class="text-muted text-center mb-0">No active admissions to display.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Active Admissions -->
    <div class="col-12 col-lg-7">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-clipboard-list me-2 text-primary"></i>Active Admissions</span>
                <a href="/kghbms/admissions/list.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Type</th>
                            <th>Bed</th>
                            <th>Admitted</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentAdm as $a): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold small"><?= e($a['full_name']) ?></div>
                                <div class="text-muted" style="font-size:0.75rem;"><?= e($a['patient_number']) ?> · <?= e($a['dept']) ?></div>
                            </td>
                            <td><span class="badge bg-<?= $a['admission_type']==='EMERGENCY'?'danger':($a['admission_type']==='PLANNED'?'primary':'warning') ?> small"><?= e($a['admission_type']) ?></span></td>
                            <td>
                                <?php if ($a['bed_number']): ?>
                                    <span class="badge bg-danger small"><?= e($a['bed_number']) ?></span>
                                    <div class="text-muted" style="font-size:0.72rem;"><?= e($a['ward']) ?></div>
                                <?php else: ?>
                                    <span class="text-warning small"><i class="fas fa-exclamation-circle me-1"></i>No bed</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted small"><?= date('d/m H:i', strtotime($a['admission_date'])) ?></td>
                            <td><a href="/kghbms/admissions/view.php?id=<?= $a['admission_id'] ?>" class="btn btn-xs btn-outline-secondary p-1"><i class="fas fa-eye"></i></a></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($recentAdm)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-3">No active admissions.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <!-- Recent Activity -->
    <?php if (has_role(['ADMINISTRATOR','MANAGER'])): ?>
    <div class="col-12 col-lg-5">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-history me-2 text-primary"></i>Recent Activity</span>
                <?php if (has_role(['ADMINISTRATOR','MANAGER'])): ?>
                <a href="/kghbms/audit/logs.php" class="btn btn-sm btn-outline-secondary">Audit Log</a>
                <?php endif; ?>
            </div>
            <div class="card-body p-0" style="max-height:320px;overflow-y:auto;">
                <div class="timeline p-3">
                    <?php foreach ($recentAudit as $a): 
                        $icon = match(true) {
                            str_contains($a['action'],'LOGIN') => 'sign-in-alt',
                            str_contains($a['action'],'ALLOC') => 'bed',
                            str_contains($a['action'],'TRANSFER') => 'exchange-alt',
                            str_contains($a['action'],'DISCHARGE') => 'sign-out-alt',
                            str_contains($a['action'],'PATIENT') => 'user',
                            default => 'circle'
                        };
                    ?>
                    <div class="tl-item">
                        <div class="tl-dot"></div>
                        <div class="small">
                            <span class="badge bg-light text-dark border me-1">
                                <i class="fas fa-<?= $icon ?> me-1 text-primary"></i><?= e($a['action']) ?>
                            </span>
                            <span class="text-muted"><?= e($a['full_name'] ?? 'System') ?></span>
                        </div>
                        <div class="text-muted mt-1" style="font-size:0.72rem;"><?= e($a['created_at']) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="card mt-4">
    <div class="card-header"><i class="fas fa-bolt me-2 text-warning"></i>Quick Actions</div>
    <div class="card-body">
        <div class="d-flex flex-wrap gap-2">
            <?php if (has_role(['ADMINISTRATOR','RECEPTION','MANAGER'])): ?>
            <a href="/kghbms/patients/register.php" class="btn btn-outline-primary"><i class="fas fa-user-plus me-1"></i>Register Patient</a>
            <?php endif; ?>
            <?php if (has_role(['ADMINISTRATOR','RECEPTION','DOCTOR','MANAGER'])): ?>
            <a href="/kghbms/admissions/admit.php" class="btn btn-outline-success"><i class="fas fa-procedures me-1"></i>New Admission</a>
            <?php endif; ?>
            <?php if (has_role(['ADMINISTRATOR','MANAGER'])): ?>
            <a href="/kghbms/admissions/allocate.php" class="btn btn-outline-danger"><i class="fas fa-bed me-1"></i>Allocate Bed</a>
            <?php endif; ?>
            <a href="/kghbms/beds/map.php" class="btn btn-outline-info"><i class="fas fa-map me-1"></i>Bed Map</a>
            <?php if (has_role(['ADMINISTRATOR','NURSE','MANAGER','DOCTOR','RECEPTION'])): ?>
            <a href="/kghbms/admissions/transfer.php" class="btn btn-outline-warning"><i class="fas fa-exchange-alt me-1"></i>Transfer Patient</a>
            <?php endif; ?>
            <?php if (has_role(['ADMINISTRATOR','RECEPTION','DOCTOR','MANAGER'])): ?>
            <a href="/kghbms/discharges/discharge.php" class="btn btn-outline-secondary"><i class="fas fa-sign-out-alt me-1"></i>Discharge</a>
            <?php endif; ?>
            <?php if (has_role(['ADMINISTRATOR','MANAGER','RECEPTION','DOCTOR','NURSE'])): ?>
            <a href="/kghbms/reports/index.php" class="btn btn-outline-dark"><i class="fas fa-chart-bar me-1"></i>Reports</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$wardLabels = array_map(function($w){ return $w['ward_name']; }, $wardStats);
$wardOccupied = array_map(function($w){ return (int)$w['occupied']; }, $wardStats);
$wardAvailable = array_map(function($w){ return (int)$w['available']; }, $wardStats);
$admissionTypeLabels = array_keys($admissionTypeStats);
$admissionTypeCounts = array_map('intval', array_values($admissionTypeStats));
$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
$extraJs = '<script>
renderOccupancyChart("bedPieChart", ' . json_encode([
    (int)$stats['AVAILABLE'], (int)$stats['OCCUPIED'], (int)$stats['RESERVED'],
    (int)$stats['CLEANING'], (int)$stats['MAINTENANCE'], (int)$stats['BLOCKED'], (int)$stats['OUT_OF_SERVICE']
], $jsonFlags) . ');
renderWardChart("wardBarChart", '
    . json_encode($wardLabels, $jsonFlags) . ', '
    . json_encode($wardOccupied, $jsonFlags) . ', '
    . json_encode($wardAvailable, $jsonFlags) . ');
renderAdmissionTypeChart("admissionTypeChart", '
    . json_encode($admissionTypeLabels, $jsonFlags) . ', '
    . json_encode($admissionTypeCounts, $jsonFlags) . ');
</script>';
?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
