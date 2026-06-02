<?php
require_once __DIR__ . '/../connect.php';

// Auth check
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

$stmt = $pdo->prepare("SELECT membership, name, email, avatar, phone FROM users WHERE id_user = ?");
$stmt->execute([$_SESSION['user_id']]);
$adminUser = $stmt->fetch();

if (!$adminUser || $adminUser['membership'] !== 'admin') {
    header('Location: ' . APP_URL . '/dashboard/index.php');
    exit;
}

// Handle settings update
$alert = null;

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
                $stmt->execute([$name, $phone, $_SESSION['user_id']]);
                
                $_SESSION['user_name'] = $name;
                
                // Handle avatar upload
                if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = __DIR__ . '/../uploads/avatars/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    
                    $fileExt = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
                    $allowedExt = ['jpg', 'jpeg', 'png', 'gif'];
                    
                    if (in_array($fileExt, $allowedExt)) {
                        $fileName = 'avatar_' . $_SESSION['user_id'] . '_' . time() . '.' . $fileExt;
                        $uploadPath = $uploadDir . $fileName;
                        
                        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $uploadPath)) {
                            $avatarUrl = 'uploads/avatars/' . $fileName;
                            $stmt = $pdo->prepare('UPDATE users SET avatar = ? WHERE id_user = ?');
                            $stmt->execute([$avatarUrl, $_SESSION['user_id']]);
                            $_SESSION['user_avatar'] = $avatarUrl;
                        }
                    }
                }
                
                $alert = ['type' => 'success', 'message' => 'Profile updated successfully!'];
                break;
                
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
                
                $passwordStmt = $pdo->prepare('SELECT password FROM users WHERE id_user = ?');
                $passwordStmt->execute([$_SESSION['user_id']]);
                $userPasswordData = $passwordStmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$userPasswordData || !password_verify($currentPassword, $userPasswordData['password'])) {
                    throw new Exception('Current password is incorrect');
                }
                
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id_user = ?');
                $stmt->execute([$hashedPassword, $_SESSION['user_id']]);
                
                $alert = ['type' => 'success', 'message' => 'Password updated successfully!'];
                break;
                
            case 'update_system':
                $systemName = trim($_POST['system_name'] ?? 'Roots ERP');
                $systemEmail = trim($_POST['system_email'] ?? '');
                $maintenanceMode = isset($_POST['maintenance_mode']) ? 1 : 0;
                $allowRegistration = isset($_POST['allow_registration']) ? 1 : 0;
                
                // Store system settings (using a simple key-value approach)
                $settings = [
                    'system_name' => $systemName,
                    'system_email' => $systemEmail,
                    'maintenance_mode' => $maintenanceMode,
                    'allow_registration' => $allowRegistration
                ];
                
                foreach ($settings as $key => $value) {
                    $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                    $stmt->execute([$key, $value, $value]);
                }
                
                $alert = ['type' => 'success', 'message' => 'System settings updated successfully!'];
                break;
        }
        
        // Refresh admin data
        $stmt = $pdo->prepare("SELECT membership, name, email, avatar, phone FROM users WHERE id_user = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $adminUser = $stmt->fetch();
        
    } catch (Exception $e) {
        $alert = ['type' => 'error', 'message' => $e->getMessage()];
    }
}

