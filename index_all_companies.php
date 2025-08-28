<?php
// index_all_companies.php
require_once "connect.php";
if (session_status() === PHP_SESSION_NONE) session_start();

/* ===== Public page: ignore login state in navbar ===== */
$user_name = trim($_SESSION['full_name'] ?? ''); // not used in navbar here

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

function svg_initials_avatar(string $text, int $size = 40): string
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

function company_logo_src(?string $filename, string $company_name, string $dir = "company_logos"): string
{
    $file = trim((string)$filename);
    if ($file !== '') {
        $safe = basename($file);
        $web  = rtrim($dir, '/') . '/' . $safe;
        $fs   = __DIR__ . '/' . $web;
        if (is_readable($fs)) return $web;
    }
    return svg_initials_avatar($company_name, 48);
}

/* ===== Query companies ===== */
$stmt = $pdo->query("
  SELECT company_id, company_name, email, phone, address, logo
  FROM companies
  ORDER BY company_name ASC
");
$companies = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>All Companies | JobHive</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            color: #22223b;
        }

        h1 {
            color: #22223b;
        }

        .logo-cell img {
            width: 48px;
            height: 48px;
            border-radius: 8px;
            object-fit: cover;
        }

        .navbar-brand {
            font-weight: 700;
            color: #ffaa2b !important;
        }

        .nav-link.active {
            font-weight: 700;
            color: #ffaa2b !important;
        }

        /* Navbar link underline on hover */
        .navbar-nav .nav-link {
            position: relative;
            padding-bottom: 4px;
            /* space for underline */
            transition: color 0.2s ease-in-out;
        }

        .navbar-nav .nav-link::after {
            content: "";
            position: absolute;
            left: 0;
            bottom: 0;
            width: 0%;
            height: 2px;
            background-color: #ffaa2b;
            transition: width 0.25s ease-in-out;
        }

        .navbar-nav .nav-link:hover::after {
            width: 100%;
        }

        .card {
            background-color: #ffffff;
        }

        .company-table {
            border-collapse: collapse;
            width: 100%;
        }

        .company-table th {
            background-color: #ffaa2b;
            color: #22223b;
            border: 1px solid #ffaa2b;
            font-weight: 700;
            font-size: 15px;
            text-align: left;
            padding: 10px;
        }

        .company-table td {
            background-color: #ffffff;
            color: #22223b;
            border: 1px solid #ffaa2b;
            font-weight: 600;
            font-size: 14px;
            padding: 10px;
            vertical-align: middle;
        }

        .company-table a {
            color: #22223b;
            font-weight: 700;
            text-decoration: none;
        }

        .company-table a:hover {
            text-decoration: underline;
        }

        .btn-warning {
            background-color: #ffaa2b;
            border: none;
            color: #22223b;
            font-weight: 700;
            font-size: 13px;
        }

        .btn-warning:hover {
            background-color: #e6991f;
            color: #fff;
        }

        .action-cell {
            text-align: center;
            vertical-align: middle;
        }
    </style>
</head>

<body>

    <!-- ===== NAVBAR (always public on this page) ===== -->
    <nav class="navbar navbar-expand-lg bg-white shadow-sm sticky-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">JobHive</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link active" href="index_all_companies.php">All Companies</a></li>
                    <li class="nav-item"><a class="nav-link" href="login.php">Login</a></li>
                    <li class="nav-item"><a class="nav-link" href="sign_up.php">Register</a></li>
                    <li class="nav-item"><a class="nav-link" href="c_sign_up.php">Company Register</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- ===== PAGE CONTENT ===== -->
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
                                        <td class="logo-cell">
                                            <img src="<?= e($logoSrc) ?>" alt="Logo">
                                        </td>
                                        <td class="action-cell">
                                            <a href="index_c_detail.php?company_id=<?= urlencode((string)$c['company_id']) ?>"
                                                class="btn btn-sm btn-warning">About Company</a>
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
</body>

</html>