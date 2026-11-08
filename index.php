<?php
$pageTitle = 'Notifications';
require_once __DIR__ . '/../includes/header.php';
require_login();

// Mark single as read
if (isset($_GET['mark']) && is_numeric($_GET['mark'])) {
    $nid = (int)$_GET['mark'];
    $pdo->prepare('UPDATE notifications SET is_read=1 WHERE notification_id=? AND user_id=?')
        ->execute([$nid, current_user_id()]);
    header('Location: /kghbms/notifications/index.php'); exit;
}

// Mark all as read
if (isset($_GET['mark_all'])) {
    $pdo->prepare('UPDATE notifications SET is_read=1 WHERE user_id=?')
        ->execute([current_user_id()]);
    set_flash('success','All notifications marked as read.');
    header('Location: /kghbms/notifications/index.php'); exit;
}

$page    = max(1,(int)($_GET['page'] ?? 1));
$perPage = 20; $offset = ($page-1)*$perPage;

$total = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=?');
$total->execute([current_user_id()]); $totalCount=(int)$total->fetchColumn();

$stmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id=? ORDER BY notification_id DESC LIMIT ? OFFSET ?');
$stmt->execute([current_user_id(), $perPage, $offset]);
$notifs = $stmt->fetchAll();
$pag = paginate($totalCount, $perPage, $page);

$unread = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0');
$unread->execute([current_user_id()]); $unreadCount=(int)$unread->fetchColumn();

$typeIcons = [
    'INFO'=>['fa-info-circle','info'],
    'RESERVATION'=>['fa-bookmark','warning'],
    'TRANSFER'=>['fa-exchange-alt','primary'],
    'BED_READY'=>['fa-check-circle','success'],
    'ALERT'=>['fa-exclamation-triangle','danger'],
];
?>
<div class="page-header">
    <div>
        <h1><i class="fas fa-bell me-2 text-warning"></i>Notifications
            <?php if ($unreadCount > 0): ?><span class="badge bg-danger ms-2"><?= $unreadCount ?> new</span><?php endif; ?>
        </h1>
        <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item active">Notifications</li></ol></nav>
    </div>
    <?php if ($unreadCount > 0): ?>
    <a href="?mark_all=1" class="btn btn-outline-secondary btn-sm" data-confirm="Mark all notifications as read?">
        <i class="fas fa-check-double me-1"></i>Mark All Read
    </a>
    <?php endif; ?>
</div>

<div class="card">
    <?php if (empty($notifs)): ?>
    <div class="text-center text-muted py-5">
        <i class="fas fa-bell-slash fa-3x mb-3 d-block opacity-25"></i>
        No notifications yet.
    </div>
    <?php else: ?>
    <div class="list-group list-group-flush">
        <?php foreach ($notifs as $n):
            [$icon, $color] = $typeIcons[$n['type']] ?? ['fa-info-circle','secondary'];
        ?>
        <div class="list-group-item list-group-item-action d-flex align-items-start gap-3 py-3 <?= !$n['is_read']?'list-group-item-light':'' ?>">
            <div class="mt-1 flex-shrink-0">
                <span class="badge bg-<?= $color ?> p-2 rounded-circle">
                    <i class="fas <?= $icon ?>"></i>
                </span>
            </div>
            <div class="flex-grow-1">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="fw-semibold <?= !$n['is_read']?'text-dark':'text-muted' ?>">
                        <?= e($n['title']) ?>
                        <?php if (!$n['is_read']): ?><span class="badge bg-primary ms-1 small">New</span><?php endif; ?>
                    </div>
                    <span class="text-muted small ms-2 text-nowrap"><?= date('d/m/Y H:i',strtotime($n['created_at'])) ?></span>
                </div>
                <div class="text-muted small mt-1"><?= e($n['message']) ?></div>
            </div>
            <?php if (!$n['is_read']): ?>
            <div class="flex-shrink-0">
                <a href="?mark=<?= $n['notification_id'] ?>" class="btn btn-xs btn-outline-secondary p-1" title="Mark read">
                    <i class="fas fa-check"></i>
                </a>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php if ($pag['pages'] > 1): ?>
    <div class="card-footer d-flex justify-content-between align-items-center">
        <div class="text-muted small">Showing <?= $offset+1 ?>–<?= min($offset+$perPage,$totalCount) ?> of <?= $totalCount ?></div>
        <?= pagination_links($pag,'?') ?>
    </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
