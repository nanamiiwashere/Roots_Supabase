<?php
session_start();
require_once '../connect.php';
require_once '../auth/auth3thparty.php';

$code = $_GET['code'] ?? '';
if (!$code) { 
    header('Location: ' . APP_URL . '/login.php?error=oauth_failed'); 
    exit; 
}

// Exchange to access token
$tokenResponse = file_get_contents('https://oauth2.googleapis.com/token', false, stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content' => http_build_query([
            'code' => $code,
            'client_id' => GOOGLE_CLIENT_ID,
            'client_secret' => GOOGLE_CLIENT_SECRET,
            'redirect_uri' => GOOGLE_REDIRECT_URI,
            'grant_type' => 'authorization_code',
        ]),
    ]
]));

$token = json_decode($tokenResponse, true);
if (empty($token['access_token'])){
    header('Location: ' . APP_URL . '/login.php?error=exchange_failed');
    exit;
}

$userResponse = file_get_contents('https://www.googleapis.com/oauth2/v2/userinfo', false, stream_context_create(['http' => [
    'header' => "Authorization: Bearer {$token['access_token']}\r\n"
]]));

$googleUser = json_decode($userResponse, true);

if (empty($googleUser['email'])){
    header('Location: ' . APP_URL . '/login.php?error=no_email');
    exit;
}

// 1. Cek dari Oauth UID
$stmt = $pdo->prepare("SELECT * FROM users WHERE oauth_uid = ? AND oauth_provider = 'google'");
$stmt->execute([$googleUser['id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    // 2. Cek dari Email
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$googleUser['email']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $avatar = $googleUser['picture'] ?? null;

    if ($user) {
        $pdo->prepare("UPDATE users SET avatar = ?, oauth_provider = 'google', oauth_uid = ? WHERE id_user = ?")
            ->execute([$avatar, $googleUser['id'], $user['id_user']]);
    } else {
        $dummyPassword = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
        
        $insertStmt = $pdo->prepare("
            INSERT INTO users (name, email, avatar, oauth_provider, oauth_uid, password, membership, role)
            VALUES (?, ?, ?, 'google', ?, ?, 'starter', 'Personal')
            RETURNING id_user
        ");
        
        $insertStmt->execute([
            $googleUser['name'],
            $googleUser['email'],
            $avatar,
            $googleUser['id'],
            $dummyPassword
        ]);
        
        $newUserId = $insertStmt->fetchColumn();
        
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id_user = ?");
        $stmt->execute([$newUserId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

if (!$user){
    header('Location: ' . APP_URL . '/login.php?error=user_not_found');
    exit();
}

loginUser($user);
header('Location: ' . APP_URL . '/dashboard/index.php');
exit();
?>
