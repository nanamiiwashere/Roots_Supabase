<?php
// Prevent caching for security
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/../connect.php';
require_once __DIR__ . '/../auth/auth-handler.php';
require_once __DIR__ . '/../auth/auth3thparty.php';
require_once __DIR__ . '/../handler/finance-data.php';

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

// No workspace yet -> send the user back to the dashboard to complete setup
if (!$workspace) {
    header('Location: index.php');
    exit;
}

$workspaceId = (int) $workspace['id'];
ensureFinanceTable($pdo);

// Handle create / update / delete (POST-Redirect-GET to avoid resubmission)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add') {
            if (trim($_POST['title'] ?? '') === '') {
                throw new Exception('Transaction title is required.');
            }
            addTransaction($pdo, $workspaceId, $_POST);
            $_SESSION['finance_flash'] = ['type' => 'success', 'msg' => 'Transaction added successfully.'];
        } elseif ($action === 'edit') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0 || trim($_POST['title'] ?? '') === '') {
                throw new Exception('Invalid transaction data.');
            }
            updateTransaction($pdo, $workspaceId, $id, $_POST);
            $_SESSION['finance_flash'] = ['type' => 'success', 'msg' => 'Transaction updated successfully.'];
        } elseif ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                deleteTransaction($pdo, $workspaceId, $id);
                $_SESSION['finance_flash'] = ['type' => 'success', 'msg' => 'Transaction deleted.'];
            }
        }
    } catch (Exception $e) {
        $_SESSION['finance_flash'] = ['type' => 'error', 'msg' => $e->getMessage()];
    }

    $qs = [];
    if (!empty($_POST['return_search'])) {
        $qs['q'] = $_POST['return_search'];
    }
    if (!empty($_POST['return_type'])) {
        $qs['type'] = $_POST['return_type'];
    }
    if (!empty($_POST['return_page'])) {
        $qs['page'] = (int) $_POST['return_page'];
    }
    header('Location: finance.php' . ($qs ? '?' . http_build_query($qs) : ''));
    exit;
}

// Read flash message
$flash = $_SESSION['finance_flash'] ?? null;
unset($_SESSION['finance_flash']);

// Search + type filter + pagination params
$search = trim($_GET['q'] ?? '');
$type   = trim($_GET['type'] ?? '');
if ($type !== '' && !in_array($type, financeTypes(), true)) {
    $type = '';
}
$page    = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$perPage = 8;

$dataError = false;
$summary = ['balance' => 0, 'income' => 0, 'expenses' => 0, 'change_percent' => null];
$trend   = ['labels' => [], 'values' => []];
$list    = ['rows' => [], 'total' => 0, 'page' => 1, 'pages' => 1, 'perPage' => $perPage];
try {
    $summary = getFinanceSummary($pdo, $workspaceId);
    $trend   = getFinanceMonthlyTrend($pdo, $workspaceId, 6);
    $list    = getFinanceTransactions($pdo, $workspaceId, $search, $page, $perPage, $type);
} catch (Exception $e) {
    $dataError = true;
}

$hasTrend = financeHasTrendData($trend);

// Display data for the sidebar/topbar
$displayName   = $currentUser['name'] ?: 'User';
$workspaceName = $workspace['name'] ?? 'My Workspace';
// $avatar and $workspaceLogo are handled by sidebar.php for correct path resolution
$currentPage   = 'finance';

/** Build a finance.php URL preserving the current search + type filter. */
function financeUrl(int $page, string $search, string $type = ''): string
{
    $qs = [];
    if ($search !== '') {
        $qs['q'] = $search;
    }
    if ($type !== '') {
        $qs['type'] = $type;
    }
    $qs['page'] = $page;
    return 'finance.php?' . http_build_query($qs);
}

