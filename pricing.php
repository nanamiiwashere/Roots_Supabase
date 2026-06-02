<?php
require_once __DIR__ . '/auth/auth-handler.php';
require_once __DIR__ . '/auth/auth3thparty.php';
require_once __DIR__ . '/handler/supabase-img.php';

$currentUser = currentUser();

// Define current URL helper
function roots_current_url(): string {
    $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    // Remove hash and query for clean return URL
    $uri = strtok($uri, '?#');
    return $scheme . '://' . $host . $uri;
}

// OAuth URLs for social login buttons in modals
$googleUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
    'client_id' => GOOGLE_CLIENT_ID,
    'redirect_uri' => GOOGLE_REDIRECT_URI,
    'response_type' => 'code',
    'scope' => 'openid email profile',
]);

$discordUrl = 'https://discord.com/api/oauth2/authorize?' . http_build_query([
    'client_id' => DISCORD_CLIENT_ID,
    'redirect_uri' => DISCORD_REDIRECT_URI,
    'response_type' => 'code',
    'scope' => 'openid email profile',
]);

$facebookUrl = 'https://www.facebook.com/v19.0/dialog/oauth?' . http_build_query([
    'client_id' => FACEBOOK_APP_ID,
    'redirect_uri' => FACEBOOK_REDIRECT_URI,
    'response_type' => 'code',
    'scope' => 'email,public_profile'
]);

$plans = [
    [
        'name' => 'Starter',
        'monthly' => 0,
        'yearly' => 0,
        'description' => 'Designed for startups at the very beginning of their journey',
        'features' => ['6 User', 'Basic Invoicing', 'Stock Management (1 Warehouse)', 'Monthly Reports'],
        'popular' => false,
    ],
    [
        'name' => 'Pro',
        'monthly' => 145,
        'yearly' => 1740,
        'description' => 'For fast growing businesses ready to scale',
        'features' => ['16 User', 'Custom Invoicing', 'Stock Management', 'Bank Integration', 'Basic AI Analytics'],
        'popular' => true,
    ],
    [
        'name' => 'Scale',
        'monthly' => 445,
        'yearly' => 5340,
        'description' => 'Built for teams ready to operate at full scale',
        'features' => ['Unlimited User', 'API Access', 'Advanced AI Analytics', 'Custom Integrations', 'White Label'],
        'popular' => false,
    ],
];

// Helper to format price in IDR (values stored in thousands)
function format_idr_price(int $valueInK): string {
    if ($valueInK === 0) {
        return '<span class="price-currency">IDR</span> 0';
    }
    return '<span class="price-currency">IDR</span> ' . $valueInK . 'k';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pricing - Roots</title>
    <meta name="description" content="Paket harga Roots ERP untuk startup, bisnis berkembang, dan tim yang siap scale.">
    <link rel="icon" href="<?= supabaseImageUrl('img/roots_symbol.png'); ?>" type="image/png">
    <link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__ . '/style.css'); ?>">
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
</head>
<body>
    <main class="page pricing-page">
        <header class="nav" aria-label="Navigasi utama">
            <a class="brand" href="index.php" aria-label="Roots home">
                <span class="brand-logo" aria-hidden="true"></span>
            </a>

            <nav class="nav-links" aria-label="Menu">
                <a class="is-active" href="pricing.php">Pricing</a>
                <a href="faq.php">Help</a>
            </nav>

            <div class="nav-actions">
                <?php if ($currentUser): ?>
                    <a class="btn btn-primary" href="./dashboard/index.php">Dashboard</a>
                <?php else: ?>
                    <a class="signin login-trigger" href="./login.php" aria-controls="signin">Sign In</a>
                    <a class="btn btn-primary signup-trigger" href="./register.php" aria-controls="signup">Try it free</a>
                <?php endif; ?>
            </div>
        </header>

        <section class="pricing-hero" aria-labelledby="pricing-title">
            <div class="pricing-kicker">EXPLORE OUR PRICING</div>
            <h1 id="pricing-title">Everything you need. Nothing you don't</h1>

            <div class="billing-toggle" aria-label="Billing period">
                <span class="billing-label is-selected">Monthly</span>
                <button class="billing-switch" type="button" aria-pressed="false" aria-label="Switch to yearly billing">
                    <span></span>
                </button>
                <span class="billing-label">Yearly</span>
            </div>

            <div class="pricing-grid">
                <?php foreach ($plans as $plan): ?>
                    <article class="pricing-card<?= $plan['popular'] ? ' is-popular' : ''; ?>">
                        <?php if ($plan['popular']): ?>
                            <div class="popular-badge"><i class="fa-solid fa-crown"></i>Most Popular</div>
                        <?php endif; ?>

                        <div class="price">
                            <strong data-monthly="<?= (int) $plan['monthly']; ?>" data-yearly="<?= (int) $plan['yearly']; ?>"><?= format_idr_price((int) $plan['monthly']); ?></strong>
                            <span class="price-period">/ month</span>
                        </div>

                        <h2><?= htmlspecialchars($plan['name'], ENT_QUOTES, 'UTF-8'); ?></h2>
                        <p><?= htmlspecialchars($plan['description'], ENT_QUOTES, 'UTF-8'); ?></p>

                        <ul>
                            <?php foreach ($plan['features'] as $feature): ?>
                                <li><span></span><?= htmlspecialchars($feature, ENT_QUOTES, 'UTF-8'); ?></li>
                            <?php endforeach; ?>
                        </ul>

                        <?php if ($currentUser && strtolower($currentUser['membership']) === strtolower($plan['name'])): ?>
                            <div class="current-plan-button">CURRENT PLAN</div>
                        <?php else: ?>
                            <a class="choose-plan signup-trigger" href="#signup" aria-controls="signup">CHOOSE PLAN</a>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <footer class="footer" id="help">
            <div class="footer-brand">
                <a class="brand" href="index.php" aria-label="Roots home">
                    <span class="brand-logo" aria-hidden="true"></span>
                </a>
                <p>MSME and Indonesia Startup partner to build a strong and scalable operational foundation.</p>
                <div class="social-links" aria-label="Social media">
                    <a href="#linkedin" aria-label="LinkedIn">in</a>
                    <a href="#twitter" aria-label="Twitter">x</a>
                    <a href="#github" aria-label="GitHub">gh</a>
                </div>
            </div>
            <nav class="footer-column" aria-label="Navigation">
                <h3>Navigation</h3>
                <a href="index.php#features-title">Features</a>
                <a href="pricing.php">Pricing</a>
                <a href="faq.php">FAQ</a>
            </nav>
            <nav class="footer-column" aria-label="Support">
                <h3>Support</h3>
                <a href="#privacy">Privacy Policy</a>
                <a href="mailto:hello@roots.local">Contact Support</a>
                <a href="#terms">Terms &amp; Conditions</a>
            </nav>
        </footer>
    </main>


    <script src="script.js?v=<?= filemtime(__DIR__ . '/script.js'); ?>"></script>
</body>
</html>
