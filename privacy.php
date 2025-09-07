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
$faqUrl     = "faq.php?"     . http_build_query(['return' => $returnParam]);
$termsUrl   = "terms.php?"   . http_build_query(['return' => $returnParam]);
$privacyUrl = "privacy.php?" . http_build_query(['return' => $returnParam]); // self
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
            --jh-ink: #0f172a;
            --jh-sub: #334155;
        }

        html {
            scroll-behavior: smooth
        }

        body {
            background: #f8fafc
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

        .pp-hero {
            background: var(--jh-ink);
            color: #fff;
            padding: 28px 0;
            text-align: center
        }

        .pp-hero h1 {
            margin: 0 0 .25rem;
            font-size: clamp(22px, 3vw, 34px);
            line-height: 1.2
        }

        .pp-hero .lead {
            margin: 0;
            opacity: .9;
            font-size: clamp(14px, 2.2vw, 18px)
        }

        .pp-wrap {
            max-width: 1200px;
            margin: 0 auto
        }

        .pp-main {
            padding: 24px 0 40px
        }

        .pp-aside {
            position: sticky;
            top: 84px
        }

        .pp-card {
            background: #fff;
            border: 1px solid rgba(15, 23, 42, .08);
            border-radius: 1rem;
            box-shadow: 0 8px 28px rgba(2, 8, 20, .06)
        }

        .pp-toc {
            list-style: none;
            padding: 10px 12px;
            margin: 0
        }

        .pp-toc li {
            margin: 2px 0
        }

        .pp-toc a {
            display: flex;
            gap: 10px;
            align-items: center;
            padding: 8px 10px;
            border-radius: 10px;
            color: #0f172a;
            text-decoration: none;
            font-weight: 500;
            border: 1px solid transparent
        }

        .pp-toc a .pp-idx {
            min-width: 28px;
            height: 28px;
            border-radius: 999px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f1f5f9;
            font-size: .85rem
        }

        .pp-toc a:hover {
            background: #fff8ec;
            border-color: #ffd48a;
            color: #b45309
        }

        .pp-toc a.active {
            background: #fff4df;
            border-color: #ffc76a;
            color: #a16207
        }

        .pp-toc-toggle {
            display: none
        }

        @media (max-width: 991.98px) {
            .pp-aside {
                position: static
            }

            .pp-toc-toggle {
                display: flex;
                align-items: center;
                gap: 8px
            }

            .pp-aside .pp-card {
                display: none
            }

            .pp-aside.show .pp-card {
                display: block
            }
        }

        .pp-prose {
            color: var(--jh-sub);
            line-height: 1.75
        }

        .pp-prose h2 {
            color: #0f172a;
            margin: 0 0 .5rem;
            display: flex;
            align-items: center;
            gap: .5rem
        }

        .pp-kicker {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 999px;
            background: #fff4df;
            color: #b45309;
            font-weight: 700;
            font-size: .9rem;
            border: 1px solid #ffd48a
        }

        .pp-section {
            padding: 18px;
            margin-bottom: 16px
        }

        .pp-callout {
            border-left: 4px solid var(--jh-gold);
            background: #fff8e1;
            padding: .85rem 1rem;
            border-radius: .75rem
        }

        .footer {
            background: #0f172a;
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
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navP"><span class="navbar-toggler-icon"></span></button>
            <div class="collapse navbar-collapse justify-content-end" id="navP">
                <ul class="navbar-nav">
                    <li class="nav-item"><a class="nav-link" href="<?= e($homeUrl) ?>">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= e($aboutUrl) ?>">About</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= e($faqUrl) ?>">FAQ</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= e($termsUrl) ?>">Terms &amp; Conditions</a></li>
                    <li class="nav-item"><a class="nav-link active" href="<?= e($privacyUrl) ?>">Privacy Policy</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <main>
        <section class="pp-hero">
            <div class="container">
                <h1 class="fw-bold">Privacy Policy</h1>
                <p class="lead mb-0">Effective date: <?= e(date('F j, Y')) ?></p>
            </div>
        </section>

        <section class="pp-main">
            <div class="container pp-wrap">
                <div class="row g-4">
                    <!-- Sidebar -->
                    <aside class="col-lg-4">
                        <button class="btn btn-outline-warning w-100 pp-toc-toggle mb-2" type="button">
                            <i class="bi bi-list"></i> Contents
                        </button>
                        <div class="pp-aside pp-card">
                            <ul id="ppToc" class="pp-toc">
                                <li><a href="#scope"><span class="pp-idx">1</span> Scope</a></li>
                                <li><a href="#collect"><span class="pp-idx">2</span> Data We Collect</a></li>
                                <li><a href="#use"><span class="pp-idx">3</span> How We Use Data</a></li>
                                <li><a href="#cookies"><span class="pp-idx">4</span> Cookies & Tracking</a></li>
                                <li><a href="#share"><span class="pp-idx">5</span> Sharing & Disclosure</a></li>
                                <li><a href="#retention"><span class="pp-idx">6</span> Data Retention</a></li>
                                <li><a href="#security"><span class="pp-idx">7</span> Security</a></li>
                                <li><a href="#rights"><span class="pp-idx">8</span> Your Rights</a></li>
                                <li><a href="#children"><span class="pp-idx">9</span> Children’s Privacy</a></li>
                                <li><a href="#intl"><span class="pp-idx">10</span> International Transfers</a></li>
                                <li><a href="#changes"><span class="pp-idx">11</span> Changes</a></li>
                                <li><a href="#contact"><span class="pp-idx">12</span> Contact</a></li>
                            </ul>
                        </div>
                    </aside>

                    <!-- Content -->
                    <div class="col-lg-8">
                        <div class="pp-card pp-section pp-prose" id="scope">
                            <h2><span class="pp-kicker">1</span> Scope</h2>
                            <p>This Privacy Policy explains how JobHive collects, uses, and protects your information when you use our website and services, including job searching, applications, and company postings.</p>
                        </div>

                        <div class="pp-card pp-section pp-prose" id="collect">
                            <h2><span class="pp-kicker">2</span> Data We Collect</h2>
                            <ul>
                                <li><strong>Account Data:</strong> name, email, password, phone, address, profile fields.</li>
                                <li><strong>Job Data:</strong> resumes, cover letters, application details, job preferences.</li>
                                <li><strong>Company Data:</strong> company name, logo, address, contacts, job posts.</li>
                                <li><strong>Usage Data:</strong> pages viewed, searches, clicks, device/browser info, IP (for security and analytics).</li>
                            </ul>
                        </div>

                        <div class="pp-card pp-section pp-prose" id="use">
                            <h2><span class="pp-kicker">3</span> How We Use Data</h2>
                            <ul>
                                <li>Operate and improve platform features and performance.</li>
                                <li>Match seekers with jobs; share applications with posting companies.</li>
                                <li>Provide support, send service notices, and prevent fraud or abuse.</li>
                                <li>Enable paid features (e.g., Premium resume) and process related records.</li>
                            </ul>
                        </div>

                        <div class="pp-card pp-section pp-prose" id="cookies">
                            <h2><span class="pp-kicker">4</span> Cookies &amp; Tracking</h2>
                            <p>We use cookies and similar technologies to keep you signed in, remember preferences, and understand usage. You can control cookies in your browser; disabling some may affect functionality.</p>
                        </div>

                        <div class="pp-card pp-section pp-prose" id="share">
                            <h2><span class="pp-kicker">5</span> Sharing &amp; Disclosure</h2>
                            <ul>
                                <li><strong>With Companies:</strong> we share your applications and relevant profile data with companies you apply to.</li>
                                <li><strong>Service Providers:</strong> hosting, analytics, and security vendors under contractual safeguards.</li>
                                <li><strong>Legal/Protection:</strong> if required by law or to protect rights, safety, or prevent misuse.</li>
                            </ul>
                        </div>

                        <div class="pp-card pp-section pp-prose" id="retention">
                            <h2><span class="pp-kicker">6</span> Data Retention</h2>
                            <p>We keep personal data only as long as needed for the purposes above, or as required by law, then delete or anonymize it.</p>
                        </div>

                        <div class="pp-card pp-section pp-prose" id="security">
                            <h2><span class="pp-kicker">7</span> Security</h2>
                            <p>We apply technical and organizational measures (encryption where applicable, access controls, monitoring) to protect your data. No method is 100% secure, but we strive to safeguard information.</p>
                        </div>

                        <div class="pp-card pp-section pp-prose" id="rights">
                            <h2><span class="pp-kicker">8</span> Your Rights</h2>
                            <p>Depending on your jurisdiction, you may request access, correction, deletion, or portability of your data, and object to or restrict certain processing. Contact us to make a request.</p>
                        </div>

                        <div class="pp-card pp-section pp-prose" id="children">
                            <h2><span class="pp-kicker">9</span> Children’s Privacy</h2>
                            <p>JobHive is not directed to children under 16. If you believe a child provided us personal data, contact us and we’ll take appropriate steps.</p>
                        </div>

                        <div class="pp-card pp-section pp-prose" id="intl">
                            <h2><span class="pp-kicker">10</span> International Transfers</h2>
                            <p>Your data may be processed in countries different from your own. We use safeguards consistent with applicable law when transferring data.</p>
                        </div>

                        <div class="pp-card pp-section pp-prose" id="changes">
                            <h2><span class="pp-kicker">11</span> Changes</h2>
                            <p>We may update this Privacy Policy. The “Effective date” above shows the latest version. Continued use means you accept the updated policy.</p>
                        </div>

                        <div class="pp-card pp-section pp-prose" id="contact">
                            <h2><span class="pp-kicker">12</span> Contact</h2>
                            <p>Questions or requests? Email
                                <a href="https://mail.google.com/mail/?view=cm&fs=1&to=phonethawnaing11305@gmail.com" target="_blank" rel="noopener">
                                    phonethawnaing11305@gmail.com
                                </a>.
                            </p>
                            <div class="pp-callout mt-3">Tip: Use the <strong>Contents</strong> on the left to jump to any section.</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- back to top -->
        <button id="ppTop" class="btn btn-warning rounded-circle pp-top" style="position:fixed;right:16px;bottom:18px;display:none">
            <i class="bi bi-arrow-up-short fs-4 text-white"></i>
        </button>
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
        // Mobile TOC toggle
        document.querySelector('.pp-toc-toggle')?.addEventListener('click', () => {
            document.querySelector('.pp-aside')?.classList.toggle('show');
        });
        // Active link highlight
        const links = Array.from(document.querySelectorAll('#ppToc a'));
        const sections = links.map(a => document.querySelector(a.getAttribute('href')));
        const markActive = () => {
            let idx = 0;
            sections.forEach((sec, i) => {
                if (sec && sec.getBoundingClientRect().top <= 120) idx = i;
            });
            links.forEach(l => l.classList.remove('active'));
            links[idx]?.classList.add('active');
        };
        document.addEventListener('scroll', markActive, {
            passive: true
        });
        markActive();
        // Back to top
        const topBtn = document.getElementById('ppTop');
        document.addEventListener('scroll', () => {
            if (window.scrollY > 300) topBtn.style.display = 'block';
            else topBtn.style.display = 'none';
        }, {
            passive: true
        });
        topBtn?.addEventListener('click', () => window.scrollTo({
            top: 0,
            behavior: 'smooth'
        }));
    </script>
</body>

</html>