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
    <title>Privacy Policy | JobHive</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <style>
        :root {
            --jh-gold: #ffaa2b;
            --jh-gold-2: #ffc107;
            --jh-dark: #151b24;
        }

        html,
        body {
            height: 100%
        }

        html {
            scroll-behavior: smooth
        }

        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background: #f8fafc
        }

        main {
            flex: 1 0 auto
        }

        /* navbar underline hover */
        .navbar-nav .nav-link {
            position: relative;
            padding-bottom: 4px;
            transition: color .2s ease-in-out
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

        /* hero */
        .page-hero {
            background: #0f172a;
            color: #fff;
            padding: 24px 0;
            text-align: center
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
            background: #fff;
            border-radius: 1rem;
            box-shadow: 0 8px 30px rgba(2, 8, 20, .06);
            padding: 1.5rem;
            margin-bottom: 1.25rem;
            border: 1px solid rgba(15, 23, 42, .06)
        }

        .prose p {
            line-height: 1.75;
            color: #334155;
            margin-bottom: 1rem
        }

        .prose h2 {
            color: #0f172a;
            margin-top: 2rem;
            margin-bottom: .75rem
        }

        /* ===== footer (screenshot style) ===== */
        .footer {
            background: #121821;
            color: #e0e6ed;
            padding: 56px 0 12px;
            flex-shrink: 0
        }

        .footer .brand {
            font-weight: 800;
            color: var(--jh-gold);
            font-size: 1.75rem
        }

        .footer .tagline {
            color: #cbd5e1;
            font-size: 1.05rem;
            margin-top: .25rem
        }

        .footer a {
            color: #eaf0f6;
            text-decoration: none
        }

        .footer a:hover {
            color: var(--jh-gold)
        }

        .footer h6 {
            color: #8ea0b5;
            letter-spacing: .02em
        }

        .footer .social a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: #1e2631;
            margin-right: 10px
        }

        .footer .social a:hover {
            background: #273140
        }

        .footer .muted {
            color: #9fb0c3
        }

        .footer hr {
            border-top: 1px solid rgba(255, 255, 255, .08);
            margin: 28px 0 12px
        }

        .footer-bottom {
            color: #9fb0c3
        }

        .footer-bottom .heart {
            color: #e25555
        }
    </style>
</head>

<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold text-warning" href="<?= e($homeUrl) ?>">JobHive</a>
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
                </ul>
            </div>
        </div>
    </nav>

    <main>
        <section class="page-hero">
            <div class="container">
                <h1 class="fw-bold mb-2">Privacy Policy</h1>
                <p class="lead mb-0">Effective date: <?= e(date('F j, Y')) ?></p>
            </div>
        </section>

        <section class="py-4">
            <div class="container content-wrap">
                <div class="section-card prose" id="scope">
                    <h2>1. Scope</h2>
                    <p>This Privacy Policy explains how JobHive collects, uses, and protects your information when you use our website and services, including job searching, applications, and company postings.</p>
                </div>

                <div class="section-card prose" id="collect">
                    <h2>2. Data We Collect</h2>
                    <ul>
                        <li><strong>Account Data:</strong> name, email, password, phone, address, profile fields.</li>
                        <li><strong>Job Data:</strong> resumes, cover letters, application details, job preferences.</li>
                        <li><strong>Company Data:</strong> company name, logo, address, contacts, job posts.</li>
                        <li><strong>Usage Data:</strong> pages viewed, searches, clicks, device/browser info, IP.</li>
                    </ul>
                </div>

                <div class="section-card prose" id="use">
                    <h2>3. How We Use Data</h2>
                    <ul>
                        <li>Operate and improve platform features and performance.</li>
                        <li>Match seekers with jobs; share applications with posting companies.</li>
                        <li>Provide support, send service notices, and prevent fraud or abuse.</li>
                        <li>Enable paid features (e.g., Premium resume) and process related records.</li>
                    </ul>
                </div>

                <div class="section-card prose" id="cookies">
                    <h2>4. Cookies &amp; Tracking</h2>
                    <p>We use cookies and similar technologies to keep you signed in, remember preferences, and understand usage. You can control cookies in your browser; disabling some may affect functionality.</p>
                </div>

                <div class="section-card prose" id="share">
                    <h2>5. Sharing &amp; Disclosure</h2>
                    <ul>
                        <li><strong>With Companies:</strong> we share your applications and relevant profile data with companies you apply to.</li>
                        <li><strong>Service Providers:</strong> hosting, analytics, and security vendors under contractual safeguards.</li>
                        <li><strong>Legal/Protection:</strong> if required by law or to protect rights, safety, or prevent misuse.</li>
                    </ul>
                </div>

                <div class="section-card prose" id="retention">
                    <h2>6. Data Retention</h2>
                    <p>We keep personal data only as long as needed for the purposes above, or as required by law, then delete or anonymize it.</p>
                </div>

                <div class="section-card prose" id="security">
                    <h2>7. Security</h2>
                    <p>We apply technical and organizational measures (encryption where applicable, access controls, monitoring) to protect your data. No method is 100% secure, but we strive to safeguard information.</p>
                </div>

                <div class="section-card prose" id="rights">
                    <h2>8. Your Rights</h2>
                    <p>Depending on your jurisdiction, you may request access, correction, deletion, or portability of your data, and object to or restrict certain processing. Contact us to make a request.</p>
                </div>

                <div class="section-card prose" id="children">
                    <h2>9. Children’s Privacy</h2>
                    <p>JobHive is not directed to children under 16. If you believe a child provided us personal data, contact us and we’ll take appropriate steps.</p>
                </div>

                <div class="section-card prose" id="intl">
                    <h2>10. International Transfers</h2>
                    <p>Your data may be processed in countries different from your own. We use safeguards consistent with applicable law when transferring data.</p>
                </div>

                <div class="section-card prose" id="changes">
                    <h2>11. Changes</h2>
                    <p>We may update this Privacy Policy. The “Effective date” above shows the latest version. Continued use means you accept the updated policy.</p>
                </div>

                <div class="section-card prose" id="contact">
                    <h2>12. Contact</h2>
                    <p>Questions or requests? Email <a href="mailto:support@jobhive.mm">support@jobhive.mm</a>.</p>
                </div>
            </div>
        </section>
    </main>

    <!-- ================= Footer (screenshot style) ================= -->
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
</body>

</html>