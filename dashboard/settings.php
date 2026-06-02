<?php
/**
 * Roots ERP - Settings Page
 * 
 * User and workspace settings management with tab navigation.
 * All settings are editable with form inputs.
 */

// Prevent caching for security
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/../connect.php';
require_once __DIR__ . '/../auth/auth-handler.php';
require_once __DIR__ . '/../auth/auth3thparty.php';
require_once __DIR__ . '/../handler/supabase-handler.php';
require_once __DIR__ . '/../handler/supabase-img.php';

// Get current user or redirect to login
requireLogin();
$currentUser = currentUser();

if (!$currentUser) {
    header('Location: ../login.php');
    exit;
}

// Handle settings update
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'update_profile':
                $name = trim($_POST['name'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                
                if (empty($name)) {
                    throw new Exception('Name is required');
                }
                
                $stmt = $pdo->prepare('UPDATE users SET name = ?, phone = ? WHERE id_user = ?');
                $stmt->execute([
                    $name, 
                    (!empty($phone) ? $phone : null), 
                    (int)$currentUser['id_user']
                ]);
                            
                // Update session so name change is reflected immediately
                $_SESSION['user_name'] = $name;
                
                // Handle avatar upload
                if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                    $fileExt = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
                    $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp']; // Tambah webp sesuai array lu
                    
                    if (in_array($fileExt, $allowedExt)) {
                        $fileName = 'avatar_' . $currentUser['id_user'] . '_' . time() . '.' . $fileExt;
                        
                        // Eksekusi upload pakai Supabase Handler
                        $uploadUrl = uploadToSupabase($_FILES['avatar']['tmp_name'], $fileName, SUPABASE_BUCKET);
                        
                        if ($uploadUrl) {
                            // Simpan nama file-nya aja ke database
                            $stmt = $pdo->prepare('UPDATE users SET avatar = ? WHERE id_user = ?');
                            $stmt->execute([$fileName, $currentUser['id_user']]);
                            
                            // Update session
                            $_SESSION['user_avatar'] = $fileName;
                        }
                    }
                }
                                        
                $_SESSION['settings_success'] = 'Profile updated successfully!';
                $_SESSION['force_user_refresh'] = true;
                header('Location: settings.php');
                exit;
                
            case 'update_workspace':
                $workspaceName = trim($_POST['workspace_name'] ?? '');
                $industry = $_POST['industry'] ?? '';
                $companySize = $_POST['company_size'] ?? '';
                
                if (empty($workspaceName)) {
                    throw new Exception('Workspace name is required');
                }
                
                $activeWorkspaceId = $_SESSION['active_workspace_id'] ?? null;
                if ($activeWorkspaceId) {
                    $stmt = $pdo->prepare('UPDATE workspaces SET name = ?, industry = ?, company_size = ? WHERE id = ?');
                    $stmt->execute([$workspaceName, $industry, $companySize, $activeWorkspaceId]);
                    $_SESSION['settings_success'] = 'Workspace settings updated successfully!';
                    header('Location: settings.php');
                    exit;
                }
                break;
                
            case 'update_preferences':
                $language = $_POST['language'] ?? 'en_US';
                $timezone = $_POST['timezone'] ?? 'Asia/Jakarta';
                $currency = $_POST['currency'] ?? 'IDR';
                
                // Store in user preferences (using session for now, can be stored in DB)
                $_SESSION['user_preferences'] = [
                    'language' => $language,
                    'timezone' => $timezone,
                    'currency' => $currency
                ];
                
                $_SESSION['settings_success'] = 'Preferences updated successfully!';
                header('Location: settings.php');
                exit;
                
            case 'update_password':
                $currentPassword = $_POST['current_password'] ?? '';
                $newPassword = $_POST['new_password'] ?? '';
                $confirmPassword = $_POST['confirm_password'] ?? '';
                
                if (empty($currentPassword) || empty($newPassword)) {
                    throw new Exception('All password fields are required');
                }
                
                if ($newPassword !== $confirmPassword) {
                    throw new Exception('New passwords do not match');
                }
                
                if (strlen($newPassword) < 8) {
                    throw new Exception('Password must be at least 8 characters');
                }
                
                // Fetch actual password hash from database (session doesn't store it)
                $passwordStmt = $pdo->prepare('SELECT password FROM users WHERE id_user = ?');
                $passwordStmt->execute([$currentUser['id_user']]);
                $userPasswordData = $passwordStmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$userPasswordData || !password_verify($currentPassword, $userPasswordData['password'])) {
                    throw new Exception('Current password is incorrect');
                }
                
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id_user = ?');
                $stmt->execute([$hashedPassword, $currentUser['id_user']]);
                
                $_SESSION['settings_success'] = 'Password updated successfully!';
                header('Location: settings.php');
                exit;
        }
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
    }
    
    // Refresh user data
    $currentUser = currentUser();
}

