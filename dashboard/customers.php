<?php
// Prevent caching for security
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/../connect.php';
require_once __DIR__ . '/../auth/auth-handler.php';
require_once __DIR__ . '/../auth/auth3thparty.php';
require_once __DIR__ . '/../handler/customers-data.php';

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
ensureCustomersTable($pdo);

// Handle create / update / delete (POST-Redirect-GET to avoid resubmission)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add') {
            if (trim($_POST['name'] ?? '') === '') {
                throw new Exception('Customer name is required.');
            }
            addCustomer($pdo, $workspaceId, $_POST);
            $_SESSION['customer_flash'] = ['type' => 'success', 'msg' => 'Customer added successfully.'];
        } elseif ($action === 'edit') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0 || trim($_POST['name'] ?? '') === '') {
                throw new Exception('Invalid customer data.');
            }
            updateCustomer($pdo, $workspaceId, $id, $_POST);
            $_SESSION['customer_flash'] = ['type' => 'success', 'msg' => 'Customer updated successfully.'];
        } elseif ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                deleteCustomer($pdo, $workspaceId, $id);
                $_SESSION['customer_flash'] = ['type' => 'success', 'msg' => 'Customer deleted.'];
            }
        }
    } catch (Exception $e) {
        $_SESSION['customer_flash'] = ['type' => 'error', 'msg' => $e->getMessage()];
    }

    // Preserve search + page + month across the redirect
    $qs = [];
    if (!empty($_POST['return_search'])) {
        $qs['q'] = $_POST['return_search'];
    }
    if (!empty($_POST['return_month'])) {
        $qs['month'] = $_POST['return_month'];
    }
    if (!empty($_POST['return_page'])) {
        $qs['page'] = (int) $_POST['return_page'];
    }
    header('Location: customers.php' . ($qs ? '?' . http_build_query($qs) : ''));
    exit;
}

// Read flash message
$flash = $_SESSION['customer_flash'] ?? null;
unset($_SESSION['customer_flash']);

// Search + month filter + pagination params
$search  = trim($_GET['q'] ?? '');
$month   = trim($_GET['month'] ?? '');
if ($month !== '' && !preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = '';
}
$page    = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$perPage = 6;

$dataError = false;
$list = ['rows' => [], 'total' => 0, 'page' => 1, 'pages' => 1, 'perPage' => $perPage];
$stats = ['total' => 0, 'new_this_month' => 0, 'repeat' => 0, 'uncontacted' => 0];
try {
    $stats = getCustomerStats($pdo, $workspaceId);
    $list  = getCustomers($pdo, $workspaceId, $search, $page, $perPage, $month);
} catch (Exception $e) {
    $dataError = true;
}

// Display data for the sidebar/topbar
$displayName   = $currentUser['name'] ?: 'User';
$workspaceName = $workspace['name'] ?? 'My Workspace';
// $avatar and $workspaceLogo are handled by sidebar.php for correct path resolution
$currentPage   = 'customers';

/**
 * Build a customers.php URL preserving the current search + month filter.
 */
function customersUrl(int $page, string $search, string $month = ''): string
{
    $qs = [];
    if ($search !== '') {
        $qs['q'] = $search;
    }
    if ($month !== '') {
        $qs['month'] = $month;
    }
    $qs['page'] = $page;
    return 'customers.php?' . http_build_query($qs);
}

