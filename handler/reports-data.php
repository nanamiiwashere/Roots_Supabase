<?php
/**
 * Roots ERP - Business Reports Data Helper
 *
 * Aggregates real data from the other modules (finance_transactions, orders,
 * customers) into the datasets the Reports page visualises. Nothing is seeded;
 * every figure is derived from what the user has actually recorded. Each query
 * is defensive so a missing module table simply yields empty/zero data.
 */

/** Safely run a prepared query, returning [] on any failure (e.g. missing table). */
function reportsSafeFetchAll(PDO $pdo, string $sql, array $params = []): array
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) {
        return [];
    }
}

function reportsSafeScalar(PDO $pdo, string $sql, array $params = [], $default = 0)
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $val = $stmt->fetchColumn();
        return $val === false ? $default : $val;
    } catch (Exception $e) {
        return $default;
    }
}

function getReportRevenueProfit(PDO $pdo, int $workspaceId): array
{
    $labels  = [];
    $revenue = [];
    $profit  = [];

    for ($i = 3; $i >= 0; $i--) {
        $start = date('Y-m-d', strtotime("monday this week -$i week"));
        $end   = date('Y-m-d', strtotime("monday this week -$i week +6 day"));

        $inc = (float) reportsSafeScalar(
            $pdo,
            "SELECT COALESCE(SUM(amount),0) FROM finance_transactions
             WHERE workspace_id = ? AND type='income' AND status='success'
               AND CAST(COALESCE(tx_date, created_at) AS DATE) BETWEEN ? AND ?",
            [$workspaceId, $start, $end]
        );
        $exp = (float) reportsSafeScalar(
            $pdo,
            "SELECT COALESCE(SUM(amount),0) FROM finance_transactions
             WHERE workspace_id = ? AND type='expense' AND status='success'
               AND CAST(COALESCE(tx_date, created_at) AS DATE) BETWEEN ? AND ?",
            [$workspaceId, $start, $end]
        );

        $labels[]  = 'Week ' . (4 - $i);
        $revenue[] = $inc;
        $profit[]  = $inc - $exp;
    }

    return ['labels' => $labels, 'revenue' => $revenue, 'profit' => $profit];
}

function getReportSalesByProduct(PDO $pdo, int $workspaceId): array
{
    $rows = reportsSafeFetchAll(
        $pdo,
        "SELECT item, SUM(COALESCE(quantity,1)) AS qty
         FROM orders
         WHERE workspace_id = ? AND item IS NOT NULL AND item <> '' AND status <> 'cancelled'
         GROUP BY item
         ORDER BY qty DESC
         LIMIT 4",
        [$workspaceId]
    );

    $palette = ['#2bb673', '#f5b301', '#b5651d', '#8b95a1'];
    $labels = [];
    $values = [];
    $colors = [];
    $i = 0;
    foreach ($rows as $r) {
        $labels[] = $r['item'];
        $values[] = (int) $r['qty'];
        $colors[] = $palette[$i % count($palette)];
        $i++;
    }

    return ['labels' => $labels, 'values' => $values, 'colors' => $colors];
}

function getReportMonthlyTargets(PDO $pdo, int $workspaceId): array
{
    // GANTI: DATE_FORMAT -> TO_CHAR untuk Postgres
    $rows = reportsSafeFetchAll(
        $pdo,
        "SELECT TO_CHAR(COALESCE(tx_date, created_at), 'YYYY-MM') AS ym, COALESCE(SUM(amount),0) AS total
         FROM finance_transactions
         WHERE workspace_id = ? AND type='income' AND status='success'
         GROUP BY ym",
        [$workspaceId]
    );
    $byMonth = [];
    foreach ($rows as $r) {
        $byMonth[$r['ym']] = (float) $r['total'];
    }

    $labels = [];
    $actual = [];
    for ($i = 5; $i >= 0; $i--) {
        $ts = strtotime("first day of -$i month");
        $labels[] = date('M', $ts);
        $actual[] = $byMonth[date('Y-m', $ts)] ?? 0;
    }

    $targetValue = (float) reportsSafeScalar(
        $pdo, 
        "SELECT monthly_target FROM workspaces WHERE id = ?", 
        [$workspaceId], 
        0
    );

    if ($targetValue <= 0) {
        $nonZero = array_filter($actual, static fn($v) => $v > 0);
        $targetValue = $nonZero ? array_sum($nonZero) / count($nonZero) : 0;
    }

    $target = array_fill(0, count($actual), round($targetValue, 2));

    return [
        'labels' => $labels, 
        'actual' => $actual, 
        'target' => $target,
        'currentTarget' => $targetValue
    ];
}

