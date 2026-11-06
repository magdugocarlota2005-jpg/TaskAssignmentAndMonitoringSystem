<?php
/**
 * Users API
 * Task Assignment and Monitoring System (TAMS)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');
requireAuth(true);

$pdo = getDbConnection();
$currentUser = currentUser();
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

// Handle JSON input
$rawInput = file_get_contents('php://input');
$jsonBody = json_decode($rawInput, true);
if (is_array($jsonBody)) {
    $_POST = array_merge($_POST, $jsonBody);
}

switch ($action) {
    case 'list':
        // If Admin: full list with details and counts
        if ($currentUser['role'] === 'admin') {
            $sql = "
                SELECT 
                    u.id, u.name, u.username, u.email, u.role, u.status, u.avatar_color, u.created_at,
                    COUNT(DISTINCT t.id) AS assigned_tasks_count,
                    SUM(CASE WHEN t.status = 'Completed' THEN 1 ELSE 0 END) AS completed_tasks_count
                FROM users u
                LEFT JOIN tasks t ON u.id = t.assigned_to
                GROUP BY u.id
                ORDER BY u.id ASC
            ";
            $stmt = $pdo->query($sql);
            $users = $stmt->fetchAll();
            jsonResponse(['success' => true, 'users' => $users]);
        } else {
            // Team Leader & Members only get active users for assignment dropdowns
            $stmt = $pdo->query("SELECT id, name, username, role, avatar_color FROM users WHERE status = 'active' ORDER BY name ASC");
            $users = $stmt->fetchAll();
            jsonResponse(['success' => true, 'users' => $users]);
        }
        break;

    case 'assignees':
        // Dropdown options for Team Members to be assigned
        $stmt = $pdo->query("SELECT id, name, username, role, avatar_color FROM users WHERE status = 'active' ORDER BY role ASC, name ASC");
        $users = $stmt->fetchAll();
        jsonResponse(['success' => true, 'users' => $users]);
        break;

    case 'create':
        // Admin only
        if ($currentUser['role'] !== 'admin') {
            jsonResponse(['success' => false, 'error' => 'Permission denied: Administrator access required.'], 403);
        }

        $name = trim($_POST['name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $role = trim($_POST['role'] ?? 'team_member');
        $status = trim($_POST['status'] ?? 'active');

        if (empty($name) || empty($username) || empty($email) || empty($password)) {
            jsonResponse(['success' => false, 'error' => 'Name, username, email, and password are all required.'], 400);
        }

        if (!in_array($role, ['admin', 'team_leader', 'team_member'])) {
            jsonResponse(['success' => false, 'error' => 'Invalid role specified.'], 400);
        }

        // Check unique username and email
        $check = $pdo->prepare("SELECT id FROM users WHERE username = :u OR email = :e");
        $check->execute([':u' => $username, ':e' => $email]);
        if ($check->fetch()) {
            jsonResponse(['success' => false, 'error' => 'A user with that username or email already exists.'], 400);
        }

        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $colors = ['#4f46e5', '#8b5cf6', '#0ea5e9', '#10b981', '#f59e0b', '#ec4899', '#06b6d4'];
        $randomColor = $colors[array_rand($colors)];

        $insert = $pdo->prepare("
            INSERT INTO users (name, username, email, password, role, status, avatar_color, created_at)
            VALUES (:name, :u, :e, :p, :role, :status, :col, CURRENT_TIMESTAMP)
        ");
        $insert->execute([
            ':name' => $name,
            ':u' => $username,
            ':e' => $email,
            ':p' => $hashed,
            ':role' => $role,
            ':status' => $status,
            ':col' => $randomColor
        ]);

        jsonResponse(['success' => true, 'message' => 'User created successfully', 'user_id' => $pdo->lastInsertId()]);
        break;

    case 'update':
        // Admin only
        if ($currentUser['role'] !== 'admin') {
            jsonResponse(['success' => false, 'error' => 'Permission denied: Administrator access required.'], 403);
        }

        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = trim($_POST['role'] ?? '');
        $status = trim($_POST['status'] ?? '');
        $newPassword = trim($_POST['password'] ?? '');

        if ($id <= 0 || empty($name) || empty($email) || empty($role)) {
            jsonResponse(['success' => false, 'error' => 'Missing required fields.'], 400);
        }

        if (!in_array($role, ['admin', 'team_leader', 'team_member'])) {
            jsonResponse(['success' => false, 'error' => 'Invalid role.'], 400);
        }

        // Check email uniqueness excluding self
        $check = $pdo->prepare("SELECT id FROM users WHERE email = :e AND id != :id");
        $check->execute([':e' => $email, ':id' => $id]);
        if ($check->fetch()) {
            jsonResponse(['success' => false, 'error' => 'Another user with that email already exists.'], 400);
        }

        if (!empty($newPassword)) {
            $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                UPDATE users 
                SET name = :name, email = :email, role = :role, status = :status, password = :p
                WHERE id = :id
            ");
            $stmt->execute([
                ':name' => $name,
                ':email' => $email,
                ':role' => $role,
                ':status' => $status,
                ':p' => $hashed,
                ':id' => $id
            ]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET name = :name, email = :email, role = :role, status = :status
                WHERE id = :id
            ");
            $stmt->execute([
                ':name' => $name,
                ':email' => $email,
                ':role' => $role,
                ':status' => $status,
                ':id' => $id
            ]);
        }

        jsonResponse(['success' => true, 'message' => 'User updated successfully']);
        break;

    case 'toggle_status':
        // Admin only
        if ($currentUser['role'] !== 'admin') {
            jsonResponse(['success' => false, 'error' => 'Permission denied: Administrator access required.'], 403);
        }

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            jsonResponse(['success' => false, 'error' => 'Invalid user ID'], 400);
        }
        if ($id === (int)$currentUser['id']) {
            jsonResponse(['success' => false, 'error' => 'You cannot deactivate your own account.'], 400);
        }

        $stmt = $pdo->prepare("SELECT status FROM users WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch();
        if (!$user) {
            jsonResponse(['success' => false, 'error' => 'User not found'], 404);
        }

        $newStatus = ($user['status'] === 'active') ? 'inactive' : 'active';
        $update = $pdo->prepare("UPDATE users SET status = :status WHERE id = :id");
        $update->execute([':status' => $newStatus, ':id' => $id]);

        jsonResponse(['success' => true, 'message' => "User status set to {$newStatus}", 'status' => $newStatus]);
        break;

    default:
        jsonResponse(['success' => false, 'error' => 'Unknown user action'], 400);
}
