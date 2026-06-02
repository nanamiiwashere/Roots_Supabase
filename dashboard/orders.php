<?php
// Prevent caching for security
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/../connect.php';
require_once __DIR__ . '/../auth/auth-handler.php';
require_once __DIR__ . '/../auth/auth3thparty.php';
require_once __DIR__ . '/../handler/orders-data.php';

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
ensureOrdersTable($pdo);

// Handle create / update / cancel / delete (POST-Redirect-GET to avoid resubmission)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add') {
            if (trim($_POST['customer_name'] ?? '') === '') {
                throw new Exception('Customer name is required.');
            }
            addOrder($pdo, $workspaceId, $_POST);
            $_SESSION['order_flash'] = ['type' => 'success', 'msg' => 'Order created successfully.'];
        } elseif ($action === 'edit') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0 || trim($_POST['customer_name'] ?? '') === '') {
                throw new Exception('Invalid order data.');
            }
            updateOrder($pdo, $workspaceId, $id, $_POST);
            $_SESSION['order_flash'] = ['type' => 'success', 'msg' => 'Order updated successfully.'];
        } elseif ($action === 'cancel') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                setOrderStatus($pdo, $workspaceId, $id, 'cancelled');
                $_SESSION['order_flash'] = ['type' => 'success', 'msg' => 'Order cancelled.'];
            }
        } elseif ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                deleteOrder($pdo, $workspaceId, $id);
                $_SESSION['order_flash'] = ['type' => 'success', 'msg' => 'Order deleted.'];
            }
        }
    } catch (Exception $e) {
        $_SESSION['order_flash'] = ['type' => 'error', 'msg' => $e->getMessage()];
    }

    // Preserve search + status + page across the redirect
    $qs = [];
    if (!empty($_POST['return_search'])) {
        $qs['q'] = $_POST['return_search'];
    }
    if (!empty($_POST['return_status'])) {
        $qs['status'] = $_POST['return_status'];
    }
    if (!empty($_POST['return_page'])) {
        $qs['page'] = (int) $_POST['return_page'];
    }
    header('Location: orders.php' . ($qs ? '?' . http_build_query($qs) : ''));
    exit;
}

// Read flash message
$flash = $_SESSION['order_flash'] ?? null;
unset($_SESSION['order_flash']);

// Search + status filter + pagination params
$search  = trim($_GET['q'] ?? '');
$status  = trim($_GET['status'] ?? '');
if ($status !== '' && !in_array($status, orderStatuses(), true)) {
    $status = '';
}
$page    = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$perPage = 7;

$dataError = false;
$list = ['rows' => [], 'total' => 0, 'page' => 1, 'pages' => 1, 'perPage' => $perPage];
$stats = ['active' => 0, 'pending' => 0, 'delivered' => 0, 'total' => 0];
try {
    $stats = getOrderStats($pdo, $workspaceId);
    $list  = getOrders($pdo, $workspaceId, $search, $page, $perPage, $status);
} catch (Exception $e) {
    $dataError = true;
}

// Display data for the sidebar/topbar
$displayName   = $currentUser['name'] ?: 'User';
$workspaceName = $workspace['name'] ?? 'My Workspace';
// $avatar and $workspaceLogo are handled by sidebar.php for correct path resolution
$currentPage   = 'orders';

/** Build an orders.php URL preserving the current search + status filter. */
function ordersUrl(int $page, string $search, string $status = ''): string
{
    $qs = [];
    if ($search !== '') {
        $qs['q'] = $search;
    }
    if ($status !== '') {
        $qs['status'] = $status;
    }
    $qs['page'] = $page;
    return 'orders.php?' . http_build_query($qs);
}

