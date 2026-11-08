<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();
$pdo = getDB();
require_role(['ADMINISTRATOR','MANAGER','DOCTOR','NURSE','RECEPTION']);
$canManage = has_role(['ADMINISTRATOR','MANAGER']);

$errors = [];
$form   = ['room_type' => 'GENERAL', 'capacity' => 1];
$wards  = $pdo->query("SELECT w.ward_id, w.name, d.name AS dept FROM wards w
    JOIN departments d ON d.department_id=w.department_id
    WHERE w.status='ACTIVE' ORDER BY d.name, w.name")->fetchAll();
$editRoomId = (int)($_GET['edit'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    require_role(['ADMINISTRATOR','MANAGER']);

    $editRoomId = (int)($_POST['edit_room_id'] ?? 0);
    $deleteRoomId = (int)($_POST['delete_room_id'] ?? 0);
    if ($deleteRoomId > 0) {
        try {
            $pdo->beginTransaction();
            $roomNumber = $pdo->prepare('SELECT r.room_number FROM rooms r WHERE r.room_id = ? AND r.status = "ACTIVE" LIMIT 1');
            $roomNumber->execute([$deleteRoomId]);
            $room = $roomNumber->fetch();
            if (!$room) {
                throw new RuntimeException('Room not found or already inactive.');
            }

            $pdo->prepare('UPDATE rooms SET status = "INACTIVE" WHERE room_id = ?')->execute([$deleteRoomId]);
            $pdo->prepare('UPDATE beds SET is_active = 0 WHERE room_id = ? AND is_active = 1')->execute([$deleteRoomId]);

            audit($pdo, current_user_id(), 'DELETE_ROOM', 'rooms', $deleteRoomId, "Deleted: {$room['room_number']}");
            $pdo->commit();
            set_flash('success', "Room <strong>{$room['room_number']}</strong> archived.");
            header('Location: /kghbms/beds/rooms.php');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = $e->getMessage();
        }
    } elseif ($editRoomId > 0) {
        $form = [
            'ward_id'     => (int)($_POST['ward_id'] ?? 0),
            'room_number' => trim($_POST['room_number'] ?? ''),
            'room_type'   => $_POST['room_type'] ?? 'GENERAL',
            'capacity'    => (int)($_POST['capacity'] ?? 1),
        ];
        if (!$form['ward_id'])     $errors[] = 'Ward is required.';
        if (!$form['room_number']) $errors[] = 'Room number is required.';
        if ($form['capacity'] < 1) $errors[] = 'Capacity must be at least 1.';
        if (empty($errors)) {
            try {
                $pdo->prepare('UPDATE rooms SET ward_id=?, room_number=?, room_type=?, capacity=? WHERE room_id=?')
                    ->execute([$form['ward_id'], $form['room_number'], $form['room_type'], $form['capacity'], $editRoomId]);
                audit($pdo, current_user_id(), 'EDIT_ROOM', 'rooms', $editRoomId, "Updated: {$form['room_number']}");
                set_flash('success', "Room <strong>{$form['room_number']}</strong> updated.");
                header('Location: /kghbms/beds/rooms.php');
                exit;
            } catch (PDOException $e) {
                $errors[] = str_contains($e->getMessage(), 'Duplicate') ? 'Room number already exists in this ward.' : $e->getMessage();
            }
        }
    } else {
        $form = [
            'ward_id'     => (int)($_POST['ward_id'] ?? 0),
            'room_number' => trim($_POST['room_number'] ?? ''),
            'room_type'   => $_POST['room_type'] ?? 'GENERAL',
            'capacity'    => (int)($_POST['capacity'] ?? 1),
        ];
        if (!$form['ward_id'])     $errors[] = 'Ward is required.';
        if (!$form['room_number']) $errors[] = 'Room number is required.';
        if ($form['capacity'] < 1) $errors[] = 'Capacity must be at least 1.';
        if (empty($errors)) {
            try {
                $pdo->prepare('INSERT INTO rooms (ward_id,room_number,room_type,capacity) VALUES (?,?,?,?)')
                    ->execute([$form['ward_id'], $form['room_number'], $form['room_type'], $form['capacity']]);
                audit($pdo, current_user_id(), 'ADD_ROOM', 'rooms', (int)$pdo->lastInsertId(), "Added: {$form['room_number']}");
                set_flash('success', "Room <strong>{$form['room_number']}</strong> added.");
                header('Location: /kghbms/beds/rooms.php');
                exit;
            } catch (PDOException $e) {
                $errors[] = str_contains($e->getMessage(), 'Duplicate') ? 'Room number already exists in this ward.' : $e->getMessage();
            }
        }
    }
}

if ($editRoomId > 0 && empty($_POST)) {
    $existingRoom = $pdo->prepare('SELECT * FROM rooms WHERE room_id = ? LIMIT 1');
    $existingRoom->execute([$editRoomId]);
    $room = $existingRoom->fetch();
    if ($room) {
        $form = [
            'ward_id' => (int)$room['ward_id'],
            'room_number' => $room['room_number'],
            'room_type' => $room['room_type'],
            'capacity' => (int)$room['capacity'],
        ];
    }
}

$rooms = $pdo->query("SELECT r.*, w.name AS ward_name, d.name AS dept_name,
    COUNT(b.bed_id) AS beds
    FROM rooms r JOIN wards w ON w.ward_id=r.ward_id
    JOIN departments d ON d.department_id=w.department_id
    LEFT JOIN beds b ON b.room_id=r.room_id AND b.is_active=1
    GROUP BY r.room_id ORDER BY d.name, w.name, r.room_number")->fetchAll();

$pageTitle = 'Rooms';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div><h1><i class="fas fa-door-open me-2 text-primary"></i>Rooms</h1>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item active">Rooms</li></ol></nav></div>
</div>
<div class="row g-4">
    <?php if ($canManage): ?>
    <div class="col-12 col-md-4">
        <div class="card">
            <div class="card-header"><i class="fas fa-<?= $editRoomId ? 'edit' : 'plus' ?> me-2"></i><?= $editRoomId ? 'Edit Room' : 'Add Room' ?></div>
            <div class="card-body">
                <?php if ($errors): ?><div class="alert alert-danger small"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
                <form method="post" data-loading><?= csrf_field() ?>
                    <?php if ($editRoomId): ?>
                    <input type="hidden" name="edit_room_id" value="<?= (int)$editRoomId ?>">
                    <?php endif; ?>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Ward <span class="required-mark">*</span></label>
                        <select name="ward_id" class="form-select form-select-sm" required>
                            <option value="">Select…</option>
                            <?php foreach ($wards as $w): ?>
                            <option value="<?= $w['ward_id'] ?>" <?= ($form['ward_id']??0)==$w['ward_id']?'selected':'' ?>><?= e($w['dept'].' › '.$w['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Room Number <span class="required-mark">*</span></label>
                        <input type="text" name="room_number" class="form-control form-control-sm" required maxlength="20" value="<?= e($form['room_number']??'') ?>" placeholder="e.g. MA-101">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Room Type</label>
                        <select name="room_type" class="form-select form-select-sm">
                            <?php foreach (['GENERAL','PRIVATE','SEMI_PRIVATE','ICU','ISOLATION'] as $t): ?>
                            <option value="<?= $t ?>" <?= ($form['room_type']??'GENERAL')===$t?'selected':'' ?>><?= $t ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Capacity (beds)</label>
                        <input type="number" name="capacity" class="form-control form-control-sm" min="1" max="20" value="<?= (int)($form['capacity']??1) ?>">
                    </div>
                    <button type="submit" class="btn btn-<?= $editRoomId ? 'warning' : 'primary' ?> btn-sm w-100"><i class="fas fa-<?= $editRoomId ? 'save' : 'plus' ?> me-1"></i><?= $editRoomId ? 'Save Changes' : 'Add Room' ?></button>
                    <?php if ($editRoomId): ?>
                    <a href="/kghbms/beds/rooms.php" class="btn btn-outline-secondary btn-sm w-100 mt-2">Cancel</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <div class="col-12 <?= $canManage ? 'col-md-8' : '' ?>">
        <div class="card">
            <div class="card-header"><i class="fas fa-list me-2"></i>Rooms (<?= count($rooms) ?>)</div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Room No.</th><th>Ward</th><th>Department</th><th>Type</th><th>Capacity</th><th>Beds</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($rooms as $r): ?>
                        <tr>
                            <td class="fw-semibold"><?= e($r['room_number']) ?></td>
                            <td class="small"><?= e($r['ward_name']) ?></td>
                            <td class="small"><?= e($r['dept_name']) ?></td>
                            <td><span class="badge bg-light text-dark border"><?= e($r['room_type']) ?></span></td>
                            <td class="text-center"><?= $r['capacity'] ?></td>
                            <td class="text-center"><span class="badge bg-info"><?= $r['beds'] ?></span></td>
                            <td><?= status_badge($r['status']) ?></td>
                            <td>
                                <?php if ($canManage): ?>
                                <div class="d-flex gap-1">
                                    <a href="/kghbms/beds/rooms.php?edit=<?= (int)$r['room_id'] ?>" class="btn btn-sm btn-outline-warning" title="Edit room"><i class="fas fa-edit"></i></a>
                                    <form method="post" style="display:inline;" data-loading>
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="delete_room_id" value="<?= (int)$r['room_id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Archive this room and all its beds?" title="Delete room"><i class="fas fa-trash"></i></button>
                                    </form>
                                </div>
                                <?php else: ?><span class="text-muted small">View only</span><?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($rooms)): ?><tr><td colspan="8" class="text-muted text-center py-3">No rooms yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
