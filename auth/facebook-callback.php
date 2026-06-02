<?php
session_start();
require_once '../connect.php';
require_once '../auth/auth3thparty.php'; 

$code = $_GET['code'] ?? '';
if (!$code) {
    header('Location: ' . APP_URL . '/login.php?error=oauth_failed');
    exit;
}

$tokenResponse = file_get_contents(
    'https://graph.facebook.com/v19.0/oauth/access_token?' .http_build_query([
        'client_id' => FACEBOOK_APP_ID,
        'client_secret' => FACEBOOK_APP_SECRET,
        'redirect_uri' => FACEBOOK_REDIRECT_URI,
        'code' => $code,
    ])
);

$token = json_decode($tokenResponse, true);
if (empty($token['access_token'])) {
    header('Location: ' . APP_URL . '/login.php?error=exchange_failed');
    exit;
}
 
$userResponse = file_get_contents(
    'https://graph.facebook.com/v19.0/me?' . http_build_query([
        'fields' => 'id,name,email,picture.type(large)',
        'access_token' => $token['access_token'],
    ])
);

$fbUser = json_decode($userResponse, true);
if (empty($fbUser['email'])) {
    header('Location: ' . APP_URL . '/login.php?error=no_email');
    exit;
}

$avatar = $fbUser['picture']['data']['url'] ?? null;
$name = $fbUser['name'];

// 1. Cek dari Oauth UID
$stmt = $pdo->prepare("SELECT * FROM users WHERE oauth_uid = ? AND oauth_provider = 'facebook'");
$stmt->execute([$fbUser['id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) { 
    // 2. Cek dari Email
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?"); 
    $stmt->execute([$fbUser['email']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $pdo->prepare("UPDATE users SET avatar = ?, oauth_provider = 'facebook', oauth_uid = ? WHERE id_user = ?")
            ->execute([$avatar, $fbUser['id'], $user['id_user']]);
    } else {
        $dummyPassword = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
        
        $insertStmt = $pdo->prepare("
            INSERT INTO users (name, email, avatar, oauth_provider, oauth_uid, password, membership, role)
            VALUES (?, ?, ?, 'facebook', ?, ?, 'starter', 'Personal')
            RETURNING id_user
        ");
        
        $insertStmt->execute([
            $name, 
            $fbUser['email'], 
            $avatar, 
            $fbUser['id'], 
            $dummyPassword
        ]);
        
        $newUserId = $insertStmt->fetchColumn();
        
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id_user = ?");
        $stmt->execute([$newUserId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} else {
    // Update avatar kalau user udah exist dari login facebook sebelumnya
    $pdo->prepare("UPDATE users SET avatar = ? WHERE id_user = ?")
        ->execute([$avatar, $user['id_user']]);
}

if (!$user){
    header('Location: ' . APP_URL . '/login.php?error=oauth_failed');
    exit;
}

loginUser($user);
header('Location: ' . APP_URL . '/dashboard/index.php');
exit;
?>
