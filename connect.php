<?php
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

define('SUPABASE_URL', getenv('SUPABASE_URL') ?: '');
define('SUPABASE_KEY', getenv('SUPABASE_KEY') ?: '');
define('SUPABASE_BUCKET', 'uploads');
define('CF_TURNSTILE_SITE_KEY', getenv('CF_TURNSTILE_SITE_KEY'));
define('CF_TURNSTILE_SECRET_KEY', getenv('CF_TURNSTILE_SECRET_KEY'));

$db_url = getenv('DATABASE_URL');
    
if ($db_url){
    #parse database url
    $parsed = parse_url($db_url);
    $host = $parsed['host'];
    $port = $parsed['port'] ?? 6543;
    $dbname = ltrim($parsed['path'], '/');
    $user = $parsed['user'];
    $pass = $parsed['pass'];
} else {
    #fallback local
    $host = getenv('DB_HOST') ?: '';
    $port = getenv('DB_PORT') ?: '6543';
    $dbname = getenv('DB_NAME') ?: 'postgres';
    $user = getenv('DB_USER') ?: 'postgres';
    $pass = getenv('DB_PASS') ?: '';
}

try {
    $pdo = new PDO(
        "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
