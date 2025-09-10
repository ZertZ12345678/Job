<?php
require_once "connect.php";
if (session_status() === PHP_SESSION_NONE) session_start();
/* ===== Auth guard ===== */
$company_id = $_SESSION['company_id'] ?? null;
if (!$company_id) {
  header("Location: login.php");
  exit;
}
/* ===== Options ===== */
$LOGO_DIR = "company_logos/";
/* ===== Helpers ===== */
function e($v)
{
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
function name_initials($name)
{
  $name = trim(preg_replace('/\s+/', ' ', (string)$name));
  if ($name === '') return 'U';
  $parts = explode(' ', $name);
  $first = mb_substr($parts[0], 0, 1, 'UTF-8');
  $last  = count($parts) > 1 ? mb_substr(end($parts), 0, 1, 'UTF-8') : '';
  return mb_strtoupper($first . $last, 'UTF-8');
}
if (!function_exists('safe_truncate')) {
  function safe_truncate($text, $limit = 160, $ellipsis = '…')
  {
    $text = (string)($text ?? '');
    if (function_exists('mb_strimwidth')) return mb_strimwidth($text, 0, $limit, $ellipsis);
    return (strlen($text) > $limit) ? substr($text, 0, $limit - strlen($ellipsis)) . $ellipsis : $text;
  }
}
/* ===== Company display info ===== */
$company_name = $company_logo = $company_member = '';
try {
  $st = $pdo->prepare("SELECT company_name, logo, member FROM companies WHERE company_id=? LIMIT 1");
  $st->execute([$company_id]);
  if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
    $company_name   = (string)($row['company_name'] ?? '');
    $company_logo   = (string)($row['logo'] ?? '');
    $company_member = (string)($row['member'] ?? 'normal');
  }
} catch (PDOException $e) {
}
$logo_src = '';
if ($company_logo !== '') {
  $logo_src = preg_match('~^https?://~i', $company_logo) ? $company_logo : $LOGO_DIR . ltrim($company_logo, '/');
}
/* ===== Pricing / membership preview ===== */
require_once "pricing.php";
$totalPosts = 0;
try {
  $cst = $pdo->prepare("SELECT COUNT(*) FROM jobs WHERE company_id = ?");
  $cst->execute([$company_id]);
  $totalPosts = (int)$cst->fetchColumn();
} catch (PDOException $e) {
  $totalPosts = 0;
}
list($rateNext, $tierNext) = jh_company_discount_for_posts($totalPosts + 1);
$feeNext = jh_price_after_discount(JH_BASE_FEE, $rateNext);
$badgeClass = match (strtolower($company_member)) {
  'gold'     => 'bg-warning text-dark',
  'platinum' => 'bg-secondary',
  'diamond'  => 'bg-info text-dark',
  default    => 'bg-light text-dark'
};
/* ===== Applications (for inbox) ===== */
$apps = [];
try {
  $q = "SELECT a.application_id, a.status, a.applied_at, a.resume,
               u.user_id, u.full_name, u.email, u.phone, u.address AS location, u.profile_picture,
               j.job_id, j.job_title
        FROM application a
        JOIN jobs j ON a.job_id=j.job_id
        JOIN users u ON a.user_id=u.user_id
        WHERE j.company_id=?
        ORDER BY a.applied_at DESC";
  $st = $pdo->prepare($q);
  $st->execute([$company_id]);
  $apps = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  $apps = [];
}
/* ===== Inbox read state (session) ===== */
$_SESSION['company_app_read'] = $_SESSION['company_app_read'] ?? [];
$readMap = &$_SESSION['company_app_read'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (isset($_POST['mark_all_company'])) {
    $_SESSION['c_mark_all_pending'] = 1;
    header("Location: company_home.php?inbox=1");
    exit;
  }
  if (isset($_POST['mark_one_company'])) {
    $aid = (int)$_POST['mark_one_company'];
    if ($aid > 0) $readMap[$aid] = 1;
    header("Location: company_home.php?inbox=1");
    exit;
  }
}
if (!empty($_SESSION['c_mark_all_pending'])) {
  foreach ($apps as $a) $readMap[(int)$a['application_id']] = 1;
  $_SESSION['c_just_marked_all'] = 1;
  unset($_SESSION['c_mark_all_pending']);
}
$did_mark_all = !empty($_SESSION['c_just_marked_all']);
unset($_SESSION['c_just_marked_all']);
$badge_count = 0;
foreach ($apps as $a) if (empty($readMap[(int)$a['application_id']])) $badge_count++;
$open_inbox = (isset($_GET['inbox']) && $_GET['inbox'] == '1');
/* ===== Search filters ===== */
$qtxt = trim($_GET['q'] ?? '');
$jt   = trim($_GET['jt'] ?? '');
$isSearch = (isset($_GET['csearch']) || isset($_GET['q']) || isset($_GET['jt']));
if ($jt !== '' && !in_array($jt, ['Software', 'Network'], true)) $jt = '';
$conds  = ["j.company_id = ?"];
$params = [$company_id];
if ($qtxt !== '') {
  $conds[] = "j.job_title LIKE ?";
  $params[] = "%{$qtxt}%";
}
if ($jt   !== '') {
  $conds[] = "j.job_type = ?";
  $params[] = $jt;
}
$whereSql = "WHERE " . implode(" AND ", $conds);
/* ============================================================
   Featured Jobs (9-by-default + More Posts / Show Less)
   ============================================================ */
