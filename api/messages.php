<?php
/**
 * Messages API Gateway
 * Delegates actions to notifications.php or handles them directly
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';

// Map actions
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';
if ($action === 'send') {
    $_GET['action'] = 'send_message';
    $_POST['action'] = 'send_message';
} elseif ($action === 'task') {
    $_GET['action'] = 'task_messages';
    $_POST['action'] = 'task_messages';
}

require __DIR__ . '/notifications.php';
