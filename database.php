<?php
/**
 * Database Configuration - Kuyu General Hospital BMS
 */
define('DB_HOST', 'localhost');
define('DB_NAME', 'kuyu_bed_management');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME', 'Kuyu General Hospital BMS');
define('APP_VERSION', '1.0.0');
define('SESSION_TIMEOUT', 1200); // 20 minutes

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('DB Connection Error: ' . $e->getMessage());
            die('<div style="font-family:sans-serif;padding:40px;text-align:center;"><h2>Database Connection Error</h2><p>Could not connect to the database. Please ensure XAMPP MySQL is running and the database <strong>' . DB_NAME . '</strong> exists.</p><p><a href="/kghbms/install.php">Run Installer</a></p></div>');
        }
    }
    return $pdo;
}
