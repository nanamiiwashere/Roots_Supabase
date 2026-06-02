<?php 

require_once __DIR__ . '/auth/auth-handler.php';
require_once __DIR__ . '/auth/auth3thparty.php';
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
    'redirect_uri' => DISCORD_REDIRECT_URL,
    'response_type' => 'code',
    'scope' => 'openid email profile',
]);

$facebookUrl = 'https://www.facebook.com/v19.0/dialog/oauth?' . http_build_query([
    'client_id' => FACEBOOK_APP_ID,
    'redirect_uri' => FACEBOOK_REDIRECT_URI,
    'response_type' => 'code',
    'scope' => 'email,public_profile'
]);

$faqGroups = [
    'getting-started' => [
        'label' => 'Getting Started',
        'title' => 'Getting Started',
        'items' => [
            [
                'question' => 'What is Roots?',
                'answer' => "Roots is a modern ERP solution designed specifically for startups and MSMEs. We help you manage your inventory, sales, finance, and customer relationships in one unified platform. It's built to scale with your business while keeping operations simple.",
            ],
            [
                'question' => 'How do I create a new organization?',
                'answer' => "When you sign up for an account, you will be prompted to create your first organization through our onboarding flow. You can also create additional organizations from the workspace switcher in the dashboard menu. Each organization has its own isolated data and user roles.",
            ],
            [
                'question' => 'Can I invite my team members?',
                'answer' => "Yes! Based on your subscription plan, you can invite team members and assign them specific roles (Admin, Manager, Staff) to control their access levels across different modules. You can manage invites in the Organization Settings page.",
            ],
        ],
    ],
    'billing' => [
        'label' => 'Billing & Subscriptions',
        'title' => 'Billing & Subscriptions',
        'items' => [
            [
                'question' => 'How do I upgrade my plan?',
                'answer' => 'You can upgrade your plan anytime by navigating to Settings > Billing, or directly visiting the Pricing page. Upgrades take effect immediately and are prorated for the current billing cycle.',
            ],
            [
                'question' => 'What payment methods do you accept?',
                'answer' => 'We accept all major credit cards (Visa, Mastercard, Amex), PayPal, and localized payment methods like bank transfers or e-wallets depending on your region. All payments are processed securely through our payment provider.',
            ],
        ],
    ],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FAQ - Roots</title>
    <meta name="description" content="Dokumentasi dan pertanyaan umum Roots ERP untuk billing, subscription, dan onboarding dashboard.">
    <link rel="icon" href="img/roots_symbol.png?v=<?= filemtime(__DIR__ . '/img/roots_symbol.png'); ?>" type="image/png">
    <link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__ . '/style.css'); ?>">
</head>
<body>
    <main class="page faq-page">
        <header class="nav" aria-label="Navigasi utama">
            <a class="brand" href="index.php" aria-label="Roots home">
                <span class="brand-logo" aria-hidden="true"></span>
            </a>

            <nav class="nav-links" aria-label="Menu">
                <a href="pricing.php">Pricing</a>
                <a class="is-active" href="faq.php">Help</a>
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

        <section class="faq-hero" aria-labelledby="faq-title">
            <div class="faq-kicker">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M4 5.5c2.9-1.1 5.4-.9 8 1v12c-2.6-1.9-5.1-2.1-8-1V5.5Z"></path>
                    <path d="M12 6.5c2.6-1.9 5.1-2.1 8-1v12c-2.9-1.1-5.4-.9-8 1v-12Z"></path>
                </svg>
                DOCUMENTATION &amp; FAQ
            </div>
            <h1 id="faq-title">Frequently Asked Questions</h1>
        </section>

        <section class="faq-layout" aria-label="FAQ content">
            <aside class="faq-sidebar" aria-label="FAQ categories">
                <h2>CATEGORIES</h2>
                <?php foreach ($faqGroups as $key => $group): ?>
                    <button
                        class="faq-category<?= $key === 'getting-started' ? ' is-active' : ''; ?>"
                        type="button"
                        data-faq-tab="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>"
                        aria-pressed="<?= $key === 'getting-started' ? 'true' : 'false'; ?>">
                        <span><?= htmlspecialchars($group['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <i aria-hidden="true"></i>
                    </button>
                <?php endforeach; ?>
            </aside>

            <div class="faq-panels">
                <?php foreach ($faqGroups as $key => $group): ?>
                    <section
                        class="faq-panel<?= $key === 'getting-started' ? ' is-active' : ''; ?>"
                        data-faq-panel="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>"
                        aria-hidden="<?= $key === 'getting-started' ? 'false' : 'true'; ?>">
                        <h2><?= htmlspecialchars($group['title'], ENT_QUOTES, 'UTF-8'); ?></h2>
                        <div class="faq-rule" aria-hidden="true"></div>
                        <?php foreach ($group['items'] as $item): ?>
                            <article class="faq-item">
                                <h3><?= htmlspecialchars($item['question'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                <p><?= htmlspecialchars($item['answer'], ENT_QUOTES, 'UTF-8'); ?></p>
                            </article>
                        <?php endforeach; ?>
                    </section>
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
