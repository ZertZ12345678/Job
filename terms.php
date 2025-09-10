<?php
require_once "connect.php";
if (session_status() === PHP_SESSION_NONE) session_start();
function e($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
/* ========= 3-way return flow ========= */
$company_id = $_SESSION['company_id'] ?? null;
$user_id    = $_SESSION['user_id'] ?? null;
$return     = $_GET['return'] ?? null;
$homeUrl     = 'index.php';
$returnParam = 'index';
if ($return === 'company_home' && $company_id) {
    $homeUrl = 'company_home.php';
    $returnParam = 'company_home';
} elseif ($return === 'user_home' && $user_id) {
    $homeUrl = 'user_home.php?' . http_build_query(['user_id' => $user_id]);
    $returnParam = 'user_home';
} elseif ($return === 'index') {
    $homeUrl = 'index.php';
    $returnParam = 'index';
} else {
    if ($company_id) {
        $homeUrl = 'company_home.php';
        $returnParam = 'company_home';
    } elseif ($user_id) {
        $homeUrl = 'user_home.php?' . http_build_query(['user_id' => $user_id]);
        $returnParam = 'user_home';
    }
}
$aboutUrl   = 'about.php?'   . http_build_query(['return' => $returnParam]);
$faqUrl     = 'faq.php?'     . http_build_query(['return' => $returnParam]);
$termsUrl   = 'terms.php?'   . http_build_query(['return' => $returnParam]);
$privacyUrl = 'privacy.php?' . http_build_query(['return' => $returnParam]);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Terms &amp; Conditions | JobHive</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />
    <style>
        :root {
            --bg-primary: #f8fafc;
            --bg-secondary: #ffffff;
            --bg-tertiary: #f3f4f6;
            --bg-footer: #121821;
            --bg-card: #ffffff;
            --bg-hero: #0f172a;
            --bg-input: #ffffff;
            --text-primary: #22223b;
            --text-secondary: #334155;
            --text-muted: #6c757d;
            --text-white: #ffffff;
            --text-footer: #e0e6ed;
            --text-footer-muted: #9fb0c3;
            --text-footer-link: #eaf0f6;
            --text-footer-heading: #8ea0b5;
            --border-color: #dee2e6;
            --navbar-bg: #ffffff;
            --navbar-text: #22223b;
            --navbar-border: #dee2e6;
            --card-shadow: 0 8px 30px rgba(2, 8, 20, .06);
            --btn-primary-bg: #ffaa2b;
            --btn-primary-text: #22223b;
            --btn-primary-hover: #ffc107;
            --jh-gold: #ffaa2b;
            --jh-gold-2: #ffc107;
            --jh-dark: #151b24;
            --footer-social-bg: #1e2631;
            --footer-social-hover: #273140;
            --footer-hr: rgba(255, 255, 255, .08);
            --heart-color: #e25555;
            --prose-heading: #0f172a;
            --prose-text: #334155;
        }

        [data-theme="dark"] {
            --bg-primary: #121212;
            --bg-secondary: #1e1e1e;
            --bg-tertiary: #2d2d2d;
            --bg-footer: #0d1117;
            --bg-card: #1e1e1e;
            --bg-hero: #0d1117;
            --bg-input: #2d2d2d;
            --text-primary: #ffffff;
            --text-secondary: #ffffff;
            --text-muted: #ffffff;
            --text-white: #ffffff;
            --text-footer: #e0e6ed;
            --text-footer-muted: #9fb0c3;
            --text-footer-link: #eaf0f6;
            --text-footer-heading: #8ea0b5;
            --border-color: #343a40;
            --navbar-bg: #1e1e1e;
            --navbar-text: #ffffff;
            --navbar-border: #343a40;
            --card-shadow: 0 8px 30px rgba(0, 0, 0, 0.3);
            --btn-primary-bg: #ffaa2b;
            --btn-primary-text: #22223b;
            --btn-primary-hover: #ffc107;
            --jh-gold: #ffaa2b;
            --jh-gold-2: #ffc107;
            --jh-dark: #151b24;
            --footer-social-bg: #21262d;
            --footer-social-hover: #30363d;
            --footer-hr: rgba(255, 255, 255, .08);
            --heart-color: #e25555;
            --prose-heading: #ffffff;
            --prose-text: #e0e6ed;
        }

        html,
        body {
            height: 100%;
            background-color: var(--bg-primary);
            color: var(--text-primary);
            transition: background-color 0.3s, color 0.3s;
        }

        html {
            scroll-behavior: smooth
        }

        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        main {
            flex: 1 0 auto
        }

        /* navbar underline hover */
        .navbar {
            background-color: var(--navbar-bg) !important;
            border-bottom: 1px solid var(--navbar-border);
            transition: background-color 0.3s, border-color 0.3s;
        }

        .navbar-brand {
            color: var(--btn-primary-bg) !important;
            transition: color 0.3s;
        }

        .navbar-nav .nav-link {
            position: relative;
            padding-bottom: 4px;
            transition: color .2s ease-in-out;
            color: var(--navbar-text) !important;
        }

        .navbar-nav .nav-link::after {
            content: "";
            position: absolute;
            left: 0;
            bottom: 0;
            height: 2px;
            width: 0%;
            background-color: var(--jh-gold);
            transition: width .25s ease-in-out
        }

        .navbar-nav .nav-link:hover::after {
            width: 100%
        }

        .navbar-toggler {
            border-color: var(--text-primary);
        }

        .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%2834, 34, 59, 0.75%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
        }

        [data-theme="dark"] .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%28255, 255, 255, 0.75%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
        }

        /* hero */
        .page-hero {
            background: var(--bg-hero);
            color: var(--text-white);
            padding: 24px 0;
            text-align: center;
            transition: background-color 0.3s, color 0.3s;
        }

        .page-hero h1 {
            margin: 0 0 .25rem;
            font-size: clamp(22px, 3vw, 34px);
            line-height: 1.2
        }

        .page-hero .lead {
            margin: 0;
            opacity: .9;
            font-size: clamp(14px, 2.2vw, 18px)
        }

        .content-wrap {
            max-width: 900px;
            margin: 0 auto
        }

        .section-card {
            background: var(--bg-card);
            border-radius: 1rem;
            box-shadow: var(--card-shadow);
            padding: 1.5rem;
            margin-bottom: 1.25rem;
            border: 1px solid var(--border-color);
            transition: background-color 0.3s, border-color 0.3s, box-shadow 0.3s;
        }

        .prose p {
            line-height: 1.75;
            color: var(--prose-text);
            margin-bottom: 1rem;
            transition: color 0.3s;
        }

        .prose h2 {
            color: var(--prose-heading);
            margin-top: 2rem;
            margin-bottom: .75rem;
            transition: color 0.3s;
        }

        .prose ul {
            color: var(--prose-text);
            transition: color 0.3s;
        }

        .prose a {
            color: var(--btn-primary-bg);
            text-decoration: none;
            transition: color 0.3s;
        }

        .prose a:hover {
            color: var(--btn-primary-hover);
        }

        /* ===== footer ===== */
        .footer {
            background: var(--bg-footer);
            color: var(--text-footer);
            padding: 56px 0 12px;
            flex-shrink: 0;
            transition: background-color 0.3s, color 0.3s;
        }

        .footer .brand {
            font-weight: 800;
            color: var(--jh-gold);
            font-size: 1.75rem
        }

        .footer .tagline {
            color: var(--text-footer-muted);
            font-size: 1.05rem;
            margin-top: .25rem
        }

        .footer a {
            color: var(--text-footer-link);
            text-decoration: none;
            transition: color 0.3s;
        }

        .footer a:hover {
            color: var(--jh-gold)
        }

        .footer h6 {
            color: var(--text-footer-heading);
            letter-spacing: .02em
        }

        .footer .social a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: var(--footer-social-bg);
            margin-right: 10px;
            transition: background-color 0.3s;
        }

        .footer .social a:hover {
            background: var(--footer-social-hover)
        }

        .footer .muted {
            color: var(--text-footer-muted)
        }

        .footer hr {
            border-top: 1px solid var(--footer-hr);
            margin: 28px 0 12px;
            transition: border-color 0.3s;
        }

        .footer-bottom {
            color: var(--text-footer-muted)
        }

        .footer-bottom .heart {
            color: var(--heart-color)
        }

        /* Theme toggle button */
        .theme-toggle {
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }

        .theme-toggle:hover {
            background: var(--bg-tertiary);
        }
    </style>
