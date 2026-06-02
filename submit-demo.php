<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'ok' => false,
        'message' => 'Method not allowed.',
    ]);
    exit;
}

$email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);

if (!$email) {
    http_response_code(422);
    echo json_encode([
        'ok' => false,
        'message' => 'Please enter a valid email address.',
    ]);
    exit;
}

require __DIR__ . '/connect.php';

try {
    $pdo = roots_pdo();
    $statement = $pdo->prepare(
        'INSERT INTO demo_requests (email, source, created_at) VALUES (:email, :source, NOW())'
    );
    $statement->execute([
        'email' => $email,
        'source' => 'landing_page',
    ]);

    echo json_encode([
        'ok' => true,
        'message' => 'Thanks, your demo request has been received.',
    ]);
} catch (Throwable $exception) {
    http_response_code(503);
    echo json_encode([
        'ok' => false,
        'message' => 'Database error!',
    ]);
}
