<?php
// Handle workspace setup submission
require_once __DIR__ . '/../connect.php';
require_once __DIR__ . '/supabase-handler.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_URL . '/dashboard/index.php');
    exit;
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . APP_URL . '/index.php#signin');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$workspaceName = trim($_POST['workspace_name'] ?? '');
$industry = trim($_POST['industry'] ?? 'retail_ecommerce');
$companySize = trim($_POST['company_size'] ?? '1-10');

if (empty($workspaceName)) {
    $_SESSION['setup_error'] = 'Workspace name is required';
    header('Location: ' . APP_URL . '/dashboard/index.php');
    exit;
}

try {
    $pdo->beginTransaction();

    // Handle logo upload (Supabase Version)
    $logoPath = null;
    if (isset($_FILES['workspace_logo']) && $_FILES['workspace_logo']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png'];
        $fileType = $_FILES['workspace_logo']['type'];
        
        if (in_array($fileType, $allowedTypes)) {
            $extension = $fileType === 'image/jpeg' ? 'jpg' : 'png';
            $fileName = 'workspace_' . $userId . '_' . time() . '.' . $extension;
            
            // Eksekusi upload pakai Supabase Handler
            $uploadUrl = uploadToSupabase($_FILES['workspace_logo']['tmp_name'], $fileName, SUPABASE_BUCKET);
            
            if ($uploadUrl) {
                // Cukup simpan nama file-nya aja ke database
                $logoPath = $fileName;
            }
        }
    }

    // Create workspace (Pakai RETURNING id buat Postgres)
    $stmt = $pdo->prepare(
        'INSERT INTO workspaces (name, logo, industry, company_size, created_at, setup_completed_at) 
         VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP) RETURNING id'
    );
    $stmt->execute([$workspaceName, $logoPath, $industry, $companySize]);
    
    // Tangkap ID yang baru aja di-generate (Pengganti lastInsertId yang rawan error)
    $workspaceId = $stmt->fetchColumn();

    if (!$workspaceId) {
        throw new Exception("Gagal mendapatkan ID workspace baru.");
    }

    // Add user as workspace member (owner)
    $stmt = $pdo->prepare(
        'INSERT INTO workspace_members (workspace_id, user_id, role, joined_at) 
         VALUES (?, ?, ?, CURRENT_TIMESTAMP)'
    );
    $stmt->execute([$workspaceId, $userId, 'owner']);

    $pdo->commit();
    
    // Success - redirect to dashboard
    $_SESSION['setup_success'] = 'Workspace created successfully!';
    // Bawa parameter workspace_id biar dropdown-nya langsung milih workspace baru ini
    header('Location: ' . APP_URL . '/dashboard/index.php?workspace_id=' . $workspaceId);
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['setup_error'] = 'Failed to create workspace: ' . $e->getMessage();
    header('Location: ' . APP_URL . '/dashboard/index.php');
    exit;
}