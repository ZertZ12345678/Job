<?php
require_once "connect.php";
if (session_status() === PHP_SESSION_NONE) session_start();

/* ===== Guard ===== */
if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit;
}
$user_id   = (int)$_SESSION['user_id'];
$full_name = trim($_SESSION['full_name'] ?? '');
$email     = trim($_SESSION['email'] ?? '');

/* ===== Options ===== */
$PROFILE_DIR = "profile_pics/";
$LOGO_DIR    = "company_logos/";

/* ===== Helpers ===== */
function e($v)
{
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
if (!function_exists('safe_truncate')) {
  function safe_truncate($text, $limit = 160, $ellipsis = '…')
  {
    $text = (string)($text ?? '');
    if (function_exists('mb_strimwidth')) return mb_strimwidth($text, 0, $limit, $ellipsis);
    return (strlen($text) > $limit) ? substr($text, 0, $limit - strlen($ellipsis)) . $ellipsis : $text;
  }
}
function initials($name)
{
  $parts = preg_split('/\s+/', trim((string)$name));
  $ini = '';
  foreach ($parts as $p) {
    if ($p !== '') $ini .= mb_strtoupper(mb_substr($p, 0, 1));
    if (mb_strlen($ini) >= 2) break;
  }
  return $ini ?: 'U';
}

/* ===== Fetch fresh user (now including package) ===== */
$profile_picture = $_SESSION['profile_picture'] ?? null;
$package = $_SESSION['package'] ?? 'normal';
try {
  $st = $pdo->prepare("SELECT full_name,email,profile_picture,package FROM users WHERE user_id=? LIMIT 1");
  $st->execute([$user_id]);
  if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
    $full_name       = $row['full_name'] ?: $full_name;
    $email           = $row['email'] ?: $email;
    $profile_picture = $row['profile_picture'] ?? null;
    $package         = $row['package'] ?: 'normal';

    $_SESSION['full_name']       = $full_name;
    $_SESSION['email']           = $email;
    $_SESSION['profile_picture'] = $profile_picture;
    $_SESSION['package']         = $package;
  } else {
    header("Location: index.php");
    exit;
  }
} catch (PDOException $e) {
}
$is_premium = (strtolower((string)$package) === 'premium');

/* ===== Profile completion (must be 100% to upgrade) ===== */
$REQUIRED = [
  'full_name'        => 'Full name',
  'email'            => 'Email',
  'password'         => 'Password',
  'gender'           => 'Gender',
  'education'        => 'Education',
  'phone'            => 'Phone',
  'address'          => 'Address',
  'b_date'           => 'Birth date',
  'job_category'     => 'Job category',
  'current_position' => 'Current position',
  'profile_picture'  => 'Profile picture'
];
$missing_fields = [];
try {
  $cols = implode(',', array_map(fn($c) => "`$c`", array_keys($REQUIRED)));
  $u = $pdo->prepare("SELECT $cols FROM users WHERE user_id=? LIMIT 1");
  $u->execute([$user_id]);
  $ud = $u->fetch(PDO::FETCH_ASSOC) ?: [];
  foreach ($REQUIRED as $col => $label) {
    $val = $ud[$col] ?? null;
    $ok  = !is_null($val) && trim((string)$val) !== '';
    if (!$ok) $missing_fields[] = $label;
  }
} catch (PDOException $e) {
}
$can_upgrade = empty($missing_fields);

/* ===== POST handlers (session-scoped read state) ===== */
$_SESSION['app_seen_status']    = $_SESSION['app_seen_status']    ?? [];
$_SESSION['session_notif_read'] = $_SESSION['session_notif_read'] ?? [];
$seenApp  = &$_SESSION['app_seen_status'];
$seenSess = &$_SESSION['session_notif_read'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (isset($_POST['mark_all'])) {
    $_SESSION['mark_all_pending'] = 1;
    header("Location: user_home.php?inbox=1");
    exit;
  }
  if (isset($_POST['mark_one']) && isset($_POST['type'])) {
    $type = $_POST['type']; // 'A' or 'S'
    if ($type === 'A') {
      $aid    = (int)($_POST['mark_one'] ?? 0);
      $status = trim($_POST['status_now'] ?? '');
      if ($aid > 0 && ($status === 'Accepted' || $status === 'Rejected')) {
        $seenApp[$aid] = $status;
      }
    } elseif ($type === 'S') {
      $nid = trim($_POST['mark_one'] ?? '');
      if ($nid !== '') $seenSess[$nid] = 1;
    }
    header("Location: user_home.php?inbox=1");
    exit;
  }
}

