<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_role(['ADMINISTRATOR','MANAGER','DOCTOR','NURSE','RECEPTION']);
header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
$pdo = getDB();

if (strlen($q) < 1) { echo json_encode([]); exit; }

$like = "%$q%";
$sql = 'SELECT p.patient_id, p.patient_number, p.full_name, p.gender, p.date_of_birth, p.phone
        FROM patients p
        WHERE (p.full_name LIKE ? OR p.patient_number = ? OR p.phone = ?)';
$params = [$like, $q, $q];
if (has_role(['NURSE'])) {
    $sql .= ' AND EXISTS (SELECT 1 FROM admissions a
        JOIN admission_nurse_assignments ana ON ana.admission_id=a.admission_id
        WHERE a.patient_id=p.patient_id AND ana.nurse_user_id=?)';
    $params[] = current_user_id();
}
$sql .= ' ORDER BY p.full_name LIMIT 20';
$st = $pdo->prepare($sql);
$st->execute($params);
echo json_encode($st->fetchAll());
