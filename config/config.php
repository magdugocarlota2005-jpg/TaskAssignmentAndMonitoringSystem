<?php
/**
 * Configuration & Helper Functions
 * Task Assignment and Monitoring System (TAMS)
 */

// Timezone configuration (e.g. 'UTC', 'America/New_York', 'Asia/Manila', 'Europe/London')
define('APP_TIMEZONE', 'UTC');
date_default_timezone_set(APP_TIMEZONE);

// Application definitions
define('APP_NAME', 'Task Assignment & Monitoring System');
define('APP_VERSION', '1.0.0');
define('BASE_PATH', dirname(__DIR__));
define('DATA_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'data');
define('SQLITE_FILE', DATA_PATH . DIRECTORY_SEPARATOR . 'tams.sqlite');

// Threshold in days for tasks to be flagged as "Approaching Deadline" (default: 2 days / 48 hrs)
define('APPROACHING_DEADLINE_DAYS', 2);

// Session configuration
$sessDir = DATA_PATH . DIRECTORY_SEPARATOR . 'sessions';
if (!is_dir($sessDir)) {
    @mkdir($sessDir, 0777, true);
}
if (is_dir($sessDir) && is_writable($sessDir)) {
    @session_save_path($sessDir);
}

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// Database configuration
// Driver options:
// - 'sqlite' : Zero-setup file-based database stored in data/tams.sqlite (Recommended for quick start)
// - 'mysql'  : MySQL / MariaDB via XAMPP (requires MySQL service started in XAMPP Control Panel)
define('DB_DRIVER', 'sqlite'); 
define('DB_HOST', '127.0.0.1');
define('DB_PORT', 3306);
define('DB_NAME', 'tams_db');
define('DB_USER', 'root');
define('DB_PASS', '');

/**
 * Return JSON response and exit
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user']) && !empty($_SESSION['user']['id']);
}

/**
 * Get current logged in user
 */
function currentUser() {
    return $_SESSION['user'] ?? null;
}

/**
 * Ensure user is logged in, otherwise return 401 or redirect
 */
function requireAuth($isApi = false) {
    if (!isLoggedIn()) {
        if ($isApi) {
            jsonResponse(['success' => false, 'error' => 'Authentication required'], 401);
        } else {
            header('Location: login.php');
            exit;
        }
    }
}

/**
 * Require specific roles
 */
function requireRole($allowedRoles, $isApi = false) {
    requireAuth($isApi);
    $user = currentUser();
    if (!in_array($user['role'], (array)$allowedRoles)) {
        if ($isApi) {
            jsonResponse(['success' => false, 'error' => 'Access denied: insufficient permissions'], 403);
        } else {
            header('Location: index.php?error=forbidden');
            exit;
        }
    }
}

/**
 * Helper to escape HTML safely
 */
function h($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Check if a task is overdue
 * Overdue: deadline is passed AND status is NOT 'Completed'
 */
function isTaskOverdue($deadline, $status) {
    if ($status === 'Completed') {
        return false;
    }
    if (empty($deadline)) {
        return false;
    }
    $tzName = defined('APP_TIMEZONE') ? APP_TIMEZONE : 'UTC';
    $now = new DateTime('now', new DateTimeZone($tzName));
    $due = new DateTime($deadline, new DateTimeZone($tzName));
    return $due < $now;
}

/**
 * Check if task deadline is approaching (within configured threshold, e.g. 48 hours)
 */
function isDeadlineApproaching($deadline, $status) {
    if ($status === 'Completed') {
        return false;
    }
    if (empty($deadline)) {
        return false;
    }
    $tzName = defined('APP_TIMEZONE') ? APP_TIMEZONE : 'UTC';
    $now = new DateTime('now', new DateTimeZone($tzName));
    $due = new DateTime($deadline, new DateTimeZone($tzName));
    $diff = $now->diff($due);
    
    // If already overdue
    if ($due < $now) {
        return false;
    }
    // Approaching if within configured threshold days
    $threshold = defined('APPROACHING_DEADLINE_DAYS') ? APPROACHING_DEADLINE_DAYS : 2;
    return ($diff->days <= $threshold);
}
