<?php
// Prevent caching for security
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/../connect.php';
require_once __DIR__ . '/../auth/auth-handler.php';
require_once __DIR__ . '/../auth/auth3thparty.php';
require_once __DIR__ . '/../handler/reports-data.php';

// Require authentication
requireLogin();
$currentUser = currentUser();

if (!$currentUser) {
    header('Location: ../login.php');
    exit;
}

// Resolve active workspace (same logic as the dashboard)
$workspace = null;
$requestedWorkspaceId = isset($_GET['workspace_id']) ? (int) $_GET['workspace_id'] : null;
if ($requestedWorkspaceId) {
    $_SESSION['active_workspace_id'] = $requestedWorkspaceId;
}
$activeWorkspaceId = $_SESSION['active_workspace_id'] ?? null;

try {
    if ($activeWorkspaceId) {
        $stmt = $pdo->prepare(
            'SELECT w.* FROM workspaces w
             INNER JOIN workspace_members wm ON wm.workspace_id = w.id
             WHERE wm.user_id = ? AND w.id = ? AND w.setup_completed_at IS NOT NULL
             LIMIT 1'
        );
        $stmt->execute([$currentUser['id_user'], $activeWorkspaceId]);
        $workspace = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$workspace) {
            unset($_SESSION['active_workspace_id']);
        }
    }

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
        if ($workspace) {
            $_SESSION['active_workspace_id'] = $workspace['id'];
        }
    }
} catch (Exception $e) {
    $workspace = null;
}

if (!$workspace) {
    header('Location: index.php');
    exit;
}

$workspaceId = (int) $workspace['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_target'){
    $newTarget = (float) str_replace(['Rp', '.', ' '], '', $_POST['monthly_target'] ?? '0');
    try {
        updateWorkspaceTarget($pdo, $workspaceId, $newTarget);
        $_SESSION['update_success'] = true;
        header('Location: reports.php');
        exit;
    } catch (Exception $e) {
        $dataError = true;
    }
}

// Load all report datasets (defensive: empty if modules have no data yet)
$dataError = false;
$report = [
    'revenueProfit' => ['labels' => [], 'revenue' => [], 'profit' => []],
    'salesProduct'  => ['labels' => [], 'values' => [], 'colors' => []],
    'monthly'       => ['labels' => [], 'actual' => [], 'target' => []],
    'kpis'          => [],
];
try {
    $report = getReportData($pdo, $workspaceId);
} catch (Exception $e) {
    $dataError = true;
}

$hasRevenueProfit = reportsHasData($report['revenueProfit']['revenue']) || reportsHasData($report['revenueProfit']['profit']);
$hasSalesProduct  = !empty($report['salesProduct']['values']);
$hasMonthly       = reportsHasData($report['monthly']['actual']) || reportsHasData($report['monthly']['target']);

// Display data for the sidebar/topbar
$displayName   = $currentUser['name'] ?: 'User';
$workspaceName = $workspace['name'] ?? 'My Workspace';
// $avatar and $workspaceLogo are handled by sidebar.php for correct path resolution
$currentPage   = 'reports';

// AI summary is now loaded asynchronously via AJAX for faster page load
// Only available for Pro, Scale, and Admin members
$aiSummaryEnabled = in_array($currentUser['membership'], ['pro', 'scale', 'admin']);

// Print mode renders a clean, printable layout for "Save as PDF".
$printMode = isset($_GET['print']) && $_GET['print'] === '1';

$chartPayload = json_encode([
    'rp'      => $report['revenueProfit'],
    'sales'   => $report['salesProduct'],
    'monthly' => $report['monthly'],
], JSON_UNESCAPED_UNICODE);

$generatedAt = date('d M Y, H:i');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Roots ERP</title>
    <meta name="description" content="Business reports for Roots ERP: revenue vs profit, sales by product, monthly targets and key metrics.">
    <link rel="icon" href="../img/roots_symbol.png?v=<?= filemtime(__DIR__ . '/../img/roots_symbol.png'); ?>" type="image/png">
    <link rel="stylesheet" href="../dashboard/partials/style.css?v=<?= filemtime(__DIR__ . '/partials/style.css'); ?>">
