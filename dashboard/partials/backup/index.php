<?php
// Prevent caching for security
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/../connect.php';
require_once __DIR__ . '/../auth/auth-handler.php';
require_once __DIR__ . '/../auth/auth3thparty.php';

// Get current user or redirect to login
requireLogin();
$currentUser = currentUser();

if (!$currentUser) {
    header('Location: ../login.php');
    exit;
}

// Check if user has completed workspace setup
$workspace = null;
$setupUnavailable = false;

// Handle workspace switching via URL parameter
$requestedWorkspaceId = isset($_GET['workspace_id']) ? (int) $_GET['workspace_id'] : null;

// If workspace_id is provided, save it to session
if ($requestedWorkspaceId) {
    $_SESSION['active_workspace_id'] = $requestedWorkspaceId;
}

// Get active workspace from session or use default (most recent)
$activeWorkspaceId = $_SESSION['active_workspace_id'] ?? null;

try {
    if ($activeWorkspaceId) {
        // Try to load the specific workspace from session
        $stmt = $pdo->prepare(
            'SELECT w.* FROM workspaces w
             INNER JOIN workspace_members wm ON wm.workspace_id = w.id
             WHERE wm.user_id = ? AND w.id = ? AND w.setup_completed_at IS NOT NULL
             LIMIT 1'
        );
        $stmt->execute([$currentUser['id_user'], $activeWorkspaceId]);
        $workspace = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        
        // If workspace not found (e.g., user removed from workspace), clear session
        if (!$workspace) {
            unset($_SESSION['active_workspace_id']);
        }
    }
    
    // If no workspace from session, get most recent
    if (!$workspace) {
        $stmt = $pdo->prepare(
            'SELECT w.* FROM workspaces w
             INNER JOIN workspace_members wm ON wm.workspace_id = w.id
             WHERE wm.user_id = ? AND w.setup_completed_at IS NOT NULL
             ORDER BY w.setup_completed_at DESC
             LIMIT 1'
        );
        $stmt->execute([$currentUser['id_user']]);
        $workspace = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        
        // Save the default workspace to session
        if ($workspace) {
            $_SESSION['active_workspace_id'] = $workspace['id'];
        }
    }
} catch (Exception $e) {
    $setupUnavailable = true;
}

