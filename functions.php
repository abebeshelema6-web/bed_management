<?php
/**
 * Core Helper & Business Logic Functions - Kuyu HBMS
 */
require_once __DIR__ . '/../includes/auth.php';

// ─── Output escaping ──────────────────────────────────────────────────────────
function e(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// ─── Flash messages ───────────────────────────────────────────────────────────
function set_flash(string $type, string $msg): void {
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function get_flash(): array {
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

// ─── Audit logging ────────────────────────────────────────────────────────────
function audit(PDO $pdo, ?int $userId, string $action, ?string $table = null,
               ?int $recordId = null, ?string $details = null): void {
    try {
        $pdo->prepare('INSERT INTO audit_logs (user_id, action, table_name, record_id,
                       details, ip_address, user_agent) VALUES (?,?,?,?,?,?,?)')
            ->execute([$userId, $action, $table, $recordId, $details,
                       $_SERVER['REMOTE_ADDR'] ?? null,
                       substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)]);
    } catch (Throwable $e) {
        error_log('Audit log error: ' . $e->getMessage());
    }
}

// ─── Notifications ────────────────────────────────────────────────────────────
function notify_user(PDO $pdo, int $userId, string $title, string $msg, string $type = 'INFO'): void {
    $pdo->prepare('INSERT INTO notifications (user_id, title, message, type) VALUES (?,?,?,?)')
        ->execute([$userId, $title, $msg, $type]);
}

function notify_role(PDO $pdo, string $role, string $title, string $msg, string $type = 'INFO'): void {
    $pdo->prepare('INSERT INTO notifications (user_id, title, message, type)
                   SELECT u.user_id, ?, ?, ? FROM users u
                   JOIN roles r ON r.role_id = u.role_id
                   WHERE r.role_name = ? AND u.status = "ACTIVE"')
        ->execute([$title, $msg, $type, $role]);
}

function get_unread_count(PDO $pdo, int $userId): int {
    $st = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $st->execute([$userId]);
    return (int)$st->fetchColumn();
}

function is_admission_assigned_to_nurse(PDO $pdo, int $admissionId, int $nurseUserId): bool {
    $st = $pdo->prepare('SELECT 1 FROM admission_nurse_assignments WHERE admission_id=? AND nurse_user_id=? LIMIT 1');
    $st->execute([$admissionId, $nurseUserId]);
    return (bool)$st->fetchColumn();
}

function is_patient_assigned_to_nurse(PDO $pdo, int $patientId, int $nurseUserId): bool {
    $st = $pdo->prepare('SELECT 1 FROM admissions a
        JOIN admission_nurse_assignments ana ON ana.admission_id=a.admission_id
        WHERE a.patient_id=? AND ana.nurse_user_id=? LIMIT 1');
    $st->execute([$patientId, $nurseUserId]);
    return (bool)$st->fetchColumn();
}

// ─── Bed status transitions ───────────────────────────────────────────────────
const BED_TRANSITIONS = [
    'AVAILABLE'      => ['OCCUPIED', 'RESERVED', 'MAINTENANCE', 'BLOCKED'],
    'RESERVED'       => ['OCCUPIED', 'AVAILABLE'],
    'OCCUPIED'       => ['CLEANING'],
    'CLEANING'       => ['AVAILABLE', 'MAINTENANCE'],
    'MAINTENANCE'    => ['AVAILABLE', 'OUT_OF_SERVICE'],
    'BLOCKED'        => ['AVAILABLE'],
    'OUT_OF_SERVICE' => ['AVAILABLE'],
];

const BED_STATUS_COLORS = [
    'AVAILABLE'      => 'success',
    'OCCUPIED'       => 'danger',
    'RESERVED'       => 'warning',
    'CLEANING'       => 'info',
    'MAINTENANCE'    => 'purple',
    'BLOCKED'        => 'secondary',
    'OUT_OF_SERVICE' => 'dark',
];

const BED_STATUS_ICONS = [
    'AVAILABLE'      => 'fa-check-circle',
    'OCCUPIED'       => 'fa-user',
    'RESERVED'       => 'fa-clock',
    'CLEANING'       => 'fa-broom',
    'MAINTENANCE'    => 'fa-wrench',
    'BLOCKED'        => 'fa-ban',
    'OUT_OF_SERVICE' => 'fa-times-circle',
];

/**
 * Change bed status. Must be called inside an open transaction with the bed row locked.
 */
function set_bed_status(PDO $pdo, int $bedId, string $new, int $userId, string $reason = ''): void {
    $st = $pdo->prepare('SELECT current_status FROM beds WHERE bed_id = ?');
    $st->execute([$bedId]);
    $old = $st->fetchColumn();
    if ($old === false) {
        throw new RuntimeException("Bed #$bedId not found.");
    }
    $allowed = BED_TRANSITIONS[$old] ?? [];
    if (!in_array($new, $allowed, true)) {
        throw new RuntimeException("Status change $old → $new is not allowed for bed #$bedId.");
    }
    $pdo->prepare('UPDATE beds SET current_status = ?, updated_at = NOW() WHERE bed_id = ?')
        ->execute([$new, $bedId]);
    $pdo->prepare('INSERT INTO bed_status_history (bed_id, old_status, new_status, changed_by, reason)
                   VALUES (?,?,?,?,?)')
        ->execute([$bedId, $old, $new, $userId, $reason]);
}

// ─── Bed Allocation ───────────────────────────────────────────────────────────
function allocate_bed(PDO $pdo, int $admissionId, int $bedId, int $userId): array {
    try {
        $pdo->beginTransaction();

        // Check admission is ACTIVE and lock it
        $st = $pdo->prepare("SELECT patient_id FROM admissions
                             WHERE admission_id = ? AND status = 'ACTIVE' FOR UPDATE");
        $st->execute([$admissionId]);
        $adm = $st->fetch();
        if (!$adm) throw new RuntimeException('No active admission found.');

        // Check admission has no active bed
        $st = $pdo->prepare('SELECT 1 FROM bed_allocations WHERE admission_id = ? AND is_active = 1');
        $st->execute([$admissionId]);
        if ($st->fetch()) throw new RuntimeException('This patient already has an allocated bed.');

        // Lock and validate the bed
        $st = $pdo->prepare('SELECT b.current_status FROM beds b
              JOIN rooms r ON r.room_id = b.room_id AND r.status = "ACTIVE"
              JOIN wards w ON w.ward_id = r.ward_id AND w.status = "ACTIVE"
              WHERE b.bed_id = ? AND b.is_active = 1 FOR UPDATE');
        $st->execute([$bedId]);
        $status = $st->fetchColumn();
        if ($status === false) throw new RuntimeException('Bed not found or inactive.');

        if ($status === 'RESERVED') {
            $rs = $pdo->prepare("SELECT reservation_id FROM bed_reservations
                 WHERE bed_id = ? AND patient_id = ? AND status = 'ACTIVE' FOR UPDATE");
            $rs->execute([$bedId, $adm['patient_id']]);
            $res = $rs->fetchColumn();
            if (!$res) throw new RuntimeException('Bed is reserved for a different patient.');
            $pdo->prepare("UPDATE bed_reservations SET status = 'FULFILLED' WHERE reservation_id = ?")
                ->execute([$res]);
        } elseif ($status !== 'AVAILABLE') {
            throw new RuntimeException("Bed is $status and cannot be allocated.");
        }

        // Create allocation
        $pdo->prepare('INSERT INTO bed_allocations (admission_id, bed_id, allocated_by) VALUES (?,?,?)')
            ->execute([$admissionId, $bedId, $userId]);

        // Change bed status
        set_bed_status($pdo, $bedId, 'OCCUPIED', $userId, 'Bed allocation');

        audit($pdo, $userId, 'ALLOCATE_BED', 'bed_allocations', $bedId,
              "Admission #$admissionId → Bed #$bedId");

        $pdo->commit();
        return [true, 'Bed allocated successfully.'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return [false, $e->getMessage()];
    }
}

// ─── Bed Transfer ─────────────────────────────────────────────────────────────
function transfer_patient(PDO $pdo, int $admissionId, int $toBedId, string $reason, int $userId): array {
    try {
        if (trim($reason) === '') throw new RuntimeException('Transfer reason is required.');

        $pdo->beginTransaction();

        $st = $pdo->prepare('SELECT a.allocation_id, a.bed_id, ad.patient_id
               FROM bed_allocations a JOIN admissions ad ON ad.admission_id = a.admission_id
               WHERE a.admission_id = ? AND a.is_active = 1 FOR UPDATE');
        $st->execute([$admissionId]);
        $cur = $st->fetch();
        if (!$cur) throw new RuntimeException('Patient has no active bed allocation.');
        if ((int)$cur['bed_id'] === $toBedId) throw new RuntimeException('Destination is the same as current bed.');

        // Lock both beds in ascending order to prevent deadlocks
        $ids = [(int)$cur['bed_id'], $toBedId];
        sort($ids);
        $lock = $pdo->prepare('SELECT current_status FROM beds WHERE bed_id = ? FOR UPDATE');
        foreach ($ids as $id) { $lock->execute([$id]); }

        // Verify destination
        $chk = $pdo->prepare('SELECT current_status FROM beds WHERE bed_id = ? AND is_active = 1');
        $chk->execute([$toBedId]);
        $destStatus = $chk->fetchColumn();
        if ($destStatus !== 'AVAILABLE') {
            throw new RuntimeException("Destination bed is $destStatus and cannot accept a transfer.");
        }

        // Close old allocation
        $pdo->prepare("UPDATE bed_allocations SET is_active = 0, released_at = NOW(),
                       release_reason = 'TRANSFER' WHERE allocation_id = ?")
            ->execute([$cur['allocation_id']]);

        // Create new allocation
        $pdo->prepare('INSERT INTO bed_allocations (admission_id, bed_id, allocated_by) VALUES (?,?,?)')
            ->execute([$admissionId, $toBedId, $userId]);

        // Update bed statuses
        set_bed_status($pdo, (int)$cur['bed_id'], 'CLEANING', $userId, 'Transfer out');
        set_bed_status($pdo, $toBedId, 'OCCUPIED', $userId, 'Transfer in');

        // Record transfer
        $pdo->prepare('INSERT INTO bed_transfers (admission_id, patient_id, from_bed_id,
                       to_bed_id, transferred_by, reason) VALUES (?,?,?,?,?,?)')
            ->execute([$admissionId, $cur['patient_id'], $cur['bed_id'], $toBedId, $userId, $reason]);

        audit($pdo, $userId, 'TRANSFER_PATIENT', 'bed_transfers', $admissionId,
              "Bed #{$cur['bed_id']} → Bed #$toBedId. Reason: $reason");

        notify_role($pdo, 'NURSE', 'Patient Transfer', "Patient transferred to Bed #$toBedId.", 'TRANSFER');

        $pdo->commit();
        return [true, 'Transfer completed successfully.'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return [false, $e->getMessage()];
    }
}

// ─── Patient Discharge ────────────────────────────────────────────────────────
function discharge_patient(PDO $pdo, int $admissionId, string $type, string $date,
                           string $summary, int $userId): array {
    try {
        $pdo->beginTransaction();

        $st = $pdo->prepare("SELECT patient_id, admission_date FROM admissions
                             WHERE admission_id = ? AND status = 'ACTIVE' FOR UPDATE");
        $st->execute([$admissionId]);
        $adm = $st->fetch();
        if (!$adm) throw new RuntimeException('No active admission found.');
        if (!empty($date) && strtotime($date) < strtotime($adm['admission_date'])) {
            throw new RuntimeException('Discharge date cannot be before the admission date.');
        }

        // Create discharge record
        $pdo->prepare('INSERT INTO discharges (admission_id, patient_id, discharged_by,
               discharge_type, discharge_date, summary) VALUES (?,?,?,?,?,?)')
            ->execute([$admissionId, $adm['patient_id'], $userId, $type,
                       $date ?: date('Y-m-d H:i:s'), $summary]);

        // Close admission
        $pdo->prepare("UPDATE admissions SET status = 'DISCHARGED' WHERE admission_id = ?")
            ->execute([$admissionId]);

        // Release active allocation
        $al = $pdo->prepare('SELECT allocation_id, bed_id FROM bed_allocations
                             WHERE admission_id = ? AND is_active = 1 FOR UPDATE');
        $al->execute([$admissionId]);
        if ($row = $al->fetch()) {
            $pdo->prepare("UPDATE bed_allocations SET is_active = 0, released_at = NOW(),
                           release_reason = 'DISCHARGE' WHERE allocation_id = ?")
                ->execute([$row['allocation_id']]);
            set_bed_status($pdo, (int)$row['bed_id'], 'CLEANING', $userId, 'Patient discharged');
        }

        audit($pdo, $userId, 'DISCHARGE_PATIENT', 'discharges', $admissionId,
              "Type: $type. Patient #{$adm['patient_id']}");

        $pdo->commit();
        return [true, 'Patient discharged. Bed sent to cleaning.'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return [false, $e->getMessage()];
    }
}

// ─── Expire Reservations ─────────────────────────────────────────────────────
function expire_reservations(PDO $pdo, int $systemUserId = 1): int {
    $n = 0;
    $rows = $pdo->query("SELECT reservation_id, bed_id FROM bed_reservations
                         WHERE status = 'ACTIVE' AND reserved_until < NOW()")->fetchAll();
    foreach ($rows as $r) {
        try {
            $pdo->beginTransaction();
            $pdo->prepare('SELECT bed_id FROM beds WHERE bed_id = ? FOR UPDATE')
                ->execute([$r['bed_id']]);
            $pdo->prepare("UPDATE bed_reservations SET status = 'EXPIRED'
                           WHERE reservation_id = ? AND status = 'ACTIVE'")
                ->execute([$r['reservation_id']]);
            // Only change bed if it's still RESERVED
            $chk = $pdo->prepare("SELECT current_status FROM beds WHERE bed_id = ?");
            $chk->execute([$r['bed_id']]);
            if ($chk->fetchColumn() === 'RESERVED') {
                set_bed_status($pdo, (int)$r['bed_id'], 'AVAILABLE', $systemUserId, 'Reservation expired');
            }
            audit($pdo, $systemUserId, 'EXPIRE_RESERVATION', 'bed_reservations', (int)$r['reservation_id']);
            $pdo->commit();
            $n++;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
        }
    }
    return $n;
}

// ─── Dashboard stats ─────────────────────────────────────────────────────────
function get_bed_stats(PDO $pdo): array {
    $statuses = ['AVAILABLE','OCCUPIED','RESERVED','CLEANING','MAINTENANCE','BLOCKED','OUT_OF_SERVICE'];
    $counts = array_fill_keys($statuses, 0);
    foreach ($pdo->query('SELECT current_status, COUNT(*) c FROM beds WHERE is_active = 1 GROUP BY current_status') as $r) {
        $counts[$r['current_status']] = (int)$r['c'];
    }
    $total       = array_sum($counts);
    $operational = $total - $counts['MAINTENANCE'] - $counts['BLOCKED'] - $counts['OUT_OF_SERVICE'];
    $occupancy   = $operational > 0 ? round($counts['OCCUPIED'] / $operational * 100, 1) : 0;
    $utilization = $operational > 0 ? round(($counts['OCCUPIED'] + $counts['RESERVED']) / $operational * 100, 1) : 0;
    return array_merge($counts, [
        'total'       => $total,
        'operational' => $operational,
        'occupancy'   => $occupancy,
        'utilization' => $utilization,
    ]);
}

// ─── Pagination helper ───────────────────────────────────────────────────────
function paginate(int $total, int $perPage, int $current): array {
    $pages = (int)ceil($total / $perPage);
    return ['total' => $total, 'per_page' => $perPage, 'current' => $current, 'pages' => $pages];
}

function pagination_links(array $p, string $url): string {
    if ($p['pages'] <= 1) return '';
    $html = '<nav><ul class="pagination pagination-sm mb-0">';
    $prev = $p['current'] - 1;
    $next = $p['current'] + 1;
    $html .= '<li class="page-item' . ($p['current'] <= 1 ? ' disabled' : '') . '">
                <a class="page-link" href="' . $url . '&page=' . $prev . '">«</a></li>';
    for ($i = 1; $i <= $p['pages']; $i++) {
        if ($i == $p['current'] || abs($i - $p['current']) <= 2 || $i == 1 || $i == $p['pages']) {
            $html .= '<li class="page-item' . ($i == $p['current'] ? ' active' : '') . '">
                        <a class="page-link" href="' . $url . '&page=' . $i . '">' . $i . '</a></li>';
        } elseif (abs($i - $p['current']) == 3) {
            $html .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
        }
    }
    $html .= '<li class="page-item' . ($p['current'] >= $p['pages'] ? ' disabled' : '') . '">
                <a class="page-link" href="' . $url . '&page=' . $next . '">»</a></li>';
    $html .= '</ul></nav>';
    return $html;
}

// ─── Bed status badge ─────────────────────────────────────────────────────────
function status_badge(string $status): string {
    $colors = BED_STATUS_COLORS;
    $icons  = BED_STATUS_ICONS;
    $c = $colors[$status] ?? 'secondary';
    $i = $icons[$status] ?? 'fa-circle';
    return '<span class="badge bg-' . $c . ' bed-badge"><i class="fas ' . $i . ' me-1"></i>' . e($status) . '</span>';
}
