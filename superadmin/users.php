<?php
require_once __DIR__ . '/../connect.php';
require_once __DIR__ . '/../auth/auth-handler.php';
require_once __DIR__ . '/../auth/auth3thparty.php';

// Auth check
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

$stmt = $pdo->prepare("SELECT membership, name, email, avatar FROM users WHERE id_user = ?");
$stmt->execute([$_SESSION['user_id']]);
$adminUser = $stmt->fetch();

if (!$adminUser || $adminUser['membership'] !== 'admin') {
    header('Location: ' . APP_URL . '/dashboard/index.php');
    exit;
}

// Handle actions
$alert = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_membership') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $membership = $_POST['membership'] ?? '';
        $validPlans = ['starter', 'pro', 'scale', 'admin'];

        if ($userId && in_array($membership, $validPlans)) {
            $stmt = $pdo->prepare("UPDATE users SET membership = ? WHERE id_user = ?");
            $stmt->execute([$membership, $userId]);
            $alert = ['type' => 'success', 'message' => 'Membership updated successfully.'];
        }
    } elseif ($action === 'update_user') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = $_POST['role'] ?? '';
        $membership = $_POST['membership'] ?? '';
        $validPlans = ['starter', 'pro', 'scale', 'admin'];
        $validRoles = ['Finance Manager', 'Staff', 'Manager', 'Founder', 'Personal'];

        if ($userId && $name && $email && in_array($membership, $validPlans) && in_array($role, $validRoles)) {
            $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, role = ?, membership = ? WHERE id_user = ?");
            $stmt->execute([$name, $email, $role, $membership, $userId]);
            $alert = ['type' => 'success', 'message' => 'User updated successfully.'];
        }
    } elseif ($action === 'delete_user') {
        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId && $userId != $_SESSION['user_id']) {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id_user = ?");
            $stmt->execute([$userId]);
            $alert = ['type' => 'success', 'message' => 'User deleted successfully.'];
        }
    }
}

