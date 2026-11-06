<?php
/**
 * Dashboard Metrics API
 * Task Assignment and Monitoring System (TAMS)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');
requireAuth(true);

$pdo = getDbConnection();
$currentUser = currentUser();
$role = $currentUser['role'];
$userId = (int)$currentUser['id'];

// Base query for tasks depending on user role
// Admin: all tasks
// Team Leader: all tasks in team
// Team Member: only assigned tasks
$roleFilter = "";
$roleParams = [];

if ($role === 'team_member') {
    $roleFilter = " WHERE assigned_to = :uid";
    $roleParams[':uid'] = $userId;
}

// Fetch all relevant tasks for this user's scope
$stmt = $pdo->prepare("SELECT * FROM tasks" . $roleFilter);
$stmt->execute($roleParams);
$tasks = $stmt->fetchAll();

$totalTasks = count($tasks);
$todoCount = 0;
$inProgressCount = 0;
$completedCount = 0;
$overdueCount = 0;
$approachingCount = 0;
$urgentCount = 0;
$highCount = 0;

$now = new DateTime('now', new DateTimeZone('UTC'));

foreach ($tasks as $task) {
    if ($task['status'] === 'To Do') {
        $todoCount++;
    } elseif ($task['status'] === 'In Progress') {
        $inProgressCount++;
    } elseif ($task['status'] === 'Completed') {
        $completedCount++;
    }

    if (isTaskOverdue($task['deadline'], $task['status'])) {
        $overdueCount++;
    } elseif (isDeadlineApproaching($task['deadline'], $task['status'])) {
        $approachingCount++;
    }

    if ($task['priority'] === 'Urgent') {
        $urgentCount++;
    } elseif ($task['priority'] === 'High') {
        $highCount++;
    }
}

$pendingCount = $todoCount + $inProgressCount;
$completionRate = $totalTasks > 0 ? round(($completedCount / $totalTasks) * 100) : 0;

// Recent Activities (from task_history)
$actSql = "
    SELECT th.*, u.name as actor_name, u.role as actor_role, u.avatar_color as actor_color, t.title as task_title
    FROM task_history th
    JOIN users u ON th.user_id = u.id
    JOIN tasks t ON th.task_id = t.id
";

if ($role === 'team_member') {
    $actSql .= " WHERE t.assigned_to = :uid ";
}
$actSql .= " ORDER BY th.created_at DESC, th.id DESC LIMIT 8";

$actStmt = $pdo->prepare($actSql);
$actStmt->execute($roleParams);
$recentActivities = $actStmt->fetchAll();

foreach ($recentActivities as &$act) {
    $actTime = new DateTime($act['created_at'], new DateTimeZone('UTC'));
    $act['time_ago'] = $actTime->format('M d, H:i');
}

// User count & active members for Admin
$totalUsers = 0;
if ($role === 'admin') {
    $totalUsers = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
}

jsonResponse([
    'success' => true,
    'user' => [
        'id' => $currentUser['id'],
        'name' => $currentUser['name'],
        'username' => $currentUser['username'] ?? '',
        'role' => $currentUser['role'],
        'avatar_color' => $currentUser['avatar_color'] ?? '#4f46e5'
    ],
    'metrics' => [
        'total' => $totalTasks,
        'pending' => $pendingCount,
        'todo' => $todoCount,
        'in_progress' => $inProgressCount,
        'completed' => $completedCount,
        'overdue' => $overdueCount,
        'approaching' => $approachingCount,
        'urgent' => $urgentCount,
        'high' => $highCount,
        'completion_rate' => $completionRate,
        'total_users' => $totalUsers
    ],
    'recent_activities' => $recentActivities
]);
