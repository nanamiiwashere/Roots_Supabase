<?php
// Prevent caching for security
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/../connect.php';
require_once __DIR__ . '/../auth/auth-handler.php';
require_once __DIR__ . '/../auth/auth3thparty.php';
require_once __DIR__ . '/../handler/analytics-data.php';
require_once __DIR__ . '/../handler/supabase-img.php'; // KUNCI: Panggil handler gambar Supabase di sini

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
// $avatar and $workspaceLogo are handled by sidebar.php for correct path resolution
$setupError = $_SESSION['setup_error'] ?? '';
$setupSuccess = $_SESSION['setup_success'] ?? '';
unset($_SESSION['setup_error']);
unset($_SESSION['setup_success']);

// Load analytics data for the active workspace (empty until real data exists)
$analytics = [
    'metrics'  => [],
    'monthly'  => [],
    'activity' => [],
    'reviews'  => [],
];
if ($workspace) {
    try {
        $analytics = getAnalyticsData($pdo, (int) $workspace['id']);
    } catch (Exception $e) {
        // Keep empty defaults on failure; dashboard shows empty states
    }
}

// Summary cards (order matches the mockup: Revenue, Total Sales, Total Orders, Total Profit)
$metrics = $analytics['metrics'];
$revenueMetric = $metrics['revenue'] ?? null;
$salesMetric   = $metrics['sales'] ?? null;
$ordersMetric  = $metrics['orders'] ?? null;
$profitMetric  = $metrics['profit'] ?? null;

// Chart series
$chartMonths  = array_map(static fn($r) => $r['month_label'], $analytics['monthly']);
$chartSales   = array_map(static fn($r) => (int) $r['sales'], $analytics['monthly']);
$chartRevenue = array_map(static fn($r) => (float) $r['revenue'], $analytics['monthly']);

$hasSalesChart   = !empty($analytics['monthly']) && count($chartSales) > 0;
$hasRevenueChart = !empty($analytics['monthly']) && count($chartRevenue) > 0;