// Search and filter
$search = $_GET['search'] ?? '';
$membershipFilter = $_GET['membership'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

// Build query
$where = [];
$params = [];

if ($search) {
    $where[] = "(u.name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($membershipFilter) {
    $where[] = "u.membership = ?";
    $params[] = $membershipFilter;
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Get total count
$countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM users u $whereClause");
$countStmt->execute($params);
$totalUsers = $countStmt->fetch()['total'];
$totalPages = ceil($totalUsers / $perPage);

// Get membership stats for stat cards
$membershipStats = [];
$stmt = $pdo->query("SELECT membership, COUNT(*) as count FROM users GROUP BY membership");
while ($row = $stmt->fetch()) {
    $membershipStats[$row['membership']] = (int) $row['count'];
}
$totalStarter = $membershipStats['starter'] ?? 0;
$totalPro = $membershipStats['pro'] ?? 0;
$totalScale = $membershipStats['scale'] ?? 0;
$totalAdmin = $membershipStats['admin'] ?? 0;

// Get users
$stmt = $pdo->prepare("
    SELECT u.*, 
    COUNT(DISTINCT wm.workspace_id) as workspace_count
    FROM users u
    LEFT JOIN workspace_members wm ON u.id_user = wm.user_id
    $whereClause
    GROUP BY u.id_user
    ORDER BY u.id_user DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$users = $stmt->fetchAll();

// Set sidebar variables
$displayName = $adminUser['name'];
$adminEmail = $adminUser['email'];
$avatar = $adminUser['avatar'];

$pageTitle = 'User Management - Super Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/superadmin/partials/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Poppins:wght@600;700;800;900&display=swap" rel="stylesheet">
</head>
<body>
    <div class="superadmin-shell">
        <?php include __DIR__ . '/partials/sidebar.php'; ?>

        <div class="superadmin-main">
            <header class="superadmin-topbar">
                <div class="superadmin-breadcrumb">
                    <a href="<?php echo APP_URL; ?>/superadmin/index.php">Roots</a>
                    <i></i>
                    <strong>Users</strong>
                </div>
            </header>

            <main class="superadmin-content">
                <h1>User Management</h1>
                <p class="superadmin-subtitle">Manage all registered users and their membership plans.</p>

                <?php if ($alert): ?>
                    <div class="sa-alert sa-alert-<?php echo $alert['type']; ?>">
                        <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        <?php echo htmlspecialchars($alert['message']); ?>
                    </div>
                <?php endif; ?>

                <!-- Stat Cards -->
                <div class="superadmin-stats">
                    <div class="sa-stat-card">
                        <div class="sa-stat-head">
                            <div class="sa-stat-ico">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                            </div>
                            <span>Total Users</span>
                        </div>
                        <strong><?php echo number_format($totalUsers); ?></strong>
                        <span class="sa-stat-sub">All accounts</span>
                    </div>

                    <div class="sa-stat-card">
                        <div class="sa-stat-head">
                            <div class="sa-stat-ico" style="background: #f0f1f3;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="#6f7681" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            </div>
                            <span>Starter</span>
                        </div>
                        <strong><?php echo number_format($totalStarter); ?></strong>
                        <span class="sa-stat-sub">Free plan users</span>
                    </div>

                    <div class="sa-stat-card">
                        <div class="sa-stat-head">
                            <div class="sa-stat-ico" style="background: var(--mint-pale);">
                                <svg viewBox="0 0 24 24" fill="none" stroke="var(--mint-dark)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                            </div>
                            <span>Pro</span>
                        </div>
                        <strong><?php echo number_format($totalPro); ?></strong>
                        <span class="sa-stat-sub">Pro subscribers</span>
                    </div>

                    <div class="sa-stat-card">
                        <div class="sa-stat-head">
                            <div class="sa-stat-ico" style="background: #ede9fe;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                            </div>
                            <span>Scale</span>
                        </div>
                        <strong><?php echo number_format($totalScale); ?></strong>
                        <span class="sa-stat-sub">Scale subscribers</span>
                    </div>
                </div>

                <!-- Toolbar -->
                <div class="sa-toolbar">
                    <form class="sa-search" method="GET" action="">
                        <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <input type="text" name="search" placeholder="Search users..." value="<?php echo htmlspecialchars($search); ?>">
                        <?php if ($membershipFilter): ?>
                            <input type="hidden" name="membership" value="<?php echo htmlspecialchars($membershipFilter); ?>">
                        <?php endif; ?>
                    </form>

                    <select class="sa-select" onchange="window.location.href='?membership='+this.value+'&search=<?php echo urlencode($search); ?>'">
                        <option value="">All Plans</option>
                        <option value="starter" <?php echo $membershipFilter === 'starter' ? 'selected' : ''; ?>>Starter</option>
                        <option value="pro" <?php echo $membershipFilter === 'pro' ? 'selected' : ''; ?>>Pro</option>
                        <option value="scale" <?php echo $membershipFilter === 'scale' ? 'selected' : ''; ?>>Scale</option>
                        <option value="admin" <?php echo $membershipFilter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                    </select>
                </div>

                <!-- Users Table -->
                <div class="sa-panel">
                    <?php if (empty($users)): ?>
                        <div class="sa-empty">
                            <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                            <p>No users found</p>
                        </div>
                    <?php else: ?>
                        <div class="sa-table-wrap">
                            <table class="sa-table">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Role</th>
                                        <th>Membership</th>
                                        <th>Workspaces</th>
                                        <th>ID</th>
                                        <th style="text-align:right;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td>
                                                <div class="sa-user-cell">
                                                        <?php if (!empty($user['avatar'])): ?>
                                                            <?php 
                                                            if (filter_var($user['avatar'], FILTER_VALIDATE_URL)) {
                                                                $finalAvatarPath = $user['avatar'];
                                                            } else {
                                                                $fileName = basename($user['avatar']);
                                                                $cleanAppUrl = rtrim(APP_URL, '/'); 
                                                                $finalAvatarPath = $cleanAppUrl . '/uploads/avatars/' . $fileName;
                                                            }
                                                            ?>
                                                            <img src="<?php echo htmlspecialchars($finalAvatarPath); ?>" alt="Avatar" class="sa-user-avatar">
                                                        <?php else: ?>
                                                            <div class="sa-user-avatar-placeholder">
                                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                                            </div>
                                                        <?php endif; ?>
                                                    <div class="sa-user-info">
                                                        <strong><?php echo htmlspecialchars($user['name']); ?></strong>
                                                        <span><?php echo htmlspecialchars($user['email']); ?></span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($user['role']); ?></td>
                                            <td>
                                                <span class="sa-membership-badge <?php echo htmlspecialchars($user['membership']); ?>">
                                                    <?php echo ucfirst(htmlspecialchars($user['membership'])); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $user['workspace_count']; ?> workspace(s)</td>
                                            <td>#<?php echo $user['id_user']; ?></td>
                                            <td>
                                                <div class="sa-actions" style="justify-content: flex-end;">
                                                    <button class="sa-action-btn" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($user)); ?>)" title="Edit User">
                                                        <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                                    </button>
                                                    <button class="sa-action-btn" onclick="openMembershipModal(<?php echo $user['id_user']; ?>, '<?php echo htmlspecialchars($user['name']); ?>', '<?php echo $user['membership']; ?>')" title="Change Plan">
                                                        <svg viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                                                    </button>
                                                    <?php if ($user['id_user'] != $_SESSION['user_id']): ?>
                                                        <button class="sa-action-btn danger" onclick="openDeleteModal(<?php echo $user['id_user']; ?>, '<?php echo htmlspecialchars($user['name']); ?>')" title="Delete User">
                                                            <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                            <div style="display:flex;justify-content:center;gap:8px;margin-top:20px;">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&membership=<?php echo urlencode($membershipFilter); ?>" class="sa-btn">← Prev</a>
                                <?php endif; ?>
                                <span class="sa-btn" style="pointer-events:none;">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                                <?php if ($page < $totalPages): ?>
                                    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&membership=<?php echo urlencode($membershipFilter); ?>" class="sa-btn">Next →</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="sa-modal" id="editModal">
        <div class="sa-modal-backdrop" onclick="closeModal('editModal')"></div>
        <div class="sa-modal-dialog">
            <div class="sa-modal-header">
                <h2>Edit User</h2>
                <button class="sa-modal-close" onclick="closeModal('editModal')">×</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_user">
                <input type="hidden" name="user_id" id="edit_user_id">
                <div class="sa-modal-body">
                    <div class="sa-field">
                        <label>Name</label>
                        <input type="text" name="name" id="edit_name" required>
                    </div>
                    <div class="sa-field">
                        <label>Email</label>
                        <input type="email" name="email" id="edit_email" required>
                    </div>
                    <div class="sa-field">
                        <label>Role</label>
                        <select name="role" id="edit_role" required>
                            <option value="Finance Manager">Finance Manager</option>
                            <option value="Staff">Staff</option>
                            <option value="Manager">Manager</option>
                            <option value="Founder">Founder</option>
                            <option value="Personal">Personal</option>
                        </select>
                    </div>
                    <div class="sa-field">
                        <label>Membership Plan</label>
                        <select name="membership" id="edit_membership" required>
                            <option value="starter">Starter</option>
                            <option value="pro">Pro</option>
                            <option value="scale">Scale</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </div>
                <div class="sa-modal-footer">
                    <button type="button" class="sa-btn" onclick="closeModal('editModal')">Cancel</button>
                    <button type="submit" class="sa-btn sa-btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Change Membership Modal -->
    <div class="sa-modal" id="membershipModal">
        <div class="sa-modal-backdrop" onclick="closeModal('membershipModal')"></div>
        <div class="sa-modal-dialog">
            <div class="sa-modal-header">
                <h2>Change Membership Plan</h2>
                <button class="sa-modal-close" onclick="closeModal('membershipModal')">×</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_membership">
                <input type="hidden" name="user_id" id="membership_user_id">
                <div class="sa-modal-body">
                    <p style="margin:0 0 16px;font-size:13px;color:#6f7681;">
                        Changing plan for: <strong id="membership_user_name"></strong>
                    </p>
                    <div class="sa-field">
                        <label>New Membership Plan</label>
                        <select name="membership" id="membership_select" required>
                            <option value="starter">Starter - Free</option>
                            <option value="pro">Pro - $29/month</option>
                            <option value="scale">Scale - $79/month</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </div>
                <div class="sa-modal-footer">
                    <button type="button" class="sa-btn" onclick="closeModal('membershipModal')">Cancel</button>
                    <button type="submit" class="sa-btn sa-btn-primary">Update Plan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="sa-modal" id="deleteModal">
        <div class="sa-modal-backdrop" onclick="closeModal('deleteModal')"></div>
        <div class="sa-modal-dialog">
            <div class="sa-modal-header">
                <h2>Delete User</h2>
                <button class="sa-modal-close" onclick="closeModal('deleteModal')">×</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="delete_user">
                <input type="hidden" name="user_id" id="delete_user_id">
                <div class="sa-modal-body">
                    <p style="margin:0;font-size:14px;color:#1f2730;">
                        Are you sure you want to delete <strong id="delete_user_name"></strong>?
                    </p>
                    <p style="margin:8px 0 0;font-size:13px;color:#e23b54;">
                        This action cannot be undone. All workspace data associated with this user will also be removed.
                    </p>
                </div>
                <div class="sa-modal-footer">
                    <button type="button" class="sa-btn" onclick="closeModal('deleteModal')">Cancel</button>
                    <button type="submit" class="sa-btn sa-btn-danger">Delete User</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditModal(user) {
            document.getElementById('edit_user_id').value = user.id_user;
            document.getElementById('edit_name').value = user.name;
            document.getElementById('edit_email').value = user.email;
            document.getElementById('edit_role').value = user.role;
            document.getElementById('edit_membership').value = user.membership;
            document.getElementById('editModal').classList.add('is-open');
            document.body.classList.add('sa-modal-open');
        }

        function openMembershipModal(userId, userName, currentPlan) {
            document.getElementById('membership_user_id').value = userId;
            document.getElementById('membership_user_name').textContent = userName;
            document.getElementById('membership_select').value = currentPlan;
            document.getElementById('membershipModal').classList.add('is-open');
            document.body.classList.add('sa-modal-open');
        }

        function openDeleteModal(userId, userName) {
            document.getElementById('delete_user_id').value = userId;
            document.getElementById('delete_user_name').textContent = userName;
            document.getElementById('deleteModal').classList.add('is-open');
            document.body.classList.add('sa-modal-open');
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('is-open');
            document.body.classList.remove('sa-modal-open');
        }
    </script>
</body>
</html>
