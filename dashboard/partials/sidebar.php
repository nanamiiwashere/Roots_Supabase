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
                <span class="user-profile-verify" aria-hidden="true" title="Verified">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="5" width="18" height="14" rx="2"/>
                        <path d="m3 7 9 6 9-6"/>
                        <path d="m9 13 2 2 4-4"/>
                    </svg>
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