function getReportKpis(PDO $pdo, int $workspaceId): array
{
    // GANTI: INTERVAL 3 MONTH -> INTERVAL '3 months' untuk Postgres
    $recent = (float) reportsSafeScalar(
        $pdo,
        "SELECT COALESCE(SUM(CASE WHEN type='income' THEN amount ELSE -amount END),0)
         FROM finance_transactions
         WHERE workspace_id = ? AND status='success'
           AND COALESCE(tx_date, created_at) >= (CURRENT_DATE - INTERVAL '3 months')",
        [$workspaceId]
    );
    $prior = (float) reportsSafeScalar(
        $pdo,
        "SELECT COALESCE(SUM(CASE WHEN type='income' THEN amount ELSE -amount END),0)
         FROM finance_transactions
         WHERE workspace_id = ? AND status='success'
           AND COALESCE(tx_date, created_at) >= (CURRENT_DATE - INTERVAL '6 months')
           AND COALESCE(tx_date, created_at) <  (CURRENT_DATE - INTERVAL '3 months')",
        [$workspaceId]
    );
    
    if (abs($prior) > 0.0001) {
        $growth = (($recent - $prior) / abs($prior)) * 100;
        $growthText = ($growth >= 0 ? '+' : '') . rtrim(rtrim(number_format($growth, 1, ',', '.'), '0'), ',') . '%';
        $growthTrend = $growth >= 0 ? 'up' : 'down';
    } else {
        $growthText = '—';
        $growthTrend = 'up';
    }

    // --- Operating expenses (all-time success expenses). ---
    $expenses = (float) reportsSafeScalar(
        $pdo,
        "SELECT COALESCE(SUM(amount),0) FROM finance_transactions
         WHERE workspace_id = ? AND type='expense' AND status='success'",
        [$workspaceId]
    );

    // --- Repeat order rate: customers with more than one order / customers who ordered. ---
    $orderCustomers = (int) reportsSafeScalar(
        $pdo,
        "SELECT COUNT(*) FROM (
            SELECT customer_name FROM orders
            WHERE workspace_id = ? AND customer_name IS NOT NULL AND customer_name <> ''
            GROUP BY customer_name
         ) t",
        [$workspaceId]
    );
    $repeatOrderCustomers = (int) reportsSafeScalar(
        $pdo,
        "SELECT COUNT(*) FROM (
            SELECT customer_name FROM orders
            WHERE workspace_id = ? AND customer_name IS NOT NULL AND customer_name <> ''
            GROUP BY customer_name
            HAVING COUNT(*) > 1
         ) t",
        [$workspaceId]
    );

    if ($orderCustomers > 0) {
        $rate = ($repeatOrderCustomers / $orderCustomers) * 100;
        $rateText = rtrim(rtrim(number_format($rate, 1, ',', '.'), '0'), ',') . '%';
        $rateSub = $repeatOrderCustomers . ' of ' . $orderCustomers . ' customers reordered';
    } else {
        // Fall back to the customer "regular" segment when there are no orders yet.
        $totalCustomers = (int) reportsSafeScalar($pdo, 'SELECT COUNT(*) FROM customers WHERE workspace_id = ?', [$workspaceId]);
        $repeatCustomers = (int) reportsSafeScalar($pdo, "SELECT COUNT(*) FROM customers WHERE workspace_id = ? AND segment='regular'", [$workspaceId]);
        if ($totalCustomers > 0) {
            $rate = ($repeatCustomers / $totalCustomers) * 100;
            $rateText = rtrim(rtrim(number_format($rate, 1, ',', '.'), '0'), ',') . '%';
            $rateSub = 'Regular customers share';
        } else {
            $rateText = '—';
            $rateSub = 'No customer data yet';
        }
    }

    return [
        [
            'key'      => 'net_growth',
            'label'    => 'Annual Net Growth',
            'value'    => $growthText,
            'subtitle' => $growthTrend === 'down' ? 'Net trend is down' : 'Stable growth trend',
            'trend'    => $growthTrend,
        ],
        [
            'key'      => 'operating_expenses',
            'label'    => 'Operating Expenses',
            'value'    => formatReportRupiahShort($expenses),
            'subtitle' => 'Total recorded expenses',
            'trend'    => 'down',
        ],
        [
            'key'      => 'repeat_order',
            'label'    => 'Repeat Order Rate',
            'value'    => $rateText,
            'subtitle' => $rateSub,
            'trend'    => 'up',
        ],
    ];
}

