<?php
/**
 * Roots ERP - Sidebar Partial
 * 
 * Modern SaaS sidebar with dynamic data from database.
 * 
 * Required variables (should be defined before including this file):
 * - $currentUser: Current user array from database
 * - $workspace: Current workspace array from database
 * - $displayName: User's display name
 * - $workspaceName: Workspace name
 * - $workspaceLogo: Workspace logo URL
 * - $avatar: User avatar URL
 * - $currentPage: Current page identifier for active menu highlighting (optional)
 * - $pdo: PDO database connection
 */

// Default values if not provided
$_SESSION['user'] ?? [];
$displayName = $displayName ?? ($currentUser['name'] ?? 'User');
$workspaceName = $workspaceName ?? ($workspace['name'] ?? 'My Workspace');
require_once __DIR__ . '/../../handler/supabase-img.php';

// Handle workspace logo path - ensure correct directory
if (!empty($workspace['logo'])) {
    $workspaceLogo = supabaseImageUrl($workspace['logo']);
} else {
    $workspaceLogo = '../img/roots_symbol.png';
}
// Handle avatar path - ensure correct directory
if (!isset($avatar) || empty($avatar)) {
    if (!empty($currentUser['avatar'])) {
        $avatar = supabaseImageUrl($currentUser['avatar']);
    } else {
        $avatar = '../img/roots_symbol.png';
    }
}

$currentPage = $currentPage ?? 'analytics';

// Fetch all workspaces for current user
$userWorkspaces = [];
if (isset($pdo) && isset($currentUser['id_user'])) {
    try {
        $wsStmt = $pdo->prepare(
            'SELECT w.* FROM workspaces w
             INNER JOIN workspace_members wm ON wm.workspace_id = w.id
             WHERE wm.user_id = ? AND w.setup_completed_at IS NOT NULL
             ORDER BY w.created_at DESC'
        );
        $wsStmt->execute([$currentUser['id_user']]);
        $userWorkspaces = $wsStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $userWorkspaces = [];
    }
}
?>

