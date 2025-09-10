<?php
// index_c_detail.php  (public company detail)
require_once "connect.php";
if (session_status() === PHP_SESSION_NONE) session_start();
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
/* ===== Robust company_id parsing ===== */
$company_id = null;
$cid = filter_input(INPUT_GET, 'company_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($cid) $company_id = $cid;
if ($company_id === null) {
    $cid2 = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($cid2) $company_id = $cid2;
}
if ($company_id === null && !empty($_SERVER['PATH_INFO'])) {
    if (preg_match('~/(\d+)$~', $_SERVER['PATH_INFO'], $m)) $company_id = (int)$m[1];
}
/* ===== If still missing, go back to list ===== */
if (!$company_id) {
    header("Location: index_all_companies.php");
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
    header("Location: index_all_companies.php");
    exit;
}
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
        }

        [data-theme="dark"] {
            --bg-primary: #121212;
            --bg-secondary: #1e1e1e;
            --bg-tertiary: #2d2d2d;
            --text-primary: #e9ecef;
            --text-secondary: #adb5bd;
            --text-muted: #6c757d;
            --text-white: #ffffff;
            --border-color: #343a40;
            --navbar-bg: #1e1e1e;
            --navbar-text: #e9ecef;
            --navbar-border: #343a40;
            --card-bg: #1e1e1e;
            --card-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.3);
            --company-header-bg: #e6991f;
            --company-header-text: #ffffff;
            --btn-primary-bg: #ffaa2b;
            --btn-primary-text: #22223b;
            --btn-primary-hover: #e6991f;
        }

        body {
            background-color: var(--bg-primary);
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            color: var(--text-primary);
            transition: background-color 0.3s, color 0.3s;
        }

        /* ===== Navbar brand (JobHive logo) ===== */
        .navbar {
            background-color: var(--navbar-bg) !important;
            border-bottom: 1px solid var(--navbar-border);
            transition: background-color 0.3s, border-color 0.3s;
        }

        .navbar-brand {
            font-weight: 700;
            color: var(--btn-primary-bg) !important;
            text-decoration: none !important;
        }

        .navbar-brand:hover {
            color: var(--btn-primary-bg) !important;
            text-decoration: none !important;
        }

        /* ===== Navbar links ===== */
        .navbar-nav .nav-item:not(.dropdown) .nav-link {
            position: relative;
            padding-bottom: 4px;
            transition: color 0.2s ease-in-out;
            text-decoration: none !important;
            color: var(--navbar-text) !important;
        }

        /* yellow underline effect */
        .navbar-nav .nav-item:not(.dropdown) .nav-link::after {
            content: "";
            position: absolute;
            left: 0;
            bottom: 0;
            width: 0%;
            height: 2px;
            background-color: var(--btn-primary-bg);
            transition: width 0.25s ease-in-out;
        }

        /* expand underline on hover OR when active */
        .navbar-nav .nav-item:not(.dropdown) .nav-link:hover::after,
        .navbar-nav .nav-item:not(.dropdown) .nav-link.active::after {
            width: 100%;
        }

        /* active link stays yellow */
        .navbar-nav .nav-item:not(.dropdown) .nav-link.active {
            font-weight: bold;
            color: var(--btn-primary-bg) !important;
        }

        .company-card {
            background-color: var(--card-bg);
            border: 0;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            transition: background-color 0.3s, box-shadow 0.3s;
        }

        .company-header {
            background: var(--company-header-bg);
            color: var(--company-header-text);
            padding: 2rem;
            transition: background-color 0.3s;
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
            transition: background-color 0.3s;
        }

        h1,
        h5 {
            color: var(--text-primary);
            font-weight: 700;
            transition: color 0.3s;
        }

        .info-item {
            margin-bottom: 1rem;
            font-size: 15px;
            color: var(--text-primary);
            transition: color 0.3s;
        }

        .info-item span {
            font-weight: 700;
            color: var(--text-primary);
            transition: color 0.3s;
        }

        p {
            color: var(--text-primary);
            font-weight: 500;
            line-height: 1.6;
            transition: color 0.3s;
        }

        a {
            color: var(--text-primary);
            font-weight: 600;
            transition: color 0.3s;
        }

        a:hover {
            text-decoration: underline;
        }

        .btn-outline-secondary {
            color: var(--text-primary);
            border-color: var(--border-color);
            background-color: transparent;
            transition: all 0.3s;
        }

        .btn-outline-secondary:hover {
            background-color: var(--bg-tertiary);
            border-color: var(--border-color);
            color: var(--text-primary);
        }

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

        .navbar-toggler {
            border-color: var(--text-primary);
        }

        .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%2834, 34, 59, 0.75%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
        }

        [data-theme="dark"] .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%28233, 236, 239, 0.75%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-white shadow-sm sticky-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">JobHive</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="index_all_companies.php">All Companies</a></li>
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
                <div class="info-item"><span>Email:</span> <a href="mailto:<?= e($company['email']) ?>"><?= e($company['email']) ?></a></div>
                <div class="info-item"><span>Phone:</span> <?= e($company['phone'] ?: '—') ?></div>
                <?php if (!empty($company['c_detail'])): ?>
                    <hr>
                    <h5>About <?= e($company['company_name']) ?></h5>
                    <p><?= nl2br(e($company['c_detail'])) ?></p>
                <?php endif; ?>
                <hr>
                <a class="btn btn-outline-secondary btn-sm" href="index_all_companies.php">&larr; Back to All Companies</a>
            </div>
        </div>
    </div>
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