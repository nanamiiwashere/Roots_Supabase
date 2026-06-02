<?php
require_once __DIR__ . '/auth/auth-handler.php';
require_once __DIR__ . '/auth/auth3thparty.php';

// Get current user (allow logged-in users to visit landing page)
$currentUser = currentUser();

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

$features = [
    [
        'title' => 'Roots Inventory',
        'text' => 'Effortlessly manage and edit stock inventory to keep your warehouse organized and up-to-date.',
        'visual' => 'inventory',
    ],
    [
        'title' => 'Roots CRM',
        'text' => 'Streamline customer management, edit details, and build stronger engagement with clients.',
        'visual' => 'crm',
    ],
    [
        'title' => 'Roots Finance',
        'text' => 'Monitor cash flow, track and edit transactions, and manage company finances securely.',
        'visual' => 'finance',
    ],
    [
        'title' => 'Roots Analytics',
        'text' => 'Access real-time business reports and insights from your centralized dashboard.',
        'visual' => 'analytics',
    ],
];

function roots_feature_visual(string $name): string
{
    $visuals = [
        'inventory' => '
            <div class="mini-dots"><span></span><span></span><span></span></div>
            <div class="mini-app-icon"><span></span></div>
            <div class="mini-lines top"><span></span><span></span></div>
            <div class="mini-row"><i></i><span></span></div>
        ',
        'crm' => '
            <div class="crm-card crm-card-one"><i></i><span></span><span></span></div>
            <div class="crm-card crm-card-two"><i></i><span></span><span></span></div>
            <div class="crm-check">&#10003;</div>
        ',
        'finance' => '
            <div class="finance-label">BALANCE</div>
            <div class="finance-total">Rp 45M</div>
            <div class="finance-badge">+12%</div>
            <div class="finance-bars"><span></span><span></span><span></span><span></span><span></span><span></span></div>
        ',
        'analytics' => '
            <div class="analytics-panel analytics-panel-left"><span></span><i></i><i></i><i></i></div>
            <div class="analytics-panel analytics-panel-right"><b></b></div>
        ',
    ];

    return $visuals[$name] ?? $visuals['analytics'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home - Roots</title>
    <meta name="description" content="Landing page ERP Roots untuk startup dan bisnis yang ingin menyatukan operasi, data, payroll, inventory, dan analytics.">
    <link rel="icon" href="img/roots_symbol.png?v=<?= filemtime(__DIR__ . '/img/roots_symbol.png'); ?>" type="image/png">
    <link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__ . '/style.css'); ?>">
</head>
<body>
    <main class="page">
        <header class="nav" aria-label="Navigasi utama">
            <a class="brand" href="#" aria-label="Roots home">
                <span class="brand-logo" aria-hidden="true"></span>
            </a>

            <nav class="nav-links" aria-label="Menu">
                <a href="pricing.php">Pricing</a>
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

        <section class="hero" id="top">
            <div class="hero-copy">
                <div class="pill">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M4 15.2c0-2 1.5-3.6 3.5-3.8A5.7 5.7 0 0 1 18.4 9c2.1.3 3.6 2 3.6 4.1 0 2.4-1.9 4.3-4.3 4.3H7.1A3.1 3.1 0 0 1 4 15.2Z"/>
                        <path d="m9 14 2 2 4-5"/>
                    </svg>
                    AI Analysis for Small Startups
                </div>

                <h1>Business<br>operations that scale<br>with your <span class="typewriter" data-words="Startup,Future,Business">Startup</span><i class="cursor">|</i></h1>
                <p>Intuitive ERP built to grow with your business — from inventory to payroll, all in one place. No enterprise complexity, just results.</p>

                <form class="demo-form" id="demoForm" action="submit-demo.php" method="post" novalidate>
                    <label class="sr-only" for="email">Email address</label>
                    <input id="email" name="email" type="email" placeholder="Enter your email address" autocomplete="email" required>
                    <button type="submit">Request Demo</button>
                </form>
                <p class="form-note" id="formNote" role="status" aria-live="polite"></p>
            </div>

            <div class="hero-visual" aria-label="Dashboard analytics Roots">
                <div class="revenue-card">
                    <p>Total Revenue</p>
                    <strong class="count-up" data-prefix="$" data-target="209.10" data-decimals="2" data-comma="true" data-loop="true">$209,10</strong>
                    <div class="chart" aria-hidden="true">
                        <span style="--h:38%"></span>
                        <span style="--h:42%"></span>
                        <span style="--h:55%"></span>
                        <span style="--h:45%"></span>
                        <span style="--h:30%"></span>
                        <span style="--h:62%"></span>
                        <span style="--h:48%"></span>
                        <span style="--h:40%"></span>
                        <span style="--h:16%"></span>
                        <span style="--h:95%"></span>
                    </div>
                    <div class="metric-row">
                        <div>
                            <small>Active Employees</small>
                            <b class="count-up" data-target="28" data-loop="true">28</b>
                            <em>+5 hired this month</em>
                        </div>
                        <div>
                            <small>Utilization</small>
                            <b class="count-up" data-target="75" data-suffix="%" data-loop="true">75%</b>
                            <em>+5% this month</em>
                        </div>
                        <div>
                            <small>Invoices</small>
                            <b class="count-up" data-target="49" data-loop="true">49</b>
                        </div>
                    </div>
                </div>

                <div class="floating-card">
                    <span>Monthly Revenue</span>
                    <strong class="count-up" data-prefix="$" data-target="59.09" data-decimals="2" data-comma="true" data-loop="true">$59,09</strong>
                    <small>↑ 18% vs last month</small>
                </div>
            </div>
        </section>

        <section class="insight-band" aria-labelledby="insight-title">
            <div class="insight-copy">
                <h2 id="insight-title">Unlock the Power of Your <span>Business Data</span></h2>
                <p>Roots is designed to simplify operational complexity so you can focus on what truly matters.</p>
            </div>

            <div class="insight-illustration" aria-hidden="true">
                <div class="screen screen-left">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
                <div class="screen screen-right">
                    <svg viewBox="0 0 160 110">
                        <path d="M8 58c21-31 40 17 61-4s38-51 82 2" fill="none"/>
                        <path d="M10 84h139"/>
                        <path d="M24 72h18M52 65h18M81 52h18M110 36h18"/>
                    </svg>
                </div>
                <div class="magnifier"></div>
                <div class="person">
                    <span class="head"></span>
                    <span class="body"></span>
                    <span class="arm arm-left"></span>
                    <span class="arm arm-right"></span>
                    <span class="leg leg-left"></span>
                    <span class="leg leg-right"></span>
                </div>
                <div class="bars">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </div>
        </section>

        <section class="features" id="pricing" aria-labelledby="features-title">
            <div class="section-kicker">FEATURES</div>
            <h2 id="features-title">Explore Our<br>Complete Range of Solutions</h2>

            <div class="feature-grid">
                <?php foreach ($features as $feature): ?>
                    <article class="feature-card reveal">
                        <div class="feature-visual feature-visual-<?= htmlspecialchars($feature['visual'], ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true">
                            <?= roots_feature_visual($feature['visual']); ?>
                        </div>
                        <h3><?= htmlspecialchars($feature['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                        <p><?= htmlspecialchars($feature['text'], ENT_QUOTES, 'UTF-8'); ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="cta" id="demo">
            <div>
                <h2>Grow Stronger<br>Your Business with Roots.</h2>
            </div>
            <a class="btn btn-cta signup-trigger" href="#signup" aria-controls="signup">Try it free</a>
        </section>

        <footer class="footer" id="help">
            <div class="footer-brand">
                <a class="brand" href="#" aria-label="Roots home">
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
                <a href="#features-title">Features</a>
                <a href="pricing.php">Pricing</a>
                <a href="faq.php">FAQ</a>
            </nav>
            <nav class="footer-column" aria-label="Support">
                <h3>Support</h3>
                <a href="#privacy">Privacy Policy</a>
                <a href="mailto:hello@roots.local">Contact Support</a>
                <a href="#terms">Terms & Conditions</a>
            </nav>
        </footer>
    </main>


    <script src="script.js?v=<?= filemtime(__DIR__ . '/script.js'); ?>"></script>

    <script>
        document.addEventListener("DOMContentLoaded", () => {
            <?php if (!empty($error)): ?>
                <?php if (($_POST['action'] ?? '') === 'login'): ?>
                    const modalLogin = document.getElementById('signin');
                    if (modalLogin) {
                        modalLogin.classList.add('is-open');
                        modalLogin.setAttribute('aria-hidden', 'false');
                        document.body.classList.add('login-open');
                    }
                <?php elseif (($_POST['action'] ?? '') === 'signup'): ?>
                    const modalSignup = document.getElementById('signup');
                    if (modalSignup) {
                        modalSignup.classList.add('is-open');
                        modalSignup.setAttribute('aria-hidden', 'false');
                        document.body.classList.add('login-open');
                    }
                <?php endif; ?>
            <?php endif; ?>
    });
    </script>

</body>
</html>
