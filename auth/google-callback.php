<?php

session_start();
require_once '../connect.php';
require_once '../Auth/auth3thparty.php';

$code = $_GET['code'] ?? '';
if (!$code) { 
    header('Location: login.php?error=oauth_failed'); 
    exit; 
    }

#exchange to access token
$tokenResponse = file_get_contents('https://oauth2.googleapis.com/token', false, stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/x-www-form-urlencoded\r\n',
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
    header('Location: login.php?error=exchange_failed');
    exit;
}

$userResponse = file_get_contents('https://www.googleapis.com/oauth2/v2/userinfo', false, stream_context_create(['http' => [
    'header' => "Authorization: Bearer {$token['access_token']}\r\n"
    ]])
);

$googleUser = json_decode($userResponse, true);

if (empty($googleUser['email'])){
    header('Location: login.php?error=no_email');
    exit;
}

$stmt = $pdo->prepare("
    SELECT * FROM users 
    WHERE oauth_uid = ? AND oauth_provider = 'google'");
$stmt -> execute([$googleUser['id']]);
$user = $stmt->fetch();


if (!$user) {
    // Ga ketemu by oauth_uid → cek by email
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$googleUser['email']]);  // ← ['email'] bukan [$email]
    $user = $stmt->fetch();

    if ($user) {
        $pdo->prepare("
            UPDATE users 
            SET avatar = ?, oauth_provider = 'google', oauth_uid = ?
            WHERE id_user = ?
        ")->execute([
            $googleUser['picture'] ?? null,
            $googleUser['id'],
            $user['id_user'],
        ]);

    } else {
        $pdo->prepare("
            INSERT INTO users (nama, email, avatar, oauth_provider, oauth_uid, role)
            VALUES (?, ?, ?, 'google', ?, 'user')
        ")->execute([
            $googleUser['name'],
            $googleUser['email'],
            $googleUser['picture'] ?? null,
            $googleUser['id'],
        ]);
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$googleUser['email']]);
    $user = $stmt->fetch();
}

if (!$user){
    header('Location: ' . APP_URL . 'login.php?error=user_not_found');
    exit();
}

loginUser($user);
header('Location: ' . APP_URL . '/dashboard.php');
exit();