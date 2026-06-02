<?php
// Prevent caching for security
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/../connect.php';
require_once __DIR__ . '/../auth/auth-handler.php';
require_once __DIR__ . '/../auth/auth3thparty.php';
require_once __DIR__ . '/../handler/inventory-data.php';

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
ensureInventoryTable($pdo);

// Handle create / update / delete (POST-Redirect-GET to avoid resubmission)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add') {
            if (trim($_POST['product_name'] ?? '') === '') {
                throw new Exception('Product name is required.');
            }
            addInventoryItem($pdo, $workspaceId, $_POST);
            $_SESSION['inventory_flash'] = ['type' => 'success', 'msg' => 'Product added successfully.'];
        } elseif ($action === 'edit') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0 || trim($_POST['product_name'] ?? '') === '') {
                throw new Exception('Invalid product data.');
            }
            updateInventoryItem($pdo, $workspaceId, $id, $_POST);
            $_SESSION['inventory_flash'] = ['type' => 'success', 'msg' => 'Product updated successfully.'];
        } elseif ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                deleteInventoryItem($pdo, $workspaceId, $id);
                $_SESSION['inventory_flash'] = ['type' => 'success', 'msg' => 'Product deleted.'];
            }
        }
    } catch (Exception $e) {
        $_SESSION['inventory_flash'] = ['type' => 'error', 'msg' => $e->getMessage()];
    }

    // Preserve search + category + page across the redirect
    $qs = [];
    if (!empty($_POST['return_search'])) {
        $qs['q'] = $_POST['return_search'];
    }
    if (!empty($_POST['return_category'])) {
        $qs['category'] = $_POST['return_category'];
    }
    if (!empty($_POST['return_page'])) {
        $qs['page'] = (int) $_POST['return_page'];
    }
    header('Location: inventory.php' . ($qs ? '?' . http_build_query($qs) : ''));
    exit;
}

// Read flash message
$flash = $_SESSION['inventory_flash'] ?? null;
unset($_SESSION['inventory_flash']);

// Search + category filter + pagination params
$search   = trim($_GET['q'] ?? '');
$category = trim($_GET['category'] ?? '');
$page     = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$perPage  = 7;

$dataError = false;
$list = ['rows' => [], 'total' => 0, 'page' => 1, 'pages' => 1, 'perPage' => $perPage];
$stats = ['total' => 0, 'low' => 0, 'out' => 0, 'categories' => 0];
$categories = [];
try {
    $stats      = getInventoryStats($pdo, $workspaceId);
    $categories = getInventoryCategories($pdo, $workspaceId);
    // If the requested category is not one of the known ones, ignore it.
    if ($category !== '' && !in_array($category, $categories, true)) {
        $category = '';
    }
    $list = getInventoryItems($pdo, $workspaceId, $search, $page, $perPage, $category);
} catch (Exception $e) {
    $dataError = true;
}

// Display data for the sidebar/topbar
$displayName   = $currentUser['name'] ?: 'User';
$workspaceName = $workspace['name'] ?? 'My Workspace';
// $avatar and $workspaceLogo are handled by sidebar.php for correct path resolution
$currentPage   = 'inventory';

/** Build an inventory.php URL preserving the current search + category filter. */
function inventoryUrl(int $page, string $search, string $category = ''): string
{
    $qs = [];
    if ($search !== '') {
        $qs['q'] = $search;
    }
    if ($category !== '') {
        $qs['category'] = $category;
    }
    $qs['page'] = $page;
    return 'inventory.php?' . http_build_query($qs);
}

