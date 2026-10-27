<?php
// auth.php — Real MySQL Database Authentication, Registration & Password Reset for XAMPP
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// 1. MySQL Database Connection (XAMPP Default: root / no password)
require_once __DIR__ . '/db_connect.php';

$pdo = null;
$dbConnected = false;
$dbError = null;

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    $dbConnected = true;
} catch (PDOException $e) {
    $dbConnected = false;
    $dbError = $e->getMessage();
}

// 2. Read Request Payload
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action = $input['action'] ?? $_GET['action'] ?? 'login'; // 'login', 'signup', or 'reset_password'
$email = strtolower(trim($input['email'] ?? ''));
$password = $input['password'] ?? '';
$newPassword = $input['new_password'] ?? $input['newPassword'] ?? $password;
$role = $input['role'] ?? 'listener';
$displayName = trim($input['name'] ?? $input['username'] ?? explode('@', $email)[0]);

if (empty($email)) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Email address is required!'
    ]);
    exit;
}

if ($dbConnected && $pdo) {
    try {
        if ($action === 'signup') {
            // Check if user already exists
            $stmt = $pdo->prepare("SELECT id, display_name, role FROM users WHERE LOWER(email) = ?");
            $stmt->execute([$email]);
            $existing = $stmt->fetch();

            if ($existing) {
                http_response_code(409);
                echo json_encode([
                    'status' => 'error',
                    'action' => 'user_already_exists',
                    'message' => 'An account with this email already exists. Please sign in.',
                    'db_connected' => true
                ]);
                exit;
            }

            // Insert new user into MySQL with secure password_hash
            $newId = 'u_' . rand(1000, 9999);
            $passHash = password_hash($password ?: '1234', PASSWORD_DEFAULT);
            
            $insert = $pdo->prepare("INSERT INTO users (id, email, password_hash, display_name, role, status) VALUES (?, ?, ?, ?, ?, 'active')");
            $insert->execute([$newId, $email, $passHash, $displayName, $role]);

            echo json_encode([
                'status' => 'success',
                'action' => 'signup',
                'message' => 'Account successfully registered and saved in MySQL database!',
                'db_connected' => true,
                'user' => [
                    'id' => $newId,
                    'name' => $displayName,
                    'email' => $email,
                    'role' => $role
                ]
            ]);
            exit;
        } elseif ($action === 'reset_password' || $action === 'forgetpassword') {
            // Check if user exists in MySQL
            $stmt = $pdo->prepare("SELECT id, display_name, role FROM users WHERE LOWER(email) = ?");
            $stmt->execute([$email]);
            $found = $stmt->fetch();

            if (!$found) {
                http_response_code(404);
                echo json_encode([
                    'status' => 'error',
                    'action' => 'user_not_found',
                    'message' => 'User not found. No account is registered with ' . $email . '.',
                    'db_connected' => true
                ]);
                exit;
            }

            if (empty($newPassword)) {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'New password is required.',
                    'db_connected' => true
                ]);
                exit;
            }

            // Update password hash in MySQL database
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $update = $pdo->prepare("UPDATE users SET password_hash = ? WHERE LOWER(email) = ?");
            $update->execute([$newHash, $email]);

            echo json_encode([
                'status' => 'success',
                'action' => 'reset_password',
                'message' => 'Password updated successfully. Please sign in with your new password.',
                'db_connected' => true,
                'user' => [
                    'id' => $found['id'],
                    'name' => $found['display_name'],
                    'email' => $email,
                    'role' => $found['role']
                ]
            ]);
            exit;
        } else {
            // LOGIN: Strict credential validation
            $stmt = $pdo->prepare("SELECT id, display_name, role, password_hash FROM users WHERE LOWER(email) = ?");
            $stmt->execute([$email]);
            $found = $stmt->fetch();

            if ($found) {
                // Verify password hash
                $isValidPassword = false;
                if (!empty($found['password_hash'])) {
                    if (password_verify($password, $found['password_hash']) || $found['password_hash'] === $password) {
                        $isValidPassword = true;
                    }
                }

                if ($isValidPassword) {
                    echo json_encode([
                        'status' => 'success',
                        'action' => 'login',
                        'message' => 'User verified and logged in successfully from MySQL database!',
                        'db_connected' => true,
                        'user' => [
                            'id' => $found['id'],
                            'name' => $found['display_name'],
                            'email' => $email,
                            'role' => $found['role']
                        ]
                    ]);
                    exit;
                } else {
                    http_response_code(401);
                    echo json_encode([
                        'status' => 'error',
                        'action' => 'invalid_password',
                        'message' => 'Incorrect password. Please check your credentials and try again.',
                        'db_connected' => true
                    ]);
                    exit;
                }
            } else {
                http_response_code(404);
                echo json_encode([
                    'status' => 'error',
                    'action' => 'user_not_found',
                    'message' => 'User not found. Please create an account first.',
                    'db_connected' => true
                ]);
                exit;
            }
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'MySQL Database Error: ' . $e->getMessage(),
            'db_connected' => true
        ]);
        exit;
    }
} else {
    // Database offline mode handled in frontend
    echo json_encode([
        'status' => 'fallback',
        'db_connected' => false,
        'db_error' => $dbError,
        'message' => 'MySQL Server Offline. Handled via browser credential store.',
        'user' => [
            'id' => 'u_' . rand(100, 999),
            'name' => $displayName,
            'email' => $email,
            'role' => $role
        ]
    ]);
    exit;
}
?>
