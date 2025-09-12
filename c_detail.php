<?php
// c_detail.php  — Company detail (accessible by job seeker or company)
require_once "connect.php";
if (session_status() === PHP_SESSION_NONE) session_start();

/* ===== Auth: allow either job seeker (user) or company ===== */
$logged_user_id    = $_SESSION['user_id']    ?? null;   // job seeker
$logged_company_id = $_SESSION['company_id'] ?? null;   // company

if (!$logged_user_id && !$logged_company_id) {
    header("Location: login.php?next=" . urlencode("c_detail.php" . (isset($_SERVER['QUERY_STRING']) ? "?{$_SERVER['QUERY_STRING']}" : "")));
    exit;
}

/* ===== Helpers ===== */
function e($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function initials_from_name($name): string
{
    $name = trim((string)$name);
    if ($name === '') return 'C';
    $parts = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY);
    $ini = '';
    foreach ($parts as $p) {
        $ini .= mb_strtoupper(mb_substr($p, 0, 1));
        if (mb_strlen($ini) >= 2) break;
    }
    return $ini ?: 'C';
}

function svg_initials_avatar(string $text, int $size = 120): string
{
    $ini = initials_from_name($text);
    $bg = "#ffaa2b";
    $fg = "#fff";
    $fontSize = (int)round($size * 0.4);
    $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="{$size}" height="{$size}">
  <rect width="100%" height="100%" rx="16" ry="16" fill="{$bg}"/>
  <text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle"
        font-family="Arial, sans-serif" font-size="{$fontSize}"
        fill="{$fg}" font-weight="700">{$ini}</text>
</svg>
SVG;
    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

function company_logo_src(?string $filename, string $company_name, string $dir = "company_logos"): string
{
    $file = trim((string)$filename);
    if ($file !== '') {
        $safe = basename($file);
        $web  = rtrim($dir, '/') . '/' . $safe;
        $fs   = __DIR__ . '/' . $web;
        if (is_readable($fs)) return $web;
    }
    return svg_initials_avatar($company_name, 120);
}

/* ===== Determine which company to show =====
   Priority:
   1) ?company_id= in URL (validated)
   2) if a company is logged in and no param -> show its own detail
   3) otherwise (user logged in but no param) -> go to All Companies
*/
$company_id = filter_input(INPUT_GET, 'company_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$company_id && $logged_company_id) {
    $company_id = (int)$logged_company_id;
}
if (!$company_id) {
    header("Location: all_companies.php");
    exit;
}

/* ===== Fetch company ===== */
$stmt = $pdo->prepare("
    SELECT company_id, company_name, email, phone, address, c_detail, logo
    FROM companies
    WHERE company_id = ?
    LIMIT 1
");
$stmt->execute([$company_id]);
$company = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$company) {
    header("Location: all_companies.php");
    exit;
}

/* ===== Navbar bits ===== */
$homeHref = $logged_company_id ? 'company_home.php' : 'user_home.php';
$helloName = $logged_company_id
    ? trim($_SESSION['company_name'] ?? 'Company')
    : trim($_SESSION['full_name'] ?? 'User');
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Company Detail | JobHive</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --bg-primary: #f8f9fa;
            --bg-secondary: #ffffff;
            --bg-tertiary: #f3f4f6;
            --text-primary: #22223b;
            --text-secondary: #495057;
            --text-muted: #6c757d;
            --text-white: #ffffff;
            --border-color: #dee2e6;
            --navbar-bg: #ffffff;
            --navbar-text: #22223b;
            --navbar-border: #dee2e6;
            --card-bg: #ffffff;
            --card-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            --company-header-bg: #ffaa2b;
            --company-header-text: #ffffff;
            --btn-primary-bg: #ffaa2b;
            --btn-primary-text: #22223b;
            --btn-primary-hover: #e6991f;
            --link-normal: #22223b;
        }

        [data-theme="dark"] {
            --bg-primary: #121212;
            --bg-secondary: #1e1e1e;
            --bg-tertiary: #2d2d2d;
            --text-primary: #e9ecef;
            --text-secondary: #ced4da;
            --text-muted: #adb5bd;
            --text-white: #ffffff;
            --border-color: #343a40;
            --navbar-bg: #1e1e1e;
            --navbar-text: #ffffff;
            /* << white text in dark mode */
            --navbar-border: #343a40;
            --card-bg: #1e1e1e;
            --card-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.3);
            --company-header-bg: #e6991f;
            --company-header-text: #ffffff;
            --btn-primary-bg: #ffaa2b;
            --btn-primary-text: #22223b;
            --btn-primary-hover: #e6991f;
            --link-normal: #ffffff;
            /* links show white in dark mode */
        }

        body {
            background-color: var(--bg-primary);
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            color: var(--text-primary);
            transition: background-color .3s, color .3s;
        }

        /* ===== Navbar (exact items required) ===== */
        .navbar {
            background-color: var(--navbar-bg) !important;
            border-bottom: 1px solid var(--navbar-border);
        }

        .navbar-brand {
            font-weight: 700;
            color: var(--btn-primary-bg) !important;
            text-decoration: none !important;
        }

        .navbar-nav .nav-link {
            color: var(--navbar-text) !important;
            position: relative;
            padding-bottom: 4px;
            text-decoration: none !important;
        }

        .navbar-nav .nav-link::after {
            content: "";
            position: absolute;
            left: 0;
            bottom: 0;
            width: 0%;
            height: 2px;
            background: var(--btn-primary-bg);
            transition: width .25s;
        }

        .navbar-nav .nav-link:hover::after,
        .navbar-nav .nav-link.active::after {
            width: 100%;
        }

        .nav-link.disabled {
            opacity: .9;
        }

        .theme-toggle {
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--navbar-text);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .theme-toggle:hover {
            background: var(--bg-tertiary);
        }

        /* ===== Card ===== */
        .company-card {
            background-color: var(--card-bg);
            border: 0;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
        }

        .company-header {
            background: var(--company-header-bg);
            color: var(--company-header-text);
            padding: 2rem;
        }

        .company-logo {
            width: 120px;
            height: 120px;
            border-radius: 16px;
            object-fit: cover;
            background: var(--bg-secondary);
            box-shadow: 0 3px 10px rgba(0, 0, 0, .15);
        }

        .company-body {
            padding: 2rem;
            background-color: var(--card-bg);
        }

        .info-item {
            margin-bottom: 1rem;
            font-size: 15px;
        }

        a {
            color: var(--link-normal);
            font-weight: 600;
        }

        .btn-outline-secondary {
            color: var(--text-primary);
            border-color: var(--border-color);
            background: transparent;
        }

        .btn-outline-secondary:hover {
            background: var(--bg-tertiary);
        }

        /* Force text white in dark mode */
        [data-theme="dark"] h1,
        [data-theme="dark"] h5,
        [data-theme="dark"] small,
        [data-theme="dark"] .info-item,
        [data-theme="dark"] .info-item span,
        [data-theme="dark"] p,
        [data-theme="dark"] a {
            color: #ffffff !important;
        }
    </style>