/* ===== Optional: set past-deadline jobs to Inactive ===== */
try {
  $today = date('Y-m-d');
  $pdo->prepare("UPDATE jobs SET status='Inactive' WHERE status='Active' AND deadline<?")->execute([$today]);
} catch (PDOException $e) {
}

/* ===== Search filters ===== */
$q = '';
$loc = '';
$jt = '';
$isSearch = false;
if (isset($_GET['csearch'])) {
  $q   = trim($_GET['q']   ?? '');
  $loc = trim($_GET['loc'] ?? '');
  $jt  = trim($_GET['jt']  ?? '');
  $isSearch = true;
} else {
  if (isset($_GET['q'])) {
    $q  = trim($_GET['q']  ?? '');
    $isSearch = true;
  }
  if (isset($_GET['loc'])) {
    $loc = trim($_GET['loc'] ?? '');
    $isSearch = true;
  }
  if (isset($_GET['jt'])) {
    $jt = trim($_GET['jt']  ?? '');
    $isSearch = true;
  }
}
if ($jt !== '' && !in_array($jt, ['Software', 'Network'], true)) $jt = '';

$conds = [];
$params = [];
if ($q   !== '') {
  $conds[] = "(j.job_title LIKE ? OR c.company_name LIKE ?)";
  $like = "%{$q}%";
  array_push($params, $like, $like);
}
if ($loc !== '') {
  $conds[] = "j.location LIKE ?";
  $params[] = "%{$loc}%";
}
if ($jt  !== '') {
  $conds[] = "j.job_type = ?";
  $params[] = $jt;
}
$whereSql = $conds ? ("WHERE " . implode(" AND ", $conds)) : "";

/* ===== Jobs ===== */
try {
  $sql = "SELECT j.job_id,j.job_title,j.job_description,j.location,j.status,j.posted_at,
                 c.company_name,c.logo
          FROM jobs j JOIN companies c ON c.company_id=j.company_id
          $whereSql
          ORDER BY CASE j.status WHEN 'Active' THEN 1 WHEN 'Inactive' THEN 2 WHEN 'Closed' THEN 3 ELSE 4 END,
                   j.posted_at DESC
          LIMIT 60";
  $st = $pdo->prepare($sql);
  $st->execute($params);
  $jobs = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  $jobs = [];
}

