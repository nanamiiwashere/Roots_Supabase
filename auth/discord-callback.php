<?php
session_start();
require_once '../connect.php';
require_once '../auth/auth3thparty.php';

$code = $_GET['code'] ?? '';
if (!$code) {
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

// Exchange token
$tokenResponse = file_get_contents('https://discord.com/api/oauth2/token', false, stream_context_create(['http' => [
    'method' => 'POST',
    'header' => "Content-Type: application/x-www-form-urlencoded\r\nContent-Length: " . strlen(http_build_query([
        'client_id' => DISCORD_CLIENT_ID,
        'client_secret' => DISCORD_CLIENT_SECRET,
        'grant_type' => 'authorization_code',
        'code' => $code,
        'redirect_uri' => DISCORD_REDIRECT_URI
    ])) . "\r\n",
    'content' => http_build_query([
        'client_id' => DISCORD_CLIENT_ID,
        'client_secret' => DISCORD_CLIENT_SECRET,
        'grant_type' => 'authorization_code',
        'code' => $code,
        'redirect_uri' => DISCORD_REDIRECT_URI
    ]),
]]));

$token = json_decode($tokenResponse, true);
if (empty($token['access_token'])){
    header('Location: ' . APP_URL . '/login.php?error=exchange_failed');
    exit;
}

$userResponse = file_get_contents('https://discord.com/api/users/@me', false, stream_context_create(['http' => [
    'method' => 'GET',
    'header' => "Authorization: Bearer {$token['access_token']}\r\n"
]]));

$discordUser = json_decode($userResponse, true);

if (empty($discordUser['email'])){
    header('Location: ' . APP_URL . '/login.php?error=no_email');
    exit;
}

$avatar = $discordUser['avatar']
    ? "https://cdn.discordapp.com/avatars/{$discordUser['id']}/{$discordUser['avatar']}.png"
    : "https://cdn.discordapp.com/embed/avatars/0.png";

$name = $discordUser['global_name'] ?? $discordUser['username'];

// 1. Cek dari Oauth UID
$stmt = $pdo->prepare("SELECT * FROM users WHERE oauth_uid = ? AND oauth_provider = 'discord'");
$stmt->execute([$discordUser['id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    // 2. Cek dari Email
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$discordUser['email']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $pdo->prepare("UPDATE users SET avatar = ?, oauth_provider = 'discord', oauth_uid = ? WHERE id_user = ?")
            ->execute([$avatar, $discordUser['id'], $user['id_user']]);
    } else {
        $dummyPassword = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
        
        $insertStmt = $pdo->prepare("
            INSERT INTO users (name, email, avatar, oauth_provider, oauth_uid, password, membership, role)
            VALUES (?, ?, ?, 'discord', ?, ?, 'starter', 'Personal')
            RETURNING id_user
        ");
        
        $insertStmt->execute([
            $name, 
            $discordUser['email'], 
            $avatar, 
            $discordUser['id'], 
            $dummyPassword
        ]);
        
        $newUserId = $insertStmt->fetchColumn();
        
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id_user = ?");
        $stmt->execute([$newUserId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} else {
    // Update avatar
    $pdo->prepare("UPDATE users SET avatar = ? WHERE id_user = ?")
        ->execute([$avatar, $user['id_user']]);
}

if (!$user){
    header('Location: ' . APP_URL . '/login.php?error=user_not_found');
    exit();
}

loginUser($user);
header('Location: ' . APP_URL . '/dashboard/index.php');
exit;
?>
