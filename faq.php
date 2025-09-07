<?php
require_once "connect.php";
if (session_status() === PHP_SESSION_NONE) session_start();
function e($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

/* Dynamic Home URL (same rules) */
$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
$homeUrl = "index.php";
$return  = $_GET['return'] ?? null;

if ($return === 'index') {
    $homeUrl = "index.php";
} elseif ($return === 'user_home' && $user_id) {
    $homeUrl = "user_home.php?" . http_build_query(['user_id' => $user_id]);
} else {
    $homeUrl = $user_id ? "user_home.php?" . http_build_query(['user_id' => $user_id]) : "index.php";
}

$returnParam = ($return === 'index' || $return === 'user_home') ? $return : ($user_id ? 'user_home' : 'index');
$aboutUrl   = "about.php?"   . http_build_query(['return' => $returnParam]);
$faqUrl     = "faq.php?"     . http_build_query(['return' => $returnParam]); // self
$termsUrl   = "terms.php?"   . http_build_query(['return' => $returnParam]);
$privacyUrl = "privacy.php?" . http_build_query(['return' => $returnParam]);
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
            --jh-dark: #1a202c;
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

        .navbar-nav .nav-link {
            position: relative;
            padding-bottom: 4px;
            transition: color .2s
        }

        .navbar-nav .nav-link::after {
            content: "";
            position: absolute;
            left: 0;
            bottom: 0;
            width: 0;
            height: 2px;
            background-color: var(--jh-gold);
            transition: width .25s
        }

        .navbar-nav .nav-link:hover::after {
            width: 100%
        }

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
            color: #f8fafc;
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

        .footer {
            background: var(--jh-dark);
            color: #e9ecef;
            padding: 40px 0 16px
        }

        .footer a {
            color: #f8f9fa;
            text-decoration: none
        }

        .footer a:hover {
            color: #ffaa2b
        }

        .footer .brand {
            font-weight: 800;
            color: #ffaa2b
        }

        .footer .social a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: rgba(255, 255, 255, .08);
            margin-right: 8px
        }

        .footer .social a:hover {
            background: rgba(255, 193, 7, .2)
        }

        .footer hr {
            border-top: 1px solid rgba(255, 255, 255, .12);
            margin: 24px 0 12px
        }

        .footer small {
            color: #cbd5e1
        }
    </style>
</head>

<body>
    <!-- Navbar (only required items) -->
    <nav class="navbar navbar-expand-lg bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold text-warning" href="<?= e($homeUrl) ?>">JobHive</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navF"><span class="navbar-toggler-icon"></span></button>
            <div class="collapse navbar-collapse justify-content-end" id="navF">
                <ul class="navbar-nav">
                    <li class="nav-item"><a class="nav-link" href="<?= e($homeUrl) ?>">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= e($aboutUrl) ?>">About</a></li>
                    <li class="nav-item"><a class="nav-link active" href="<?= e($faqUrl) ?>">FAQ</a></li>
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
                                    Login (or register), open a job, and click <strong>Apply</strong>. Premium users can use the <em>Premium Resume</em> page for nicer exports.
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
                                    Premium users unlock professional templates with color themes and PDF/PNG export. Access via <strong>resume_premium.php</strong>.
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
                                    See <a href="<?= e($termsUrl) ?>">Terms &amp; Conditions</a>. We keep your data secure and use it only to run the platform.
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
                                    Go to your dashboard, click <strong>Upgrade</strong>, and follow the payment instructions. Once confirmed, Premium features are unlocked instantly.
                                </div>
                            </div>
                        </div>

                        <!-- Q7 -->
                        <div class="accordion-item" data-tags="company logo profile update">
                            <h2 class="accordion-header" id="q8h">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#q8">How can companies update their profile/logo?</button>
                            </h2>
                            <div id="q8" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Companies can log in, go to <strong>Company Profile</strong>, and upload a new logo or update their details at any time.
                                </div>
                            </div>
                        </div>

                        <!-- Q8 -->
                        <div class="accordion-item" data-tags="jobs inactive closed deadline">
                            <h2 class="accordion-header" id="q10h">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#q10">Why do some jobs show as Inactive or Closed?</button>
                            </h2>
                            <div id="q10" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Jobs automatically close when their deadline passes or if the company deactivates them. Only <em>Active</em> jobs can accept applications.
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="section-card mt-3">
                        Can’t find your answer? <strong><br>
                            Contact Admin Email: <a href="https://mail.google.com/mail/?view=cm&fs=1&to=phonethawnaing11305@gmail.com" target="_blank" rel="noopener">phonethawnaing11305@gmail.com</a></strong>.
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Footer (unchanged) -->
    <footer class="footer mt-auto">
        <div class="container">
            <div class="row gy-4">
                <div class="col-md-3">
                    <div class="brand h4 mb-2">JobHive</div>
                    <p class="mb-2">Find jobs. Apply fast. Get hired.</p>
                    <div class="social">
                        <a href="#"><i class="bi bi-facebook"></i></a>
                        <a href="#"><i class="bi bi-twitter-x"></i></a>
                        <a href="#"><i class="bi bi-linkedin"></i></a>
                    </div>
                </div>
                <div class="col-md-3">
                    <h6 class="text-uppercase text-white-50 mb-3">Quick Links</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="<?= e($homeUrl) ?>">Home</a></li>
                        <li class="mb-2"><a href="<?= e($aboutUrl) ?>">About</a></li>
                        <li class="mb-2"><a href="<?= e($faqUrl) ?>">FAQ</a></li>
                        <li class="mb-2"><a href="<?= e($privacyUrl) ?>">Privacy Policy</a></li>
                        <li class="mb-2"><a href="<?= e($termsUrl) ?>">Terms &amp; Conditions</a></li>
                    </ul>
                </div>
                <div class="col-md-3"><br></div>
                <div class="col-md-3">
                    <h6 class="text-uppercase text-white-50 mb-3">Contact</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><i class="bi bi-geo-alt me-2"></i>Yangon, Myanmar</li>
                        <li class="mb-2"><i class="bi bi-envelope me-2"></i><a href="https://mail.google.com/mail/?view=cm&fs=1&to=phonethawnaing11305@gmail.com" target="_blank" rel="noopener">phonethawnaing11305@gmail.com</a></li>
                        <li class="mb-2"><i class="bi bi-telephone me-2"></i><a href="tel:+95957433847">+95 957 433 847</a></li>
                    </ul>
                </div>
            </div>
            <hr>
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center">
                <small>&copy; <?= date('Y') ?> JobHive. All rights reserved.</small>
                <small>Made with <span style="color:#e25555;">♥</span> in Myanmar</small>
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