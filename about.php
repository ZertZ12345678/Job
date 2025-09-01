<?php
require_once "connect.php";
if (session_status() === PHP_SESSION_NONE) session_start();
function e($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>About Us | JobHive</title>
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

        /* ===== Navbar link underline on hover ===== */
        .navbar-nav .nav-link {
            position: relative;
            padding-bottom: 4px;
            transition: color 0.2s ease-in-out;
        }

        .navbar-nav .nav-link::after {
            content: "";
            position: absolute;
            left: 0;
            bottom: 0;
            width: 0%;
            height: 2px;
            background-color: var(--jh-gold);
            transition: width 0.25s ease-in-out;
        }

        .navbar-nav .nav-link:hover::after {
            width: 100%;
        }

        /* Compact hero */
        .page-hero {
            background: #0f172a;
            color: #fff;
            padding: 24px 0;
            text-align: center;
        }

        .page-hero h1 {
            margin: 0 0 .25rem;
            /* remove extra top/bottom space */
            font-size: clamp(22px, 3vw, 34px);
            /* scale smoothly */
            line-height: 1.2;
        }

        .page-hero .lead {
            margin: 0;
            color: #f8fafc;
            opacity: .9;
            font-size: clamp(14px, 2.2vw, 18px);
        }

        /* tighten section spacing a bit so the page breathes less */
        .content-wrap {
            max-width: 900px;
            margin: 0 auto;
        }

        .section-card {
            padding: 1.25rem;
            margin-bottom: 1rem;
        }


        .content-wrap {
            max-width: 900px;
            margin: 0 auto
        }

        .prose p {
            line-height: 1.75;
            color: #334155;
            margin-bottom: 1rem
        }

        .prose h2,
        .prose h3 {
            color: #0f172a;
            margin-top: 2rem;
            margin-bottom: .75rem
        }

        .section-card {
            background: #fff;
            border-radius: 1rem;
            box-shadow: 0 8px 30px rgba(2, 8, 20, .06);
            padding: 1.5rem;
            margin-bottom: 1.25rem;
            border: 1px solid rgba(15, 23, 42, .06)
        }

        .callout {
            border-left: 4px solid var(--jh-gold-2);
            background: #fff8e1;
            padding: .85rem 1rem;
            border-radius: .5rem
        }

        /* footer */
        .footer {
            background: var(--jh-dark);
            color: #e9ecef;
            padding: 40px 0 16px;
            flex-shrink: 0
        }

        .footer a {
            color: #f8f9fa;
            text-decoration: none
        }

        .footer a:hover {
            color: var(--jh-gold)
        }

        .footer .brand {
            font-weight: 800;
            color: var(--jh-gold)
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
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold text-warning" href="index.php">JobHive</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav1"><span class="navbar-toggler-icon"></span></button>
            <div class="collapse navbar-collapse justify-content-end" id="nav1">
                <ul class="navbar-nav">
                    <li class="nav-item"><a class="nav-link" href="index_all_companies.php">All Companies</a></li>
                    <li class="nav-item"><a class="nav-link" href="faq.php">FAQ</a></li>
                    <li class="nav-item"><a class="nav-link" href="login.php">Login</a></li>
                    <li class="nav-item"><a class="btn btn-warning ms-2 text-white" href="sign_up.php">Register</a></li>
                    <li class="nav-item"><a class="btn btn-outline-warning ms-2" href="c_sign_up.php">Company Register</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <main>
        <section class="page-hero">
            <div class="container">
                <h1 class="fw-bold mb-2">About JobHive</h1>
                <p class="lead mb-0">Helping seekers and companies connect, faster.</p>
            </div>
        </section>

        <section class="py-4">
            <div class="container content-wrap">
                <div class="section-card prose">
                    <h2>Our Mission</h2>
                    <p>JobHive makes it simple to discover roles, apply in seconds, and get hired. We focus on Myanmar’s market with localized features and fast performance.</p>

                    <h2>What We Do</h2>
                    <ul>
                        <li>Smart search by title, company, location, and job type (e.g., Software, Network).</li>
                        <li>Premium resume builder with export to PDF/PNG.</li>
                        <li>Clear application tracking for seekers and companies.</li>
                    </ul>

                    <div class="callout mt-3">
                        <strong>Why JobHive?</strong> We’re lightweight, mobile-first, and built for speed. Less friction, more opportunities.
                    </div>
                </div>

                <div class="section-card">
                    <h2 class="mb-3">Our Journey</h2>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="p-3 border rounded-3 h-100">
                                <div class="text-warning fw-bold">2025</div>
                                <div class="fw-semibold">Premium Resumes</div>
                                <p class="mb-0 text-muted">Resume templates, export, and premium gating launched.</p>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="p-3 border rounded-3 h-100">
                                <div class="text-warning fw-bold">2026</div>
                                <div class="fw-semibold">Expansion</div>
                                <p class="mb-0 text-muted">Broader company partnerships, AI job matching, and mobile app rollout.</p>
                            </div>
                        </div>
                    </div>
                </div>


                <div class="section-card prose">
                    <h2>Contact</h2>
                    <p>Email <a href="https://mail.google.com/mail/?view=cm&fs=1&to=phonethawnaing11305@gmail.com" target="_blank" rel="noopener">
                            phonethawnaing11305@gmail.com
                        </a> or call <a href="tel:+95957433847">+95 957 433 847</a>. We’d love to hear from you.</p>
                </div>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <footer class="footer mt-auto">
        <div class="container">
            <div class="row gy-4">
                <div class="col-md-3">
                    <div class="brand h4 mb-2">JobHive</div>
                    <p class="mb-2">Find jobs. Apply fast. Get hired.</p>
                    <div class="social">
                        <a href="#" aria-label="Facebook"><i class="bi bi-facebook"></i></a>
                        <a href="#" aria-label="Twitter / X"><i class="bi bi-twitter-x"></i></a>
                        <a href="#" aria-label="LinkedIn"><i class="bi bi-linkedin"></i></a>
                    </div>
                </div>
                <div class="col-md-3">
                    <h6 class="text-uppercase text-white-50 mb-3">Quick Links</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="index.php">Home</a></li>
                        <li class="mb-2"><a href="login.php">Login</a></li>
                        <li class="mb-2"><a href="sign_up.php">Register</a></li>
                        <li class="mb-2"><a href="c_sign_up.php">Company Register</a></li>
                        <li class="mb-2"><a href="index_all_companies.php">All Companies</a></li>
                    </ul>
                </div>


                <div class="col-md-3">
                    <br>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="faq.php">FAQ</a></li>
                        <li class="mb-2"><a href="about.php">About Us</a></li>
                        <li class="mb-2"><a href="privacy.php">Privacy Policy</a></li>
                        <li class="mb-2"><a href="terms.php">Terms &amp; Conditions</a></li>

                    </ul>
                </div>



                <div class="col-md-3">
                    <h6 class="text-uppercase text-white-50 mb-3">Contact</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><i class="bi bi-geo-alt me-2"></i>Yangon, Myanmar</li>
                        <li class="mb-2"><i class="bi bi-envelope me-2"></i><a href="https://mail.google.com/mail/?view=cm&fs=1&to=phonethawnaing11305@gmail.com" target="_blank" rel="noopener">
                                phonethawnaing11305@gmail.com
                            </a></li>
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
</body>

</html>