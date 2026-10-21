<?php
/**
 * Database Connection & Auto-Initialization
 * Task Assignment and Monitoring System (TAMS)
 */

require_once __DIR__ . '/config.php';

function getDbConnection() {
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    try {
        if (DB_DRIVER === 'sqlite') {
            if (!is_dir(DATA_PATH)) {
                mkdir(DATA_PATH, 0777, true);
            }
            $pdo = new PDO('sqlite:' . SQLITE_FILE);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $pdo->exec("PRAGMA foreign_keys = ON;");
            initializeDatabase($pdo, 'sqlite');
        } else {
            $dsn = "mysql:host=" . DB_HOST . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `" . DB_NAME . "`");
            initializeDatabase($pdo, 'mysql');
        }

        return $pdo;
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}

/**
 * Initialize Tables & Demo Seed Data
 */
function initializeDatabase(PDO $pdo, $driver = 'sqlite') {
    // Check if users table exists
    if ($driver === 'sqlite') {
        $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'");
        $tableExists = (bool)$stmt->fetch();
    } else {
        $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
        $tableExists = (bool)$stmt->fetch();
    }

    if ($tableExists) {
        return; // Already initialized
    }

    if ($driver === 'sqlite') {
        // SQLite Schema
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                username TEXT UNIQUE NOT NULL,
                email TEXT UNIQUE NOT NULL,
                password TEXT NOT NULL,
                role TEXT NOT NULL CHECK(role IN ('admin', 'team_leader', 'team_member')),
                status TEXT NOT NULL DEFAULT 'active' CHECK(status IN ('active', 'inactive')),
                avatar_color TEXT DEFAULT '#4f46e5',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS tasks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                description TEXT,
                priority TEXT NOT NULL DEFAULT 'Medium' CHECK(priority IN ('Low', 'Medium', 'High', 'Urgent')),
                status TEXT NOT NULL DEFAULT 'To Do' CHECK(status IN ('To Do', 'In Progress', 'Completed')),
                created_by INTEGER NOT NULL,
                assigned_to INTEGER,
                deadline DATETIME NOT NULL,
                completed_at DATETIME,
                completed_by INTEGER,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
                FOREIGN KEY (completed_by) REFERENCES users(id) ON DELETE SET NULL
            );

            CREATE TABLE IF NOT EXISTS task_history (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                task_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                action_type TEXT NOT NULL,
                description TEXT NOT NULL,
                old_value TEXT,
                new_value TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS notifications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                task_id INTEGER,
                title TEXT NOT NULL,
                message TEXT NOT NULL,
                is_read INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE SET NULL
            );
        ");
    } else {
        // MySQL Schema
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                username VARCHAR(50) UNIQUE NOT NULL,
                email VARCHAR(100) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                role ENUM('admin', 'team_leader', 'team_member') NOT NULL,
                status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
                avatar_color VARCHAR(20) DEFAULT '#4f46e5',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS tasks (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                description TEXT,
                priority ENUM('Low', 'Medium', 'High', 'Urgent') NOT NULL DEFAULT 'Medium',
                status ENUM('To Do', 'In Progress', 'Completed') NOT NULL DEFAULT 'To Do',
                created_by INT NOT NULL,
                assigned_to INT NULL,
                deadline DATETIME NOT NULL,
                completed_at DATETIME NULL,
                completed_by INT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
                FOREIGN KEY (completed_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS task_history (
                id INT AUTO_INCREMENT PRIMARY KEY,
                task_id INT NOT NULL,
                user_id INT NOT NULL,
                action_type VARCHAR(50) NOT NULL,
                description TEXT NOT NULL,
                old_value TEXT NULL,
                new_value TEXT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                task_id INT NULL,
                title VARCHAR(200) NOT NULL,
                message TEXT NOT NULL,
                is_read TINYINT(1) DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }

    // Seed Initial Realistic Users
    $passwordAdmin = password_hash('admin123', PASSWORD_DEFAULT);
    $passwordLeader = password_hash('leader123', PASSWORD_DEFAULT);
    $passwordMember = password_hash('member123', PASSWORD_DEFAULT);

    $insertUser = $pdo->prepare("
        INSERT INTO users (name, username, email, password, role, status, avatar_color) 
        VALUES (:name, :username, :email, :password, :role, :status, :color)
    ");

    $users = [
        ['name' => 'Eleanor Vance', 'username' => 'admin', 'email' => 'admin@tam-system.local', 'password' => $passwordAdmin, 'role' => 'admin', 'status' => 'active', 'color' => '#8b5cf6'],
        ['name' => 'Marcus Sterling', 'username' => 'leader', 'email' => 'marcus@tam-system.local', 'password' => $passwordLeader, 'role' => 'team_leader', 'status' => 'active', 'color' => '#4f46e5'],
        ['name' => 'Sarah Connor', 'username' => 'member1', 'email' => 'sarah@tam-system.local', 'password' => $passwordMember, 'role' => 'team_member', 'status' => 'active', 'color' => '#0ea5e9'],
        ['name' => 'David Miller', 'username' => 'member2', 'email' => 'david@tam-system.local', 'password' => $passwordMember, 'role' => 'team_member', 'status' => 'active', 'color' => '#10b981'],
        ['name' => 'Aaliyah Chen', 'username' => 'member3', 'email' => 'aaliyah@tam-system.local', 'password' => $passwordMember, 'role' => 'team_member', 'status' => 'active', 'color' => '#f59e0b'],
    ];

    foreach ($users as $u) {
        $insertUser->execute([
            ':name' => $u['name'],
            ':username' => $u['username'],
            ':email' => $u['email'],
            ':password' => $u['password'],
            ':role' => $u['role'],
            ':status' => $u['status'],
            ':color' => $u['color']
        ]);
    }

    // Seed Initial Tasks with realistic dates
    // 1 Overdue task (deadline 2 days ago)
    // 1 Approaching deadline task (deadline tomorrow)
    // 1 To Do task (deadline next week)
    // 1 In Progress task (deadline in 4 days)
    // 1 Completed task (completed yesterday)
    $now = time();
    $twoDaysAgo = date('Y-m-d H:i:s', $now - (2 * 86400));
    $threeDaysAgo = date('Y-m-d H:i:s', $now - (3 * 86400));
    $yesterday = date('Y-m-d H:i:s', $now - 86400);
    $tomorrow = date('Y-m-d H:i:s', $now + 86400);
    $inThreeDays = date('Y-m-d H:i:s', $now + (3 * 86400));
    $nextWeek = date('Y-m-d H:i:s', $now + (7 * 86400));

    $insertTask = $pdo->prepare("
        INSERT INTO tasks (title, description, priority, status, created_by, assigned_to, deadline, completed_at, completed_by, created_at)
        VALUES (:title, :description, :priority, :status, :created_by, :assigned_to, :deadline, :completed_at, :completed_by, :created_at)
    ");

    $insertHistory = $pdo->prepare("
        INSERT INTO task_history (task_id, user_id, action_type, description, old_value, new_value, created_at)
        VALUES (:task_id, :user_id, :action_type, :description, :old_value, :new_value, :created_at)
    ");

    $tasksSeed = [
        [
            'title' => 'Fix SSL Certificate and API Security Headers',
            'description' => 'Renew the production SSL certificate on the gateway proxy and configure Content Security Policy (CSP) headers.',
            'priority' => 'Urgent',
            'status' => 'In Progress', // Overdue because deadline was 2 days ago!
            'created_by' => 2, // Marcus (Team Leader)
            'assigned_to' => 3, // Sarah Connor
            'deadline' => $twoDaysAgo,
            'completed_at' => null,
            'completed_by' => null,
            'created_at' => date('Y-m-d H:i:s', $now - (5 * 86400)),
            'history' => [
                ['action_type' => 'created', 'desc' => 'Task created by Marcus Sterling', 'user' => 2, 'time' => date('Y-m-d H:i:s', $now - (5 * 86400))],
                ['action_type' => 'assigned', 'desc' => 'Assigned task to Sarah Connor', 'user' => 2, 'time' => date('Y-m-d H:i:s', $now - (5 * 86400))],
                ['action_type' => 'status_change', 'desc' => 'Changed status from To Do to In Progress', 'user' => 3, 'time' => date('Y-m-d H:i:s', $now - (3 * 86400))]
            ]
        ],
        [
            'title' => 'Design User Onboarding & Role Dashboard Wireframes',
            'description' => 'Create high-fidelity Figma components and flow wireframes for Admin, Team Leader, and Team Member interfaces.',
            'priority' => 'High',
            'status' => 'In Progress', // Approaching deadline (tomorrow)
            'created_by' => 2,
            'assigned_to' => 4, // David Miller
            'deadline' => $tomorrow,
            'completed_at' => null,
            'completed_by' => null,
            'created_at' => date('Y-m-d H:i:s', $now - (4 * 86400)),
            'history' => [
                ['action_type' => 'created', 'desc' => 'Task created by Marcus Sterling', 'user' => 2, 'time' => date('Y-m-d H:i:s', $now - (4 * 86400))],
                ['action_type' => 'assigned', 'desc' => 'Assigned task to David Miller', 'user' => 2, 'time' => date('Y-m-d H:i:s', $now - (4 * 86400))],
                ['action_type' => 'status_change', 'desc' => 'Changed status from To Do to In Progress', 'user' => 4, 'time' => date('Y-m-d H:i:s', $now - (2 * 86400))]
            ]
        ],
        [
            'title' => 'Implement Automated Regression & Stress Testing Suite',
            'description' => 'Write end-to-end Cypress test cases for task assignments, status state transitions, and audit trail validation.',
            'priority' => 'Medium',
            'status' => 'To Do',
            'created_by' => 2,
            'assigned_to' => 5, // Aaliyah Chen
            'deadline' => $inThreeDays,
            'completed_at' => null,
            'completed_by' => null,
            'created_at' => date('Y-m-d H:i:s', $now - (2 * 86400)),
            'history' => [
                ['action_type' => 'created', 'desc' => 'Task created by Marcus Sterling', 'user' => 2, 'time' => date('Y-m-d H:i:s', $now - (2 * 86400))],
                ['action_type' => 'assigned', 'desc' => 'Assigned task to Aaliyah Chen', 'user' => 2, 'time' => date('Y-m-d H:i:s', $now - (2 * 86400))]
            ]
        ],
        [
            'title' => 'Optimize SQLite/MySQL Indexing for Audit History Queries',
            'description' => 'Add indexes on task_id, status, and created_at columns to guarantee sub-millisecond query performance on large data sets.',
            'priority' => 'Low',
            'status' => 'To Do',
            'created_by' => 1, // Eleanor (Admin)
            'assigned_to' => 3, // Sarah Connor
            'deadline' => $nextWeek,
            'completed_at' => null,
            'completed_by' => null,
            'created_at' => date('Y-m-d H:i:s', $now - 86400),
            'history' => [
                ['action_type' => 'created', 'desc' => 'Task created by Eleanor Vance', 'user' => 1, 'time' => date('Y-m-d H:i:s', $now - 86400)],
                ['action_type' => 'assigned', 'desc' => 'Assigned task to Sarah Connor', 'user' => 1, 'time' => date('Y-m-d H:i:s', $now - 86400)]
            ]
        ],
        [
            'title' => 'Set Up System Role-Based Access Control Middleware',
            'description' => 'Verify session-based access policies restricting Admin, Team Leader, and Member permissions across all endpoints.',
            'priority' => 'High',
            'status' => 'Completed',
            'created_by' => 2,
            'assigned_to' => 3, // Sarah Connor
            'deadline' => $yesterday,
            'completed_at' => $yesterday,
            'completed_by' => 3,
            'created_at' => date('Y-m-d H:i:s', $now - (6 * 86400)),
            'history' => [
                ['action_type' => 'created', 'desc' => 'Task created by Marcus Sterling', 'user' => 2, 'time' => date('Y-m-d H:i:s', $now - (6 * 86400))],
                ['action_type' => 'assigned', 'desc' => 'Assigned task to Sarah Connor', 'user' => 2, 'time' => date('Y-m-d H:i:s', $now - (6 * 86400))],
                ['action_type' => 'status_change', 'desc' => 'Changed status from To Do to In Progress', 'user' => 3, 'time' => date('Y-m-d H:i:s', $now - (4 * 86400))],
                ['action_type' => 'completed', 'desc' => 'Task marked as Completed by Sarah Connor', 'user' => 3, 'time' => $yesterday]
            ]
        ]
    ];

    foreach ($tasksSeed as $t) {
        $insertTask->execute([
            ':title' => $t['title'],
            ':description' => $t['description'],
            ':priority' => $t['priority'],
            ':status' => $t['status'],
            ':created_by' => $t['created_by'],
            ':assigned_to' => $t['assigned_to'],
            ':deadline' => $t['deadline'],
            ':completed_at' => $t['completed_at'],
            ':completed_by' => $t['completed_by'],
            ':created_at' => $t['created_at']
        ]);
        $taskId = $pdo->lastInsertId();

        foreach ($t['history'] as $h) {
            $insertHistory->execute([
                ':task_id' => $taskId,
                ':user_id' => $h['user'],
                ':action_type' => $h['action_type'],
                ':description' => $h['desc'],
                ':old_value' => null,
                ':new_value' => null,
                ':created_at' => $h['time']
            ]);
        }
    }

    // Insert sample notification for Team Leader about completed task
    $pdo->prepare("
        INSERT INTO notifications (user_id, task_id, title, message, is_read, created_at)
        VALUES (:user_id, :task_id, :title, :message, :is_read, :created_at)
    ")->execute([
        ':user_id' => 2, // Marcus Sterling (Leader)
        ':task_id' => 5,
        ':title' => 'Task Completed: RBAC Middleware',
        ':message' => 'Sarah Connor completed the task "Set Up System Role-Based Access Control Middleware".',
        ':is_read' => 0,
        ':created_at' => $yesterday
    ]);
}
