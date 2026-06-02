<?php
/**
 * Roots ERP - Customer Management Data Helper (PostgreSQL Version)
 */

/**
 * Agregasi stats untuk 4 kartu di atas.
 * Catatan: Menggunakan sintaks PostgreSQL untuk filter tanggal.
 */
function getCustomerStats(PDO $pdo, int $workspaceId): array
{
    // Total
    $total = $pdo->prepare('SELECT COUNT(*) FROM customers WHERE workspace_id = ?');
    $total->execute([$workspaceId]);

    // New This Month (Postgres compatible)
    $new = $pdo->prepare("
        SELECT COUNT(*) FROM customers
        WHERE workspace_id = ?
          AND EXTRACT(MONTH FROM created_at) = EXTRACT(MONTH FROM CURRENT_DATE)
          AND EXTRACT(YEAR FROM created_at) = EXTRACT(YEAR FROM CURRENT_DATE)
    ");
    $new->execute([$workspaceId]);

    // Repeat & Uncontacted (keduanya sudah standar)
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
 * Fetch list pelanggan dengan pagination & search.
 */
function getCustomers(PDO $pdo, int $workspaceId, string $search = '', int $page = 1, int $perPage = 6): array
{
    $page    = max(1, $page);
    $perPage = max(1, $perPage);
    $offset  = ($page - 1) * $perPage;

    $where  = 'WHERE workspace_id = :ws';
    $params = [':ws' => $workspaceId];

    if ($search !== '') {
        $where .= ' AND (name ILIKE :q OR email ILIKE :q)'; // ILIKE biar case-insensitive di Postgres
        $params[':q'] = '%' . $search . '%';
    }

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM customers $where");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $pages = max(1, (int) ceil($total / $perPage));

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
 * Insert customer baru (PostgreSQL RETURNING style).
 */
function addCustomer(PDO $pdo, int $workspaceId, array $data): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO customers (workspace_id, name, email, total_purchase, last_transaction, segment, contacted)
         VALUES (?, ?, ?, ?, ?, ?, ?) RETURNING id'
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
    return (int) $stmt->fetchColumn();
}

/**
 * Update customer (tetap sama).
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
 * Delete customer (tetap sama).
 */
function deleteCustomer(PDO $pdo, int $workspaceId, int $id): void
{
    $stmt = $pdo->prepare('DELETE FROM customers WHERE id = ? AND workspace_id = ?');
    $stmt->execute([$id, $workspaceId]);
}

function formatCustomerRupiah($value): string
{
    return 'Rp ' . number_format((float) $value, 0, ',', '.');
}