// Prepare display data
$needsSetup = !$workspace;
$createWorkspace = isset($_GET['create_workspace']) && $_GET['create_workspace'] === '1';
$showSetupModal = $needsSetup || $createWorkspace;
$displayName = $currentUser['name'] ?: 'User';
$workspaceName = $workspace['name'] ?? 'My Workspace';
// $workspaceLogo is handled by sidebar.php for correct path resolution
$avatar = $currentUser['avatar'] ?: '../img/roots_symbol.png';
$setupError = $_SESSION['setup_error'] ?? '';
$setupSuccess = $_SESSION['setup_success'] ?? '';
unset($_SESSION['setup_error']);
unset($_SESSION['setup_success']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Roots ERP</title>
    <meta name="description" content="Dashboard Roots ERP untuk analytics, customers, orders, inventory, finance, dan reports.">
    <link rel="icon" href="../img/roots_symbol.png?v=<?= filemtime(__DIR__ . '/../img/roots_symbol.png'); ?>" type="image/png">
    <link rel="stylesheet" href="../dashboard/partials/style.css">
    <style>
        /* SVG gradient for chart */
        .chart-gradient-defs { position: absolute; width: 0; height: 0; }
    </style>
</head>
<body class="<?= $showSetupModal ? 'workspace-setup-open' : ''; ?>">
    
    <!-- SVG Defs for gradients -->
    <svg class="chart-gradient-defs" aria-hidden="true">
        <defs>
            <linearGradient id="chartGradient" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="#54d9aa" stop-opacity="0.3"/>
                <stop offset="100%" stop-color="#54d9aa" stop-opacity="0"/>
            </linearGradient>
        </defs>
    </svg>

    <!-- Main Dashboard -->
    <main class="dashboard-shell" aria-label="Roots dashboard">
        
        <!-- Sidebar (Partial) -->
        <?php 
        $currentPage = 'analytics';
        include __DIR__ . '/partials/sidebar.php'; 
        ?>

        <!-- Main Content Area -->
        <section class="dashboard-main">
            
            <!-- Top Bar -->
            <header class="dashboard-topbar">
                <button class="dashboard-mobile-menu-btn" type="button" aria-label="Open menu" id="mobileMenuBtn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                </button>
                <div class="dashboard-breadcrumb">
                    <span>Dashboard</span>
                    <i></i>
                    <strong>Analytics</strong>
                </div>
                <label class="dashboard-search">
                    <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="search" placeholder="Search items, account or more...">
                </label>
                <div class="dashboard-actions">
                    <button type="button" aria-label="Notifications" class="action-btn" id="notifBtn">
                        <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                        <span class="notif-dot"></span>
                    </button>
                    <!-- Notification Popup -->
                    <div class="notif-popup" id="notifPopup">
                        <div class="notif-popup-header">
                            <strong>Notifications</strong>
                            <button type="button" class="notif-close" id="notifClose">&times;</button>
                        </div>
                        <div class="notif-popup-body">
                            <p class="notif-empty">No new notifications</p>
                        </div>
                    </div>
                    <img src="<?= htmlspecialchars($avatar, ENT_QUOTES, 'UTF-8'); ?>" alt="User avatar" class="topbar-avatar" id="topbarAvatar">
                </div>
            </header>

            <!-- Dashboard Content -->
            <div class="dashboard-content">
                <h1>Welcome, <?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?>!</h1>

                <?php if ($setupSuccess): ?>
                    <div class="alert alert-success" role="alert">
                        <?= htmlspecialchars($setupSuccess, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <!-- Upgrade Banner -->
                <section class="dashboard-upgrade">
                    <div>
                        <span>★ Unlock Premium Features</span>
                        <h2>Upgrade to Pro for AI Analytics & Bank Integrations.</h2>
                    </div>
                    <a href="../pricing.php" class="upgrade-btn">Upgrade Now</a>
                </section>

                <!-- Stats Grid -->
                <div class="dashboard-grid">
                    <article class="dashboard-card stat-small">
                        <span>Total Profit</span>
                        <strong>Rp 0</strong>
                        <em>0% from last month</em>
                    </article>
                    <article class="dashboard-card stat-large">
                        <span>Revenue</span>
                        <strong>Rp 0</strong>
                        <em>0% from last month</em>
                        <div class="dashboard-line-chart" aria-hidden="true">
                            <svg viewBox="0 0 360 150" preserveAspectRatio="none">
                                <path d="M10 130 L55 74 L98 96 L142 42 L185 110 L228 78 L280 55 L338 8"></path>
                                <path d="M10 130 L55 74 L98 96 L142 42 L185 110 L228 78 L280 55 L338 8 L338 142 L10 142Z"></path>
                            </svg>
                        </div>
                    </article>
                    <article class="dashboard-card activity-card">
                        <h2>Recent Activity</h2>
                        <p class="no-data-message">No activity yet. Start using Roots to see your activity here.</p>
                    </article>
                    <article class="dashboard-card review-card">
                        <h2>Product Reviews</h2>
                        <p class="no-data-message">No reviews yet.</p>
                    </article>
                    <article class="dashboard-card support-card">
                        <span></span>
                        <h2>Need help?</h2>
                        <p>Contact our support team to finish your first dashboard setup.</p>
                    </article>
                </div>
            </div>
        </section>
    </main>

    <?php if ($showSetupModal): ?>
        <!-- Workspace Setup Onboarding Modal -->
        <div class="workspace-setup-modal is-open" id="workspaceSetup" aria-hidden="false">
            <div class="workspace-setup-backdrop" aria-hidden="true"></div>
            
            <section class="workspace-setup-dialog" role="dialog" aria-modal="true" aria-labelledby="setup-title">
                <!-- Left Panel -->
                <aside class="workspace-setup-side">
                    <img src="../img/roots_symbol_white.png" alt="Roots" class="setup-logo">
                    
                    <h2>Set up your<br>workspace.</h2>
                    
                    <p>Let's get your organization created so you can invite your team and start managing your operations.</p>

                    <ol class="setup-progress" aria-label="Setup progress">
                        <li class="is-active" data-step="1">
                            <span>1</span>
                            <strong>Organization Details</strong>
                        </li>
                        <li data-step="2">
                            <span>2</span>
                            <strong>Industry &amp; Size</strong>
                        </li>
                    </ol>
                </aside>

                <!-- Right Panel - Form -->
                <form class="workspace-setup-form" id="workspaceSetupForm" action="../handler/workspace-setup.php" method="post" enctype="multipart/form-data">
                    
                    <!-- Step 1: Organization Details -->
                    <section class="setup-step is-active" data-setup-step="1">
                        <h2 id="setup-title">Organization Name</h2>
                        <p>What's the name of your company or team?</p>

                        <!-- Logo Upload -->
                        <label class="setup-upload" for="workspaceLogo" id="uploadLabel">
                            <input id="workspaceLogo" name="workspace_logo" type="file" accept="image/png,image/jpeg">
                            <span class="upload-icon" id="uploadIcon">
                                <svg viewBox="0 0 24 24">
                                    <path d="M12 16V8m0 0-3 3m3-3 3 3"></path>
                                    <path d="M6.5 17.5h11a3.5 3.5 0 0 0 .5-7 5.6 5.6 0 0 0-10.8-1.8 4.3 4.3 0 0 0-.7 8.8Z"></path>
                                </svg>
                            </span>
                            <img id="logoPreview" class="upload-preview" style="display: none;">
                        </label>
                        <p class="upload-help">Recommended: JPG or PNG<br>Minimum 400x400px</p>

                        <!-- Workspace Name Input -->
                        <label class="setup-label" for="workspaceName">Workspace Name</label>
                        <div class="setup-input">
                            <svg viewBox="0 0 24 24">
                                <path d="M5 21V7l7-4 7 4v14H5Zm4 0v-4h6v4M9 9h.01M12 9h.01M15 9h.01M9 13h.01M12 13h.01M15 13h.01"></path>
                            </svg>
                            <input id="workspaceName" name="workspace_name" type="text" placeholder="e.g., Acme Corporation" required>
                        </div>
                    </section>

                    <!-- Step 2: Industry & Company Size -->
                    <section class="setup-step" data-setup-step="2">
                        <h2>A bit more detail</h2>
                        <p>Help us customize your dashboard experience.</p>

                        <!-- Industry Dropdown -->
                        <label class="setup-label" for="workspaceIndustry">Industry</label>
                        <div class="setup-input setup-select">
                            <svg viewBox="0 0 24 24">
                                <path d="M6 8h12v12H6V8Zm3 0V5h6v3m-6 5h6"></path>
                            </svg>
                            <select id="workspaceIndustry" name="industry" required>
                                <option value="retail_ecommerce">Retail / E-Commerce</option>
                                <option value="service_business">Service Business</option>
                                <option value="manufacturing">Manufacturing</option>
                                <option value="finance_accounting">Finance &amp; Accounting</option>
                                <option value="technology">Technology</option>
                                <option value="healthcare">Healthcare</option>
                                <option value="education">Education</option>
                                <option value="other">Other</option>
                            </select>
                        </div>

                        <!-- Company Size Radio Buttons -->
                        <fieldset class="company-size">
                            <legend>Company Size</legend>
                            <label>
                                <input type="radio" name="company_size" value="1-10" checked>
                                <span><strong>1 - 10</strong> People</span>
                            </label>
                            <label>
                                <input type="radio" name="company_size" value="11-50">
                                <span><strong>11 - 50</strong> People</span>
                            </label>
                            <label>
                                <input type="radio" name="company_size" value="51-100">
                                <span><strong>51 - 100</strong> People</span>
                            </label>
                            <label>
                                <input type="radio" name="company_size" value="201+">
                                <span><strong>201+</strong> People</span>
                            </label>
                        </fieldset>
                    </section>

                    <!-- Error/Success Messages -->
                    <?php if ($setupError): ?>
                        <p class="setup-note is-error"><?= htmlspecialchars($setupError); ?></p>
                    <?php endif; ?>
                    <p class="setup-note" id="workspaceSetupNote"></p>

                    <!-- Navigation Buttons -->
                    <div class="setup-actions">
                        <button class="setup-back" type="button" data-setup-back style="display: none;">Back</button>
                        <button class="setup-continue" type="button" data-setup-next>
                            <span>Continue</span>
                            <svg viewBox="0 0 24 24">
                                <path d="M5 12h14m0 0-6-6m6 6-6 6"></path>
                            </svg>
                        </button>
                    </div>
                </form>
            </section>
        </div>
    <?php endif; ?>

    <script src="../script.js?v=<?= filemtime(__DIR__ . '/../script.js'); ?>"></script>
    
    <!-- Workspace Dropdown, User Dropdown & Notification Scripts -->
    <script>
    (function() {
        // === Workspace Dropdown ===
        var workspaceToggle = document.getElementById('workspaceToggle');
        var workspaceDropdown = document.getElementById('workspaceDropdown');
        
        if (workspaceToggle && workspaceDropdown) {
            workspaceToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                workspaceDropdown.classList.toggle('is-visible');
                // Close other dropdowns
                userDropdown && userDropdown.classList.remove('is-visible');
                notifPopup && notifPopup.classList.remove('is-visible');
            });
            
            document.addEventListener('click', function(e) {
                if (!workspaceDropdown.contains(e.target) && !workspaceToggle.contains(e.target)) {
                    workspaceDropdown.classList.remove('is-visible');
                }
            });
            
            workspaceDropdown.querySelectorAll('.dropdown-item[data-workspace-id]').forEach(function(item) {
                item.addEventListener('click', function() {
                    var workspaceId = this.getAttribute('data-workspace-id');
                    window.location.href = 'index.php?workspace_id=' + workspaceId;
                });
            });
            
            var newWorkspaceBtn = workspaceDropdown.querySelector('.dropdown-new-workspace');
            if (newWorkspaceBtn) {
                newWorkspaceBtn.addEventListener('click', function() {
                    var modal = document.getElementById('workspaceSetup');
                    if (modal) {
                        modal.classList.add('is-open');
                        document.body.classList.add('workspace-setup-open');
                    } else {
                        window.location.href = 'index.php?create_workspace=1';
                    }
                });
            }
        }
        
        // === User Profile Dropdown ===
        var userProfileToggle = document.getElementById('userProfileToggle');
        var userDropdown = document.getElementById('userDropdown');
        
        if (userProfileToggle && userDropdown) {
            userProfileToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                userDropdown.classList.toggle('is-visible');
                // Close other dropdowns
                workspaceDropdown && workspaceDropdown.classList.remove('is-visible');
                notifPopup && notifPopup.classList.remove('is-visible');
            });
            
            document.addEventListener('click', function(e) {
                if (!userDropdown.contains(e.target) && !userProfileToggle.contains(e.target)) {
                    userDropdown.classList.remove('is-visible');
                }
            });
        }
        
        // === Notification Popup ===
        var notifBtn = document.getElementById('notifBtn');
        var notifPopup = document.getElementById('notifPopup');
        var notifClose = document.getElementById('notifClose');
        
        if (notifBtn && notifPopup) {
            notifBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                notifPopup.classList.toggle('is-visible');
                // Close other dropdowns
                workspaceDropdown && workspaceDropdown.classList.remove('is-visible');
                userDropdown && userDropdown.classList.remove('is-visible');
            });
            
            if (notifClose) {
                notifClose.addEventListener('click', function() {
                    notifPopup.classList.remove('is-visible');
                });
            }
            
            document.addEventListener('click', function(e) {
                if (!notifPopup.contains(e.target) && !notifBtn.contains(e.target)) {
                    notifPopup.classList.remove('is-visible');
                }
            });
        }
    })();
    </script>

</body>
</html>
