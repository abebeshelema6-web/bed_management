<?php
/**
 * Database Installer — Kuyu HBMS
 * Run once at: http://localhost/kghbms/install.php
 */
$host = 'localhost';
$user = 'root';
$pass = '';
$dbName = 'kuyu_bed_management';

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Connect without a database first
        $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        // Create database
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `$dbName`");

        $sql = file_get_contents(__DIR__ . '/install_schema.sql');
        // Split by semicolon and run each statement
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        foreach ($statements as $stmt) {
            if ($stmt !== '') $pdo->exec($stmt);
        }

        // Insert initial admin password hash
        $adminPass = password_hash('Admin@1234', PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password_hash = ? WHERE username = 'admin'")->execute([$adminPass]);

        $success = true;
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Install — Kuyu HBMS</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="bg-light">
<div class="container mt-5" style="max-width:580px;">
    <div class="card shadow">
        <div class="card-header bg-primary text-white text-center py-3">
            <h4 class="mb-0"><i class="fas fa-hospital me-2"></i>Kuyu HBMS — Database Installer</h4>
        </div>
        <div class="card-body p-4">
            <?php if ($success): ?>
            <div class="alert alert-success">
                <h5><i class="fas fa-check-circle me-2"></i>Installation Successful!</h5>
                <p>Database <strong><?= $dbName ?></strong> has been created with all tables.</p>
                <hr>
                <p class="mb-1"><strong>Default Admin Login:</strong></p>
                <p class="mb-1">Username: <code>admin</code></p>
                <p class="mb-3">Password: <code>Admin@1234</code></p>
                <div class="alert alert-warning py-2"><i class="fas fa-exclamation-triangle me-1"></i>Change the admin password immediately after first login!</div>
            </div>
            <a href="/kghbms/login.php" class="btn btn-primary btn-lg w-100">
                <i class="fas fa-sign-in-alt me-2"></i>Go to Login
            </a>
            <?php else: ?>
            <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $e): ?><p class="mb-1"><i class="fas fa-times me-1"></i><?= htmlspecialchars($e) ?></p><?php endforeach; ?>
            </div>
            <?php endif; ?>
            <p class="text-muted mb-3">This will create the <strong><?= $dbName ?></strong> database and all required tables. Ensure XAMPP MySQL is running.</p>
            <form method="post">
                <div class="mb-3">
                    <label class="form-label">MySQL Host</label>
                    <input type="text" class="form-control" value="<?= $host ?>" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label">Database</label>
                    <input type="text" class="form-control" value="<?= $dbName ?>" readonly>
                </div>
                <button type="submit" class="btn btn-primary btn-lg w-100">
                    <i class="fas fa-database me-2"></i>Install Database
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
