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
    <title>Terms & Conditions | JobHive</title>
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

        .toc a {
            text-decoration: none
        }

        .toc a {
            color: #374151;
            transition: color .2s
        }

        .toc a:hover {
            color: #ff9800
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
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold text-warning" href="index.php">JobHive</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav3"><span class="navbar-toggler-icon"></span></button>
            <div class="collapse navbar-collapse justify-content-end" id="nav3">
                <ul class="navbar-nav">
                    <li class="nav-item"><a class="nav-link" href="about.php">About</a></li>
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
                <h1 class="fw-bold mb-2">Terms &amp; Conditions</h1>
                <p class="lead mb-0">Effective date: <?= e(date('F j, Y')) ?></p>
            </div>
        </section>

        <section class="py-4">
            <div class="container content-wrap">
                <div class="section-card">
                    <h5 class="mb-3">Contents</h5>
                    <div class="row toc">
                        <div class="col-6 col-md-3"><a href="#acceptance">1. Acceptance</a></div>
                        <div class="col-6 col-md-3"><a href="#accounts">2. Accounts</a></div>
                        <div class="col-6 col-md-3"><a href="#jobs">3. Job Posts</a></div>
                        <div class="col-6 col-md-3"><a href="#apps">4. Applications</a></div>
                        <div class="col-6 col-md-3"><a href="#payments">5. Payments</a></div>
                        <div class="col-6 col-md-3"><a href="#privacy">6. Privacy</a></div>
                        <div class="col-6 col-md-3"><a href="#conduct">7. Conduct</a></div>
                        <div class="col-6 col-md-3"><a href="#liability">8. Liability</a></div>
                        <div class="col-6 col-md-3"><a href="#termination">9. Termination</a></div>
                        <div class="col-6 col-md-3"><a href="#changes">10. Changes</a></div>
                        <div class="col-6 col-md-3"><a href="#contact">11. Contact</a></div>
                    </div>
                </div>

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
                    <p>Your data is handled according to our Privacy Policy (see this page). We use personal data to operate and improve JobHive.</p>
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
                    <p>JobHive is provided “as is.” We do not guarantee hires or uninterrupted availability. To the extent permitted by law, our liability is limited.</p>
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
                    <p>Questions? Email <a href="https://mail.google.com/mail/?view=cm&fs=1&to=phonethawnaing11305@gmail.com" target="_blank" rel="noopener">
                            phonethawnaing11305@gmail.com
                        </a>.</p>
                </div>

                <div class="section-card">
                    Tip: Use the <strong>table of contents</strong> above—smooth scroll will take you to any section.
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
                        <li class="mb-2"><a href="pri">Privacy Policy</a></li>
                        <li class="mb-2"><a href="terms.php">Terms &amp; Conditions</a></li>

                    </ul>
                </div>



                <div class="col-md-3">
                    <h6 class="text-uppercase text-white-50 mb-3">Contact</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><i class="bi bi-geo-alt me-2"></i>Yangon, Myanmar</li>
                        <li class="mb-2"><i class="bi bi-envelope me-2"></i> <a href="https://mail.google.com/mail/?view=cm&fs=1&to=phonethawnaing11305@gmail.com" target="_blank" rel="noopener">
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