<?php
/**
 * Roots ERP - Analytics Dashboard Data Helper
 *
 * Ensures the analytics tables exist and returns the datasets used by the
 * dashboard "Overview" section (index.php).
 *
 * IMPORTANT: This helper does NOT seed demo data. A fresh workspace starts
 * empty, so the dashboard renders its empty states:
 *   - numeric cards show 0 (or "Rp 0")
 *   - the change line shows "—"
 *   - charts are hidden until real data is present
 *
 * Tables created:
 *  - analytics_metrics  : the four summary cards (revenue, sales, orders, profit)
 *  - analytics_monthly  : monthly sales + revenue series (bar / area charts)
 *  - analytics_activity : recent activity feed items
 *  - analytics_reviews  : product review feed items
 */

/**
 * Create analytics tables if they don't already exist.
 */
function ensureAnalyticsTables(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `analytics_metrics` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `workspace_id` INT(11) NOT NULL,
            `metric_key` VARCHAR(50) NOT NULL,
            `metric_value` DECIMAL(18,2) NOT NULL DEFAULT 0,
            `change_percent` DECIMAL(6,2) DEFAULT NULL,
            `trend` ENUM('up','down','flat') NOT NULL DEFAULT 'up',
            `sort_order` INT(11) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_am_ws_key` (`workspace_id`, `metric_key`),
            INDEX `idx_am_ws` (`workspace_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `analytics_monthly` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `workspace_id` INT(11) NOT NULL,
            `month_label` VARCHAR(20) NOT NULL,
            `sales` INT(11) NOT NULL DEFAULT 0,
            `revenue` DECIMAL(18,2) NOT NULL DEFAULT 0,
            `sort_order` INT(11) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            INDEX `idx_amo_ws` (`workspace_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `analytics_activity` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `workspace_id` INT(11) NOT NULL,
            `title` VARCHAR(255) NOT NULL,
            `code` VARCHAR(50) DEFAULT NULL,
            `status` VARCHAR(50) DEFAULT NULL,
            `activity_type` ENUM('order','finance') NOT NULL DEFAULT 'order',
            `activity_date` DATE DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `idx_aa_ws` (`workspace_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
    
    // Migration: add activity_type column if not exists
    try {
        $col = $pdo->query("SHOW COLUMNS FROM `analytics_activity` LIKE 'activity_type'")->fetch();
        if (!$col) {
            $pdo->exec("ALTER TABLE `analytics_activity` ADD COLUMN `activity_type` ENUM('order','finance') NOT NULL DEFAULT 'order' AFTER `status`");
        }
    } catch (Exception $e) {
        // Ignore migration errors
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `analytics_reviews` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `workspace_id` INT(11) NOT NULL,
            `reviewer_name` VARCHAR(255) NOT NULL,
            `rating` TINYINT(1) NOT NULL DEFAULT 5,
            `comment` TEXT DEFAULT NULL,
            `code` VARCHAR(50) DEFAULT NULL,
            `review_date` DATE DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `idx_ar_ws` (`workspace_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
}

/**
 * Sync orders data to analytics tables for a workspace.
 * Aggregates order data by month and updates analytics_monthly and analytics_metrics.
 */
function syncOrdersToAnalytics(PDO $pdo, int $workspaceId): void
{
    ensureAnalyticsTables($pdo);
    
    try {
        $checkTable = $pdo->query("SHOW TABLES LIKE 'orders'")->fetch();
        if (!$checkTable) {
            return;
        }
        
        // Sync monthly data
        $stmt = $pdo->prepare(
            'SELECT 
                DATE_FORMAT(order_date, "%Y-%m") AS month_key,
                DATE_FORMAT(order_date, "%b") AS month_label,
                COUNT(*) AS sales,
                SUM(price * quantity) AS revenue,
                MIN(order_date) AS sort_date
             FROM orders 
             WHERE workspace_id = ? 
               AND order_date IS NOT NULL 
               AND status != "cancelled"
             GROUP BY DATE_FORMAT(order_date, "%Y-%m")
             ORDER BY sort_date ASC'
        );
        $stmt->execute([$workspaceId]);
        $monthlyData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $pdo->prepare('DELETE FROM analytics_monthly WHERE workspace_id = ?')
            ->execute([$workspaceId]);
        
        $insertMonthly = $pdo->prepare(
            'INSERT INTO analytics_monthly (workspace_id, month_label, sales, revenue, sort_order)
             VALUES (?, ?, ?, ?, ?)'
        );
        $sortOrder = 1;
        foreach ($monthlyData as $row) {
            $insertMonthly->execute([
                $workspaceId,
                $row['month_label'],
                (int) $row['sales'],
                (float) $row['revenue'],
                $sortOrder++
            ]);
        }
        
        // Sync metrics
        $metrics = [
            'revenue' => ['value' => 0, 'trend' => 'up'],
            'sales'   => ['value' => 0, 'trend' => 'up'],
            'orders'  => ['value' => 0, 'trend' => 'up'],
            'profit'  => ['value' => 0, 'trend' => 'up'],
        ];
        
        $totalsStmt = $pdo->prepare(
            'SELECT 
                COUNT(*) AS total_orders,
                SUM(quantity) AS total_sales,
                SUM(price * quantity) AS total_revenue
             FROM orders 
             WHERE workspace_id = ? AND status != "cancelled"'
        );
        $totalsStmt->execute([$workspaceId]);
        $totals = $totalsStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($totals) {
            $metrics['orders']['value'] = (int) $totals['total_orders'];
            $metrics['sales']['value']  = (int) $totals['total_sales'];
            $metrics['revenue']['value'] = (float) $totals['total_revenue'];
            $metrics['profit']['value'] = $metrics['revenue']['value'] * 0.3;
        }
        
        $upsertMetric = $pdo->prepare(
            'INSERT INTO analytics_metrics (workspace_id, metric_key, metric_value, trend, sort_order)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE metric_value = VALUES(metric_value), trend = VALUES(trend)'
        );
        $sortMap = ['revenue' => 1, 'sales' => 2, 'orders' => 3, 'profit' => 4];
        foreach ($metrics as $key => $data) {
            $upsertMetric->execute([
                $workspaceId,
                $key,
                $data['value'],
                $data['trend'],
                $sortMap[$key]
            ]);
        }
        
        // Sync activity feed from recent orders
        $activityStmt = $pdo->prepare(
            'SELECT 
                order_code,
                customer_name,
                item,
                quantity,
                status,
                order_date,
                created_at
             FROM orders 
             WHERE workspace_id = ? 
             ORDER BY created_at DESC, id DESC 
             LIMIT 10'
        );
        $activityStmt->execute([$workspaceId]);
        $orders = $activityStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Clear existing activity for this workspace
        $pdo->prepare('DELETE FROM analytics_activity WHERE workspace_id = ?')
            ->execute([$workspaceId]);
        
        // Insert new activity records
        $insertActivity = $pdo->prepare(
            'INSERT INTO analytics_activity (workspace_id, title, code, status, activity_type, activity_date)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        
        $statusLabels = [
            'on_progress' => 'On Progress',
            'on_hold' => 'On Hold',
            'pending_payment' => 'Pending Payment',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled'
        ];
        
        foreach ($orders as $order) {
            $title = 'New order from ' . $order['customer_name'];
            if ($order['item']) {
                $title .= ' - ' . $order['item'];
            }
            
            $statusLabel = $statusLabels[$order['status']] ?? ucfirst($order['status']);
            
            $activityDate = $order['order_date'] ?: date('Y-m-d', strtotime($order['created_at']));
            $insertActivity->execute([
                $workspaceId,
                $title,
                $order['order_code'],
                $statusLabel,
                'order',
                $activityDate
            ]);
        }
        
    } catch (Exception $e) {
        error_log('Analytics sync error: ' . $e->getMessage());
    }
}

/**
 * Sync finance_transactions data to analytics tables for a workspace.
 * Updates analytics_metrics with revenue from successful income transactions.
 */
function syncFinanceToAnalytics(PDO $pdo, int $workspaceId): void
{
    ensureAnalyticsTables($pdo);
    
    try {
        $checkTable = $pdo->query("SHOW TABLES LIKE 'finance_transactions'")->fetch();
        if (!$checkTable) {
            return;
        }
        
        // Get total revenue from successful income transactions
        $revenueStmt = $pdo->prepare(
            'SELECT COALESCE(SUM(amount), 0) AS total_revenue
             FROM finance_transactions 
             WHERE workspace_id = ? AND type = "income" AND status = "success"'
        );
        $revenueStmt->execute([$workspaceId]);
        $revenue = (float) $revenueStmt->fetchColumn();
        
        // Get total expenses from successful expense transactions
        $expenseStmt = $pdo->prepare(
            'SELECT COALESCE(SUM(amount), 0) AS total_expenses
             FROM finance_transactions 
             WHERE workspace_id = ? AND type = "expense" AND status = "success"'
        );
        $expenseStmt->execute([$workspaceId]);
        $expenses = (float) $expenseStmt->fetchColumn();
        
        // Calculate profit
        $profit = $revenue - $expenses;
        
        // Update revenue and profit metrics (preserve orders/sales from syncOrdersToAnalytics)
        $upsertMetric = $pdo->prepare(
            'INSERT INTO analytics_metrics (workspace_id, metric_key, metric_value, trend, sort_order)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE metric_value = VALUES(metric_value)'
        );
        
        // Always update metrics to reflect current state (even if 0)
        $upsertMetric->execute([$workspaceId, 'revenue', $revenue, $revenue > 0 ? 'up' : 'flat', 1]);
        $upsertMetric->execute([$workspaceId, 'profit', $profit, $profit > 0 ? 'up' : 'flat', 4]);
        
        // Sync activity feed from recent finance transactions
        $txStmt = $pdo->prepare(
            'SELECT tx_code, title, type, amount, status, tx_date, created_at
             FROM finance_transactions 
             WHERE workspace_id = ? AND status = "success"
             ORDER BY created_at DESC, id DESC 
             LIMIT 10'
        );
        $txStmt->execute([$workspaceId]);
        $transactions = $txStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $insertActivity = $pdo->prepare(
            'INSERT INTO analytics_activity (workspace_id, title, code, status, activity_type, activity_date)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        
        foreach ($transactions as $tx) {
            $typeLabel = $tx['type'] === 'income' ? 'Income received' : 'Expense paid';
            $title = $typeLabel . ': ' . $tx['title'];
            $statusLabel = ucfirst($tx['status']);
            
            $activityDate = $tx['tx_date'] ?: date('Y-m-d', strtotime($tx['created_at']));
            $insertActivity->execute([
                $workspaceId,
                $title,
                $tx['tx_code'],
                $statusLabel,
                'finance',
                $activityDate
            ]);
        }
        
    } catch (Exception $e) {
        error_log('Finance sync error: ' . $e->getMessage());
    }
}

/**
 * Fetch all analytics datasets for a workspace.
 * Always syncs latest data from orders and finance_transactions.
 *
 * @return array{metrics:array,monthly:array,activity:array,reviews:array}
 */
function getAnalyticsData(PDO $pdo, int $workspaceId): array
{
    ensureAnalyticsTables($pdo);
    
    // Always sync to ensure fresh data from orders and finance
    try {
        syncOrdersToAnalytics($pdo, $workspaceId);
        syncFinanceToAnalytics($pdo, $workspaceId);
    } catch (Exception $e) {
        // Fail silently - analytics will show cached/empty state
        error_log('Analytics sync warning: ' . $e->getMessage());
    }

    $metricsStmt = $pdo->prepare(
        'SELECT metric_key, metric_value, change_percent, trend
         FROM analytics_metrics WHERE workspace_id = ?'
    );
    $metricsStmt->execute([$workspaceId]);
    $metrics = [];
    foreach ($metricsStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $metrics[$row['metric_key']] = $row;
    }

    $monthlyStmt = $pdo->prepare(
        'SELECT month_label, sales, revenue FROM analytics_monthly
         WHERE workspace_id = ? ORDER BY sort_order ASC, id ASC'
    );
    $monthlyStmt->execute([$workspaceId]);
    $monthly = $monthlyStmt->fetchAll(PDO::FETCH_ASSOC);

    $activityStmt = $pdo->prepare(
        'SELECT title, code, status, activity_type, activity_date, created_at FROM analytics_activity
         WHERE workspace_id = ? ORDER BY COALESCE(activity_date, created_at) DESC, id DESC LIMIT 6'
    );
    $activityStmt->execute([$workspaceId]);
    $activity = $activityStmt->fetchAll(PDO::FETCH_ASSOC);

    $reviewsStmt = $pdo->prepare(
        'SELECT reviewer_name, rating, comment, code, review_date FROM analytics_reviews
         WHERE workspace_id = ? ORDER BY review_date DESC, id DESC LIMIT 6'
    );
    $reviewsStmt->execute([$workspaceId]);
    $reviews = $reviewsStmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'metrics'  => $metrics,
        'monthly'  => $monthly,
        'activity' => $activity,
        'reviews'  => $reviews,
    ];
}

/**
 * Format a numeric value as a short Indonesian Rupiah string,
 * e.g. 2_170_000 -> "Rp 2,17 Jt", 3_400_000 -> "Rp 3,4 Jt".
 * Returns "Rp 0" for empty/zero values.
 */
function formatRupiahShort($value): string
{
    $value = (float) $value;
    if ($value <= 0) {
        return 'Rp 0';
    }

    $trim = static function (float $n): string {
        $s = number_format($n, 2, ',', '.');
        $s = rtrim($s, '0');
        return rtrim($s, ',');
    };

    if ($value >= 1000000000) {
        return 'Rp ' . $trim($value / 1000000000) . ' M';
    }
    if ($value >= 1000000) {
        return 'Rp ' . $trim($value / 1000000) . ' Jt';
    }
    if ($value >= 1000) {
        return 'Rp ' . $trim($value / 1000) . ' Rb';
    }
    return 'Rp ' . number_format($value, 0, ',', '.');
}

/**
 * Render the small "+X% from last month" change line for a metric.
 * Falls back to a muted dash when there is no change data.
 */
function renderMetricChange(?array $metric): string
{
    if (!$metric || $metric['change_percent'] === null) {
        return '<em class="analytics-stat-change is-empty">&mdash;</em>';
    }

    $trend = $metric['trend'] ?? 'up';
    $arrow = $trend === 'down' ? '&#8595;' : '&#8593;';
    $cls   = $trend === 'down' ? 'is-down' : 'is-up';

    $pct = number_format((float) $metric['change_percent'], 1, ',', '.');
    $pct = rtrim(rtrim($pct, '0'), ',');

    return '<em class="analytics-stat-change ' . $cls . '">'
        . $arrow . ' ' . htmlspecialchars($pct, ENT_QUOTES, 'UTF-8')
        . '% from last month</em>';
}
