<?php
// signup.php — Inserts new user into MySQL 'users' table
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/db_connect.php';

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$email = trim($input['email'] ?? '');
$password = $input['password'] ?? '';
$role = $input['role'] ?? 'listener';
$name = trim($input['name'] ?? explode('@', $email)[0]);

if (empty($email) || empty($password)) {
    echo json_encode(['status' => 'error', 'message' => 'Email and Password are required!']);
    exit;
}

// 1. Check if user already exists
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->fetch()) {
    echo json_encode(['status' => 'error', 'message' => 'User with this email already exists! Please login.']);
    exit;
}

// 2. Hash Password and Insert
$userId = 'u_' . rand(1000, 9999);
$passHash = password_hash($password, PASSWORD_DEFAULT);

$insert = $pdo->prepare("INSERT INTO users (id, email, password_hash, display_name, role, status) VALUES (?, ?, ?, ?, ?, 'active')");
$insert->execute([$userId, $email, $passHash, $name, $role]);

echo json_encode([
    'status' => 'success',
    'message' => 'User registered and added to MySQL database successfully!',
    'user' => [
        'id' => $userId,
        'name' => $name,
        'email' => $email,
        'role' => $role
    ]
]);
