<?php
/**
 * Roots ERP - AI Business Summary AJAX Endpoint
 * Returns AI-generated business summary as JSON.
 */
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');

require_once __DIR__ . '/../connect.php';
require_once __DIR__ . '/../auth/auth-handler.php';
require_once __DIR__ . '/reports-data.php';

// Require authentication
requireLogin();
$currentUser = currentUser();
if (!$currentUser) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Pro/Scale membership check - AI Summary is a Pro & Scale feature
if (!in_array($currentUser['membership'], ['pro', 'scale', 'admin'])) {
    echo json_encode(['error' => 'Pro or Scale membership required', 'requires_pro' => true]);
    exit;
}

// Get workspace ID
$workspaceId = isset($_GET['workspace_id']) ? (int) $_GET['workspace_id'] : ($_SESSION['active_workspace_id'] ?? 0);
if (!$workspaceId) {
    echo json_encode(['error' => 'No workspace']);
    exit;
}

// Verify workspace access
try {
    $stmt = $pdo->prepare(
        'SELECT w.* FROM workspaces w
         INNER JOIN workspace_members wm ON wm.workspace_id = w.id
         WHERE wm.user_id = ? AND w.id = ? AND w.setup_completed_at IS NOT NULL
         LIMIT 1'
    );
    $stmt->execute([$currentUser['id_user'], $workspaceId]);
    $workspace = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$workspace) {
        echo json_encode(['error' => 'Workspace not found']);
        exit;
    }
} catch (Exception $e) {
    echo json_encode(['error' => 'Database error']);
    exit;
}

$workspaceName = $workspace['name'] ?? 'My Workspace';

// Get report data
try {
    $report = getReportData($pdo, $workspaceId);
} catch (Exception $e) {
    echo json_encode(['error' => 'Failed to load report data']);
    exit;
}

// Generate AI summary
try {
    $summary = getAIBusinessSummary($report['kpis'], $report['revenueProfit'], $workspaceName);
    if ($summary) {
        echo json_encode(['success' => true, 'summary' => $summary]);
    } else {
        echo json_encode(['success' => false, 'summary' => '']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'summary' => '', 'error' => $e->getMessage()]);
}