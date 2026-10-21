<?php
/**
 * Task History / Audit Trail API
 * Task Assignment and Monitoring System (TAMS)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');
requireAuth(true);

$pdo = getDbConnection();
$currentUser = currentUser();

$taskId = (int)($_GET['task_id'] ?? 0);
if ($taskId <= 0) {
    jsonResponse(['success' => false, 'error' => 'Valid task_id is required'], 400);
}

// Check task access
$stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = :id");
$stmt->execute([':id' => $taskId]);
$task = $stmt->fetch();

if (!$task) {
    jsonResponse(['success' => false, 'error' => 'Task not found'], 404);
}

// Access Rules:
// Admin & Team Leader: view for managed/all tasks
// Team Member: view for assigned tasks
if ($currentUser['role'] === 'team_member' && $task['assigned_to'] != $currentUser['id']) {
    jsonResponse(['success' => false, 'error' => 'Access denied: you can only view history for tasks assigned to you.'], 403);
}

// Fetch history entries
$histStmt = $pdo->prepare("
    SELECT 
        th.*,
        u.name AS actor_name,
        u.role AS actor_role,
        u.avatar_color AS actor_avatar_color
    FROM task_history th
    JOIN users u ON th.user_id = u.id
    WHERE th.task_id = :task_id
    ORDER BY th.created_at DESC, th.id DESC
");
$histStmt->execute([':task_id' => $taskId]);
$history = $histStmt->fetchAll();

foreach ($history as &$item) {
    $item['created_at_formatted'] = (new DateTime($item['created_at'], new DateTimeZone('UTC')))->format('M d, Y - h:i A');
}

jsonResponse([
    'success' => true,
    'task' => [
        'id' => $task['id'],
        'title' => $task['title'],
        'status' => $task['status'],
        'priority' => $task['priority'],
        'deadline' => $task['deadline']
    ],
    'history' => $history
]);