<!-- Sidebar -->
<aside class="dashboard-sidebar" id="sidebar">
    <!-- Collapse Button (on sidebar edge) -->
    <button class="dashboard-collapse-btn" type="button" aria-label="Collapse sidebar" id="sidebarCollapseBtn">
        <!-- Double chevron left (collapse) -->
        <svg class="collapse-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="11 17 6 12 11 7"/>
            <polyline points="18 17 13 12 18 7"/>
        </svg>
        <!-- Double chevron right (expand) -->
        <svg class="expand-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="13 17 18 12 13 7"/>
            <polyline points="6 17 11 12 6 7"/>
        </svg>
    </button>

    <!-- Brand / Logo -->
            <a class="dashboard-brand" href="index.php" aria-label="Roots dashboard">
                <img src="../img/roots_brand.png" alt="Roots" class="brand-logo brand-expanded">
                <img src="../img/Roots.png" alt="Roots" class="brand-logo brand-collapsed">
                <span class="dashboard-brand-text"></span>
            </a>

    <!-- Organization Selector -->
    <section class="dashboard-org">
        <p>Workspace</p>
        <div class="org-selector-wrap">
            <button type="button" class="org-selector" id="workspaceToggle">
                <img src="<?= htmlspecialchars($workspaceLogo, ENT_QUOTES, 'UTF-8'); ?>" alt="" class="org-avatar">
                <strong><?= htmlspecialchars($workspaceName, ENT_QUOTES, 'UTF-8'); ?></strong>
                <i aria-hidden="true"></i>
            </button>
            <!-- Workspace Dropdown -->
            <div class="workspace-dropdown" id="workspaceDropdown">
                <?php if (!empty($userWorkspaces)): ?>
                    <?php foreach ($userWorkspaces as $ws): ?>
                        <div class="dropdown-item <?= ($workspace && $workspace['id'] === $ws['id']) ? 'is-active' : ''; ?>" data-workspace-id="<?= $ws['id']; ?>">
                            <?php 
                            $wsLogo = !empty($ws['logo']) 
                                ? (strpos($ws['logo'], 'uploads/') === 0 ? '../' . $ws['logo'] : $ws['logo'])
                                : '../img/roots_symbol.png';
                            ?>
                            <img src="<?= htmlspecialchars($wsLogo, ENT_QUOTES, 'UTF-8'); ?>" alt="" class="org-avatar" style="width:24px;height:24px;">
                            <strong><?= htmlspecialchars($ws['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <div class="dropdown-item dropdown-new-workspace">
                    <svg viewBox="0 0 24 24" style="width:20px;height:20px;stroke:var(--mint-dark);fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;flex-shrink:0;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    <strong>Create New Workspace</strong>
                </div>
            </div>
        </div>
    </section>

    <!-- Main Navigation - Operational -->
    <nav class="dashboard-menu" aria-label="Dashboard menu">
        <span>Operational</span>
        <a class="<?= $currentPage === 'analytics' ? 'is-active' : ''; ?>" href="index.php?page=analytics">
            <svg viewBox="0 0 24 24"><path d="M3 3v18h18"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/></svg>
            <span>Analytics</span>
        </a>
        <a class="<?= $currentPage === 'customers' ? 'is-active' : ''; ?>" href="customers.php">
            <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            <span>Customers</span>
        </a>
        <a class="<?= $currentPage === 'orders' ? 'is-active' : ''; ?>" href="orders.php">
            <svg viewBox="0 0 24 24"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
            <span>Orders</span>
        </a>
        <a class="<?= $currentPage === 'inventory' ? 'is-active' : ''; ?>" href="inventory.php">
            <svg viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
            <span>Inventory</span>
        </a>
        <a class="<?= $currentPage === 'finance' ? 'is-active' : ''; ?>" href="finance.php">
            <svg viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
            <span>Finance</span>
        </a>
        <a class="<?= $currentPage === 'reports' ? 'is-active' : ''; ?>" href="reports.php">
            <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
            <span>Reports</span>
        </a>
    </nav>

    <!-- Help Menu -->
    <nav class="dashboard-menu dashboard-help" aria-label="Dashboard help">
        <span>Help</span>
        <a class="<?= $currentPage === 'settings' ? 'is-active' : ''; ?>" href="settings.php">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
            <span>Settings</span>
        </a>
    </nav>

    <!-- User Profile Card -->
    <div class="dashboard-user">
        <div class="user-profile-card">
            <div class="user-profile-avatar-wrap">
                <img src="<?= htmlspecialchars($avatar, ENT_QUOTES, 'UTF-8'); ?>" alt="User avatar" class="user-profile-avatar">
                
                <?php
                // Cek apakah 'oauth_provider' udah ke-load dari session/auth-handler
                $dbProvider = $currentUser['oauth_provider'] ?? null;
                
                // Kalau datanya kosong (karena query auth-handler kurang lengkap), kita tarik manual dari DB
                if ($dbProvider === null && isset($pdo) && isset($currentUser['id_user'])) {
                    $stmt = $pdo->prepare("SELECT oauth_provider FROM users WHERE id_user = ?");
                    $stmt->execute([$currentUser['id_user']]);
                    $dbProvider = $stmt->fetchColumn();
                }

                // Format datanya, kalau beneran NULL atau kosong, jadikan 'email'
                $provider = strtolower($dbProvider ?: 'email');

                $providerTitle = ucfirst($provider) . ' Login';
                $iconSvg = '';

                switch ($provider) {
                    case 'google':
                        $iconSvg = '<svg class="solid-icon" viewBox="0 0 24 24"><path d="M12.48 10.92v3.28h7.84c-.24 1.84-.853 3.187-1.787 4.133-1.147 1.147-2.933 2.4-6.053 2.4-4.827 0-8.6-3.893-8.6-8.72s3.773-8.72 8.6-8.72c2.6 0 4.507 1.027 5.907 2.347l2.307-2.307C18.747 1.44 16.133 0 12.48 0 5.867 0 .307 5.387.307 12s5.56 12 12.173 12c3.573 0 6.267-1.173 8.373-3.36 2.16-2.16 2.84-5.213 2.84-7.667 0-.76-.053-1.467-.173-2.053H12.48z"/></svg>';
                        break;
                    case 'discord':
                        $iconSvg = '<svg class="solid-icon" viewBox="0 0 24 24"><path d="M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 0 0 .031.057 19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028 14.09 14.09 0 0 0 1.226-1.994.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.299 12.299 0 0 1-1.873.892.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.028zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.956-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.956 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.946 2.418-2.157 2.418z"/></svg>';
                        break;
                    case 'facebook':
                        $iconSvg = '<svg class="solid-icon" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.469h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.469h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>';
                        break;
                    default:
                        $iconSvg = '<svg class="line-icon" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>';
                        break;
                }
                ?>

                <span class="user-profile-verify" aria-hidden="true" title="<?= htmlspecialchars($providerTitle, ENT_QUOTES, 'UTF-8'); ?>">
                    <?= $iconSvg; ?>
                </span>
            </div>
            <div class="user-profile-info">
                <strong class="user-profile-name"><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></strong>
                <span class="user-profile-email"><?= htmlspecialchars($currentUser['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="user-profile-badge">
                    <svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor" stroke="none">
                        <path d="M3 6a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6z"/>
                    </svg>
                    <?= htmlspecialchars(ucfirst($currentUser['membership'] ?? 'Starter'), ENT_QUOTES, 'UTF-8'); ?>
                </span>
            </div>
            <a href="../auth/logout.php" class="user-profile-logout" aria-label="Log out" title="Log out">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                    <polyline points="16 17 21 12 16 7"/>
                    <line x1="21" y1="12" x2="9" y2="12"/>
                </svg>
            </a>
        </div>
    </div>
</aside>
