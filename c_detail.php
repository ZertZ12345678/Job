<?php
// c_detail.php  (logged-in detail)
require_once "connect.php";
if (session_status() === PHP_SESSION_NONE) session_start();

/* ===== Require login ===== */
if (!isset($_SESSION['user_id'])) {
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

/* ===== Robust company_id parsing ===== */
$company_id = null;

// preferred: ?company_id=#
$cid = filter_input(INPUT_GET, 'company_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($cid) $company_id = $cid;

// fallback: ?id=#
if ($company_id === null) {
    $cid2 = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($cid2) $company_id = $cid2;
}

// fallback: /c_detail.php/#
if ($company_id === null && !empty($_SERVER['PATH_INFO'])) {
    if (preg_match('~/(\d+)$~', $_SERVER['PATH_INFO'], $m)) $company_id = (int)$m[1];
}

/* ===== If still missing, go back to the logged-in list ===== */
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

/* ===== View ===== */
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Company Detail | JobHive</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #f8f9fa;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            color: #22223b;
        }

        /* ===== Navbar brand (JobHive logo) ===== */
        .navbar-brand {
            font-weight: 700;
            color: #ffaa2b !important;
            text-decoration: none !important;
            /* prevent underline */
        }

        .navbar-brand:hover {
            color: #ffaa2b !important;
            text-decoration: none !important;
            /* still no underline on hover */
        }

        /* ===== Navbar links ===== */
        .navbar-nav .nav-item:not(.dropdown) .nav-link {
            position: relative;
            padding-bottom: 4px;
            transition: color 0.2s ease-in-out;
            text-decoration: none !important;
            /* remove default underline */
        }

        /* yellow underline effect */
        .navbar-nav .nav-item:not(.dropdown) .nav-link::after {
            content: "";
            position: absolute;
            left: 0;
            bottom: 0;
            width: 0%;
            height: 2px;
            background-color: #ffaa2b;
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
            color: #ffaa2b !important;
        }


        .company-card {
            border: 0;
            border-radius: 16px;
            overflow: hidden;
        }

        .company-header {
            background: #ffaa2b;
            color: #fff;
            padding: 2rem;
        }

        .company-logo {
            width: 120px;
            height: 120px;
            border-radius: 16px;
            object-fit: cover;
            background: #fff;
            box-shadow: 0 3px 10px rgba(0, 0, 0, .15);
        }

        .company-body {
            padding: 2rem;
        }

        h1,
        h5 {
            color: #22223b;
            font-weight: 700;
        }

        .info-item {
            margin-bottom: 1rem;
            font-size: 15px;
            color: #22223b;
        }

        .info-item span {
            font-weight: 700;
            color: #22223b;
        }

        p {
            color: #22223b;
            font-weight: 500;
            line-height: 1.6;
        }

        a {
            color: #22223b;
            font-weight: 600;
        }

        a:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>

    <nav class="navbar navbar-expand-lg bg-white shadow-sm sticky-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">JobHive</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="user_home.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="all_companies.php">All Companies</a></li>
                    <li class="nav-item"><span class="nav-link disabled">Hello, <?= e($_SESSION['full_name'] ?? 'User') ?></span></li>
                    <li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>
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
                <a class="btn btn-outline-secondary btn-sm" href="all_companies.php">&larr; Back to All Companies</a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>