<?php
// ── Process before any output ─────────────────────────────────────────────────
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();
$pdo = getDB();
require_role(['ADMINISTRATOR','RECEPTION','MANAGER']);

$errors = [];
$form   = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $form = [
        'full_name'               => trim($_POST['full_name'] ?? ''),
        'gender'                  => $_POST['gender'] ?? '',
        'date_of_birth'           => $_POST['date_of_birth'] ?? '',
        'phone'                   => trim($_POST['phone'] ?? ''),
        'address'                 => trim($_POST['address'] ?? ''),
        'emergency_contact_name'  => trim($_POST['emergency_contact_name'] ?? ''),
        'emergency_contact_phone' => trim($_POST['emergency_contact_phone'] ?? ''),
    ];
    if (empty($form['full_name']))          $errors[] = 'Full name is required.';
    elseif (mb_strlen($form['full_name']) > 150) $errors[] = 'Full name too long.';
    if (!in_array($form['gender'], ['MALE','FEMALE','OTHER'])) $errors[] = 'Gender is required.';
    if (empty($form['date_of_birth']))       $errors[] = 'Date of birth is required.';
    elseif ($form['date_of_birth'] > date('Y-m-d')) $errors[] = 'Date of birth cannot be in the future.';

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            $ins = $pdo->prepare('INSERT INTO patients (patient_number, full_name, gender, date_of_birth,
                                  phone, address, emergency_contact_name, emergency_contact_phone, created_by)
                                  VALUES (?,?,?,?,?,?,?,?,?)');
            $ins->execute(['TMP', $form['full_name'], $form['gender'], $form['date_of_birth'],
                           $form['phone'] ?: null, $form['address'] ?: null,
                           $form['emergency_contact_name'] ?: null,
                           $form['emergency_contact_phone'] ?: null,
                           current_user_id()]);
            $newId  = (int)$pdo->lastInsertId();
            $number = sprintf('P-%06d', $newId);
            $pdo->prepare('UPDATE patients SET patient_number = ? WHERE patient_id = ?')
                ->execute([$number, $newId]);
            audit($pdo, current_user_id(), 'REGISTER_PATIENT', 'patients', $newId, "Registered: {$form['full_name']}");
            $pdo->commit();
            set_flash('success', "Patient <strong>{$form['full_name']}</strong> registered as <strong>$number</strong>.");
            header("Location: /kghbms/patients/view.php?id=$newId");
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = 'Could not save patient: ' . $e->getMessage();
        }
    }
}

// ── Output ─────────────────────────────────────────────────────────────────────
$pageTitle = 'Register Patient';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div>
        <h1><i class="fas fa-user-plus me-2 text-primary"></i>Register Patient</h1>
        <nav aria-label="breadcrumb"><ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/kghbms/patients/list.php">Patients</a></li>
            <li class="breadcrumb-item active">Register</li>
        </ol></nav>
    </div>
</div>
<div class="row justify-content-center">
<div class="col-12 col-xl-8">
<div class="card">
    <div class="card-header"><i class="fas fa-id-card me-2"></i>Patient Information</div>
    <div class="card-body">
        <?php if ($errors): ?>
        <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>
        <form method="post" data-loading>
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">Full Name <span class="required-mark">*</span></label>
                    <input type="text" name="full_name" class="form-control" maxlength="150" required
                           value="<?= e($form['full_name'] ?? '') ?>" placeholder="Enter full name">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Gender <span class="required-mark">*</span></label>
                    <select name="gender" class="form-select" required>
                        <option value="">Select gender…</option>
                        <?php foreach (['MALE','FEMALE','OTHER'] as $g): ?>
                        <option value="<?= $g ?>" <?= ($form['gender'] ?? '') === $g ? 'selected' : '' ?>><?= $g ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Date of Birth <span class="required-mark">*</span></label>
                    <input type="date" name="date_of_birth" class="form-control" required
                           max="<?= date('Y-m-d') ?>" value="<?= e($form['date_of_birth'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Phone Number</label>
                    <input type="tel" name="phone" class="form-control" maxlength="20"
                           value="<?= e($form['phone'] ?? '') ?>" placeholder="+251…">
                </div>
                <div class="col-12">
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-control" rows="2" maxlength="255"
                              placeholder="Woreda, Town, Region"><?= e($form['address'] ?? '') ?></textarea>
                </div>
                <div class="col-12"><div class="section-divider"></div>
                    <h6 class="text-muted"><i class="fas fa-phone-alt me-1"></i>Emergency Contact</h6>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Contact Name</label>
                    <input type="text" name="emergency_contact_name" class="form-control" maxlength="100"
                           value="<?= e($form['emergency_contact_name'] ?? '') ?>" placeholder="Name of family member">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Contact Phone</label>
                    <input type="tel" name="emergency_contact_phone" class="form-control" maxlength="20"
                           value="<?= e($form['emergency_contact_phone'] ?? '') ?>" placeholder="+251…">
                </div>
                <div class="col-12 d-flex gap-2 mt-2">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Register Patient</button>
                    <a href="/kghbms/patients/list.php" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </div>
        </form>
    </div>
</div>
</div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