$jobs = [];
/* Collapsed vs All view */
$show_all = (isset($_GET['all']) && $_GET['all'] === '1');
/* When collapsed, fetch 10 so we can show 9 and know if "more" exists */
$limit = $show_all ? 300 : 10;
/* Helper: build URL that preserves current filters */
function build_url_with($overrides = [])
{
  $qs = $_GET;
  foreach ($overrides as $k => $v) {
    if ($v === null) {
      unset($qs[$k]);
    } else {
      $qs[$k] = $v;
    }
  }
  // keep search mode stable for consistency
  if (!isset($qs['csearch'])) $qs['csearch'] = '1';
  $qstr = http_build_query($qs);
  return 'company_home.php' . ($qstr ? ('?' . $qstr) : '');
}
try {
  $sql = "SELECT j.job_id,j.job_title,j.job_description,j.location,j.status,j.posted_at,
                 c.company_name,c.logo
          FROM jobs j
          JOIN companies c ON c.company_id=j.company_id
          $whereSql
          ORDER BY CASE j.status WHEN 'Active' THEN 1 WHEN 'Inactive' THEN 2 WHEN 'Closed' THEN 3 ELSE 4 END,
                   j.posted_at DESC
          LIMIT $limit";
  $st = $pdo->prepare($sql);
  $st->execute($params);
  $jobs = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  $jobs = [];
}
/* Derived display list and "more" flag */
$jobsToDisplay = $show_all ? $jobs : array_slice($jobs, 0, 9);
$hasMore = !$show_all && count($jobs) > 9;
/* ===== Membership preview modal trigger on login ===== */
$show_member_after_login = (int)($_SESSION['show_member_after_login'] ?? 0);
unset($_SESSION['show_member_after_login']);
/* ===== Company Feedback (CSRF + POST) ===== */
$company_name = $company_logo = $company_member = $company_email = '';
try {
  $st = $pdo->prepare("SELECT company_name, email, logo, member FROM companies WHERE company_id=? LIMIT 1");
  $st->execute([$company_id]);
  if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
    $company_name   = (string)($row['company_name'] ?? '');
    $company_email  = (string)($row['email'] ?? '');
    $company_logo   = (string)($row['logo'] ?? '');
    $company_member = (string)($row['member'] ?? 'normal');
  }
} catch (PDOException $e) {
}
if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf'];
$fb_success = "";
$fb_error   = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'company_feedback') {
  $token = $_POST['csrf'] ?? '';
  if (!hash_equals($_SESSION['csrf'], $token)) {
    $fb_error = "Invalid request. Please refresh and try again.";
  } else {
    $fb_name    = trim($_POST['fb_name'] ?? $company_name);
    $fb_email   = trim($_POST['fb_email'] ?? $company_email);
    $fb_message = trim($_POST['fb_message'] ?? '');
    $hp         = trim($_POST['fb_hp'] ?? ''); // honeypot
    if ($hp !== '') {
      $fb_error = "Spam detected.";
    } elseif ($fb_message === '') {
      $fb_error = "Please write your feedback.";
    } elseif (mb_strlen($fb_message) > 4000) {
      $fb_error = "Feedback is too long (max 4000 chars).";
    } else {
      try {
        $stmt = $pdo->prepare("
          INSERT INTO feedback (user_id, company_id, name, email, message)
          VALUES (NULL, ?, ?, ?, ?)
        ");
        $ok = $stmt->execute([(int)$company_id, $fb_name, $fb_email, $fb_message]);
        $fb_success = $ok ? "Thanks! Your feedback was sent." : "Could not save feedback. Please try again.";
      } catch (PDOException $e) {
        $fb_error = "Database error. Please try again later.";
      }
    }
  }
}
/* ===== Company Feedback list (same behaviour as user home) ===== */
$show_all_feedback = (isset($_GET['allfb']) && $_GET['allfb'] === '1');
$feedbacks = [];
try {
  $sql = "SELECT name, message, submitted_at FROM feedback ORDER BY submitted_at DESC";
  if (!$show_all_feedback) {
    $sql .= " LIMIT 5";
  }
  $fbst = $pdo->query($sql);
  $feedbacks = $fbst->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  $feedbacks = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>JobHive | Company Home</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <style>
    :root {
      /* Light mode variables */
      --jh-gold: #ffaa2b;
      --jh-gold-2: #ffc107;
      --jh-dark: #1a202c;
      --bg-color: #f8fafc;
      --text-color: #334155;
      --card-bg: #ffffff;
      --border-color: rgba(15, 23, 42, 0.06);
      --header-bg: #ffffff;
      --footer-bg: var(--jh-dark);
      --input-bg: #ffffff;
      --button-bg: var(--jh-gold-2);
      --button-text: #ffffff;
      --link-color: var(--jh-gold);
      --section-bg: #f8fafc;
      --card-shadow: 0 8px 32px rgba(0, 0, 0, .06);
      --transition-speed: 0.3s;
      --bg-tertiary: #f3f4f6;
    }

    /* Dark mode variables */
    [data-theme="dark"] {
      --bg-color: #121212;
      --text-color: #e0e0e0;
      --card-bg: #1e1e1e;
      --border-color: rgba(255, 255, 255, 0.1);
      --header-bg: #1a1a1a;
      --footer-bg: #0d0d0d;
      --input-bg: #2d2d2d;
      --button-bg: var(--jh-gold-2);
      --button-text: #000000;
      --link-color: var(--jh-gold);
      --section-bg: #1a1a1a;
      --card-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
      --bg-tertiary: #2d2d2d;
    }

    /* Global transitions */
    body,
    .navbar,
    .card,
    .footer,
    .form-control,
    .form-select,
    .btn {
      transition: background-color var(--transition-speed) ease,
        color var(--transition-speed) ease,
        border-color var(--transition-speed) ease,
        box-shadow var(--transition-speed) ease;
    }

    /* Base styles */
    body {
      background: var(--bg-color);
      color: var(--text-color);
    }

    /* Theme Toggle Button - matching about.php */
    .theme-toggle {
      background: transparent;
      border: 1px solid var(--border-color);
      color: var(--text-color);
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

    /* Navbar styling for dark/light mode */
    .navbar {
      background-color: var(--header-bg) !important;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    [data-theme="dark"] .navbar {
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
    }

    .navbar-brand {
      color: var(--text-color) !important;
    }

    .navbar-nav .nav-link {
      color: var(--text-color) !important;
    }

    .navbar-toggler {
      border-color: var(--border-color) !important;
    }

    .navbar-toggler-icon {
      background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%2333, 0.75)' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e") !important;
    }

    [data-theme="dark"] .navbar-toggler-icon {
      background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%23255, 255, 255, 0.75)' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e") !important;
    }

    .navbar .avatar {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      background: #fff3cd;
      color: #ff8c00;
      font-weight: 700;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border: 1px solid rgba(0, 0, 0, .06)
    }

    .navbar .avatar-img {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      border: 1px solid rgba(0, 0, 0, .06);
      object-fit: cover;
      background: #fff
    }

    .navbar-nav .nav-item:not(.dropdown) .nav-link {
      position: relative;
      padding-bottom: 4px;
      transition: color .2s;
      color: var(--text-color) !important;
    }

    .navbar-nav .nav-item:not(.dropdown) .nav-link::after {
      content: "";
      position: absolute;
      left: 0;
      bottom: 0;
      width: 0;
      height: 2px;
      background-color: var(--jh-gold);
      transition: width .25s
    }

    .navbar-nav .nav-item:not(.dropdown) .nav-link:hover::after {
      width: 100%
    }

    .hero-section {
      padding: 56px 0 14px;
      text-align: center;
      background-color: var(--section-bg);
    }

    .search-bar {
      max-width: 920px;
      margin: 14px auto 0;
      padding: 1rem 1.25rem;
      background: var(--card-bg);
      border-radius: 2rem;
      box-shadow: var(--card-shadow);
      display: flex;
      flex-direction: column;
      gap: .9rem
    }

    .search-row {
      display: flex;
      flex-wrap: wrap;
      gap: .75rem;
      align-items: center;
      margin-top: .1rem
    }

    .search-bar .form-control {
      min-height: 52px;
      border-radius: .8rem;
      font-size: 1rem;
      padding: .65rem .9rem;
      background-color: var(--input-bg);
      color: var(--text-color);
      border: 1px solid var(--border-color);
    }

    .search-bar .form-control::placeholder {
      color: #6c757d !important;
      opacity: 0.7;
    }

    [data-theme="dark"] .search-bar .form-control::placeholder {
      color: #adb5bd !important;
      opacity: 0.8;
    }

    .btn-search {
      min-width: 140px;
      min-height: 48px;
      border-radius: .8rem;
      background: var(--button-bg);
      color: var(--button-text);
      border: none;
      font-weight: 600;
      font-size: 1rem;
      padding: 0 .9rem
    }

    .btn-search:hover {
      background: #ff9800;
      color: var(--button-text)
    }

    .popular-btn {
      border: 1.6px solid var(--jh-gold-2);
      color: var(--jh-gold-2);
      background: var(--card-bg);
      font-size: .98rem;
      border-radius: .55rem;
      padding: .3rem 1.15rem;
      font-weight: 500;
      text-decoration: none;
    }

    .popular-btn:hover {
      background: rgba(255, 193, 7, 0.1);
      color: var(--jh-gold-2);
      border-color: var(--jh-gold-2);
      text-decoration: none;
    }

    .badge-dot {
      position: absolute;
      top: -6px;
      right: -6px;
      font-size: .70rem
    }

    .inbox-panel {
      position: fixed;
      inset: 0 0 0 auto;
      width: 50vw;
      max-width: 900px;
      min-width: 360px;
      background: var(--card-bg);
      box-shadow: -12px 0 28px rgba(0, 0, 0, .08);
      transform: translateX(100%);
      transition: transform .28s;
      display: flex;
      flex-direction: column;
      z-index: 1080;
      color: var(--text-color);
    }

    [data-theme="dark"] .inbox-panel {
      box-shadow: -12px 0 28px rgba(0, 0, 0, .3);
    }

    .inbox-panel.open {
      transform: translateX(0)
    }

    .inbox-header {
      padding: 14px 18px;
      border-bottom: 1px solid var(--border-color);
      display: flex;
      align-items: center;
      justify-content: space-between
    }

    .inbox-body {
      padding: 14px 16px;
      overflow-y: auto;
      height: 100%;
      scrollbar-width: none
    }

    .inbox-body::-webkit-scrollbar {
      display: none
    }

    .backdrop {
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, .25);
      opacity: 0;
      pointer-events: none;
      transition: opacity .28s;
      z-index: 1079
    }

    .backdrop.show {
      opacity: 1;
      pointer-events: auto
    }

    .app-card {
      border: 1px solid var(--border-color);
      border-radius: .75rem;
      background-color: var(--card-bg);
    }

    .app-card .card-body {
      padding: .85rem .9rem
    }

    .app-card.unread {
      background: #fffdf5;
      border-left: 4px solid #ffc107
    }

    [data-theme="dark"] .app-card.unread {
      background: rgba(255, 193, 7, 0.1);
      border-left: 4px solid #ffc107;
    }

    .app-card.read {
      background: var(--section-bg);
      border-left: 4px solid var(--border-color);
    }

    .status-badge {
      font-size: .82rem
    }

    .app-photo {
      width: 50px;
      height: 50px;
      border-radius: 50%;
      object-fit: cover;
      background: var(--card-bg);
      border: 1px solid var(--border-color);
    }

    .avatar-initials {
      width: 50px;
      height: 50px;
      border-radius: 50%;
      background: #fff4d6;
      color: #ff8a00;
      border: 1px solid #ffe5a3;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 800;
      letter-spacing: .5px;
      font-size: .95rem;
      flex: 0 0 50px
    }

    /* Application notification text visibility */
    .app-info .fw-semibold {
      color: var(--text-color) !important;
      font-weight: 600 !important;
    }

    .app-info .text-muted {
      color: var(--text-color) !important;
      opacity: 0.8 !important;
    }

    .app-info small.text-muted {
      color: var(--text-color) !important;
      opacity: 0.75 !important;
    }

    /* Notification card specific styling */
    .app-card.unread .fw-semibold {
      color: #333 !important;
    }

    [data-theme="dark"] .app-card.unread .fw-semibold {
      color: #f0f0f0 !important;
    }

    .app-card.read .fw-semibold {
      color: #333 !important;
    }

    [data-theme="dark"] .app-card.read .fw-semibold {
      color: #e0e0e0 !important;
    }

    /* Status badge colors */
    .app-card .status-badge.bg-warning {
      background-color: #ffc107 !important;
      color: #212529 !important;
    }

    .app-card .status-badge.bg-success {
      background-color: #198754 !important;
      color: #ffffff !important;
    }

    .app-card .status-badge.bg-secondary {
      background-color: #6c757d !important;
      color: #ffffff !important;
    }

    /* Application timestamp */
    .app-card .small.text-muted {
      color: #6c757d !important;
    }

    [data-theme="dark"] .app-card .small.text-muted {
      color: #adb5bd !important;
    }

    /* Application details text */
    .app-card .text-muted {
      color: #6c757d !important;
    }

    [data-theme="dark"] .app-card .text-muted {
      color: #adb5bd !important;
    }

    /* Email and phone links */
    .app-card .text-muted small a {
      color: #0d6efd !important;
    }

    [data-theme="dark"] .app-card .text-muted small a {
      color: #6ea8fe !important;
    }

    .job-card {
      border: 0;
      border-radius: 1.25rem;
      background-color: var(--card-bg);
      box-shadow: var(--card-shadow);
    }

    .job-card .logo {
      width: 56px;
      height: 56px;
      object-fit: cover;
      border-radius: .75rem;
      background: var(--card-bg);
      border: 1px solid var(--border-color);
    }

    .job-card h5 {
      color: var(--text-color) !important;
    }

    .job-card small {
      color: var(--text-color) !important;
      opacity: 0.8;
    }

    .job-card .text-muted {
      color: var(--text-color) !important;
      opacity: 0.7;
    }

    .job-badge {
      font-size: .82rem;
      background-color: var(--card-bg) !important;
      color: var(--text-color) !important;
      border: 1px solid var(--border-color) !important;
    }

    [data-theme="dark"] .job-badge.bg-success {
      background-color: #198754 !important;
      color: white !important;
    }

    .member-highlight {
      background: linear-gradient(135deg, #e8f7ff, #fff);
      border: 1px solid #bfe7ff;
      border-radius: 1rem
    }

    [data-theme="dark"] .member-highlight {
      background: linear-gradient(135deg, #1a1a1a, #2d2d2d);
      border: 1px solid rgba(191, 231, 255, 0.3);
    }

    .modal.fade .modal-dialog {
      transform: translateY(18px);
      transition: transform .28s ease, opacity .28s ease
    }

    .modal.show .modal-dialog {
      transform: none
    }

    [data-theme="dark"] .modal-content {
      background-color: var(--card-bg);
      color: var(--text-color);
      border: 1px solid var(--border-color);
    }

    [data-theme="dark"] .modal-header,
    [data-theme="dark"] .modal-footer {
      border-color: var(--border-color);
    }

    [data-theme="dark"] .btn-close {
      filter: invert(1) grayscale(100%) brightness(200%);
    }

    .app-actions {
      display: flex;
      gap: .42rem;
      flex-wrap: wrap;
      align-items: center
    }

    .btn-icon-sm {
      padding: .28rem .55rem;
      font-size: .84rem;
      line-height: 1
    }

    .btn-detail {
      background: #0dcaf0;
      color: #fff;
      border: 0
    }

    .btn-detail:hover {
      background: #0bb8db;
      color: #fff
    }

    .footer {
      background: var(--footer-bg);
      color: #e9ecef;
      padding: 40px 0 16px;
      flex-shrink: 0;
      margin-top: 24px
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

    /* Dark mode adjustments */
    [data-theme="dark"] .text-muted {
      color: #adb5bd !important;
    }

    [data-theme="dark"] .alert-danger {
      background-color: #2d0b0b;
      border-color: #4a1010;
      color: #f8d7da;
    }

    [data-theme="dark"] .bg-light {
      background-color: #1a1a1a !important;
    }

    [data-theme="dark"] .text-dark {
      color: #e0e0e0 !important;
    }

    [data-theme="dark"] .border {
      border-color: rgba(255, 255, 255, 0.1) !important;
    }

    [data-theme="dark"] .list-group-item {
      background-color: var(--card-bg);
      color: var(--text-color);
      border-color: var(--border-color);
    }

    [data-theme="dark"] .btn-outline-warning {
      color: #ffc107 !important;
      border-color: #ffc107 !important;
    }

    [data-theme="dark"] .btn-outline-warning:hover {
      background-color: #ffc107 !important;
      color: #000000 !important;
    }

    /* Chatbot Styles */
    #chatbot-messages {
      font-size: 0.9rem;
      background-color: var(--card-bg);
      color: var(--text-color);
    }

    .chatbot-message {
      padding: 8px 12px;
      border-radius: 18px;
      margin-bottom: 10px;
      max-width: 80%;
      word-wrap: break-word;
    }

    .chatbot-user {
      background-color: var(--jh-gold-2);
      color: var(--button-text);
      margin-left: auto;
      text-align: right;
    }

    .chatbot-bot {
      background-color: var(--section-bg);
      color: var(--text-color);
    }

    .chatbot-timestamp {
      font-size: 0.7rem;
      color: var(--text-color);
      opacity: 0.7;
      margin-top: 4px;
    }

    #chatbot-input {
      background-color: var(--input-bg);
      color: var(--text-color);
      border: 1px solid var(--border-color);
    }

    #chatbot-input:focus {
      box-shadow: none;
      border-color: var(--jh-gold-2);
    }

    /* Chatbot Button Styles */
    .chatbot-buttons {
      display: flex;
      flex-wrap: wrap;
      gap: 5px;
      margin-top: 8px;
    }

    .chatbot-buttons .btn {
      font-size: 0.8rem;
      padding: 6px 12px;
      border-radius: 4px;
      background-color: var(--jh-gold-2) !important;
      color: var(--button-text) !important;
      border: 1px solid var(--jh-gold-2);
      font-weight: 500;
    }

    .chatbot-buttons .btn:hover {
      background-color: #ffca2c !important;
      border-color: #ffc720;
      color: var(--button-text) !important;
    }

    /* Chatbot Animations */
    @keyframes pulse {
      0% {
        box-shadow: 0 0 0 0 rgba(255, 193, 7, 0.7);
      }

      70% {
        box-shadow: 0 0 0 10px rgba(255, 193, 7, 0);
      }

      100% {
        box-shadow: 0 0 0 0 rgba(255, 193, 7, 0);
      }
    }

    @keyframes slideIn {
      from {
        transform: translateX(100%);
        opacity: 0;
      }

      to {
        transform: translateX(0);
        opacity: 1;
      }
    }

    @keyframes fadeIn {
      from {
        opacity: 0;
        transform: translateY(10px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    @keyframes bounce {

      0%,
      20%,
      50%,
      80%,
      100% {
        transform: translateY(0);
      }

      40% {
        transform: translateY(-10px);
      }

      60% {
        transform: translateY(-5px);
      }
    }

    #chatbot-toggle {
      animation: pulse 2s infinite;
    }

    #chatbot-window {
      animation: slideIn 0.3s ease-out;
      background-color: var(--card-bg);
      border: 1px solid var(--border-color);
    }

    .chatbot-message {
      animation: fadeIn 0.3s ease-out;
    }

    .chatbot-message.chatbot-user {
      animation: bounce 0.5s ease-out;
    }

    #chatbot-toggle:hover {
      animation: none;
    }

    .chatbot-typing {
      display: inline-block;
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background-color: var(--text-color);
      margin: 0 2px;
      animation: typing 1.4s infinite;
    }

    .chatbot-typing:nth-child(2) {
      animation-delay: 0.2s;
    }

    .chatbot-typing:nth-child(3) {
      animation-delay: 0.4s;
    }

    @keyframes typing {

      0%,
      60%,
      100% {
        transform: translateY(0);
        opacity: 0.7;
      }

      30% {
        transform: translateY(-10px);
        opacity: 1;
      }
    }

    /* Floating animation for chatbot button */
    #chatbot-toggle {
      position: fixed;
      bottom: 20px;
      right: 20px;
      width: 60px;
      height: 60px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 1000;
      box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
      transition: transform 0.3s ease, box-shadow 0.3s ease;
      background-color: var(--jh-gold-2);
      color: var(--button-text);
    }

    #chatbot-toggle:hover {
      transform: scale(1.1);
      box-shadow: 0 6px 12px rgba(0, 0, 0, 0.3);
    }

    #chatbot-toggle:active {
      transform: scale(0.95);
    }

    /* Notification badge animation */
    @keyframes badgeBounce {

      0%,
      100% {
        transform: scale(1);
      }

      50% {
        transform: scale(1.2);
      }
    }

    #chatbot-toggle .badge {
      animation: badgeBounce 2s infinite;
    }

    /* Chatbot Continuous Bounce Animation */
    @keyframes continuousBounce {

      0%,
      100% {
        transform: translateY(0);
      }

      50% {
        transform: translateY(-15px);
      }
    }

    #chatbot-toggle {
      position: fixed;
      bottom: 20px;
      right: 20px;
      width: 60px;
      height: 60px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 1000;
      box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
      transition: box-shadow 0.3s ease;
      animation: continuousBounce 2s infinite;
    }

    #chatbot-toggle:hover {
      box-shadow: 0 6px 12px rgba(0, 0, 0, 0.3);
    }

    #chatbot-toggle:active {
      transform: scale(0.95);
    }

    /* Pause animation when chat is open */
    #chatbot-toggle.paused {
      animation-play-state: paused;
    }

    /* Enhanced bounce animation */
    @keyframes continuousBounce {

      0%,
      20%,
      50%,
      80%,
      100% {
        transform: translateY(0);
      }

      40% {
        transform: translateY(-20px);
      }

      60% {
        transform: translateY(-10px);
      }
    }

    #chatbot-toggle {
      animation: continuousBounce 2s infinite ease-in-out;
    }

    /* Floating effect */
    @keyframes float {

      0%,
      100% {
        transform: translateY(0px);
      }

      50% {
        transform: translateY(-10px);
      }
    }

    #chatbot-toggle {
      animation: continuousBounce 2s infinite ease-in-out, float 2s infinite ease-in-out;
    }

    /* Add a subtle glow effect */
    #chatbot-toggle::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      border-radius: 50%;
      background: inherit;
      filter: blur(8px);
      opacity: 0.7;
      z-index: -1;
    }

    @media (max-width:992px) {
      .inbox-panel {
        width: 100vw
      }
    }
  </style>
