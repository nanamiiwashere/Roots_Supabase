<?php

session_start();
require_once '../connect.php';
require_once '../auth/auth3thparty.php';

$code  = $_GET['code'] ?? '';
if (!$code) {
    header('Location: ' . APP_URL . 'login.php');
    exit;
}

#exchange token
$tokenResponse = file_get_contents('https://discord.com/api/oauth2/token', false, stream_context_create(['http' => [
    'method' => 'POST',
    'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
    'content' => http_build_query([
        'client_id' => DISCORD_CLIENT_ID,
        'client_secret' => DISCORD_CLIENT_SECRET,
        'grant_type' => 'authorization_code',
        'code' => $code,
        'redirect_uri' => DISCORD_REDIRECT_URI
        ]),
    ]])
);

$token = json_decode($tokenResponse, true);
if (empty($token['access_token'])){
    header('Location: login.php?error=exchange_failed');
    exit;
}

$userResponse = file_get_contents('https://discord.com/api/users/@me', false, stream_context_create(['http' => [
    'method' => 'GET',
    'header' => "Authorization: Bearer {$token['access_token']}\r\n"
    ]])
);

$discordUser = json_decode($userResponse, true);

if (empty($discordUser['email'])){
    header('Location: login.php?error=no_email');
    exit;
}

$avatar = $discordUser['avatar']
    ? "https://cdn.discordapp.com/avatars/{$discordUser['id']}/{$discordUser['avatar']}.png"
    : "https://cdn.discordapp.com/embed/avatars/0.png";

$name  = $discordUser['global_name'] ?? $discordUser['username'];

$stmt = $pdo->prepare("SELECT * FROM users WHERE oauth_uid = ? AND oauth_provider = 'discord'");
$stmt -> execute([$discordUser['email']]);
$user = $stmt -> fetch();

if (!$user){
    $stmt = $pdo -> prepare(
        "INSERT INTO users (name, email, avatar, oauth_provider, oauth_uid)
        VALUES (?, ?, ?, 'discord', ?)"
    );

    $stmt -> execute([$name, $discordUser['email'], $avatar, $discordUser['id']]);
    $stmt = $pdo -> prepare("SELECT * FROM users WHERE id_user = ?");
    $stmt -> execute([$pdo -> lastInsertId()]);
    $user = $stmt -> fetch();
} else {
    $pdo -> prepare("UPDATE users SET avatar = ?, oauth_provider = 'discord', oauth_uid = ? WHERE id_user = ?")
         -> execute([$avatar, $discordUser['id'], $user['id_user']]);
}

loginUser($user);
header('Location: ' . APP_URL . '/dashboard.php');
exit;