// Get active workspace (optimized: single query, sidebar.php handles the full workspace list)
$workspace = null;
$activeWorkspaceId = $_SESSION['active_workspace_id'] ?? null;

try {
    if ($activeWorkspaceId) {
        $stmt = $pdo->prepare(
            'SELECT w.* FROM workspaces w
             INNER JOIN workspace_members wm ON wm.workspace_id = w.id
             WHERE wm.user_id = ? AND w.id = ? AND w.setup_completed_at IS NOT NULL
             LIMIT 1'
        );
        $stmt->execute([$currentUser['id_user'], $activeWorkspaceId]);
        $workspace = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    
    if (!$workspace) {
        // Fallback: get most recent workspace and cache it in session
        $stmt = $pdo->prepare(
            'SELECT w.* FROM workspaces w
             INNER JOIN workspace_members wm ON wm.workspace_id = w.id
             WHERE wm.user_id = ? AND w.setup_completed_at IS NOT NULL
             ORDER BY w.setup_completed_at DESC
             LIMIT 1'
        );
        $stmt->execute([$currentUser['id_user']]);
        $workspace = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        
        if ($workspace) {
            $_SESSION['active_workspace_id'] = $workspace['id'];
        }
    }
} catch (Exception $e) {
    // Handle error silently - workspace will be null
}

// Get user preferences
$userPrefs = $_SESSION['user_preferences'] ?? [
    'language' => 'en_US',
    'timezone' => 'Asia/Jakarta',
    'currency' => 'IDR'
];

$displayName = $currentUser['name'] ?: 'User';
$workspaceName = $workspace['name'] ?? 'My Workspace';

// Handle avatar path - ensure correct directory
if (!empty($currentUser['avatar'])) {
    $avatar = supabaseImageUrl($currentUser['avatar']);
} else {
    $avatar = '../img/roots_symbol.png';
}

$currentPage = 'settings';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Roots ERP</title>
    <meta name="description" content="Settings page for Roots ERP dashboard.">
    <link rel="icon" href="../img/roots_symbol.png?v=<?= filemtime(__DIR__ . '/../img/roots_symbol.png'); ?>" type="image/png">
    <link rel="stylesheet" href="../dashboard/partials/style.css?v=<?= filemtime(__DIR__ . '/partials/style.css'); ?>">
</head>
<body>
    
    <!-- Main Dashboard -->
    <main class="dashboard-shell" aria-label="Roots dashboard">
        
        <!-- Sidebar (Partial) -->
        <?php 
        include __DIR__ . '/partials/sidebar.php'; 
        ?>

        <!-- Main Content Area -->
        <section class="dashboard-main">
            
            <!-- Top Bar -->
            <header class="dashboard-topbar">
                <button class="dashboard-mobile-menu-btn" type="button" aria-label="Open menu" id="mobileMenuBtn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                </button>
                <div class="dashboard-breadcrumb">
                    <span>Dashboard</span>
                    <i></i>
                    <strong>Settings</strong>
                </div>
                <label class="dashboard-search">
                    <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="search" placeholder="Search settings...">
                </label>
                <div class="dashboard-actions">
                    <div class="topbar-notif">
                        <button type="button" aria-label="Notifications" class="action-btn" id="notifBtn">
                            <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                            <span class="notif-dot"></span>
                        </button>
                        <!-- Notification Popup -->
                        <div class="notif-popup" id="notifPopup">
                            <div class="notif-popup-header">
                                <strong>Notifications</strong>
                                <button type="button" class="notif-close" id="notifClose">&times;</button>
                            </div>
                            <div class="notif-popup-body">
                                <p class="notif-empty">No new notifications</p>
                            </div>
                        </div>
                    </div>
                    <!-- Account Menu -->
                    <div class="topbar-user">
                        <button type="button" class="topbar-avatar-btn" id="topbarAvatar" aria-label="Account menu" aria-haspopup="true" aria-expanded="false">
                            <img src="<?= htmlspecialchars($avatar, ENT_QUOTES, 'UTF-8'); ?>" alt="User avatar" class="topbar-avatar">
                            <svg class="topbar-avatar-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                        </button>
                        <div class="topbar-dropdown" id="topbarDropdown">
                            <div class="topbar-dropdown-header">
                                <img src="<?= htmlspecialchars($avatar, ENT_QUOTES, 'UTF-8'); ?>" alt="" class="topbar-dropdown-avatar">
                                <div class="topbar-dropdown-info">
                                    <strong><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></strong>
                                    <span><?= htmlspecialchars($currentUser['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                            </div>
                            <a href="settings.php" class="topbar-dropdown-item">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                                <span>Settings</span>
                            </a>
                            <a href="../auth/logout.php" class="topbar-dropdown-item topbar-dropdown-logout">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                                <span>Log Out</span>
                            </a>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Settings Content -->
            <div class="dashboard-content">
                <div class="settings-header">
                    <h1>Settings</h1>
                    <p class="settings-subtitle">Manage your account settings and preferences.</p>
                </div>

                <!-- Messages -->
                <?php if (isset($_SESSION['settings_success']) && $_SESSION['settings_success']): ?>
                    <div class="settings-alert settings-alert-success">
                        <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        <span><?= htmlspecialchars($_SESSION['settings_success']); ?></span>
                    </div>
                    <?php unset($_SESSION['settings_success']); ?>
                <?php endif; ?>
                <?php if ($errorMessage): ?>
                    <div class="settings-alert settings-alert-error">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                        <span><?= htmlspecialchars($errorMessage); ?></span>
                    </div>
                <?php endif; ?>

                <!-- Settings Tabs -->
                <div class="settings-tabs">
                    <button class="settings-tab active" data-tab="profile">
                        <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        My Profile
                    </button>
                    <button class="settings-tab" data-tab="workspace">
                        <svg viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
                        Workspace
                    </button>
                    <button class="settings-tab" data-tab="preferences">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                        Preferences
                    </button>
                    <button class="settings-tab" data-tab="security">
                        <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        Security
                    </button>
                    <button class="settings-tab" data-tab="billing">
                        <svg viewBox="0 0 24 24"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                        Billing
                    </button>
                </div>
                
                <!-- Tab Content -->
                <div class="settings-tab-content">
                    
                    <!-- My Profile Tab -->
                    <div class="tab-panel active" id="tab-profile">
                        <form class="settings-form" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="update_profile">
                            
                            <article class="dashboard-card settings-card">
                                <div class="settings-card-header">
                                    <h2>Profile Information</h2>
                                    <p>Update your personal details and profile photo.</p>
                                </div>
                                
                                <div class="settings-form-group">
                                    <div class="settings-avatar-upload">
                                        <img src="<?= htmlspecialchars($avatar, ENT_QUOTES, 'UTF-8'); ?>" alt="Avatar" class="settings-avatar-preview" id="avatarPreview">
                                        <div class="settings-avatar-actions">
                                            <label class="settings-btn settings-btn-outline" for="avatarInput">
                                                <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                                Upload Photo
                                            </label>
                                            <input type="file" id="avatarInput" name="avatar" accept="image/*" hidden>
                                            <span class="settings-avatar-hint">JPG, PNG or GIF. Max 2MB.</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="settings-form-row">
                                    <div class="settings-form-group">
                                        <label class="settings-label" for="profileName">Full Name</label>
                                        <input type="text" id="profileName" name="name" class="settings-input" value="<?= htmlspecialchars($currentUser['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                                    </div>
                                    <div class="settings-form-group">
                                        <label class="settings-label" for="profileEmail">Email Address</label>
                                        <input type="email" id="profileEmail" class="settings-input" value="<?= htmlspecialchars($currentUser['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" disabled>
                                        <span class="settings-hint">Email cannot be changed for security reasons.</span>
                                    </div>
                                </div>
                                
                                <div class="settings-form-group">
                                    <label class="settings-label" for="profilePhone">Phone Number</label>
                                    <input type="tel" id="profilePhone" name="phone" class="settings-input" value="<?= htmlspecialchars($currentUser['phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="+62 812 3456 7890">
                                </div>
                                
                                <div class="settings-form-actions">
                                    <button type="submit" class="settings-btn settings-btn-primary">
                                        <svg viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                                        Save Changes
                                    </button>
                                </div>
                            </article>
                        </form>
                    </div>

                    <!-- Workspace Tab -->
                    <div class="tab-panel" id="tab-workspace">
                        <form class="settings-form" method="POST">
                            <input type="hidden" name="action" value="update_workspace">
                            
                            <article class="dashboard-card settings-card">
                                <div class="settings-card-header">
                                    <h2>Workspace Settings</h2>
                                    <p>Configure your workspace details and preferences.</p>
                                </div>
                                
                                <div class="settings-form-group">
                                    <label class="settings-label" for="workspaceName">Workspace Name</label>
                                    <input type="text" id="workspaceName" name="workspace_name" class="settings-input" value="<?= htmlspecialchars($workspace['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                                </div>
                                
                                <div class="settings-form-row">
                                    <div class="settings-form-group">
                                        <label class="settings-label" for="workspaceIndustry">Industry</label>
                                        <select id="workspaceIndustry" name="industry" class="settings-select-input">
                                            <option value="retail_ecommerce" <?= ($workspace['industry'] ?? '') === 'retail_ecommerce' ? 'selected' : ''; ?>>Retail / E-Commerce</option>
                                            <option value="service_business" <?= ($workspace['industry'] ?? '') === 'service_business' ? 'selected' : ''; ?>>Service Business</option>
                                            <option value="manufacturing" <?= ($workspace['industry'] ?? '') === 'manufacturing' ? 'selected' : ''; ?>>Manufacturing</option>
                                            <option value="finance_accounting" <?= ($workspace['industry'] ?? '') === 'finance_accounting' ? 'selected' : ''; ?>>Finance & Accounting</option>
                                            <option value="technology" <?= ($workspace['industry'] ?? '') === 'technology' ? 'selected' : ''; ?>>Technology</option>
                                            <option value="healthcare" <?= ($workspace['industry'] ?? '') === 'healthcare' ? 'selected' : ''; ?>>Healthcare</option>
                                            <option value="education" <?= ($workspace['industry'] ?? '') === 'education' ? 'selected' : ''; ?>>Education</option>
                                            <option value="other" <?= ($workspace['industry'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                                        </select>
                                    </div>
                                    <div class="settings-form-group">
                                        <label class="settings-label" for="workspaceSize">Company Size</label>
                                        <select id="workspaceSize" name="company_size" class="settings-select-input">
                                            <option value="1-10" <?= ($workspace['company_size'] ?? '') === '1-10' ? 'selected' : ''; ?>>1 - 10 People</option>
                                            <option value="11-50" <?= ($workspace['company_size'] ?? '') === '11-50' ? 'selected' : ''; ?>>11 - 50 People</option>
                                            <option value="51-100" <?= ($workspace['company_size'] ?? '') === '51-100' ? 'selected' : ''; ?>>51 - 100 People</option>
                                            <option value="201+" <?= ($workspace['company_size'] ?? '') === '201+' ? 'selected' : ''; ?>>201+ People</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="settings-form-actions">
                                    <button type="submit" class="settings-btn settings-btn-primary">
                                        <svg viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                                        Save Changes
                                    </button>
                                </div>
                            </article>
                        </form>
                    </div>

                    <!-- Preferences Tab -->
                    <div class="tab-panel" id="tab-preferences">
                        <form class="settings-form" method="POST">
                            <input type="hidden" name="action" value="update_preferences">
                            
                            <article class="dashboard-card settings-card">
                                <div class="settings-card-header">
                                    <h2>Preferences</h2>
                                    <p>Customize your dashboard experience and regional settings.</p>
                                </div>
                                
                                <div class="settings-form-group">
                                    <label class="settings-label" for="prefLanguage">Language</label>
                                    <select id="prefLanguage" name="language" class="settings-select-input">
                                        <option value="en_US" <?= $userPrefs['language'] === 'en_US' ? 'selected' : ''; ?>>English (US)</option>
                                        <option value="id_ID" <?= $userPrefs['language'] === 'id_ID' ? 'selected' : ''; ?>>Bahasa Indonesia</option>
                                    </select>
                                </div>
                                
                                <div class="settings-form-row">
                                    <div class="settings-form-group">
                                        <label class="settings-label" for="prefTimezone">Timezone</label>
                                        <select id="prefTimezone" name="timezone" class="settings-select-input">
                                            <option value="Asia/Jakarta" <?= $userPrefs['timezone'] === 'Asia/Jakarta' ? 'selected' : ''; ?>>GMT+07:00 Jakarta</option>
                                            <option value="Asia/Singapore" <?= $userPrefs['timezone'] === 'Asia/Singapore' ? 'selected' : ''; ?>>GMT+08:00 Singapore</option>
                                            <option value="Asia/Tokyo" <?= $userPrefs['timezone'] === 'Asia/Tokyo' ? 'selected' : ''; ?>>GMT+09:00 Tokyo</option>
                                            <option value="UTC" <?= $userPrefs['timezone'] === 'UTC' ? 'selected' : ''; ?>>UTC</option>
                                        </select>
                                    </div>
                                    <div class="settings-form-group">
                                        <label class="settings-label" for="prefCurrency">Currency</label>
                                        <select id="prefCurrency" name="currency" class="settings-select-input">
                                            <option value="IDR" <?= $userPrefs['currency'] === 'IDR' ? 'selected' : ''; ?>>IDR (Rp) - Indonesian Rupiah</option>
                                            <option value="USD" <?= $userPrefs['currency'] === 'USD' ? 'selected' : ''; ?>>USD ($) - US Dollar</option>
                                            <option value="EUR" <?= $userPrefs['currency'] === 'EUR' ? 'selected' : ''; ?>>EUR (€) - Euro</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="settings-form-actions">
                                    <button type="submit" class="settings-btn settings-btn-primary">
                                        <svg viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                                        Save Changes
                                    </button>
                                </div>
                            </article>
                        </form>
                    </div>

                    <!-- Security Tab -->
                    <div class="tab-panel" id="tab-security">
                        <form class="settings-form" method="POST" id="passwordForm">
                            <input type="hidden" name="action" value="update_password">
                            
                            <article class="dashboard-card settings-card">
                                <div class="settings-card-header">
                                    <h2>Change Password</h2>
                                    <p>Ensure your account is using a strong password.</p>
                                </div>
                                
                                <div class="settings-form-group">
                                    <label class="settings-label" for="currentPassword">Current Password</label>
                                    <input type="password" id="currentPassword" name="current_password" class="settings-input" placeholder="Enter current password" required>
                                </div>
                                
                                <div class="settings-form-row">
                                    <div class="settings-form-group">
                                        <label class="settings-label" for="newPassword">New Password</label>
                                        <input type="password" id="newPassword" name="new_password" class="settings-input" placeholder="Enter new password" required>
                                        <span class="settings-hint">Minimum 8 characters.</span>
                                    </div>
                                    <div class="settings-form-group">
                                        <label class="settings-label" for="confirmPassword">Confirm Password</label>
                                        <input type="password" id="confirmPassword" name="confirm_password" class="settings-input" placeholder="Confirm new password" required>
                                    </div>
                                </div>
                                
                                <div class="settings-form-actions">
                                    <button type="submit" class="settings-btn settings-btn-primary">
                                        <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                        Update Password
                                    </button>
                                </div>
                            </article>
                        </form>
                        
                        <article class="dashboard-card settings-card">
                            <div class="settings-card-header">
                                <h2>Two-Factor Authentication</h2>
                                <p>Add an extra layer of security to your account.</p>
                            </div>
                            <div class="settings-2fa-section">
                                <div class="settings-2fa-status">
                                    <span class="settings-badge settings-badge-warning">Disabled</span>
                                    <p>Two-factor authentication is currently disabled for your account.</p>
                                </div>
                                <button type="button" class="settings-btn settings-btn-outline" disabled>
                                    Enable 2FA
                                    <span class="settings-badge settings-badge-info">Coming Soon</span>
                                </button>
                            </div>
                        </article>
                        
                        <article class="dashboard-card settings-card">
                            <div class="settings-card-header">
                                <h2>Active Sessions</h2>
                                <p>Manage your active login sessions across devices.</p>
                            </div>
                            <div class="settings-sessions">
                                <div class="settings-session-item">
                                    <div class="settings-session-info">
                                        <svg viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                                        <div>
                                            <strong>Current Session</strong>
                                            <span>Windows · Chrome · Jakarta, Indonesia</span>
                                        </div>
                                    </div>
                                    <span class="settings-badge settings-badge-success">Active Now</span>
                                </div>
                            </div>
                        </article>
                    </div>

                    <!-- Billing Tab -->
                    <div class="tab-panel" id="tab-billing">
                        <article class="dashboard-card settings-card">
                            <div class="settings-card-header">
                                <h2>Current Plan</h2>
                                <p>Manage your subscription and billing details.</p>
                            </div>
                            
                            <div class="settings-plan-card">
                                <div class="settings-plan-info">
                                    <div class="settings-plan-badge"><?= htmlspecialchars(ucfirst($currentUser['membership'] ?? 'Starter'), ENT_QUOTES, 'UTF-8'); ?></div>
                                    <div class="settings-plan-details">
                                        <strong><?= htmlspecialchars(ucfirst($currentUser['membership'] ?? 'Starter'), ENT_QUOTES, 'UTF-8'); ?> Plan</strong>
                                        <span>Member since <?= htmlspecialchars(date('M d, Y', strtotime($currentUser['created_at'] ?? 'now')), ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                </div>
                                <?php if (($currentUser['membership'] ?? 'Starter') === 'Starter'): ?>
                                    <a href="../pricing.php" class="settings-btn settings-btn-primary">
                                        Upgrade Plan
                                        <svg viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </article>
                        
                        <article class="dashboard-card settings-card">
                            <div class="settings-card-header">
                                <h2>Payment Method</h2>
                                <p>Update your payment information.</p>
                            </div>
                            <div class="settings-payment-method">
                                <div class="settings-payment-card">
                                    <svg viewBox="0 0 24 24"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                                    <div>
                                        <strong>No payment method added</strong>
                                        <span>Add a payment method to upgrade your plan.</span>
                                    </div>
                                </div>
                                <button type="button" class="settings-btn settings-btn-outline">
                                    Add Payment Method
                                </button>
                            </div>
                        </article>
                        
                        <article class="dashboard-card settings-card">
                            <div class="settings-card-header">
                                <h2>Billing History</h2>
                                <p>View your past invoices and payments.</p>
                            </div>
                            <div class="settings-billing-empty">
                                <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                <p>No billing history yet.</p>
                            </div>
                        </article>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Scripts -->
    <script src="../script.js?v=<?= filemtime(__DIR__ . '/../script.js'); ?>"></script>
    <script>
    (function() {
        // === Tab Navigation ===
        document.querySelectorAll('.settings-tab').forEach(function(tab) {
            tab.addEventListener('click', function() {
                document.querySelectorAll('.settings-tab').forEach(function(t) {
                    t.classList.remove('active');
                });
                tab.classList.add('active');
                document.querySelectorAll('.tab-panel').forEach(function(panel) {
                    panel.classList.remove('active');
                });
                var tabId = tab.getAttribute('data-tab');
                document.getElementById('tab-' + tabId).classList.add('active');
            });
        });

        // === Avatar Preview ===
        var avatarInput = document.getElementById('avatarInput');
        var avatarPreview = document.getElementById('avatarPreview');
        
        if (avatarInput && avatarPreview) {
            avatarInput.addEventListener('change', function(e) {
                var file = e.target.files[0];
                if (file) {
                    var reader = new FileReader();
                    reader.onload = function(event) {
                        avatarPreview.src = event.target.result;
                    };
                    reader.readAsDataURL(file);
                }
            });
        }

        // === Password Validation ===
        var passwordForm = document.getElementById('passwordForm');
        if (passwordForm) {
            passwordForm.addEventListener('submit', function(e) {
                var newPass = document.getElementById('newPassword').value;
                var confirmPass = document.getElementById('confirmPassword').value;
                
                if (newPass !== confirmPass) {
                    e.preventDefault();
                    alert('New passwords do not match!');
                    return;
                }
                
                if (newPass.length < 8) {
                    e.preventDefault();
                    alert('Password must be at least 8 characters!');
                    return;
                }
            });
        }

        // === Workspace Dropdown ===
        var workspaceToggle = document.getElementById('workspaceToggle');
        var workspaceDropdown = document.getElementById('workspaceDropdown');
        
        if (workspaceToggle && workspaceDropdown) {
            workspaceToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                workspaceDropdown.classList.toggle('is-visible');
                var topbarDropdown = document.getElementById('topbarDropdown');
                var notifPopup = document.getElementById('notifPopup');
                topbarDropdown && topbarDropdown.classList.remove('is-visible');
                notifPopup && notifPopup.classList.remove('is-visible');
            });
            
            document.addEventListener('click', function(e) {
                if (!workspaceDropdown.contains(e.target) && !workspaceToggle.contains(e.target)) {
                    workspaceDropdown.classList.remove('is-visible');
                }
            });
            
            workspaceDropdown.querySelectorAll('.dropdown-item[data-workspace-id]').forEach(function(item) {
                item.addEventListener('click', function() {
                    var workspaceId = this.getAttribute('data-workspace-id');
                    window.location.href = 'index.php?workspace_id=' + workspaceId;
                });
            });
        }
        
        // === Topbar Account Menu ===
        var topbarAvatar = document.getElementById('topbarAvatar');
        var topbarDropdown = document.getElementById('topbarDropdown');
        
        if (topbarAvatar && topbarDropdown) {
            topbarAvatar.addEventListener('click', function(e) {
                e.stopPropagation();
                var isOpen = topbarDropdown.classList.toggle('is-visible');
                topbarAvatar.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                workspaceDropdown && workspaceDropdown.classList.remove('is-visible');
                var notifPopup = document.getElementById('notifPopup');
                notifPopup && notifPopup.classList.remove('is-visible');
            });
            
            document.addEventListener('click', function(e) {
                if (!topbarDropdown.contains(e.target) && !topbarAvatar.contains(e.target)) {
                    topbarDropdown.classList.remove('is-visible');
                    topbarAvatar.setAttribute('aria-expanded', 'false');
                }
            });
        }
        
        // === Notification Popup ===
        var notifBtn = document.getElementById('notifBtn');
        var notifPopup = document.getElementById('notifPopup');
        var notifClose = document.getElementById('notifClose');
        
        if (notifBtn && notifPopup) {
            notifBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                notifPopup.classList.toggle('is-visible');
                workspaceDropdown && workspaceDropdown.classList.remove('is-visible');
                topbarDropdown && topbarDropdown.classList.remove('is-visible');
                topbarAvatar && topbarAvatar.setAttribute('aria-expanded', 'false');
            });
            
            if (notifClose) {
                notifClose.addEventListener('click', function() {
                    notifPopup.classList.remove('is-visible');
                });
            }
            
            document.addEventListener('click', function(e) {
                if (!notifPopup.contains(e.target) && !notifBtn.contains(e.target)) {
                    notifPopup.classList.remove('is-visible');
                }
            });
        }
    })();
    </script>

</body>
</html>
