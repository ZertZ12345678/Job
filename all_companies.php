<?php
// all_companies.php
require_once "connect.php";
if (session_status() === PHP_SESSION_NONE) session_start();
/* ===== Allow user OR company (block only unauthenticated) ===== */
$isUser    = isset($_SESSION['user_id']);
$isCompany = isset($_SESSION['company_id']);
if (!$isUser && !$isCompany) {
    header("Location: login.php");
    exit;
}
$user_id      = (int)($_SESSION['user_id'] ?? 0);
$user_name    = trim($_SESSION['full_name'] ?? 'User');
$company_id   = (int)($_SESSION['company_id'] ?? 0);
$company_name = trim($_SESSION['company_name'] ?? 'Company');
/* Home URL per role */
$homeUrl = $isUser ? 'user_home.php' : 'company_home.php';
/* ===== Helpers ===== */
function e($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
function initials_from_name($name): string
{
    $name = trim((string)$name);
    if ($name === '') return 'U';
    $parts = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY);
    $ini = '';
    foreach ($parts as $p) {
        $ini .= mb_strtoupper(mb_substr($p, 0, 1));
        if (mb_strlen($ini) >= 2) break;
    }
    return $ini ?: 'U';
}
/** Rounded-square initials SVG (company logo fallback) */
function svg_initials_avatar(string $text, int $size = 48): string
{
    $ini = initials_from_name($text);
    $bg  = "#f4f4f5";
    $fg  = "#111827";
    $fontSize = (int)round($size * 0.45);
    $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="{$size}" height="{$size}">
  <rect width="100%" height="100%" rx="8" ry="8" fill="{$bg}"/>
  <text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle"
        font-family="Arial, sans-serif"
        font-size="{$fontSize}" fill="{$fg}" font-weight="700">{$ini}</text>
</svg>
SVG;
    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}
/** Circle initials SVG (user avatar fallback) */
function svg_initials_circle_avatar(string $text, int $size = 40): string
{
    $ini = initials_from_name($text);
    $bg  = "#f4f4f5";
    $fg  = "#111827";
    $fontSize = (int)round($size * 0.45);
    $r = (int)floor($size / 2);
    $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="{$size}" height="{$size}">
  <defs><clipPath id="clipCircle"><circle cx="{$r}" cy="{$r}" r="{$r}"/></clipPath></defs>
  <g clip-path="url(#clipCircle)"><rect width="100%" height="100%" fill="{$bg}"/></g>
  <text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle"
        font-family="Arial, sans-serif"
        font-size="{$fontSize}" fill="{$fg}" font-weight="700">{$ini}</text>
</svg>
SVG;
    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}
