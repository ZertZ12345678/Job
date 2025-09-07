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

        /* navbar hover underline */
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

        /* ===== footer (matches your screenshot) ===== */
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
                <h1 class="fw-bold mb-2">Frequently Asked Questions</h1>
                <p class="lead mb-0">Quick answers to common questions.</p>
            </div>
        </section>

        <section class="py-4">
            <div class="container content-wrap">
                <div class="section-card">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
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
                    Can’t find your answer? <strong><br>
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

    <script>
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

</html>