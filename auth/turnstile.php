<?php
function verifyTurnstile(string $token, string $ip): bool{
    if (empty($token)) return false;

    $response = file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false,
        stream_context_create(['http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/x-www-form-urlencoded',
            'content' => http_build_query([
                'secret' => CF_TURNSTILE_SECRET_KEY,
                'response' => $token,
                'remoteip' => $ip,
            ])
        ]])
    );

    $data = json_decode($response, true);
    return ($data['success']) === true;
}
?>