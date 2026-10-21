<?php
/**
 * Notifications API
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
            SELECT n.*, t.title as task_title
            FROM notifications n
            LEFT JOIN tasks t ON n.task_id = t.id
            WHERE n.user_id = :uid
            ORDER BY n.created_at DESC, n.id DESC
            LIMIT 20
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

    default:
        jsonResponse(['success' => false, 'error' => 'Unknown action'], 400);
}
