<?php
/**
 * Roots ERP - Finance Management Data Helper
 *
 * Ensures the `finance_transactions` table exists and exposes helpers for
 * summary stats, the monthly revenue trend (for the chart), the recent
 * transactions feed and CRUD. No demo/seed data is inserted: a fresh
 * workspace starts empty until the user records transactions.
 *
 * A transaction is either:
 *   - type "income"  -> adds to the balance
 *   - type "expense" -> subtracts from the balance
 * and has a status of "success" or "pending".
 */

/** Allowed transaction types. */
function financeTypes(): array
{
    return ['income', 'expense'];
}

/** Allowed transaction statuses. */
function financeStatuses(): array
{
    return ['success', 'pending'];
}

/** Human-friendly label for a status key. */
function financeStatusLabel(string $status): string
{
    $map = ['success' => 'Success', 'pending' => 'Pending'];
    return $map[$status] ?? ucfirst($status);
}

/**
 * Create the finance_transactions table if it doesn't already exist.
 */


/**
 * Summary figures for the balance / income / expense cards.
 * Only "success" transactions count toward the realised balance.
 *
 * @return array{balance:float,income:float,expenses:float,prev_balance:float,change_percent:?float}
 */
function getFinanceSummary(PDO $pdo, int $workspaceId): array
{
    $income = $pdo->prepare(
        "SELECT COALESCE(SUM(amount), 0) FROM finance_transactions
         WHERE workspace_id = ? AND type = 'income' AND status = 'success'"
    );
    $income->execute([$workspaceId]);
    $income = (float) $income->fetchColumn();

    $expenses = $pdo->prepare(
        "SELECT COALESCE(SUM(amount), 0) FROM finance_transactions
         WHERE workspace_id = ? AND type = 'expense' AND status = 'success'"
    );
    $expenses->execute([$workspaceId]);
    $expenses = (float) $expenses->fetchColumn();

    $balance = $income - $expenses;

    // Net for the current month vs previous month, to derive a change %.
    $thisMonthStmt = $pdo->prepare(
        "SELECT
            COALESCE(SUM(CASE WHEN type='income' THEN amount ELSE -amount END), 0)
         FROM finance_transactions
         WHERE workspace_id = ? AND status = 'success'
           AND YEAR(tx_date) = YEAR(CURRENT_DATE())
           AND MONTH(tx_date) = MONTH(CURRENT_DATE())"
    );
    $thisMonthStmt->execute([$workspaceId]);
    $thisMonthNet = (float) $thisMonthStmt->fetchColumn();

    $prevMonthStmt = $pdo->prepare(
        "SELECT
            COALESCE(SUM(CASE WHEN type='income' THEN amount ELSE -amount END), 0)
         FROM finance_transactions
         WHERE workspace_id = ? AND status = 'success'
           AND YEAR(tx_date) = YEAR(CURRENT_DATE() - INTERVAL 1 MONTH)
           AND MONTH(tx_date) = MONTH(CURRENT_DATE() - INTERVAL 1 MONTH)"
    );
    $prevMonthStmt->execute([$workspaceId]);
    $prevMonthNet = (float) $prevMonthStmt->fetchColumn();

    $changePercent = null;
    if (abs($prevMonthNet) > 0.0001) {
        $changePercent = (($thisMonthNet - $prevMonthNet) / abs($prevMonthNet)) * 100;
    }

    return [
        'balance'        => $balance,
        'income'         => $income,
        'expenses'       => $expenses,
        'change_percent' => $changePercent,
    ];
}

/**
 * Monthly net revenue series for the trend chart, covering the last $months
 * months up to the current month. Returns labels + values aligned by month.
 *
 * @return array{labels:array<string>,values:array<float>}
 */
function getFinanceMonthlyTrend(PDO $pdo, int $workspaceId, int $months = 6): array
{
    $stmt = $pdo->prepare(
        "SELECT DATE_FORMAT(tx_date, '%Y-%m') AS ym,
                COALESCE(SUM(CASE WHEN type='income' THEN amount ELSE -amount END), 0) AS net
         FROM finance_transactions
         WHERE workspace_id = ? AND status = 'success' AND tx_date IS NOT NULL
         GROUP BY ym"
    );
    $stmt->execute([$workspaceId]);

    $byMonth = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $byMonth[$row['ym']] = (float) $row['net'];
    }

    $labels = [];
    $values = [];
    for ($i = $months - 1; $i >= 0; $i--) {
        $ts = strtotime("first day of -$i month");
        $key = date('Y-m', $ts);
        $labels[] = date('M', $ts);
        $values[] = $byMonth[$key] ?? 0;
    }

    return ['labels' => $labels, 'values' => $values];
}

