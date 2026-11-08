<?php
/**
 * Common HTML Header - Kuyu HBMS
 */
// Start output buffering so headers can still be sent after this file is included
if (!ob_get_level()) {
    ob_start();
}

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Only call require_login if not already authenticated by the calling page
if (empty($_SESSION['user_id'])) {
    require_login();
}
if (!isset($pdo)) {
    $pdo = getDB();
}
expire_reservations($pdo);

$unread = get_unread_count($pdo, current_user_id());
$role   = current_role();
$userId = current_user_id();

// Get current user details
$userStmt = $pdo->prepare('SELECT full_name, username, profile_photo FROM users WHERE user_id = ?');
$userStmt->execute([$userId]);
$currentUser = $userStmt->fetch();
$currentUserPhoto = !empty($currentUser['profile_photo'])
    ? '/kghbms/uploads/profiles/' . rawurlencode($currentUser['profile_photo'])
    : null;

$flashMessages = get_flash();

$pageTitle = $pageTitle ?? APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> — Kuyu GHBMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.min.css">
    <link rel="stylesheet" href="/kghbms/assets/css/style.css">
</head>
<body>
<!-- Top Navigation Bar -->
<nav class="navbar navbar-expand-lg navbar-dark top-navbar fixed-top" id="topNav">
    <div class="container-fluid">
        <button class="btn btn-link text-white me-2" id="sidebarToggle" title="Toggle Sidebar">
            <i class="fas fa-bars fa-lg"></i>
        </button>
        <a class="navbar-brand fw-bold d-flex align-items-center" href="/kghbms/dashboard.php">
            <img src="/kghbms/assets/images/Logos.png" alt="Kuyu General Hospital" class="header-logo me-2">
            <span class="d-none d-md-inline">Kuyu General Hospital Bed Management System</span>
            <span class="d-md-none">KGHBMS</span>
        </a>
        <div class="d-flex align-items-center ms-auto gap-3">
            <!-- Quick Search -->
            <form class="d-none d-lg-flex" action="/kghbms/patients/list.php" method="get">
                <div class="input-group input-group-sm">
                    <input type="text" name="q" class="form-control top-search" placeholder="Search patients…" autocomplete="off">
                    <button class="btn btn-outline-light btn-sm" type="submit"><i class="fas fa-search"></i></button>
                </div>
            </form>
            <!-- Notifications -->
            <div class="dropdown">
                <button class="btn btn-link text-white position-relative p-1" data-bs-toggle="dropdown" title="Notifications">
                    <i class="fas fa-bell fa-lg"></i>
                    <?php if ($unread > 0): ?>
                    <span class="badge bg-danger rounded-pill position-absolute top-0 start-100 translate-middle notif-badge"><?= $unread > 99 ? '99+' : $unread ?></span>
                    <?php endif; ?>
                </button>
                <div class="dropdown-menu dropdown-menu-end notification-dropdown shadow" style="min-width:320px;">
                    <div class="dropdown-header d-flex justify-content-between align-items-center px-3 py-2">
                        <strong>Notifications</strong>
                        <a href="/kghbms/notifications/index.php" class="small text-primary">View All</a>
                    </div>
                    <div id="notifList">
                        <?php
                        $notifStmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 5');
                        $notifStmt->execute([$userId]);
                        $notifs = $notifStmt->fetchAll();
                        if (empty($notifs)):
                        ?>
                        <div class="px-3 py-3 text-muted small text-center"><i class="fas fa-check-circle text-success me-1"></i>No new notifications</div>
                        <?php else: foreach ($notifs as $n): ?>
                        <a href="/kghbms/notifications/index.php?mark=<?= $n['notification_id'] ?>" class="dropdown-item notif-item py-2">
                            <div class="fw-semibold small"><?= e($n['title']) ?></div>
                            <div class="text-muted small"><?= e($n['message']) ?></div>
                            <div class="text-muted" style="font-size:0.7rem;"><?= e($n['created_at']) ?></div>
                        </a>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
            </div>
            <!-- User Menu -->
            <div class="dropdown">
                <button class="btn btn-link text-white d-flex align-items-center gap-2 p-1" data-bs-toggle="dropdown">
                    <?php if ($currentUserPhoto): ?>
                    <img src="<?= e($currentUserPhoto) ?>" alt="" class="user-avatar user-avatar-image">
                    <?php else: ?>
                    <div class="user-avatar"><?= e(strtoupper(substr($currentUser['full_name'] ?? 'U', 0, 1))) ?></div>
                    <?php endif; ?>
                    <span class="d-none d-md-inline small"><?= e($currentUser['full_name'] ?? 'User') ?></span>
                    <i class="fas fa-chevron-down small"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow">
                    <li><h6 class="dropdown-header"><i class="fas fa-id-badge me-1"></i><?= e($role) ?></h6></li>
                    <li><a class="dropdown-item" href="/kghbms/users/profile.php"><i class="fas fa-user-cog me-2"></i>My Profile</a></li>
                    <li><a class="dropdown-item" href="/kghbms/users/change_password.php"><i class="fas fa-key me-2"></i>Change Password</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="/kghbms/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<!-- Sidebar -->