/** Map status -> badge css modifier. */
function orderStatusClass(string $status): string
{
    switch ($status) {
        case 'delivered':       return 'is-delivered';
        case 'cancelled':       return 'is-cancelled';
        case 'on_hold':         return 'is-hold';
        case 'pending_payment': return 'is-pending';
        case 'on_progress':
        default:                return 'is-progress';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders - Roots ERP</title>
    <meta name="description" content="Order management for Roots ERP: track orders, items, prices and fulfilment status in one place.">
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
                    <strong>Orders</strong>
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

            <!-- Orders Content -->
            <div class="dashboard-content">

                <!-- Page Header -->
                <div class="customers-header">
                    <div>
                        <h1>Order Management</h1>
                        <p>Stay on top of every order, every step of the way</p>
                    </div>
                    <button type="button" class="customers-add-btn" id="addOrderBtn">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        <span>New Order</span>
                    </button>
                </div>

                <?php if ($flash): ?>
                    <div class="alert alert-<?= $flash['type'] === 'error' ? 'error' : 'success'; ?>" role="alert">
                        <?= htmlspecialchars($flash['msg'], ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>
                <?php if ($dataError): ?>
                    <div class="alert alert-error" role="alert">Unable to load order data right now. Please try again later.</div>
                <?php endif; ?>

                <!-- Stat Cards -->
                <div class="customers-stats">
                    <article class="customers-stat-card">
                        <span class="customers-stat-head">
                            <span class="customers-stat-ico">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17"/><circle cx="9" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg>
                            </span>
                            Active Orders
                        </span>
                        <strong><?= number_format($stats['active'], 0, ',', '.'); ?></strong>
                    </article>

                    <article class="customers-stat-card">
                        <span class="customers-stat-head">
                            <span class="customers-stat-ico">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            </span>
                            Pending Payment
                        </span>
                        <strong><?= number_format($stats['pending'], 0, ',', '.'); ?></strong>
                    </article>

                    <article class="customers-stat-card">
                        <span class="customers-stat-head">
                            <span class="customers-stat-ico">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                            </span>
                            Delivered
                        </span>
                        <strong><?= number_format($stats['delivered'], 0, ',', '.'); ?></strong>
                    </article>

                    <article class="customers-stat-card">
                        <span class="customers-stat-head">
                            <span class="customers-stat-ico">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                            </span>
                            Total Orders
                        </span>
                        <strong><?= number_format($stats['total'], 0, ',', '.'); ?></strong>
                        <em class="customers-stat-sub">Orders</em>
                    </article>
                </div>

                <!-- Order Table Panel -->
                <section class="customers-panel">

                    <!-- Search + Status filter -->
                    <div class="customers-toolbar">
                        <form class="customers-search" method="get" action="orders.php">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                            <input type="search" name="q" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search by name, order id...">
                            <?php if ($status !== ''): ?>
                                <input type="hidden" name="status" value="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php endif; ?>
                        </form>
                        <div class="orders-filter">
                            <form method="get" action="orders.php" id="ordersStatusForm">
                                <?php if ($search !== ''): ?>
                                    <input type="hidden" name="q" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php endif; ?>
                                <select name="status" class="orders-status-select" onchange="this.form.submit()">
                                    <option value="">All statuses</option>
                                    <?php foreach (orderStatuses() as $st): ?>
                                        <option value="<?= $st; ?>" <?= $status === $st ? 'selected' : ''; ?>><?= htmlspecialchars(orderStatusLabel($st), ENT_QUOTES, 'UTF-8'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </div>
                    </div>

                    <!-- Table head -->
                    <?php if (!empty($list['rows'])): ?>
                        <div class="orders-thead">
                            <span class="orders-col-id">Order ID</span>
                            <span class="orders-col-customer">Customer</span>
                            <span class="orders-col-item">Item</span>
                            <span class="orders-col-qty">Qty</span>
                            <span class="orders-col-price">Price</span>
                            <span class="orders-col-status">Status</span>
                            <span class="orders-col-actions"></span>
                        </div>
                    <?php endif; ?>

                    <!-- Rows -->
                    <div class="customers-list orders-list">
                        <?php if (empty($list['rows'])): ?>
                            <div class="customers-empty">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                                <p>
                                    <?php if ($search !== '' || $status !== ''): ?>
                                        No orders match your filters.
                                    <?php else: ?>
                                        No orders yet. Create your first order to get started.
                                    <?php endif; ?>
                                </p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($list['rows'] as $o): ?>
                                <?php
                                    $st = $o['status'] ?? 'on_progress';
                                    $qty = (int) ($o['quantity'] ?? 1);
                                    $ojson = htmlspecialchars(json_encode([
                                        'id'            => (int) $o['id'],
                                        'order_code'    => $o['order_code'],
                                        'customer_name' => $o['customer_name'],
                                        'item'          => $o['item'],
                                        'quantity'      => $qty,
                                        'price'         => (float) $o['price'],
                                        'status'        => $st,
                                        'status_label'  => orderStatusLabel($st),
                                        'order_date'    => $o['order_date'],
                                        'price_label'   => formatOrderRupiah($o['price']),
                                    ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                                ?>
                                <div class="order-row">
                                    <div class="order-cell orders-col-id">
                                        <span class="order-code"><?= htmlspecialchars($o['order_code'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>

                                    <div class="order-cell orders-col-customer">
                                        <span class="customer-avatar" aria-hidden="true">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1"/></svg>
                                        </span>
                                        <strong><?= htmlspecialchars($o['customer_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                    </div>

                                    <div class="order-cell orders-col-item">
                                        <span><?= htmlspecialchars($o['item'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>

                                    <div class="order-cell orders-col-qty">
                                        <span class="order-qty"><?= number_format($qty, 0, ',', '.'); ?>x</span>
                                    </div>

                                    <div class="order-cell orders-col-price">
                                        <span><?= htmlspecialchars(formatOrderRupiah($o['price']), ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>

                                    <div class="order-cell orders-col-status">
                                        <span class="order-badge <?= orderStatusClass($st); ?>">
                                            <span class="order-badge-dot" aria-hidden="true"></span>
                                            <?= htmlspecialchars(strtoupper(orderStatusLabel($st)), ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    </div>

                                    <div class="order-cell orders-col-actions">
                                        <button type="button" class="customer-action-btn js-view-order" data-order="<?= $ojson; ?>" aria-label="View order">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                        </button>
                                        <?php if ($st !== 'cancelled'): ?>
                                            <form method="post" action="orders.php" onsubmit="return confirm('Cancel this order?');">
                                                <input type="hidden" name="action" value="cancel">
                                                <input type="hidden" name="id" value="<?= (int) $o['id']; ?>">
                                                <input type="hidden" name="return_search" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="return_status" value="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="return_page" value="<?= (int) $list['page']; ?>">
                                                <button type="submit" class="customer-action-btn order-action-cancel" aria-label="Cancel order">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="post" action="orders.php" onsubmit="return confirm('Delete this cancelled order permanently?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= (int) $o['id']; ?>">
                                                <input type="hidden" name="return_search" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="return_status" value="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="return_page" value="<?= (int) $list['page']; ?>">
                                                <button type="submit" class="customer-action-btn customer-action-delete" aria-label="Delete order">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($list['pages'] > 1): ?>
                        <?php $cur = $list['page']; $pages = $list['pages']; ?>
                        <nav class="customers-pagination" aria-label="Orders pagination">
                            <a class="page-btn <?= $cur <= 1 ? 'is-disabled' : ''; ?>" href="<?= $cur <= 1 ? '#' : ordersUrl(1, $search, $status); ?>" aria-label="First page">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="11 17 6 12 11 7"/><polyline points="18 17 13 12 18 7"/></svg>
                            </a>
                            <a class="page-btn <?= $cur <= 1 ? 'is-disabled' : ''; ?>" href="<?= $cur <= 1 ? '#' : ordersUrl($cur - 1, $search, $status); ?>" aria-label="Previous page">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                            </a>
                            <a class="page-btn <?= $cur >= $pages ? 'is-disabled' : ''; ?>" href="<?= $cur >= $pages ? '#' : ordersUrl($cur + 1, $search, $status); ?>" aria-label="Next page">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                            </a>
                            <a class="page-btn <?= $cur >= $pages ? 'is-disabled' : ''; ?>" href="<?= $cur >= $pages ? '#' : ordersUrl($pages, $search, $status); ?>" aria-label="Last page">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="13 17 18 12 13 7"/><polyline points="6 17 11 12 6 7"/></svg>
                            </a>
                        </nav>
                    <?php endif; ?>
                </section>
            </div>
        </section>
    </main>

    <!-- Add / Edit Order Modal -->
    <div class="customer-modal" id="orderModal" aria-hidden="true">
        <div class="customer-modal-backdrop" id="orderModalBackdrop"></div>
        <div class="customer-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="orderModalTitle">
            <div class="customer-modal-head">
                <h2 id="orderModalTitle">New Order</h2>
                <button type="button" class="customer-modal-close" id="orderModalClose" aria-label="Close">&times;</button>
            </div>
            <form method="post" action="orders.php" class="customer-modal-form">
                <input type="hidden" name="action" value="add" id="orderFormAction">
                <input type="hidden" name="id" value="" id="orderFormId">
                <input type="hidden" name="return_search" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="return_status" value="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="return_page" value="<?= (int) $list['page']; ?>">

                <div class="customer-field-row">
                    <div class="customer-field">
                        <label for="of_code">Order ID</label>
                        <input type="text" id="of_code" name="order_code" placeholder="Auto-generated if blank">
                    </div>
                    <div class="customer-field">
                        <label for="of_date">Order Date</label>
                        <input type="date" id="of_date" name="order_date">
                    </div>
                </div>
                <div class="customer-field">
                    <label for="of_customer">Customer Name</label>
                    <input type="text" id="of_customer" name="customer_name" required placeholder="e.g., Budi Santoso">
                </div>
                <div class="customer-field-row">
                    <div class="customer-field">
                        <label for="of_item">Item</label>
                        <input type="text" id="of_item" name="item" placeholder="e.g., Matcha Pie">
                    </div>
                    <div class="customer-field">
                        <label for="of_qty">Quantity</label>
                        <input type="number" id="of_qty" name="quantity" min="1" step="1" value="1">
                    </div>
                </div>
                <div class="customer-field-row">
                    <div class="customer-field">
                        <label for="of_price">Price (Rp)</label>
                        <input type="number" id="of_price" name="price" min="0" step="1000" value="0">
                    </div>
                    <div class="customer-field">
                        <label for="of_status">Status</label>
                        <select id="of_status" name="status">
                            <?php foreach (orderStatuses() as $st): ?>
                                <option value="<?= $st; ?>"><?= htmlspecialchars(orderStatusLabel($st), ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="customer-modal-actions">
                    <button type="button" class="customer-btn-cancel" id="orderModalCancel">Cancel</button>
                    <button type="submit" class="customer-btn-save">Save Order</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Order Modal -->
    <div class="customer-modal" id="orderViewModal" aria-hidden="true">
        <div class="customer-modal-backdrop" id="orderViewBackdrop"></div>
        <div class="customer-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="orderViewTitle">
            <div class="customer-modal-head">
                <h2 id="orderViewTitle">Order Details</h2>
                <button type="button" class="customer-modal-close" id="orderViewClose" aria-label="Close">&times;</button>
            </div>
            <div class="order-view-body">
                <div class="order-view-row"><span>Order ID</span><strong id="ov_code">—</strong></div>
                <div class="order-view-row"><span>Customer</span><strong id="ov_customer">—</strong></div>
                <div class="order-view-row"><span>Item</span><strong id="ov_item">—</strong></div>
                <div class="order-view-row"><span>Quantity</span><strong id="ov_qty">—</strong></div>
                <div class="order-view-row"><span>Price</span><strong id="ov_price">—</strong></div>
                <div class="order-view-row"><span>Status</span><strong id="ov_status">—</strong></div>
                <div class="order-view-row"><span>Order Date</span><strong id="ov_date">—</strong></div>
            </div>
            <div class="customer-modal-actions">
                <button type="button" class="customer-btn-cancel" id="orderViewCloseBtn">Close</button>
                <button type="button" class="customer-btn-save" id="orderViewEditBtn">Edit Order</button>
            </div>
        </div>
    </div>

    <script src="../script.js?v=<?= filemtime(__DIR__ . '/../script.js'); ?>"></script>

    <!-- Order page scripts -->
    <script>
    (function() {
        // === Add / Edit modal ===
        var modal = document.getElementById('orderModal');
        var openBtn = document.getElementById('addOrderBtn');
        var closeBtn = document.getElementById('orderModalClose');
        var cancelBtn = document.getElementById('orderModalCancel');
        var backdrop = document.getElementById('orderModalBackdrop');
        var title = document.getElementById('orderModalTitle');
        var fAction = document.getElementById('orderFormAction');
        var fId = document.getElementById('orderFormId');
        var fCode = document.getElementById('of_code');
        var fDate = document.getElementById('of_date');
        var fCustomer = document.getElementById('of_customer');
        var fItem = document.getElementById('of_item');
        var fQty = document.getElementById('of_qty');
        var fPrice = document.getElementById('of_price');
        var fStatus = document.getElementById('of_status');

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
                title.textContent = 'New Order';
                fAction.value = 'add';
                fId.value = '';
                fCode.value = '';
                fDate.value = '';
                fCustomer.value = '';
                fItem.value = '';
                fQty.value = '1';
                fPrice.value = '0';
                fStatus.value = 'on_progress';
                openModal();
            });
        }

        function fillForm(data) {
            title.textContent = 'Edit Order';
            fAction.value = 'edit';
            fId.value = data.id;
            fCode.value = data.order_code || '';
            fDate.value = data.order_date || '';
            fCustomer.value = data.customer_name || '';
            fItem.value = data.item || '';
            fQty.value = data.quantity != null ? data.quantity : 1;
            fPrice.value = data.price != null ? data.price : 0;
            fStatus.value = data.status || 'on_progress';
            openModal();
        }

        [closeBtn, cancelBtn, backdrop].forEach(function(el) {
            if (el) el.addEventListener('click', closeModal);
        });

        // === View modal ===
        var viewModal = document.getElementById('orderViewModal');
        var viewClose = document.getElementById('orderViewClose');
        var viewCloseBtn = document.getElementById('orderViewCloseBtn');
        var viewBackdrop = document.getElementById('orderViewBackdrop');
        var viewEditBtn = document.getElementById('orderViewEditBtn');
        var ovCode = document.getElementById('ov_code');
        var ovCustomer = document.getElementById('ov_customer');
        var ovItem = document.getElementById('ov_item');
        var ovQty = document.getElementById('ov_qty');
        var ovPrice = document.getElementById('ov_price');
        var ovStatus = document.getElementById('ov_status');
        var ovDate = document.getElementById('ov_date');
        var currentOrder = null;

        function openView() {
            viewModal.classList.add('is-open');
            viewModal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('customer-modal-open');
        }
        function closeView() {
            viewModal.classList.remove('is-open');
            viewModal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('customer-modal-open');
        }

        document.querySelectorAll('.js-view-order').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var data;
                try { data = JSON.parse(this.getAttribute('data-order')); }
                catch (e) { return; }
                currentOrder = data;
                ovCode.textContent = data.order_code || '—';
                ovCustomer.textContent = data.customer_name || '—';
                ovItem.textContent = data.item || '—';
                ovQty.textContent = (data.quantity != null ? data.quantity : 1) + 'x';
                ovPrice.textContent = data.price_label || '—';
                ovStatus.textContent = data.status_label || '—';
                ovDate.textContent = data.order_date || '—';
                openView();
            });
        });

        [viewClose, viewCloseBtn, viewBackdrop].forEach(function(el) {
            if (el) el.addEventListener('click', closeView);
        });
        if (viewEditBtn) {
            viewEditBtn.addEventListener('click', function() {
                if (!currentOrder) return;
                closeView();
                fillForm(currentOrder);
            });
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') { closeModal(); closeView(); }
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
                    window.location.href = 'orders.php?workspace_id=' + this.getAttribute('data-workspace-id');
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