</head>

<body>
  <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
    <div class="container">
      <a class="navbar-brand fw-bold text-warning" href="company_home.php">JobHive</a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"><span class="navbar-toggler-icon"></span></button>
      <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
        <ul class="navbar-nav align-items-lg-center">
          <li class="nav-item"><a class="nav-link" href="company_home.php">Home</a></li>
          <li class="nav-item"><a class="nav-link" href="c_dashboard.php">Dashboard</a></li>
          <!-- Theme Toggle Button -->
          <li class="nav-item">
            <button class="theme-toggle ms-3" id="themeToggle" aria-label="Toggle theme">
              <i class="bi bi-sun-fill" id="themeIcon"></i>
            </button>
          </li>
          <li class="nav-item"><button id="btnMember" class="btn btn-outline-warning ms-2"><i class="bi bi-gem me-1"></i> Membership</button></li>
          <li class="nav-item"><a class="btn btn-warning ms-2 text-white fw-bold" href="post_job.php" style="border-radius:0.6rem;">Post Job</a></li>
          <li class="nav-item ms-2">
            <button id="btnInbox" class="btn btn-outline-secondary position-relative" type="button" title="Applications inbox">
              <i class="bi bi-envelope"></i>
              <?php if ($badge_count > 0): ?>
                <span id="notifBadge" class="badge rounded-pill text-bg-danger badge-dot"><?= $badge_count > 99 ? '99+' : $badge_count ?></span>
              <?php else: ?>
                <span id="notifBadge" class="badge rounded-pill text-bg-danger badge-dot" style="display:none"></span>
              <?php endif; ?>
            </button>
          </li>
          <li class="nav-item dropdown ms-lg-2">
            <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              <?php if ($logo_src !== ''): ?>
                <img src="<?= e($logo_src) ?>" alt="Company" class="avatar-img">
              <?php else: ?>
                <span class="avatar"><?= e(name_initials($company_name)) ?></span>
              <?php endif; ?>
              <span class="d-none d-lg-inline"><?= e($company_name ?: 'Company') ?></span>
              <span class="badge rounded-pill <?= $badgeClass ?> d-none d-lg-inline"><?= e(ucfirst($company_member)) ?></span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
              <li class="px-3 py-1">
                <small class="text-muted">Member tier</small><br>
                <span class="badge rounded-pill <?= $badgeClass ?>"><?= e(ucfirst($company_member)) ?></span>
                <small class="ms-2 text-muted">(posts: <?= $totalPosts ?>)</small>
              </li>
              <li>
                <hr class="dropdown-divider">
              </li>
              <li><a class="dropdown-item" href="company_profile.php">Profile</a></li>
              <li><a class="dropdown-item text-danger" href="logout.php">Logout</a></li>
            </ul>
          </li>
        </ul>
      </div>
    </div>
  </nav>
  <section class="hero-section">
    <div class="container">
      <h1 class="display-6 fw-bold">Welcome back<?= $company_name ? ', ' . e($company_name) : '' ?>!</h1>
      <p class="lead mb-2">Search your postings quickly.</p>
      <form class="search-bar" autocomplete="off" method="get" action="company_home.php">
        <input class="form-control mb-2" type="text" name="q" placeholder="Job title..." value="<?= e($qtxt) ?>">
        <div class="search-row">
          <?php if ($jt !== ''): ?><input type="hidden" name="jt" value="<?= e($jt) ?>"><?php endif; ?>
          <button class="btn btn-search" type="submit" name="csearch" value="1">Search</button>
          <div class="d-flex flex-wrap align-items-center ms-3 gap-2">
            <a href="company_home.php?csearch=1&jt=Software" class="popular-btn<?= $jt === 'Software' ? ' border-2' : '' ?>">Software</a>
            <a href="company_home.php?csearch=1&jt=Network" class="popular-btn<?= $jt === 'Network' ? ' border-2' : '' ?>">Network</a>
            <a href="company_home.php?csearch=1" class="popular-btn<?= $jt === '' ? ' border-2' : '' ?>">All Jobs</a>
          </div>
        </div>
      </form>
    </div>
  </section>
  <div id="backdrop" class="backdrop"></div>
  <aside id="inboxPanel" class="inbox-panel" aria-hidden="true">
    <div class="inbox-header">
      <div class="d-flex align-items-center gap-2">
        <i class="bi bi-envelope text-warning fs-5"></i>
        <h5 class="m-0">Applications</h5>
      </div>
      <div class="d-flex gap-2">
        <form method="post" class="m-0">
          <button name="mark_all_company" value="1" class="btn btn-light btn-sm js-mark-all">Mark all read</button>
        </form>
        <button id="btnCloseInbox" class="btn btn-light btn-sm"><i class="bi bi-x-lg"></i></button>
      </div>
    </div>
    <div class="inbox-body">
      <?php if (empty($apps)): ?>
        <p class="text-muted mb-0">No applications yet.</p>
        <?php else: foreach ($apps as $a):
          $aid = (int)$a['application_id'];
          $isUnread = empty($readMap[$aid]);
          $cardCls = $isUnread ? 'unread' : 'read';
          $pp = $a['profile_picture'] ?? '';
          if ($pp && !preg_match('~^https?://~', $pp) && !preg_match('~^profile_pics/~', $pp)) $pp = 'profile_pics/' . ltrim($pp, '/');
          $initials = name_initials($a['full_name']);
          $st = (string)$a['status'];
          $stCls = ($st === 'Pending') ? 'bg-warning' : (($st === 'Accepted') ? 'bg-success' : 'bg-secondary');
        ?>
          <div class="card app-card <?= $cardCls ?> shadow-sm mb-3" data-id="<?= (int)$a['application_id'] ?>" data-unread="<?= $isUnread ? '1' : '0' ?>">
            <div class="card-body d-flex align-items-start justify-content-between">
              <div class="d-flex">
                <?php if (!empty($pp)): ?>
                  <img class="app-photo me-3" src="<?= e($pp) ?>" alt="photo">
                <?php else: ?>
                  <div class="avatar-initials me-3" title="No photo"><?= e($initials) ?></div>
                <?php endif; ?>
                <div class="flex-grow-1 app-info">
                  <div class="fw-semibold d-flex align-items-center flex-wrap gap-2">
                    <span>Application Submitted — <?= e($a['job_title']) ?></span>
                    <span class="badge status-badge <?= e($stCls) ?>"><?= e($st) ?></span>
                  </div>
                  <div class="small text-muted mb-1"><?= e(date('M d, Y H:i', strtotime($a['applied_at']))) ?></div>
                  <div class="text-muted mb-1"><?= e($a['full_name']) ?> applied<?= $a['location'] ? ' from ' . e($a['location']) : '' ?>.</div>
                  <?php if (!empty($a['email'])): ?>
                    <div class="text-muted small"><i class="bi bi-envelope me-1"></i> <a href="mailto:<?= e($a['email']) ?>"><?= e($a['email']) ?></a></div>
                  <?php endif; ?>
                  <?php if (!empty($a['phone'])): ?>
                    <div class="text-muted small"><i class="bi bi-telephone me-1"></i> <a href="tel:<?= e($a['phone']) ?>"><?= e($a['phone']) ?></a></div>
                  <?php endif; ?>
                </div>
              </div>
              <div class="ms-3 app-actions">
                <a class="btn btn-detail btn-icon-sm"
                  href="app_user_detail.php?application_id=<?= (int)$a['application_id'] ?>">
                  Detail
                </a>
                <?php if ($isUnread): ?>
                  <form method="post" class="m-0 d-inline">
                    <button class="btn btn-light btn-icon-sm js-mark-one"
                      name="mark_one_company"
                      value="<?= (int)$a['application_id'] ?>">
                      Mark read
                    </button>
                  </form>
                <?php else: ?>
                  <button class="btn btn-light btn-icon-sm" disabled>Marked</button>
                <?php endif; ?>
              </div>
            </div>
          </div>
      <?php endforeach;
      endif; ?>
    </div>
  </aside>
  <section class="py-5">
    <div class="container">
      <h2 class="text-center fw-bold mb-4">Featured Jobs</h2>
      <?php if (empty($jobs)): ?>
        <?= $isSearch ? '<div class="alert alert-danger text-center" role="alert">No jobs match your filters.</div>' : '<div class="alert alert-light border text-center" role="alert">No jobs posted yet.</div>' ?>
        <?php else:
        if ($hasMore): ?>
          <div class="text-center mt-4">
            <a class="btn btn-warning" href="<?= e(build_url_with(['all' => '1'])) ?>">
              More Posts
            </a>
          </div>
        <?php elseif ($show_all): ?>
          <div class="text-center mt-4">
            <a class="btn btn-outline-secondary" href="<?= e(build_url_with(['all' => null])) ?>">
              Show Less
            </a>
          </div>
        <?php endif; ?>
        <div class="row g-4">
          <?php foreach ($jobsToDisplay as $job): ?>
            <div class="col-12 col-md-6 col-lg-4">
              <div class="card job-card h-100 shadow-sm">
                <div class="card-body">
                  <div class="d-flex align-items-center mb-3">
                    <?php $logoFile = trim((string)$job['logo']);
                    $logoPath = $logoFile !== '' ? ($LOGO_DIR . $logoFile) : ''; ?>
                    <img class="logo" src="<?= e($logoPath !== '' ? $logoPath : 'https://via.placeholder.com/56') ?>" alt="Company logo" onerror="this.src='https://via.placeholder.com/56'">
                    <div class="ms-3">
                      <h5 class="mb-0"><?= e($job['job_title']) ?></h5>
                      <small class="text-muted"><?= e($job['company_name']) ?></small>
                    </div>
                  </div>
                  <span class="badge bg-light text-dark border job-badge mb-2"><?= e($job['location']) ?></span>
                  <p class="text-muted small mb-3"><?= e(safe_truncate($job['job_description'], 160, '…')) ?></p>
                  <div class="d-flex justify-content-between align-items-center">
                    <?php $status = (string)$job['status'];
                    $cls = ($status === 'Active') ? 'bg-success' : (($status === 'Inactive') ? 'bg-secondary' : 'bg-danger'); ?>
                    <span class="badge job-badge <?= $cls ?>"><?= e($status) ?></span>
                    <a class="btn btn-outline-warning" href="c_job_detail.php?id=<?= (int)$job['job_id'] ?>">Detail</a>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>
  <!-- Recent Feedback Section (same as user home) -->
  <section id="feedback" class="py-5 bg-light">
    <div class="container">
      <h2 class="text-center fw-bold mb-4">Latest Feedback</h2>
      <?php if (empty($feedbacks)): ?>
        <div class="alert alert-secondary text-center">No feedback yet.</div>
      <?php else: ?>
        <div class="list-group shadow-sm mb-3">
          <?php foreach ($feedbacks as $fb): ?>
            <div class="list-group-item">
              <div class="d-flex w-100 justify-content-between">
                <h6 class="mb-1 text-warning"><?= e($fb['name']) ?></h6>
                <small class="text-muted"><?= date('M d, Y H:i', strtotime($fb['submitted_at'])) ?></small>
              </div>
              <p class="mb-1"><?= e($fb['message']) ?></p>
            </div>
          <?php endforeach; ?>
        </div>
        <!-- Buttons: same row, same size -->
        <div class="d-flex justify-content-center gap-2">
          <?php if ($show_all_feedback): ?>
            <a href="company_home.php#feedback" class="btn btn-outline-secondary px-4">Show Less</a>
          <?php else: ?>
            <a href="company_home.php?allfb=1#feedback" class="btn btn-outline-warning px-4">More Feedback</a>
          <?php endif; ?>
          <!-- Open the existing Company Feedback modal -->
          <button type="button" class="btn btn-warning px-4" data-bs-toggle="modal" data-bs-target="#companyFeedbackModal">
            <i class="bi bi-chat-text me-1"></i> Send Feedback
          </button>
        </div>
      <?php endif; ?>
    </div>
  </section>
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
            <li class="mb-2"><a href="all_companies.php">All Companies</a></li>
          </ul>
        </div>
        <div class="col-md-3">
          <h6 class="text-uppercase text-white-50 mb-3">Company</h6>
          <ul class="list-unstyled">
            <li class="mb-2"><a href="faq.php?return=company_home">FAQ</a></li>
            <li class="mb-2"><a href="about.php?return=company_home">About</a></li>
            <li class="mb-2"><a href="privacy.php?return=company_home">Privacy Policy</a></li>
            <li class="mb-2"><a href="terms.php?return=company_home">Terms &amp; Conditions</a></li>
          </ul>
        </div>
        <div class="col-md-3">
          <h6 class="text-uppercase text-white-50 mb-3">Contact</h6>
          <ul class="list-unstyled">
            <li class="mb-2"><i class="bi bi-geo-alt me-2"></i>Yangon, Myanmar</li>
            <li class="mb-2"><i class="bi bi-envelope me-2"></i><a href="mailto:support@jobhive.mm">support@jobhive.mm</a></li>
            <li class="mb-2"><i class="bi bi-telephone me-2"></i><a href="tel:+95957433847">+95 957 433 847</a></li>
            </button>
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
  <div class="modal fade" id="memberModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content member-highlight shadow">
        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title"><i class="bi bi-gem me-1"></i> Grow Faster with Your Membership</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body pt-2">
          <div class="text-center">
            <div class="mb-2">
              <div class="fw-semibold">You're currently on</div>
              <span class="badge rounded-pill <?= $badgeClass ?>"><?= e(ucfirst($company_member)) ?></span>
              <span class="ms-2 text-muted">(<?= $totalPosts ?> posts)</span>
            </div>
            <div class="mb-2">
              <div class="fw-semibold">After your next post</div>
              <div class="fs-6"><?= e(strtoupper($tierNext)) ?> tier</div>
            </div>
            <div class="mb-3">
              <div class="fw-semibold">Next post price</div>
              <div class="fs-5 fw-bold">
                <?= number_format($feeNext) ?> MMK
                <small class="text-muted d-block"> base <?= number_format(JH_BASE_FEE) ?> · save <?= number_format(max(0, JH_BASE_FEE - $feeNext)) ?> MMK (<?= (int)round($rateNext * 100) ?>%) </small>
              </div>
            </div>
            <p class="small text-muted mb-0">The more you post, the more you save — enjoy <strong>10% off after 5 posts</strong>, <strong>15% off after 15 posts</strong>, and <strong>20% off once you reach 25 posts</strong>.</p>
          </div>
          <hr class="my-3">
          <ul class="list-unstyled small m-0 text-muted">
            <li class="mb-1"><i class="bi bi-check2-circle me-1"></i> Transparent pricing before you publish</li>
            <li class="mb-1"><i class="bi bi-check2-circle me-1"></i> Volume-based discounts for ongoing hiring</li>
            <li class="mb-1"><i class="bi bi-check2-circle me-1"></i> Optimized for active recruitment campaigns</li>
          </ul>
        </div>
        <div class="modal-footer border-0 pt-0">
          <a href="post_job.php" class="btn btn-warning"><i class="bi bi-plus-square me-1"></i> Post a Job Now</a>
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>
  <!-- Chatbot UI -->
  <div id="chatbot-container" class="position-fixed bottom-0 end-0 p-3" style="z-index: 1050; width: 350px; max-width: 90vw;">
    <!-- Chat Button -->
    <button id="chatbot-toggle" class="btn btn-warning rounded-circle shadow" style="width: 60px; height: 60px; position: fixed; bottom: 20px; right: 20px;">
      <i class="bi bi-chat-dots-fill fs-4"></i>
    </button>
    <!-- Chat Window -->
    <div id="chatbot-window" class="card shadow-lg border-0 d-none" style="position: fixed; bottom: 90px; right: 20px; width: 350px; max-width: 90vw;">
      <div class="card-header bg-warning text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">JobHive Assistant</h5>
        <button id="chatbot-close" class="btn btn-sm btn-light">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>
      <div id="chatbot-messages" class="card-body" style="height: 300px; overflow-y: auto; background-color: #f8f9fa;">
        <div class="text-center text-muted my-3">
          <i class="bi bi-robot fs-1"></i>
          <p class="mt-2">Hello! I'm your JobHive assistant. How can I help you today?</p>
        </div>
      </div>
      <div class="card-footer">
        <form id="chatbot-form" class="d-flex">
          <input type="text" id="chatbot-input" class="form-control me-2" placeholder="Type your message..." autocomplete="off">
          <button type="submit" class="btn btn-warning">
            <i class="bi bi-send"></i>
          </button>
        </form>
      </div>
    </div>
  </div>
  <!-- Company Feedback Modal -->
  <div class="modal fade" id="companyFeedbackModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <form method="post" class="modal-content">
        <input type="hidden" name="action" value="company_feedback">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <!-- Honeypot -->
        <input type="text" name="fb_hp" value="" style="display:none !important" tabindex="-1" autocomplete="off">
        <div class="modal-header">
          <h5 class="modal-title">
            <i class="bi bi-chat-left-quote me-2 text-warning"></i>
            Send Feedback
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <?php if (!empty($fb_success)): ?>
            <div class="alert alert-success py-2 mb-3"><?= e($fb_success) ?></div>
          <?php elseif (!empty($fb_error)): ?>
            <div class="alert alert-danger py-2 mb-3"><?= e($fb_error) ?></div>
          <?php else: ?>
            <p class="text-muted small">Tell us what we can improve for employers. We read every message.</p>
          <?php endif; ?>
          <div class="mb-3">
            <label class="form-label">Company</label>
            <input type="text" class="form-control" name="fb_name" value="<?= e($company_name) ?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" class="form-control" name="fb_email" value="<?= e($company_email) ?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Message</label>
            <textarea class="form-control" name="fb_message" rows="5" placeholder="Your feedback..." required></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-warning">
            <i class="bi bi-send me-1"></i> Submit
          </button>
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </form>
    </div>
  </div>
  <script>
    // Theme toggle functionality - matching about.php
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
  <script>
    const panel = document.getElementById('inboxPanel');
    const backdrop = document.getElementById('backdrop');
    const btnInbox = document.getElementById('btnInbox');
    const btnClose = document.getElementById('btnCloseInbox');

    function openInbox() {
      panel.classList.add('open');
      backdrop.classList.add('show');
      panel.setAttribute('aria-hidden', 'false');
      document.body.style.overflow = 'hidden'
    }

    function closeInbox() {
      panel.classList.remove('open');
      backdrop.classList.remove('show');
      panel.setAttribute('aria-hidden', 'true');
      document.body.style.overflow = ''
    }
    btnInbox.addEventListener('click', openInbox);
    btnClose.addEventListener('click', closeInbox);
    backdrop.addEventListener('click', closeInbox);
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') closeInbox()
    });
    <?php if ($open_inbox): ?>openInbox();
    (function() {
      const url = new URL(window.location.href);
      url.searchParams.delete('inbox');
      history.replaceState(null, "", url.pathname + (url.searchParams.toString() ? ('?' + url.searchParams.toString()) : ''));
    })();
    <?php endif; ?>
  </script>
  <script>
    (function() {
      const COMPANY_ID = <?= (int)$company_id ?>;
      const STORAGE_KEY = `jh_c_read_${COMPANY_ID}`;
      const DID_MARK_ALL = <?= $did_mark_all ? 'true' : 'false' ?>;
      const getMap = () => {
        try {
          return JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}')
        } catch {
          return {}
        }
      };
      const setMap = (m) => localStorage.setItem(STORAGE_KEY, JSON.stringify(m || {}));
      const isRead = (id) => !!getMap()[id];
      const markOne = (id) => {
        const m = getMap();
        m[id] = 1;
        setMap(m)
      };

      function paintCardAsRead(card) {
        card.classList.remove('unread');
        card.classList.add('read');
        card.setAttribute('data-unread', '0');
        const btn = card.querySelector('.js-mark-one');
        if (btn) {
          btn.textContent = 'Marked';
          btn.disabled = true;
          btn.classList.add('disabled')
        }
      }

      function updateBellBadge() {
        const badge = document.getElementById('notifBadge');
        if (!badge) return;
        let unread = 0;
        document.querySelectorAll('.app-card').forEach(card => {
          const id = card.getAttribute('data-id');
          const domUnread = card.getAttribute('data-unread') === '1';
          if (domUnread && !isRead(id)) unread++;
        });
        if (unread > 0) {
          badge.textContent = unread > 99 ? '99+' : unread;
          badge.style.display = ''
        } else {
          badge.style.display = 'none'
        }
      }

      function applyStorageToUI() {
        document.querySelectorAll('.app-card').forEach(card => {
          const id = card.getAttribute('data-id');
          if (isRead(id)) paintCardAsRead(card)
        });
        updateBellBadge();
      }
      document.addEventListener('click', function(e) {
        const btn = e.target.closest('.js-mark-one');
        if (!btn) return;
        const card = btn.closest('.app-card');
        if (!card) return;
        const id = card.getAttribute('data-id');
        markOne(id);
        paintCardAsRead(card);
        updateBellBadge();
      }, false);
      document.addEventListener('click', function(e) {
        const btn = e.target.closest('.js-mark-all');
        if (!btn) return;
        document.querySelectorAll('.app-card').forEach(card => {
          const id = card.getAttribute('data-id');
          markOne(id);
          paintCardAsRead(card)
        });
        updateBellBadge();
      }, false);
      if (DID_MARK_ALL) {
        document.querySelectorAll('.app-card').forEach(card => {
          const id = card.getAttribute('data-id');
          markOne(id);
          paintCardAsRead(card)
        });
        updateBellBadge();
      }
      document.addEventListener('DOMContentLoaded', applyStorageToUI);
    })();
  </script>
  <script>
    (function() {
      const modalEl = document.getElementById('memberModal');
      if (!modalEl) return;
      const memberModal = new bootstrap.Modal(modalEl, {
        backdrop: 'static',
        keyboard: true
      });
      const btnMember = document.getElementById('btnMember');
      btnMember?.addEventListener('click', () => {
        memberModal.show();
        sessionStorage.setItem('jh_member_shown', '1')
      });
      const JUST_LOGGED_IN = <?= $show_member_after_login ? 'true' : 'false' ?>;
      window.addEventListener('load', () => {
        const hasShown = sessionStorage.getItem('jh_member_shown') === '1';
        if ((JUST_LOGGED_IN || !hasShown)) {
          setTimeout(() => {
            memberModal.show();
            sessionStorage.setItem('jh_member_shown', '1')
          }, 3000)
        }
      });
    })();
  </script>
  <script>
    // Chatbot functionality
    document.addEventListener('DOMContentLoaded', function() {
      const chatbotToggle = document.getElementById('chatbot-toggle');
      const chatbotWindow = document.getElementById('chatbot-window');
      const chatbotClose = document.getElementById('chatbot-close');
      const chatbotForm = document.getElementById('chatbot-form');
      const chatbotInput = document.getElementById('chatbot-input');
      const chatbotMessages = document.getElementById('chatbot-messages');
      // Toggle chat window and control animation
      chatbotToggle.addEventListener('click', function() {
        const isOpen = !chatbotWindow.classList.contains('d-none');
        if (isOpen) {
          // Close the chat window
          chatbotWindow.classList.add('d-none');
          // Resume bounce animation
          chatbotToggle.classList.remove('paused');
        } else {
          // Open the chat window
          chatbotWindow.classList.remove('d-none');
          // Pause bounce animation
          chatbotToggle.classList.add('paused');
          // Reset animation for chat window
          chatbotWindow.style.animation = 'none';
          setTimeout(() => {
            chatbotWindow.style.animation = 'slideIn 0.3s ease-out';
          }, 10);
          // Focus on input
          chatbotInput.focus();
        }
      });
      // Close chat window and resume animation
      chatbotClose.addEventListener('click', function() {
        chatbotWindow.classList.add('d-none');
        chatbotToggle.classList.remove('paused');
      });
      // Function to show typing indicator
      function showTypingIndicator() {
        const typingDiv = document.createElement('div');
        typingDiv.className = 'chatbot-message chatbot-bot';
        typingDiv.id = 'typing-indicator';
        const typingContent = document.createElement('div');
        typingContent.className = 'd-flex align-items-center';
        typingContent.innerHTML = `
          <span class="me-2">Bot is typing</span>
          <span class="chatbot-typing"></span>
          <span class="chatbot-typing"></span>
          <span class="chatbot-typing"></span>
        `;
        typingDiv.appendChild(typingContent);
        chatbotMessages.appendChild(typingDiv);
        chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
        return typingDiv;
      }
      // Function to add message to chat
      function addMessage(message, sender, buttons, image) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `chatbot-message chatbot-${sender}`;
        const now = new Date();
        const timeString = now.toLocaleTimeString([], {
          hour: '2-digit',
          minute: '2-digit'
        });
        // Create message content
        const messageContent = document.createElement('div');
        messageContent.innerHTML = message;
        messageDiv.appendChild(messageContent);
        // Add image if it exists
        if (image && sender === 'bot') {
          const imageContainer = document.createElement('div');
          imageContainer.className = 'mt-2 mb-2 text-center';
          const imageElement = document.createElement('img');
          imageElement.src = image;
          imageElement.className = 'img-fluid rounded';
          imageElement.style.maxWidth = '100%';
          imageElement.style.height = 'auto';
          imageElement.style.maxHeight = '200px';
          imageElement.alt = 'Preview';
          imageContainer.appendChild(imageElement);
          messageDiv.appendChild(imageContainer);
        }
        // Add buttons if they exist
        if (buttons && Array.isArray(buttons) && buttons.length > 0) {
          const buttonsContainer = document.createElement('div');
          buttonsContainer.className = 'chatbot-buttons mt-2';
          buttons.forEach(button => {
            const buttonElement = document.createElement('button');
            buttonElement.className = 'btn btn-warning btn-sm';
            buttonElement.textContent = button.text;
            buttonElement.onclick = function() {
              window.location.href = button.href;
            };
            buttonsContainer.appendChild(buttonElement);
          });
          messageDiv.appendChild(buttonsContainer);
        }
        // Add timestamp
        const timestamp = document.createElement('div');
        timestamp.className = 'chatbot-timestamp';
        timestamp.textContent = timeString;
        messageDiv.appendChild(timestamp);
        chatbotMessages.appendChild(messageDiv);
        chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
        // Add animation for new messages
        messageDiv.style.animation = 'none';
        setTimeout(() => {
          messageDiv.style.animation = `${sender === 'user' ? 'bounce' : 'fadeIn'} 0.5s ease-out`;
        }, 10);
      }
      // Handle form submission
      chatbotForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const message = chatbotInput.value.trim();
        if (message === '') return;
        // Add user message to chat
        addMessage(message, 'user');
        // Clear input
        chatbotInput.value = '';
        // Show typing indicator
        const typingIndicator = showTypingIndicator();
        // Send to server and get response
        fetch('company_chatbot.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'message=' + encodeURIComponent(message)
          })
          .then(response => response.json())
          .then(data => {
            // Remove typing indicator
            if (typingIndicator) {
              typingIndicator.remove();
            }
            if (data.response) {
              addMessage(data.response, 'bot', data.buttons, data.image);
            }
          })
          .catch(error => {
            console.error('Error:', error);
            // Remove typing indicator
            if (typingIndicator) {
              typingIndicator.remove();
            }
            addMessage("Sorry, I'm having trouble responding right now. Please try again later.", 'bot');
          });
      });
    });
    // Show feedback modal if there's a message
    (function() {
      const hasFbMsg = <?= (!empty($fb_success) || !empty($fb_error)) ? 'true' : 'false' ?>;
      if (hasFbMsg) {
        const el = document.getElementById('companyFeedbackModal');
        if (el) new bootstrap.Modal(el).show();
      }
    })();
  </script>
</body>

</html>