/** Map stock status -> badge css modifier. */
function inventoryStatusClass(string $status): string
{
    switch ($status) {
        case 'out_of_stock': return 'is-out';
        case 'low_stock':    return 'is-low';
        case 'in_stock':
        default:             return 'is-in';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory - Roots ERP</title>
    <meta name="description" content="Stock management for Roots ERP: track products, categories, stock levels and prices in real time.">
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
                    <strong>Inventory</strong>
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

            <!-- Inventory Content -->
            <div class="dashboard-content">

                <!-- Page Header -->
                <div class="customers-header">
                    <div>
                        <h1>Stock Management</h1>
                        <p>Track and manage your product stock in real time</p>
                    </div>
                    <button type="button" class="customers-add-btn" id="addItemBtn">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        <span>Add Product</span>
                    </button>
                </div>

                <?php if ($flash): ?>
                    <div class="alert alert-<?= $flash['type'] === 'error' ? 'error' : 'success'; ?>" role="alert">
                        <?= htmlspecialchars($flash['msg'], ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>
                <?php if ($dataError): ?>
                    <div class="alert alert-error" role="alert">Unable to load inventory data right now. Please try again later.</div>
                <?php endif; ?>

                <!-- Stat Cards -->
                <div class="customers-stats">
                    <article class="customers-stat-card">
                        <span class="customers-stat-head">
                            <span class="customers-stat-ico">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
                            </span>
                            Total Items
                        </span>
                        <strong><?= number_format($stats['total'], 0, ',', '.'); ?></strong>
                    </article>

                    <article class="customers-stat-card">
                        <span class="customers-stat-head">
                            <span class="customers-stat-ico">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                            </span>
                            Low Stock
                        </span>
                        <strong><?= number_format($stats['low'], 0, ',', '.'); ?></strong>
                    </article>

                    <article class="customers-stat-card">
                        <span class="customers-stat-head">
                            <span class="customers-stat-ico customers-stat-ico-danger">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18l-1.5 13.5a2 2 0 0 1-2 1.8H6.5a2 2 0 0 1-2-1.8L3 6z"/><line x1="10" y1="11" x2="14" y2="15"/><line x1="14" y1="11" x2="10" y2="15"/></svg>
                            </span>
                            Out of stock
                        </span>
                        <strong><?= number_format($stats['out'], 0, ',', '.'); ?></strong>
                    </article>

                    <article class="customers-stat-card">
                        <span class="customers-stat-head">
                            <span class="customers-stat-ico">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h6v6H4zM14 4h6v6h-6zM14 14h6v6h-6zM4 14h6v6H4z"/></svg>
                            </span>
                            Active Category
                        </span>
                        <strong><?= number_format($stats['categories'], 0, ',', '.'); ?></strong>
                    </article>
                </div>

                <!-- Inventory Table Panel -->
                <section class="customers-panel">

                    <!-- Search + Category filter -->
                    <div class="customers-toolbar">
                        <form class="customers-search" method="get" action="inventory.php">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                            <input type="search" name="q" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search by sku, product name...">
                            <?php if ($category !== ''): ?>
                                <input type="hidden" name="category" value="<?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php endif; ?>
                        </form>
                        <?php if (!empty($categories)): ?>
                            <div class="orders-filter">
                                <form method="get" action="inventory.php">
                                    <?php if ($search !== ''): ?>
                                        <input type="hidden" name="q" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php endif; ?>
                                    <select name="category" class="orders-status-select" onchange="this.form.submit()">
                                        <option value="">All categories</option>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8'); ?>" <?= $category === $cat ? 'selected' : ''; ?>><?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Table head -->
                    <?php if (!empty($list['rows'])): ?>
                        <div class="inventory-thead">
                            <span class="inv-col-sku">SKU</span>
                            <span class="inv-col-product">Product</span>
                            <span class="inv-col-category">Category</span>
                            <span class="inv-col-stock">Stock</span>
                            <span class="inv-col-price">Price</span>
                            <span class="inv-col-actions"></span>
                        </div>
                    <?php endif; ?>

                    <!-- Rows -->
                    <div class="customers-list inventory-list">
                        <?php if (empty($list['rows'])): ?>
                            <div class="customers-empty">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
                                <p>
                                    <?php if ($search !== '' || $category !== ''): ?>
                                        No products match your filters.
                                    <?php else: ?>
                                        No products yet. Add your first product to get started.
                                    <?php endif; ?>
                                </p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($list['rows'] as $it): ?>
                                <?php
                                    $qty = (int) $it['stock'];
                                    $st  = inventoryStockStatus($qty);
                                    $ijson = htmlspecialchars(json_encode([
                                        'id'           => (int) $it['id'],
                                        'sku'          => $it['sku'],
                                        'product_name' => $it['product_name'],
                                        'category'     => $it['category'],
                                        'stock'        => $qty,
                                        'price'        => (float) $it['price'],
                                        'price_label'  => formatInventoryRupiah($it['price']),
                                        'status_label' => inventoryStatusLabel($st),
                                    ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                                ?>
                                <div class="inventory-row">
                                    <div class="inv-cell inv-col-sku">
                                        <span class="inv-sku"><?= htmlspecialchars($it['sku'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>

                                    <div class="inv-cell inv-col-product">
                                        <span class="inv-product-ico" aria-hidden="true">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l1-5h16l1 5"/><path d="M4 9v10a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1V9"/><path d="M9 13h6"/></svg>
                                        </span>
                                        <div class="inv-product-text">
                                            <strong><?= htmlspecialchars($it['product_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                            <span class="inv-status <?= inventoryStatusClass($st); ?>">
                                                <span class="inv-status-dot" aria-hidden="true"></span>
                                                <?= htmlspecialchars(strtoupper(inventoryStatusLabel($st)), ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="inv-cell inv-col-category">
                                        <?php if (!empty($it['category'])): ?>
                                            <span class="inv-category">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                                                <?= htmlspecialchars($it['category'], ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="inv-category-empty">—</span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="inv-cell inv-col-stock">
                                        <span><?= number_format($qty, 0, ',', '.'); ?> pcs</span>
                                    </div>

                                    <div class="inv-cell inv-col-price">
                                        <span><?= htmlspecialchars(formatInventoryRupiah($it['price']), ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>

                                    <div class="inv-cell inv-col-actions">
                                        <button type="button" class="customer-action-btn js-view-item" data-item="<?= $ijson; ?>" aria-label="View product">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                        </button>
                                        <form method="post" action="inventory.php" onsubmit="return confirm('Delete this product? This cannot be undone.');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int) $it['id']; ?>">
                                            <input type="hidden" name="return_search" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="return_category" value="<?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="return_page" value="<?= (int) $list['page']; ?>">
                                            <button type="submit" class="customer-action-btn inv-action-delete" aria-label="Delete product">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
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
                        <nav class="customers-pagination" aria-label="Inventory pagination">
                            <a class="page-btn <?= $cur <= 1 ? 'is-disabled' : ''; ?>" href="<?= $cur <= 1 ? '#' : inventoryUrl(1, $search, $category); ?>" aria-label="First page">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="11 17 6 12 11 7"/><polyline points="18 17 13 12 18 7"/></svg>
                            </a>
                            <a class="page-btn <?= $cur <= 1 ? 'is-disabled' : ''; ?>" href="<?= $cur <= 1 ? '#' : inventoryUrl($cur - 1, $search, $category); ?>" aria-label="Previous page">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                            </a>
                            <a class="page-btn <?= $cur >= $pages ? 'is-disabled' : ''; ?>" href="<?= $cur >= $pages ? '#' : inventoryUrl($cur + 1, $search, $category); ?>" aria-label="Next page">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                            </a>
                            <a class="page-btn <?= $cur >= $pages ? 'is-disabled' : ''; ?>" href="<?= $cur >= $pages ? '#' : inventoryUrl($pages, $search, $category); ?>" aria-label="Last page">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="13 17 18 12 13 7"/><polyline points="6 17 11 12 6 7"/></svg>
                            </a>
                        </nav>
                    <?php endif; ?>
                </section>
            </div>
        </section>
    </main>

    <!-- Add / Edit Product Modal -->
    <div class="customer-modal" id="itemModal" aria-hidden="true">
        <div class="customer-modal-backdrop" id="itemModalBackdrop"></div>
        <div class="customer-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="itemModalTitle">
            <div class="customer-modal-head">
                <h2 id="itemModalTitle">Add Product</h2>
                <button type="button" class="customer-modal-close" id="itemModalClose" aria-label="Close">&times;</button>
            </div>
            <form method="post" action="inventory.php" class="customer-modal-form">
                <input type="hidden" name="action" value="add" id="itemFormAction">
                <input type="hidden" name="id" value="" id="itemFormId">
                <input type="hidden" name="return_search" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="return_category" value="<?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="return_page" value="<?= (int) $list['page']; ?>">

                <div class="customer-field-row">
                    <div class="customer-field">
                        <label for="if_sku">SKU</label>
                        <input type="text" id="if_sku" name="sku" placeholder="Auto-generated if blank">
                    </div>
                    <div class="customer-field">
                        <label for="if_category">Category</label>
                        <input type="text" id="if_category" name="category" placeholder="e.g., Bakery &amp; Pastry" list="inventoryCategoryList">
                        <datalist id="inventoryCategoryList">
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8'); ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                </div>
                <div class="customer-field">
                    <label for="if_name">Product Name</label>
                    <input type="text" id="if_name" name="product_name" required placeholder="e.g., Choco Pie">
                </div>
                <div class="customer-field-row">
                    <div class="customer-field">
                        <label for="if_stock">Stock (pcs)</label>
                        <input type="number" id="if_stock" name="stock" min="0" step="1" value="0">
                    </div>
                    <div class="customer-field">
                        <label for="if_price">Price (Rp)</label>
                        <input type="number" id="if_price" name="price" min="0" step="1000" value="0">
                    </div>
                </div>
                <div class="customer-modal-actions">
                    <button type="button" class="customer-btn-cancel" id="itemModalCancel">Cancel</button>
                    <button type="submit" class="customer-btn-save">Save Product</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Product Modal -->
    <div class="customer-modal" id="itemViewModal" aria-hidden="true">
        <div class="customer-modal-backdrop" id="itemViewBackdrop"></div>
        <div class="customer-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="itemViewTitle">
            <div class="customer-modal-head">
                <h2 id="itemViewTitle">Product Details</h2>
                <button type="button" class="customer-modal-close" id="itemViewClose" aria-label="Close">&times;</button>
            </div>
            <div class="order-view-body">
                <div class="order-view-row"><span>SKU</span><strong id="iv_sku">—</strong></div>
                <div class="order-view-row"><span>Product</span><strong id="iv_name">—</strong></div>
                <div class="order-view-row"><span>Category</span><strong id="iv_category">—</strong></div>
                <div class="order-view-row"><span>Stock</span><strong id="iv_stock">—</strong></div>
                <div class="order-view-row"><span>Status</span><strong id="iv_status">—</strong></div>
                <div class="order-view-row"><span>Price</span><strong id="iv_price">—</strong></div>
            </div>
            <div class="customer-modal-actions">
                <button type="button" class="customer-btn-cancel" id="itemViewCloseBtn">Close</button>
                <button type="button" class="customer-btn-save" id="itemViewEditBtn">Edit Product</button>
            </div>
        </div>
    </div>

    <script src="../script.js?v=<?= filemtime(__DIR__ . '/../script.js'); ?>"></script>

    <!-- Inventory page scripts -->
    <script>
    (function() {
        // === Add / Edit modal ===
        var modal = document.getElementById('itemModal');
        var openBtn = document.getElementById('addItemBtn');
        var closeBtn = document.getElementById('itemModalClose');
        var cancelBtn = document.getElementById('itemModalCancel');
        var backdrop = document.getElementById('itemModalBackdrop');
        var title = document.getElementById('itemModalTitle');
        var fAction = document.getElementById('itemFormAction');
        var fId = document.getElementById('itemFormId');
        var fSku = document.getElementById('if_sku');
        var fCategory = document.getElementById('if_category');
        var fName = document.getElementById('if_name');
        var fStock = document.getElementById('if_stock');
        var fPrice = document.getElementById('if_price');

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
                title.textContent = 'Add Product';
                fAction.value = 'add';
                fId.value = '';
                fSku.value = '';
                fCategory.value = '';
                fName.value = '';
                fStock.value = '0';
                fPrice.value = '0';
                openModal();
            });
        }

        function fillForm(data) {
            title.textContent = 'Edit Product';
            fAction.value = 'edit';
            fId.value = data.id;
            fSku.value = data.sku || '';
            fCategory.value = data.category || '';
            fName.value = data.product_name || '';
            fStock.value = data.stock != null ? data.stock : 0;
            fPrice.value = data.price != null ? data.price : 0;
            openModal();
        }

        [closeBtn, cancelBtn, backdrop].forEach(function(el) {
            if (el) el.addEventListener('click', closeModal);
        });

        // === View modal ===
        var viewModal = document.getElementById('itemViewModal');
        var viewClose = document.getElementById('itemViewClose');
        var viewCloseBtn = document.getElementById('itemViewCloseBtn');
        var viewBackdrop = document.getElementById('itemViewBackdrop');
        var viewEditBtn = document.getElementById('itemViewEditBtn');
        var ivSku = document.getElementById('iv_sku');
        var ivName = document.getElementById('iv_name');
        var ivCategory = document.getElementById('iv_category');
        var ivStock = document.getElementById('iv_stock');
        var ivStatus = document.getElementById('iv_status');
        var ivPrice = document.getElementById('iv_price');
        var currentItem = null;

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

        document.querySelectorAll('.js-view-item').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var data;
                try { data = JSON.parse(this.getAttribute('data-item')); }
                catch (e) { return; }
                currentItem = data;
                ivSku.textContent = data.sku || '—';
                ivName.textContent = data.product_name || '—';
                ivCategory.textContent = data.category || '—';
                ivStock.textContent = (data.stock != null ? data.stock : 0) + ' pcs';
                ivStatus.textContent = data.status_label || '—';
                ivPrice.textContent = data.price_label || '—';
                openView();
            });
        });

        [viewClose, viewCloseBtn, viewBackdrop].forEach(function(el) {
            if (el) el.addEventListener('click', closeView);
        });
        if (viewEditBtn) {
            viewEditBtn.addEventListener('click', function() {
                if (!currentItem) return;
                closeView();
                fillForm(currentItem);
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
                    window.location.href = 'inventory.php?workspace_id=' + this.getAttribute('data-workspace-id');
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
