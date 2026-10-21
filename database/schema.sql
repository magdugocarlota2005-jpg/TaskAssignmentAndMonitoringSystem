-- Task Assignment and Monitoring System (TAMS)
-- MySQL / MariaDB Schema & Initial Seed Data

CREATE DATABASE IF NOT EXISTS `tams_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `tams_db`;

-- Users Table
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `username` VARCHAR(50) UNIQUE NOT NULL,
  `email` VARCHAR(100) UNIQUE NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'team_leader', 'team_member') NOT NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `avatar_color` VARCHAR(20) DEFAULT '#4f46e5',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tasks Table
CREATE TABLE IF NOT EXISTS `tasks` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `priority` ENUM('Low', 'Medium', 'High', 'Urgent') NOT NULL DEFAULT 'Medium',
  `status` ENUM('To Do', 'In Progress', 'Completed') NOT NULL DEFAULT 'To Do',
  `created_by` INT NOT NULL,
  `assigned_to` INT NULL,
  `deadline` DATETIME NOT NULL,
  `completed_at` DATETIME NULL,
  `completed_by` INT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`assigned_to`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`completed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Task History / Audit Log Table
CREATE TABLE IF NOT EXISTS `task_history` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `task_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `action_type` VARCHAR(50) NOT NULL,
  `description` TEXT NOT NULL,
  `old_value` TEXT NULL,
  `new_value` TEXT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`task_id`) REFERENCES `tasks`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Notifications Table
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `task_id` INT NULL,
  `title` VARCHAR(200) NOT NULL,
  `message` TEXT NOT NULL,
  `is_read` TINYINT(1) DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`task_id`) REFERENCES `tasks`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default Users:
-- Admin: admin / admin123
-- Team Leader: leader / leader123
-- Member: member1 / member123
-- Member: member2 / member123
-- Member: member3 / member123
INSERT INTO `users` (`id`, `name`, `username`, `email`, `password`, `role`, `status`, `avatar_color`) VALUES
(1, 'Eleanor Vance', 'admin', 'admin@tam-system.local', '$2y$10$wO0s1qJkRkM3R0N7b9pCqOi8WfD0eE6.hO5n9jC7yU5.2yP8.dZea', 'admin', 'active', '#8b5cf6'),
(2, 'Marcus Sterling', 'leader', 'marcus@tam-system.local', '$2y$10$Q4e07z13E0e4pB9qB0x8.O5nF5w7E6n0aP7kQ8m9.eD2zZ6vA9uTq', 'team_leader', 'active', '#4f46e5'),
(3, 'Sarah Connor', 'member1', 'sarah@tam-system.local', '$2y$10$y6pG5w7eE6n0aP7kQ8m9.eD2zZ6vA9uTqQ4e07z13E0e4pB9qB0x8.', 'team_member', 'active', '#0ea5e9'),
(4, 'David Miller', 'member2', 'david@tam-system.local', '$2y$10$y6pG5w7eE6n0aP7kQ8m9.eD2zZ6vA9uTqQ4e07z13E0e4pB9qB0x8.', 'team_member', 'active', '#10b981'),
(5, 'Aaliyah Chen', 'member3', 'aaliyah@tam-system.local', '$2y$10$y6pG5w7eE6n0aP7kQ8m9.eD2zZ6vA9uTqQ4e07z13E0e4pB9qB0x8.', 'team_member', 'active', '#f59e0b')
ON DUPLICATE KEY UPDATE `username`=`username`;