<nav id="sidebar" class="sidebar">
    <div class="sidebar-sticky">
        <div class="sidebar-section-label">MAIN</div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>" href="/kghbms/dashboard.php">
                    <i class="fas fa-tachometer-alt"></i><span>Dashboard</span>
                </a>
            </li>
        </ul>

        <div class="sidebar-section-label">PATIENTS</div>
        <ul class="nav flex-column">
            <li><a class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/patients/') !== false ? 'active' : '' ?>" href="/kghbms/patients/list.php"><i class="fas fa-users"></i><span>Patients</span></a></li>
            <?php if (has_role(['ADMINISTRATOR','RECEPTION','MANAGER'])): ?>
            <li><a class="nav-link" href="/kghbms/patients/register.php"><i class="fas fa-user-plus"></i><span>Register Patient</span></a></li>
            <?php endif; ?>
        </ul>

        <div class="sidebar-section-label">ADMISSIONS</div>
        <ul class="nav flex-column">
            <li><a class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/admissions/') !== false ? 'active' : '' ?>" href="/kghbms/admissions/list.php"><i class="fas fa-clipboard-list"></i><span>Admissions</span></a></li>
            <?php if (has_role(['ADMINISTRATOR','RECEPTION','DOCTOR','MANAGER'])): ?>
            <li><a class="nav-link" href="/kghbms/admissions/admit.php"><i class="fas fa-procedures"></i><span>New Admission</span></a></li>
            <?php endif; ?>
            <?php if (has_role(['ADMINISTRATOR','MANAGER'])): ?>
            <li><a class="nav-link" href="/kghbms/admissions/allocate.php"><i class="fas fa-bed"></i><span>Allocate Bed</span></a></li>
            <li><a class="nav-link" href="/kghbms/admissions/reserve.php"><i class="fas fa-bookmark"></i><span>Reservations</span></a></li>
            <?php else: ?>
            <li><a class="nav-link" href="/kghbms/admissions/allocate.php"><i class="fas fa-bed"></i><span>Bed Allocations</span></a></li>
            <?php endif; ?>
            <?php if (has_role(['ADMINISTRATOR','NURSE','MANAGER','DOCTOR','RECEPTION'])): ?>
            <li><a class="nav-link" href="/kghbms/admissions/transfer.php"><i class="fas fa-exchange-alt"></i><span>Transfers</span></a></li>
            <?php endif; ?>
        </ul>

        <div class="sidebar-section-label">BEDS</div>
        <ul class="nav flex-column">
            <li><a class="nav-link" href="/kghbms/beds/map.php"><i class="fas fa-map"></i><span>Bed Map</span></a></li>
            <li><a class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/beds/') !== false ? 'active' : '' ?>" href="/kghbms/beds/list.php"><i class="fas fa-hospital-alt"></i><span>Beds</span></a></li>
            <li><a class="nav-link" href="/kghbms/beds/departments.php"><i class="fas fa-building"></i><span>Departments</span></a></li>
            <li><a class="nav-link" href="/kghbms/beds/wards.php"><i class="fas fa-layer-group"></i><span>Wards</span></a></li>
            <li><a class="nav-link" href="/kghbms/beds/rooms.php"><i class="fas fa-door-open"></i><span>Rooms</span></a></li>
        </ul>

        <div class="sidebar-section-label">DISCHARGE</div>
        <ul class="nav flex-column">
            <?php if (has_role(['ADMINISTRATOR','RECEPTION','DOCTOR','MANAGER'])): ?>
            <li><a class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/discharges/') !== false ? 'active' : '' ?>" href="/kghbms/discharges/discharge.php"><i class="fas fa-sign-out-alt"></i><span>Discharge Patient</span></a></li>
            <?php endif; ?>
            <li><a class="nav-link" href="/kghbms/discharges/history.php"><i class="fas fa-history"></i><span>Discharge History</span></a></li>
        </ul>

        <?php if (has_role(['ADMINISTRATOR','MANAGER','RECEPTION','DOCTOR','NURSE'])): ?>
        <div class="sidebar-section-label">REPORTS</div>
        <ul class="nav flex-column">
            <li><a class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/reports/') !== false ? 'active' : '' ?>" href="/kghbms/reports/index.php"><i class="fas fa-chart-bar"></i><span>Reports</span></a></li>
        </ul>
        <?php endif; ?>

        <?php if (has_role(['ADMINISTRATOR','MANAGER'])): ?>
        <div class="sidebar-section-label">ADMIN</div>
        <ul class="nav flex-column">
            <?php if (has_role(['ADMINISTRATOR'])): ?>
            <li><a class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/users/') !== false ? 'active' : '' ?>" href="/kghbms/users/list.php"><i class="fas fa-user-shield"></i><span>Users</span></a></li>
            <?php endif; ?>
            <li><a class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/audit/') !== false ? 'active' : '' ?>" href="/kghbms/audit/logs.php"><i class="fas fa-file-alt"></i><span>Audit Logs</span></a></li>
        </ul>
        <?php endif; ?>
    </div>
</nav>

<!-- Main Content Wrapper -->
<div class="main-wrapper" id="mainWrapper">
<div class="main-content">

<?php foreach ($flashMessages as $flash): ?>
<div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show mx-3 mt-3" role="alert">
    <i class="fas fa-<?= $flash['type'] === 'success' ? 'check-circle' : ($flash['type'] === 'danger' ? 'exclamation-triangle' : 'info-circle') ?> me-2"></i>
    <?= e($flash['msg']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endforeach; ?>
