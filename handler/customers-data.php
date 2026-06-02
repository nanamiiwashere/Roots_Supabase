<?php
/**
 * Roots ERP - Customer Management Data Helper
 *
 * Ensures the `customers` table exists, seeds a small demo set per workspace
 * (only when empty, so the page looks like the mockup out of the box), and
 * exposes helpers for stats, listing (search + pagination) and CRUD.
 */

/**
 * Create the customers table if it doesn't already exist.
 */
function ensureCustomersTable(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `customers` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `workspace_id` INT(11) NOT NULL,
            `name` VARCHAR(255) NOT NULL,
            `email` VARCHAR(255) DEFAULT NULL,
            `total_purchase` DECIMAL(18,2) NOT NULL DEFAULT 0,
            `last_transaction` DATE DEFAULT NULL,
            `segment` ENUM('new','regular') NOT NULL DEFAULT 'new',
            `contacted` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `idx_cust_ws` (`workspace_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
}

/**
 * Seed demo customers for a workspace when none exist yet.
 * Mirrors the data shown in the mockup so the page is populated immediately.
 */
function seedCustomersDemoData(PDO $pdo, int $workspaceId): void
{
    $check = $pdo->prepare('SELECT COUNT(*) FROM customers WHERE workspace_id = ?');
    $check->execute([$workspaceId]);
    if ((int) $check->fetchColumn() > 0) {
        return;
    }

    $rows = [
        // name, email, total_purchase, last_transaction, segment, contacted
        ['Budi Santoso',    'budi.santoso@gmail.com',     85000,  '2026-05-12', 'new',     1],
        ['Siti Aminah',     'siti.aminah@yahoo.com',      120000, '2026-05-10', 'regular', 1],
        ['Haruki Tanaka',   'haruki.tanaka@gmail.com',    45000,  '2026-05-14', 'new',     0],
        ['Dewi Lestari',    'dewi.lestari@gmail.com',     200000, '2026-04-28', 'new',     1],
        ['Yuki Matsumoto',  'yuki.matsumoto@outlook.com', 75000,  '2026-05-15', 'regular', 0],
        ['Kenji Nakamura',  'kenji.nakamura@gmail.com',   180000, '2026-05-11', 'regular', 1],
    ];

    $stmt = $pdo->prepare(
        'INSERT INTO customers (workspace_id, name, email, total_purchase, last_transaction, segment, contacted)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    foreach ($rows as $r) {
        $stmt->execute([$workspaceId, $r[0], $r[1], $r[2], $r[3], $r[4], $r[5]]);
    }
}

/**
 * Aggregate stats for the four cards at the top of the page.
 *
 * @return array{total:int,new_this_month:int,repeat:int,uncontacted:int}
 */
function getCustomerStats(PDO $pdo, int $workspaceId): array
{
    $total = $pdo->prepare('SELECT COUNT(*) FROM customers WHERE workspace_id = ?');
    $total->execute([$workspaceId]);

    $new = $pdo->prepare(
        "SELECT COUNT(*) FROM customers
         WHERE workspace_id = ?
           AND MONTH(created_at) = MONTH(CURRENT_DATE())
           AND YEAR(created_at) = YEAR(CURRENT_DATE())"
    );
    $new->execute([$workspaceId]);

    $repeat = $pdo->prepare("SELECT COUNT(*) FROM customers WHERE workspace_id = ? AND segment = 'regular'");
    $repeat->execute([$workspaceId]);

    $uncontacted = $pdo->prepare('SELECT COUNT(*) FROM customers WHERE workspace_id = ? AND contacted = 0');
    $uncontacted->execute([$workspaceId]);

    return [
        'total'          => (int) $total->fetchColumn(),
        'new_this_month' => (int) $new->fetchColumn(),
        'repeat'         => (int) $repeat->fetchColumn(),
        'uncontacted'    => (int) $uncontacted->fetchColumn(),
    ];
}

/**
 * Fetch a paginated, optionally-searched list of customers.
 *
 * @return array{rows:array,total:int,page:int,pages:int,perPage:int}
 */
function getCustomers(PDO $pdo, int $workspaceId, string $search = '', int $page = 1, int $perPage = 6): array
{
    $page    = max(1, $page);
    $perPage = max(1, $perPage);
    $offset  = ($page - 1) * $perPage;

    $where  = 'WHERE workspace_id = :ws';
    $params = [':ws' => $workspaceId];

    if ($search !== '') {
        $where .= ' AND (name LIKE :q OR email LIKE :q)';
        $params[':q'] = '%' . $search . '%';
    }

    // Total matching rows
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM customers $where");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $pages = max(1, (int) ceil($total / $perPage));

    // Page rows
    $sql = "SELECT id, name, email, total_purchase, last_transaction, segment, contacted
            FROM customers $where
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

/**
 * Insert a new customer. Returns the new id.
 */
function addCustomer(PDO $pdo, int $workspaceId, array $data): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO customers (workspace_id, name, email, total_purchase, last_transaction, segment, contacted)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $workspaceId,
        $data['name'],
        $data['email'] ?: null,
        (float) ($data['total_purchase'] ?? 0),
        $data['last_transaction'] ?: null,
        in_array($data['segment'] ?? 'new', ['new', 'regular'], true) ? $data['segment'] : 'new',
        !empty($data['contacted']) ? 1 : 0,
    ]);
    return (int) $pdo->lastInsertId();
}

/**
 * Update an existing customer scoped to its workspace.
 */
function updateCustomer(PDO $pdo, int $workspaceId, int $id, array $data): void
{
    $stmt = $pdo->prepare(
        'UPDATE customers
         SET name = ?, email = ?, total_purchase = ?, last_transaction = ?, segment = ?, contacted = ?
         WHERE id = ? AND workspace_id = ?'
    );
    $stmt->execute([
        $data['name'],
        $data['email'] ?: null,
        (float) ($data['total_purchase'] ?? 0),
        $data['last_transaction'] ?: null,
        in_array($data['segment'] ?? 'new', ['new', 'regular'], true) ? $data['segment'] : 'new',
        !empty($data['contacted']) ? 1 : 0,
        $id,
        $workspaceId,
    ]);
}

/**
 * Delete a customer scoped to its workspace.
 */
function deleteCustomer(PDO $pdo, int $workspaceId, int $id): void
{
    $stmt = $pdo->prepare('DELETE FROM customers WHERE id = ? AND workspace_id = ?');
    $stmt->execute([$id, $workspaceId]);
}

/**
 * Format a value as a Rupiah string, e.g. 85000 -> "Rp 85.000".
 */
function formatCustomerRupiah($value): string
{
    return 'Rp ' . number_format((float) $value, 0, ',', '.');
}