</head>

<body>
    <!-- NAVBAR: Home, All companies, Hello(...), Logout, Toggle -->
    <nav class="navbar navbar-expand-lg navbar-white shadow-sm sticky-top">
        <div class="container">
            <a class="navbar-brand" href="<?= e($homeHref) ?>">JobHive</a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
                    <li class="nav-item"><a class="nav-link" href="<?= e($homeHref) ?>">Home</a></li>
                    <li class="nav-item"><a class="nav-link active" href="all_companies.php">All companies</a></li>
                    <li class="nav-item"><span class="nav-link disabled">Hello, <?= e($helloName) ?></span></li>
                    <li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>
                    <li class="nav-item">
                        <button class="theme-toggle ms-2" id="themeToggle" aria-label="Toggle theme">
                            <i class="bi bi-sun-fill" id="themeIcon"></i>
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Content -->
    <div class="container my-5">
        <div class="card company-card shadow">
            <div class="company-header d-flex align-items-center gap-4">
                <img class="company-logo" src="<?= e(company_logo_src($company['logo'] ?? '', $company['company_name'] ?? '')) ?>" alt="Logo">
                <div>
                    <h1 class="h3 mb-1"><?= e($company['company_name']) ?></h1>
                    <small style="font-weight:600;"><?= e($company['address'] ?: '—') ?></small>
                </div>
            </div>
            <div class="company-body">
                <div class="info-item"><span class="fw-semibold">Email:</span> <a href="mailto:<?= e($company['email']) ?>"><?= e($company['email']) ?></a></div>
                <div class="info-item"><span class="fw-semibold">Phone:</span> <?= e($company['phone'] ?: '—') ?></div>

                <?php if (!empty($company['c_detail'])): ?>
                    <hr>
                    <h5>About <?= e($company['company_name']) ?></h5>
                    <p><?= nl2br(e($company['c_detail'])) ?></p>
                <?php endif; ?>

                <hr>
                <!-- Back button: only to All Companies (per your request) -->
                <a class="btn btn-outline-secondary btn-sm" href="all_companies.php">&larr; Back to All Companies</a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Theme toggle with proper icon + dark-mode colors
        const themeToggle = document.getElementById('themeToggle');
        const themeIcon = document.getElementById('themeIcon');
        const html = document.documentElement;

        const current = localStorage.getItem('theme') || 'light';
        html.setAttribute('data-theme', current);
        updateIcon(current);

        themeToggle.addEventListener('click', () => {
            const t = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', t);
            localStorage.setItem('theme', t);
            updateIcon(t);
        });

        function updateIcon(t) {
            if (t === 'dark') {
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