/** Whether the workspace has any transactions with a date (chart data). */
function financeHasTrendData(array $trend): bool
{
    foreach ($trend['values'] as $v) {
        if (abs($v) > 0.0001) {
            return true;
        }
    }
    return false;
}

/**
 * Fetch a paginated, optionally-searched/filtered list of transactions.
 *
 * @param string $type Optional type filter (income|expense).
 * @return array{rows:array,total:int,page:int,pages:int,perPage:int}
 */
function getFinanceTransactions(PDO $pdo, int $workspaceId, string $search = '', int $page = 1, int $perPage = 8, string $type = ''): array
{
    $page    = max(1, $page);
    $perPage = max(1, $perPage);
    $offset  = ($page - 1) * $perPage;

    $where  = 'WHERE workspace_id = :ws';
    $params = [':ws' => $workspaceId];

    if ($search !== '') {
        $where .= ' AND (title LIKE :q OR tx_code LIKE :q)';
        $params[':q'] = '%' . $search . '%';
    }

    if ($type !== '' && in_array($type, financeTypes(), true)) {
        $where .= ' AND type = :type';
        $params[':type'] = $type;
    }

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM finance_transactions $where");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $pages = max(1, (int) ceil($total / $perPage));

    $sql = "SELECT id, tx_code, title, type, amount, status, tx_date
            FROM finance_transactions $where
            ORDER BY tx_date DESC, id DESC
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

/** Generate a unique-ish transaction code like "TX-1024". */
function generateTxCode(PDO $pdo, int $workspaceId): string
{
    for ($i = 0; $i < 8; $i++) {
        $code = 'TX-' . random_int(1000, 9999);
        $check = $pdo->prepare('SELECT COUNT(*) FROM finance_transactions WHERE workspace_id = ? AND tx_code = ?');
        $check->execute([$workspaceId, $code]);
        if ((int) $check->fetchColumn() === 0) {
            return $code;
        }
    }
    return 'TX-' . time();
}

/** Insert a new transaction. Returns the new id. */
function addTransaction(PDO $pdo, int $workspaceId, array $data): int
{
    $code = trim($data['tx_code'] ?? '');
    if ($code === '') {
        $code = generateTxCode($pdo, $workspaceId);
    }
    $type = in_array($data['type'] ?? '', financeTypes(), true) ? $data['type'] : 'income';
    $status = in_array($data['status'] ?? '', financeStatuses(), true) ? $data['status'] : 'success';

    $stmt = $pdo->prepare(
        'INSERT INTO finance_transactions (workspace_id, tx_code, title, type, amount, status, tx_date)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $workspaceId,
        $code,
        $data['title'],
        $type,
        max(0, (float) ($data['amount'] ?? 0)),
        $status,
        $data['tx_date'] ?: null,
    ]);
    return (int) $pdo->lastInsertId();
}

/** Update an existing transaction scoped to its workspace. */
function updateTransaction(PDO $pdo, int $workspaceId, int $id, array $data): void
{
    $type = in_array($data['type'] ?? '', financeTypes(), true) ? $data['type'] : 'income';
    $status = in_array($data['status'] ?? '', financeStatuses(), true) ? $data['status'] : 'success';

    $stmt = $pdo->prepare(
        'UPDATE finance_transactions
         SET tx_code = ?, title = ?, type = ?, amount = ?, status = ?, tx_date = ?
         WHERE id = ? AND workspace_id = ?'
    );
    $stmt->execute([
        trim($data['tx_code'] ?? ''),
        $data['title'],
        $type,
        max(0, (float) ($data['amount'] ?? 0)),
        $status,
        $data['tx_date'] ?: null,
        $id,
        $workspaceId,
    ]);
}

/** Delete a transaction scoped to its workspace. */
function deleteTransaction(PDO $pdo, int $workspaceId, int $id): void
{
    $stmt = $pdo->prepare('DELETE FROM finance_transactions WHERE id = ? AND workspace_id = ?');
    $stmt->execute([$id, $workspaceId]);
}

/** Format a value as a Rupiah string, e.g. 3500000 -> "Rp 3.500.000". */
function formatFinanceRupiah($value): string
{
    return 'Rp ' . number_format((float) $value, 0, ',', '.');
}