</head>

<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="<?= e($homeUrl) ?>">JobHive</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navStatic">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-end" id="navStatic">
                <ul class="navbar-nav">
                    <li class="nav-item"><a class="nav-link" href="<?= e($homeUrl) ?>">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= e($aboutUrl) ?>">About</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= e($faqUrl) ?>">FAQ</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= e($termsUrl) ?>">Terms &amp; Conditions</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= e($privacyUrl) ?>">Privacy Policy</a></li>
                    <!-- Theme Toggle Button -->
                    <li class="nav-item">
                        <button class="theme-toggle ms-3" id="themeToggle" aria-label="Toggle theme">
                            <i class="bi bi-sun-fill" id="themeIcon"></i>
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <main>
        <section class="page-hero">
            <div class="container">
                <h1 class="fw-bold mb-2">Terms &amp; Conditions</h1>
                <p class="lead mb-0">Effective date: <?= e(date('F j, Y')) ?></p>
            </div>
        </section>
        <section class="py-4">
            <div class="container content-wrap">
                <div class="section-card prose" id="acceptance">
                    <h2>1. Acceptance</h2>
                    <p>By accessing or using JobHive, you agree to these Terms. If you do not agree, do not use the platform.</p>
                </div>
                <div class="section-card prose" id="accounts">
                    <h2>2. Accounts</h2>
                    <p>Provide accurate information and keep your credentials secure. You are responsible for activities under your account.</p>
                </div>
                <div class="section-card prose" id="jobs">
                    <h2>3. Job Posts</h2>
                    <p>Companies must post lawful, accurate listings. We may edit, suspend, or remove posts that violate policies or laws.</p>
                </div>
                <div class="section-card prose" id="apps">
                    <h2>4. Applications</h2>
                    <p>Applicants must submit truthful profiles and documents. Submissions may be shared with the posting company.</p>
                </div>
                <div class="section-card prose" id="payments">
                    <h2>5. Payments</h2>
                    <p>Paid features (e.g., premium resumes, job posting fees) are billed as shown. Fees are non-refundable except as required by law.</p>
                </div>
                <div class="section-card prose" id="privacy">
                    <h2>6. Privacy</h2>
                    <p>Your data is handled according to our Privacy Policy. We use personal data to operate and improve JobHive.</p>
                </div>
                <div class="section-card prose" id="conduct">
                    <h2>7. Acceptable Use</h2>
                    <ul>
                        <li>No harassment, spam, or illegal content.</li>
                        <li>No scraping or reverse engineering without permission.</li>
                        <li>No posting of misleading or discriminatory jobs.</li>
                    </ul>
                </div>
                <div class="section-card prose" id="liability">
                    <h2>8. Disclaimer &amp; Liability</h2>
                    <p>JobHive is provided "as is." We do not guarantee hires or uninterrupted availability. To the extent permitted by law, our liability is limited.</p>
                </div>
                <div class="section-card prose" id="termination">
                    <h2>9. Suspension / Termination</h2>
                    <p>We may suspend or terminate accounts that violate these Terms or applicable laws.</p>
                </div>
                <div class="section-card prose" id="changes">
                    <h2>10. Changes to Terms</h2>
                    <p>We may update these Terms. Continued use after changes means you accept the revised terms.</p>
                </div>
                <div class="section-card prose" id="contact">
                    <h2>11. Contact</h2>
                    <p>Questions? Email <a href="mailto:support@jobhive.mm">support@jobhive.mm</a>.</p>
                </div>
            </div>
        </section>
    </main>
    <!-- ================= Footer ================= -->
    <footer class="footer mt-auto">
        <div class="container">
            <div class="row gy-4">
                <!-- Brand + tagline + social -->
                <div class="col-md-4">
                    <div class="brand mb-2">JobHive</div>
                    <div class="tagline">Find jobs. Apply fast. Get hired.</div>
                    <div class="social mt-3">
                        <a href="#" aria-label="Facebook"><i class="bi bi-facebook"></i></a>
                        <a href="#" aria-label="Twitter / X"><i class="bi bi-twitter-x"></i></a>
                        <a href="#" aria-label="LinkedIn"><i class="bi bi-linkedin"></i></a>
                    </div>
                </div>
                <!-- Quick Links -->
                <div class="col-md-3">
                    <h6 class="text-uppercase muted mb-3">Quick Links</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="<?= e($homeUrl) ?>">Home</a></li>
                        <li class="mb-2"><a href="login.php">Login</a></li>
                        <li class="mb-2"><a href="sign_up.php">Register</a></li>
                        <li class="mb-2"><a href="c_sign_up.php">Company Register</a></li>
                        <li class="mb-2"><a href="index_all_companies.php">All Companies</a></li>
                    </ul>
                </div>
                <!-- Contact Links -->
                <div class="col-md-3">
                    <h6 class="text-uppercase muted mb-3">Contact</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="<?= e($faqUrl) ?>">FAQ</a></li>
                        <li class="mb-2"><a href="<?= e($aboutUrl) ?>">About</a></li>
                        <li class="mb-2"><a href="<?= e($privacyUrl) ?>">Privacy Policy</a></li>
                        <li class="mb-2"><a href="<?= e($termsUrl) ?>">Terms &amp; Conditions</a></li>
                    </ul>
                </div>
                <!-- Contact Info -->
                <div class="col-md-2">
                    <h6 class="text-uppercase muted mb-3">Contact</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><i class="bi bi-geo-alt me-2"></i>Yangon, Myanmar</li>
                        <li class="mb-2"><i class="bi bi-envelope me-2"></i>
                            <a href="mailto:support@jobhive.mm">support@jobhive.mm</a>
                        </li>
                        <li class="mb-2"><i class="bi bi-telephone me-2"></i>
                            <a href="tel:+95957433847">+95 957 433 847</a>
                        </li>
                    </ul>
                </div>
            </div>
            <hr>
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center footer-bottom">
                <small>© <?= date('Y') ?> JobHive. All rights reserved.</small>
                <small>Made with <span class="heart">♥</span> in Myanmar</small>
            </div>
        </div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Theme toggle functionality
        const themeToggle = document.getElementById('themeToggle');
        const themeIcon = document.getElementById('themeIcon');
        const html = document.documentElement;

        // Check for saved theme preference or default to light
        const currentTheme = localStorage.getItem('theme') || 'light';
        html.setAttribute('data-theme', currentTheme);
        updateThemeIcon(currentTheme);

        themeToggle.addEventListener('click', () => {
            const theme = html.getAttribute('data-theme');
            const newTheme = theme === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateThemeIcon(newTheme);
        });

        function updateThemeIcon(theme) {
            if (theme === 'dark') {
                themeIcon.classList.remove('bi-sun-fill');
                themeIcon.classList.add('bi-moon-fill');
            } else {
                themeIcon.classList.remove('bi-moon-fill');
                themeIcon.classList.add('bi-sun-fill');
            }
        }
    </script>
</body>

</html>