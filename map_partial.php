<?php
if (!isset($pdo)) {
    require_once __DIR__ . '/../includes/auth.php';
    require_login();
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../includes/functions.php';
    $pdo = getDB();
}
require_role(['ADMINISTRATOR','MANAGER','DOCTOR','NURSE','RECEPTION']);
$filterWard = (int)($_GET['ward'] ?? 0);
$patientColumns = 'p.full_name AS patient_name, p.patient_number';
$params = [];
if (has_role(['NURSE'])) {
    $patientColumns = 'CASE WHEN EXISTS (SELECT 1 FROM admission_nurse_assignments ana
                           WHERE ana.admission_id=adm.admission_id AND ana.nurse_user_id=?) THEN p.full_name END AS patient_name,
                       CASE WHEN EXISTS (SELECT 1 FROM admission_nurse_assignments ana
                           WHERE ana.admission_id=adm.admission_id AND ana.nurse_user_id=?) THEN p.patient_number END AS patient_number';
    $params = [current_user_id(), current_user_id()];
}

$sql = "SELECT b.bed_id, b.bed_number, b.bed_type, b.current_status,
               r.room_number, r.room_id,
               w.ward_id, w.name AS ward_name, w.ward_type,
               d.name AS dept_name,
               $patientColumns,
               ba.allocated_at
        FROM beds b
        JOIN rooms r ON r.room_id=b.room_id AND r.status='ACTIVE'
        JOIN wards w ON w.ward_id=r.ward_id AND w.status='ACTIVE'
        JOIN departments d ON d.department_id=w.department_id
        LEFT JOIN bed_allocations ba ON ba.bed_id=b.bed_id AND ba.is_active=1
        LEFT JOIN admissions adm ON adm.admission_id=ba.admission_id
        LEFT JOIN patients p ON p.patient_id=adm.patient_id
        WHERE b.is_active=1";
if ($filterWard) { $sql .= ' AND w.ward_id=?'; $params[] = $filterWard; }
$sql .= ' ORDER BY w.name, r.room_number, b.bed_number';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$beds = $stmt->fetchAll();

// Group by ward → room
$grouped = [];
foreach ($beds as $b) {
    $grouped[$b['ward_id']]['name']   = $b['ward_name'];
    $grouped[$b['ward_id']]['dept']   = $b['dept_name'];
    $grouped[$b['ward_id']]['type']   = $b['ward_type'];
    $grouped[$b['ward_id']]['rooms'][$b['room_id']]['number'] = $b['room_number'];
    $grouped[$b['ward_id']]['rooms'][$b['room_id']]['beds'][] = $b;
}

$statusIcons = ['AVAILABLE'=>'fa-check-circle','OCCUPIED'=>'fa-user','RESERVED'=>'fa-clock',
                'CLEANING'=>'fa-broom','MAINTENANCE'=>'fa-wrench','BLOCKED'=>'fa-ban','OUT_OF_SERVICE'=>'fa-times-circle'];

if (empty($grouped)):
    echo '<div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>No beds found. Add departments, wards, rooms and beds first.</div>';
else:
    foreach ($grouped as $wId => $ward):
?>
<div class="bed-map-ward" data-ward-id="<?= $wId ?>">
    <div class="bed-map-ward-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-layer-group me-2"></i><?= htmlspecialchars($ward['name'],ENT_QUOTES,'UTF-8') ?>
        <small class="opacity-75 ms-2"><?= htmlspecialchars($ward['dept'],ENT_QUOTES,'UTF-8') ?></small></span>
        <span class="badge bg-warning text-dark"><?= htmlspecialchars($ward['type'],ENT_QUOTES,'UTF-8') ?></span>
    </div>
    <?php foreach ($ward['rooms'] as $rId => $room): ?>
    <div class="px-3 pt-2 pb-0">
        <div class="text-muted small fw-semibold mb-1"><i class="fas fa-door-open me-1"></i>Room <?= htmlspecialchars($room['number'],ENT_QUOTES,'UTF-8') ?></div>
    </div>
    <div class="bed-map-grid pt-0">
        <?php foreach ($room['beds'] as $bed):
            $icon = $statusIcons[$bed['current_status']] ?? 'fa-circle';
            $cls  = 'bt-' . $bed['current_status'];
            $title = $bed['bed_number'] . ' — ' . $bed['current_status'];
            if ($bed['patient_name']) $title .= "\n" . $bed['patient_name'];
        ?>
        <a href="javascript:void(0)" onclick="openBedDetail(<?= $bed['bed_id'] ?>)"
           class="bed-tile <?= $cls ?>" title="<?= htmlspecialchars($title,ENT_QUOTES,'UTF-8') ?>">
            <i class="fas <?= $icon ?>"></i>
            <span><?= htmlspecialchars($bed['bed_number'],ENT_QUOTES,'UTF-8') ?></span>
            <?php if ($bed['patient_name']): ?>
            <span style="font-size:0.58rem;opacity:0.9;max-width:82px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($bed['patient_name'],ENT_QUOTES,'UTF-8') ?></span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php
    endforeach;
endif;
?>
