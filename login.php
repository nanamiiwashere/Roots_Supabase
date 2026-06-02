<?php
require_once './connect.php';
require_once './Auth/auth3thparty.php';
require_once './Auth/auth-handler.php';

if (isLoggedIn()){
    header('Location: ' . APP_URL . '/dashboard/index.php');
    exit();
}

$googleUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' .http_build_query([
    'client_id' => GOOGLE_CLIENT_ID,
    'redirect_uri' => GOOGLE_REDIRECT_URI,
    'response_type' => 'code',
    'scope' => 'openid email profile',
]);

$discordUrl = 'https://discord.com/api/oauth2/authorize?' . http_build_query([
    'client_id' => DISCORD_CLIENT_ID,
    'redirect_uri' => DISCORD_REDIRECT_URI,
    'response_type' => 'code',
    'scope' => 'identify email',
]);

$facebookUrl = 'https://www.facebook.com/v19.0/dialog/oauth?' . http_build_query([
    'client_id' => FACEBOOK_APP_ID,
    'redirect_uri' => FACEBOOK_REDIRECT_URI,
    'response_type' => 'code',
    'scope' => 'email,public_profile'
]);

$urlError = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Roots</title>
    <link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__ . '/style.css'); ?>">
      <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
</head>
<body class="auth-body">

    <section class="login-dialog" role="dialog" aria-labelledby="login-title">
        <a href="index.php" class="login-close" aria-label="Back to home" style="text-decoration:none;">&times;</a>

        <div class="login-art" aria-hidden="true">
            <div class="login-art-content">
                <img class="login-symbol" src="img/roots_symbol_white.png" alt="">
                <p>Get Access your analytics<br>with Roots.</p>
            </div>
        </div>

        <div class="login-panel">
            <h2 id="login-title">Log in to your account</h2>
            <p>Welcome back! Log in to access your business operations.</p>

            <form class="login-form" id="loginForm" action="" method="post">
                <input type="hidden" name="action" value="login">

                <?php if (!empty($error) && ($_POST['action'] ?? '') === 'login'): ?>
                    <div style="background-color: #fee2e2; color: #dc2626; padding: 10px; border-radius: 6px; margin-bottom: 15px; font-size: 0.85rem;">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <label for="loginEmail">Email</label>
                <input id="loginEmail" name="email" type="email" placeholder="johndoe@gmail.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">

                <label for="loginPassword">Password</label>
                <div class="login-password-field">
                    <input id="loginPassword" name="password" type="password" placeholder="**********" required>
                    <button class="password-tool" type="button" aria-label="Show password" onclick="togglePassword('loginPassword', this)">
                        <svg class="eye-icon eye-open" viewBox="0 0 24 24" fill="none" stroke="#9aa9ba" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg class="eye-icon eye-closed" viewBox="0 0 24 24" fill="none" stroke="#9aa9ba" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                    </button>
                </div>

                <div class="login-options">
                    <label class="remember">
                        <input type="checkbox" checked>
                        <span>Remember me</span>
                    </label>
                    <a href="#forgot-password">Forgot Password?</a>
                </div>

                <div class="cf-turnstile mb-3"
                        data-sitekey="<?= CF_TURNSTILE_SITE_KEY ?>"
                        data-theme="dark">
                    </div>

                <button class="login-submit" type="submit">Login</button>
                <a href="register.php" class="auth-switch">Don't have an account?</a>

                <div class="login-divider">
                    <span></span>
                    <em>or continue with</em>
                    <span></span>
                </div>

                <div class="login-socials" aria-label="Social login">
                    <a href="<?= $googleUrl ?>" aria-label="Continue with Google">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path fill="#4285f4" d="M21.6 12.2c0-.7-.1-1.3-.2-1.9H12v3.6h5.4a4.6 4.6 0 0 1-2 3v2.4h3.2c1.9-1.7 3-4.2 3-7.1Z"/>
                            <path fill="#34a853" d="M12 22c2.7 0 5-0.9 6.6-2.5l-3.2-2.4c-.9.6-2 .9-3.4.9-2.6 0-4.8-1.8-5.6-4.1H3.1v2.5A10 10 0 0 0 12 22Z"/>
                            <path fill="#fbbc05" d="M6.4 13.9a6 6 0 0 1 0-3.8V7.6H3.1a10 10 0 0 0 0 8.8l3.3-2.5Z"/>
                            <path fill="#ea4335" d="M12 6c1.5 0 2.8.5 3.8 1.5l2.8-2.8A9.5 9.5 0 0 0 12 2a10 10 0 0 0-8.9 5.6l3.3 2.5C7.2 7.8 9.4 6 12 6Z"/>
                        </svg>
                    </a>
                    <a href="<?= $discordUrl ?>" aria-label="Continue with Discord">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path fill="#6575ff" d="M19.5 5.2A16 16 0 0 0 15.6 4l-.2.4c1.4.4 2.1 1 2.1 1s-1.8-1-5.5-1-5.5 1-5.5 1 .7-.6 2.2-1L8.4 4a16 16 0 0 0-3.9 1.2C2 9 1.3 12.7 1.6 16.4A15.6 15.6 0 0 0 6.3 19l1-1.4c-.6-.2-1.2-.5-1.7-.9l.4.2c3.4 1.6 8.5 1.6 11.9 0l.4-.2c-.5.4-1.1.7-1.7.9l1 1.4a15.6 15.6 0 0 0 4.7-2.6C22.7 12.1 21.6 8.4 19.5 5.2ZM8.7 14.5c-.9 0-1.7-.8-1.7-1.8s.8-1.8 1.7-1.8 1.7.8 1.7 1.8-.8 1.8-1.7 1.8Zm6.6 0c-.9 0-1.7-.8-1.7-1.8s.8-1.8 1.7-1.8 1.7.8 1.7 1.8-.8 1.8-1.7 1.8Z"/>
                        </svg>
                    </a>
                    <a href="<?= $facebookUrl ?>" aria-label="Continue with Facebook">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path fill="#1877f2" d="M22 12a10 10 0 1 0-11.6 9.9v-7H7.9V12h2.5V9.8c0-2.5 1.5-3.9 3.8-3.9 1.1 0 2.2.2 2.2.2v2.4h-1.3c-1.2 0-1.6.8-1.6 1.6V12h2.8l-.4 2.9h-2.4v7A10 10 0 0 0 22 12Z"/>
                        </svg>
                    </a>
                </div>
            </form>
        </div>
    </section>

    <script>
    function togglePassword(inputId, btn) {
        const input = document.getElementById(inputId);
        const eyeOpen = btn.querySelector('.eye-open');
        const eyeClosed = btn.querySelector('.eye-closed');
        if (input.type === 'password') {
            input.type = 'text';
            eyeOpen.style.display = 'none';
            eyeClosed.style.display = 'block';
        } else {
            input.type = 'password';
            eyeOpen.style.display = 'block';
            eyeClosed.style.display = 'none';
        }
    }
    </script>

</body>
</html>