/** Company logo (file if exists, else initials SVG) */
function company_logo_src(?string $filename, string $company_name, string $dir = "company_logos"): string
{
    $file = trim((string)$filename);
    if ($file !== '') {
        $safe = basename($file);
        $web  = rtrim($dir, '/') . '/' . $safe;
        $fs   = __DIR__ . '/' . $web;
        if (is_readable($fs)) {
            $ver = @filemtime($fs) ?: time();
            return $web . '?v=' . $ver;
        }
    }
    return svg_initials_avatar($company_name, 48);
}
/** User profile photo (with cache-busting) */
function user_photo_src(?string $filename, string $full_name, string $dir = "profile_pics"): string
{
    $file = trim((string)$filename);
    if ($file !== '') {
        $safe = basename($file);
        $web  = rtrim($dir, '/') . '/' . $safe;
        $fs   = __DIR__ . '/' . $web;
        if (is_readable($fs)) {
            $ver = @filemtime($fs) ?: time();
            return $web . '?v=' . $ver;
        }
    }
    return svg_initials_circle_avatar($full_name, 40);
}
/* ===== Query companies (table data) ===== */
$stmt = $pdo->query("
  SELECT company_id, company_name, email, phone, address, logo
  FROM companies
  ORDER BY company_name ASC
");
$companies = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
/* ===== Navbar avatar/name per role ===== */
$navName  = $isUser ? $user_name : $company_name;
$navPhoto = '';
if ($isUser) {
    try {
        $u = $pdo->prepare("SELECT profile_picture FROM users WHERE user_id = ?");
        $u->execute([$user_id]);
        $row = $u->fetch(PDO::FETCH_ASSOC);
        $navPhoto = user_photo_src($row['profile_picture'] ?? '', $user_name);
    } catch (Throwable $e) {
        $navPhoto = user_photo_src(null, $user_name);
    }
} else {
    // company avatar
    try {
        $c = $pdo->prepare("SELECT logo, company_name FROM companies WHERE company_id = ?");
        $c->execute([$company_id]);
        $crow = $c->fetch(PDO::FETCH_ASSOC) ?: [];
        $navPhoto = company_logo_src($crow['logo'] ?? '', $crow['company_name'] ?? $company_name);
    } catch (Throwable $e) {
        $navPhoto = company_logo_src('', $company_name);
    }
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>All Companies | JobHive</title>
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
            --table-header-bg: #22223b;
            --table-header-text: #ffaa2b;
            --table-row-bg: #ffffff;
            --table-row-text: #22223b;
            --table-row-hover: #f8f9fa;
            --btn-primary-bg: #ffaa2b;
            --btn-primary-text: #22223b;
            --btn-primary-hover: #e6991f;
            --card-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
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
            --table-header-bg: #22223b;
            --table-header-text: #ffaa2b;
            --table-row-bg: #1e1e1e;
            --table-row-text: #e9ecef;
            --table-row-hover: #2d2d2d;
            --btn-primary-bg: #ffaa2b;
            --btn-primary-text: #22223b;
            --btn-primary-hover: #e6991f;
            --card-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.3);
        }

        body {
            background-color: var(--bg-primary);
            color: var(--text-primary);
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            transition: background-color 0.3s, color 0.3s;
        }

        h1 {
            color: var(--text-primary);
        }

        .navbar {
            background-color: var(--navbar-bg) !important;
            border-bottom: 1px solid var(--navbar-border);
            transition: background-color 0.3s, border-color 0.3s;
        }

        .navbar-brand {
            font-weight: bold;
            color: var(--btn-primary-bg) !important;
        }

        .nav-link {
            color: var(--navbar-text) !important;
            transition: color 0.2s ease-in-out;
        }

        .nav-link.active {
            font-weight: bold;
            color: var(--btn-primary-bg) !important;
        }

        .navbar-nav .nav-item:not(.dropdown) .nav-link {
            position: relative;
            padding-bottom: 4px;
        }

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

        .navbar-nav .nav-item:not(.dropdown) .nav-link:hover::after {
            width: 100%;
        }

        .nav-profile img {
            width: 36px;
            height: 36px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid var(--btn-primary-bg);
            background: var(--bg-secondary);
        }

        .nav-profile span {
            color: var(--navbar-text);
            font-weight: 600;
        }

        .dropdown-menu {
            background-color: var(--bg-secondary);
            border: 1px solid var(--border-color);
        }

        .dropdown-item {
            color: var(--text-primary);
            transition: background-color 0.2s;
        }

        .dropdown-item:hover {
            background-color: var(--bg-tertiary);
            color: var(--text-primary);
        }

        .card {
            background-color: var(--bg-secondary);
            border: none;
            box-shadow: var(--card-shadow);
            transition: background-color 0.3s, box-shadow 0.3s;
        }

        .company-table {
            border-collapse: collapse;
            width: 100%;
        }

        .company-table th {
            background-color: var(--table-header-bg);
            color: var(--table-header-text);
            border: 1px solid var(--table-header-bg);
            font-weight: 700;
            font-size: 15px;
            text-align: left;
            padding: 10px;
        }

        .company-table td {
            background-color: var(--table-row-bg);
            color: var(--table-row-text);
            border: 1px solid var(--border-color);
            font-weight: 700;
            font-size: 14px;
            padding: 10px;
            vertical-align: middle;
            transition: background-color 0.3s, color 0.3s;
        }

        .company-table tbody tr:hover td {
            background-color: var(--table-row-hover);
        }

        .company-table a {
            color: var(--text-primary);
            text-decoration: none;
        }

        .company-table a:hover {
            text-decoration: underline;
        }

        .logo-cell img {
            width: 48px;
            height: 48px;
            border-radius: 8px;
            object-fit: cover;
            background: var(--bg-secondary);
        }

        .btn-warning {
            background-color: var(--btn-primary-bg);
            border: none;
            color: var(--btn-primary-text);
            font-weight: 600;
            font-size: 13px;
            transition: background-color 0.3s, color 0.3s;
        }

        .btn-warning:hover {
            background-color: var(--btn-primary-hover);
            color: var(--text-white);
        }

        .company-table td.action-cell {
            text-align: center;
            vertical-align: middle;
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

        .text-muted {
            color: var(--text-muted) !important;
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-white shadow-sm sticky-top">
        <div class="container">
            <a class="navbar-brand" href="<?= e($homeUrl) ?>">JobHive</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item"><a class="nav-link" href="<?= e($homeUrl) ?>">Home</a></li>
                    <?php if ($isUser): ?>
                        <li class="nav-item"><a class="nav-link" href="user_dashboard.php">Dashboard</a></li>
                    <?php else: ?>
                        <!-- Company role: no user dashboard -->
                    <?php endif; ?>
                    <li class="nav-item"><a class="nav-link active" href="all_companies.php">All Companies</a></li>

                    <!-- Theme Toggle Button -->
                    <li class="nav-item">
                        <button class="theme-toggle me-3" id="themeToggle" aria-label="Toggle theme">
                            <i class="bi bi-sun-fill" id="themeIcon"></i>
                        </button>
                    </li>

                    <!-- Right profile (user or company) -->
                    <li class="nav-item dropdown d-flex align-items-center nav-profile">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" data-bs-toggle="dropdown">
                            <img src="<?= e($navPhoto) ?>" alt="Profile">
                            <span class="ms-2"><?= e($navName) ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <?php if ($isUser): ?>
                                <li><a class="dropdown-item" href="user_profile.php">Profile</a></li>
                            <?php else: ?>
                                <li><a class="dropdown-item" href="company_profile.php">Profile</a></li>
                            <?php endif; ?>
                            <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <!-- PAGE CONTENT -->
    <div class="container py-4">
        <h1 class="h4 mb-3">All Companies</h1>
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table company-table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Company Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Address</th>
                                <th>Logo</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($companies)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">No companies found.</td>
                                </tr>
                                <?php else: foreach ($companies as $c):
                                    $logoSrc = company_logo_src($c['logo'] ?? '', $c['company_name'] ?? '');
                                ?>
                                    <tr>
                                        <td><?= e($c['company_name']) ?></td>
                                        <td><a href="mailto:<?= e($c['email']) ?>"><?= e($c['email']) ?></a></td>
                                        <td><?= e($c['phone']) ?></td>
                                        <td><?= e($c['address']) ?></td>
                                        <td class="logo-cell"><img src="<?= e($logoSrc) ?>" alt="Logo"></td>
                                        <td class="action-cell">
                                            <a href="c_detail.php?company_id=<?= urlencode((string)$c['company_id']) ?>" class="btn btn-sm btn-warning">About Company</a>
                                        </td>
                                    </tr>
                            <?php endforeach;
                            endif; ?>
                        </tbody>
                    </table>
                </div>
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