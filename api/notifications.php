<?php
/**
 * Notifications API & In-App Messaging
 * Task Assignment and Monitoring System (TAMS)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');
requireAuth(true);

$pdo = getDbConnection();
$currentUser = currentUser();
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

$rawInput = file_get_contents('php://input');
$jsonBody = json_decode($rawInput, true);
if (is_array($jsonBody)) {
    $_POST = array_merge($_POST, $jsonBody);
}

switch ($action) {
    case 'list':
        $stmt = $pdo->prepare("
            SELECT 
                n.*, 
                t.title AS task_title,
                u.name AS sender_name,
                u.avatar_color AS sender_avatar_color,
                u.role AS sender_role
            FROM notifications n
            LEFT JOIN tasks t ON n.task_id = t.id
            LEFT JOIN users u ON n.sender_id = u.id
            WHERE n.user_id = :uid
            ORDER BY n.created_at DESC, n.id DESC
            LIMIT 30
        ");
        $stmt->execute([':uid' => $currentUser['id']]);
        $notifications = $stmt->fetchAll();

        $unreadCount = 0;
        foreach ($notifications as &$n) {
            if (!$n['is_read']) {
                $unreadCount++;
            }
            $dt = new DateTime($n['created_at'], new DateTimeZone('UTC'));
            $n['created_at_formatted'] = $dt->format('M d, H:i');
        }

        jsonResponse([
            'success' => true,
            'notifications' => $notifications,
            'unread_count' => $unreadCount
        ]);
        break;

    case 'check_new':
        $lastId = (int)($_GET['last_id'] ?? 0);

        // Check for new notifications since lastId
        $stmt = $pdo->prepare("
            SELECT 
                n.*, 
                t.title AS task_title,
                u.name AS sender_name,
                u.avatar_color AS sender_avatar_color,
                u.role AS sender_role
            FROM notifications n
            LEFT JOIN tasks t ON n.task_id = t.id
            LEFT JOIN users u ON n.sender_id = u.id
            WHERE n.user_id = :uid AND n.id > :last_id
            ORDER BY n.id ASC
        ");
        $stmt->execute([':uid' => $currentUser['id'], ':last_id' => $lastId]);
        $newNotifications = $stmt->fetchAll();

        foreach ($newNotifications as &$n) {
            $dt = new DateTime($n['created_at'], new DateTimeZone('UTC'));
            $n['created_at_formatted'] = $dt->format('M d, H:i');
        }

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND is_read = 0");
        $countStmt->execute([':uid' => $currentUser['id']]);
        $unreadCount = (int)$countStmt->fetchColumn();

        $maxStmt = $pdo->prepare("SELECT COALESCE(MAX(id), 0) FROM notifications WHERE user_id = :uid");
        $maxStmt->execute([':uid' => $currentUser['id']]);
        $latestId = (int)$maxStmt->fetchColumn();

        jsonResponse([
            'success' => true,
            'new_notifications' => $newNotifications,
            'unread_count' => $unreadCount,
            'latest_id' => $latestId
        ]);
        break;

    case 'mark_read':
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :uid");
            $stmt->execute([':id' => $id, ':uid' => $currentUser['id']]);
        } else {
            // Mark all as read
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :uid");
            $stmt->execute([':uid' => $currentUser['id']]);
        }

        jsonResponse(['success' => true, 'message' => 'Notification(s) marked as read']);
        break;

    case 'send_message':
        $receiverId = isset($_POST['receiver_id']) && $_POST['receiver_id'] !== '' && $_POST['receiver_id'] !== '0' ? (int)$_POST['receiver_id'] : null;
        $taskId = !empty($_POST['task_id']) ? (int)$_POST['task_id'] : null;
        $title = trim($_POST['title'] ?? '');
        $messageText = trim($_POST['message'] ?? '');

        if (empty($messageText)) {
            jsonResponse(['success' => false, 'error' => 'Message content cannot be empty.'], 400);
        }

        $taskTitle = '';
        $taskRow = null;
        if ($taskId) {
            $tStmt = $pdo->prepare("SELECT title, created_by, assigned_to FROM tasks WHERE id = :id");
            $tStmt->execute([':id' => $taskId]);
            $taskRow = $tStmt->fetch();
            if ($taskRow) {
                $taskTitle = $taskRow['title'];
            }
        }

        if (empty($title)) {
            $title = $taskId ? "Update on {$taskTitle}" : "Message from {$currentUser['name']}";
        }

        // Insert message
        $msgStmt = $pdo->prepare("
            INSERT INTO messages (sender_id, receiver_id, task_id, title, message, created_at)
            VALUES (:sender_id, :receiver_id, :task_id, :title, :message, CURRENT_TIMESTAMP)
        ");
        $msgStmt->execute([
            ':sender_id' => $currentUser['id'],
            ':receiver_id' => $receiverId,
            ':task_id' => $taskId,
            ':title' => $title,
            ':message' => $messageText
        ]);
        $messageId = (int)$pdo->lastInsertId();

        // Determine recipients
        $recipients = [];
        if ($receiverId) {
            $recipients[] = $receiverId;
        } elseif ($taskId && !empty($taskRow)) {
            if ($taskRow['assigned_to'] && $taskRow['assigned_to'] != $currentUser['id']) {
                $recipients[] = (int)$taskRow['assigned_to'];
            }
            if ($taskRow['created_by'] && $taskRow['created_by'] != $currentUser['id'] && !in_array((int)$taskRow['created_by'], $recipients)) {
                $recipients[] = (int)$taskRow['created_by'];
            }
        } else {
            // Broadcast to all other active users
            $allStmt = $pdo->prepare("SELECT id FROM users WHERE status = 'active' AND id != :uid");
            $allStmt->execute([':uid' => $currentUser['id']]);
            $recipients = $allStmt->fetchAll(PDO::FETCH_COLUMN);
        }

        // Insert into notifications table
        $notifStmt = $pdo->prepare("
            INSERT INTO notifications (user_id, task_id, sender_id, type, title, message, is_read, created_at)
            VALUES (:uid, :tid, :sender_id, 'message', :title, :message, 0, CURRENT_TIMESTAMP)
        ");

        $notifTitle = $taskId ? "💬 Message on task: {$taskTitle}" : "💬 " . $title;

        foreach ($recipients as $uid) {
            $notifStmt->execute([
                ':uid' => (int)$uid,
                ':tid' => $taskId,
                ':sender_id' => $currentUser['id'],
                ':title' => $notifTitle,
                ':message' => $messageText
            ]);
        }

        // Record in task_history if linked to task
        if ($taskId) {
            $snippet = mb_strlen($messageText) > 60 ? mb_substr($messageText, 0, 57) . '...' : $messageText;
            $histStmt = $pdo->prepare("
                INSERT INTO task_history (task_id, user_id, action_type, description, created_at)
                VALUES (:tid, :uid, 'message', :desc, CURRENT_TIMESTAMP)
            ");
            $histStmt->execute([
                ':tid' => $taskId,
                ':uid' => $currentUser['id'],
                ':desc' => "Message posted by {$currentUser['name']}: \"{$snippet}\""
            ]);
        }

        jsonResponse([
            'success' => true,
            'message' => 'Message notification sent successfully',
            'message_id' => $messageId,
            'recipient_count' => count($recipients)
        ]);
        break;

    case 'task_messages':
        $taskId = (int)($_GET['task_id'] ?? 0);
        if ($taskId <= 0) {
            jsonResponse(['success' => false, 'error' => 'Invalid task ID'], 400);
        }

        $stmt = $pdo->prepare("
            SELECT 
                m.*, 
                u.name AS sender_name, 
                u.avatar_color AS sender_avatar_color, 
                u.role AS sender_role
            FROM messages m
            JOIN users u ON m.sender_id = u.id
            WHERE m.task_id = :task_id
            ORDER BY m.created_at ASC, m.id ASC
        ");
        $stmt->execute([':task_id' => $taskId]);
        $messages = $stmt->fetchAll();

        foreach ($messages as &$m) {
            $dt = new DateTime($m['created_at'], new DateTimeZone('UTC'));
            $m['created_at_formatted'] = $dt->format('M d, H:i');
            $m['is_mine'] = ($m['sender_id'] == $currentUser['id']);
        }

        jsonResponse([
            'success' => true,
            'messages' => $messages
        ]);
        break;

    case 'get_message':
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            jsonResponse(['success' => false, 'error' => 'Invalid message ID'], 400);
        }

        $stmt = $pdo->prepare("
            SELECT 
                m.*,
                u.name AS sender_name,
                u.avatar_color AS sender_avatar_color,
                u.role AS sender_role,
                t.title AS task_title
            FROM messages m
            JOIN users u ON m.sender_id = u.id
            LEFT JOIN tasks t ON m.task_id = t.id
            WHERE m.id = :id
        ");
        $stmt->execute([':id' => $id]);
        $msg = $stmt->fetch();
        if (!$msg) {
            jsonResponse(['success' => false, 'error' => 'Message not found'], 404);
        }
        $dt = new DateTime($msg['created_at'], new DateTimeZone('UTC'));
        $msg['created_at_formatted'] = $dt->format('M d, Y H:i');
        jsonResponse(['success' => true, 'message' => $msg]);
        break;

    case 'delete':
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            jsonResponse(['success' => false, 'error' => 'Invalid notification ID'], 400);
        }
        $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = :id AND user_id = :uid");
        $stmt->execute([':id' => $id, ':uid' => $currentUser['id']]);
        jsonResponse(['success' => true, 'message' => 'Notification deleted']);
        break;

    default:
        jsonResponse(['success' => false, 'error' => 'Unknown action'], 400);
}
