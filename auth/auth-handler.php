<?php
require_once 'auth3thparty.php';
require_once __DIR__ . '/../connect.php';
require_once __DIR__ . '/turnstile.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

// Login //
    if ($action === 'login') {
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);

        if (!$email || !$password) {
            $error = "Please fill in all fields.";
        } else {

            $token = $_POST['cf-turnstile-response'] ?? '';
            if (!verifyTurnstile($token, $_SERVER['REMOTE_ADDR'])){
                $error = "Human verification failed. Please Try Again.";
            } else {

            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                loginUser($user);
                
                if ($user['membership'] === 'admin') {
                    header('Location: ' . APP_URL . '/superadmin/index.php');
                } else {
                    header('Location: ' . APP_URL . '/dashboard/index.php');
                }
                exit();
            } else {
                $error = "Invalid email or password.";
            }
        }
    }
}
    
//Signup//
    elseif ($action === 'signup') {
        $name     = trim($_POST['name']);
        $email    = trim($_POST['email']);
        $password = trim($_POST['password']);
        $confirm  = trim($_POST['confirm'] ?? '');
        $role     = trim($_POST['company_role'] ?? $_POST['role'] ?? 'Personal');

        if (!$name || !$email || !$password) {
            $error = "Please fill in all fields.";
        } elseif ($password !== $confirm) {
            $error = "Passwords do not match.";
        } else {

            $token = $_POST['cf-turnstile-response'] ?? '';
            if (!verifyTurnstile($token, $_SERVER['REMOTE_ADDR'])){
                $error = "Human verification failed. Please Try Again.";
            } else {

            $stmt = $pdo->prepare("SELECT id_user FROM users WHERE email = :email");
            $stmt->execute(['email' => $email]);
            
            if ($stmt->fetch()) {
                $error = "Email is already registered.";
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                $insertStmt = $pdo->prepare("INSERT INTO users (name, email, password, membership, role) VALUES (?, ?, ?, 'starter', ?) RETURNING id_user");
                $insertStmt->execute([$name, $email, $hashedPassword, $role]);
                $newUserId = $insertStmt->fetchColumn();

                if ($newUserId) {
                    $getUserStmt = $pdo->prepare("SELECT * FROM users WHERE id_user = ?");
                    $getUserStmt->execute([$newUserId]);
                    $newUser = $getUserStmt->fetch(PDO::FETCH_ASSOC);

                    if ($newUser) {
                        loginUser($newUser);
                        header('Location: ' . APP_URL . '/dashboard/index.php');
                        exit();
                    } else {
                        $error = "Failed to retrieve user data after registration.";
                    }
                } else {
                    $error = "Registration failed, could not generate user ID.";
                }
            }
        }
    }
}}
