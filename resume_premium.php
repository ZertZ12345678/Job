<?php
require_once "connect.php";
session_start();
$PROFILE_DIR = "profile_pics/";
/* ----------------- HELPERS ----------------- */
function e($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
function initials($name)
{
    $parts = preg_split('/\s+/', trim((string)$name));
    $ini = '';
    foreach ($parts as $p) {
        if ($p !== '') $ini .= mb_strtoupper(mb_substr($p, 0, 1));
        if (mb_strlen($ini) >= 2) break;
    }
    return $ini ?: 'YN';
}
function data_uri_for_file($fs_path)
{
    if (!is_readable($fs_path)) return '';
    $mime = function_exists('mime_content_type') ? @mime_content_type($fs_path) : '';
    if (!$mime) {
        $ext = strtolower(pathinfo($fs_path, PATHINFO_EXTENSION));
        $map = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'];
        $mime = $map[$ext] ?? '';
    }
    if (!$mime) return '';
    $data = @file_get_contents($fs_path);
    if ($data === false) return '';
    return "data:$mime;base64," . base64_encode($data);
}
/* ----------------- AUTH ----------------- */
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header("Location: login.php?next=" . urlencode($_SERVER['REQUEST_URI']));
    exit;
}
/* ----------------- LOAD USER + PREMIUM CHECK ----------------- */
try {
    $stmt = $pdo->prepare("
        SELECT user_id, full_name, email, phone, address,
               job_category, current_position, profile_picture,
               b_date, gender, education, package
        FROM users
        WHERE user_id=? LIMIT 1
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $user = null;
}
if (!$user) {
    http_response_code(404);
    $fatal_error = "User not found.";
} else {
    $is_premium = (strtolower(trim($user['package'] ?? '')) === 'premium');
    if (!$is_premium) {
        http_response_code(403);
        $fatal_error = "Premium feature only. Please upgrade to access Premium Resume.";
    }
}
/* ----------------- LOAD JOB + COMPANY (from ?job_id=) ----------------- */
$job_id = isset($_GET['job_id']) ? (int)$_GET['job_id'] : 0;
$jobTitle = '';
$companyName = '';
if (empty($fatal_error) && $job_id > 0) {
    try {
        $stJ = $pdo->prepare("
            SELECT j.job_id, j.job_title, c.company_name
            FROM jobs j
            JOIN companies c ON c.company_id = j.company_id
            WHERE j.job_id = ?
            LIMIT 1
        ");
        $stJ->execute([$job_id]);
        $row = $stJ->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $jobTitle    = (string)$row['job_title'];
            $companyName = (string)$row['company_name'];
        }
    } catch (PDOException $e) { /* ignore */
    }
}
/* ----------------- PREP DATA ----------------- */
$name  = $user['full_name'] ?? '';
$email = $user['email'] ?? '';
$phone = $user['phone'] ?? '';
$addr  = $user['address'] ?? '';
$cat   = $user['job_category'] ?? '';
$pos   = $user['current_position'] ?? '';
$photo = $user['profile_picture'] ?? '';
$birth = $user['b_date'] ?? '';
$gender = $user['gender'] ?? '';
$edu   = $user['education'] ?? '';
$ini   = initials($name);
/* Normalize */
$gender = $gender ? ucwords(strtolower($gender)) : '';
$edu    = $edu ? ucwords($edu) : '';
$photo_src = '';
if ($photo) {
    $fs_path = __DIR__ . '/' . $PROFILE_DIR . $photo;
    $photo_src = data_uri_for_file($fs_path);
}
/* ----------------- Summary (first-person; no premium/parentheses) ----------------- */
$companyLabel      = $companyName ?: 'the company';
$companyPossessive = $companyName ? ($companyName . "’s") : "the company's";
if ($jobTitle && $companyName)      $opening = "I am applying for the {$jobTitle} position at {$companyName}.";
elseif ($jobTitle)                   $opening = "I am applying for the {$jobTitle} position.";
elseif ($companyName)                $opening = "I am applying to {$companyName} for a suitable role.";
else                                 $opening = "I am seeking a role that matches my skills and goals.";
$bg = [];
if ($pos && $cat) $bg[] = "I have experience as {$pos} in the {$cat} field";
elseif ($pos)     $bg[] = "I have experience as {$pos}";
elseif ($cat)     $bg[] = "My focus is on {$cat} roles";
if ($edu)         $bg[] = "I hold a {$edu}";
if ($addr)        $bg[] = "I am based in {$addr}";
if ($birth)       $bg[] = "I was born on {$birth}";
$background = $bg ? implode('. ', $bg) . '.' : '';
$commitment = "I learn quickly, follow company policies, and collaborate well with teams. My goal is to deliver reliable, high-quality work that contributes to {$companyPossessive} objectives.";
$contactPieces = [];
if ($phone) $contactPieces[] = $phone;
if ($email) $contactPieces[] = $email;
$contact = $contactPieces ? "You can reach me at " . implode(' or ', $contactPieces) . "." : "";
$premiumSummary = trim(preg_replace('/\s+/', ' ', $opening . ' ' . $background . ' ' . $commitment . ' ' . $contact));
/* Build Apply link (include job_id if present). Adjust path if resume.php lives elsewhere. */
$applyHref = 'resume.php';
if ($job_id) $applyHref .= '?job_id=' . urlencode((string)$job_id);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Premium Resume | JobHive</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/dom-to-image-more@3.3.0/dist/dom-to-image-more.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <!-- Apply theme immediately to prevent flash -->
    <script>
        (function() {
            // Check for saved theme preference - using the same key as job_detail.php
            const savedTheme = localStorage.getItem('theme') || 'light';
            // Apply the theme immediately
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>
    <style>
        :root {
            --accent: #0ea5e9;
            --ink: #0f172a;
            --muted: #6b7280;
            --sp-1: 6px;
            --sp-2: 10px;
            --sp-3: 14px;
            --sp-4: 18px;
            --sp-5: 24px;
            --sp-6: 32px;
            --sp-7: 40px;
            --sp-8: 56px;
            --radius-lg: 16px;
            --shadow: 0 10px 30px rgba(0, 0, 0, .08);
            --line: 1.65;
            /* Light mode colors */
            --bg-primary: #f8fafc;
            --bg-secondary: #ffffff;
            --bg-tertiary: #f3f4f6;
            --bg-footer: #212529;
            --text-primary: #22223b;
            --text-secondary: #495057;
            --text-muted: #6c757d;
            --text-white: #ffffff;
            --border-color: #dee2e6;
            --navbar-bg: #ffffff;
            --navbar-text: #22223b;
            --navbar-border: #dee2e6;
            --card-bg: #ffffff;
            --card-shadow: 0 6px 24px rgba(0, 0, 0, 0.06);
            --btn-primary-bg: #ffaa2b;
            --btn-primary-text: #22223b;
            --btn-primary-hover: #e6991f;
            --page-header-bg: #ffffff;
            --page-header-border: rgba(0, 0, 0, .06);
            --apply-box-bg: #ffffff;
            --apply-box-text: #0f172a;
            --apply-box-border: #e5e7eb;
            --apply-box-steps: #475569;
            --btn-outline-bg: transparent;
            --btn-outline-text: #0f172a;
            --btn-outline-border: #6c757d;
            --btn-outline-hover-bg: rgba(0, 0, 0, 0.05);
            --btn-warning-bg: #ffc107;
            --btn-warning-text: #212529;
            --form-check-bg: #ffffff;
            --form-check-text: #0f172a;
        }

        [data-theme="dark"] {
            /* Dark mode colors */
            --bg-primary: #000000;
            --bg-secondary: #0d0d0d;
            --bg-tertiary: #1a1a1a;
            --bg-footer: #000000;
            --text-primary: #ffffff;
            --text-secondary: #ffffff;
            --text-muted: #ffffff;
            --text-white: #ffffff;
            --border-color: #333333;
            --navbar-bg: #0d0d0d;
            --navbar-text: #ffffff;
            --navbar-border: #333333;
            --card-bg: #0d0d0d;
            --card-shadow: 0 6px 24px rgba(0, 0, 0, 0.5);
            --btn-primary-bg: #ffaa2b;
            --btn-primary-text: #000000;
            --btn-primary-hover: #e6991f;
            --page-header-bg: #000000;
            --page-header-border: #333333;
            --apply-box-bg: #0d0d0d;
            --apply-box-text: #e2e8f0;
            --apply-box-border: #4a5568;
            --apply-box-steps: #cbd5e0;
            --btn-outline-bg: transparent;
            --btn-outline-text: #e2e8f0;
            --btn-outline-border: #4a5568;
            --btn-outline-hover-bg: rgba(255, 255, 255, 0.1);
            --btn-warning-bg: #d69e2e;
            --btn-warning-text: #1a202c;
            --form-check-bg: #0d0d0d;
            --form-check-text: #e2e8f0;
        }

        /* Force light theme for resume content */
        .resume-content {
            background-color: #ffffff !important;
            color: #0f172a !important;
        }

        .resume-content .muted {
            color: #6b7280 !important;
        }

        .resume-content .section-title {
            color: var(--accent) !important;
        }

        .resume-content .t1 .side {
            background: color-mix(in srgb, var(--accent) 9%, white) !important;
            border-right-color: var(--accent) !important;
        }

        .resume-content .t2 .head {
            background: var(--accent) !important;
            color: #ffffff !important;
        }

        .resume-content .t3 .left {
            background: #0b1220 !important;
            color: #e5e7eb !important;
        }

        .resume-content .t3 .chip {
            background: rgba(255, 255, 255, .05) !important;
            border-color: #e5e7eb !important;
        }

        html,
        body {
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            text-rendering: optimizeLegibility;
            font-feature-settings: "liga", "kern";
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-primary);
            color: var(--text-primary);
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        header {
            position: relative;
            z-index: 20;
            background-color: var(--page-header-bg);
            color: var(--text-primary);
            border-bottom: 1px solid var(--page-header-border);
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        .apply-box {
            position: relative;
            z-index: 21;
            background-color: var(--apply-box-bg);
            color: var(--apply-box-text);
            border: none;
            transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease;
        }

        .apply-box h6 {
            color: var(--apply-box-text);
        }

        .apply-box p {
            color: var(--apply-box-steps);
        }

        .apply-box .steps {
            color: var(--apply-box-steps);
        }

        .resume,
        .resume * {
            font-variant-numeric: tabular-nums;
        }

        .resume p {
            margin: .45rem 0 .9rem;
            text-align: justify;
            text-justify: inter-word;
            hyphens: none;
            word-break: normal;
            overflow-wrap: anywhere;
        }

        .section {
            margin: var(--sp-4) 0;
        }

        .section:first-child {
            margin-top: 0;
        }

        .editable [contenteditable="true"] {
            border-bottom: 1px dashed transparent;
            cursor: text;
        }

        .editable [contenteditable="true"]:hover {
            border-bottom: 1px dashed #cbd5e1;
        }

        .editable.off [contenteditable="true"] {
            border-bottom: none !important;
            cursor: default;
        }

        .toolbar {
            gap: .5rem;
        }

        .template-picker .swatch {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            border: 2px solid #fff;
            box-shadow: 0 0 0 2px rgba(0, 0, 0, .1);
            cursor: pointer;
        }

        .template-pills .btn-templ {
            border-radius: 999px;
            padding: .35rem .75rem;
            font-weight: 600;
            line-height: 1.2;
        }

        .template-pills .btn-templ.active {
            color: #fff;
            background: var(--accent);
            border-color: var(--accent);
        }

        .apply-box .btn-apply {
            background: var(--accent);
            border: 1px solid var(--accent);
            color: #fff;
            font-weight: 700;
            padding: .475rem .9rem;
            border-radius: 999px;
        }

        .apply-box .btn-apply:hover {
            filter: brightness(.95);
        }

        .resume-stage {
            background: #fff;
            width: 794px;
            min-height: 1123px;
            margin: 0 auto;
            box-shadow: var(--shadow);
            border-radius: var(--radius-lg);
            overflow: hidden;
            transition: box-shadow .25s ease, transform .25s ease;
        }

        .resume-stage:hover {
            box-shadow: 0 12px 36px rgba(0, 0, 0, .10);
        }

        .divider {
            height: 2px;
            background: color-mix(in srgb, var(--accent) 18%, white);
            margin: var(--sp-5) 0 var(--sp-4);
        }

        .h-name {
            font-weight: 900;
            letter-spacing: .2px;
            margin-bottom: var(--sp-2);
        }

        .muted {
            color: var(--muted);
        }

        .stack>*+* {
            margin-top: var(--sp-3);
        }

        .avatar {
            width: 120px;
            height: 120px;
            border-radius: 14px;
            object-fit: cover;
            border: 2px solid rgba(0, 0, 0, .06);
            background: #fff;
        }

        .avatar-fallback {
            width: 120px;
            height: 120px;
            border-radius: 14px;
            background: var(--accent);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 34px;
        }

        .circle {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 4px solid var(--accent);
            object-fit: cover;
            background: #fff;
        }

        .t1 {
            display: grid;
            grid-template-columns: 280px 1fr;
            min-height: 1123px;
        }

        .t1 .side {
            background: color-mix(in srgb, var(--accent) 9%, white);
            padding: var(--sp-7) var(--sp-6);
            border-right: 3px solid var(--accent);
            display: flex;
            flex-direction: column;
        }

        .t1 .main {
            padding: var(--sp-7) var(--sp-7);
            display: flex;
            flex-direction: column;
        }

        .t1 .spacer {
            flex: 1 1 auto;
        }

        .section-title {
            color: var(--accent);
            font-weight: 800;
            letter-spacing: .4px;
            margin: var(--sp-4) 0 var(--sp-2);
            border-bottom: 2px solid color-mix(in srgb, var(--accent) 18%, white);
            padding-bottom: .25rem;
        }

        .t2 {
            min-height: 1123px;
            display: flex;
            flex-direction: column;
        }

        .t2 .head {
            background: var(--accent);
            color: #fff;
            padding: var(--sp-7) var(--sp-7) var(--sp-6);
        }

        .t2 .body {
            padding: var(--sp-6) var(--sp-7);
            display: flex;
            flex-direction: column;
            flex: 1 1 auto;
        }

        .t2 .body .spacer {
            flex: 1 1 auto;
        }

        .t2 .badge-role {
            background: rgba(255, 255, 255, .18);
            border: 1px solid rgba(255, 255, 255, .35);
            padding: .35rem .7rem;
            border-radius: 999px;
        }

        .t2 .row.gx-6 {
            --bs-gutter-x: 3rem;
        }

        .t3 {
            display: grid;
            grid-template-columns: 300px 1fr;
            min-height: 1123px;
        }

        .t3 .left {
            background: #0b1220;
            color: #e5e7eb;
            padding: var(--sp-7) var(--sp-6);
            display: flex;
            flex-direction: column;
        }

        .t3 .right {
            padding: var(--sp-7) var(--sp-7);
            display: flex;
            flex-direction: column;
        }

        .t3 .right .spacer {
            flex: 1 1 auto;
        }

        .t3 .chip {
            display: inline-block;
            padding: .35rem .7rem;
            border: 1px solid #e5e7eb;
            border-radius: 999px;
            margin-right: .35rem;
            margin-top: .35rem;
            background: rgba(255, 255, 255, .05);
            line-height: 1;
        }

        @media print {
            body {
                background: #fff !important;
            }

            .resume-stage {
                box-shadow: none !important;
                border-radius: 0 !important;
            }

            .navbar,
            header,
            footer,
            .toolbar,
            .apply-box,
            .template-pills {
                display: none !important;
            }

            @page {
                margin: 0;
                size: A4;
            }
        }

        @media (max-width:992px) {
            .apply-box {
                margin-top: 10px;
            }
        }

        @media (max-width:900px) {
            .resume-stage {
                width: 100%;
                border-radius: 0;
            }

            .t1,
            .t3 {
                grid-template-columns: 1fr;
            }

            .t1 .side {
                border-right: 0;
                border-bottom: 3px solid var(--accent);
            }
        }

        .navbar {
            background-color: var(--navbar-bg) !important;
            color: var(--navbar-text) !important;
            border-bottom: 1px solid var(--navbar-border) !important;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        .navbar .navbar-brand {
            color: var(--btn-primary-bg) !important;
            font-weight: 700;
        }

        .navbar .btn-outline-secondary {
            background-color: var(--btn-outline-bg) !important;
            color: var(--btn-outline-text) !important;
            border-color: var(--btn-outline-border) !important;
            transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease;
        }

        .navbar .btn-outline-secondary:hover {
            background-color: var(--btn-outline-hover-bg) !important;
            color: var(--btn-outline-text) !important;
        }

        .navbar .btn-outline-danger {
            background-color: var(--btn-outline-bg) !important;
            color: var(--btn-outline-text) !important;
            border-color: #dc3545 !important;
        }

        .navbar .btn-outline-danger:hover {
            background-color: rgba(220, 53, 69, 0.2) !important;
            color: var(--btn-outline-text) !important;
        }

        .navbar .btn-warning {
            background-color: var(--btn-warning-bg) !important;
            border-color: var(--btn-warning-bg) !important;
            color: var(--btn-warning-text) !important;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        .navbar .btn-warning:hover {
            filter: brightness(0.9) !important;
        }

        .navbar .form-check-input:checked {
            background-color: var(--accent) !important;
            border-color: var(--accent) !important;
        }

        .navbar .form-check-label {
            color: var(--navbar-text) !important;
        }

        footer {
            background-color: var(--bg-footer) !important;
            color: var(--text-white) !important;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        footer a {
            color: var(--text-white);
            transition: color 0.3s;
        }

        footer a:hover {
            color: var(--btn-primary-bg);
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

        .alert-warning {
            background-color: var(--bg-tertiary);
            border-color: var(--btn-primary-bg);
            color: var(--text-primary);
            transition: background-color 0.3s, border-color 0.3s, color 0.3s;
        }

        .alert-danger {
            background-color: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }

        [data-theme="dark"] .alert-danger {
            background-color: #721c24;
            border-color: #a71e2a;
            color: #ffffff;
        }

        .alert-success {
            background-color: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }

        [data-theme="dark"] .alert-success {
            background-color: #155724;
            border-color: #0f5132;
            color: #ffffff;
        }

        .form-control {
            background-color: var(--bg-secondary);
            color: var(--text-primary);
            border-color: var(--border-color);
        }

        .form-control:focus {
            background-color: var(--bg-secondary);
            color: var(--text-primary);
            border-color: var(--btn-primary-bg);
        }

        .form-label {
            color: var(--text-primary);
        }

        .form-text {
            color: var(--text-muted);
        }

        /* Ensure all text is white in dark mode */
        [data-theme="dark"] .text-muted {
            color: #ffffff !important;
        }

        [data-theme="dark"] .fw-semibold {
            color: #ffffff !important;
        }

        [data-theme="dark"] small {
            color: #ffffff !important;
        }

        [data-theme="dark"] p {
            color: #ffffff !important;
        }

        [data-theme="dark"] h1,
        [data-theme="dark"] h2,
        [data-theme="dark"] h3,
        [data-theme="dark"] h4,
        [data-theme="dark"] h5,
        [data-theme="dark"] h6 {
            color: #ffffff !important;
        }

        [data-theme="dark"] ol {
            color: #ffffff !important;
        }

        [data-theme="dark"] ol li {
            color: #ffffff !important;
        }

        [data-theme="dark"] .form-text {
            color: #e9ecef !important;
        }

        .border-bottom {
            border-bottom: 1px solid var(--page-header-border) !important;
        }

        /* Apply job section specific styling for dark mode */
        [data-theme="dark"] main {
            background-color: var(--bg-primary) !important;
        }

        [data-theme="dark"] .container {
            background-color: transparent;
        }

        [data-theme="dark"] .card {
            background-color: var(--card-bg) !important;
            border: 1px solid var(--border-color) !important;
        }

        [data-theme="dark"] .form-control {
            background-color: var(--bg-tertiary) !important;
            border-color: var(--border-color) !important;
            color: var(--text-primary) !important;
        }

        [data-theme="dark"] .form-control:focus {
            background-color: var(--bg-tertiary) !important;
            border-color: var(--btn-primary-bg) !important;
            color: var(--text-primary) !important;
        }

        [data-theme="dark"] .btn-outline-secondary {
            background-color: transparent !important;
            border-color: var(--border-color) !important;
            color: var(--text-primary) !important;
        }

        [data-theme="dark"] .btn-outline-secondary:hover {
            background-color: var(--bg-tertiary) !important;
            border-color: var(--border-color) !important;
            color: var(--text-primary) !important;
        }

        /* Apply to Job header styling */
        [data-theme="dark"] header.py-4 {
            background-color: var(--page-header-bg) !important;
            border-bottom: 1px solid var(--page-header-border) !important;
        }

        [data-theme="dark"] header.py-4 h1 {
            color: var(--text-primary) !important;
        }

        /* Job details styling */
        [data-theme="dark"] .card .row>div>div {
            color: var(--text-primary) !important;
        }

        [data-theme="dark"] .card .row>div>div>div {
            color: var(--text-primary) !important;
        }

        [data-theme="dark"] .card .row>div>div>span {
            color: var(--text-primary) !important;
        }

        [data-theme="dark"] .card h2 {
            color: var(--text-primary) !important;
        }

        [data-theme="dark"] .card .text-muted {
            color: var(--text-muted) !important;
        }

        /* Dark mode fixes for resume templates */
        [data-theme="dark"] .resume-content .t1 .side .fw-semibold,
        [data-theme="dark"] .resume-content .t1 .side .muted {
            color: #000000 !important;
        }

        /* Dark mode fixes for resume templates */
        [data-theme="dark"] .resume-content .t1 .side .fw-semibold,
        [data-theme="dark"] .resume-content .t1 .side .muted {
            color: #000000 !important;
        }

        /* Make Category and Position labels match section titles in Template 1 */
        [data-theme="dark"] .resume-content .t1 .side .section:nth-child(3) small {
            color: var(--accent) !important;
            font-weight: 800 !important;
            letter-spacing: .4px !important;
        }

        /* Specific fix for Template 1 Company and Position */
        [data-theme="dark"] .resume-content .t1 .main .section:nth-child(2) .fw-semibold,
        [data-theme="dark"] .resume-content .t1 .main .section:nth-child(2) .muted,
        [data-theme="dark"] .resume-content .t1 .main .section:nth-child(2) div[contenteditable="true"] {
            color: #000000 !important;
        }

        /* Template 2 fixes - make all titles consistent */
        [data-theme="dark"] .resume-content .t2 .body .section .fw-semibold {
            color: #000000 !important;
        }

        [data-theme="dark"] .resume-content .t2 .body .section:first-child .muted {
            color: #000000 !important;
        }

        [data-theme="dark"] .resume-content .t2 .head .badge-role,
        [data-theme="dark"] .resume-content .t2 .body .section:last-child div[contenteditable="true"] {
            color: #000000 !important;
        }

        /* Template 3 fixes */
        [data-theme="dark"] .resume-content .t3 .right .section:first-child .display-6,
        [data-theme="dark"] .resume-content .t3 .right .section:nth-child(2) .fw-semibold,
        [data-theme="dark"] .resume-content .t3 .right .section:nth-child(3) .fw-semibold {
            color: var(--accent) !important;
        }

        /* Add gold underline hover effect for navigation links */
        .navbar-nav .nav-link {
            position: relative;
            transition: color 0.3s ease;
        }

        .navbar-nav .nav-link::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: -2px;
            left: 0;
            background-color: var(--btn-primary-bg);
            /* Gold color */
            transition: width 0.3s ease;
        }

        .navbar-nav .nav-link:hover::after {
            width: 100%;
        }

        /* Dark mode navigation link styles */
        [data-theme="dark"] .navbar-nav .nav-link {
            color: #ffffff !important;
        }

        [data-theme="dark"] .navbar-nav .nav-link:hover {
            color: #ffffff !important;
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-light shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="user_home.php">JobHive</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-end" id="nav">
                <ul class="navbar-nav">
                    <li class="nav-item"><a class="nav-link" href="user_home.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="user_dashboard.php">Dashboard</a></li>
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
    <header class="py-4 border-bottom">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-3">
                    <h1 class="h4 fw-bold mb-2">Select your template</h1>
                    <div class="text-muted">Customize your resume below.</div>
                </div>
                <div class="col-md-9">
                    <div class="apply-box h-100 d-flex flex-column justify-content-between">
                        <div>
                            <h6>Apply with this resume</h6>
                            <p class="steps mb-2">1. Choose a template & color · 2. Edit your details · 3. <strong>Download</strong> as PNG or PDF</p>
                            <p class="mb-0">After downloading the resume, click <strong>Apply Resume</strong> to continue applying for the job.</p>
                        </div>
                        <div class="mt-3">
                            <!-- IMPORTANT: include job_id if present -->
                            <a class="btn btn-apply" id="btnApply" href="<?= e($applyHref) ?>" role="button">Apply Resume</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>
    <main class="py-4">
        <div class="container">
            <?php if (!empty($fatal_error)): ?>
                <div class="alert alert-warning"><?= e($fatal_error) ?></div>
            <?php else: ?>
                <!-- Toolbar with all original buttons -->
                <div class="d-flex justify-content-end mb-3 toolbar">
                    <div class="form-check form-switch me-2">
                        <input class="form-check-input" type="checkbox" id="toggleEdit" checked>
                        <label class="form-check-label" for="toggleEdit">Edit Mode</label>
                    </div>
                    <!-- Photo controls -->
                    <input id="photoInput" type="file" accept="image/*" class="d-none">
                    <button id="btnPhoto" class="btn btn-outline-secondary btn-sm">Change Photo</button>
                    <button id="btnPhotoRemove" class="btn btn-outline-danger btn-sm">Remove Photo</button>
                    <button id="btnReset" class="btn btn-outline-secondary btn-sm ms-2">Reset Edits</button>
                    <a href="user_home.php" class="btn btn-outline-secondary btn-sm">Back</a>
                    <button id="btnPNG" class="btn btn-outline-secondary btn-sm">Download PNG</button>
                    <button id="btnPDF" class="btn btn-warning btn-sm">Download PDF</button>
                </div>
                <!-- Template & color -->
                <div class="row g-3 align-items-stretch mb-3 template-picker">
                    <div class="col-12 col-lg-7">
                        <div class="d-flex align-items-center flex-wrap gap-2">
                            <span class="me-2">Theme color</span>
                            <?php $swatches = ['#111827', '#64748b', '#6b7280', '#1d4ed8', '#b91c1c', '#065f46', '#0ea5e9', '#ef4444', '#f59e0b', '#14b8a6', '#eab308'];
                            foreach ($swatches as $hex): ?>
                                <div class="swatch" data-color="<?= e($hex) ?>" style="background: <?= e($hex) ?>;"></div>
                            <?php endforeach; ?>
                            <span class="mx-2">or</span>
                            <input id="hexColor" type="text" class="form-control form-control-sm" value="#0EA5E9" style="max-width:120px;" />
                        </div>
                        <div id="templatePills" class="template-pills d-flex flex-wrap gap-2 mt-3" role="tablist">
                            <button type="button" class="btn btn-outline-secondary btn-sm btn-templ active" data-template="t1" aria-selected="true">Template 1</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm btn-templ" data-template="t2" aria-selected="false">Template 2</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm btn-templ" data-template="t3" aria-selected="false">Template 3</button>
                        </div>
                    </div>
                </div>
                <!-- Live A4 Preview -->
                <div id="resumeStage" class="resume-stage editable" style="--accent:#0ea5e9;">
                    <div id="resumeHost" class="resume-content"></div>
                </div>
                <!-- T1 -->
                <template id="tpl-t1">
                    <div class="resume t1">
                        <aside class="side">
                            <div class="stack">
                                <!-- Avatar (editable) -->
                                <div class="position-relative">
                                    <img class="avatar js-photo d-none" alt="Profile">
                                    <div class="avatar-fallback js-fallback"><?= e($ini) ?></div>
                                </div>
                                <div class="section">
                                    <div class="fw-semibold" contenteditable="true"><?= e($email) ?></div>
                                    <div class="muted" contenteditable="true"><?= e($phone) ?></div>
                                    <div class="muted" contenteditable="true"><?= e($addr) ?></div>
                                    <div class="muted" contenteditable="true"><?= e($birth) ?></div>
                                    <?php if ($gender): ?><div class="muted" contenteditable="true">Gender: <?= e($gender) ?></div><?php endif; ?>
                                    <?php if ($edu): ?><div class="muted" contenteditable="true">Education: <?= e($edu) ?></div><?php endif; ?>
                                </div>
                                <div class="section">
                                    <div class="section-title">Key Info</div>
                                    <div><small>Category</small><br><strong contenteditable="true"><?= e($cat) ?></strong></div>
                                    <div class="mt-2"><small>Position</small><br><strong contenteditable="true"><?= e($pos) ?></strong></div>
                                </div>
                            </div>
                            <div class="spacer"></div>
                        </aside>
                        <section class="main">
                            <div class="section">
                                <div class="display-6 h-name mb-1" contenteditable="true"><?= e($name) ?></div>
                                <div class="fs-5 muted" contenteditable="true"><?= e($pos) ?></div>
                            </div>
                            <?php if ($jobTitle || $companyName): ?>
                                <div class="section">
                                    <div class="section-title">Applied Job</div>
                                    <div class="row g-2">
                                        <div class="col-md-6">
                                            <div class="fw-semibold">Company</div>
                                            <div class="muted" contenteditable="true"><?= e($companyName) ?></div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="fw-semibold">Job Title</div>
                                            <div class="muted" contenteditable="true"><?= e($jobTitle) ?></div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <div class="section">
                                <div class="section-title">Summary</div>
                                <p class="mb-0 muted" contenteditable="true"><?= e($premiumSummary) ?></p>
                            </div>
                            <div class="spacer"></div>
                        </section>
                    </div>
                </template>
                <!-- T2 -->
                <template id="tpl-t2">
                    <div class="resume t2">
                        <div class="head d-flex align-items-center gap-4">
                            <!-- Avatar (editable) -->
                            <div class="position-relative">
                                <img class="avatar js-photo d-none" alt="Profile" style="border:3px solid rgba(255,255,255,.55);">
                                <div class="avatar-fallback js-fallback"><?= e($ini) ?></div>
                            </div>
                            <div class="flex-grow-1">
                                <div class="display-6 fw-bold mb-1" contenteditable="true"><?= e($name) ?></div>
                                <span class="badge-role" contenteditable="true"><?= e($pos ?: $cat) ?></span>
                            </div>
                        </div>
                        <div class="body">
                            <div class="section">
                                <div class="row gx-6 gy-4">
                                    <div class="col-md-6">
                                        <div class="fw-semibold">Email</div>
                                        <div class="muted" contenteditable="true"><?= e($email) ?></div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="fw-semibold">Phone</div>
                                        <div class="muted" contenteditable="true"><?= e($phone) ?></div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="fw-semibold">Address</div>
                                        <div class="muted" contenteditable="true"><?= e($addr) ?></div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="fw-semibold">Category</div>
                                        <div class="muted" contenteditable="true"><?= e($cat) ?></div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="fw-semibold">Birth Date</div>
                                        <div class="muted" contenteditable="true"><?= e($birth) ?></div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="fw-semibold">Gender</div>
                                        <div class="muted" contenteditable="true"><?= e($gender) ?></div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="fw-semibold">Education</div>
                                        <div class="muted" contenteditable="true"><?= e($edu) ?></div>
                                    </div>
                                    <?php if ($jobTitle || $companyName): ?>
                                        <div class="col-md-6">
                                            <div class="fw-semibold">Company</div>
                                            <div class="muted" contenteditable="true"><?= e($companyName) ?></div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="fw-semibold">Job Title</div>
                                            <div class="muted" contenteditable="true"><?= e($jobTitle) ?></div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="section">
                                <div class="fw-semibold">Summary</div>
                                <p class="muted mb-0" contenteditable="true"><?= e($premiumSummary) ?></p>
                            </div>
                            <div class="divider"></div>
                            <div class="section">
                                <div class="fw-semibold mb-1">Current Position</div>
                                <div contenteditable="true"><?= e($pos) ?></div>
                            </div>
                            <div class="spacer"></div>
                        </div>
                    </div>
                </template>
                <!-- T3 -->
                <template id="tpl-t3">
                    <div class="resume t3">
                        <div class="left">
                            <!-- Avatar (editable) -->
                            <div class="position-relative">
                                <img class="circle js-photo d-none" alt="Profile">
                                <div class="avatar-fallback js-fallback" style="border-radius:50%;width:120px;height:120px;"><?= e($ini) ?></div>
                            </div>
                            <div class="section">
                                <div contenteditable="true"><?= e($email) ?></div>
                                <div contenteditable="true"><?= e($phone) ?></div>
                                <div contenteditable="true"><?= e($addr) ?></div>
                                <div contenteditable="true"><?= e($birth) ?></div>
                                <?php if ($gender): ?><div contenteditable="true"><?= e('Gender: ' . $gender) ?></div><?php endif; ?>
                                <?php if ($edu): ?><div contenteditable="true"><?= e('Education: ' . $edu) ?></div><?php endif; ?>
                                <?php if ($companyName): ?><div contenteditable="true"><?= e('Company: ' . $companyName) ?></div><?php endif; ?>
                                <?php if ($jobTitle): ?><div contenteditable="true"><?= e('Job Title: ' . $jobTitle) ?></div><?php endif; ?>
                            </div>
                            <div class="section">
                                <?php if ($cat): ?><span class="chip" contenteditable="true"><?= e($cat) ?></span><?php endif; ?>
                                <?php if ($pos): ?><span class="chip" contenteditable="true"><?= e($pos) ?></span><?php endif; ?>
                                <?php if ($edu): ?><span class="chip" contenteditable="true"><?= e($edu) ?></span><?php endif; ?>
                            </div>
                            <div class="spacer"></div>
                        </div>
                        <div class="right">
                            <div class="section">
                                <div class="display-6 fw-bold mb-1" style="color:var(--accent)" contenteditable="true"><?= e($name) ?></div>
                                <div class="fs-5 muted mb-4" contenteditable="true"><?= e($pos ?: $cat) ?></div>
                            </div>
                            <div class="section">
                                <div class="fw-semibold">Summary</div>
                                <p class="muted" contenteditable="true"><?= e($premiumSummary) ?></p>
                            </div>
                            <div class="section">
                                <div class="fw-semibold">Contact</div>
                                <ul class="mb-0">
                                    <li contenteditable="true"><?= e($email) ?></li>
                                    <li contenteditable="true"><?= e($phone) ?></li>
                                    <li contenteditable="true"><?= e($addr) ?></li>
                                </ul>
                            </div>
                            <div class="spacer"></div>
                        </div>
                    </div>
                </template>
            <?php endif; ?>
        </div>
    </main>
    <footer class="mt-5 py-4">
        <div class="container d-flex flex-column align-items-center">
            <div class="mb-2">
                <a href="#" class="text-white text-decoration-none me-3">About</a>
                <a href="#" class="text-white text-decoration-none me-3">Contact</a>
                <a href="#" class="text-white text-decoration-none">Privacy Policy</a>
            </div>
            <small>&copy; 2025 JobHive. All rights reserved.</small>
        </div>
    </footer>
    <script>
        (function() {
            const A4_W = 794,
                A4_H = 1123;
            const stage = document.getElementById('resumeStage');
            const host = document.getElementById('resumeHost');
            const hexInput = document.getElementById('hexColor');
            const swatches = document.querySelectorAll('.swatch');
            const templBtns = document.querySelectorAll('.btn-templ');
            const btnPNG = document.getElementById('btnPNG');
            const btnPDF = document.getElementById('btnPDF');
            const btnReset = document.getElementById('btnReset');
            const toggleEdit = document.getElementById('toggleEdit');
            const btnApply = document.getElementById('btnApply');
            const btnPhoto = document.getElementById('btnPhoto');
            const btnPhotoRemove = document.getElementById('btnPhotoRemove');
            const photoInput = document.getElementById('photoInput');
            const themeToggle = document.getElementById('themeToggle');
            const themeIcon = document.getElementById('themeIcon');
            const tpl1 = document.getElementById('tpl-t1');
            const tpl2 = document.getElementById('tpl-t2');
            const tpl3 = document.getElementById('tpl-t3');
            let originalHTML = '';
            let currentTplKey = 't1';

            /* ---- Theme Management ---- */
            const THEME_KEY = 'theme'; // Use the same key as job_detail.php
            const THEME_PARAM = 'theme';

            // Set theme icon based on current theme
            function updateThemeIcon(theme) {
                if (theme === 'dark') {
                    themeIcon.classList.remove('bi-sun-fill');
                    themeIcon.classList.add('bi-moon-fill');
                } else {
                    themeIcon.classList.remove('bi-moon-fill');
                    themeIcon.classList.add('bi-sun-fill');
                }
            }

            // Get theme from URL parameter
            function getThemeFromURL() {
                const urlParams = new URLSearchParams(window.location.search);
                return urlParams.get(THEME_PARAM);
            }

            // Initialize theme on page load
            function initTheme() {
                // Check for theme parameter in URL
                const urlTheme = getThemeFromURL();
                const savedTheme = localStorage.getItem(THEME_KEY);

                // Determine which theme to use (URL parameter takes precedence)
                let currentTheme;
                if (urlTheme && (urlTheme === 'dark' || urlTheme === 'light')) {
                    currentTheme = urlTheme;
                    // Save the theme from URL to localStorage for future use
                    localStorage.setItem(THEME_KEY, currentTheme);
                } else if (savedTheme) {
                    currentTheme = savedTheme;
                } else {
                    currentTheme = 'light'; // Default theme
                }

                document.documentElement.setAttribute('data-theme', currentTheme);
                updateThemeIcon(currentTheme);
            }

            // Toggle theme function
            function toggleTheme() {
                const currentTheme = document.documentElement.getAttribute('data-theme');
                const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
                document.documentElement.setAttribute('data-theme', newTheme);
                localStorage.setItem(THEME_KEY, newTheme);
                updateThemeIcon(newTheme);
            }

            // Initialize theme on page load
            initTheme();

            // Add event listener to theme toggle
            themeToggle.addEventListener('click', toggleTheme);

            /* ---- Session model: only for photo + accent (no DB writes) ---- */
            const STORAGE_KEY = 'resumePremiumModel';
            const defaultModel = {
                photoSrc: <?= json_encode($photo_src ?: "") ?>,
                accent: '#0EA5E9'
            };
            let resumeModel = loadModel();

            function loadModel() {
                try {
                    const raw = sessionStorage.getItem(STORAGE_KEY);
                    if (raw) {
                        const p = JSON.parse(raw);
                        return {
                            ...defaultModel,
                            ...p
                        };
                    }
                } catch (e) {}
                return {
                    ...defaultModel
                };
            }

            function saveModel() {
                try {
                    sessionStorage.setItem(STORAGE_KEY, JSON.stringify(resumeModel));
                } catch (e) {}
            }

            function mountTemplate(tplEl) {
                host.innerHTML = '';
                host.appendChild(tplEl.content.cloneNode(true));
                forceFillCurrentTemplate(host);
                originalHTML = host.innerHTML;
                applyEditMode();
                applyPhoto();
            }

            function setAccent(hex) {
                const v = hex.trim();
                if (!/^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$/.test(v)) return;
                stage.style.setProperty('--accent', v);
                hexInput.value = v.toUpperCase();
                resumeModel.accent = v;
                saveModel();
            }

            function setTemplate(key) {
                currentTplKey = key;
                templBtns.forEach(b => {
                    const active = b.dataset.template === key;
                    b.classList.toggle('active', active);
                    b.setAttribute('aria-selected', active ? 'true' : 'false');
                });
                if (key === 't1') mountTemplate(tpl1);
                if (key === 't2') mountTemplate(tpl2);
                if (key === 't3') mountTemplate(tpl3);
            }

            function forceFillCurrentTemplate(root) {
                stage.style.width = A4_W + 'px';
                stage.style.height = A4_H + 'px';
                const resume = root.querySelector('.resume');
                if (!resume) return;
                resume.style.minHeight = A4_H + 'px';
                resume.style.height = '100%';
                if (resume.classList.contains('t1')) {
                    resume.style.display = 'grid';
                    resume.style.gridTemplateColumns = '280px 1fr';
                }
                if (resume.classList.contains('t2')) {
                    resume.style.display = 'flex';
                    resume.style.flexDirection = 'column';
                    resume.style.height = '100%';
                    const body = resume.querySelector('.body');
                    if (body) body.style.flex = '1 1 auto';
                }
                if (resume.classList.contains('t3')) {
                    resume.style.display = 'grid';
                    resume.style.gridTemplateColumns = '300px 1fr';
                }
            }

            function applyEditMode() {
                const on = toggleEdit.checked;
                stage.classList.toggle('off', !on);
                stage.querySelectorAll('[contenteditable]').forEach(el => el.setAttribute('contenteditable', on ? 'true' : 'false'));
            }

            function resetEdits() {
                if (!originalHTML) return;
                host.innerHTML = originalHTML;
                forceFillCurrentTemplate(host);
                applyEditMode();
                applyPhoto();
            }

            /* ---- Photo handling ---- */
            function applyPhoto() {
                const hasPhoto = !!resumeModel.photoSrc;
                stage.querySelectorAll('.js-photo').forEach(img => {
                    if (hasPhoto) {
                        img.src = resumeModel.photoSrc;
                        img.classList.remove('d-none');
                    } else {
                        img.classList.add('d-none');
                        img.removeAttribute('src');
                    }
                });
                stage.querySelectorAll('.js-fallback').forEach(fb => fb.style.display = hasPhoto ? 'none' : '');
                saveModel();
            }

            function readFileAsDataURL(file) {
                return new Promise((resolve, reject) => {
                    const fr = new FileReader();
                    fr.onload = () => resolve(fr.result);
                    fr.onerror = reject;
                    fr.readAsDataURL(file);
                });
            }

            /* Init */
            setAccent(resumeModel.accent || '#0EA5E9');
            setTemplate('t1');

            /* UI */
            swatches.forEach(s => s.addEventListener('click', () => setAccent(s.dataset.color)));
            hexInput.addEventListener('change', () => setAccent(hexInput.value));
            templBtns.forEach(b => b.addEventListener('click', () => setTemplate(b.dataset.template)));
            toggleEdit.addEventListener('change', applyEditMode);
            btnReset.addEventListener('click', resetEdits);
            btnPhoto.addEventListener('click', () => photoInput.click());
            photoInput.addEventListener('change', async (e) => {
                const f = e.target.files && e.target.files[0];
                if (!f) return;
                if (!f.type.startsWith('image/')) {
                    alert('Please choose an image file.');
                    return;
                }
                const MB = f.size / (1024 * 1024);
                if (MB > 5 && !confirm('This image is larger than 5MB. Continue?')) {
                    photoInput.value = '';
                    return;
                }
                try {
                    const dataUrl = await readFileAsDataURL(f);
                    resumeModel.photoSrc = dataUrl;
                    applyPhoto();
                } catch (err) {
                    alert('Could not read the selected image.');
                    console.error(err);
                } finally {
                    photoInput.value = '';
                }
            });
            btnPhotoRemove.addEventListener('click', () => {
                resumeModel.photoSrc = '';
                applyPhoto();
            });

            // Update the Apply Resume button to include the current theme
            if (btnApply) {
                const currentTheme = document.documentElement.getAttribute('data-theme');
                let href = btnApply.getAttribute('href');
                // Check if href already has query parameters
                const separator = href.includes('?') ? '&' : '?';
                href += separator + 'theme=' + encodeURIComponent(currentTheme);
                btnApply.setAttribute('href', href);
            }

            btnApply?.addEventListener('click', function(ev) {
                const url = this.getAttribute('href') || 'resume.php';
                setTimeout(() => {
                    try {
                        window.location.assign(url);
                    } catch (e) {}
                }, 0);
            });

            /* Capture helpers */
            function isIOS() {
                return /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
            }

            function isSafari() {
                return /^((?!chrome|android).)*safari/i.test(navigator.userAgent);
            }

            async function ensureImages(node) {
                const imgs = Array.from(node.querySelectorAll('img'));
                await Promise.all(imgs.map(img => img.complete ? Promise.resolve() : new Promise(r => (img.onload = img.onerror = r))));
            }

            function buildSnapshotNode() {
                const cloneStage = stage.cloneNode(true);
                cloneStage.id = 'snapshotStage';
                Object.assign(cloneStage.style, {
                    width: A4_W + 'px',
                    height: A4_H + 'px',
                    boxShadow: 'none',
                    borderRadius: '0',
                    margin: '0',
                    position: 'fixed',
                    left: '-10000px',
                    top: '0',
                    background: '#ffffff',
                    overflow: 'hidden'
                });
                const liveResume = stage.querySelector('.resume');
                const resumeClone = liveResume ? liveResume.cloneNode(true) : document.createElement('div');
                const wrapper = document.createElement('div');
                Object.assign(wrapper.style, {
                    width: A4_W + 'px',
                    height: A4_H + 'px',
                    background: '#ffffff',
                    overflow: 'hidden'
                });
                wrapper.appendChild(resumeClone);
                cloneStage.innerHTML = '';
                cloneStage.appendChild(wrapper);
                (function enforce(el) {
                    const resume = el.querySelector('.resume');
                    if (!resume) return;
                    resume.style.minHeight = A4_H + 'px';
                    resume.style.height = '100%';
                    if (resume.classList.contains('t1')) {
                        resume.style.display = 'grid';
                        resume.style.gridTemplateColumns = '280px 1fr';
                    }
                    if (resume.classList.contains('t2')) {
                        resume.style.display = 'flex';
                        resume.style.flexDirection = 'column';
                        const body = resume.querySelector('.body');
                        if (body) body.style.flex = '1 1 auto';
                    }
                    if (resume.classList.contains('t3')) {
                        resume.style.display = 'grid';
                        resume.style.gridTemplateColumns = '300px 1fr';
                    }
                })(cloneStage);
                document.body.appendChild(cloneStage);
                return cloneStage;
            }

            async function renderWithHtml2Canvas(node) {
                if (typeof html2canvas !== 'function') throw new Error('html2canvas not loaded');
                await new Promise(r => requestAnimationFrame(() => requestAnimationFrame(r)));
                return html2canvas(node, {
                    scale: 2,
                    useCORS: true,
                    backgroundColor: '#ffffff',
                    logging: false,
                    imageTimeout: 15000
                });
            }

            async function renderBlobWithDomToImage(node) {
                if (!window.domtoimage) throw new Error('dom-to-image-more not loaded');
                return window.domtoimage.toBlob(node, {
                    cacheBust: true,
                    bgcolor: '#ffffff',
                    width: A4_W,
                    height: A4_H
                });
            }

            async function captureA4() {
                const snap = buildSnapshotNode();
                try {
                    await ensureImages(snap);
                    try {
                        const canvas = await renderWithHtml2Canvas(snap);
                        return {
                            kind: 'canvas',
                            value: canvas
                        };
                    } catch (e) {
                        console.warn('html2canvas failed, using dom-to-image:', e);
                        const blob = await renderBlobWithDomToImage(snap);
                        return {
                            kind: 'blob',
                            value: blob
                        };
                    }
                } finally {
                    document.body.removeChild(snap);
                }
            }

            /* Downloads */
            btnPNG?.addEventListener('click', async () => {
                try {
                    const out = await captureA4();
                    const needsNewTab = isIOS() || isSafari();
                    if (out.kind === 'canvas') {
                        const url = out.value.toDataURL('image/png');
                        if (needsNewTab) window.open(url, '_blank');
                        else {
                            const a = document.createElement('a');
                            a.href = url;
                            a.download = 'resume.png';
                            a.click();
                        }
                    } else {
                        const url = URL.createObjectURL(out.value);
                        if (needsNewTab) window.open(url, '_blank');
                        else {
                            const a = document.createElement('a');
                            a.href = url;
                            a.download = 'resume.png';
                            a.click();
                        }
                        URL.revokeObjectURL(url);
                    }
                } catch (err) {
                    alert('Could not generate PNG.\n' + (err?.message || err));
                    console.error(err);
                }
            });

            btnPDF?.addEventListener('click', async () => {
                try {
                    const out = await captureA4();
                    const {
                        jsPDF
                    } = window.jspdf || {};
                    const PDFCtor = jsPDF || (window.jspdf && window.jspdf.jsPDF);
                    if (!PDFCtor) throw new Error('jsPDF not loaded');
                    const pdf = new PDFCtor('p', 'mm', 'a4');
                    const pageW = pdf.internal.pageSize.getWidth();
                    const pageH = pdf.internal.pageSize.getHeight();
                    const needsNewTab = isIOS() || isSafari();
                    if (out.kind === 'canvas') {
                        const imgData = out.value.toDataURL('image/png');
                        pdf.addImage(imgData, 'PNG', 0, 0, pageW, pageH, undefined, 'FAST');
                    } else {
                        const blobUrl = URL.createObjectURL(out.value);
                        const img = new Image();
                        img.src = blobUrl;
                        await new Promise(r => (img.onload = img.onerror = r));
                        const c2 = document.createElement('canvas');
                        c2.width = img.naturalWidth;
                        c2.height = img.naturalHeight;
                        c2.getContext('2d').drawImage(img, 0, 0);
                        const imgData = c2.toDataURL('image/png');
                        pdf.addImage(imgData, 'PNG', 0, 0, pageW, pageH, undefined, 'FAST');
                        URL.revokeObjectURL(blobUrl);
                    }
                    if (needsNewTab) window.open(pdf.output('bloburl'), '_blank');
                    else pdf.save('resume.pdf');
                } catch (err) {
                    alert('Could not generate PDF.\n' + (err?.message || err));
                    console.error(err);
                }
            });
        })();
    </script>
</body>

</html>