$changePercent = $summary['change_percent'];
if ($changePercent !== null) {
    $changeText = ($changePercent >= 0 ? '+' : '') . rtrim(rtrim(number_format($changePercent, 1, ',', '.'), '0'), ',') . '% from last month';
    $changeUp = $changePercent >= 0;
} else {
    $changeText = '';
    $changeUp = true;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finance - Roots ERP</title>
    <meta name="description" content="Finance management for Roots ERP: monitor income, expenses, balance and transactions.">
    <link rel="icon" href="../img/roots_symbol.png?v=<?= filemtime(__DIR__ . '/../img/roots_symbol.png'); ?>" type="image/png">
    <link rel="stylesheet" href="../dashboard/partials/style.css?v=<?= filemtime(__DIR__ . '/partials/style.css'); ?>">
</head>
<body>

    <!-- Main Dashboard -->
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
                    <strong>Finance</strong>
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

            <!-- Finance Content -->
            <div class="dashboard-content">

                <!-- Page Header -->
                <div class="customers-header">
                    <div>
                        <h1>Finance</h1>
                        <p>Monitor your business finances simply and accurately</p>
                    </div>
                    <button type="button" class="customers-add-btn" id="addTxBtn">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                        <span>New Transaction</span>
                    </button>
                </div>

                <?php if ($flash): ?>
                    <div class="alert alert-<?= $flash['type'] === 'error' ? 'error' : 'success'; ?>" role="alert">
                        <?= htmlspecialchars($flash['msg'], ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>
                <?php if ($dataError): ?>
                    <div class="alert alert-error" role="alert">Unable to load finance data right now. Please try again later.</div>
                <?php endif; ?>

                <!-- Chart + Balance row -->
                <div class="finance-top">
                    <!-- Revenue Growth Trend -->
                    <article class="finance-chart-card">
                        <h2>Revenue Growth Trend</h2>
                        <?php if ($hasTrend): ?>
                            <div class="finance-chart-wrap">
                                <canvas id="financeTrendChart" aria-label="Revenue growth trend chart" role="img"></canvas>
                            </div>
                        <?php else: ?>
                            <div class="analytics-chart-empty finance-chart-empty">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><polyline points="6 15 10 10 14 13 19 6"/></svg>
                                <p>No revenue data yet. Your trend will appear here once you record transactions.</p>
                            </div>
                        <?php endif; ?>
                    </article>

                    <!-- Balance + income/expense -->
                    <div class="finance-side">
                        <article class="finance-balance-card">
                            <span class="finance-balance-head">
                                <span class="finance-balance-ico">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M16 12h.01"/><path d="M2 10h20"/></svg>
                                </span>
                                Main Balance
                            </span>
                            <strong><?= htmlspecialchars(formatFinanceRupiah($summary['balance']), ENT_QUOTES, 'UTF-8'); ?></strong>
                            <?php if ($changeText !== ''): ?>
                                <em class="finance-balance-change">
                                    <?= $changeUp ? '&#8593;' : '&#8595;'; ?> <?= htmlspecialchars($changeText, ENT_QUOTES, 'UTF-8'); ?>
                                </em>
                            <?php endif; ?>
                        </article>

                        <article class="finance-mini-card">
                            <div class="finance-mini-row">
                                <div>
                                    <span class="finance-mini-label">Total Income</span>
                                    <strong class="finance-mini-value is-income"><?= htmlspecialchars(formatFinanceRupiah($summary['income']), ENT_QUOTES, 'UTF-8'); ?></strong>
                                </div>
                                <span class="finance-mini-ico is-income" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="7" y1="17" x2="17" y2="7"/><polyline points="7 7 17 7 17 17"/></svg>
                                </span>
                            </div>
                            <div class="finance-mini-divider"></div>
                            <div class="finance-mini-row">
                                <div>
                                    <span class="finance-mini-label">Total Expenses</span>
                                    <strong class="finance-mini-value is-expense"><?= htmlspecialchars(formatFinanceRupiah($summary['expenses']), ENT_QUOTES, 'UTF-8'); ?></strong>
                                </div>
                                <span class="finance-mini-ico is-expense" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="7" y1="7" x2="17" y2="17"/><polyline points="17 7 17 17 7 17"/></svg>
                                </span>
                            </div>
                        </article>
                    </div>
                </div>

                <!-- Recent Transactions -->
                <section class="customers-panel finance-tx-panel">
                    <div class="finance-tx-head">
                        <h2>Recent Transactions</h2>
                        <div class="finance-tx-tools">
                            <form method="get" action="finance.php" class="finance-tx-search">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                                <input type="search" name="q" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search transactions...">
                                <?php if ($type !== ''): ?>
                                    <input type="hidden" name="type" value="<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php endif; ?>
                            </form>
                            <form method="get" action="finance.php">
                                <?php if ($search !== ''): ?>
                                    <input type="hidden" name="q" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php endif; ?>
                                <select name="type" class="orders-status-select" onchange="this.form.submit()">
                                    <option value="">All types</option>
                                    <option value="income" <?= $type === 'income' ? 'selected' : ''; ?>>Income</option>
                                    <option value="expense" <?= $type === 'expense' ? 'selected' : ''; ?>>Expense</option>
                                </select>
                            </form>
                        </div>
                    </div>

                    <div class="finance-tx-list">
                        <?php if (empty($list['rows'])): ?>
                            <div class="customers-empty">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>
                                <p>
                                    <?php if ($search !== '' || $type !== ''): ?>
                                        No transactions match your filters.
                                    <?php else: ?>
                                        No transactions yet. Record your first transaction to get started.
                                    <?php endif; ?>
                                </p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($list['rows'] as $tx): ?>
                                <?php
                                    $isIncome = ($tx['type'] ?? 'income') === 'income';
                                    $txStatus = $tx['status'] ?? 'success';
                                    $dateLabel = !empty($tx['tx_date']) ? strtoupper(date('d M Y', strtotime($tx['tx_date']))) : '—';
                                    $tjson = htmlspecialchars(json_encode([
                                        'id'      => (int) $tx['id'],
                                        'tx_code' => $tx['tx_code'],
                                        'title'   => $tx['title'],
                                        'type'    => $tx['type'],
                                        'amount'  => (float) $tx['amount'],
                                        'status'  => $txStatus,
                                        'tx_date' => $tx['tx_date'],
                                    ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                                ?>
                                <div class="finance-tx-row">
                                    <span class="finance-tx-ico <?= $isIncome ? 'is-income' : 'is-expense'; ?>" aria-hidden="true">
                                        <?php if ($isIncome): ?>
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="7" y1="17" x2="17" y2="7"/><polyline points="7 7 17 7 17 17"/></svg>
                                        <?php else: ?>
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="7" y1="7" x2="17" y2="17"/><polyline points="17 7 17 17 7 17"/></svg>
                                        <?php endif; ?>
                                    </span>

                                    <div class="finance-tx-info">
                                        <strong><?= htmlspecialchars($tx['title'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <span><?= htmlspecialchars($dateLabel, ENT_QUOTES, 'UTF-8'); ?> &middot; <?= htmlspecialchars($tx['tx_code'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>

                                    <div class="finance-tx-amount-wrap">
                                        <span class="finance-tx-amount <?= $isIncome ? 'is-income' : 'is-expense'; ?>">
                                            <?= $isIncome ? '+' : '&minus;'; ?> <?= htmlspecialchars(formatFinanceRupiah($tx['amount']), ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                        <span class="finance-tx-status <?= $txStatus === 'pending' ? 'is-pending' : 'is-success'; ?>">
                                            <?php if ($txStatus === 'pending'): ?>
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                            <?php else: ?>
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="9 12 11.5 14.5 16 9.5"/></svg>
                                            <?php endif; ?>
                                            <?= htmlspecialchars(strtoupper(financeStatusLabel($txStatus)), ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    </div>

                                    <div class="finance-tx-actions">
                                        <button type="button" class="customer-action-btn js-edit-tx" data-tx="<?= $tjson; ?>" aria-label="Edit transaction">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                        </button>
                                        <form method="post" action="finance.php" onsubmit="return confirm('Delete this transaction? This cannot be undone.');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int) $tx['id']; ?>">
                                            <input type="hidden" name="return_search" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="return_type" value="<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="return_page" value="<?= (int) $list['page']; ?>">
                                            <button type="submit" class="customer-action-btn inv-action-delete" aria-label="Delete transaction">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($list['pages'] > 1): ?>
                        <?php $cur = $list['page']; $pages = $list['pages']; ?>
                        <nav class="customers-pagination" aria-label="Transactions pagination">
                            <a class="page-btn <?= $cur <= 1 ? 'is-disabled' : ''; ?>" href="<?= $cur <= 1 ? '#' : financeUrl(1, $search, $type); ?>" aria-label="First page">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="11 17 6 12 11 7"/><polyline points="18 17 13 12 18 7"/></svg>
                            </a>
                            <a class="page-btn <?= $cur <= 1 ? 'is-disabled' : ''; ?>" href="<?= $cur <= 1 ? '#' : financeUrl($cur - 1, $search, $type); ?>" aria-label="Previous page">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                            </a>
                            <a class="page-btn <?= $cur >= $pages ? 'is-disabled' : ''; ?>" href="<?= $cur >= $pages ? '#' : financeUrl($cur + 1, $search, $type); ?>" aria-label="Next page">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                            </a>
                            <a class="page-btn <?= $cur >= $pages ? 'is-disabled' : ''; ?>" href="<?= $cur >= $pages ? '#' : financeUrl($pages, $search, $type); ?>" aria-label="Last page">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="13 17 18 12 13 7"/><polyline points="6 17 11 12 6 7"/></svg>
                            </a>
                        </nav>
                    <?php endif; ?>
                </section>
            </div>
        </section>
    </main>

    <!-- Add / Edit Transaction Modal -->
    <div class="customer-modal" id="txModal" aria-hidden="true">
        <div class="customer-modal-backdrop" id="txModalBackdrop"></div>
        <div class="customer-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="txModalTitle">
            <div class="customer-modal-head">
                <h2 id="txModalTitle">New Transaction</h2>
                <button type="button" class="customer-modal-close" id="txModalClose" aria-label="Close">&times;</button>
            </div>
            <form method="post" action="finance.php" class="customer-modal-form">
                <input type="hidden" name="action" value="add" id="txFormAction">
                <input type="hidden" name="id" value="" id="txFormId">
                <input type="hidden" name="return_search" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="return_type" value="<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="return_page" value="<?= (int) $list['page']; ?>">

                <div class="customer-field">
                    <label for="tf_title">Title</label>
                    <input type="text" id="tf_title" name="title" required placeholder="e.g., Retail Sale - Marketplace">
                </div>
                <div class="customer-field-row">
                    <div class="customer-field">
                        <label for="tf_type">Type</label>
                        <select id="tf_type" name="type">
                            <option value="income">Income</option>
                            <option value="expense">Expense</option>
                        </select>
                    </div>
                    <div class="customer-field">
                        <label for="tf_amount">Amount (Rp)</label>
                        <input type="number" id="tf_amount" name="amount" min="0" step="1000" value="0">
                    </div>
                </div>
                <div class="customer-field-row">
                    <div class="customer-field">
                        <label for="tf_status">Status</label>
                        <select id="tf_status" name="status">
                            <option value="success">Success</option>
                            <option value="pending">Pending</option>
                        </select>
                    </div>
                    <div class="customer-field">
                        <label for="tf_date">Date</label>
                        <input type="date" id="tf_date" name="tx_date">
                    </div>
                </div>
                <div class="customer-field">
                    <label for="tf_code">Transaction ID</label>
                    <input type="text" id="tf_code" name="tx_code" placeholder="Auto-generated if blank">
                </div>
                <div class="customer-modal-actions">
                    <button type="button" class="customer-btn-cancel" id="txModalCancel">Cancel</button>
                    <button type="submit" class="customer-btn-save">Save Transaction</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="../script.js?v=<?= filemtime(__DIR__ . '/../script.js'); ?>"></script>

    <!-- Finance trend chart -->
    <script>
    (function() {
        if (typeof Chart === 'undefined') return;
        var canvas = document.getElementById('financeTrendChart');
        if (!canvas) return;
        var labels = <?= json_encode($trend['labels'], JSON_UNESCAPED_UNICODE); ?>;
        var values = <?= json_encode($trend['values'], JSON_UNESCAPED_UNICODE); ?>;
        var ctx = canvas.getContext('2d');
        var grad = ctx.createLinearGradient(0, 0, 0, 320);
        grad.addColorStop(0, 'rgba(245, 179, 1, 0.28)');
        grad.addColorStop(1, 'rgba(245, 179, 1, 0)');
        new Chart(canvas, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Net Revenue',
                    data: values,
                    borderColor: '#f5b301',
                    backgroundColor: grad,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 0,
                    pointHoverRadius: 5,
                    borderWidth: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false }, border: { display: false } },
                    y: { beginAtZero: true, grid: { color: '#eef1f4', borderDash: [4, 4] }, border: { display: false }, ticks: { maxTicksLimit: 4 } }
                }
            }
        });
    })();
    </script>

    <!-- Finance page scripts -->
    <script>
    (function() {
        // === Add / Edit modal ===
        var modal = document.getElementById('txModal');
        var openBtn = document.getElementById('addTxBtn');
        var closeBtn = document.getElementById('txModalClose');
        var cancelBtn = document.getElementById('txModalCancel');
        var backdrop = document.getElementById('txModalBackdrop');
        var title = document.getElementById('txModalTitle');
        var fAction = document.getElementById('txFormAction');
        var fId = document.getElementById('txFormId');
        var fTitle = document.getElementById('tf_title');
        var fType = document.getElementById('tf_type');
        var fAmount = document.getElementById('tf_amount');
        var fStatus = document.getElementById('tf_status');
        var fDate = document.getElementById('tf_date');
        var fCode = document.getElementById('tf_code');

        function openModal() {
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('customer-modal-open');
        }
        function closeModal() {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('customer-modal-open');
        }

        if (openBtn) {
            openBtn.addEventListener('click', function() {
                title.textContent = 'New Transaction';
                fAction.value = 'add';
                fId.value = '';
                fTitle.value = '';
                fType.value = 'income';
                fAmount.value = '0';
                fStatus.value = 'success';
                fDate.value = '';
                fCode.value = '';
                openModal();
            });
        }

        document.querySelectorAll('.js-edit-tx').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var data;
                try { data = JSON.parse(this.getAttribute('data-tx')); }
                catch (e) { return; }
                title.textContent = 'Edit Transaction';
                fAction.value = 'edit';
                fId.value = data.id;
                fTitle.value = data.title || '';
                fType.value = data.type || 'income';
                fAmount.value = data.amount != null ? data.amount : 0;
                fStatus.value = data.status || 'success';
                fDate.value = data.tx_date || '';
                fCode.value = data.tx_code || '';
                openModal();
            });
        });

        [closeBtn, cancelBtn, backdrop].forEach(function(el) {
            if (el) el.addEventListener('click', closeModal);
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeModal();
        });

        // === Workspace Dropdown / topbar / notif ===
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
                    window.location.href = 'finance.php?workspace_id=' + this.getAttribute('data-workspace-id');
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

        // === Mobile sidebar toggle ===
        var mobileMenuBtn = document.getElementById('mobileMenuBtn');
        var sidebar = document.getElementById('sidebar');
        if (mobileMenuBtn && sidebar) {
            mobileMenuBtn.addEventListener('click', function() {
                sidebar.classList.toggle('is-mobile-open');
            });
        }
    })();
    </script>

</body>
</html>
