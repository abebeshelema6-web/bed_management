<?php
$pageTitle = 'Bed Map';
require_once __DIR__ . '/../includes/header.php';
require_login();

$filterWard = (int)($_GET['ward'] ?? 0);
$filterStatus = $_GET['status'] ?? '';
$wards = $pdo->query("SELECT w.*, d.name AS dept_name FROM wards w JOIN departments d ON d.department_id=w.department_id WHERE w.status='ACTIVE' ORDER BY d.name, w.name")->fetchAll();
?>
<div class="page-header">
    <div><h1><i class="fas fa-map me-2 text-info"></i>Bed Map</h1>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item active">Bed Map</li></ol></nav></div>
    <div class="d-flex gap-2 align-items-center flex-wrap">
        <select id="wardFilter" class="form-select form-select-sm" style="width:220px;">
            <option value="">All Wards</option>
            <?php foreach ($wards as $w): ?>
            <option value="<?= $w['ward_id'] ?>" <?= $filterWard==$w['ward_id']?'selected':'' ?>><?= e($w['dept_name'].' › '.$w['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <span class="text-muted small"><i class="fas fa-sync-alt me-1"></i>Auto-refreshes every 30s</span>
    </div>
</div>

<!-- Legend -->
<div class="card mb-3">
    <div class="card-body py-2">
        <div class="bed-legend">
            <?php foreach ([
                'AVAILABLE'=>['#27ae60','fa-check-circle'],
                'OCCUPIED'=>['#e74c3c','fa-user'],
                'RESERVED'=>['#f39c12','fa-clock'],
                'CLEANING'=>['#2980b9','fa-broom'],
                'MAINTENANCE'=>['#8e44ad','fa-wrench'],
                'BLOCKED'=>['#7f8c8d','fa-ban'],
                'OUT_OF_SERVICE'=>['#2c3e50','fa-times-circle'],
            ] as $s => [$color, $icon]): ?>
            <div class="legend-item">
                <div class="legend-dot" style="background:<?= $color ?>;"></div>
                <i class="fas <?= $icon ?> small" style="color:<?= $color ?>"></i>
                <span class="small"><?= $s ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div id="bedMapContainer">
    <?php include __DIR__ . '/map_partial.php'; ?>
</div>

<!-- Bed Detail Modal (filled via JS) -->
<div class="modal fade" id="bedDetailModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" id="bedDetailContent"></div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
