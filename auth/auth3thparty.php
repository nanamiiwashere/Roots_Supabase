<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
 
require_once __DIR__ . '/../connect.php';

define('GOOGLE_CLIENT_ID', getenv('GOOGLE_CLIENT_ID') ?: '');
define('GOOGLE_CLIENT_SECRET', getenv('GOOGLE_CLIENT_SECRET') ?: '');
define('GOOGLE_REDIRECT_URI', getenv('GOOGLE_REDIRECT_URI') ?: '');

define('DISCORD_CLIENT_ID', getenv('DISCORD_CLIENT_ID') ?: '');
define('DISCORD_CLIENT_SECRET', getenv('DISCORD_CLIENT_SECRET') ?: '');
define('DISCORD_REDIRECT_URI', getenv('DISCORD_REDIRECT_URI') ?: '');

define('FACEBOOK_APP_ID', getenv('FACEBOOK_APP_ID') ?: '');
define('FACEBOOK_APP_SECRET', getenv('FACEBOOK_APP_SECRET') ?: '');
define('FACEBOOK_REDIRECT_URI', getenv('FACEBOOK_REDIRECT_URI') ?: '');

define('APP_URL', rtrim(getenv('APP_URL') ?: 'http://localhost:8000', '/' . '/'));

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}


function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . APP_URL . '/login.php');
        exit;
    }
}

function currentUser(): ?array {
    if (!isLoggedIn()) return null;
    
    global $pdo;
    
    // Use cached session data for performance - only refresh from DB every 60 seconds
    // or when explicitly forced (e.g., after profile updates)
    $cacheKey = 'user_cache_time';
    $cacheTtl = 60; // seconds
    $forceRefresh = isset($_SESSION['force_user_refresh']) && $_SESSION['force_user_refresh'];
    $cacheExpired = !isset($_SESSION[$cacheKey]) || (time() - $_SESSION[$cacheKey]) > $cacheTtl;
    
    if ($forceRefresh || $cacheExpired) {
        // Fetch fresh user data from database to ensure membership and other
        // fields are up-to-date (e.g., after admin changes membership)
        try {
            $stmt = $pdo->prepare("SELECT id_user, email, name, role, membership, avatar, phone, created_at FROM users WHERE id_user = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $freshUser = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$freshUser) {
                // User no longer exists in database, log them out
                logoutUser();
                return null;
            }
            
            // Update session with fresh data so it stays in sync
            $_SESSION['user_email'] = $freshUser['email'];
            $_SESSION['user_name'] = $freshUser['name'];
            $_SESSION['user_role'] = $freshUser['role'] ?? 'Personal';
            $_SESSION['user_membership'] = $freshUser['membership'] ?? 'starter';
            $_SESSION['user_avatar'] = $freshUser['avatar'] ?? null;
            $_SESSION['user_phone'] = $freshUser['phone'] ?? null;
            $_SESSION['user_created_at'] = $freshUser['created_at'] ?? null;
            $_SESSION[$cacheKey] = time();
            
            // Clear force refresh flag
            if ($forceRefresh) {
                unset($_SESSION['force_user_refresh']);
            }
        } catch (Exception $e) {
            // If DB query fails, fall back to session data
        }
    }
    
    return [
        'id_user' => $_SESSION['user_id'],
        'email' => $_SESSION['user_email'] ?? '',
        'name' => $_SESSION['user_name'] ?? '',
        'role' => $_SESSION['user_role'] ?? 'Personal',
        'membership' => $_SESSION['user_membership'] ?? 'starter',
        'provider' => $_SESSION['oauth_provider'] ?? 'email',
        'avatar' => $_SESSION['user_avatar'] ?? null,
        'phone' => $_SESSION['user_phone'] ?? null,
        'created_at' => $_SESSION['user_created_at'] ?? null,
    ];
}


function loginUser(array $user): void {
    session_regenerate_id(true);
    
    $_SESSION['user_id'] = $user['id_user'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_role'] = $user['role'] ?? 'Personal';
    $_SESSION['user_membership'] = $user['membership'] ?? 'Starter';
    $_SESSION['oauth_provider'] = $user['oauth_provider'] ?? 'email';
    $_SESSION['user_avatar'] = $user['avatar'] ?? null;
    $_SESSION['user_phone'] = $user['phone'] ?? null;
    $_SESSION['user_created_at'] = $user['created_at'] ?? null;
    $_SESSION['logged_at'] = time();
    $_SESSION['user_cache_time'] = time();
}

/**
 * Logout user and destroy session
 */
function logoutUser(): void {
    session_unset();
    session_destroy();
    header('Location: ' . APP_URL . '/index.php');
    exit;
}


function requireStaff(): void {
    if (!isLoggedIn()) {
        header('Location: ' . APP_URL . '/login.php');
        exit;
    }
    
    if (($_SESSION['membership'] ?? '') !== 'admin') {
        header('Location: ' . APP_URL . '/superadmin/index.php');
        exit;
    }
}