/** Fetch every reports dataset for a workspace. */
function getReportData(PDO $pdo, int $workspaceId): array
{
    return [
        'revenueProfit' => getReportRevenueProfit($pdo, $workspaceId),
        'salesProduct'  => getReportSalesByProduct($pdo, $workspaceId),
        'monthly'       => getReportMonthlyTargets($pdo, $workspaceId),
        'kpis'          => getReportKpis($pdo, $workspaceId),
    ];
}

/** Whether a numeric series has any non-zero value. */
function reportsHasData(array $values): bool
{
    foreach ($values as $v) {
        if (abs((float) $v) > 0.0001) {
            return true;
        }
    }
    return false;
}

/** Short Rupiah, e.g. 3_000_000 -> "Rp 3.0 Jt". */
function formatReportRupiahShort($value): string
{
    $value = (float) $value;
    if ($value <= 0) {
        return 'Rp 0';
    }
    if ($value >= 1000000000) {
        return 'Rp ' . rtrim(rtrim(number_format($value / 1000000000, 1, ',', '.'), '0'), ',') . ' M';
    }
    if ($value >= 1000000) {
        return 'Rp ' . rtrim(rtrim(number_format($value / 1000000, 1, ',', '.'), '0'), ',') . ' Jt';
    }
    if ($value >= 1000) {
        return 'Rp ' . rtrim(rtrim(number_format($value / 1000, 1, ',', '.'), '0'), ',') . ' Rb';
    }
    return 'Rp ' . number_format($value, 0, ',', '.');
}

/** KPI icon paths (inline SVG body per metric). */
function reportKpiIcon(string $key): string
{
    switch ($key) {
        case 'net_growth':
            return '<polyline points="3 17 9 11 13 15 21 7"/><polyline points="14 7 21 7 21 14"/>';
        case 'operating_expenses':
            return '<polyline points="21 17 15 11 11 15 3 7"/><polyline points="10 17 3 17 3 10"/>';
        case 'repeat_order':
        default:
            return '<polyline points="3 15 8 10 12 13 16 8 21 11"/><polyline points="16 8 21 8 21 13"/>';
    }
}

function updateWorkspaceTarget(PDO $pdo, int $workspaceId, float $targetAmount): void
{
    $stmt = $pdo->prepare('UPDATE workspaces SET monthly_target = ? WHERE id = ?');
    $stmt->execute([max(0, $targetAmount), $workspaceId]);
}

/**
 * Generate AI-powered business summary from KPI data using BlueMinds API (Gemini 3 Flash Preview).
 *
 * @param array $kpis KPI data from getReportKpis()
 * @param array $revenueProfit Revenue vs Profit data
 * @param string $workspaceName Workspace name for context
 * @return string AI-generated summary text, or empty string on failure
 */
