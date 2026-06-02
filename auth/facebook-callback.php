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

$stmt = $pdo->prepare("SELECT * FROM users WHERE oauth_uid = ? AND oauth_provider = 'facebook'");
$stmt->execute([$fbUser['id']]);
$user = $stmt->fetch();

if (!$user){ 
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?"); 
    $stmt->execute([$fbUser['email']]);
    $user = $stmt->fetch();

    if ($user){
        $pdo->prepare("UPDATE users SET avatar = ?, oauth_provider = 'facebook', oauth_uid = ? WHERE id_user = ?")
            ->execute([$avatar, $fbUser['id'], $user['id_user']]);
    } else {
        $pdo->prepare("INSERT INTO users (nama, email, avatar, oauth_provider, oauth_uid, role) VALUES (?, ?, ?, 'facebook', ?, 'user')")
            ->execute([$name, $fbUser['email'], $avatar, $fbUser['id']]);
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$fbUser['email']]);
    $user = $stmt->fetch();
} else {
    $pdo->prepare("UPDATE users SET avatar = ? WHERE id_user = ?")
        ->execute([$avatar, $user['id_user']]);
}

if (!$user){
    header('Location: ' . APP_URL . '/login.php?error=oauth_failed');
    exit;
}

loginUser($user);
header('Location: ' . APP_URL . '/dashboard.php');
exit;