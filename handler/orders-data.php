<?php
/**
 * Roots ERP - Order Management Data Helper
 *
 * Ensures the `orders` table exists and exposes helpers for stats,
 * listing (search + status filter + pagination) and CRUD. No demo/seed
 * data is inserted: a fresh workspace starts empty until the user adds orders.
 */

/** Allowed order statuses. */
function orderStatuses(): array
{
    return ['on_progress', 'on_hold', 'pending_payment', 'delivered', 'cancelled'];
}

/** Human-friendly label for a status key. */
function orderStatusLabel(string $status): string
{
    $map = [
        'on_progress'     => 'On Progress',
        'on_hold'         => 'On Hold',
        'pending_payment' => 'Pending Payment',
        'delivered'       => 'Delivered',
        'cancelled'       => 'Cancelled',
    ];
    return $map[$status] ?? ucfirst(str_replace('_', ' ', $status));
}

/**
 * Create the orders table if it doesn't already exist.
 */
function ensureOrdersTable(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `orders` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `workspace_id` INT(11) NOT NULL,
            `order_code` VARCHAR(50) NOT NULL,
            `customer_name` VARCHAR(255) NOT NULL,
            `item` VARCHAR(255) DEFAULT NULL,
            `quantity` INT(11) NOT NULL DEFAULT 1,
            `price` DECIMAL(18,2) NOT NULL DEFAULT 0,
            `status` ENUM('on_progress','on_hold','pending_payment','delivered','cancelled') NOT NULL DEFAULT 'on_progress',
            `order_date` DATE DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `idx_ord_ws` (`workspace_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );

    // Migration: add `quantity` for tables created before this column existed.
    try {
        $col = $pdo->query("SHOW COLUMNS FROM `orders` LIKE 'quantity'")->fetch();
        if (!$col) {
            $pdo->exec("ALTER TABLE `orders` ADD COLUMN `quantity` INT(11) NOT NULL DEFAULT 1 AFTER `item`");
        }
    } catch (Exception $e) {
        // Ignore: if SHOW/ALTER fails the table is already current or unavailable.
    }
}

/**
 * Aggregate stats for the four cards at the top of the page.
 *
 * @return array{active:int,pending:int,delivered:int,total:int}
 */
function getOrderStats(PDO $pdo, int $workspaceId): array
{
    $active = $pdo->prepare(
        "SELECT COUNT(*) FROM orders WHERE workspace_id = ? AND status IN ('on_progress','on_hold')"
    );
    $active->execute([$workspaceId]);

    $pending = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE workspace_id = ? AND status = 'pending_payment'");
    $pending->execute([$workspaceId]);

    $delivered = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE workspace_id = ? AND status = 'delivered'");
    $delivered->execute([$workspaceId]);

    $total = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE workspace_id = ?');
    $total->execute([$workspaceId]);

    return [
        'active'    => (int) $active->fetchColumn(),
        'pending'   => (int) $pending->fetchColumn(),
        'delivered' => (int) $delivered->fetchColumn(),
        'total'     => (int) $total->fetchColumn(),
    ];
}

/**
 * Fetch a paginated, optionally-searched/filtered list of orders.
 *
 * @param string $status Optional status filter (one of orderStatuses()).
 * @return array{rows:array,total:int,page:int,pages:int,perPage:int}
 */
function getOrders(PDO $pdo, int $workspaceId, string $search = '', int $page = 1, int $perPage = 7, string $status = ''): array
{
    $page    = max(1, $page);
    $perPage = max(1, $perPage);
    $offset  = ($page - 1) * $perPage;

    $where  = 'WHERE workspace_id = :ws';
    $params = [':ws' => $workspaceId];

    if ($search !== '') {
        $where .= ' AND (customer_name LIKE :q OR order_code LIKE :q OR item LIKE :q)';
        $params[':q'] = '%' . $search . '%';
    }

    if ($status !== '' && in_array($status, orderStatuses(), true)) {
        $where .= ' AND status = :status';
        $params[':status'] = $status;
    }

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM orders $where");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $pages = max(1, (int) ceil($total / $perPage));

    $sql = "SELECT id, order_code, customer_name, item, quantity, price, status, order_date
            FROM orders $where
            ORDER BY id DESC
            LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return [
        'rows'    => $stmt->fetchAll(PDO::FETCH_ASSOC),
        'total'   => $total,
        'page'    => $page,
        'pages'   => $pages,
        'perPage' => $perPage,
    ];
}

/** Fetch a single order scoped to its workspace. */
function getOrder(PDO $pdo, int $workspaceId, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ? AND workspace_id = ? LIMIT 1');
    $stmt->execute([$id, $workspaceId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/** Generate a unique-ish order code like "CRH-1913". */
function generateOrderCode(PDO $pdo, int $workspaceId): string
{
    for ($i = 0; $i < 8; $i++) {
        $code = 'CRH-' . random_int(1000, 9999);
        $check = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE workspace_id = ? AND order_code = ?');
        $check->execute([$workspaceId, $code]);
        if ((int) $check->fetchColumn() === 0) {
            return $code;
        }
    }
    return 'CRH-' . time();
}

/**
 * Insert a new order. Returns the new id.
 */
function addOrder(PDO $pdo, int $workspaceId, array $data): int
{
    $code = trim($data['order_code'] ?? '');
    if ($code === '') {
        $code = generateOrderCode($pdo, $workspaceId);
    }
    $status = $data['status'] ?? 'on_progress';
    if (!in_array($status, orderStatuses(), true)) {
        $status = 'on_progress';
    }

    $stmt = $pdo->prepare(
        'INSERT INTO orders (workspace_id, order_code, customer_name, item, quantity, price, status, order_date)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $workspaceId,
        $code,
        $data['customer_name'],
        $data['item'] ?: null,
        max(1, (int) ($data['quantity'] ?? 1)),
        (float) ($data['price'] ?? 0),
        $status,
        $data['order_date'] ?: null,
    ]);
    return (int) $pdo->lastInsertId();
}

/**
 * Update an existing order scoped to its workspace.
 */
function updateOrder(PDO $pdo, int $workspaceId, int $id, array $data): void
{
    $status = $data['status'] ?? 'on_progress';
    if (!in_array($status, orderStatuses(), true)) {
        $status = 'on_progress';
    }

    $stmt = $pdo->prepare(
        'UPDATE orders
         SET order_code = ?, customer_name = ?, item = ?, quantity = ?, price = ?, status = ?, order_date = ?
         WHERE id = ? AND workspace_id = ?'
    );
    $stmt->execute([
        trim($data['order_code'] ?? ''),
        $data['customer_name'],
        $data['item'] ?: null,
        max(1, (int) ($data['quantity'] ?? 1)),
        (float) ($data['price'] ?? 0),
        $status,
        $data['order_date'] ?: null,
        $id,
        $workspaceId,
    ]);
}

/** Set just the status of an order (used by the cancel action). */
function setOrderStatus(PDO $pdo, int $workspaceId, int $id, string $status): void
{
    if (!in_array($status, orderStatuses(), true)) {
        return;
    }
    $stmt = $pdo->prepare('UPDATE orders SET status = ? WHERE id = ? AND workspace_id = ?');
    $stmt->execute([$status, $id, $workspaceId]);
}

/** Delete an order scoped to its workspace. */
function deleteOrder(PDO $pdo, int $workspaceId, int $id): void
{
    $stmt = $pdo->prepare('DELETE FROM orders WHERE id = ? AND workspace_id = ?');
    $stmt->execute([$id, $workspaceId]);
}

/** Format a value as a Rupiah string, e.g. 55000 -> "Rp 55.000". */
function formatOrderRupiah($value): string
{
    return 'Rp ' . number_format((float) $value, 0, ',', '.');
}
