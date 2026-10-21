<?php
/**
 * Authentication API
 * Task Assignment and Monitoring System (TAMS)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$pdo = getDbConnection();

// Check if request is JSON body
$rawInput = file_get_contents('php://input');
$jsonBody = json_decode($rawInput, true);
if (is_array($jsonBody)) {
    $_POST = array_merge($_POST, $jsonBody);
}

switch ($action) {
    case 'login':
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (empty($username) || empty($password)) {
            jsonResponse(['success' => false, 'error' => 'Please provide both username and password.'], 400);
        }

        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :u OR email = :u LIMIT 1");
        $stmt->execute([':u' => $username]);
        $user = $stmt->fetch();

        if (!$user) {
            jsonResponse(['success' => false, 'error' => 'Invalid username or password.'], 401);
        }

        if ($user['status'] !== 'active') {
            jsonResponse(['success' => false, 'error' => 'Your account has been deactivated. Please contact an Administrator.'], 403);
        }

        if (!password_verify($password, $user['password'])) {
            jsonResponse(['success' => false, 'error' => 'Invalid username or password.'], 401);
        }

        // Set session
        unset($user['password']);
        $_SESSION['user'] = $user;

        jsonResponse([
            'success' => true,
            'message' => 'Login successful',
            'user' => $user
        ]);
        break;

    case 'switch_demo':
        // Quick switch for demo and evaluation
        $role = trim($_POST['role'] ?? 'team_leader');
        $validRoles = ['admin', 'team_leader', 'team_member'];
        if (!in_array($role, $validRoles)) {
            $role = 'team_leader';
        }

        $stmt = $pdo->prepare("SELECT * FROM users WHERE role = :role AND status = 'active' ORDER BY id ASC LIMIT 1");
        $stmt->execute([':role' => $role]);
        $user = $stmt->fetch();

        if (!$user) {
            jsonResponse(['success' => false, 'error' => 'No active user found for demo role: ' . $role], 404);
        }

        unset($user['password']);
        $_SESSION['user'] = $user;

        jsonResponse([
            'success' => true,
            'message' => 'Switched to ' . $user['name'] . ' (' . ucfirst(str_replace('_', ' ', $user['role'])) . ')',
            'user' => $user
        ]);
        break;

    case 'logout':
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
        jsonResponse(['success' => true, 'message' => 'Logged out successfully']);
        break;

    case 'me':
        if (isLoggedIn()) {
            jsonResponse(['success' => true, 'user' => currentUser()]);
        } else {
            jsonResponse(['success' => false, 'error' => 'Not authenticated'], 401);
        }
        break;

    default:
        jsonResponse(['success' => false, 'error' => 'Unknown auth action: ' . $action], 400);
}
