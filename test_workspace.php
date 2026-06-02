<?php
// Test file untuk check workspace setup
// Akses via: http://localhost/roots/test_workspace.php

require_once __DIR__ . '/auth.php';

$currentUser = roots_current_user();

if (!$currentUser) {
    die('Please login first');
}

echo "<h2>Current User</h2>";
echo "<pre>";
print_r($currentUser);
echo "</pre>";

try {
    $pdo = roots_pdo();
    
    // Check workspaces
    echo "<h2>User Workspaces</h2>";
    $stmt = $pdo->prepare(
        'SELECT w.*, wm.role, wm.joined_at 
         FROM workspaces w
         INNER JOIN workspace_members wm ON wm.workspace_id = w.id
         WHERE wm.user_id = ?
         ORDER BY w.setup_completed_at DESC'
    );
    $stmt->execute([$currentUser['id_user']]);
    $workspaces = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($workspaces)) {
        echo "<p>No workspaces found. User needs onboarding.</p>";
    } else {
        echo "<pre>";
        print_r($workspaces);
        echo "</pre>";
    }
    
    // Check tables exist
    echo "<h2>Database Tables</h2>";
    $tables = $pdo->query("SHOW TABLES LIKE 'workspace%'")->fetchAll(PDO::FETCH_COLUMN);
    echo "<pre>";
    print_r($tables);
    echo "</pre>";
    
} catch (Exception $e) {
    echo "<h2>Error</h2>";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>";
    echo "<p>Make sure to run database_workspace.sql first!</p>";
}
?>
