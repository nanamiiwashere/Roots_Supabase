<?php
/**
 * Roots ERP - Superadmin Sidebar Partial
 * 
 * Modern SaaS sidebar for superadmin panel.
 * Matches user dashboard layout with admin-specific differences.
 * Key difference: Shows admin session indicator instead of workspace selector.
 */

$currentPage = basename($_SERVER['PHP_SELF'], '.php');

// Default values
$displayName = $displayName ?? ($_SESSION['name'] ?? 'Admin');
$adminEmail = $adminEmail ?? ($_SESSION['email'] ?? 'admin@roots.id');
$avatar = $avatar ?? ($_SESSION['avatar'] ?? '');
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
    <a class="dashboard-brand" href="<?php echo APP_URL; ?>/superadmin/index.php" aria-label="Roots Superadmin">
        <img src="<?php echo APP_URL; ?>/img/roots_brand.png" alt="Roots" class="brand-logo brand-expanded">
        <img src="<?php echo APP_URL; ?>/img/Roots.png" alt="Roots" class="brand-logo brand-collapsed">
        <span class="dashboard-brand-text"></span>
    </a>

    <!-- Admin Session Indicator (replaces workspace selector) -->
    <section class="dashboard-org admin-session-section">
        <p>Session</p>
        <div class="admin-session-indicator">
            <div class="admin-session-avatar">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                </svg>
            </div>
            <strong>Super Admin</strong>
            <span class="admin-session-badge">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                    <polyline points="22 4 12 14.01 9 11.01"/>
                </svg>
                Full Access
            </span>
        </div>
    </section>

    <!-- Main Navigation - Management -->
    <nav class="dashboard-menu" aria-label="Superadmin menu">
        <span>Management</span>
        <a class="<?= $currentPage === 'index' ? 'is-active' : ''; ?>" href="<?php echo APP_URL; ?>/superadmin/index.php">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            <span>Dashboard</span>
        </a>
        <a class="<?= $currentPage === 'users' ? 'is-active' : ''; ?>" href="<?php echo APP_URL; ?>/superadmin/users.php">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            <span>Users</span>
        </a>
        <a class="<?= $currentPage === 'workspaces' ? 'is-active' : ''; ?>" href="<?php echo APP_URL; ?>/superadmin/workspaces.php">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
            <span>Workspaces</span>
        </a>
        <a class="<?= $currentPage === 'plans' ? 'is-active' : ''; ?>" href="<?php echo APP_URL; ?>/superadmin/plans.php">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
            <span>Pricing Plans</span>
        </a>
    </nav>

    <!-- Help Menu -->
    <nav class="dashboard-menu dashboard-help" aria-label="Superadmin help">
        <span>System</span>
        <a class="<?= $currentPage === 'settings' ? 'is-active' : ''; ?>" href="<?php echo APP_URL; ?>/superadmin/settings.php">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
            <span>Settings</span>
        </a>
    </nav>

    <!-- User Profile Card -->
    <div class="dashboard-user">
        <div class="user-profile-card">
            <div class="user-profile-avatar-wrap">
                <?php if (!empty($avatar)): ?>
                    <?php 
                    // Handle avatar path - ensure correct path resolution
                    $avatarPath = $avatar;
                    // Strip /roots/ prefix if present (database stores /roots/uploads/...)
                    if (strpos($avatarPath, '/roots/') === 0) {
                        $avatarPath = substr($avatarPath, 6); // Remove /roots
                    }
                    if (strpos($avatarPath, 'uploads/') === 0) {
                        $avatarPath = '../' . $avatarPath;
                    } elseif (strpos($avatarPath, '/uploads/') === 0) {
                        $avatarPath = '..' . $avatarPath;
                    } elseif (strpos($avatarPath, 'http') !== 0) {
                        $avatarPath = APP_URL . $avatarPath;
                    }
                    ?>
                    <img src="<?php echo htmlspecialchars($avatarPath, ENT_QUOTES, 'UTF-8'); ?>" alt="Admin avatar" class="user-profile-avatar">
                <?php else: ?>
                    <img src="../img/roots_symbol.png" alt="Admin avatar" class="user-profile-avatar">
                <?php endif; ?>
                <span class="user-profile-verify" aria-hidden="true" title="Verified Admin">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                    </svg>
                </span>
            </div>
            <div class="user-profile-info">
                <strong class="user-profile-name"><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></strong>
                <span class="user-profile-email"><?= htmlspecialchars($adminEmail, ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="user-profile-badge admin-badge">
                    <svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor" stroke="none">
                        <path d="M12 2L3 7v6c0 5.25 3.83 10.14 9 11.25C17.17 23.14 21 18.25 21 13V7l-9-5z"/>
                    </svg>
                    Super Admin
                </span>
            </div>
            <a href="<?php echo APP_URL; ?>/auth/logout.php" class="user-profile-logout" aria-label="Log out" title="Log out">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                    <polyline points="16 17 21 12 16 7"/>
                    <line x1="21" y1="12" x2="9" y2="12"/>
                </svg>
            </a>
        </div>
    </div>
</aside>

<!-- Mobile Sidebar Toggle -->
<button class="dashboard-mobile-toggle" type="button" aria-label="Toggle sidebar" id="mobileSidebarToggle">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="3" y1="12" x2="21" y2="12"/>
        <line x1="3" y1="6" x2="21" y2="6"/>
        <line x1="3" y1="18" x2="21" y2="18"/>
    </svg>
</button>

<!-- Sidebar Overlay for Mobile -->
<div class="dashboard-sidebar-overlay" id="sidebarOverlay"></div>

<script>
// Sidebar collapse functionality
(function() {
    const sidebar = document.getElementById('sidebar');
    const collapseBtn = document.getElementById('sidebarCollapseBtn');
    const mobileToggle = document.getElementById('mobileSidebarToggle');
    const overlay = document.getElementById('sidebarOverlay');
    
    // Desktop collapse
    if (collapseBtn) {
        collapseBtn.addEventListener('click', function() {
            sidebar.classList.toggle('is-collapsed');
            // Save state
            localStorage.setItem('superadmin-sidebar-collapsed', sidebar.classList.contains('is-collapsed'));
        });
        
        // Restore state
        if (localStorage.getItem('superadmin-sidebar-collapsed') === 'true') {
            sidebar.classList.add('is-collapsed');
        }
    }
    
    // Mobile toggle
    if (mobileToggle && overlay) {
        mobileToggle.addEventListener('click', function() {
            sidebar.classList.toggle('is-mobile-open');
            overlay.classList.toggle('is-active');
        });
        
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('is-mobile-open');
            overlay.classList.remove('is-active');
        });
    }
})();
</script>