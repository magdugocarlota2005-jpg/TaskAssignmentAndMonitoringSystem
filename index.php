<?php
/**
 * Main Application Dashboard
 * Task Assignment and Monitoring System (TAMS)
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';

requireAuth(false);
$currentUser = currentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h(APP_NAME) ?></title>
    <link rel="stylesheet" href="assets/css/app.css?v=<?= filemtime(__DIR__ . '/assets/css/app.css') ?>">
</head>
<body>

    <!-- Demo Role Switcher Banner (For testing & evaluation) -->
    <div class="demo-bar">
        <div class="container demo-bar-inner">
            <div style="display:flex; align-items:center; gap:0.5rem;">
                <span style="color:#fff; font-weight:600;">⚡ Quick Demo Switcher:</span>
                <span style="color:var(--text-dim);">Test any role with 1 click:</span>
            </div>
            <div class="demo-badges">
                <button class="demo-btn <?= $currentUser['role'] === 'admin' ? 'active' : '' ?>" data-role="admin" onclick="TAMS.switchRole('admin')">
                    👑 Administrator
                </button>
                <button class="demo-btn <?= $currentUser['role'] === 'team_leader' ? 'active' : '' ?>" data-role="team_leader" onclick="TAMS.switchRole('team_leader')">
                    🎯 Team Leader
                </button>
                <button class="demo-btn <?= $currentUser['role'] === 'team_member' ? 'active' : '' ?>" data-role="team_member" onclick="TAMS.switchRole('team_member')">
                    💻 Team Member
                </button>
            </div>
        </div>
    </div>

    <!-- Navigation Header -->
    <nav class="navbar">
        <div class="container nav-container">
            <div class="nav-brand">
                <div class="brand-icon">✓</div>
                <div>
                    <div>TAMS</div>
                    <div style="font-size:0.65rem; color:var(--text-dim); font-weight:normal; letter-spacing:0.02em;">Task Monitoring</div>
                </div>
            </div>

            <div class="nav-links">
                <button class="nav-btn active" id="nav-btn-tasks" onclick="showView('tasks')">
                    📋 Tasks
                </button>
                
                <?php if ($currentUser['role'] === 'admin'): ?>
                    <button class="nav-btn role-admin-only" id="nav-btn-users" onclick="showView('users')">
                        👥 Users Management
                    </button>
                <?php endif; ?>
            </div>

            <div class="user-menu">
                <!-- Send Message Quick Action -->
                <button class="btn btn-sm btn-secondary" onclick="TAMS.openSendMessageModal()" title="Send Message Notification" style="gap:0.35rem; display:inline-flex; align-items:center;">
                    <span>✉️</span> <span class="hide-mobile">Message</span>
                </button>

                <!-- Notifications Bell -->
                <div style="position:relative;">
                    <button class="notif-btn" id="notif-btn" title="Notifications">
                        🔔
                        <span class="notif-badge" id="notif-badge" style="display:none;">0</span>
                    </button>

                    <div class="notif-dropdown" id="notif-dropdown">
                        <div class="notif-header">
                            <div>
                                <span style="font-weight:700; color:#fff; font-size:0.875rem;">Notifications</span>
                                <span id="notif-unread-tag" style="display:none; font-size:0.7rem; background:rgba(79,70,229,0.25); color:#818cf8; padding:0.1rem 0.4rem; border-radius:999px; margin-left:0.3rem;">0 unread</span>
                            </div>
                            <div style="display:flex; gap:0.35rem;">
                                <button class="btn btn-sm btn-secondary" onclick="TAMS.markAllNotificationsRead()" style="font-size:0.7rem; padding:0.2rem 0.5rem;">
                                    Mark all read
                                </button>
                                <button class="btn btn-sm btn-primary" onclick="TAMS.openSendMessageModal()" style="font-size:0.7rem; padding:0.2rem 0.5rem;" title="Compose new message">
                                    + Compose
                                </button>
                            </div>
                        </div>
                        <div class="notif-list" id="notif-list">
                            <div style="padding:1.5rem; text-align:center; color:var(--text-dim); font-size:0.85rem;">
                                Loading notifications...
                            </div>
                        </div>
                    </div>
                </div>

                <!-- User Profile Badge -->
                <div class="user-badge">
                    <div class="avatar" id="header-user-avatar">
                        <?= strtoupper(substr($currentUser['name'], 0, 1)) ?>
                    </div>
                    <div class="user-info">
                        <span class="user-name" id="header-user-name"><?= h($currentUser['name']) ?></span>
                        <span class="user-role-tag" id="header-user-role"><?= ucfirst(str_replace('_', ' ', $currentUser['role'])) ?></span>
                    </div>
                </div>

                <!-- Logout Button -->
                <a href="logout.php" class="btn btn-secondary btn-sm" title="Log out of system">
                    🚪 Logout
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Content Container -->
    <main class="main-content">
        <div class="container">

            <!-- VIEW 1: TASKS DASHBOARD -->
            <div id="view-tasks">
                <!-- Page Title Header -->
                <div class="page-header">
                    <div class="header-title-box">
                        <h1>
                            <span>
                                <?php if ($currentUser['role'] === 'admin'): ?>
                                    Admin Dashboard
                                <?php elseif ($currentUser['role'] === 'team_leader'): ?>
                                    Team Leader Dashboard
                                <?php else: ?>
                                    Team Member Dashboard
                                <?php endif; ?>
                            </span>
                            <span class="role-pill <?= $currentUser['role'] ?>">
                                <?= ucfirst(str_replace('_', ' ', $currentUser['role'])) ?>
                            </span>
                        </h1>
                        <p class="header-subtitle">
                            <?php if ($currentUser['role'] === 'admin'): ?>
                                System-wide task assignment, access control, audit records, and progress monitoring.
                            <?php elseif ($currentUser['role'] === 'team_leader'): ?>
                                Create and assign tasks, set deadlines, monitor team progress, and review audit history.
                            <?php else: ?>
                                Track your assigned responsibilities, review deadlines, and update your task status.
                            <?php endif; ?>
                        </p>
                    </div>

                    <div class="header-actions">
                        <button class="btn btn-secondary" onclick="TAMS.openSendMessageModal()" title="Send Message Notification to User or Team">
                            <span>✉️</span> Message Team
                        </button>

                        <?php if (in_array($currentUser['role'], ['admin', 'team_leader'])): ?>
                            <button class="btn btn-primary" onclick="TAMS.openCreateTaskModal()">
                                <span>+</span> Create New Task
                            </button>
                        <?php endif; ?>

                        <button class="btn btn-secondary" onclick="TAMS.refreshAll()" title="Refresh Data">
                            🔄 Refresh
                        </button>
                    </div>
                </div>

                <!-- Overdue Warning Banner (Flow Step 34: "Highlight task on dashboard") -->
                <div class="overdue-alert-banner" id="overdue-banner" style="display:none;">
                    <div class="overdue-alert-content">
                        <div class="overdue-pulse-icon">⚠️</div>
                        <div>
                            <div style="font-weight:700; color:#fff; font-size:1rem;">Overdue Tasks Detected</div>
                            <div style="color:#fca5a5; font-size:0.875rem;" id="overdue-banner-text">
                                One or more tasks have exceeded their deadline.
                            </div>
                        </div>
                    </div>
                    <button class="btn btn-danger btn-sm" onclick="filterOverdue()">
                        View Overdue Tasks →
                    </button>
                </div>

                <!-- Dashboard Metric Cards (Flowchart Step 56) -->
                <div class="stats-grid">
                    <div class="stat-card total" onclick="setFilter('all')">
                        <div class="stat-top">
                            <span class="stat-label">Total Tasks</span>
                            <span class="stat-icon">📊</span>
                        </div>
                        <div class="stat-value" id="stat-total">0</div>
                        <div class="stat-desc">All system tasks in scope</div>
                    </div>

                    <div class="stat-card pending" onclick="setFilter('pending')">
                        <div class="stat-top">
                            <span class="stat-label">Pending</span>
                            <span class="stat-icon">⏳</span>
                        </div>
                        <div class="stat-value" id="stat-pending">0</div>
                        <div class="stat-desc">To Do &amp; In Progress</div>
                    </div>

                    <div class="stat-card completed" onclick="setFilter('completed')">
                        <div class="stat-top">
                            <span class="stat-label">Completed</span>
                            <span class="stat-icon">✓</span>
                        </div>
                        <div class="stat-value" id="stat-completed">0</div>
                        <div class="stat-desc">Finished tasks</div>
                    </div>

                    <div class="stat-card overdue" onclick="setFilter('overdue')">
                        <div class="stat-top">
                            <span class="stat-label">Overdue</span>
                            <span class="stat-icon">⚠️</span>
                        </div>
                        <div class="stat-value" id="stat-overdue">0</div>
                        <div class="stat-desc">Missed deadlines</div>
                    </div>

                    <div class="stat-card approaching" onclick="setFilter('approaching')">
                        <div class="stat-top">
                            <span class="stat-label">Due Soon</span>
                            <span class="stat-icon">⏰</span>
                        </div>
                        <div class="stat-value" id="stat-approaching">0</div>
                        <div class="stat-desc">Within 48 hours</div>
                    </div>
                </div>

                <!-- Controls & Filters Bar -->
                <div class="controls-card">
                    <!-- Search Input -->
                    <div class="search-box">
                        <span class="search-icon">🔍</span>
                        <input type="text" id="search-input" class="search-input" placeholder="Search tasks by title or keyword...">
                    </div>

                    <!-- Dropdown Selectors -->
                    <div class="filter-group">
                        <select id="filter-status" class="filter-select">
                            <option value="">All Statuses</option>
                            <option value="To Do">To Do</option>
                            <option value="In Progress">In Progress</option>
                            <option value="Completed">Completed</option>
                        </select>

                        <select id="filter-priority" class="filter-select">
                            <option value="">All Priorities</option>
                            <option value="Urgent">Urgent</option>
                            <option value="High">High</option>
                            <option value="Medium">Medium</option>
                            <option value="Low">Low</option>
                        </select>

                        <?php if ($currentUser['role'] !== 'team_member'): ?>
                            <select id="filter-assignee" class="filter-select">
                                <option value="">All Assignees</option>
                            </select>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Filter Tabs -->
                <div class="tab-pills">
                    <button class="tab-pill active" data-filter="all">
                        All Tasks <span class="pill-count" id="count-all">0</span>
                    </button>
                    <button class="tab-pill" data-filter="overdue">
                        ⚠️ Overdue <span class="pill-count" id="count-overdue">0</span>
                    </button>
                    <button class="tab-pill" data-filter="approaching">
                        ⏳ Due Soon <span class="pill-count" id="count-approaching">0</span>
                    </button>
                    <button class="tab-pill" data-filter="pending">
                        📋 Pending <span class="pill-count" id="count-pending">0</span>
                    </button>
                    <button class="tab-pill" data-filter="completed">
                        ✓ Completed <span class="pill-count" id="count-completed">0</span>
                    </button>
                </div>

                <!-- Grid Layout: Tasks Grid + Recent Activities Sidebar -->
                <div style="display:grid; grid-template-columns: 1fr 340px; gap: 1.5rem; align-items: flex-start;">
                    <!-- Tasks Cards Grid -->
                    <div class="tasks-grid" id="tasks-container" style="margin-bottom:0;">
                        <!-- Tasks dynamically injected by app.js -->
                    </div>

                    <!-- Recent Audit Activity Sidebar -->
                    <div style="background:var(--bg-card); border:1px solid var(--border-color); border-radius:var(--radius-lg); padding:1.25rem;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; border-bottom:1px solid var(--border-color); padding-bottom:0.75rem;">
                            <h3 style="font-size:1rem; color:#fff;">📜 Audit Stream</h3>
                            <span style="font-size:0.75rem; color:var(--text-dim);">Live Log</span>
                        </div>
                        <div id="recent-activities-list">
                            <!-- Injected by app.js -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- VIEW 2: USER MANAGEMENT (Admin only) -->
            <?php if ($currentUser['role'] === 'admin'): ?>
                <div id="view-users" style="display:none;">
                    <div class="page-header">
                        <div class="header-title-box">
                            <h1>User Accounts &amp; Access Control</h1>
                            <p class="header-subtitle">Manage user accounts, roles, access permissions, and monitor assignments.</p>
                        </div>
                        <div class="header-actions">
                            <button class="btn btn-primary" onclick="TAMS.openCreateUserModal()">
                                <span>+</span> Add New User
                            </button>
                            <button class="btn btn-secondary" onclick="TAMS.loadUsersTable()">
                                🔄 Refresh Users
                            </button>
                        </div>
                    </div>

                    <div class="table-card">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Account Status</th>
                                        <th>Assigned Tasks</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="users-table-body">
                                    <!-- Injected by app.js -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </main>

    <!-- ==================== MODALS ==================== -->

    <!-- 1. Task Details & Audit History Modal -->
    <div class="modal-backdrop" id="task-detail-modal">
        <div class="modal-box" style="max-width: 680px;">
            <div class="modal-header">
                <div>
                    <h3 id="td-title">Task Details</h3>
                    <div style="font-size:0.75rem; color:var(--text-dim); margin-top:2px;">Task Information &amp; Audit Trail</div>
                </div>
                <button class="modal-close" onclick="TAMS.closeModal('task-detail-modal')">&times;</button>
            </div>
            <div class="modal-body">
                <div style="display:flex; flex-wrap:wrap; gap:0.5rem; margin-bottom:1rem; align-items:center;">
                    <span id="td-priority" class="badge"></span>
                    <span id="td-status" class="badge"></span>
                </div>

                <div class="form-group">
                    <label class="form-label">Description</label>
                    <div id="td-description" style="background:var(--bg-surface); padding:0.85rem; border-radius:var(--radius-md); font-size:0.9rem; color:var(--text-main); white-space:pre-line; border:1px solid var(--border-color);"></div>
                </div>

                <div class="form-row" style="margin-bottom:1rem;">
                    <div>
                        <label class="form-label">Deadline</label>
                        <div id="td-deadline" style="font-size:0.875rem; font-weight:600; color:#fff;"></div>
                    </div>
                    <div>
                        <label class="form-label">Assigned To</label>
                        <div id="td-assignee" style="font-size:0.875rem; font-weight:600; color:#fff;"></div>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:1rem;">
                    <label class="form-label">Created By</label>
                    <div id="td-creator" style="font-size:0.85rem; color:var(--text-muted);"></div>
                </div>

                <!-- Status Update Buttons -->
                <div id="td-status-actions" style="background:var(--bg-surface-alt); padding:0.75rem 1rem; border-radius:var(--radius-md); border:1px solid var(--border-color); margin-bottom:1.5rem;"></div>

                <!-- Modal Tabs: Audit Trail & Task Messages -->
                <div class="modal-tabs">
                    <button type="button" class="modal-tab-btn active" id="tab-btn-audit" onclick="TAMS.switchTaskModalTab('audit')">
                        📜 Task Audit Trail
                    </button>
                    <button type="button" class="modal-tab-btn" id="tab-btn-messages" onclick="TAMS.switchTaskModalTab('messages')">
                        💬 Task Messages &amp; Discussion <span id="td-messages-count" style="font-size:0.7rem; background:rgba(139,92,246,0.25); color:#c4b5fd; padding:0.1rem 0.4rem; border-radius:999px;">0</span>
                    </button>
                </div>

                <!-- Tab 1: Audit History Timeline -->
                <div class="modal-tab-pane active" id="tab-pane-audit">
                    <div class="timeline" id="td-timeline">
                        <!-- Populated by app.js -->
                    </div>
                </div>

                <!-- Tab 2: Task Messages / Comments & Notifications -->
                <div class="modal-tab-pane" id="tab-pane-messages">
                    <div class="task-messages-container" id="td-messages-list">
                        <!-- Populated by app.js -->
                    </div>
                    
                    <form id="task-message-form" onsubmit="TAMS.submitTaskMessage(event)" style="margin-top:0.75rem;">
                        <div style="display:flex; gap:0.5rem;">
                            <input type="text" id="task-message-input" class="form-input" placeholder="Type a message or note to notify team..." style="flex:1;" required autocomplete="off">
                            <button type="submit" class="btn btn-primary" id="btn-send-task-msg" style="white-space:nowrap;">
                                <span>➤</span> Send
                            </button>
                        </div>
                        <div style="font-size:0.7rem; color:var(--text-dim); margin-top:4px;">
                            💡 Sending a message here automatically sends an in-app message notification to all task collaborators.
                        </div>
                    </form>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="TAMS.closeModal('task-detail-modal')">Close</button>
            </div>
        </div>
    </div>

    <!-- 2. Create Task Modal (Admin & Team Leader) -->
    <div class="modal-backdrop" id="task-create-modal">
        <div class="modal-box">
            <div class="modal-header">
                <h3>Create New Task</h3>
                <button class="modal-close" onclick="TAMS.closeModal('task-create-modal')">&times;</button>
            </div>
            <form id="task-create-form" onsubmit="TAMS.submitCreateTask(event)">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label" for="task-create-title">Task Title *</label>
                        <input type="text" id="task-create-title" name="title" class="form-input" placeholder="e.g. Implement OAuth2 Authentication" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="task-create-description">Task Description</label>
                        <textarea id="task-create-description" name="description" class="form-textarea" placeholder="Detailed instructions, deliverables, and acceptance criteria..."></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="task-create-priority">Priority *</label>
                            <select id="task-create-priority" name="priority" class="form-select" required>
                                <option value="Low">Low</option>
                                <option value="Medium" selected>Medium</option>
                                <option value="High">High</option>
                                <option value="Urgent">Urgent 🔥</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="task-create-deadline">Deadline Date &amp; Time *</label>
                            <input type="datetime-local" id="task-create-deadline" name="deadline" class="form-input" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="task-create-assignee">Assign To Team Member</label>
                        <select id="task-create-assignee" name="assigned_to" class="form-select">
                            <option value="">-- Unassigned --</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="TAMS.closeModal('task-create-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create &amp; Assign Task</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 3. Edit Task Modal (Admin & Team Leader) -->
    <div class="modal-backdrop" id="task-edit-modal">
        <div class="modal-box">
            <div class="modal-header">
                <h3>Edit Task Details &amp; Assignment</h3>
                <button class="modal-close" onclick="TAMS.closeModal('task-edit-modal')">&times;</button>
            </div>
            <form id="task-edit-form" onsubmit="TAMS.submitEditTask(event)">
                <input type="hidden" id="task-edit-id" name="id">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label" for="task-edit-title">Task Title *</label>
                        <input type="text" id="task-edit-title" name="title" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="task-edit-description">Description</label>
                        <textarea id="task-edit-description" name="description" class="form-textarea"></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="task-edit-priority">Priority</label>
                            <select id="task-edit-priority" name="priority" class="form-select">
                                <option value="Low">Low</option>
                                <option value="Medium">Medium</option>
                                <option value="High">High</option>
                                <option value="Urgent">Urgent 🔥</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="task-edit-deadline">Deadline Date &amp; Time</label>
                            <input type="datetime-local" id="task-edit-deadline" name="deadline" class="form-input" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="task-edit-assignee">Assign / Reassign To</label>
                        <select id="task-edit-assignee" name="assigned_to" class="form-select">
                            <option value="">-- Unassigned --</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="TAMS.closeModal('task-edit-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes &amp; Log History</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 4. Create User Modal (Admin only) -->
    <div class="modal-backdrop" id="user-create-modal">
        <div class="modal-box">
            <div class="modal-header">
                <h3>Add New User</h3>
                <button class="modal-close" onclick="TAMS.closeModal('user-create-modal')">&times;</button>
            </div>
            <form id="user-create-form" onsubmit="TAMS.submitCreateUser(event)">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label" for="user-create-name">Full Name *</label>
                        <input type="text" id="user-create-name" name="name" class="form-input" placeholder="e.g. John Doe" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="user-create-username">Username *</label>
                            <input type="text" id="user-create-username" name="username" class="form-input" placeholder="e.g. jdoe" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="user-create-email">Email *</label>
                            <input type="email" id="user-create-email" name="email" class="form-input" placeholder="jdoe@example.com" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="user-create-password">Password *</label>
                            <input type="password" id="user-create-password" name="password" class="form-input" placeholder="••••••••" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="user-create-role">Role *</label>
                            <select id="user-create-role" name="role" class="form-select" required>
                                <option value="team_member" selected>Team Member</option>
                                <option value="team_leader">Team Leader</option>
                                <option value="admin">Administrator</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="TAMS.closeModal('user-create-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create User Account</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 5. Edit User Modal (Admin only) -->
    <div class="modal-backdrop" id="user-edit-modal">
        <div class="modal-box">
            <div class="modal-header">
                <h3>Edit User Account</h3>
                <button class="modal-close" onclick="TAMS.closeModal('user-edit-modal')">&times;</button>
            </div>
            <form id="user-edit-form" onsubmit="TAMS.submitEditUser(event)">
                <input type="hidden" id="user-edit-id" name="id">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label" for="user-edit-name">Full Name *</label>
                        <input type="text" id="user-edit-name" name="name" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="user-edit-email">Email *</label>
                        <input type="email" id="user-edit-email" name="email" class="form-input" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="user-edit-role">Role</label>
                            <select id="user-edit-role" name="role" class="form-select" required>
                                <option value="team_member">Team Member</option>
                                <option value="team_leader">Team Leader</option>
                                <option value="admin">Administrator</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="user-edit-status">Status</label>
                            <select id="user-edit-status" name="status" class="form-select" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="user-edit-password">New Password (leave blank to keep current)</label>
                        <input type="password" id="user-edit-password" name="password" class="form-input" placeholder="••••••••">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="TAMS.closeModal('user-edit-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 6. Send Message Notification Modal -->
    <div class="modal-backdrop" id="send-message-modal">
        <div class="modal-box" style="max-width: 520px;">
            <div class="modal-header">
                <div>
                    <h3>✉️ Send Message Notification</h3>
                    <div style="font-size:0.75rem; color:var(--text-dim); margin-top:2px;">Notify a team member or broadcast to all</div>
                </div>
                <button class="modal-close" onclick="TAMS.closeModal('send-message-modal')">&times;</button>
            </div>
            <form id="send-message-form" onsubmit="TAMS.submitSendMessage(event)">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label" for="msg-recipient">Recipient *</label>
                        <select id="msg-recipient" name="receiver_id" class="form-select" required>
                            <option value="0">📢 Broadcast to All Active Users</option>
                            <!-- Populated with users -->
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="msg-task">Related Task (Optional)</label>
                        <select id="msg-task" name="task_id" class="form-select">
                            <option value="">-- None / General Message --</option>
                            <!-- Populated with tasks -->
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="msg-title">Subject / Title (Optional)</label>
                        <input type="text" id="msg-title" name="title" class="form-input" placeholder="e.g. Schedule update, Question about requirements">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="msg-content">Message Content *</label>
                        <textarea id="msg-content" name="message" class="form-textarea" rows="4" placeholder="Type your message notification here..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="TAMS.closeModal('send-message-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="btn-send-message">
                        <span>✉️</span> Send Message Notification
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- 7. View Message Modal -->
    <div class="modal-backdrop" id="view-message-modal">
        <div class="modal-box" style="max-width: 500px;">
            <div class="modal-header">
                <div>
                    <h3 id="vm-title">💬 Message Notification</h3>
                    <div id="vm-date" style="font-size:0.75rem; color:var(--text-dim); margin-top:2px;"></div>
                </div>
                <button class="modal-close" onclick="TAMS.closeModal('view-message-modal')">&times;</button>
            </div>
            <div class="modal-body">
                <div style="display:flex; align-items:center; gap:0.75rem; margin-bottom:1rem; padding-bottom:0.75rem; border-bottom:1px solid var(--border-color);">
                    <div class="avatar" id="vm-sender-avatar" style="width:38px; height:38px; font-size:0.875rem;"></div>
                    <div>
                        <div id="vm-sender-name" style="font-weight:600; color:#fff;"></div>
                        <div id="vm-sender-role" style="font-size:0.75rem; color:var(--text-muted);"></div>
                    </div>
                </div>

                <div id="vm-task-box" style="margin-bottom:0.75rem; display:none;">
                    <span style="font-size:0.75rem; color:var(--text-dim);">Regarding Task: </span>
                    <button type="button" class="btn btn-sm btn-secondary" id="vm-task-btn" style="font-size:0.75rem; padding:0.2rem 0.5rem;"></button>
                </div>

                <div class="form-group">
                    <label class="form-label" style="font-size:0.8rem; color:var(--text-dim);">Message</label>
                    <div id="vm-content" style="background:var(--bg-surface); padding:1rem; border-radius:var(--radius-md); font-size:0.9rem; color:var(--text-main); line-height:1.5; white-space:pre-wrap; border:1px solid var(--border-color);"></div>
                </div>
            </div>
            <div class="modal-footer" style="display:flex; justify-content:space-between;">
                <button type="button" class="btn btn-secondary" onclick="TAMS.closeModal('view-message-modal')">Close</button>
                <button type="button" class="btn btn-primary" id="vm-reply-btn" onclick="TAMS.replyToCurrentMessage()">
                    <span>↩</span> Reply
                </button>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="assets/js/app.js?v=<?= filemtime(__DIR__ . '/assets/js/app.js') ?>"></script>
    <script>
        function showView(viewName) {
            const tasksView = document.getElementById('view-tasks');
            const usersView = document.getElementById('view-users');
            const navTasks = document.getElementById('nav-btn-tasks');
            const navUsers = document.getElementById('nav-btn-users');

            if (viewName === 'tasks') {
                if (tasksView) tasksView.style.display = 'block';
                if (usersView) usersView.style.display = 'none';
                if (navTasks) navTasks.classList.add('active');
                if (navUsers) navUsers.classList.remove('active');
            } else if (viewName === 'users') {
                if (tasksView) tasksView.style.display = 'none';
                if (usersView) usersView.style.display = 'block';
                if (navTasks) navTasks.classList.remove('active');
                if (navUsers) navUsers.classList.add('active');
                TAMS.loadUsersTable();
            }
        }

        function setFilter(filterType) {
            document.querySelectorAll('.tab-pill').forEach(pill => {
                if (pill.getAttribute('data-filter') === filterType) {
                    pill.classList.add('active');
                } else {
                    pill.classList.remove('active');
                }
            });
            TAMS.currentFilter = filterType;
            TAMS.loadTasks();
        }

        function filterOverdue() {
            setFilter('overdue');
            document.getElementById('tasks-container').scrollIntoView({ behavior: 'smooth' });
        }
    </script>
</body>
</html>