function getAIBusinessSummary(array $kpis, array $revenueProfit, string $workspaceName): string
{
    // Get API key from environment variable or config
    $apiKey = getenv('BLUEMINDS_API_KEY');
    if (!$apiKey) {
        // Fallback: check for a config file
        $configPath = __DIR__ . '/../auth/.env';
        if (file_exists($configPath)) {
            $lines = file($configPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if (strpos($line, 'BLUEMINDS_API_KEY') === 0) {
                    $parts = explode('=', $line, 2);
                    if (isset($parts[1])) {
                        $apiKey = trim($parts[1]);
                        // Remove quotes if present
                        $apiKey = trim($apiKey, '"\'');
                        break;
                    }
                }
            }
        }
    }

    if (!$apiKey) {
        return ''; // No API key configured
    }

    // Build the prompt from KPI data
    $netGrowth = $kpis[0]['value'] ?? '—';
    $netGrowthSub = $kpis[0]['subtitle'] ?? '';
    $expenses = $kpis[1]['value'] ?? 'Rp 0';
    $expensesSub = $kpis[1]['subtitle'] ?? '';
    $repeatOrder = $kpis[2]['value'] ?? '—';
    $repeatOrderSub = $kpis[2]['subtitle'] ?? '';

    // Get latest revenue/profit figures
    $latestRevenue = !empty($revenueProfit['revenue']) ? end($revenueProfit['revenue']) : 0;
    $latestProfit = !empty($revenueProfit['profit']) ? end($revenueProfit['profit']) : 0;
    $latestWeek = !empty($revenueProfit['labels']) ? end($revenueProfit['labels']) : 'latest week';

    $systemPrompt = "You are a professional business analyst.";

    $userPrompt = "Analyze the following business metrics for '{$workspaceName}':
- Annual Net Growth: {$netGrowth} ({$netGrowthSub})
- Operating Expenses: {$expenses} ({$expensesSub})
- Repeat Order Rate: {$repeatOrder} ({$repeatOrderSub})
- Latest Week Revenue: Rp " . number_format($latestRevenue, 0, ',', '.') . "
- Latest Week Profit: Rp " . number_format($latestProfit, 0, ',', '.') . "

Task: Provide a business analysis focusing on overall health, key strengths/concerns, and one actionable recommendation. Note that dashes (—) mean historical data is still being collected.

CRITICAL INSTRUCTIONS:
1. Make it EXACTLY 4 short sentences.
2. Output MUST be in ENGLISH.
3. You MUST wrap your final 4-sentence summary strictly inside <FINAL_SUMMARY> and </FINAL_SUMMARY> tags. Do not include your thinking process inside these tags.";

    // BlueMinds API endpoint (OpenAI-compatible format)
    $apiUrl = 'https://api.bluesminds.com/v1/chat/completions';

    $payload = json_encode([
        'model' => 'accounts/fireworks/models/deepseek-v4-pro',
        'messages' => [
            [
                'role' => 'system',
                'content' => $systemPrompt
            ],
            [
                'role' => 'user',
                'content' => $userPrompt
            ]
        ],
        'temperature' => 0.1,
        'max_tokens' => 1500,
    ]);

    try {
        error_log('AI Summary: Calling BlueMinds API with model deepseek-v4-pro');
        error_log('AI Summary: API Key length = ' . strlen($apiKey));
        error_log('AI Summary: Payload = ' . $payload);

        // Use cURL for the API call (more reliable than file_get_contents)
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $apiUrl,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
        ]);

        $response = curl_exec($ch);
        $httpStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);

        curl_close($ch);

        error_log('AI Summary: HTTP Status = ' . $httpStatus);
        error_log('AI Summary: Response body = ' . substr($response, 0, 1000));

        if ($curlError) {
            error_log('AI Summary: cURL error (' . $curlErrno . ') - ' . $curlError);
            return '';
        }

        if ($response === false || $response === null) {
            error_log('AI Summary: cURL returned false/null');
            return '';
        }

        if ($httpStatus >= 400) {
            error_log('AI Summary: HTTP error ' . $httpStatus . ' - Response: ' . $response);
            return '';
        }

        error_log('AI Summary: API response received, length = ' . strlen($response));

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('AI Summary: JSON decode error - ' . json_last_error_msg());
            error_log('AI Summary: Raw response: ' . substr($response, 0, 500));
            return '';
        }

        if (!isset($data['choices'][0]['message']['content'])) {
            error_log('AI Summary: Unexpected response structure');
            error_log('AI Summary: Response keys: ' . print_r(array_keys($data), true));
            if (isset($data['error'])) {
                error_log('AI Summary: API error - ' . print_r($data['error'], true));
            }
            return '';
        }

  
        $rawOutput = $data['choices'][0]['message']['content'];

        if (preg_match('/<FINAL_SUMMARY>(.*?)<\/FINAL_SUMMARY>/is', $rawOutput, $matches)) {
            $cleanOutput = $matches[1];
        } else {
            // Fallback Ekstrem: Kalau dia bandel ga pake tag, 
            // kita potong sadis dan ambil 1 paragraf paling BAWAH aja.
            $paragraphs = array_filter(explode("\n", str_replace("\r", "", $rawOutput)));
            $cleanOutput = end($paragraphs); 
        }

        // Sapu bersih sisa-sisa kotoran kalau ada
        $cleanOutput = preg_replace('/<think>.*?<\/think>/is', '', $cleanOutput);
        $cleanOutput = trim($cleanOutput);

        error_log('AI Summary: Success - generated 4-line summary');
        return $cleanOutput;

    } catch (Exception $e) {
        error_log('AI Summary Error: ' . $e->getMessage());
        return '';
    }
}
