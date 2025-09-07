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
  'full_name' => 'Full name',
  'email' => 'Email',
  'password' => 'Password',
  'gender' => 'Gender',
  'education' => 'Education',
  'phone' => 'Phone',
  'address' => 'Address',
  'b_date' => 'Birth date',
  'job_category' => 'Job category',
  'current_position' => 'Current position',
  'profile_picture' => 'Profile picture'
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
      if ($aid > 0 && ($status === 'Accepted' || $status === 'Rejected')) $seenApp[$aid] = $status;
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
  $q = trim($_GET['q'] ?? '');
  $loc = trim($_GET['loc'] ?? '');
  $jt = trim($_GET['jt'] ?? '');
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
if ($q !== '') {
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
        ? "Great news! Your application to {$a['company_name']} for \"{$a['job_title']}\" was accepted."
        : "Update: Your application to {$a['company_name']} for \"{$a['job_title']}\" was rejected."),
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


  <link rel="stylesheet" href="css/user_home.css">
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
          <span>You're on <strong>Premium</strong> — enjoy auto-fill resumes and pro templates.</span>
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
  <!-- ===== Footer (identical to index) ===== -->
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
            <li class="mb-2"><a href="user_home.php">Home</a></li>
            <li class="mb-2"><a href="sign_up.php">Register</a></li>
            <li class="mb-2"><a href="c_sign_up.php">Company Register</a></li>
            <li class="mb-2"><a href="all_companies.php">All Companies</a></li>
            <li class="mb-2"><a href="logout.php">Logout</a></li>
          </ul>
        </div>
        <div class="col-md-3">
          <h6 class="text-uppercase text-white-50 mb-3">Company</h6>
          <ul class="list-unstyled">
            <li class="mb-2"><a href="faq.php?return=user_home">FAQ</a></li>
            <li class="mb-2"><a href="about.php?return=user_home">About</a></li>
            <li class="mb-2"><a href="privacy.php?return=user_home">Privacy Policy</a></li>
            <li class="mb-2"><a href="terms.php?return=user_home">Terms &amp; Conditions</a></li>
          </ul>
        </div>
        <div class="col-md-3">
          <h6 class="text-uppercase text-white-50 mb-3">Contact</h6>
          <ul class="list-unstyled">
            <li class="mb-2"><i class="bi bi-geo-alt me-2"></i>Yangon, Myanmar</li>
            <li class="mb-2"><i class="bi bi-envelope me-2"></i><a href="mailto:support@jobhive.mm">support@jobhive.mm</a></li>
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

  // Panel + Notification Persistence JS
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
        // Premium modal logic
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
          window.addEventListener('load', () => {
            const hasShown = sessionStorage.getItem(promoKey) === '1';
            if (!hasShown) {
              setTimeout(() => {
                premiumModal.show();
                sessionStorage.setItem(promoKey, '1');
              }, 3000);
            }
          });
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

      // Notification read persistence (localStorage)
      (function() {
        const USER_ID = <?= (int)$user_id ?>;
        const keyA = `jh_read_A_${USER_ID}`,
          keyS = `jh_read_S_${USER_ID}`;
        const getA = () => {
          try {
            return JSON.parse(localStorage.getItem(keyA) || '{}');
          } catch {
            return {};
          }
        };
        const setA = o => localStorage.setItem(keyA, JSON.stringify(o || {}));
        const getS = () => {
          try {
            return JSON.parse(localStorage.getItem(keyS) || '{}');
          } catch {
            return {};
          }
        };
        const setS = o => localStorage.setItem(keyS, JSON.stringify(o || {}));
        const isRead = (t, id, st) => t === 'A' ? !!(getA()[id] && getA()[id] === (st || '')) : !!getS()[id];
        const mark = (t, id, st) => {
          if (t === 'A') {
            const m = getA();
            m[id] = st || '';
            setA(m);
          } else {
            const s = getS();
            s[id] = 1;
            setS(s);
          }
        };

        function badge() {
          const cards = document.querySelectorAll('.notif-card');
          let unread = 0;
          cards.forEach(c => {
            const t = c.getAttribute('data-ntype'),
              id = c.getAttribute('data-id'),
              st = c.getAttribute('data-status') || '',
              dom = c.getAttribute('data-unread') === '1';
            if (dom && !isRead(t, id, st)) unread++;
          });
          const b = document.getElementById('notifBadge');
          if (!b) return;
          if (unread > 0) {
            b.textContent = unread > 99 ? '99+' : unread;
            b.style.display = '';
          } else {
            b.style.display = 'none';
          }
        }

        function paint(card) {
          const pill = card.querySelector('.js-pill'),
            btn = card.querySelector('.js-mark-one');
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

        function apply() {
          document.querySelectorAll('.notif-card').forEach(c => {
            const t = c.getAttribute('data-ntype'),
              id = c.getAttribute('data-id'),
              st = c.getAttribute('data-status') || '';
            if (isRead(t, id, st)) paint(c);
          });
          badge();
        }
        document.addEventListener('click', e => {
          const btn = e.target.closest('.js-mark-one');
          if (!btn) return;
          const card = btn.closest('.notif-card');
          if (!card) return;
          const t = card.getAttribute('data-ntype'),
            id = card.getAttribute('data-id'),
            st = card.getAttribute('data-status') || '';
          mark(t, id, st);
          paint(card);
          badge();
        }, false);
        document.addEventListener('click', e => {
          const btn = e.target.closest('.js-mark-all');
          if (!btn) return;
          document.querySelectorAll('.notif-card').forEach(card => {
            const t = card.getAttribute('data-ntype'),
              id = card.getAttribute('data-id'),
              st = card.getAttribute('data-status') || '';
            mark(t, id, st);
            paint(card);
          });
          badge();
        }, false);
        document.addEventListener('DOMContentLoaded', apply);
      })();

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
        fetch('user_chatbot.php', {
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
  </script>
</body>

</html>