// Human-friendly label for the active month filter (e.g. "May 2026")
$monthLabel = $month !== '' ? date('F Y', strtotime($month . '-01')) : '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customers - Roots ERP</title>
    <meta name="description" content="Customer management for Roots ERP: track customers, purchases and segments in one place.">
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
                    <strong>Customers</strong>
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

            <!-- Customers Content -->
            <div class="dashboard-content">

                <!-- Page Header -->
                <div class="customers-header">
                    <div>
                        <h1>Customer Management</h1>
                        <p>Manage and track all your customer data in one place</p>
                    </div>
                    <div class="customers-datepicker">
                        <button type="button" class="customers-date-btn <?= $month !== '' ? 'is-active' : ''; ?>" id="customersDateBtn" aria-label="Filter by month" aria-haspopup="true" aria-expanded="false">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            <?php if ($monthLabel !== ''): ?>
                                <span class="customers-date-label"><?= htmlspecialchars($monthLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                        </button>
                        <div class="customers-date-pop" id="customersDatePop">
                            <form method="get" action="customers.php">
                                <?php if ($search !== ''): ?>
                                    <input type="hidden" name="q" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php endif; ?>
                                <label for="customersMonthInput">Filter by month</label>
                                <input type="month" id="customersMonthInput" name="month" value="<?= htmlspecialchars($month, ENT_QUOTES, 'UTF-8'); ?>">
                                <div class="customers-date-actions">
                                    <?php if ($month !== ''): ?>
                                        <a href="<?= $search !== '' ? 'customers.php?q=' . urlencode($search) : 'customers.php'; ?>" class="customers-date-clear">Clear</a>
                                    <?php endif; ?>
                                    <button type="submit" class="customers-date-apply">Apply</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <?php if ($flash): ?>
                    <div class="alert alert-<?= $flash['type'] === 'error' ? 'error' : 'success'; ?>" role="alert">
                        <?= htmlspecialchars($flash['msg'], ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>
                <?php if ($dataError): ?>
                    <div class="alert alert-error" role="alert">Unable to load customer data right now. Please try again later.</div>
                <?php endif; ?>

                <!-- Stat Cards -->
                <div class="customers-stats">
                    <article class="customers-stat-card">
                        <span class="customers-stat-head">
                            <span class="customers-stat-ico">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                            </span>
                            Total Customers
                        </span>
                        <strong><?= number_format($stats['total'], 0, ',', '.'); ?></strong>
                    </article>

                    <article class="customers-stat-card">
                        <span class="customers-stat-head">
                            <span class="customers-stat-ico">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
                            </span>
                            New This Month
                        </span>
                        <strong><?= number_format($stats['new_this_month'], 0, ',', '.'); ?></strong>
                        <em class="customers-stat-sub">Customers</em>
                    </article>

                    <article class="customers-stat-card">
                        <span class="customers-stat-head">
                            <span class="customers-stat-ico">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
                            </span>
                            Repeat Customers
                        </span>
                        <strong><?= number_format($stats['repeat'], 0, ',', '.'); ?></strong>
                    </article>

                    <article class="customers-stat-card">
                        <span class="customers-stat-head">
                            <span class="customers-stat-ico">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/><line x1="9" y1="10" x2="15" y2="10"/></svg>
                            </span>
                            Uncontacted
                        </span>
                        <strong><?= number_format($stats['uncontacted'], 0, ',', '.'); ?></strong>
                        <em class="customers-stat-sub">Customers</em>
                    </article>
                </div>

                <!-- Customer Table Panel -->
                <section class="customers-panel">

                    <!-- Search + Add -->
                    <div class="customers-toolbar">
                        <form class="customers-search" method="get" action="customers.php">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                            <input type="search" name="q" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search by name, email...">
                        </form>
                        <button type="button" class="customers-add-btn" id="addCustomerBtn">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            <span>Add Customer</span>
                        </button>
                    </div>

                    <!-- Rows -->
                    <div class="customers-list">
                        <?php if (empty($list['rows'])): ?>
                            <div class="customers-empty">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/></svg>
                                <p>
                                    <?php if ($search !== '' || $month !== ''): ?>
                                        No customers match your filters.
                                    <?php else: ?>
                                        No customers yet. Add your first customer to get started.
                                    <?php endif; ?>
                                </p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($list['rows'] as $c): ?>
                                <?php
                                    $isNew = ($c['segment'] ?? 'new') === 'new';
                                    $lastTx = !empty($c['last_transaction'])
                                        ? date('M j, Y', strtotime($c['last_transaction']))
                                        : '—';
                                    $cjson = htmlspecialchars(json_encode([
                                        'id'               => (int) $c['id'],
                                        'name'             => $c['name'],
                                        'email'            => $c['email'],
                                        'total_purchase'   => (float) $c['total_purchase'],
                                        'last_transaction' => $c['last_transaction'],
                                        'segment'          => $c['segment'],
                                        'contacted'        => (int) $c['contacted'],
                                    ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                                ?>
                                <div class="customer-row">
                                    <div class="customer-cell customer-identity">
                                        <span class="customer-avatar" aria-hidden="true">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1"/></svg>
                                        </span>
                                        <div class="customer-identity-text">
                                            <strong><?= htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                            <span><?= htmlspecialchars($c['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                                        </div>
                                    </div>

                                    <div class="customer-cell customer-purchase">
                                        <span class="customer-purchase-ico" aria-hidden="true">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2.5"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                                        </span>
                                        <div>
                                            <strong><?= htmlspecialchars(formatCustomerRupiah($c['total_purchase']), ENT_QUOTES, 'UTF-8'); ?></strong>
                                            <span>Total Purchase</span>
                                        </div>
                                    </div>

                                    <div class="customer-cell customer-lasttx">
                                        <em>Last Transaction: <?= htmlspecialchars($lastTx, ENT_QUOTES, 'UTF-8'); ?></em>
                                    </div>

                                    <div class="customer-cell customer-segment">
                                        <span class="customer-badge <?= $isNew ? 'is-new' : 'is-regular'; ?>">
                                            <?= $isNew ? 'NEW THIS MONTH' : 'REGULAR'; ?>
                                        </span>
                                    </div>

                                    <div class="customer-cell customer-actions">
                                        <button type="button" class="customer-action-btn js-edit-customer" data-customer="<?= $cjson; ?>" aria-label="Edit customer">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                        </button>
                                        <form method="post" action="customers.php" class="js-delete-form" onsubmit="return confirm('Delete this customer? This cannot be undone.');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int) $c['id']; ?>">
                                            <input type="hidden" name="return_search" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="return_month" value="<?= htmlspecialchars($month, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="return_page" value="<?= (int) $list['page']; ?>">
                                            <button type="submit" class="customer-action-btn customer-action-delete" aria-label="Delete customer">
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
                        <nav class="customers-pagination" aria-label="Customers pagination">
                            <a class="page-btn <?= $cur <= 1 ? 'is-disabled' : ''; ?>" href="<?= $cur <= 1 ? '#' : customersUrl(1, $search, $month); ?>" aria-label="First page">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="11 17 6 12 11 7"/><polyline points="18 17 13 12 18 7"/></svg>
                            </a>
                            <a class="page-btn <?= $cur <= 1 ? 'is-disabled' : ''; ?>" href="<?= $cur <= 1 ? '#' : customersUrl($cur - 1, $search, $month); ?>" aria-label="Previous page">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                            </a>
                            <a class="page-btn <?= $cur >= $pages ? 'is-disabled' : ''; ?>" href="<?= $cur >= $pages ? '#' : customersUrl($cur + 1, $search, $month); ?>" aria-label="Next page">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                            </a>
                            <a class="page-btn <?= $cur >= $pages ? 'is-disabled' : ''; ?>" href="<?= $cur >= $pages ? '#' : customersUrl($pages, $search, $month); ?>" aria-label="Last page">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="13 17 18 12 13 7"/><polyline points="6 17 11 12 6 7"/></svg>
                            </a>
                        </nav>
                    <?php endif; ?>
                </section>
            </div>
        </section>
    </main>

    <!-- Add / Edit Customer Modal -->
    <div class="customer-modal" id="customerModal" aria-hidden="true">
        <div class="customer-modal-backdrop" id="customerModalBackdrop"></div>
        <div class="customer-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="customerModalTitle">
            <div class="customer-modal-head">
                <h2 id="customerModalTitle">Add Customer</h2>
                <button type="button" class="customer-modal-close" id="customerModalClose" aria-label="Close">&times;</button>
            </div>
            <form method="post" action="customers.php" class="customer-modal-form">
                <input type="hidden" name="action" value="add" id="customerFormAction">
                <input type="hidden" name="id" value="" id="customerFormId">
                <input type="hidden" name="return_search" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="return_month" value="<?= htmlspecialchars($month, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="return_page" value="<?= (int) $list['page']; ?>">

                <div class="customer-field">
                    <label for="cf_name">Full Name</label>
                    <input type="text" id="cf_name" name="name" required placeholder="e.g., Budi Santoso">
                </div>
                <div class="customer-field">
                    <label for="cf_email">Email Address</label>
                    <input type="email" id="cf_email" name="email" placeholder="e.g., budi@gmail.com">
                </div>
                <div class="customer-field-row">
                    <div class="customer-field">
                        <label for="cf_total">Total Purchase (Rp)</label>
                        <input type="number" id="cf_total" name="total_purchase" min="0" step="1000" value="0">
                    </div>
                    <div class="customer-field">
                        <label for="cf_lasttx">Last Transaction</label>
                        <input type="date" id="cf_lasttx" name="last_transaction">
                    </div>
                </div>
                <div class="customer-field-row">
                    <div class="customer-field">
                        <label for="cf_segment">Segment</label>
                        <select id="cf_segment" name="segment">
                            <option value="new">New This Month</option>
                            <option value="regular">Regular</option>
                        </select>
                    </div>
                    <div class="customer-field customer-field-check">
                        <label class="customer-check">
                            <input type="checkbox" id="cf_contacted" name="contacted" value="1" checked>
                            <span>Already contacted</span>
                        </label>
                    </div>
                </div>
                <div class="customer-modal-actions">
                    <button type="button" class="customer-btn-cancel" id="customerModalCancel">Cancel</button>
                    <button type="submit" class="customer-btn-save">Save Customer</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../script.js?v=<?= filemtime(__DIR__ . '/../script.js'); ?>"></script>

    <!-- Customer page scripts -->
    <script>
    (function() {
        // === Modal (add / edit) ===
        var modal = document.getElementById('customerModal');
        var openBtn = document.getElementById('addCustomerBtn');
        var closeBtn = document.getElementById('customerModalClose');
        var cancelBtn = document.getElementById('customerModalCancel');
        var backdrop = document.getElementById('customerModalBackdrop');
        var title = document.getElementById('customerModalTitle');
        var fAction = document.getElementById('customerFormAction');
        var fId = document.getElementById('customerFormId');
        var fName = document.getElementById('cf_name');
        var fEmail = document.getElementById('cf_email');
        var fTotal = document.getElementById('cf_total');
        var fLastTx = document.getElementById('cf_lasttx');
        var fSegment = document.getElementById('cf_segment');
        var fContacted = document.getElementById('cf_contacted');

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
                title.textContent = 'Add Customer';
                fAction.value = 'add';
                fId.value = '';
                fName.value = '';
                fEmail.value = '';
                fTotal.value = '0';
                fLastTx.value = '';
                fSegment.value = 'new';
                fContacted.checked = true;
                openModal();
            });
        }

        document.querySelectorAll('.js-edit-customer').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var data;
                try { data = JSON.parse(this.getAttribute('data-customer')); }
                catch (e) { return; }
                title.textContent = 'Edit Customer';
                fAction.value = 'edit';
                fId.value = data.id;
                fName.value = data.name || '';
                fEmail.value = data.email || '';
                fTotal.value = data.total_purchase != null ? data.total_purchase : 0;
                fLastTx.value = data.last_transaction || '';
                fSegment.value = data.segment || 'new';
                fContacted.checked = !!data.contacted;
                openModal();
            });
        });

        [closeBtn, cancelBtn, backdrop].forEach(function(el) {
            if (el) el.addEventListener('click', closeModal);
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeModal();
        });

        // === Month filter popover ===
        var dateBtn = document.getElementById('customersDateBtn');
        var datePop = document.getElementById('customersDatePop');
        if (dateBtn && datePop) {
            dateBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                var open = datePop.classList.toggle('is-visible');
                dateBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
            document.addEventListener('click', function(e) {
                if (!datePop.contains(e.target) && !dateBtn.contains(e.target)) {
                    datePop.classList.remove('is-visible');
                    dateBtn.setAttribute('aria-expanded', 'false');
                }
            });
        }

        // === Workspace Dropdown ===
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
                    window.location.href = 'customers.php?workspace_id=' + this.getAttribute('data-workspace-id');
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