// Get system settings
$systemSettings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch()) {
        $systemSettings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {
    // Table might not exist yet
}

// Get system stats
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalWorkspaces = $pdo->query("SELECT COUNT(*) FROM workspaces")->fetchColumn();
$totalAdmins = $pdo->query("SELECT COUNT(*) FROM users WHERE membership = 'admin'")->fetchColumn();

// Set sidebar variables
$displayName = $adminUser['name'];
$adminEmail = $adminUser['email'];
$avatar = $adminUser['avatar'];

$pageTitle = 'Settings - Super Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/superadmin/partials/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Poppins:wght@600;700;800;900&display=swap" rel="stylesheet">
</head>
<body>
    <div class="superadmin-shell">
        <?php include __DIR__ . '/partials/sidebar.php'; ?>

        <div class="superadmin-main">
            <header class="superadmin-topbar">
                <div class="superadmin-breadcrumb">
                    <a href="<?php echo APP_URL; ?>/superadmin/index.php">Roots</a>
                    <i></i>
                    <strong>Settings</strong>
                </div>
            </header>

            <main class="superadmin-content">
                <h1>Settings</h1>
                <p class="superadmin-subtitle">Manage your admin account and system configuration.</p>

                <?php if ($alert): ?>
                    <div class="sa-alert sa-alert-<?php echo $alert['type']; ?>">
                        <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        <?php echo htmlspecialchars($alert['message']); ?>
                    </div>
                <?php endif; ?>

                <!-- Settings Tabs -->
                <div class="sa-settings-tabs">
                    <button class="sa-settings-tab active" data-tab="profile">
                        <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        My Profile
                    </button>
                    <button class="sa-settings-tab" data-tab="security">
                        <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        Security
                    </button>
                    <button class="sa-settings-tab" data-tab="system">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                        System
                    </button>
                </div>

                <!-- Tab Content -->
                <div class="sa-tab-content">
                    
                    <!-- My Profile Tab -->
                    <div class="sa-tab-panel active" id="tab-profile">
                        <form class="sa-settings-form" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="update_profile">
                            
                            <div class="sa-panel">
                                <div class="sa-panel-header">
                                    <h2>Profile Information</h2>
                                    <p>Update your personal details and profile photo.</p>
                                </div>
                                
                                <div class="sa-settings-avatar-upload">
                                    <?php 
                                    $avatarPath = '';
                                    if (!empty($adminUser['avatar'])) {
                                        $avatarPath = $adminUser['avatar'];
                                        if (strpos($avatarPath, 'uploads/') === 0) {
                                            $avatarPath = '../' . $avatarPath;
                                        }
                                    }
                                    ?>
                                    <img src="<?php echo !empty($avatarPath) ? htmlspecialchars($avatarPath) : '../img/roots_symbol.png'; ?>" alt="Avatar" class="sa-settings-avatar-preview" id="avatarPreview">
                                    <div class="sa-settings-avatar-actions">
                                        <label class="sa-btn" for="avatarInput">
                                            <svg viewBox="0 0 24 24" style="width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                            Upload Photo
                                        </label>
                                        <input type="file" id="avatarInput" name="avatar" accept="image/*" hidden>
                                        <span style="font-size:12px;color:#6f7681;">JPG, PNG or GIF. Max 2MB.</span>
                                    </div>
                                </div>
                                
                                <div class="sa-settings-row">
                                    <div class="sa-field">
                                        <label>Full Name</label>
                                        <input type="text" name="name" value="<?php echo htmlspecialchars($adminUser['name']); ?>" required>
                                    </div>
                                    <div class="sa-field">
                                        <label>Email Address</label>
                                        <input type="email" value="<?php echo htmlspecialchars($adminUser['email']); ?>" disabled>
                                        <span style="font-size:12px;color:#6f7681;margin-top:4px;display:block;">Email cannot be changed for security reasons.</span>
                                    </div>
                                </div>
                                
                                <div class="sa-field">
                                    <label>Phone Number</label>
                                    <input type="tel" name="phone" value="<?php echo htmlspecialchars($adminUser['phone'] ?? ''); ?>" placeholder="+62 812 3456 7890">
                                </div>
                                
                                <div style="margin-top:20px;">
                                    <button type="submit" class="sa-btn sa-btn-primary">
                                        <svg viewBox="0 0 24 24" style="width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2;"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                                        Save Changes
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Security Tab -->
                    <div class="sa-tab-panel" id="tab-security">
                        <form class="sa-settings-form" method="POST" id="passwordForm">
                            <input type="hidden" name="action" value="update_password">
                            
                            <div class="sa-panel">
                                <div class="sa-panel-header">
                                    <h2>Change Password</h2>
                                    <p>Ensure your account is using a strong password.</p>
                                </div>
                                
                                <div class="sa-field">
                                    <label>Current Password</label>
                                    <input type="password" name="current_password" placeholder="Enter current password" required>
                                </div>
                                
                                <div class="sa-settings-row">
                                    <div class="sa-field">
                                        <label>New Password</label>
                                        <input type="password" name="new_password" placeholder="Enter new password" required>
                                        <span style="font-size:12px;color:#6f7681;margin-top:4px;display:block;">Minimum 8 characters.</span>
                                    </div>
                                    <div class="sa-field">
                                        <label>Confirm Password</label>
                                        <input type="password" name="confirm_password" placeholder="Confirm new password" required>
                                    </div>
                                </div>
                                
                                <div style="margin-top:20px;">
                                    <button type="submit" class="sa-btn sa-btn-primary">
                                        <svg viewBox="0 0 24 24" style="width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                        Update Password
                                    </button>
                                </div>
                            </div>
                        </form>
                        
                        <div class="sa-panel" style="margin-top:20px;">
                            <div class="sa-panel-header">
                                <h2>Active Sessions</h2>
                                <p>Manage your active login sessions across devices.</p>
                            </div>
                            <div class="sa-session-item">
                                <div class="sa-session-info">
                                    <svg viewBox="0 0 24 24" style="width:20px;height:20px;stroke:var(--mint-dark);fill:none;stroke-width:2;flex-shrink:0;"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                                    <div>
                                        <strong>Current Session</strong>
                                        <span style="display:block;font-size:13px;color:#6f7681;">Windows · <?php echo htmlspecialchars($_SERVER['HTTP_USER_AGENT'] ? explode(' ', explode('/', $_SERVER['HTTP_USER_AGENT'])[0])[0] : 'Browser'); ?> · Admin Panel</span>
                                    </div>
                                </div>
                                <span class="sa-session-badge">Active Now</span>
                            </div>
                        </div>
                    </div>

                    <!-- System Tab -->
                    <div class="sa-tab-panel" id="tab-system">
                        <!-- System Stats -->
                        <div class="sa-stats-row">
                            <div class="sa-stat-card">
                                <div class="sa-stat-head">
                                    <div class="sa-stat-ico">
                                        <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                                    </div>
                                    <span>Total Users</span>
                                </div>
                                <strong><?php echo number_format($totalUsers); ?></strong>
                            </div>
                            <div class="sa-stat-card">
                                <div class="sa-stat-head">
                                    <div class="sa-stat-ico" style="background:var(--mint-pale);">
                                        <svg viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                                    </div>
                                    <span>Workspaces</span>
                                </div>
                                <strong><?php echo number_format($totalWorkspaces); ?></strong>
                            </div>
                            <div class="sa-stat-card">
                                <div class="sa-stat-head">
                                    <div class="sa-stat-ico" style="background:#ede9fe;">
                                        <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                                    </div>
                                    <span>Admins</span>
                                </div>
                                <strong><?php echo number_format($totalAdmins); ?></strong>
                            </div>
                        </div>

                        <form class="sa-settings-form" method="POST" style="margin-top:20px;">
                            <input type="hidden" name="action" value="update_system">
                            
                            <div class="sa-panel">
                                <div class="sa-panel-header">
                                    <h2>System Configuration</h2>
                                    <p>Configure global system settings for Roots ERP.</p>
                                </div>
                                
                                <div class="sa-settings-row">
                                    <div class="sa-field">
                                        <label>System Name</label>
                                        <input type="text" name="system_name" value="<?php echo htmlspecialchars($systemSettings['system_name'] ?? 'Roots ERP'); ?>">
                                    </div>
                                    <div class="sa-field">
                                        <label>System Email</label>
                                        <input type="email" name="system_email" value="<?php echo htmlspecialchars($systemSettings['system_email'] ?? ''); ?>" placeholder="admin@roots.id">
                                    </div>
                                </div>
                                
                                <div class="sa-settings-row">
                                    <div class="sa-field">
                                        <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                                            <input type="checkbox" name="maintenance_mode" value="1" <?php echo !empty($systemSettings['maintenance_mode']) ? 'checked' : ''; ?>>
                                            <div>
                                                <strong>Maintenance Mode</strong>
                                                <span style="display:block;font-size:12px;color:#6f7681;">Restrict access to the application</span>
                                            </div>
                                        </label>
                                    </div>
                                    <div class="sa-field">
                                        <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                                            <input type="checkbox" name="allow_registration" value="1" <?php echo isset($systemSettings['allow_registration']) && $systemSettings['allow_registration'] ? 'checked' : (!isset($systemSettings['allow_registration']) ? 'checked' : ''); ?>>
                                            <div>
                                                <strong>Allow Registration</strong>
                                                <span style="display:block;font-size:12px;color:#6f7681;">Allow new users to register</span>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                                
                                <div style="margin-top:20px;">
                                    <button type="submit" class="sa-btn sa-btn-primary">
                                        <svg viewBox="0 0 24 24" style="width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2;"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                                        Save System Settings
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
    (function() {
        // Tab Navigation
        document.querySelectorAll('.sa-settings-tab').forEach(function(tab) {
            tab.addEventListener('click', function() {
                document.querySelectorAll('.sa-settings-tab').forEach(function(t) {
                    t.classList.remove('active');
                });
                tab.classList.add('active');
                document.querySelectorAll('.sa-tab-panel').forEach(function(panel) {
                    panel.classList.remove('active');
                });
                var tabId = tab.getAttribute('data-tab');
                document.getElementById('tab-' + tabId).classList.add('active');
            });
        });

        // Avatar Preview
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

        // Password Validation
        var passwordForm = document.getElementById('passwordForm');
        if (passwordForm) {
            passwordForm.addEventListener('submit', function(e) {
                var newPass = document.querySelector('[name="new_password"]').value;
                var confirmPass = document.querySelector('[name="confirm_password"]').value;
                
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
    })();
    </script>
</body>
</html>