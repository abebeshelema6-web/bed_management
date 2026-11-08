<?php
$pageTitle = 'Audit Logs';
require_once __DIR__ . '/../includes/header.php';
require_role(['ADMINISTRATOR','MANAGER']);

$q       = trim($_GET['q'] ?? '');
$action  = $_GET['action'] ?? '';
$from    = $_GET['from'] ?? '';
$to      = $_GET['to']   ?? '';
$page    = max(1,(int)($_GET['page'] ?? 1));
$perPage = 25; $offset = ($page-1)*$perPage;

$where = '1=1'; $params = [];
if ($q)      { $where .= ' AND (u.full_name LIKE ? OR u.username LIKE ? OR al.details LIKE ?)'; $params[]="%$q%"; $params[]="%$q%"; $params[]="%$q%"; }
if ($action) { $where .= ' AND al.action=?'; $params[]=$action; }
if ($from)   { $where .= ' AND DATE(al.created_at)>=?'; $params[]=$from; }
if ($to)     { $where .= ' AND DATE(al.created_at)<=?'; $params[]=$to; }

$total = $pdo->prepare("SELECT COUNT(*) FROM audit_logs al LEFT JOIN users u ON u.user_id=al.user_id WHERE $where");
$total->execute($params); $totalCount=(int)$total->fetchColumn();

$stmt = $pdo->prepare("
    SELECT al.*, u.full_name, u.username, u.role_id,
           r.role_name
    FROM audit_logs al
    LEFT JOIN users u ON u.user_id=al.user_id
    LEFT JOIN roles r ON r.role_id=u.role_id
    WHERE $where
    ORDER BY al.audit_id DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params); $logs = $stmt->fetchAll();
$pag = paginate($totalCount, $perPage, $page);

// Action list for filter
$actions = $pdo->query("SELECT DISTINCT action FROM audit_logs ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);

$actionIcons = [
    'LOGIN'=>'sign-in-alt','LOGOUT'=>'sign-out-alt','LOGIN_FAILED'=>'times',
    'LOGIN_BLOCKED'=>'ban','REGISTER_PATIENT'=>'user-plus','UPDATE_PATIENT'=>'user-edit',
    'CREATE_ADMISSION'=>'procedures','ALLOCATE_BED'=>'bed','TRANSFER_PATIENT'=>'exchange-alt',
    'DISCHARGE_PATIENT'=>'sign-out-alt','CHANGE_BED_STATUS'=>'cog','CREATE_RESERVATION'=>'bookmark',
    'CANCEL_RESERVATION'=>'times','CREATE_USER'=>'user-plus','UPDATE_USER'=>'user-edit',
    'TOGGLE_USER_STATUS'=>'toggle-on','UNLOCK_USER'=>'unlock','CHANGE_PASSWORD'=>'key',
    'ADD_DEPARTMENT'=>'building','ADD_WARD'=>'layer-group','ADD_ROOM'=>'door-open','ADD_BED'=>'bed',
    'EXPIRE_RESERVATION'=>'clock',
    'ASSIGN_NURSES_TO_ADMISSION'=>'user-nurse','UPDATE_ADMISSION_CLINICAL'=>'notes-medical',
];
?>
<div class="page-header">
    <div>
        <h1><i class="fas fa-file-alt me-2 text-primary"></i>Audit Logs</h1>
        <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item active">Audit Logs</li></ol></nav>
    </div>
    <span class="badge bg-secondary fs-6"><?= $totalCount ?> total records</span>
</div>

<div class="card mb-3">
    <div class="card-body py-2">
        <form class="row g-2 align-items-end" method="get">
            <div class="col-12 col-md-3">
                <input type="text" name="q" class="form-control form-control-sm" placeholder="User name, username, details…" value="<?= e($q) ?>">
            </div>
            <div class="col-12 col-md-2">
                <select name="action" class="form-select form-select-sm">
                    <option value="">All Actions</option>
                    <?php foreach ($actions as $a): ?>
                    <option value="<?= e($a) ?>" <?= $action===$a?'selected':'' ?>><?= e($a) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto"><input type="date" name="from" class="form-control form-control-sm" value="<?= e($from) ?>" placeholder="From"></div>
            <div class="col-auto"><input type="date" name="to" class="form-control form-control-sm" value="<?= e($to) ?>" placeholder="To"></div>
            <div class="col-auto d-flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search me-1"></i>Filter</button>
                <a href="?" class="btn btn-outline-secondary btn-sm">Clear</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead><tr>
                <th>#</th><th>Action</th><th>User</th><th>Role</th>
                <th>Table / Record</th><th>Details</th><th>IP</th><th>Time</th>
            </tr></thead>
            <tbody>
                <?php foreach ($logs as $log):
                    $icon = $actionIcons[$log['action']] ?? 'circle';
                    $isAuth = in_array($log['action'],['LOGIN_FAILED','LOGIN_BLOCKED']);
                ?>
                <tr class="<?= $isAuth?'table-warning':'' ?>">
                    <td class="text-muted small"><?= $log['audit_id'] ?></td>
                    <td>
                        <span class="badge bg-<?= $isAuth?'warning text-dark':($log['action']==='DISCHARGE_PATIENT'?'secondary':'light text-dark border') ?>">
                            <i class="fas fa-<?= $icon ?> me-1"></i><?= e($log['action']) ?>
                        </span>
                    </td>
                    <td class="small">
                        <?php if ($log['full_name']): ?>
                        <div class="fw-semibold"><?= e($log['full_name']) ?></div>
                        <div class="text-muted" style="font-size:.72rem"><code><?= e($log['username']) ?></code></div>
                        <?php else: ?>
                        <span class="text-muted">Unknown / System</span>
                        <?php endif; ?>
                    </td>
                    <td class="small">
                        <?php if ($log['role_name']): ?>
                        <span class="badge bg-light text-dark border"><?= e($log['role_name']) ?></span>
                        <?php else: echo '—'; endif; ?>
                    </td>
                    <td class="small">
                        <?php if ($log['table_name']): ?>
                        <code><?= e($log['table_name']) ?></code>
                        <?php if ($log['record_id']): ?><span class="text-muted"> #<?= $log['record_id'] ?></span><?php endif; ?>
                        <?php else: echo '—'; endif; ?>
                    </td>
                    <td class="small text-muted" style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= e($log['details']??'') ?>">
                        <?= e($log['details'] ?? '—') ?>
                    </td>
                    <td class="small text-muted"><?= e($log['ip_address'] ?? '—') ?></td>
                    <td class="small text-muted text-nowrap"><?= date('d/m/Y H:i:s',strtotime($log['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($logs)): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">
                    <i class="fas fa-file-alt fa-2x mb-2 d-block"></i>No audit records found.
                </td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pag['pages'] > 1): ?>
    <div class="card-footer d-flex justify-content-between align-items-center">
        <div class="text-muted small">Showing <?= $offset+1 ?>–<?= min($offset+$perPage,$totalCount) ?> of <?= $totalCount ?></div>
        <?= pagination_links($pag,'?q='.urlencode($q).'&action='.urlencode($action).'&from='.urlencode($from).'&to='.urlencode($to)) ?>
    </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