</head>
<body class="<?= $printMode ? 'reports-print-mode' : ''; ?>">

<?php if ($printMode): ?>
    <!-- ============ PRINT / PDF LAYOUT ============ -->
    <div class="report-print">
        <header class="report-print-header">
            <div class="report-print-brand">
                <img src="../img/roots_brand.png" alt="Roots">
                <span>Business Report</span>
            </div>
            <div class="report-print-meta">
                <strong><?= htmlspecialchars($workspaceName, ENT_QUOTES, 'UTF-8'); ?></strong>
                <span>Generated <?= htmlspecialchars($generatedAt, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        </header>

        <h1 class="report-print-title">Business Reports</h1>

        <section class="report-print-grid">
            <article class="report-print-card">
                <h2>Revenue vs Profit (last 4 weeks)</h2>
                <div class="report-chart-wrap"><canvas id="revenueProfitChart"></canvas></div>
            </article>
            <article class="report-print-card">
                <h2>Sales by Product</h2>
                <?php if ($hasSalesProduct): ?>
                    <div class="report-doughnut-wrap"><canvas id="salesProductChart"></canvas></div>
                    <ul class="report-legend">
                        <?php foreach ($report['salesProduct']['labels'] as $i => $lbl): ?>
                            <li>
                                <span class="legend-dot" style="background: <?= htmlspecialchars($report['salesProduct']['colors'][$i] ?? '#54d9aa', ENT_QUOTES, 'UTF-8'); ?>"></span>
                                <span class="legend-name"><?= htmlspecialchars($lbl, ENT_QUOTES, 'UTF-8'); ?></span>
                                <strong class="legend-value"><?= (int) ($report['salesProduct']['values'][$i] ?? 0); ?></strong>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="report-empty-text">No product sales recorded yet.</p>
                <?php endif; ?>
            </article>
        </section>

        <section class="report-print-grid">
            <article class="report-print-card">
                <h2>Monthly Target vs Actual</h2>
                <div class="report-chart-wrap"><canvas id="monthlyTargetChart"></canvas></div>
            </article>
            <article class="report-print-card">
                <h2>Key Metrics</h2>
                <div class="report-print-kpis">
                    <?php foreach ($report['kpis'] as $kpi): ?>
                        <div class="report-print-kpi">
                            <span class="report-print-kpi-label"><?= htmlspecialchars($kpi['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <strong><?= htmlspecialchars($kpi['value'], ENT_QUOTES, 'UTF-8'); ?></strong>
                            <small><?= htmlspecialchars($kpi['subtitle'], ENT_QUOTES, 'UTF-8'); ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
            </article>
        </section>

        <footer class="report-print-footer">
            <span>Roots ERP &middot; <?= htmlspecialchars($workspaceName, ENT_QUOTES, 'UTF-8'); ?></span>
            <span>This report was generated automatically from recorded business data.</span>
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
    (function() {
        var data = <?= $chartPayload; ?>;
        function build() {
            if (typeof Chart === 'undefined') return;
            Chart.defaults.animation = false;
            renderReportCharts(data);
            // Give Chart.js a tick to paint before printing.
            setTimeout(function() { window.print(); }, 600);
        }
        <?php /* shared render fn defined below via renderReportCharts */ ?>
        window.addEventListener('load', build);
    })();
    </script>
    <?php include __DIR__ . '/partials/reports-charts.php'; ?>
    </body></html>
    <?php exit; endif; ?>

    <!-- ============ NORMAL DASHBOARD LAYOUT ============ -->
    <main class="dashboard-shell" aria-label="Roots dashboard">

        <!-- Sidebar (Partial) -->
        <?php include __DIR__ . '/partials/sidebar.php'; ?>

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
                    <strong>Reports</strong>
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
                    <!-- Account Menu -->
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

            <!-- Reports Content -->
            <div class="dashboard-content">

                <!-- Page Header -->
                <div class="customers-header">
                    <div>
                        <h1>Business Reports</h1>
                        <p>Turn your business data into actionable reports, instantly</p>
                    </div>
                    <div class="reports-header-actions">
                        <button type="button" class="customers-date-btn" aria-label="Date range">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        </button>
                        <button type="button" class="customers-add-btn" id="downloadReportBtn">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                            <span>Download Report</span>
                        </button>
                    </div>
                </div>

<?php if (isset($_SESSION['update_success']) && $_SESSION['update_success']): ?>
    <div class="alert alert-success" role="alert" style="margin-top: 1rem;">Monthly target has been updated successfully.</div>
    <?php unset($_SESSION['update_success']); ?>
<?php endif; ?>

                <?php if ($dataError): ?>
                    <div class="alert alert-error" role="alert">Unable to load report data right now. Please try again later.</div>
                <?php endif; ?>

                <!-- Charts grid -->
                <div class="reports-grid">

                    <!-- Revenue vs Profit -->
                    <article class="report-card report-card-line">
                        <h2>Revenue vs Profit</h2>
                        <?php if ($hasRevenueProfit): ?>
                            <div class="report-chart-wrap"><canvas id="revenueProfitChart" role="img" aria-label="Revenue versus profit chart"></canvas></div>
                        <?php else: ?>
                            <div class="analytics-chart-empty">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><polyline points="6 15 10 10 14 13 19 6"/></svg>
                                <p>No finance data yet. Record income &amp; expenses to see this chart.</p>
                            </div>
                        <?php endif; ?>
                    </article>

                    <!-- Sales by Product -->
                    <article class="report-card report-card-doughnut">
                        <h2>Sales by Product</h2>
                        <?php if ($hasSalesProduct): ?>
                            <div class="report-doughnut-wrap"><canvas id="salesProductChart" role="img" aria-label="Sales by product chart"></canvas></div>
                            <ul class="report-legend">
                                <?php foreach ($report['salesProduct']['labels'] as $i => $lbl): ?>
                                    <li>
                                        <span class="legend-dot" style="background: <?= htmlspecialchars($report['salesProduct']['colors'][$i] ?? '#54d9aa', ENT_QUOTES, 'UTF-8'); ?>"></span>
                                        <span class="legend-name"><?= htmlspecialchars($lbl, ENT_QUOTES, 'UTF-8'); ?></span>
                                        <strong class="legend-value"><?= (int) ($report['salesProduct']['values'][$i] ?? 0); ?></strong>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <div class="analytics-chart-empty">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 3a9 9 0 0 1 9 9"/></svg>
                                <p>No product sales yet. Add orders with items to see this breakdown.</p>
                            </div>
                        <?php endif; ?>
                    </article>

                    <!-- Monthly Target vs Actual -->
                    <article class="report-card report-card-bar">
                        <div class="report-card-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                            <h2 style="margin: 0; font-weight: 700;">Monthly Target vs Actual</h2>
                            <button type="button" class="action-btn" id="editTargetBtn">
                                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                            </button>
                        </div>
                        <?php if ($hasMonthly): ?>
                            <div class="report-chart-wrap" style="margin-top: 0; height: 180px;"><canvas id="monthlyTargetChart" role="img" aria-label="Monthly target versus actual chart"></canvas></div>
                        <?php else: ?>
                            <div class="analytics-chart-empty">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><rect x="7" y="12" width="3" height="6"/><rect x="12" y="8" width="3" height="10"/><rect x="17" y="14" width="3" height="4"/></svg>
                                <p>No monthly income yet. Record transactions to compare actual vs target.</p>
                            </div>
                        <?php endif; ?>
                    </article>

                    <!-- KPI Metrics -->
                    <aside class="report-card report-card-kpis">
                        <?php foreach ($report['kpis'] as $kpi): ?>
                            <div class="kpi-item kpi-trend-<?= htmlspecialchars($kpi['trend'], ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="kpi-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?= reportKpiIcon($kpi['key']); ?></svg>
                                </span>
                                <div class="kpi-text">
                                    <span class="kpi-label"><?= htmlspecialchars($kpi['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <strong class="kpi-value"><?= htmlspecialchars($kpi['value'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                    <small class="kpi-subtitle"><?= htmlspecialchars($kpi['subtitle'], ENT_QUOTES, 'UTF-8'); ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <!-- AI Business Summary (loaded asynchronously) -->
                        <?php if ($aiSummaryEnabled): ?>
                            <div class="ai-summary-section" id="aiSummarySection">
                                <div class="ai-summary-header">
                                    <svg class="ai-summary-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M12 2a4 4 0 0 1 4 4c0 1.1-.9 2-2 2h-4a2 2 0 0 1-2-2 4 4 0 0 1 4-4z"/>
                                        <path d="M12 8v4"/>
                                        <path d="M8 12h8"/>
                                        <path d="M5 16h14"/>
                                        <path d="M7 20h10"/>
                                        <circle cx="12" cy="14" r="1"/>
                                    </svg>
                                    <span>AI Business Analysis</span>
                                </div>
                                <div class="ai-summary-body" id="aiSummaryBody">
                                    <div class="ai-summary-loading">
                                        <div class="ai-loading-spinner"></div>
                                        <span>Generating analysis...</span>
                                    </div>
                                </div>
                                <div class="ai-summary-footer">
                                    <span>Powered by Roots AI</span>
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- Pro Upgrade Prompt for AI Summary -->
                            <div class="ai-summary-section ai-summary-locked" id="aiSummarySection">
                                <div class="ai-summary-header">
                                    <svg class="ai-summary-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M12 2a4 4 0 0 1 4 4c0 1.1-.9 2-2 2h-4a2 2 0 0 1-2-2 4 4 0 0 1 4-4z"/>
                                        <path d="M12 8v4"/>
                                        <path d="M8 12h8"/>
                                        <path d="M5 16h14"/>
                                        <path d="M7 20h10"/>
                                        <circle cx="12" cy="14" r="1"/>
                                    </svg>
                                    <span>AI Business Analysis</span>
                                    <span class="ai-pro-badge">PRO</span>
                                </div>
                                <div class="ai-summary-body ai-locked-body">
                                    <svg class="ai-lock-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                    </svg>
                                    <h3>Unlock AI Analysis</h3>
                                    <p>Get intelligent business insights powered by AI. Upgrade to Pro to unlock this feature.</p>
                                    <a href="../pricing.php" class="ai-upgrade-btn">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l2.9 6.3 6.9.7-5.1 4.6 1.4 6.8L12 17.8 5.9 20.4l1.4-6.8L2.2 9l6.9-.7L12 2z"/></svg>
                                        <span>Upgrade to Pro</span>
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </aside>
                </div>
            </div>
        </section>
    </main>

    <!-- Edit Target Modal -->
    <div class="customer-modal" id="editTargetModal" aria-hidden="true">
        <div class="customer-modal-backdrop" id="editTargetBackdrop"></div>
        <div class="customer-modal-dialog">
            <div class="customer-modal-head">
                <h2>Set Monthly Target</h2>
                <button type="button" class="customer-modal-close" id="closeTargetModal">&times;</button>
            </div>
            <form method="post" action="reports.php" class="customer-modal-form">
                <p style="margin: 0 0 16px 0; font-size: 13px; color: #6f7681;">Set your revenue goal to compare against actual sales.</p>
                
                <input type="hidden" name="action" value="update_target">
                
                <div class="customer-field">
                    <label for="monthlyTargetInput">Target Amount (Rp)</label>
                    <input id="monthlyTargetInput" name="monthly_target" type="text" value="<?= number_format($report['monthly']['currentTarget'] ?? 0, 0, '', ''); ?>" placeholder="e.g. 50000000" required>
                </div>
                
                <div class="customer-modal-actions">
                    <button type="button" class="customer-btn-cancel" id="cancelTargetBtn">Cancel</button>
                    <button type="submit" class="customer-btn-save">Save Target</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="../script.js?v=<?= filemtime(__DIR__ . '/../script.js'); ?>"></script>

    <!-- Define renderReportCharts BEFORE it is called below -->
    <?php include __DIR__ . '/partials/reports-charts.php'; ?>

    <!-- Render charts -->
    <script>
    (function() {
        var data = <?= $chartPayload; ?>;
        if (typeof Chart !== 'undefined') {
            renderReportCharts(data);
        }

        // Download as PDF: open print-mode in a new tab (auto-triggers print).
        var dl = document.getElementById('downloadReportBtn');
        if (dl) {
            dl.addEventListener('click', function() {
                window.open('reports.php?print=1', '_blank');
            });
        }
    })();
    </script>

    <!-- Dropdown / topbar / notif scripts -->
    <script>
    (function() {
        var workspaceToggle = document.getElementById('workspaceToggle');
        var workspaceDropdown = document.getElementById('workspaceDropdown');
        var topbarAvatar = document.getElementById('topbarAvatar');
        var topbarDropdown = document.getElementById('topbarDropdown');
        var notifBtn = document.getElementById('notifBtn');
        var notifPopup = document.getElementById('notifPopup');
        var notifClose = document.getElementById('notifClose');

        if (workspaceToggle && workspaceDropdown) {
            workspaceToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                workspaceDropdown.classList.toggle('is-visible');
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
                    window.location.href = 'reports.php?workspace_id=' + this.getAttribute('data-workspace-id');
                });
            });
            var newWorkspaceBtn = workspaceDropdown.querySelector('.dropdown-new-workspace');
            if (newWorkspaceBtn) {
                newWorkspaceBtn.addEventListener('click', function() {
                    window.location.href = 'index.php?create_workspace=1';
                });
            }
        }

        if (topbarAvatar && topbarDropdown) {
            topbarAvatar.addEventListener('click', function(e) {
                e.stopPropagation();
                var isOpen = topbarDropdown.classList.toggle('is-visible');
                topbarAvatar.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
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

        if (notifBtn && notifPopup) {
            notifBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                notifPopup.classList.toggle('is-visible');
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

        var mobileMenuBtn = document.getElementById('mobileMenuBtn');
        var sidebar = document.getElementById('sidebar');
        if (mobileMenuBtn && sidebar) {
            mobileMenuBtn.addEventListener('click', function() {
                sidebar.classList.toggle('is-mobile-open');
            });
        }
    })();

    // Edit Target Modal Logic
    (function() {
        var editTargetBtn = document.getElementById('editTargetBtn');
        var editTargetModal = document.getElementById('editTargetModal');
        var editTargetBackdrop = document.getElementById('editTargetBackdrop');
        var cancelTargetBtn = document.getElementById('cancelTargetBtn');
        var closeTargetModal = document.getElementById('closeTargetModal');

        if (editTargetBtn && editTargetModal) {
            editTargetBtn.addEventListener('click', function() {
                editTargetModal.classList.add('is-open');
                editTargetModal.setAttribute('aria-hidden', 'false');
            });
            
            var closeModal = function() {
                editTargetModal.classList.remove('is-open');
                editTargetModal.setAttribute('aria-hidden', 'true');
            };

            if (cancelTargetBtn) {
                cancelTargetBtn.addEventListener('click', closeModal);
            }
            if (editTargetBackdrop) {
                editTargetBackdrop.addEventListener('click', closeModal);
            }
            if (closeTargetModal) {
                closeTargetModal.addEventListener('click', closeModal);
            }
        }
    })();

    // AI Summary - Async fetch (page loads fast, AI loads in background)
    <?php if ($aiSummaryEnabled): ?>
    (function() {
        var aiBody = document.getElementById('aiSummaryBody');
        if (!aiBody) return;

        fetch('../handler/ai-summary.php?workspace_id=<?= $workspaceId; ?>')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success && data.summary) {
                    aiBody.innerHTML = data.summary.replace(/\n/g, '<br>');
                } else if (data.requires_pro) {
                    // User is not Pro - show upgrade prompt
                    window.location.reload();
                } else {
                    aiBody.innerHTML = '<p style="color:#6f7681; font-size:13px;">AI analysis unavailable right now.</p>';
                }
            })
            .catch(function(err) {
                aiBody.innerHTML = '<p style="color:#6f7681; font-size:13px;">AI analysis unavailable right now.</p>';
            });
    })();
    <?php endif; ?>

    </script>

</body>
</html>