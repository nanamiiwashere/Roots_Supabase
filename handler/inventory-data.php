<?php
/**
 * Roots ERP - Inventory / Stock Management Data Helper
 *
 * Ensures the `inventory` table exists and exposes helpers for stats,
 * listing (search + category filter + pagination) and CRUD. No demo/seed
 * data is inserted: a fresh workspace starts empty until the user adds items.
 *
 * Stock status is derived from the quantity:
 *   - 0           -> "out_of_stock"
 *   - 1..low_at   -> "low_stock"   (low_at defaults to 5)
 *   - > low_at    -> "in_stock"
 */

/** Default threshold at/under which an item counts as "low stock". */
function inventoryLowThreshold(): int
{
    return 5;
}

/** Derive the stock status key from a quantity. */
function inventoryStockStatus(int $qty): string
{
    if ($qty <= 0) {
        return 'out_of_stock';
    }
    if ($qty <= inventoryLowThreshold()) {
        return 'low_stock';
    }
    return 'in_stock';
}

/** Human-friendly label for a stock status key. */
function inventoryStatusLabel(string $status): string
{
    $map = [
        'in_stock'     => 'In Stock',
        'low_stock'    => 'Low Stock',
        'out_of_stock' => 'Out of Stock',
    ];
    return $map[$status] ?? ucfirst(str_replace('_', ' ', $status));
}

/**
 * Create the inventory table if it doesn't already exist.
 */

/**
 * Aggregate stats for the four cards at the top of the page.
 *
 * @return array{total:int,low:int,out:int,categories:int}
 */
function getInventoryStats(PDO $pdo, int $workspaceId): array
{
    $low = inventoryLowThreshold();

    $total = $pdo->prepare('SELECT COUNT(*) FROM inventory WHERE workspace_id = ?');
    $total->execute([$workspaceId]);

    $lowStock = $pdo->prepare('SELECT COUNT(*) FROM inventory WHERE workspace_id = ? AND stock > 0 AND stock <= ?');
    $lowStock->execute([$workspaceId, $low]);

    $out = $pdo->prepare('SELECT COUNT(*) FROM inventory WHERE workspace_id = ? AND stock <= 0');
    $out->execute([$workspaceId]);

    $cats = $pdo->prepare("SELECT COUNT(DISTINCT category) FROM inventory WHERE workspace_id = ? AND category IS NOT NULL AND category <> ''");
    $cats->execute([$workspaceId]);

    return [
        'total'      => (int) $total->fetchColumn(),
        'low'        => (int) $lowStock->fetchColumn(),
        'out'        => (int) $out->fetchColumn(),
        'categories' => (int) $cats->fetchColumn(),
    ];
}

/** Distinct category names for the active workspace (for the filter dropdown). */
function getInventoryCategories(PDO $pdo, int $workspaceId): array
{
    $stmt = $pdo->prepare(
        "SELECT DISTINCT category FROM inventory
         WHERE workspace_id = ? AND category IS NOT NULL AND category <> ''
         ORDER BY category ASC"
    );
    $stmt->execute([$workspaceId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
}

/**
 * Fetch a paginated, optionally-searched/filtered list of inventory items.
 *
 * @return array{rows:array,total:int,page:int,pages:int,perPage:int}
 */
function getInventoryItems(PDO $pdo, int $workspaceId, string $search = '', int $page = 1, int $perPage = 7, string $category = ''): array
{
    $page    = max(1, $page);
    $perPage = max(1, $perPage);
    $offset  = ($page - 1) * $perPage;

    $where  = 'WHERE workspace_id = :ws';
    $params = [':ws' => $workspaceId];

    if ($search !== '') {
        $where .= ' AND (sku LIKE :q OR product_name LIKE :q)';
        $params[':q'] = '%' . $search . '%';
    }

    if ($category !== '') {
        $where .= ' AND category = :cat';
        $params[':cat'] = $category;
    }

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM inventory $where");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $pages = max(1, (int) ceil($total / $perPage));

    $sql = "SELECT id, sku, product_name, category, stock, price
            FROM inventory $where
            ORDER BY id ASC
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

/** Generate a SKU from a product name, e.g. "Choco Pie" -> "PC-001". */
function generateSku(PDO $pdo, int $workspaceId, string $productName): string
{
    $clean = strtoupper(preg_replace('/[^A-Za-z]/', '', $productName));
    $prefix = 'P';
    if ($clean !== '') {
        $prefix = 'P' . substr($clean, 0, 1);
    }

    for ($i = 0; $i < 999; $i++) {
        $candidate = $prefix . '-' . str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT);
        $check = $pdo->prepare('SELECT COUNT(*) FROM inventory WHERE workspace_id = ? AND sku = ?');
        $check->execute([$workspaceId, $candidate]);
        if ((int) $check->fetchColumn() === 0) {
            return $candidate;
        }
    }
    return $prefix . '-' . time();
}

/**
 * Insert a new inventory item. Returns the new id.
 */
function addInventoryItem(PDO $pdo, int $workspaceId, array $data): int
{
    $sku = trim($data['sku'] ?? '');
    if ($sku === '') {
        $sku = generateSku($pdo, $workspaceId, $data['product_name'] ?? 'Item');
    }

    $stmt = $pdo->prepare(
        'INSERT INTO inventory (workspace_id, sku, product_name, category, stock, price)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $workspaceId,
        $sku,
        $data['product_name'],
        $data['category'] ?: null,
        max(0, (int) ($data['stock'] ?? 0)),
        (float) ($data['price'] ?? 0),
    ]);
    return (int) $pdo->lastInsertId();
}

/**
 * Update an existing inventory item scoped to its workspace.
 */
function updateInventoryItem(PDO $pdo, int $workspaceId, int $id, array $data): void
{
    $stmt = $pdo->prepare(
        'UPDATE inventory
         SET sku = ?, product_name = ?, category = ?, stock = ?, price = ?
         WHERE id = ? AND workspace_id = ?'
    );
    $stmt->execute([
        trim($data['sku'] ?? ''),
        $data['product_name'],
        $data['category'] ?: null,
        max(0, (int) ($data['stock'] ?? 0)),
        (float) ($data['price'] ?? 0),
        $id,
        $workspaceId,
    ]);
}

/** Delete an inventory item scoped to its workspace. */
function deleteInventoryItem(PDO $pdo, int $workspaceId, int $id): void
{
    $stmt = $pdo->prepare('DELETE FROM inventory WHERE id = ? AND workspace_id = ?');
    $stmt->execute([$id, $workspaceId]);
}

/** Format a value as a Rupiah string, e.g. 33000 -> "Rp 33.000". */
function formatInventoryRupiah($value): string
{
    return 'Rp ' . number_format((float) $value, 0, ',', '.');
}