/* ===== Applications for this user (for notifications) ===== */
try {
  $apq = $pdo->prepare("
    SELECT a.application_id, a.status, a.applied_at, a.job_id,
           j.job_title, c.company_name
    FROM application a
    JOIN jobs j      ON j.job_id = a.job_id
    JOIN companies c ON c.company_id = j.company_id
    WHERE a.user_id = ?
    ORDER BY a.applied_at DESC
  ");
  $apq->execute([$user_id]);
  $apps = $apq->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  $apps = [];
}

/* ===== Build unified inbox ===== */
$items = [];
date_default_timezone_set('Asia/Yangon');
if (!isset($_SESSION['already_notified'])) $_SESSION['already_notified'] = [];
$alreadyNotified = &$_SESSION['already_notified'];

foreach ($apps as $a) {
  $aid = (int)$a['application_id'];
  $stt = (string)$a['status'];
  if ($stt === 'Accepted' || $stt === 'Rejected') {
    $isFirstThisSession = !(isset($alreadyNotified[$aid]) && $alreadyNotified[$aid] === $stt);
    if ($isFirstThisSession) $alreadyNotified[$aid] = $stt;
    $unread = $isFirstThisSession && (!isset($seenApp[$aid]) || $seenApp[$aid] !== $stt);
    $items[] = [
      '_type' => 'A',
      '_id' => (string)$aid,
      '_unread' => $unread,
      'title' => $stt . " — " . $a['job_title'],
      'body' => ($stt === 'Accepted'
        ? "Great news! Your application to {$a['company_name']} for “{$a['job_title']}” was accepted."
        : "Update: Your application to {$a['company_name']} for “{$a['job_title']}” was rejected."),
      'when' => 'Applied: ' . date('M d, Y H:i', strtotime($a['applied_at'])),
      'link' => '',
      'pill' => $unread ? 'New' : 'Read',
      'pillClass' => $unread ? 'text-bg-warning' : 'text-bg-secondary',
      'status_now' => $stt,
    ];
  }
}

/* Session custom notifications */
$sessList = $_SESSION['notifications'] ?? [];
if (is_array($sessList) && $sessList) {
  foreach ($sessList as $n) {
    $nid = (string)($n['id'] ?? '');
    if ($nid === '') continue;
    $unread = empty($seenSess[$nid]);
    $items[] = [
      '_type' => 'S',
      '_id' => $nid,
      '_unread' => $unread,
      'title' => (string)($n['title'] ?? 'Notification'),
      'body' => (string)($n['message'] ?? ''),
      'when' => date('M d, Y H:i', strtotime((string)($n['created_at'] ?? date('Y-m-d H:i:s')))),
      'link' => (string)($n['link'] ?? ''),
      'pill' => $unread ? 'New' : 'Read',
      'pillClass' => $unread ? 'text-bg-primary' : 'text-bg-secondary',
      'status_now' => null,
    ];
  }
}

/* Mark all pending (session only) */
if (!empty($_SESSION['mark_all_pending'])) {
  foreach ($items as $it) {
    if ($it['_type'] === 'A') $seenApp[(int)$it['_id']] = (string)$it['status_now'];
    else $seenSess[(string)$it['_id']] = 1;
  }
  unset($_SESSION['mark_all_pending']);
}

/* Badge + shake */
$badge_count = 0;
foreach ($items as $it) if (!empty($it['_unread'])) $badge_count++;
$prev_badge   = (int)($_SESSION['prev_badge_count'] ?? 0);
$should_shake = $badge_count > $prev_badge;
$_SESSION['prev_badge_count'] = $badge_count;

/* Auto-open inbox param */
$open_inbox = (isset($_GET['inbox']) && $_GET['inbox'] == '1');
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>JobHive | User Home</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <style>
    body {
      background: #f8fafc
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

    .navbar-nav .nav-item:not(.dropdown) .nav-link {
      position: relative;
      padding-bottom: 4px;
      transition: color .2s
    }

    .navbar-nav .nav-item:not(.dropdown) .nav-link::after {
      content: "";
      position: absolute;
      left: 0;
      bottom: 0;
      width: 0;
      height: 2px;
      background-color: #ffaa2b;
      transition: width .25s
    }

    .navbar-nav .nav-item:not(.dropdown) .nav-link:hover::after {
      width: 100%
    }

    .hero-section {
      background: #f8fafc;
      padding: 64px 0 44px;
      text-align: center
    }

    .hero-section h1 {
      font-weight: 700
    }

    .hero-section .lead {
      color: #556
    }

    .search-bar {
      max-width: 920px;
      margin: 26px auto 0;
      padding: 1rem 1.25rem;
      background: #fff;
      border-radius: 2rem;
      box-shadow: 0 8px 32px rgba(0, 0, 0, .06);
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
      padding: .65rem .9rem
    }

    .search-row .form-select {
      min-width: 220px;
      max-width: 260px;
      min-height: 48px;
      border-radius: .8rem;
      font-size: .98rem
    }

    .btn-search {
      min-width: 140px;
      min-height: 48px;
      border-radius: .8rem;
      background: #ffc107;
      color: #fff;
      border: none;
      font-weight: 600;
      font-size: 1rem;
      padding: 0 .9rem
    }

    .btn-search:hover {
      background: #ff9800;
      color: #fff
    }

    .popular-label {
      font-size: 1.05rem;
      color: #22223b;
      font-weight: 600;
      margin-right: 36px
    }

    .popular-tags {
      margin-top: 1rem;
      display: flex;
      gap: .6rem;
      justify-content: center;
      flex-wrap: wrap
    }

    .popular-btn {
      border: 1.6px solid #ffc107;
      color: #ffc107;
      background: #fff;
      font-size: .98rem;
      border-radius: .55rem;
      padding: .3rem 1.15rem;
      font-weight: 500
    }

    .popular-btn:hover {
      background: #fff8ec;
      color: #ff8800;
      border-color: #ff8800
    }

    .job-card {
      border: 0;
      border-radius: 1.25rem
    }

    .job-card .logo {
      width: 56px;
      height: 56px;
      object-fit: cover;
      border-radius: .75rem;
      background: #fff;
      border: 1px solid rgba(0, 0, 0, .05)
    }

    .job-badge {
      font-size: .82rem
    }

    .footer {
      background: #1a202c;
      color: #fff;
      padding: 30px 0 10px;
      text-align: center
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
      background: #fff;
      box-shadow: -12px 0 28px rgba(0, 0, 0, .08);
      transform: translateX(100%);
      transition: transform .28s;
      display: flex;
      flex-direction: column;
      z-index: 1080
    }

    .inbox-panel.open {
      transform: translateX(0)
    }

    .inbox-header {
      padding: 14px 18px;
      border-bottom: 1px solid #eef0f2;
      display: flex;
      align-items: center;
      justify-content: space-between
    }

    .inbox-body {
      padding: 14px 16px;
      overflow-y: auto;
      height: 100%
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

    .notif-card {
      border: 1px solid #e9ecef;
      border-radius: .75rem
    }

    .notif-card .card-body {
      padding: .85rem .9rem
    }

    @media (max-width:992px) {
      .inbox-panel {
        width: 100vw
      }
    }

    .notif-card.unread {
      background: #fffdf5;
      border-left: 4px solid #ffc107
    }

    .notif-card.read {
      background: #f8f9fa;
      border-left: 4px solid #e9ecef
    }

    .notif-chip {
      font-size: .72rem
    }

    @keyframes bell {
      0% {
        transform: rotate(0)
      }

      15% {
        transform: rotate(12deg)
      }

      30% {
        transform: rotate(-10deg)
      }

      45% {
        transform: rotate(8deg)
      }

      60% {
        transform: rotate(-6deg)
      }

      75% {
        transform: rotate(4deg)
      }

      100% {
        transform: rotate(0)
      }
    }

    .btn-bell-shake {
      animation: bell .6s ease-in-out 1;
      transform-origin: 50% 0%
    }

    .premium-highlight {
      background: linear-gradient(135deg, #fff9e6, #fff);
      border: 1px solid #ffe08a;
      border-radius: 1rem;
    }

    .modal.fade .modal-dialog {
      transform: translateY(18px);
      transition: transform .28s ease, opacity .28s ease;
    }

    .modal.show .modal-dialog {
      transform: none;
    }

    .sparkle {
      background: linear-gradient(90deg, rgba(255, 193, 7, .25), rgba(255, 193, 7, .65), rgba(255, 193, 7, .25));
      background-size: 200% 100%;
      animation: shine 2.2s ease-in-out infinite;
      border-radius: .6rem;
      padding: .25rem .5rem;
      display: inline-block;
    }

    @keyframes shine {
      0% {
        background-position: 200% 0
      }

      100% {
        background-position: 0 0
      }
    }

    .price-wrap {
      display: flex;
      align-items: baseline;
      gap: .6rem
    }

    .price-old {
      text-decoration: line-through;
      color: #6c757d
    }

    .price-new {
      font-weight: 800;
      font-size: 1.35rem;
      color: #198754
    }

    .badge-save {
      background: #198754;
      color: #fff;
      border-radius: .5rem;
      padding: .2rem .5rem;
      font-size: .75rem;
      font-weight: 600
    }

    @keyframes shakeX {

      0%,
      100% {
        transform: translateX(0)
      }

      20% {
        transform: translateX(-4px)
      }

      40% {
        transform: translateX(4px)
      }

      60% {
        transform: translateX(-3px)
      }

      80% {
        transform: translateX(3px)
      }
    }

    #upgradeWarn.shake {
      animation: shakeX .35s ease-in-out 1;
    }
  </style>
</head>

<body>
  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg bg-white shadow-sm">
    <div class="container">
      <a class="navbar-brand fw-bold text-warning" href="user_home.php">JobHive</a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"><span class="navbar-toggler-icon"></span></button>

      <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
        <ul class="navbar-nav align-items-lg-center">
          <li class="nav-item"><a class="nav-link active" aria-current="page" href="user_home.php">Home</a></li>
          <li class="nav-item"><a class="nav-link" href="user_dashboard.php">Dashboard</a></li>
          <li class="nav-item"><a class="nav-link" href="all_companies.php">All Companies</a></li>

          <!-- Envelope -->
          <li class="nav-item ms-lg-2">
            <button id="btnInbox" class="btn btn-outline-secondary position-relative <?= $should_shake ? 'btn-bell-shake' : '' ?>" type="button" title="Notifications">
              <i class="bi bi-envelope"></i>
              <?php if ($badge_count > 0): ?>
                <span id="notifBadge" class="badge rounded-pill text-bg-danger badge-dot"><?= $badge_count > 99 ? '99+' : $badge_count ?></span>
              <?php else: ?>
                <span id="notifBadge" class="badge rounded-pill text-bg-danger badge-dot" style="display:none"></span>
              <?php endif; ?>
            </button>
          </li>

          <!-- Premium control -->
          <li class="nav-item ms-lg-2 d-none d-lg-block">
            <?php if (!$is_premium): ?>
              <button id="btnPremium" class="btn btn-warning">
                <i class="bi bi-star-fill me-1"></i> Go Premium
              </button>
            <?php else: ?>
              <button class="btn btn-outline-warning" disabled>
                <i class="bi bi-stars me-1"></i> Premium User
              </button>
            <?php endif; ?>
          </li>

          <!-- User dropdown -->
          <li class="nav-item dropdown ms-lg-2">
            <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              <?php if (!empty($profile_picture)): ?>
                <img src="<?= e($PROFILE_DIR . $profile_picture) ?>" alt="Me" style="width:32px;height:32px;border-radius:50%;border:1px solid rgba(0,0,0,.06);object-fit:cover;">
              <?php else: ?>
                <span class="avatar"><?= e(initials($full_name ?: $email)) ?></span>
              <?php endif; ?>
              <span class="d-none d-lg-inline"><?= e($full_name ?: $email) ?></span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><a class="dropdown-item" href="user_profile.php">My Profile</a></li>
              <li>
                <hr class="dropdown-divider">
              </li>
              <li><a class="dropdown-item text-danger" href="logout.php">Logout</a></li>
            </ul>
          </li>
        </ul>
      </div>
    </div>
  </nav>

  <!-- Hero -->
  <section class="hero-section">
    <div class="container">
      <h1 class="display-6 fw-bold">Welcome back<?= $full_name ? ', ' . e($full_name) : '' ?>!</h1>
      <p class="lead mb-2">Ready to discover your next opportunity?</p>

      <?php if ($is_premium): ?>
        <div class="alert alert-success d-inline-flex align-items-center gap-2 py-2 px-3 mt-2" role="alert">
          <i class="bi bi-patch-check-fill"></i>
          <span>You’re on <strong>Premium</strong> — enjoy auto-fill resumes and pro templates.</span>
        </div>
      <?php endif; ?>

      <!-- Search -->
      <form class="search-bar" autocomplete="off" method="get" action="user_home.php">
        <input class="form-control mb-2" type="text" name="q" placeholder="Job title or company..." value="<?= e($q) ?>">
        <div class="search-row">
          <select class="form-select" name="loc" style="max-width:230px;">
            <?php foreach (['', 'Yangon', 'Mandalay', 'Naypyidaw'] as $L): $sel = ($loc === $L) ? 'selected' : ''; ?>
              <option value="<?= e($L) ?>" <?= $sel ?>><?= e($L === '' ? 'All Locations' : $L) ?></option>
            <?php endforeach; ?>
          </select>
          <?php if ($jt !== ''): ?><input type="hidden" name="jt" value="<?= e($jt) ?>"><?php endif; ?>
          <button class="btn btn-search" type="submit" name="csearch" value="1">Search</button>
        </div>
      </form>

      <!-- Popular by Job Type -->
      <div class="popular-tags">
        <span class="popular-label">Popular:</span>
        <form method="get" action="user_home.php" style="display:inline;">
          <input type="hidden" name="jt" value="Software">
          <button type="submit" class="popular-btn<?= $jt === 'Software' ? ' border-2' : '' ?>">Software</button>
        </form>
        <form method="get" action="user_home.php" style="display:inline;">
          <input type="hidden" name="jt" value="Network">
          <button type="submit" class="popular-btn<?= $jt === 'Network' ? ' border-2' : '' ?>">Network</button>
        </form>
        <form method="get" action="user_home.php" style="display:inline;">
          <input type="hidden" name="jt" value="">
          <button type="submit" class="popular-btn<?= $jt === '' ? ' border-2' : '' ?>">All Jobs</button>
        </form>
      </div>
  </section>

  <!-- Slide-in Notifications -->
  <div id="backdrop" class="backdrop"></div>
  <aside id="inboxPanel" class="inbox-panel" aria-hidden="true">
    <div class="inbox-header">
      <div class="d-flex align-items-center gap-2">
        <i class="bi bi-envelope text-warning fs-5"></i>
        <h5 class="m-0">Notifications</h5>
      </div>
      <div class="d-flex gap-2">
        <form method="post" class="m-0">
          <button name="mark_all" value="1" class="btn btn-light btn-sm js-mark-all">Mark all read</button>
        </form>
        <button id="btnCloseInbox" class="btn btn-light btn-sm"><i class="bi bi-x-lg"></i></button>
      </div>
    </div>
    <div class="inbox-body">
      <?php if (empty($items)): ?>
        <p class="text-muted mb-0">No notifications yet. When you apply or when a company Accepts/Rejects, you'll see updates here.</p>
        <?php else:
        usort($items, fn($a, $b) => strtotime($b['when']) <=> strtotime($a['when']));
        foreach ($items as $n):
          $isUnread = !empty($n['_unread']);
          $cardCls  = $isUnread ? 'unread' : 'read';
        ?>
          <div class="card notif-card <?= $cardCls ?> shadow-sm mb-2"
            data-ntype="<?= e($n['_type']) ?>"
            data-id="<?= e($n['_id']) ?>"
            data-status="<?= e($n['status_now'] ?? '') ?>"
            data-unread="<?= $isUnread ? '1' : '0' ?>">
            <div class="card-body d-flex justify-content-between align-items-start">
              <div class="pe-2">
                <div class="fw-semibold d-flex align-items-center flex-wrap gap-2">
                  <span><?= e($n['title']) ?></span>
                  <span class="badge <?= e($n['pillClass']) ?> notif-chip js-pill"><?= e($n['pill']) ?></span>
                </div>
                <div class="small text-muted mb-1"><?= e($n['when']) ?></div>
                <div class="text-muted"><?= e($n['body']) ?></div>
              </div>
              <div class="d-flex flex-column gap-2">
                <?php if ($isUnread): ?>
                  <form method="post" class="m-0">
                    <input type="hidden" name="type" value="<?= e($n['_type']) ?>">
                    <?php if ($n['_type'] === 'A'): ?>
                      <input type="hidden" name="status_now" value="<?= e($n['status_now']) ?>">
                    <?php endif; ?>
                    <button class="btn btn-sm btn-light js-mark-one" name="mark_one" value="<?= e($n['_id']) ?>">Mark read</button>
                  </form>
                <?php else: ?>
                  <button class="btn btn-sm btn-light" disabled>Marked</button>
                <?php endif; ?>
              </div>
            </div>
          </div>
      <?php endforeach;
      endif; ?>
    </div>
  </aside>

  <?php if (!$is_premium): ?>
    <!-- Premium Promo Modal (only for normal users) -->
    <div class="modal fade" id="premiumModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content premium-highlight shadow">
          <div class="modal-header border-0 pb-0">
            <h5 class="modal-title">
              <span class="sparkle"><i class="bi bi-stars me-1"></i> Premium — 40% OFF</span>
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>

          <div class="modal-body pt-2">
            <p class="mb-3">
              Unlock <strong>Premium Resume</strong>: beautiful templates + <strong>auto-fill</strong> from your profile. Apply faster and look professional.
            </p>

            <div class="d-flex align-items-center justify-content-between p-3 bg-white rounded-3 border">
              <div class="price-wrap">
                <span class="price-old">50,000 MMK</span>
                <span class="price-new">30,000 MMK</span>
              </div>
              <span class="badge-save">You save 20,000 MMK</span>
            </div>

            <ul class="mt-3 mb-0 small text-muted">
              <li>Premium resume templates (ATS-friendly)</li>
              <li>One-click auto-fill from your JobHive profile</li>
              <li>Download as PDF/PNG anytime</li>
            </ul>

            <div id="upgradeWarn" class="alert alert-warning mt-3 mb-0 p-2" style="display: <?= $can_upgrade ? 'none' : 'block' ?>;">
              <small>
                <strong>Fill all data…</strong>
                Missing: <?= e(implode(', ', $missing_fields)) ?>.
                <a href="user_profile.php" class="alert-link">Update profile</a>
              </small>
            </div>
          </div>

          <div class="modal-footer border-0 pt-0">
            <a id="upgradeNow" href="premium.php" class="btn btn-warning" data-can="<?= $can_upgrade ? '1' : '0' ?>">
              <i class="bi bi-lightning-charge-fill me-1"></i> Upgrade Now
            </a>
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Maybe later</button>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <!-- Featured Jobs -->
  <section class="py-5">
    <div class="container">
      <h2 class="text-center fw-bold mb-4">Featured Jobs</h2>
      <?php if (empty($jobs)): ?>
        <?= $isSearch
          ? '<div class="alert alert-danger text-center" role="alert">No jobs to show for your selection.</div>'
          : '<div class="alert alert-light border text-center" role="alert">No jobs to show yet.</div>' ?>
      <?php else: ?>
        <div class="row g-4">
          <?php foreach ($jobs as $job): ?>
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
                    <a class="btn btn-outline-warning" href="job_detail.php?id=<?= (int)$job['job_id'] ?>">Detail</a>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <!-- Footer -->
  <footer class="footer">
    <div class="container">
      <div class="mb-2">
        <a href="#" class="text-white text-decoration-none me-3">About</a>
        <a href="#" class="text-white text-decoration-none me-3">Contact</a>
        <a href="#" class="text-white text-decoration-none">Privacy Policy</a>
      </div>
      <small>&copy; <?= date('Y') ?> JobHive. All rights reserved.</small>
    </div>
  </footer>

  <!-- Panel + Notification Persistence JS -->
  <script>
    // Inbox Panel
    const panel = document.getElementById('inboxPanel');
    const backdrop = document.getElementById('backdrop');
    const btnInbox = document.getElementById('btnInbox');
    const btnClose = document.getElementById('btnCloseInbox');

    function openInbox() {
      panel.classList.add('open');
      backdrop.classList.add('show');
      panel.setAttribute('aria-hidden', 'false');
      document.body.classList.add('inbox-open');
      document.body.style.overflow = 'hidden';
    }

    function closeInbox() {
      panel.classList.remove('open');
      backdrop.classList.remove('show');
      panel.setAttribute('aria-hidden', 'true');
      document.body.classList.remove('inbox-open');
      document.body.style.overflow = '';
    }

    btnInbox.addEventListener('click', openInbox);
    btnClose.addEventListener('click', closeInbox);
    backdrop.addEventListener('click', closeInbox);
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') closeInbox();
    });

    <?php if ($open_inbox): ?>openInbox();
    (function() {
      const url = new URL(window.location.href);
      url.searchParams.delete('inbox');
      const qs = url.searchParams.toString();
      history.replaceState(null, "", url.pathname + (qs ? ('?' + qs) : ''));
    })();
    <?php endif; ?>

    <?php if (!$is_premium): ?>
        // === Premium modal logic (only for normal users) ===
        (function() {
          const promoKey = 'jobhive_premium_shown';
          const modalEl = document.getElementById('premiumModal');
          if (!modalEl) return;
          const premiumModal = new bootstrap.Modal(modalEl, {
            backdrop: 'static',
            keyboard: true
          });

          const btnPremium = document.getElementById('btnPremium');
          btnPremium?.addEventListener('click', () => {
            premiumModal.show();
            sessionStorage.setItem(promoKey, '1');
          });

          // Auto-show after 3s once per session
          window.addEventListener('load', () => {
            const hasShown = sessionStorage.getItem(promoKey) === '1';
            if (!hasShown) {
              setTimeout(() => {
                premiumModal.show();
                sessionStorage.setItem(promoKey, '1');
              }, 3000);
            }
          });

          // Handle Upgrade Now inside modal
          const upg = document.getElementById('upgradeNow');
          const warn = document.getElementById('upgradeWarn');
          upg?.addEventListener('click', (e) => {
            const can = upg.getAttribute('data-can') === '1';
            if (!can) {
              e.preventDefault();
              warn.style.display = 'block';
              warn.classList.remove('shake');
              void warn.offsetWidth;
              warn.classList.add('shake');
            }
          });
        })();
    <?php endif; ?>

      /* === Notification read persistence (localStorage per user) === */
      (function() {
        const USER_ID = <?= (int)$user_id ?>;
        const keyA = `jh_read_A_${USER_ID}`;
        const keyS = `jh_read_S_${USER_ID}`;

        const getA = () => {
          try {
            return JSON.parse(localStorage.getItem(keyA) || '{}');
          } catch {
            return {};
          }
        };
        const setA = obj => localStorage.setItem(keyA, JSON.stringify(obj || {}));
        const getS = () => {
          try {
            return JSON.parse(localStorage.getItem(keyS) || '{}');
          } catch {
            return {};
          }
        };
        const setS = obj => localStorage.setItem(keyS, JSON.stringify(obj || {}));

        const isRead = (ntype, id, status) => {
          if (ntype === 'A') {
            const m = getA();
            return (m[id] && m[id] === (status || ''));
          }
          const s = getS();
          return !!s[id];
        };

        const markOneInStorage = (ntype, id, status) => {
          if (ntype === 'A') {
            const m = getA();
            m[id] = status || '';
            setA(m);
          } else {
            const s = getS();
            s[id] = 1;
            setS(s);
          }
        };

        function updateBellBadge() {
          const cards = document.querySelectorAll('.notif-card');
          let unread = 0;
          cards.forEach(card => {
            const ntype = card.getAttribute('data-ntype');
            const id = card.getAttribute('data-id');
            const status = card.getAttribute('data-status') || '';
            const domUnread = card.getAttribute('data-unread') === '1';
            if (domUnread && !isRead(ntype, id, status)) unread++;
          });
          const badge = document.getElementById('notifBadge');
          if (!badge) return;
          if (unread > 0) {
            badge.textContent = unread > 99 ? '99+' : unread;
            badge.style.display = '';
          } else {
            badge.style.display = 'none';
          }
        }

        function paintCardAsRead(card) {
          const pill = card.querySelector('.js-pill');
          const btn = card.querySelector('.js-mark-one');
          card.classList.remove('unread');
          card.classList.add('read');
          card.setAttribute('data-unread', '0');
          if (pill) {
            pill.classList.remove('text-bg-warning', 'text-bg-primary');
            pill.classList.add('text-bg-secondary');
            pill.textContent = 'Read';
          }
          if (btn) {
            btn.textContent = 'Marked';
            btn.disabled = true;
            btn.classList.add('disabled');
          }
        }

        function applyStorageToUI() {
          document.querySelectorAll('.notif-card').forEach(card => {
            const ntype = card.getAttribute('data-ntype');
            const id = card.getAttribute('data-id');
            const status = card.getAttribute('data-status') || '';
            if (isRead(ntype, id, status)) paintCardAsRead(card);
          });
          updateBellBadge();
        }

        document.addEventListener('click', function(e) {
          const btn = e.target.closest('.js-mark-one');
          if (!btn) return;
          const card = btn.closest('.notif-card');
          if (!card) return;
          const ntype = card.getAttribute('data-ntype');
          const id = card.getAttribute('data-id');
          const status = card.getAttribute('data-status') || '';
          markOneInStorage(ntype, id, status);
          paintCardAsRead(card);
          updateBellBadge();
        }, false);

        document.addEventListener('click', function(e) {
          const btn = e.target.closest('.js-mark-all');
          if (!btn) return;
          document.querySelectorAll('.notif-card').forEach(card => {
            const ntype = card.getAttribute('data-ntype');
            const id = card.getAttribute('data-id');
            const status = card.getAttribute('data-status') || '';
            markOneInStorage(ntype, id, status);
            paintCardAsRead(card);
          });
          updateBellBadge();
        }, false);

        document.addEventListener('DOMContentLoaded', applyStorageToUI);
      })();
  </script>
</body>

</html>