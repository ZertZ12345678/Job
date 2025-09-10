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
    <title>FAQ | JobHive</title>
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
            --bg-accordion: #ffffff;
            --bg-hero: #0f172a;
            --bg-input: #ffffff;
            --text-primary: #22223b;
            --text-secondary: #495057;
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
        }

        [data-theme="dark"] {
            --bg-primary: #121212;
            --bg-secondary: #1e1e1e;
            --bg-tertiary: #2d2d2d;
            --bg-footer: #0d1117;
            --bg-card: #1e1e1e;
            --bg-accordion: #2d2d2d;
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

        /* navbar hover underline */
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

        .input-group {
            background-color: var(--bg-card);
            transition: background-color 0.3s;
        }

        .input-group-text {
            background-color: var(--bg-input);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            transition: background-color 0.3s, border-color 0.3s, color 0.3s;
        }

        .form-control {
            background-color: var(--bg-input);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            transition: background-color 0.3s, border-color 0.3s, color 0.3s;
        }

        .form-control:focus {
            background-color: var(--bg-input);
            border-color: var(--btn-primary-bg);
            color: var(--text-primary);
        }

        .accordion {
            --bs-accordion-bg: var(--bg-accordion);
            --bs-accordion-color: var(--text-primary);
            --bs-accordion-border-color: var(--border-color);
            --bs-accordion-btn-bg: var(--bg-accordion);
            --bs-accordion-btn-color: var(--text-primary);
            --bs-accordion-btn-icon: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%2322223b'%3e%3cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3e%3c/svg%3e");
            --bs-accordion-btn-active-icon: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%2322223b'%3e%3cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3e%3c/svg%3e");
            --bs-accordion-btn-focus-border-color: var(--btn-primary-bg);
            --bs-accordion-btn-focus-box-shadow: 0 0 0 0.25rem rgba(255, 170, 43, 0.25);
            --bs-accordion-body-padding-x: 1rem;
            --bs-accordion-body-padding-y: 1rem;
            --bs-accordion-active-color: var(--text-primary);
            --bs-accordion-active-bg: var(--bg-accordion);
        }

        [data-theme="dark"] .accordion {
            --bs-accordion-btn-icon: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23ffffff'%3e%3cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3e%3c/svg%3e");
            --bs-accordion-btn-active-icon: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23ffffff'%3e%3cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3e%3c/svg%3e");
        }

        .accordion-button:not(.collapsed) {
            color: var(--btn-primary-bg);
            background-color: var(--bg-accordion);
            box-shadow: inset 0 -1px 0 var(--border-color);
        }

        .accordion-button:focus {
            box-shadow: 0 0 0 0.25rem rgba(255, 170, 43, 0.25);
        }

        .accordion-body a {
            color: var(--btn-primary-bg);
            text-decoration: none;
            transition: color 0.3s;
        }

        .accordion-body a:hover {
            color: var(--btn-primary-hover);
        }

        /* ===== footer (matches your screenshot) ===== */
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
                <h1 class="fw-bold mb-2">Frequently Asked Questions</h1>
                <p class="lead mb-0">Quick answers to common questions.</p>
            </div>
        </section>
        <section class="py-4">
            <div class="container content-wrap">
                <div class="section-card">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input id="faqSearch" type="search" class="form-control" placeholder="Search FAQs (e.g., resume, premium, apply)">
                    </div>
                </div>
                <div class="section-card">
                    <div class="accordion" id="faqAccordion">
                        <!-- Q1 -->
                        <div class="accordion-item" data-tags="apply application login">
                            <h2 class="accordion-header" id="q1h">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#q1">How do I apply for a job?</button>
                            </h2>
                            <div id="q1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Login (or register), open a job, and click <strong>Apply</strong>.
                                </div>
                            </div>
                        </div>
                        <!-- Q2 -->
                        <div class="accordion-item" data-tags="resume premium pdf png">
                            <h2 class="accordion-header" id="q2h">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#q2">What is the Premium Resume feature?</button>
                            </h2>
                            <div id="q2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Premium users unlock professional templates with color themes and PDF/PNG export. See <a href="<?= e($termsUrl) ?>">Terms</a>.
                                </div>
                            </div>
                        </div>
                        <!-- Q3 -->
                        <div class="accordion-item" data-tags="company posting payment status">
                            <h2 class="accordion-header" id="q3h">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#q3">How do companies post jobs?</button>
                            </h2>
                            <div id="q3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Companies register, complete payment (if required), and create a job post. Status appears as <em>Active</em> when approved.
                                </div>
                            </div>
                        </div>
                        <!-- Q4 -->
                        <div class="accordion-item" data-tags="profile completion percentage">
                            <h2 class="accordion-header" id="q4h">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#q4">Why complete my profile?</button>
                            </h2>
                            <div id="q4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    A complete profile improves matches and speeds up applications. Fill out contact info, position, and upload a photo.
                                </div>
                            </div>
                        </div>
                        <!-- Q5 -->
                        <div class="accordion-item" data-tags="privacy terms security policy">
                            <h2 class="accordion-header" id="q5h">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#q5">Where can I read your Terms & Privacy?</button>
                            </h2>
                            <div id="q5" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    See <a href="<?= e($termsUrl) ?>">Terms &amp; Conditions</a> and <a href="<?= e($privacyUrl) ?>">Privacy Policy</a>.
                                </div>
                            </div>
                        </div>
                        <!-- Q6 -->
                        <div class="accordion-item" data-tags="premium upgrade payment">
                            <h2 class="accordion-header" id="q6h">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#q6">How do I upgrade to Premium?</button>
                            </h2>
                            <div id="q6" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Go to your dashboard, click <strong>Upgrade</strong>, and follow the payment steps.
                                </div>
                            </div>
                        </div>
                        <!-- Q7 -->
                        <div class="accordion-item" data-tags="company logo profile update">
                            <h2 class="accordion-header" id="q7h">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#q7">How can companies update their profile/logo?</button>
                            </h2>
                            <div id="q7" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Companies can log in, go to <strong>Company Profile</strong>, and upload a new logo or update their details.
                                </div>
                            </div>
                        </div>
                        <!-- Q8 -->
                        <div class="accordion-item" data-tags="jobs inactive closed deadline">
                            <h2 class="accordion-header" id="q8h">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#q8">Why do some jobs show as Inactive or Closed?</button>
                            </h2>
                            <div id="q8" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Jobs automatically close when their deadline passes or if the company deactivates them.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="section-card">
                    Can't find your answer? <strong><br>
                        Contact Admin Email: <a href="mailto:phonethawnaing11305@gmail.com">phonethawnaing11305@gmail.com</a></strong>.
                </div>
            </div>
        </section>
    </main>
    <!-- ================= Footer (like your screenshot) ================= -->
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

        // Simple client-side FAQ search
        const q = document.getElementById('faqSearch');
        const items = Array.from(document.querySelectorAll('#faqAccordion .accordion-item'));
        q?.addEventListener('input', () => {
            const term = (q.value || '').toLowerCase();
            items.forEach(it => {
                const tags = (it.getAttribute('data-tags') || '').toLowerCase();
                const text = it.innerText.toLowerCase();
                const show = term === '' || tags.includes(term) || text.includes(term);
                it.style.display = show ? '' : 'none';
            });
        });
    </script>
</body>

</html>DF