$analyticsPayload = [
    'months'  => $chartMonths,
    'sales'   => $chartSales,
    'revenue' => $chartRevenue,
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Roots ERP</title>
    <meta name="description" content="Dashboard Roots ERP untuk analytics, customers, orders, inventory, finance, dan reports.">
    <link rel="icon" href="<?= supabaseImageUrl('img/roots_symbol.png'); ?>" type="image/png">
    <link rel="stylesheet" href="../dashboard/partials/style.css?v=<?= filemtime(__DIR__ . '/partials/style.css'); ?>">
    <style>
        /* SVG gradient for chart */
        .chart-gradient-defs { position: absolute; width: 0; height: 0; }
    </style>
</head>
<body class="<?= $showSetupModal ? 'workspace-setup-open' : ''; ?>">
    
    <svg class="chart-gradient-defs" aria-hidden="true">
        <defs>
            <linearGradient id="chartGradient" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="#54d9aa" stop-opacity="0.3"/>
                <stop offset="100%" stop-color="#54d9aa" stop-opacity="0"/>
            </linearGradient>
        </defs>
    </svg>

    <main class="dashboard-shell" aria-label="Roots dashboard">
        
        <?php 
        $currentPage = 'analytics';
        include __DIR__ . '/partials/sidebar.php'; 
        ?>

        <section class="dashboard-main">
            
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
                    <div class="notif-popup" id="notifPopup">
                        <div class="notif-popup-header">
                            <strong>Notifications</strong>
                            <button type="button" class="notif-close" id="notifClose">&times;</button>
                        </div>
                        <div class="notif-popup-body">
                            <p class="notif-empty">No new notifications</p>
                        </div>
                    </div>
                    <div class="topbar-user">
                        <button type="button" class="topbar-avatar-btn" id="topbarAvatar" aria-label="Account menu" aria-haspopup="true" aria-expanded="false">
                            <img src="<?= htmlspecialchars($avatar, ENT_QUOTES, 'UTF-8'); ?>" alt="User avatar" class="topbar-avatar">
                            <svg class="topbar-avatar-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                        </button>
                        <div class="topbar-dropdown" id="topbarDropdown">
                            <div class="topbar-dropdown-header">
                                <img src="<?= htmlspecialchars($avatar, ENT_QUOTES, 'UTF-8'); ?>" alt="" class="topbar-dropdown-avatar">
                                <div class="topbar-dropdown-info">
                                    <strong><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></strong>
                                    <span><?= htmlspecialchars($currentUser['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                            </div>
                            <a href="settings.php" class="topbar-dropdown-item">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                                <span>Settings</span>
                            </a>
                            <a href="../auth/logout.php" class="topbar-dropdown-item topbar-dropdown-logout">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                                <span>Log Out</span>
                            </a>
                        </div>
                    </div>
                </div>
            </header>

            <div class="dashboard-content">
                <h1>Welcome, <?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?>!</h1>

                <?php if ($setupSuccess): ?>
                    <div class="alert alert-success" role="alert">
                        <?= htmlspecialchars($setupSuccess, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <section class="dashboard-upgrade">
                    <div class="dashboard-upgrade-text">
                        <span class="dashboard-upgrade-badge">
                            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l2.9 6.3 6.9.7-5.1 4.6 1.4 6.8L12 17.8 5.9 20.4l1.4-6.8L2.2 9l6.9-.7L12 2z"/></svg>
                            Unlock Premium Features
                        </span>
                        <h2>Upgrade to Pro for AI Analytics &amp; Bank Integrations.</h2>
                    </div>
                    <a href="../pricing.php" class="upgrade-btn">Upgrade Now</a>
                    <span class="dashboard-upgrade-decor" aria-hidden="true"></span>
                </section>

                <div class="analytics-overview-head">
                    <h2>Overview</h2>
                    <button type="button" class="analytics-date-btn" aria-label="Date range">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    </button>
                </div>

                <div class="analytics-stats">
                    <article class="analytics-stat-card">
                        <span class="analytics-stat-head">
                            <span class="analytics-stat-ico">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                            </span>
                            <span>Revenue</span>
                        </span>
                        <strong><?= htmlspecialchars(formatRupiahShort($revenueMetric['metric_value'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></strong>
                        <?= renderMetricChange($revenueMetric); ?>
                    </article>

                    <article class="analytics-stat-card">
                        <span class="analytics-stat-head">
                            <span class="analytics-stat-ico">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg>
                            </span>
                            <span>Total Sales</span>
                        </span>
                        <strong><?= $salesMetric ? number_format((float) $salesMetric['metric_value'], 0, ',', '.') : '0'; ?></strong>
                        <?= renderMetricChange($salesMetric); ?>
                    </article>

                    <article class="analytics-stat-card">
                        <span class="analytics-stat-head">
                            <span class="analytics-stat-ico">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                            </span>
                            <span>Total Orders</span>
                        </span>
                        <strong><?= $ordersMetric ? number_format((float) $ordersMetric['metric_value'], 0, ',', '.') : '0'; ?></strong>
                        <?= renderMetricChange($ordersMetric); ?>
                    </article>

                    <article class="analytics-stat-card">
                        <span class="analytics-stat-head">
                            <span class="analytics-stat-ico">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/></svg>
                            </span>
                            <span>Total Profit</span>
                        </span>
                        <strong><?= htmlspecialchars(formatRupiahShort($profitMetric['metric_value'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></strong>
                        <?= renderMetricChange($profitMetric); ?>
                    </article>
                </div>

                <div class="analytics-charts">
                    <article class="analytics-chart-card analytics-chart-sales">
                        <div class="analytics-chart-head">
                            <div>
                                <span class="analytics-chart-label">Total Sales</span>
                                <strong class="analytics-chart-value"><?= $salesMetric ? number_format((float) $salesMetric['metric_value'], 0, ',', '.') : '0'; ?></strong>
                                <?= renderMetricChange($salesMetric); ?>
                            </div>
                        </div>
                        <?php if ($hasSalesChart): ?>
                            <div class="analytics-chart-wrap">
                                <canvas id="salesChart" aria-label="Total sales bar chart" role="img"></canvas>
                            </div>
                        <?php else: ?>
                            <div class="analytics-chart-empty">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><rect x="7" y="12" width="3" height="6"/><rect x="12" y="8" width="3" height="10"/><rect x="17" y="14" width="3" height="4"/></svg>
                                <p>No sales data yet. Your chart will appear here once orders come in.</p>
                            </div>
                        <?php endif; ?>
                    </article>

                    <article class="analytics-chart-card analytics-chart-revenue">
                        <div class="analytics-chart-head">
                            <div>
                                <span class="analytics-chart-label">Total Revenue</span>
                                <strong class="analytics-chart-value"><?= htmlspecialchars(formatRupiahShort($revenueMetric['metric_value'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></strong>
                                <?= renderMetricChange($revenueMetric); ?>
                            </div>
                        </div>
                        <?php if ($hasRevenueChart): ?>
                            <div class="analytics-chart-wrap">
                                <canvas id="revenueChart" aria-label="Total revenue area chart" role="img"></canvas>
                            </div>
                        <?php else: ?>
                            <div class="analytics-chart-empty">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><polyline points="6 15 10 10 14 13 19 6"/></svg>
                                <p>No revenue data yet. Your trend line will appear here once you record sales.</p>
                            </div>
                        <?php endif; ?>
                    </article>
                </div>

                <div class="analytics-feeds">
                    <article class="dashboard-card activity-card">
                        <h2>Recent Activity</h2>
                        <?php if (!empty($analytics['activity'])): ?>
                            <?php 
                            $limitCount = 0;
                            foreach ($analytics['activity'] as $act): 
                                if ($limitCount >= 2) break;
                                $actType = $act['activity_type'] ?? 'order';
                                $iconClass = $actType === 'finance' ? 'activity-icon-finance' : 'activity-icon-order';
                                
                                // Format time
                                $actTime = '';
                                if (!empty($act['activity_date'])) {
                                    $actTime = date('d M Y', strtotime($act['activity_date']));
                                } elseif (!empty($act['created_at'])) {
                                    $actTime = date('d M Y', strtotime($act['created_at']));
                                }
                                
                                // Icon SVG based on type
                                $iconSvg = $actType === 'finance' 
                                    ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M2 10h20"/></svg>'
                                    : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>';
                            ?>
                                <div class="activity-item <?= $actType === 'finance' ? 'is-finance' : 'is-order' ?>">
                                    <div class="activity-icon-wrapper <?= $iconClass ?>"><?= $iconSvg ?></div>
                                    <div class="activity-content">
                                        <div class="activity-title"><?= htmlspecialchars($act['title'], ENT_QUOTES, 'UTF-8'); ?></div>
                                        <?php if (!empty($act['status'])): ?>
                                            <span class="activity-status"><?= htmlspecialchars($act['status'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php endif; ?>
                                        <div class="activity-meta">
                                            <span class="activity-code"><?= htmlspecialchars($act['code'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                                            <?php if ($actTime): ?>
                                                <span class="activity-time"><?= htmlspecialchars($actTime, ENT_QUOTES, 'UTF-8'); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php 
                                $limitCount++;
                            endforeach; ?>
                        <?php else: ?>
                            <p class="no-data-message">No activity yet. Start using Roots to see your activity here.</p>
                        <?php endif; ?>
                    </article>

                    <article class="dashboard-card review-card">
                        <h2>Product Reviews</h2>
                        <?php if (!empty($analytics['reviews'])): ?>
                            <?php foreach ($analytics['reviews'] as $rev): ?>
                                <?php $rating = max(0, min(5, (int) $rev['rating'])); ?>
                                <p>
                                    <span></span>
                                    <span class="review-body">
                                        <strong><?= htmlspecialchars($rev['reviewer_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <span class="review-stars" aria-label="<?= $rating; ?> out of 5">
                                            <?= str_repeat('★', $rating) . str_repeat('☆', 5 - $rating); ?>
                                        </span>
                                        <small><?= htmlspecialchars($rev['comment'] ?? '', ENT_QUOTES, 'UTF-8'); ?></small>
                                    </span>
                                </p>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="no-data-message">No reviews yet.</p>
                        <?php endif; ?>
                    </article>

                    <article class="dashboard-card support-card">
                        <span class="support-card-ico" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 18v-6a9 9 0 0 1 18 0v6"/><path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"/></svg>
                        </span>
                        <h2>Need help?</h2>
                        <p>Our support team is ready to assist you with any inquiries.</p>
                    </article>
                </div>
            </div>
        </section>
    </main>

    <?php if ($showSetupModal): ?>
        <div class="workspace-setup-modal is-open" id="workspaceSetup" aria-hidden="false">
            <div class="workspace-setup-backdrop" aria-hidden="true"></div>
            
            <section class="workspace-setup-dialog" role="dialog" aria-modal="true" aria-labelledby="setup-title">
                <aside class="workspace-setup-side">
                    <img src="<?= supabaseImageUrl('img/roots_symbol_white.png'); ?>" alt="Roots" class="setup-logo">
                    
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

                <form class="workspace-setup-form" id="workspaceSetupForm" action="../handler/workspace-setup.php" method="post" enctype="multipart/form-data">
                    
                    <section class="setup-step is-active" data-setup-step="1">
                        <h2 id="setup-title">Organization Name</h2>
                        <p>What's the name of your company or team?</p>

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

                        <label class="setup-label" for="workspaceName">Workspace Name</label>
                        <div class="setup-input">
                            <svg viewBox="0 0 24 24">
                                <path d="M5 21V7l7-4 7 4v14H5Zm4 0v-4h6v4M9 9h.01M12 9h.01M15 9h.01M9 13h.01M12 13h.01M15 13h.01"></path>
                            </svg>
                            <input id="workspaceName" name="workspace_name" type="text" placeholder="e.g., Acme Corporation" required>
                        </div>
                    </section>

                    <section class="setup-step" data-setup-step="2">
                        <h2>A bit more detail</h2>
                        <p>Help us customize your dashboard experience.</p>

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

                    <?php if ($setupError): ?>
                        <p class="setup-note is-error"><?= htmlspecialchars($setupError); ?></p>
                    <?php endif; ?>
                    <p class="setup-note" id="workspaceSetupNote"></p>

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

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="../script.js?v=<?= filemtime(__DIR__ . '/../script.js'); ?>"></script>

    <script>
    (function() {
        if (typeof Chart === 'undefined') return;
        var data = <?= json_encode($analyticsPayload, JSON_UNESCAPED_UNICODE); ?>;

        var salesCanvas = document.getElementById('salesChart');
        if (salesCanvas && data.sales && data.sales.length) {
            new Chart(salesCanvas, {
                type: 'bar',
                data: {
                    labels: data.months,
                    datasets: [{
                        label: 'Sales',
                        data: data.sales,
                        backgroundColor: '#f5b301',
                        borderRadius: 6,
                        maxBarThickness: 30
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { grid: { display: false }, border: { display: false } },
                        y: { beginAtZero: true, grid: { color: '#eef1f4' }, border: { display: false }, ticks: { maxTicksLimit: 5 } }
                    }
                }
            });
        }

        var revenueCanvas = document.getElementById('revenueChart');
        if (revenueCanvas && data.revenue && data.revenue.length) {
            var ctx = revenueCanvas.getContext('2d');
            var grad = ctx.createLinearGradient(0, 0, 0, 220);
            grad.addColorStop(0, 'rgba(84, 217, 170, 0.35)');
            grad.addColorStop(1, 'rgba(84, 217, 170, 0)');
            new Chart(revenueCanvas, {
                type: 'line',
                data: {
                    labels: data.months,
                    datasets: [{
                        label: 'Revenue',
                        data: data.revenue,
                        borderColor: '#20c78f',
                        backgroundColor: grad,
                        fill: true,
                        tension: 0.35,
                        pointRadius: 3,
                        pointBackgroundColor: '#20c78f',
                        borderWidth: 2.5
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { grid: { display: false }, border: { display: false } },
                        y: { beginAtZero: true, grid: { color: '#eef1f4' }, border: { display: false }, ticks: { maxTicksLimit: 5 } }
                    }
                }
            });
        }
    })();
    </script>
    
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
                topbarDropdown && topbarDropdown.classList.remove('is-visible');
                topbarAvatar && topbarAvatar.setAttribute('aria-expanded', 'false');
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
        
        // === Topbar Account Menu ===
        var topbarAvatar = document.getElementById('topbarAvatar');
        var topbarDropdown = document.getElementById('topbarDropdown');
        
        if (topbarAvatar && topbarDropdown) {
            topbarAvatar.addEventListener('click', function(e) {
                e.stopPropagation();
                var isOpen = topbarDropdown.classList.toggle('is-visible');
                topbarAvatar.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                // Close other dropdowns
                workspaceDropdown && workspaceDropdown.classList.remove('is-visible');
                notifPopup && notifPopup.classList.remove('is-visible');
            });
            
            document.addEventListener('click', function(e) {
                if (!topbarDropdown.contains(e.target) && !topbarAvatar.contains(e.target)) {
                    topbarDropdown.classList.remove('is-visible');
                    topbarAvatar.setAttribute('aria-expanded', 'false');
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
                topbarDropdown && topbarDropdown.classList.remove('is-visible');
                topbarAvatar && topbarAvatar.setAttribute('aria-expanded', 'false');
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

    document.getElementById('workspaceLogo').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        const preview = document.getElementById('logoPreview');
        const icon = document.getElementById('uploadIcon');
        
        reader.onload = function(event) {
            preview.src = event.target.result;
            preview.style.display = 'block';
            icon.style.display = 'none'; // Sembunyiin icon upload pas gambar muncul
        }
        reader.readAsDataURL(file);
    }
});
        
    </script>

</body>
</html>
