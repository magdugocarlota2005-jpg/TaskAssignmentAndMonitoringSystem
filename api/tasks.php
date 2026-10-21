<?php
/**
 * Tasks API
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
        $statusFilter = $_GET['status'] ?? '';
        $priorityFilter = $_GET['priority'] ?? '';
        $assignedFilter = $_GET['assigned_to'] ?? '';
        $conditionFilter = $_GET['condition'] ?? ''; // 'overdue', 'approaching', 'pending', 'completed'
        $search = trim($_GET['search'] ?? '');

        $sql = "
            SELECT 
                t.*,
                u_creator.name AS creator_name,
                u_creator.role AS creator_role,
                u_assignee.name AS assignee_name,
                u_assignee.avatar_color AS assignee_avatar_color,
                u_assignee.role AS assignee_role,
                u_completed.name AS completed_by_name
            FROM tasks t
            LEFT JOIN users u_creator ON t.created_by = u_creator.id
            LEFT JOIN users u_assignee ON t.assigned_to = u_assignee.id
            LEFT JOIN users u_completed ON t.completed_by = u_completed.id
            WHERE 1=1
        ";
        $params = [];

        // Role-based visibility
        // Team Member can only see assigned tasks
        if ($currentUser['role'] === 'team_member') {
            $sql .= " AND t.assigned_to = :current_user_id";
            $params[':current_user_id'] = $currentUser['id'];
        }

        // Status filter
        if (!empty($statusFilter)) {
            $sql .= " AND t.status = :status_filter";
            $params[':status_filter'] = $statusFilter;
        }

        // Priority filter
        if (!empty($priorityFilter)) {
            $sql .= " AND t.priority = :priority_filter";
            $params[':priority_filter'] = $priorityFilter;
        }

        // Assignee filter (Admin and Team Leader only)
        if (!empty($assignedFilter) && $currentUser['role'] !== 'team_member') {
            $sql .= " AND t.assigned_to = :assigned_filter";
            $params[':assigned_filter'] = $assignedFilter;
        }

        // Search query
        if (!empty($search)) {
            $sql .= " AND (t.title LIKE :search OR t.description LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }

        $sql .= " ORDER BY 
            CASE 
                WHEN t.status != 'Completed' AND t.deadline < datetime('now') THEN 1
                WHEN t.priority = 'Urgent' THEN 2
                WHEN t.priority = 'High' THEN 3
                WHEN t.priority = 'Medium' THEN 4
                ELSE 5
            END,
            t.deadline ASC,
            t.created_at DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $tasks = $stmt->fetchAll();

        // Enrich tasks with computed flags (Overdue, Approaching Deadline)
        $enriched = [];
        $now = new DateTime('now', new DateTimeZone('UTC'));

        foreach ($tasks as $task) {
            $isOverdue = isTaskOverdue($task['deadline'], $task['status']);
            $isApproaching = isDeadlineApproaching($task['deadline'], $task['status']);

            // Filter condition post-fetch
            if ($conditionFilter === 'overdue' && !$isOverdue) {
                continue;
            }
            if ($conditionFilter === 'approaching' && !$isApproaching) {
                continue;
            }
            if ($conditionFilter === 'pending' && $task['status'] === 'Completed') {
                continue;
            }
            if ($conditionFilter === 'completed' && $task['status'] !== 'Completed') {
                continue;
            }

            $task['is_overdue'] = $isOverdue;
            $task['is_approaching'] = $isApproaching;

            // Formatted date and deadline diff
            $dueDt = new DateTime($task['deadline'], new DateTimeZone('UTC'));
            $task['deadline_formatted'] = $dueDt->format('M d, Y H:i');
            $task['created_at_formatted'] = (new DateTime($task['created_at'], new DateTimeZone('UTC')))->format('M d, Y H:i');

            if ($task['status'] === 'Completed') {
                $task['deadline_badge'] = 'Completed';
                $task['deadline_badge_type'] = 'success';
            } elseif ($isOverdue) {
                $diff = $dueDt->diff($now);
                $days = $diff->days;
                $hours = $diff->h;
                $overdueText = $days > 0 ? "Overdue by {$days}d {$hours}h" : "Overdue by {$hours}h";
                $task['deadline_badge'] = $overdueText;
                $task['deadline_badge_type'] = 'danger';
            } elseif ($isApproaching) {
                $diff = $now->diff($dueDt);
                $hours = ($diff->days * 24) + $diff->h;
                $task['deadline_badge'] = "Due in {$hours}h";
                $task['deadline_badge_type'] = 'warning';
            } else {
                $diff = $now->diff($dueDt);
                $task['deadline_badge'] = "Due in {$diff->days} days";
                $task['deadline_badge_type'] = 'info';
            }

            $enriched[] = $task;
        }

        jsonResponse([
            'success' => true,
            'tasks' => $enriched,
            'count' => count($enriched)
        ]);
        break;

    case 'get':
        $taskId = (int)($_GET['id'] ?? 0);
        if ($taskId <= 0) {
            jsonResponse(['success' => false, 'error' => 'Invalid task ID'], 400);
        }

        $stmt = $pdo->prepare("
            SELECT t.*, 
                   u_creator.name AS creator_name,
                   u_assignee.name AS assignee_name,
                   u_assignee.avatar_color AS assignee_avatar_color,
                   u_completed.name AS completed_by_name
            FROM tasks t
            LEFT JOIN users u_creator ON t.created_by = u_creator.id
            LEFT JOIN users u_assignee ON t.assigned_to = u_assignee.id
            LEFT JOIN users u_completed ON t.completed_by = u_completed.id
            WHERE t.id = :id
        ");
        $stmt->execute([':id' => $taskId]);
        $task = $stmt->fetch();

        if (!$task) {
            jsonResponse(['success' => false, 'error' => 'Task not found'], 404);
        }

        // Access check for Team Member
        if ($currentUser['role'] === 'team_member' && $task['assigned_to'] != $currentUser['id']) {
            jsonResponse(['success' => false, 'error' => 'Access denied: not your assigned task'], 403);
        }

        $task['is_overdue'] = isTaskOverdue($task['deadline'], $task['status']);
        $task['is_approaching'] = isDeadlineApproaching($task['deadline'], $task['status']);

        jsonResponse(['success' => true, 'task' => $task]);
        break;

    case 'create':
        // Only Admin and Team Leader can create tasks
        if (!in_array($currentUser['role'], ['admin', 'team_leader'])) {
            jsonResponse(['success' => false, 'error' => 'Only Administrators and Team Leaders can create tasks.'], 403);
        }

        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $priority = trim($_POST['priority'] ?? 'Medium');
        $deadline = trim($_POST['deadline'] ?? '');
        $assignedTo = !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null;

        if (empty($title)) {
            jsonResponse(['success' => false, 'error' => 'Task title is required.'], 400);
        }
        if (empty($deadline)) {
            jsonResponse(['success' => false, 'error' => 'Deadline date and time are required.'], 400);
        }

        $validPriorities = ['Low', 'Medium', 'High', 'Urgent'];
        if (!in_array($priority, $validPriorities)) {
            $priority = 'Medium';
        }

        // Validate assigned user exists if provided
        $assigneeName = null;
        if ($assignedTo) {
            $userCheck = $pdo->prepare("SELECT name FROM users WHERE id = :id AND status = 'active'");
            $userCheck->execute([':id' => $assignedTo]);
            $assignee = $userCheck->fetch();
            if (!$assignee) {
                jsonResponse(['success' => false, 'error' => 'Selected assignee does not exist or is inactive.'], 400);
            }
            $assigneeName = $assignee['name'];
        }

        // Normalize deadline format
        $deadlineDt = new DateTime($deadline);
        $deadlineFormatted = $deadlineDt->format('Y-m-d H:i:s');

        // Insert task
        $stmt = $pdo->prepare("
            INSERT INTO tasks (title, description, priority, status, created_by, assigned_to, deadline, created_at, updated_at)
            VALUES (:title, :desc, :prio, 'To Do', :created_by, :assigned_to, :deadline, datetime('now'), datetime('now'))
        ");
        $stmt->execute([
            ':title' => $title,
            ':desc' => $description,
            ':prio' => $priority,
            ':created_by' => $currentUser['id'],
            ':assigned_to' => $assignedTo,
            ':deadline' => $deadlineFormatted
        ]);
        $taskId = (int)$pdo->lastInsertId();

        // Record history: creation
        $histStmt = $pdo->prepare("
            INSERT INTO task_history (task_id, user_id, action_type, description, created_at)
            VALUES (:task_id, :user_id, 'created', :desc, datetime('now'))
        ");
        $histStmt->execute([
            ':task_id' => $taskId,
            ':user_id' => $currentUser['id'],
            ':desc' => "Task created by {$currentUser['name']} ({$priority} priority, due {$deadlineFormatted})"
        ]);

        // Record history: assignment
        if ($assignedTo && $assigneeName) {
            $histStmt->execute([
                ':task_id' => $taskId,
                ':user_id' => $currentUser['id'],
                ':desc' => "Assigned task to {$assigneeName}"
            ]);

            // Notify assignee
            $notifStmt = $pdo->prepare("
                INSERT INTO notifications (user_id, task_id, title, message, created_at)
                VALUES (:uid, :tid, :title, :msg, datetime('now'))
            ");
            $notifStmt->execute([
                ':uid' => $assignedTo,
                ':tid' => $taskId,
                ':title' => 'New Task Assigned: ' . $title,
                ':msg' => "You have been assigned to '{$title}' by {$currentUser['name']}. Due: {$deadlineFormatted}"
            ]);
        }

        jsonResponse([
            'success' => true,
            'message' => 'Task created successfully',
            'task_id' => $taskId
        ]);
        break;

    case 'update_details':
        // Only Admin and Team Leader can edit task details
        if (!in_array($currentUser['role'], ['admin', 'team_leader'])) {
            jsonResponse(['success' => false, 'error' => 'Permission denied: only Admin and Team Leader can edit task details.'], 403);
        }

        $taskId = (int)($_POST['id'] ?? 0);
        if ($taskId <= 0) {
            jsonResponse(['success' => false, 'error' => 'Invalid task ID'], 400);
        }

        $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = :id");
        $stmt->execute([':id' => $taskId]);
        $existing = $stmt->fetch();
        if (!$existing) {
            jsonResponse(['success' => false, 'error' => 'Task not found'], 404);
        }

        $title = trim($_POST['title'] ?? $existing['title']);
        $description = trim($_POST['description'] ?? $existing['description']);
        $priority = trim($_POST['priority'] ?? $existing['priority']);
        $deadline = trim($_POST['deadline'] ?? $existing['deadline']);
        $assignedTo = isset($_POST['assigned_to']) && $_POST['assigned_to'] !== '' ? (int)$_POST['assigned_to'] : null;

        if (empty($title)) {
            jsonResponse(['success' => false, 'error' => 'Task title cannot be empty'], 400);
        }

        $deadlineDt = new DateTime($deadline);
        $deadlineFormatted = $deadlineDt->format('Y-m-d H:i:s');

        // Track changes for Audit History Log
        $changes = [];

        if ($title !== $existing['title']) {
            $changes[] = [
                'type' => 'details_updated',
                'desc' => "Updated title to \"{$title}\"",
                'old' => $existing['title'],
                'new' => $title
            ];
        }

        if ($description !== $existing['description']) {
            $changes[] = [
                'type' => 'details_updated',
                'desc' => "Updated task description",
                'old' => null,
                'new' => null
            ];
        }

        if ($priority !== $existing['priority']) {
            $changes[] = [
                'type' => 'details_updated',
                'desc' => "Changed priority from '{$existing['priority']}' to '{$priority}'",
                'old' => $existing['priority'],
                'new' => $priority
            ];
        }

        if ($deadlineFormatted !== $existing['deadline']) {
            $changes[] = [
                'type' => 'details_updated',
                'desc' => "Changed deadline from '{$existing['deadline']}' to '{$deadlineFormatted}'",
                'old' => $existing['deadline'],
                'new' => $deadlineFormatted
            ];
        }

        if ($assignedTo != $existing['assigned_to']) {
            $oldAssigneeName = 'Unassigned';
            if ($existing['assigned_to']) {
                $uStmt = $pdo->prepare("SELECT name FROM users WHERE id = :id");
                $uStmt->execute([':id' => $existing['assigned_to']]);
                $oldAssigneeName = $uStmt->fetchColumn() ?: 'Unknown';
            }

            $newAssigneeName = 'Unassigned';
            if ($assignedTo) {
                $uStmt = $pdo->prepare("SELECT name FROM users WHERE id = :id");
                $uStmt->execute([':id' => $assignedTo]);
                $newAssigneeName = $uStmt->fetchColumn() ?: 'Unknown';
            }

            $changes[] = [
                'type' => 'reassigned',
                'desc' => "Reassigned task from '{$oldAssigneeName}' to '{$newAssigneeName}'",
                'old' => $oldAssigneeName,
                'new' => $newAssigneeName
            ];

            // Notify new assignee if set
            if ($assignedTo) {
                $notifStmt = $pdo->prepare("
                    INSERT INTO notifications (user_id, task_id, title, message, created_at)
                    VALUES (:uid, :tid, :title, :msg, datetime('now'))
                ");
                $notifStmt->execute([
                    ':uid' => $assignedTo,
                    ':tid' => $taskId,
                    ':title' => 'Task Reassigned to You: ' . $title,
                    ':msg' => "{$currentUser['name']} reassigned the task '{$title}' to you."
                ]);
            }
        }

        // Update task table
        $updateStmt = $pdo->prepare("
            UPDATE tasks 
            SET title = :title, description = :desc, priority = :prio, deadline = :deadline, assigned_to = :assigned_to, updated_at = datetime('now')
            WHERE id = :id
        ");
        $updateStmt->execute([
            ':title' => $title,
            ':desc' => $description,
            ':prio' => $priority,
            ':deadline' => $deadlineFormatted,
            ':assigned_to' => $assignedTo,
            ':id' => $taskId
        ]);

        // Insert history entries
        $histStmt = $pdo->prepare("
            INSERT INTO task_history (task_id, user_id, action_type, description, old_value, new_value, created_at)
            VALUES (:task_id, :user_id, :action_type, :description, :old_val, :new_val, datetime('now'))
        ");
        foreach ($changes as $c) {
            $histStmt->execute([
                ':task_id' => $taskId,
                ':user_id' => $currentUser['id'],
                ':action_type' => $c['type'],
                ':description' => $c['desc'],
                ':old_val' => $c['old'],
                ':new_val' => $c['new']
            ]);
        }

        jsonResponse(['success' => true, 'message' => 'Task details updated successfully']);
        break;

    case 'update_status':
        $taskId = (int)($_POST['id'] ?? 0);
        $newStatus = trim($_POST['status'] ?? '');

        $validStatuses = ['To Do', 'In Progress', 'Completed'];
        if (!in_array($newStatus, $validStatuses)) {
            jsonResponse(['success' => false, 'error' => 'Invalid status. Must be To Do, In Progress, or Completed.'], 400);
        }

        $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = :id");
        $stmt->execute([':id' => $taskId]);
        $task = $stmt->fetch();
        if (!$task) {
            jsonResponse(['success' => false, 'error' => 'Task not found'], 404);
        }

        // Permission check:
        // Admin & Team Leader can update any task;
        // Team Member can ONLY update if assigned to this task!
        if ($currentUser['role'] === 'team_member' && $task['assigned_to'] != $currentUser['id']) {
            jsonResponse(['success' => false, 'error' => 'Permission denied: You can only update tasks assigned to you.'], 403);
        }

        $oldStatus = $task['status'];
        if ($oldStatus === $newStatus) {
            jsonResponse(['success' => true, 'message' => 'Status unchanged']);
        }

        $isCompletedNow = ($newStatus === 'Completed');
        $completedAt = $isCompletedNow ? date('Y-m-d H:i:s') : null;
        $completedBy = $isCompletedNow ? $currentUser['id'] : null;

        $updateStmt = $pdo->prepare("
            UPDATE tasks 
            SET status = :status, completed_at = :completed_at, completed_by = :completed_by, updated_at = datetime('now')
            WHERE id = :id
        ");
        $updateStmt->execute([
            ':status' => $newStatus,
            ':completed_at' => $completedAt,
            ':completed_by' => $completedBy,
            ':id' => $taskId
        ]);

        // Insert audit log in task_history
        $actionType = $isCompletedNow ? 'completed' : 'status_change';
        $desc = $isCompletedNow 
            ? "Task marked as Completed by {$currentUser['name']}"
            : "Changed status from '{$oldStatus}' to '{$newStatus}' by {$currentUser['name']}";

        $histStmt = $pdo->prepare("
            INSERT INTO task_history (task_id, user_id, action_type, description, old_value, new_value, created_at)
            VALUES (:task_id, :user_id, :action_type, :description, :old_val, :new_val, datetime('now'))
        ");
        $histStmt->execute([
            ':task_id' => $taskId,
            ':user_id' => $currentUser['id'],
            ':action_type' => $actionType,
            ':description' => $desc,
            ':old_val' => $oldStatus,
            ':new_val' => $newStatus
        ]);

        // Specific Flow Requirement (Step 51):
        // "AA: Task completed? Yes -> Show task as completed -> Notify or inform Team Leader"
        if ($isCompletedNow) {
            // Find all Team Leaders and the task creator to notify
            $leaderStmt = $pdo->query("SELECT id FROM users WHERE role IN ('team_leader', 'admin') AND status = 'active'");
            $leaders = $leaderStmt->fetchAll();

            $notifStmt = $pdo->prepare("
                INSERT INTO notifications (user_id, task_id, title, message, created_at)
                VALUES (:uid, :tid, :title, :msg, datetime('now'))
            ");

            $notified = [];
            foreach ($leaders as $ldr) {
                if ($ldr['id'] != $currentUser['id'] && !in_array($ldr['id'], $notified)) {
                    $notifStmt->execute([
                        ':uid' => $ldr['id'],
                        ':tid' => $taskId,
                        ':title' => "Task Completed: {$task['title']}",
                        ':msg' => "{$currentUser['name']} marked '{$task['title']}' as Completed."
                    ]);
                    $notified[] = $ldr['id'];
                }
            }

            // Also notify creator if not already notified
            if ($task['created_by'] != $currentUser['id'] && !in_array($task['created_by'], $notified)) {
                $notifStmt->execute([
                    ':uid' => $task['created_by'],
                    ':tid' => $taskId,
                    ':title' => "Task Completed: {$task['title']}",
                    ':msg' => "{$currentUser['name']} marked '{$task['title']}' as Completed."
                ]);
            }
        }

        jsonResponse([
            'success' => true,
            'message' => "Task status updated to {$newStatus}",
            'new_status' => $newStatus
        ]);
        break;

    case 'delete':
        // Only Administrator can delete tasks
        if ($currentUser['role'] !== 'admin') {
            jsonResponse(['success' => false, 'error' => 'Permission denied: Only Administrators can delete tasks.'], 403);
        }

        $taskId = (int)($_POST['id'] ?? 0);
        if ($taskId <= 0) {
            jsonResponse(['success' => false, 'error' => 'Invalid task ID'], 400);
        }

        $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = :id");
        $stmt->execute([':id' => $taskId]);

        jsonResponse(['success' => true, 'message' => 'Task deleted successfully']);
        break;

    default:
        jsonResponse(['success' => false, 'error' => 'Unknown task action'], 400);
}
