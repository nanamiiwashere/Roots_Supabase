<?php
require_once __DIR__ . '/../connect.php';

// Auth check - must be logged in as admin
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

// Check if user has admin membership
$stmt = $pdo->prepare("SELECT membership, name, email, avatar FROM users WHERE id_user = ?");
$stmt->execute([$_SESSION['user_id']]);
$adminUser = $stmt->fetch();

if (!$adminUser || $adminUser['membership'] !== 'admin') {
    header('Location: ' . APP_URL . '/dashboard/index.php');
    exit;
}

// Get stats
$stats = [];

// Total users
$stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
$stats['total_users'] = $stmt->fetch()['total'];

// Total workspaces
$stmt = $pdo->query("SELECT COUNT(*) as total FROM workspaces");
$stats['total_workspaces'] = $stmt->fetch()['total'];

// Users by membership
$stmt = $pdo->query("SELECT membership, COUNT(*) as count FROM users GROUP BY membership");
$membershipCounts = [];
while ($row = $stmt->fetch()) {
    $membershipCounts[$row['membership']] = $row['count'];
}

// Recent users
$stmt = $pdo->query("SELECT id_user, name, email, membership, role, avatar FROM users ORDER BY id_user DESC LIMIT 10");
$recentUsers = $stmt->fetchAll();

// Recent workspaces
$stmt = $pdo->query("SELECT w.*, COUNT(wm.user_id) as member_count FROM workspaces w LEFT JOIN workspace_members wm ON w.id = wm.workspace_id GROUP BY w.id ORDER BY w.created_at DESC LIMIT 10");
$recentWorkspaces = $stmt->fetchAll();

// Set sidebar variables
$displayName = $adminUser['name'];
$adminEmail = $adminUser['email'];
$avatar = $adminUser['avatar'];

$pageTitle = 'Dashboard - Super Admin';
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
            <!-- Topbar -->
            <header class="superadmin-topbar">
                <div class="superadmin-breadcrumb">
                    <span>Roots</span>
                    <i></i>
                    <strong>Dashboard</strong>
                </div>
            </header>

            <!-- Content -->
            <main class="superadmin-content">
                <h1>Super Admin Dashboard</h1>
                <p class="superadmin-subtitle">Manage users, pricing plans, and workspaces for Roots ERP.</p>

                <!-- Stats -->
                <div class="superadmin-stats">
                    <div class="sa-stat-card">
                        <div class="sa-stat-head">
                            <div class="sa-stat-ico">
                                <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                            </div>
                            <span>Total Users</span>
                        </div>
                        <strong><?php echo number_format($stats['total_users']); ?></strong>
                        <span class="sa-stat-sub">Registered accounts</span>
                    </div>

                    <div class="sa-stat-card">
                        <div class="sa-stat-head">
                            <div class="sa-stat-ico">
                                <svg viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                            </div>
                            <span>Workspaces</span>
                        </div>
                        <strong><?php echo number_format($stats['total_workspaces']); ?></strong>
                        <span class="sa-stat-sub">Active workspaces</span>
                    </div>

                    <div class="sa-stat-card">
                        <div class="sa-stat-head">
                            <div class="sa-stat-ico" style="background: var(--mint-pale);">
                                <svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
                            </div>
                            <span>Pro Members</span>
                        </div>
                        <strong><?php echo number_format($membershipCounts['pro'] ?? 0); ?></strong>
                        <span class="sa-stat-sub">Pro plan subscribers</span>
                    </div>

                    <div class="sa-stat-card">
                        <div class="sa-stat-head">
                            <div class="sa-stat-ico" style="background: #ede9fe;">
                                <svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                            </div>
                            <span>Scale Members</span>
                        </div>
                        <strong><?php echo number_format($membershipCounts['scale'] ?? 0); ?></strong>
                        <span class="sa-stat-sub">Scale plan subscribers</span>
                    </div>
                </div>

                <!-- Recent Users -->
                <div class="sa-panel">
                    <div class="sa-panel-header">
                        <h2>Recent Users</h2>
                        <a href="<?php echo APP_URL; ?>/superadmin/users.php" class="sa-btn">
                            View All
                            <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                        </a>
                    </div>

                    <?php if (empty($recentUsers)): ?>
                        <div class="sa-empty">
                            <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
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
                                        <th>ID</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentUsers as $user): ?>
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
                                            <td>#<?php echo $user['id_user']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Recent Workspaces -->
                <div class="sa-panel">
                    <div class="sa-panel-header">
                        <h2>Recent Workspaces</h2>
                        <a href="<?php echo APP_URL; ?>/superadmin/workspaces.php" class="sa-btn">
                            View All
                            <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                        </a>
                    </div>

                    <?php if (empty($recentWorkspaces)): ?>
                        <div class="sa-empty">
                            <svg viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                            <p>No workspaces found</p>
                        </div>
                    <?php else: ?>
                        <div class="sa-table-wrap">
                            <table class="sa-table">
                                <thead>
                                    <tr>
                                        <th>Workspace</th>
                                        <th>Industry</th>
                                        <th>Members</th>
                                        <th>Created</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentWorkspaces as $ws): ?>
                                        <tr>
                                            <td>
                                                <div class="sa-user-cell">
                                                    <?php if (!empty($ws['logo'])): ?>
                                                        <img src="<?php echo APP_URL . '/' . htmlspecialchars($ws['logo']); ?>" alt="" class="sa-user-avatar" style="border-radius: 8px;">
                                                    <?php else: ?>
                                                        <div class="sa-user-avatar-placeholder" style="border-radius: 8px;">
                                                            <svg viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div class="sa-user-info">
                                                        <strong><?php echo htmlspecialchars($ws['name']); ?></strong>
                                                        <span>Size: <?php echo htmlspecialchars($ws['company_size']); ?></span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars(str_replace('_', ' ', $ws['industry'])); ?></td>
                                            <td><?php echo $ws['member_count']; ?> member(s)</td>
                                            <td><?php echo date('M d, Y', strtotime($ws['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
</body>
</html>