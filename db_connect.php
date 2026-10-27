<?php
// ============================================================================
// db_connect.php — Central MySQL Configuration (Alwaysdata.net & XAMPP Ready)
// ============================================================================
// FOR ALWAYSDATA.NET HOSTING, replace the 4 values below with your Alwaysdata details:
//   $host = 'mysql-YOUR_ACCOUNT.alwaysdata.net';
//   $db   = 'YOUR_ACCOUNT_aidj';
//   $user = 'YOUR_ACCOUNT';
//   $pass = 'YOUR_MYSQL_PASSWORD';
// ============================================================================

$host = getenv('DB_HOST') ?: 'localhost';
$db   = getenv('DB_NAME') ?: 'ai_dj_studio';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';

$pdo = null;
$dbConnected = false;
$dbError = null;

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    $dbConnected = true;
} catch (PDOException $e) {
    $dbConnected = false;
    $dbError = $e->getMessage();
}
