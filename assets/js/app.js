/**
 * Task Assignment & Monitoring System (TAMS)
 * Frontend Interactive Controller
 */

const TAMS = {
    user: null,
    tasks: [],
    users: [],
    currentFilter: 'all',
    statusFilter: '',
    priorityFilter: '',
    assigneeFilter: '',
    searchQuery: '',
    activeTaskId: null,
    lastNotifId: 0,
    notifPollInterval: null,
    currentViewingSenderId: null,
    currentViewingSenderName: null,

    init() {
        this.bindEvents();
        this.fetchCurrentUser();
    },

    bindEvents() {
        // Search bar
        const searchInput = document.getElementById('search-input');
        if (searchInput) {
            let debounceTimer;
            searchInput.addEventListener('input', (e) => {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => {
                    TAMS.searchQuery = e.target.value.trim();
                    TAMS.loadTasks();
                }, 300);
            });
        }

        // Status & Priority Filters
        const statusSelect = document.getElementById('filter-status');
        if (statusSelect) {
            statusSelect.addEventListener('change', (e) => {
                TAMS.statusFilter = e.target.value;
                TAMS.loadTasks();
            });
        }

        const prioritySelect = document.getElementById('filter-priority');
        if (prioritySelect) {
            prioritySelect.addEventListener('change', (e) => {
                TAMS.priorityFilter = e.target.value;
                TAMS.loadTasks();
            });
        }

        const assigneeSelect = document.getElementById('filter-assignee');
        if (assigneeSelect) {
            assigneeSelect.addEventListener('change', (e) => {
                TAMS.assigneeFilter = e.target.value;
                TAMS.loadTasks();
            });
        }

        // Tab Pill Filters
        document.querySelectorAll('.tab-pill').forEach(pill => {
            pill.addEventListener('click', (e) => {
                document.querySelectorAll('.tab-pill').forEach(p => p.classList.remove('active'));
                const target = e.currentTarget;
                target.classList.add('active');
                TAMS.currentFilter = target.getAttribute('data-filter') || 'all';
                TAMS.loadTasks();
            });
        });

        // Notifications Toggle
        const notifBtn = document.getElementById('notif-btn');
        const notifDropdown = document.getElementById('notif-dropdown');
        if (notifBtn && notifDropdown) {
            notifBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                notifDropdown.classList.toggle('show');
            });

            document.addEventListener('click', () => {
                notifDropdown.classList.remove('show');
            });

            notifDropdown.addEventListener('click', (e) => {
                e.stopPropagation();
            });
        }

        // Close modal on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                TAMS.closeAllModals();
            }
        });
    },

    async fetchCurrentUser() {
        try {
            const res = await fetch('api/auth.php?action=me');
            const data = await res.json();
            if (data.success) {
                this.user = data.user;
                this.updateUserUI();
                await this.loadAssigneesDropdown();
                await this.refreshAll();
                this.startNotificationPolling();
                this.requestNotificationPermission();
            } else {
                window.location.href = 'login.php';
            }
        } catch (err) {
            console.error('Failed to load user', err);
        }
    },

    updateUserUI() {
        const nameEl = document.getElementById('header-user-name');
        const roleEl = document.getElementById('header-user-role');
        const avatarEl = document.getElementById('header-user-avatar');

        if (nameEl) nameEl.textContent = this.user.name;
        if (roleEl) roleEl.textContent = this.formatRole(this.user.role);
        if (avatarEl) {
            avatarEl.textContent = this.user.name.charAt(0).toUpperCase();
            if (this.user.avatar_color) {
                avatarEl.style.backgroundColor = this.user.avatar_color;
            }
        }

        // Show/hide role-specific elements
        const adminElements = document.querySelectorAll('.role-admin-only');
        adminElements.forEach(el => {
            el.style.display = this.user.role === 'admin' ? '' : 'none';
        });

        const leaderElements = document.querySelectorAll('.role-leader-or-admin');
        leaderElements.forEach(el => {
            el.style.display = (this.user.role === 'admin' || this.user.role === 'team_leader') ? '' : 'none';
        });

        // Highlight active demo button if present
        document.querySelectorAll('.demo-btn').forEach(btn => {
            if (btn.getAttribute('data-role') === this.user.role) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });
    },

    formatRole(role) {
        if (!role) return '';
        if (role === 'team_leader') return 'Team Leader';
        if (role === 'team_member') return 'Team Member';
        if (role === 'admin') return 'Administrator';
        return role.charAt(0).toUpperCase() + role.slice(1);
    },

    async refreshAll() {
        await Promise.all([
            this.loadDashboard(),
            this.loadTasks(),
            this.loadNotifications()
        ]);
        if (this.user.role === 'admin') {
            this.loadUsersTable();
        }
    },

    async loadDashboard() {
        try {
            const res = await fetch('api/dashboard.php');
            const data = await res.json();
            if (!data.success) return;

            const m = data.metrics;
            this.setElText('stat-total', m.total);
            this.setElText('stat-pending', m.pending);
            this.setElText('stat-completed', m.completed);
            this.setElText('stat-overdue', m.overdue);
            this.setElText('stat-approaching', m.approaching);

            // Tab counts
            this.setElText('count-all', m.total);
            this.setElText('count-pending', m.pending);
            this.setElText('count-completed', m.completed);
            this.setElText('count-overdue', m.overdue);
            this.setElText('count-approaching', m.approaching);

            // Overdue banner
            const overdueBanner = document.getElementById('overdue-banner');
            if (overdueBanner) {
                if (m.overdue > 0) {
                    overdueBanner.style.display = 'flex';
                    const msgEl = document.getElementById('overdue-banner-text');
                    if (msgEl) {
                        msgEl.innerHTML = `<strong>Attention Required:</strong> You have <strong>${m.overdue} overdue task(s)</strong> that missed their deadline. Check and update their progress.`;
                    }
                } else {
                    overdueBanner.style.display = 'none';
                }
            }

            // Render Activity Stream if element exists
            const actContainer = document.getElementById('recent-activities-list');
            if (actContainer && data.recent_activities) {
                this.renderRecentActivities(data.recent_activities, actContainer);
            }
        } catch (err) {
            console.error('Error loading dashboard', err);
        }
    },

    renderRecentActivities(activities, container) {
        if (!activities || activities.length === 0) {
            container.innerHTML = `<p class="text-dim" style="font-size:0.85rem; padding: 1rem 0;">No recent activity recorded.</p>`;
            return;
        }

        container.innerHTML = activities.map(a => `
            <div style="display:flex; gap:0.75rem; align-items:flex-start; margin-bottom:0.85rem; padding-bottom:0.75rem; border-bottom:1px solid rgba(255,255,255,0.04);">
                <div class="avatar" style="width:26px; height:26px; font-size:0.7rem; background:${a.actor_color || '#4f46e5'}; flex-shrink:0;">
                    ${a.actor_name.charAt(0)}
                </div>
                <div style="font-size:0.825rem; flex:1;">
                    <div><strong style="color:#fff;">${this.escapeHtml(a.actor_name)}</strong> <span style="color:var(--text-dim); font-size:0.75rem;">(${this.formatRole(a.actor_role)})</span></div>
                    <div style="color:var(--text-muted); margin-top:2px;">${this.escapeHtml(a.description)}</div>
                    <div style="color:var(--text-dim); font-size:0.7rem; margin-top:2px;">${a.time_ago}</div>
                </div>
            </div>
        `).join('');
    },

    async loadAssigneesDropdown() {
        try {
            const res = await fetch('api/users.php?action=assignees');
            const data = await res.json();
            if (data.success) {
                this.users = data.users;
                
                // Populate task create modal dropdown
                const createSelect = document.getElementById('task-create-assignee');
                if (createSelect) {
                    createSelect.innerHTML = '<option value="">-- Unassigned --</option>' +
                        this.users.map(u => `<option value="${u.id}">${this.escapeHtml(u.name)} (${this.formatRole(u.role)})</option>`).join('');
                }

                // Populate task edit modal dropdown
                const editSelect = document.getElementById('task-edit-assignee');
                if (editSelect) {
                    editSelect.innerHTML = '<option value="">-- Unassigned --</option>' +
                        this.users.map(u => `<option value="${u.id}">${this.escapeHtml(u.name)} (${this.formatRole(u.role)})</option>`).join('');
                }

                // Populate filter assignee dropdown
                const filterSelect = document.getElementById('filter-assignee');
                if (filterSelect) {
                    filterSelect.innerHTML = '<option value="">All Team Members</option>' +
                        this.users.map(u => `<option value="${u.id}">${this.escapeHtml(u.name)}</option>`).join('');
                }
            }
        } catch (err) {
            console.error('Error loading assignees', err);
        }
    },

    async loadTasks() {
        const container = document.getElementById('tasks-container');
        if (!container) return;

        container.innerHTML = `
            <div style="grid-column: 1 / -1; text-align: center; padding: 3rem 0; color: var(--text-muted);">
                <div style="font-size: 1.5rem; margin-bottom: 0.5rem;">⚡</div>
                Loading tasks...
            </div>
        `;

        try {
            let url = `api/tasks.php?action=list&condition=${this.currentFilter}`;
            if (this.statusFilter) url += `&status=${encodeURIComponent(this.statusFilter)}`;
            if (this.priorityFilter) url += `&priority=${encodeURIComponent(this.priorityFilter)}`;
            if (this.assigneeFilter) url += `&assigned_to=${encodeURIComponent(this.assigneeFilter)}`;
            if (this.searchQuery) url += `&search=${encodeURIComponent(this.searchQuery)}`;

            const res = await fetch(url);
            const data = await res.json();
            if (!data.success) {
                container.innerHTML = `<div class="empty-state"><p class="empty-desc">Error loading tasks: ${data.error}</p></div>`;
                return;
            }

            this.tasks = data.tasks;
            this.renderTasks(this.tasks, container);
        } catch (err) {
            console.error('Failed to load tasks', err);
            container.innerHTML = `<div class="empty-state"><p class="empty-desc">Unable to connect to tasks server.</p></div>`;
        }
    },

    renderTasks(tasks, container) {
        if (!tasks || tasks.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <div class="empty-icon">📂</div>
                    <h3 class="empty-title">No tasks found</h3>
                    <p class="empty-desc">There are no tasks matching the current filter criteria or assigned to your view.</p>
                    ${(this.user.role === 'admin' || this.user.role === 'team_leader') ? `
                        <button class="btn btn-primary" onclick="TAMS.openCreateTaskModal()">
                            <span>+</span> Create New Task
                        </button>
                    ` : ''}
                </div>
            `;
            return;
        }

        container.innerHTML = tasks.map(task => {
            const isOverdue = task.is_overdue;
            const isApproaching = task.is_approaching;
            const isCompleted = task.status === 'Completed';

            let cardExtraClass = '';
            if (isOverdue) cardExtraClass = 'is-overdue';
            else if (isApproaching) cardExtraClass = 'is-approaching';

            const statusClass = 'badge-status-' + task.status.replace(/\s+/g, '-');
            const prioClass = 'badge-prio-' + task.priority;

            const canEdit = (this.user.role === 'admin' || this.user.role === 'team_leader');
            const canUpdateStatus = (this.user.role === 'admin' || this.user.role === 'team_leader' || (this.user.role === 'team_member' && task.assigned_to == this.user.id));

            return `
                <div class="task-card ${cardExtraClass}" id="task-card-${task.id}">
                    <div>
                        <div class="task-card-header">
                            <div class="task-badges">
                                <span class="badge ${prioClass}">
                                    ${task.priority === 'Urgent' ? '🔥' : ''} ${task.priority}
                                </span>
                                <span class="badge ${statusClass}">
                                    ${task.status === 'Completed' ? '✓' : '●'} ${task.status}
                                </span>
                                ${isOverdue ? `
                                    <span class="badge badge-overdue">
                                        ⚠️ ${task.deadline_badge}
                                    </span>
                                ` : ''}
                                ${isApproaching ? `
                                    <span class="badge badge-approaching">
                                        ⏳ ${task.deadline_badge}
                                    </span>
                                ` : ''}
                            </div>
                            ${canEdit ? `
                                <div style="display:flex; gap:0.25rem;">
                                    <button class="btn btn-secondary btn-sm" title="Edit Task" onclick="TAMS.openEditTaskModal(${task.id})">
                                        ✏️
                                    </button>
                                    ${this.user.role === 'admin' ? `
                                        <button class="btn btn-danger btn-sm" title="Delete Task" onclick="TAMS.confirmDeleteTask(${task.id})">
                                            🗑️
                                        </button>
                                    ` : ''}
                                </div>
                            ` : ''}
                        </div>

                        <h3 class="task-title" onclick="TAMS.viewTaskDetails(${task.id})">
                            ${this.escapeHtml(task.title)}
                        </h3>

                        <p class="task-desc">
                            ${this.escapeHtml(task.description || 'No description provided.')}
                        </p>
                    </div>

                    <div>
                        <div class="task-footer">
                            <div class="task-assignee">
                                <div class="avatar" style="width:24px; height:24px; font-size:0.65rem; background:${task.assignee_avatar_color || '#64748b'};">
                                    ${task.assignee_name ? task.assignee_name.charAt(0).toUpperCase() : '?'}
                                </div>
                                <span class="assignee-name" title="${task.assignee_role ? this.formatRole(task.assignee_role) : ''}">
                                    ${this.escapeHtml(task.assignee_name || 'Unassigned')}
                                </span>
                            </div>

                            <div class="deadline-indicator ${task.deadline_badge_type || ''}">
                                <span>📅</span>
                                <span>${task.deadline_formatted}</span>
                            </div>
                        </div>

                        <!-- Quick status updater buttons -->
                        ${canUpdateStatus ? `
                            <div class="task-actions">
                                <button class="btn btn-secondary btn-sm" onclick="TAMS.viewTaskDetails(${task.id})" style="flex:1;">
                                    👁️ Details & History
                                </button>
                                
                                ${task.status === 'To Do' ? `
                                    <button class="btn btn-primary btn-sm" onclick="TAMS.updateTaskStatus(${task.id}, 'In Progress')">
                                        ▶ Start
                                    </button>
                                ` : ''}

                                ${task.status === 'In Progress' ? `
                                    <button class="btn btn-success btn-sm" onclick="TAMS.updateTaskStatus(${task.id}, 'Completed')">
                                        ✓ Complete
                                    </button>
                                ` : ''}

                                ${task.status === 'Completed' ? `
                                    <button class="btn btn-secondary btn-sm" style="color:var(--text-dim);" onclick="TAMS.updateTaskStatus(${task.id}, 'In Progress')">
                                        ↺ Reopen
                                    </button>
                                ` : ''}
                            </div>
                        ` : `
                            <div class="task-actions">
                                <button class="btn btn-secondary btn-sm" onclick="TAMS.viewTaskDetails(${task.id})" style="width:100%;">
                                    👁️ View Details & History
                                </button>
                            </div>
                        `}
                    </div>
                </div>
            `;
        }).join('');
    },

    async viewTaskDetails(taskId) {
        this.activeTaskId = taskId;
        const modal = document.getElementById('task-detail-modal');
        if (!modal) return;

        this.openModal('task-detail-modal');

        const titleEl = document.getElementById('td-title');
        const descEl = document.getElementById('td-description');
        const prioEl = document.getElementById('td-priority');
        const statusEl = document.getElementById('td-status');
        const deadlineEl = document.getElementById('td-deadline');
        const assigneeEl = document.getElementById('td-assignee');
        const creatorEl = document.getElementById('td-creator');
        const timelineEl = document.getElementById('td-timeline');
        const statusActionsEl = document.getElementById('td-status-actions');

        titleEl.textContent = 'Loading details...';
        timelineEl.innerHTML = '<div style="color:var(--text-dim); padding:1rem 0;">Fetching audit trail...</div>';

        try {
            // Fetch task info
            const taskRes = await fetch(`api/tasks.php?action=get&id=${taskId}`);
            const taskData = await taskRes.json();
            if (!taskData.success) {
                this.showToast(taskData.error, 'error');
                return;
            }
            const task = taskData.task;

            titleEl.textContent = task.title;
            descEl.textContent = task.description || 'No description provided.';
            
            prioEl.className = 'badge badge-prio-' + task.priority;
            prioEl.textContent = task.priority;

            statusEl.className = 'badge badge-status-' + task.status.replace(/\s+/g, '-');
            statusEl.textContent = task.status;

            deadlineEl.textContent = task.deadline;
            if (task.is_overdue) {
                deadlineEl.innerHTML += ' <span class="badge badge-overdue" style="margin-left:6px;">⚠️ OVERDUE</span>';
            } else if (task.is_approaching) {
                deadlineEl.innerHTML += ' <span class="badge badge-approaching" style="margin-left:6px;">⏳ DUE SOON</span>';
            }

            assigneeEl.textContent = task.assignee_name || 'Unassigned';
            creatorEl.textContent = task.creator_name || 'System';

            // Status Actions for current user
            const canUpdateStatus = (this.user.role === 'admin' || this.user.role === 'team_leader' || (this.user.role === 'team_member' && task.assigned_to == this.user.id));
            if (canUpdateStatus && statusActionsEl) {
                statusActionsEl.innerHTML = `
                    <div style="display:flex; align-items:center; gap:0.5rem; flex-wrap:wrap; margin-top:0.75rem;">
                        <span style="font-size:0.8rem; color:var(--text-muted); font-weight:600;">Update Status:</span>
                        <button class="btn btn-sm ${task.status === 'To Do' ? 'btn-primary' : 'btn-secondary'}" 
                                onclick="TAMS.updateTaskStatus(${task.id}, 'To Do', true)">
                            To Do
                        </button>
                        <button class="btn btn-sm ${task.status === 'In Progress' ? 'btn-primary' : 'btn-secondary'}" 
                                onclick="TAMS.updateTaskStatus(${task.id}, 'In Progress', true)">
                            In Progress
                        </button>
                        <button class="btn btn-sm ${task.status === 'Completed' ? 'btn-success' : 'btn-secondary'}" 
                                onclick="TAMS.updateTaskStatus(${task.id}, 'Completed', true)">
                            ✓ Mark Completed
                        </button>
                    </div>
                `;
            } else if (statusActionsEl) {
                statusActionsEl.innerHTML = '';
            }

            // Fetch Audit Trail History
            const histRes = await fetch(`api/task_history.php?task_id=${taskId}`);
            const histData = await histRes.json();
            if (histData.success && histData.history) {
                this.renderTimeline(histData.history, timelineEl);
            } else {
                timelineEl.innerHTML = '<p class="text-dim">No history recorded.</p>';
            }

            // Default to audit tab & load messages
            this.switchTaskModalTab('audit');
            await this.loadTaskMessages(taskId);
        } catch (err) {
            console.error('Error fetching task details', err);
            titleEl.textContent = 'Error loading task';
        }
    },

    renderTimeline(history, container) {
        if (!history || history.length === 0) {
            container.innerHTML = '<p class="text-dim" style="font-size:0.85rem;">No audit logs yet.</p>';
            return;
        }

        container.innerHTML = history.map(item => {
            let icon = '•';
            if (item.action_type === 'created') icon = '✚';
            else if (item.action_type === 'assigned' || item.action_type === 'reassigned') icon = '👤';
            else if (item.action_type === 'completed') icon = '✓';
            else if (item.action_type === 'status_change') icon = '🔄';
            else if (item.action_type === 'details_updated') icon = '✏️';
            else if (item.action_type === 'message') icon = '💬';

            return `
                <div class="timeline-item">
                    <div class="timeline-dot">${icon}</div>
                    <div class="timeline-content">
                        <div class="timeline-header">
                            <span class="timeline-actor">
                                ${this.escapeHtml(item.actor_name)} 
                                <span style="color:var(--text-dim); font-size:0.7rem; font-weight:normal;">(${this.formatRole(item.actor_role)})</span>
                            </span>
                            <span class="timeline-time">${item.created_at_formatted}</span>
                        </div>
                        <div class="timeline-desc">
                            ${this.escapeHtml(item.description)}
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    },

    async updateTaskStatus(taskId, newStatus, refreshModal = false) {
        try {
            const res = await fetch('api/tasks.php?action=update_status', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: taskId, status: newStatus })
            });
            const data = await res.json();
            if (data.success) {
                this.showToast(`Task status updated to ${newStatus}`, 'success');
                await this.refreshAll();
                if (refreshModal && this.activeTaskId === taskId) {
                    await this.viewTaskDetails(taskId);
                }
            } else {
                this.showToast(data.error || 'Failed to update status', 'error');
            }
        } catch (err) {
            console.error('Update status error', err);
            this.showToast('Network error while updating task', 'error');
        }
    },

    openCreateTaskModal() {
        const form = document.getElementById('task-create-form');
        if (form) form.reset();

        // Default deadline: tomorrow at 17:00
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        tomorrow.setHours(17, 0, 0, 0);

        // Format for datetime-local: YYYY-MM-DDTHH:mm
        const yyyy = tomorrow.getFullYear();
        const mm = String(tomorrow.getMonth() + 1).padStart(2, '0');
        const dd = String(tomorrow.getDate()).padStart(2, '0');
        const hh = String(tomorrow.getHours()).padStart(2, '0');
        const min = String(tomorrow.getMinutes()).padStart(2, '0');
        
        const deadlineInput = document.getElementById('task-create-deadline');
        if (deadlineInput) {
            deadlineInput.value = `${yyyy}-${mm}-${dd}T${hh}:${min}`;
        }

        this.openModal('task-create-modal');
    },

    async submitCreateTask(e) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        const payload = Object.fromEntries(formData.entries());

        try {
            const res = await fetch('api/tasks.php?action=create', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (data.success) {
                this.showToast('Task created and assigned successfully!', 'success');
                this.closeModal('task-create-modal');
                form.reset();
                await this.refreshAll();
            } else {
                this.showToast(data.error || 'Failed to create task', 'error');
            }
        } catch (err) {
            console.error('Create task error', err);
            this.showToast('Network error while creating task', 'error');
        }
    },

    async openEditTaskModal(taskId) {
        this.activeTaskId = taskId;
        try {
            const res = await fetch(`api/tasks.php?action=get&id=${taskId}`);
            const data = await res.json();
            if (!data.success) {
                this.showToast(data.error, 'error');
                return;
            }
            const task = data.task;

            document.getElementById('task-edit-id').value = task.id;
            document.getElementById('task-edit-title').value = task.title;
            document.getElementById('task-edit-description').value = task.description || '';
            document.getElementById('task-edit-priority').value = task.priority;
            document.getElementById('task-edit-assignee').value = task.assigned_to || '';

            // Convert SQL datetime to datetime-local
            if (task.deadline) {
                const dt = new Date(task.deadline);
                const yyyy = dt.getFullYear();
                const mm = String(dt.getMonth() + 1).padStart(2, '0');
                const dd = String(dt.getDate()).padStart(2, '0');
                const hh = String(dt.getHours()).padStart(2, '0');
                const min = String(dt.getMinutes()).padStart(2, '0');
                document.getElementById('task-edit-deadline').value = `${yyyy}-${mm}-${dd}T${hh}:${min}`;
            }

            this.openModal('task-edit-modal');
        } catch (err) {
            console.error('Error opening edit modal', err);
            this.showToast('Failed to load task for editing', 'error');
        }
    },

    async submitEditTask(e) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        const payload = Object.fromEntries(formData.entries());

        try {
            const res = await fetch('api/tasks.php?action=update_details', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (data.success) {
                this.showToast('Task details updated and audit recorded!', 'success');
                this.closeModal('task-edit-modal');
                await this.refreshAll();
            } else {
                this.showToast(data.error || 'Failed to update task', 'error');
            }
        } catch (err) {
            console.error('Edit task error', err);
            this.showToast('Network error while updating task', 'error');
        }
    },

    confirmDeleteTask(taskId) {
        if (confirm('Are you sure you want to permanently delete this task and its history?')) {
            this.deleteTask(taskId);
        }
    },

    async deleteTask(taskId) {
        try {
            const res = await fetch('api/tasks.php?action=delete', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: taskId })
            });
            const data = await res.json();
            if (data.success) {
                this.showToast('Task deleted successfully', 'success');
                await this.refreshAll();
            } else {
                this.showToast(data.error || 'Failed to delete task', 'error');
            }
        } catch (err) {
            console.error('Delete task error', err);
            this.showToast('Network error while deleting task', 'error');
        }
    },

    // User Management for Administrator
    async loadUsersTable() {
        const tbody = document.getElementById('users-table-body');
        if (!tbody) return;

        try {
            const res = await fetch('api/users.php?action=list');
            const data = await res.json();
            if (!data.success) return;

            const users = data.users;
            tbody.innerHTML = users.map(u => `
                <tr>
                    <td>
                        <div style="display:flex; align-items:center; gap:0.65rem;">
                            <div class="avatar" style="width:30px; height:30px; font-size:0.75rem; background:${u.avatar_color || '#4f46e5'};">
                                ${u.name.charAt(0).toUpperCase()}
                            </div>
                            <div>
                                <div style="font-weight:600; color:#fff;">${this.escapeHtml(u.name)}</div>
                                <div style="font-size:0.75rem; color:var(--text-dim);">@${this.escapeHtml(u.username)}</div>
                            </div>
                        </div>
                    </td>
                    <td>${this.escapeHtml(u.email)}</td>
                    <td>
                        <span class="role-pill ${u.role}">
                            ${this.formatRole(u.role)}
                        </span>
                    </td>
                    <td>
                        <span class="badge ${u.status === 'active' ? 'badge-status-Completed' : 'badge-prio-Urgent'}">
                            ${u.status === 'active' ? 'Active' : 'Inactive'}
                        </span>
                    </td>
                    <td>
                        <span style="font-weight:600; color:#fff;">${u.completed_tasks_count || 0}</span> / 
                        <span style="color:var(--text-dim);">${u.assigned_tasks_count || 0} tasks</span>
                    </td>
                    <td>
                        <div style="display:flex; gap:0.4rem;">
                            <button class="btn btn-secondary btn-sm" onclick="TAMS.openSendMessageModal(${u.id}, '${this.escapeHtml(u.name)}')" title="Send Message Notification to ${this.escapeHtml(u.name)}">
                                ✉️ Message
                            </button>
                            <button class="btn btn-secondary btn-sm" onclick="TAMS.openEditUserModal(${JSON.stringify(u).replace(/"/g, '&quot;')})">
                                Edit
                            </button>
                            ${u.id != this.user.id ? `
                                <button class="btn ${u.status === 'active' ? 'btn-danger' : 'btn-success'} btn-sm" onclick="TAMS.toggleUserStatus(${u.id})">
                                    ${u.status === 'active' ? 'Deactivate' : 'Activate'}
                                </button>
                            ` : ''}
                        </div>
                    </td>
                </tr>
            `).join('');
        } catch (err) {
            console.error('Error loading users table', err);
        }
    },

    openCreateUserModal() {
        const form = document.getElementById('user-create-form');
        if (form) form.reset();
        this.openModal('user-create-modal');
    },

    async submitCreateUser(e) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        const payload = Object.fromEntries(formData.entries());

        try {
            const res = await fetch('api/users.php?action=create', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (data.success) {
                this.showToast('User created successfully!', 'success');
                this.closeModal('user-create-modal');
                form.reset();
                await this.loadUsersTable();
                await this.loadAssigneesDropdown();
            } else {
                this.showToast(data.error || 'Failed to create user', 'error');
            }
        } catch (err) {
            console.error('Create user error', err);
            this.showToast('Network error while creating user', 'error');
        }
    },

    openEditUserModal(user) {
        document.getElementById('user-edit-id').value = user.id;
        document.getElementById('user-edit-name').value = user.name;
        document.getElementById('user-edit-email').value = user.email;
        document.getElementById('user-edit-role').value = user.role;
        document.getElementById('user-edit-status').value = user.status;
        document.getElementById('user-edit-password').value = '';
        this.openModal('user-edit-modal');
    },

    async submitEditUser(e) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        const payload = Object.fromEntries(formData.entries());

        try {
            const res = await fetch('api/users.php?action=update', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (data.success) {
                this.showToast('User updated successfully!', 'success');
                this.closeModal('user-edit-modal');
                await this.loadUsersTable();
                await this.loadAssigneesDropdown();
            } else {
                this.showToast(data.error || 'Failed to update user', 'error');
            }
        } catch (err) {
            console.error('Edit user error', err);
            this.showToast('Network error while updating user', 'error');
        }
    },

    async toggleUserStatus(userId) {
        try {
            const res = await fetch('api/users.php?action=toggle_status', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: userId })
            });
            const data = await res.json();
            if (data.success) {
                this.showToast(data.message, 'success');
                await this.loadUsersTable();
                await this.loadAssigneesDropdown();
            } else {
                this.showToast(data.error || 'Failed to toggle status', 'error');
            }
        } catch (err) {
            console.error('Toggle status error', err);
            this.showToast('Network error while toggling status', 'error');
        }
    },

    // ==================== IN-APP NOTIFICATIONS & REAL-TIME POLLING ====================

    startNotificationPolling() {
        if (this.notifPollInterval) {
            clearInterval(this.notifPollInterval);
        }

        // Poll every 8 seconds for real-time notifications
        this.notifPollInterval = setInterval(async () => {
            if (!this.user) return;
            try {
                const res = await fetch(`api/notifications.php?action=check_new&last_id=${this.lastNotifId || 0}`);
                const data = await res.json();
                if (!data.success) return;

                if (data.latest_id > (this.lastNotifId || 0)) {
                    this.lastNotifId = data.latest_id;
                }

                if (data.new_notifications && data.new_notifications.length > 0) {
                    // Update bell badge
                    const badge = document.getElementById('notif-badge');
                    if (badge) {
                        badge.textContent = data.unread_count > 99 ? '99+' : data.unread_count;
                        badge.style.display = data.unread_count > 0 ? 'flex' : 'none';
                    }

                    // Bell animation
                    const notifBtn = document.getElementById('notif-btn');
                    if (notifBtn) {
                        notifBtn.classList.add('ringing');
                        setTimeout(() => notifBtn.classList.remove('ringing'), 1000);
                    }

                    // Audio chime
                    this.playNotificationChime();

                    // Show toast popup and browser notification for each new notification
                    data.new_notifications.forEach(n => {
                        this.showToastNotification(n);
                        this.showBrowserNotification(n);
                    });

                    // Refresh notifications list and metrics
                    await this.loadNotifications();
                    await this.loadDashboard();
                    if (this.activeTaskId) {
                        await this.loadTaskMessages(this.activeTaskId);
                    }
                }
            } catch (err) {
                // Silently ignore background poll errors
            }
        }, 8000);
    },

    playNotificationChime() {
        try {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (!AudioCtx) return;
            const ctx = new AudioCtx();
            const now = ctx.currentTime;

            const osc = ctx.createOscillator();
            const gain = ctx.createGain();

            osc.type = 'sine';
            osc.frequency.setValueAtTime(587.33, now); // D5
            osc.frequency.setValueAtTime(880.00, now + 0.12); // A5

            gain.gain.setValueAtTime(0, now);
            gain.gain.linearRampToValueAtTime(0.15, now + 0.03);
            gain.gain.exponentialRampToValueAtTime(0.001, now + 0.45);

            osc.connect(gain);
            gain.connect(ctx.destination);

            osc.start(now);
            osc.stop(now + 0.45);
        } catch (e) {
            // Audio context policy
        }
    },

    requestNotificationPermission() {
        if ('Notification' in window && Notification.permission === 'default') {
            try {
                Notification.requestPermission();
            } catch (e) {}
        }
    },

    showBrowserNotification(n) {
        if ('Notification' in window && Notification.permission === 'granted') {
            try {
                const bNotif = new Notification(n.title, {
                    body: n.message,
                    icon: 'favicon.ico'
                });
                bNotif.onclick = () => {
                    window.focus();
                    this.handleNotificationClick(n.id, n.task_id || null, n.type || 'general');
                };
            } catch (e) {}
        }
    },

    showToastNotification(notif) {
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            container.className = 'toast-container';
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        toast.className = 'toast toast-message';

        const isMsg = notif.type === 'message' || (notif.title && notif.title.includes('Message'));
        const icon = isMsg ? '💬' : (notif.title && notif.title.includes('Completed') ? '✓' : '🔔');

        toast.innerHTML = `
            <div style="font-size:1.25rem; flex-shrink:0;">${icon}</div>
            <div style="flex:1; min-width:0;">
                <div class="toast-msg-title">
                    <span>${this.escapeHtml(notif.title)}</span>
                </div>
                <div class="toast-msg-text">${this.escapeHtml(notif.message)}</div>
            </div>
            <button class="btn btn-sm btn-primary" style="font-size:0.7rem; padding:0.25rem 0.6rem; flex-shrink:0;">
                View
            </button>
        `;

        toast.onclick = () => {
            this.handleNotificationClick(notif.id, notif.task_id || null, notif.type || 'general');
            toast.remove();
        };

        container.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(10px)';
            toast.style.transition = 'all 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 7000);
    },

    async loadNotifications() {
        try {
            const res = await fetch('api/notifications.php?action=list');
            const data = await res.json();
            if (!data.success) return;

            this.notifications = data.notifications || [];

            const badge = document.getElementById('notif-badge');
            const list = document.getElementById('notif-list');
            const unreadTag = document.getElementById('notif-unread-tag');

            if (badge) {
                if (data.unread_count > 0) {
                    badge.textContent = data.unread_count > 99 ? '99+' : data.unread_count;
                    badge.style.display = 'flex';
                } else {
                    badge.style.display = 'none';
                }
            }

            if (unreadTag) {
                if (data.unread_count > 0) {
                    unreadTag.textContent = `${data.unread_count} unread`;
                    unreadTag.style.display = 'inline-block';
                } else {
                    unreadTag.style.display = 'none';
                }
            }

            if (this.notifications.length > 0) {
                const maxId = Math.max(...this.notifications.map(n => parseInt(n.id) || 0));
                if (maxId > this.lastNotifId) {
                    this.lastNotifId = maxId;
                }
            }

            if (list) {
                if (!this.notifications || this.notifications.length === 0) {
                    list.innerHTML = `<div style="padding:1.5rem; text-align:center; color:var(--text-dim); font-size:0.85rem;">No notifications yet.</div>`;
                    return;
                }

                list.innerHTML = this.notifications.map(n => {
                    let typeClass = 'notif-type-general';
                    let icon = '🔔';
                    if (n.type === 'message' || (n.title && n.title.includes('Message'))) {
                        typeClass = 'notif-type-message';
                        icon = '💬';
                    } else if (n.title && n.title.includes('Completed')) {
                        typeClass = 'notif-type-completed';
                        icon = '✓';
                    } else if (n.title && n.title.includes('Assigned')) {
                        typeClass = 'notif-type-task';
                        icon = '📋';
                    } else if (n.title && (n.title.includes('Overdue') || n.title.includes('Warning') || n.title.includes('Approaching'))) {
                        typeClass = 'notif-type-warning';
                        icon = '⚠️';
                    }

                    const senderAvatar = n.sender_name ? `
                        <div class="avatar" style="width:28px; height:28px; font-size:0.7rem; background:${n.sender_avatar_color || '#8b5cf6'}; flex-shrink:0;">
                            ${n.sender_name.charAt(0).toUpperCase()}
                        </div>
                    ` : `
                        <div class="notif-icon-box">
                            ${icon}
                        </div>
                    `;

                    return `
                        <div class="notif-item ${n.is_read ? '' : 'unread'} ${typeClass}" onclick="TAMS.handleNotificationClick(${n.id})">
                            ${senderAvatar}
                            <div class="notif-item-body">
                                <div class="notif-item-title">
                                    <span>${this.escapeHtml(n.title)}</span>
                                    ${!n.is_read ? '<span style="width:6px; height:6px; border-radius:50%; background:var(--primary); display:inline-block;"></span>' : ''}
                                </div>
                                <div class="notif-item-text">${this.escapeHtml(n.message)}</div>
                                <div class="notif-item-meta">
                                    <span>${n.created_at_formatted}</span>
                                    ${n.task_title ? `<span style="color:var(--text-muted);">• ${this.escapeHtml(n.task_title)}</span>` : ''}
                                </div>
                            </div>
                        </div>
                    `;
                }).join('');
            }
        } catch (err) {
            console.error('Error loading notifications', err);
        }
    },

    async handleNotificationClick(notifId) {
        try {
            // Close dropdown immediately
            const dropdown = document.getElementById('notif-dropdown');
            if (dropdown) dropdown.classList.remove('show');

            // Find notification in cache
            let notif = (this.notifications || []).find(n => n.id == notifId);

            // Mark as read in background without blocking UI
            fetch('api/notifications.php?action=mark_read', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: notifId })
            }).then(() => this.loadNotifications());

            if (!notif) {
                const res = await fetch('api/notifications.php?action=list');
                const data = await res.json();
                if (data.success && data.notifications) {
                    this.notifications = data.notifications;
                    notif = this.notifications.find(n => n.id == notifId);
                }
            }

            if (!notif) return;

            // If message notification or no task_id, open message notification modal
            if (notif.type === 'message' || !notif.task_id) {
                this.openNotificationModal(notif);
            } else {
                // Task update notification with task_id
                try {
                    await this.viewTaskDetails(notif.task_id);
                } catch (e) {
                    this.openNotificationModal(notif);
                }
            }
        } catch (err) {
            console.error('Failed to handle notification click', err);
        }
    },

    openNotificationModal(notif) {
        if (!notif) return;
        this.currentViewingSenderId = notif.sender_id || null;
        this.currentViewingSenderName = notif.sender_name || 'Sender';

        const titleEl = document.getElementById('vm-title');
        const dateEl = document.getElementById('vm-date');
        const senderNameEl = document.getElementById('vm-sender-name');
        const senderRoleEl = document.getElementById('vm-sender-role');
        const senderAvatarEl = document.getElementById('vm-sender-avatar');
        const contentEl = document.getElementById('vm-content');
        const taskBoxEl = document.getElementById('vm-task-box');
        const taskBtnEl = document.getElementById('vm-task-btn');
        const replyBtn = document.getElementById('vm-reply-btn');

        if (titleEl) titleEl.textContent = notif.title || 'Message Notification';
        if (dateEl) dateEl.textContent = notif.created_at_formatted || 'Just now';
        if (senderNameEl) senderNameEl.textContent = notif.sender_name || 'System Notification';
        if (senderRoleEl) senderRoleEl.textContent = notif.sender_role ? this.formatRole(notif.sender_role) : 'System';
        if (senderAvatarEl) {
            senderAvatarEl.textContent = notif.sender_name ? notif.sender_name.charAt(0).toUpperCase() : '💬';
            senderAvatarEl.style.backgroundColor = notif.sender_avatar_color || '#8b5cf6';
        }
        if (contentEl) contentEl.textContent = notif.message;

        if (notif.task_id && taskBoxEl && taskBtnEl) {
            taskBoxEl.style.display = 'block';
            taskBtnEl.textContent = notif.task_title ? `📋 #${notif.task_id}: ${notif.task_title}` : `Open Task #${notif.task_id}`;
            taskBtnEl.onclick = () => {
                this.closeModal('view-message-modal');
                this.viewTaskDetails(notif.task_id);
            };
        } else if (taskBoxEl) {
            taskBoxEl.style.display = 'none';
        }

        if (replyBtn) {
            replyBtn.style.display = notif.sender_id ? 'inline-flex' : 'none';
        }

        this.openModal('view-message-modal');
    },

    async markNotificationRead(notifId, taskId = null) {
        try {
            await fetch('api/notifications.php?action=mark_read', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: notifId })
            });
            await this.loadNotifications();
            if (taskId) {
                this.viewTaskDetails(taskId);
            }
        } catch (err) {
            console.error('Failed to mark notification read', err);
        }
    },

    async markAllNotificationsRead() {
        try {
            await fetch('api/notifications.php?action=mark_read', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({})
            });
            await this.loadNotifications();
            this.showToast('All notifications marked as read', 'info');
        } catch (err) {
            console.error('Error marking all read', err);
        }
    },

    // ==================== TASK MESSAGES & DISCUSSION ====================

    switchTaskModalTab(tab) {
        const auditBtn = document.getElementById('tab-btn-audit');
        const msgBtn = document.getElementById('tab-btn-messages');
        const auditPane = document.getElementById('tab-pane-audit');
        const msgPane = document.getElementById('tab-pane-messages');

        if (tab === 'audit') {
            if (auditBtn) auditBtn.classList.add('active');
            if (msgBtn) msgBtn.classList.remove('active');
            if (auditPane) auditPane.classList.add('active');
            if (msgPane) msgPane.classList.remove('active');
        } else {
            if (msgBtn) msgBtn.classList.add('active');
            if (auditBtn) auditBtn.classList.remove('active');
            if (msgPane) msgPane.classList.add('active');
            if (auditPane) auditPane.classList.remove('active');

            if (this.activeTaskId) {
                this.loadTaskMessages(this.activeTaskId);
            }
            const input = document.getElementById('task-message-input');
            if (input) setTimeout(() => input.focus(), 150);
        }
    },

    async loadTaskMessages(taskId) {
        const listEl = document.getElementById('td-messages-list');
        const countEl = document.getElementById('td-messages-count');
        if (!listEl) return;

        try {
            const res = await fetch(`api/notifications.php?action=task_messages&task_id=${taskId}`);
            const data = await res.json();
            if (!data.success) return;

            const messages = data.messages || [];
            if (countEl) countEl.textContent = messages.length;

            if (messages.length === 0) {
                listEl.innerHTML = `
                    <div style="padding:1.5rem; text-align:center; color:var(--text-dim); font-size:0.85rem;">
                        💬 No messages on this task yet. Type a message below to notify task collaborators!
                    </div>
                `;
                return;
            }

            listEl.innerHTML = messages.map(m => `
                <div class="task-msg-bubble ${m.is_mine ? 'mine' : ''}">
                    <div class="avatar" style="width:28px; height:28px; font-size:0.7rem; background:${m.sender_avatar_color || '#6366f1'}; flex-shrink:0;">
                        ${m.sender_name.charAt(0).toUpperCase()}
                    </div>
                    <div class="task-msg-content">
                        <div class="task-msg-header">
                            <span class="task-msg-author">${this.escapeHtml(m.sender_name)}</span>
                            <span style="font-size:0.65rem; color:var(--text-dim);">(${this.formatRole(m.sender_role)})</span>
                            <span class="task-msg-time">${m.created_at_formatted}</span>
                        </div>
                        <div>${this.escapeHtml(m.message)}</div>
                    </div>
                </div>
            `).join('');

            listEl.scrollTop = listEl.scrollHeight;
        } catch (err) {
            console.error('Error loading task messages', err);
        }
    },

    async submitTaskMessage(e) {
        e.preventDefault();
        if (!this.activeTaskId) return;

        const input = document.getElementById('task-message-input');
        const text = input ? input.value.trim() : '';
        if (!text) return;

        const submitBtn = document.getElementById('btn-send-task-msg');
        if (submitBtn) submitBtn.disabled = true;

        try {
            const res = await fetch('api/notifications.php?action=send_message', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    task_id: this.activeTaskId,
                    message: text
                })
            });
            const data = await res.json();
            if (data.success) {
                if (input) input.value = '';
                await this.loadTaskMessages(this.activeTaskId);
                this.showToast('Message sent! Task collaborators notified.', 'success');

                // Refresh audit history
                const histRes = await fetch(`api/task_history.php?task_id=${this.activeTaskId}`);
                const histData = await histRes.json();
                const timelineEl = document.getElementById('td-timeline');
                if (histData.success && histData.history && timelineEl) {
                    this.renderTimeline(histData.history, timelineEl);
                }
            } else {
                this.showToast(data.error || 'Failed to send message', 'error');
            }
        } catch (err) {
            console.error('Send task message error', err);
            this.showToast('Network error sending message', 'error');
        } finally {
            if (submitBtn) submitBtn.disabled = false;
        }
    },

    // ==================== SEND MESSAGE NOTIFICATION MODAL ====================

    async openSendMessageModal(prefillUserId = null, prefillUserName = null, prefillTaskId = null) {
        // Open modal immediately for instant feedback
        this.openModal('send-message-modal');

        const form = document.getElementById('send-message-form');
        if (form) form.reset();

        const recipSelect = document.getElementById('msg-recipient');
        const taskSelect = document.getElementById('msg-task');

        if (recipSelect) {
            recipSelect.innerHTML = '<option value="0">📢 Broadcast to All Active Users</option>';
            try {
                const res = await fetch('api/users.php?action=assignees');
                const data = await res.json();
                if (data.success && data.users) {
                    data.users.forEach(u => {
                        if (this.user && u.id == this.user.id) return;
                        const opt = document.createElement('option');
                        opt.value = u.id;
                        opt.textContent = `${u.name} (${this.formatRole(u.role)})`;
                        recipSelect.appendChild(opt);
                    });
                }
            } catch (e) {}

            if (prefillUserId) {
                recipSelect.value = prefillUserId;
            }
        }

        if (taskSelect) {
            taskSelect.innerHTML = '<option value="">-- None / General Message --</option>';
            if (this.tasks && this.tasks.length > 0) {
                this.tasks.forEach(t => {
                    const opt = document.createElement('option');
                    opt.value = t.id;
                    opt.textContent = `#${t.id}: ${t.title} (${t.status})`;
                    taskSelect.appendChild(opt);
                });
                if (prefillTaskId) taskSelect.value = prefillTaskId;
            } else {
                fetch('api/tasks.php?action=list')
                    .then(res => res.json())
                    .then(data => {
                        if (data.success && data.tasks) {
                            this.tasks = data.tasks;
                            data.tasks.forEach(t => {
                                const opt = document.createElement('option');
                                opt.value = t.id;
                                opt.textContent = `#${t.id}: ${t.title} (${t.status})`;
                                taskSelect.appendChild(opt);
                            });
                            if (prefillTaskId) taskSelect.value = prefillTaskId;
                        }
                    })
                    .catch(() => {});
            }
        }

        setTimeout(() => {
            const content = document.getElementById('msg-content');
            if (content) content.focus();
        }, 150);
    },

    async submitSendMessage(e) {
        e.preventDefault();
        const recip = document.getElementById('msg-recipient').value;
        const task = document.getElementById('msg-task').value;
        const title = document.getElementById('msg-title').value.trim();
        const msg = document.getElementById('msg-content').value.trim();

        if (!msg) {
            this.showToast('Please enter message content.', 'error');
            return;
        }

        const btn = document.getElementById('btn-send-message');
        if (btn) btn.disabled = true;

        try {
            const res = await fetch('api/notifications.php?action=send_message', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    receiver_id: recip,
                    task_id: task || null,
                    title: title,
                    message: msg
                })
            });
            const data = await res.json();
            if (data.success) {
                this.closeModal('send-message-modal');
                this.showToast(`Message notification sent to ${data.recipient_count} recipient(s)!`, 'success');
                await this.loadNotifications();
                if (task && this.activeTaskId == task) {
                    await this.loadTaskMessages(task);
                }
            } else {
                this.showToast(data.error || 'Failed to send message', 'error');
            }
        } catch (err) {
            console.error('Submit send message error', err);
            this.showToast('Network error while sending message', 'error');
        } finally {
            if (btn) btn.disabled = false;
        }
    },

    async viewDirectMessageByNotifId(notifId) {
        let notif = (this.notifications || []).find(n => n.id == notifId);
        if (!notif) {
            try {
                const res = await fetch('api/notifications.php?action=list');
                const data = await res.json();
                if (data.success && data.notifications) {
                    this.notifications = data.notifications;
                    notif = this.notifications.find(n => n.id == notifId);
                }
            } catch (e) {}
        }
        if (notif) {
            this.openNotificationModal(notif);
        }
    },

    replyToCurrentMessage() {
        this.closeModal('view-message-modal');
        if (this.currentViewingSenderId) {
            this.openSendMessageModal(this.currentViewingSenderId, this.currentViewingSenderName, this.currentViewingTaskId || null);
        } else {
            this.openSendMessageModal();
        }
    },

    // Demo Role Switcher
    async switchRole(role) {
        try {
            const res = await fetch('api/auth.php?action=switch_demo', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ role: role })
            });
            const data = await res.json();
            if (data.success) {
                this.showToast(`Switched account to: ${data.user.name} (${this.formatRole(data.user.role)})`, 'info');
                setTimeout(() => {
                    window.location.reload();
                }, 400);
            } else {
                this.showToast(data.error || 'Failed to switch demo role', 'error');
            }
        } catch (err) {
            console.error('Switch role error', err);
            this.showToast('Network error while switching role', 'error');
        }
    },

    // Toast Notifications
    showToast(message, type = 'info') {
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            container.className = 'toast-container';
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        
        let icon = 'ℹ️';
        if (type === 'success') icon = '✓';
        else if (type === 'error') icon = '⚠️';

        toast.innerHTML = `
            <span style="font-size:1.1rem;">${icon}</span>
            <span style="flex:1;">${this.escapeHtml(message)}</span>
        `;

        container.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(10px)';
            toast.style.transition = 'all 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    },

    // Modal Helpers
    openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    },

    closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
    },

    closeAllModals() {
        document.querySelectorAll('.modal-backdrop').forEach(modal => {
            modal.classList.remove('active');
        });
        document.body.style.overflow = '';
    },

    // Utilities
    setElText(id, val) {
        const el = document.getElementById(id);
        if (el) el.textContent = (val !== null && val !== undefined) ? val : 0;
    },

    escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
};

// Auto initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    TAMS.init();
});
