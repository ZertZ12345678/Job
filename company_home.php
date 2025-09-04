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

/* ===== Load company profile (name + logo + member) ===== */
$company_name = $company_logo = $company_member = '';
try {
  $st = $pdo->prepare("SELECT company_name, logo, member FROM companies WHERE company_id=? LIMIT 1");
  $st->execute([$company_id]);
  if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
    $company_name   = (string)($row['company_name'] ?? '');
    $company_logo   = (string)($row['logo'] ?? '');
    $company_member = (string)($row['member'] ?? 'normal');
  }
} catch (PDOException $e) { /* ignore */
}

$logo_src = '';
if ($company_logo !== '') {
  $logo_src = preg_match('~^https?://~i', $company_logo) ? $company_logo : $LOGO_DIR . ltrim($company_logo, '/');
}

/* ===== Pricing/Tier preview for NEXT post (same info as banner, but for modal) ===== */
require_once "pricing.php"; // defines JH_BASE_FEE + helpers

// Count **all** posts for this company
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

// badge class
$badgeClass = match (strtolower($company_member)) {
  'gold'     => 'bg-warning text-dark',
  'platinum' => 'bg-secondary',
  'diamond'  => 'bg-info text-dark',
  default    => 'bg-light text-dark'
};

/* =========================================================
   INBOX: Applications (ALL statuses) for this company's jobs
   ========================================================= */
$apps = [];
try {
  $q = "
    SELECT a.application_id, a.status, a.applied_at, a.resume,
           u.user_id, u.full_name, u.email, u.phone, u.address AS location, u.profile_picture,
           j.job_id, j.job_title
    FROM application a
    JOIN jobs j  ON a.job_id=j.job_id
    JOIN users u ON a.user_id=u.user_id
    WHERE j.company_id=?
    ORDER BY a.applied_at DESC
  ";
  $st = $pdo->prepare($q);
  $st->execute([$company_id]);
  $apps = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  $apps = [];
}

/* Session-based read state for company inbox (server-side) */
$_SESSION['company_app_read'] = $_SESSION['company_app_read'] ?? []; // [application_id] => 1
$readMap = &$_SESSION['company_app_read'];

/* POST handlers (mark read / mark all) */
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

/* apply mark-all now and expose a one-shot flag for JS to mirror to localStorage */
if (!empty($_SESSION['c_mark_all_pending'])) {
  foreach ($apps as $a) $readMap[(int)$a['application_id']] = 1;
  $_SESSION['c_just_marked_all'] = 1;  // one-shot for next render
  unset($_SESSION['c_mark_all_pending']);
}
$did_mark_all = !empty($_SESSION['c_just_marked_all']);
unset($_SESSION['c_just_marked_all']);

/* unread count for badge (server-side initial) */
$badge_count = 0;
foreach ($apps as $a) if (empty($readMap[(int)$a['application_id']])) $badge_count++;

/* auto-open inbox param */
$open_inbox = (isset($_GET['inbox']) && $_GET['inbox'] == '1');

/* =========================================================
   JOB SEARCH (scoped to this company)
   ========================================================= */
$qtxt = trim($_GET['q']  ?? '');
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

/* Fetch this company’s jobs for the grid */
$jobs = [];
try {
  $sql = "SELECT j.job_id,j.job_title,j.job_description,j.location,j.status,j.posted_at,
                 c.company_name,c.logo
          FROM jobs j
          JOIN companies c ON c.company_id=j.company_id
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

/* ===== Optional one-time flag if you want "only after successful login" =====
   In login.php before redirect: $_SESSION['show_member_after_login']=1;
*/
$show_member_after_login = (int)($_SESSION['show_member_after_login'] ?? 0);
unset($_SESSION['show_member_after_login']); // one-time
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
      --jh-gold: #ffaa2b;
      --jh-gold-2: #ffc107;
      --jh-dark: #1a202c;
    }

    body {
      background: #f8fafc;
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
      border: 1px solid rgba(0, 0, 0, .06);
    }

    .navbar .avatar-img {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      border: 1px solid rgba(0, 0, 0, .06);
      object-fit: cover;
      background: #fff;
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
      padding: 56px 0 14px;
      text-align: center;
    }

    .search-bar {
      max-width: 920px;
      margin: 14px auto 0;
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
      margin-top: .1rem;
    }

    .search-bar .form-control {
      min-height: 52px;
      border-radius: .8rem;
      font-size: 1rem;
      padding: .65rem .9rem
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

    .popular-btn {
      border: 1.6px solid #ffc107;
      color: #ffc107;
      background: #fff;
      font-size: .98rem;
      border-radius: .55rem;
      padding: .3rem 1.15rem;
      font-weight: 500;
      text-decoration: none;
    }

    .popular-btn:hover {
      background: #fff8ec;
      color: #ff8800;
      border-color: #ff8800;
      text-decoration: none;
    }


    .badge-dot {
      position: absolute;
      top: -6px;
      right: -6px;
      font-size: .70rem;
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
      z-index: 1080;
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
      border: 1px solid #e9ecef;
      border-radius: .75rem
    }

    .app-card .card-body {
      padding: .85rem .9rem
    }

    .app-card.unread {
      background: #fffdf5;
      border-left: 4px solid #ffc107
    }

    .app-card.read {
      background: #f8f9fa;
      border-left: 4px solid #e9ecef
    }

    .status-badge {
      font-size: .82rem
    }

    .app-photo {
      width: 50px;
      height: 50px;
      border-radius: 50%;
      object-fit: cover;
      background: #eee
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

    /* Modal polish like user home */
    .member-highlight {
      background: linear-gradient(135deg, #e8f7ff, #fff);
      border: 1px solid #bfe7ff;
      border-radius: 1rem;
    }

    .modal.fade .modal-dialog {
      transform: translateY(18px);
      transition: transform .28s ease, opacity .28s ease;
    }

    .modal.show .modal-dialog {
      transform: none;
    }

    /* Footer */
    .footer {
      background: var(--jh-dark);
      color: #e9ecef;
      padding: 40px 0 16px;
      flex-shrink: 0;
      margin-top: 24px;
    }

    .footer a {
      color: #f8f9fa;
      text-decoration: none;
    }

    .footer a:hover {
      color: var(--jh-gold);
    }

    .footer .brand {
      font-weight: 800;
      color: var(--jh-gold);
    }

    .footer .social a {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 38px;
      height: 38px;
      border-radius: 50%;
      background: rgba(255, 255, 255, .08);
      margin-right: 8px;
    }

    .footer .social a:hover {
      background: rgba(255, 193, 7, .2);
    }

    .footer hr {
      border-top: 1px solid rgba(255, 255, 255, .12);
      margin: 24px 0 12px;
    }

    .footer small {
      color: #cbd5e1;
    }

    @media (max-width:992px) {
      .inbox-panel {
        width: 100vw
      }
    }
  </style>
</head>

<body>
  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
    <div class="container">
      <a class="navbar-brand fw-bold text-warning" href="company_home.php">JobHive</a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
        <ul class="navbar-nav align-items-lg-center">
          <li class="nav-item"><a class="nav-link" href="company_home.php">Home</a></li>
          <li class="nav-item"><a class="nav-link" href="c_dashboard.php">Dashboard</a></li>

          <!-- New: open membership modal -->
          <li class="nav-item">
            <button id="btnMember" class="btn btn-outline-warning ms-2">
              <i class="bi bi-gem me-1"></i> Membership
            </button>
          </li>

          <li class="nav-item">
            <a class="btn btn-warning ms-2 text-white fw-bold" href="post_job.php" style="border-radius:0.6rem;">Post Job</a>
          </li>

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

  <!-- Hero -->
  <section class="hero-section">
    <div class="container">
      <h1 class="display-6 fw-bold">Welcome back<?= $company_name ? ', ' . e($company_name) : '' ?>!</h1>
      <p class="lead mb-2">Search your postings quickly.</p>

      <!-- NOTE: Promo banner removed per your request -->

      <!-- Search (scoped to this company) -->
      <form class="search-bar" autocomplete="off" method="get" action="company_home.php">
        <input class="form-control mb-2" type="text" name="q" placeholder="Job title..." value="<?= e($qtxt) ?>">
        <div class="search-row">
          <?php if ($jt !== ''): ?><input type="hidden" name="jt" value="<?= e($jt) ?>"><?php endif; ?>
          <button class="btn btn-search" type="submit" name="csearch" value="1">Search</button>
          <div class="d-flex flex-wrap align-items-center ms-3 gap-2">
            <a href="company_home.php?csearch=1&jt=Software" class="popular-btn<?= $jt === 'Software' ? ' border-2' : '' ?>">Software</a>
            <a href="company_home.php?csearch=1&jt=Network" class="popular-btn<?= $jt === 'Network'  ? ' border-2' : '' ?>">Network</a>
            <a href="company_home.php?csearch=1" class="popular-btn<?= $jt === ''         ? ' border-2' : '' ?>">All Jobs</a>
          </div>
        </div>
      </form>
    </div>
  </section>

  <!-- Slide-in inbox -->
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
          $cardCls  = $isUnread ? 'unread' : 'read';
          $pp = $a['profile_picture'] ?? '';
          if ($pp && !preg_match('~^https?://~', $pp) && !preg_match('~^profile_pics/~', $pp)) $pp = 'profile_pics/' . ltrim($pp, '/');
          $initials = name_initials($a['full_name']);
          $st = (string)$a['status'];
          $stCls = ($st === 'Pending') ? 'bg-warning' : (($st === 'Accepted') ? 'bg-success' : 'bg-secondary');
        ?>
          <div class="card app-card <?= $cardCls ?> shadow-sm mb-3"
            data-id="<?= (int)$a['application_id'] ?>"
            data-unread="<?= $isUnread ? '1' : '0' ?>">
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
                  <div class="text-muted mb-1">
                    <?= e($a['full_name']) ?> applied<?= $a['location'] ? ' from ' . e($a['location']) : '' ?>.
                  </div>
                  <?php if (!empty($a['email'])): ?>
                    <div class="text-muted small">
                      <i class="bi bi-envelope me-1"></i>
                      <a href="mailto:<?= e($a['email']) ?>"><?= e($a['email']) ?></a>
                    </div>
                  <?php endif; ?>
                  <?php if (!empty($a['phone'])): ?>
                    <div class="text-muted small">
                      <i class="bi bi-telephone me-1"></i>
                      <a href="tel:<?= e($a['phone']) ?>"><?= e($a['phone']) ?></a>
                    </div>
                  <?php endif; ?>
                </div>
              </div>

              <div class="ms-3 app-actions">
                <?php if ($isUnread): ?>
                  <form method="post" class="m-0">
                    <button class="btn btn-light btn-icon-sm js-mark-one" name="mark_one_company" value="<?= (int)$a['application_id'] ?>">Mark read</button>
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

  <!-- Featured Jobs -->
  <section class="py-5">
    <div class="container">
      <h2 class="text-center fw-bold mb-4">Featured Jobs</h2>
      <?php if (empty($jobs)): ?>
        <?= $isSearch
          ? '<div class="alert alert-danger text-center" role="alert">No jobs match your filters.</div>'
          : '<div class="alert alert-light border text-center" role="alert">No jobs posted yet.</div>' ?>
      <?php else: ?>
        <div class="row g-4">
          <?php foreach ($jobs as $job): ?>
            <div class="col-12 col-md-6 col-lg-4">
              <div class="card job-card h-100 shadow-sm">
                <div class="card-body">
                  <div class="d-flex align-items-center mb-3">
                    <?php
                    $logoFile = trim((string)$job['logo']);
                    $logoPath = $logoFile !== '' ? ($LOGO_DIR . $logoFile) : '';
                    ?>
                    <img class="logo" src="<?= e($logoPath !== '' ? $logoPath : 'https://via.placeholder.com/56') ?>" alt="Company logo" onerror="this.src='https://via.placeholder.com/56'">
                    <div class="ms-3">
                      <h5 class="mb-0"><?= e($job['job_title']) ?></h5>
                      <small class="text-muted"><?= e($job['company_name']) ?></small>
                    </div>
                  </div>
                  <span class="badge bg-light text-dark border job-badge mb-2"><?= e($job['location']) ?></span>
                  <p class="text-muted small mb-3"><?= e(safe_truncate($job['job_description'], 160, '…')) ?></p>
                  <div class="d-flex justify-content-between align-items-center">
                    <?php
                    $status = (string)$job['status'];
                    $cls = ($status === 'Active') ? 'bg-success' : (($status === 'Inactive') ? 'bg-secondary' : 'bg-danger');
                    ?>
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

  <!-- ===== Footer ===== -->
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
          <h6 class="text-uppercase text-white-50 mb-3">Company</h6>
          <ul class="list-unstyled">
            <li class="mb-2"><a href="faq.php">FAQ</a></li>
            <li class="mb-2"><a href="about.php">About Us</a></li>
            <li class="mb-2"><a href="privacy.php">Privacy Policy</a></li>
            <li class="mb-2"><a href="terms.php">Terms &amp; Conditions</a></li>
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

  <!-- Membership / Pricing Modal -->
  <div class="modal fade" id="memberModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content member-highlight shadow">
        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title">
            <i class="bi bi-gem me-1"></i> Grow Faster with Your Membership
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body pt-2">
          <div class="text-center">
            <!-- Current tier -->
            <div class="mb-2">
              <div class="fw-semibold">You’re currently on</div>
              <span class="badge rounded-pill <?= $badgeClass ?>"><?= e(ucfirst($company_member)) ?></span>
              <span class="ms-2 text-muted">(<?= $totalPosts ?> posts)</span>
            </div>

            <!-- Next tier after the next post -->
            <div class="mb-2">
              <div class="fw-semibold">After your next post</div>
              <div class="fs-6"><?= e(strtoupper($tierNext)) ?> tier</div>
            </div>

            <!-- Price for the next post -->
            <div class="mb-3">
              <div class="fw-semibold">Next post price</div>
              <div class="fs-5 fw-bold">
                <?= number_format($feeNext) ?> MMK
                <small class="text-muted d-block">
                  base <?= number_format(JH_BASE_FEE) ?> · save <?= number_format(max(0, JH_BASE_FEE - $feeNext)) ?> MMK (<?= (int)round($rateNext * 100) ?>%)
                </small>
              </div>
            </div>

            <!-- New attractive sentence -->
            <p class="small text-muted mb-0">
              The more you post, the more you save — enjoy <strong>10% off after 5 posts</strong>,
              <strong>15% off after 15 posts</strong>, and <strong>20% off once you reach 25 posts</strong>.
            </p>
          </div>

          <hr class="my-3">

          <ul class="list-unstyled small m-0 text-muted">
            <li class="mb-1"><i class="bi bi-check2-circle me-1"></i> Transparent pricing before you publish</li>
            <li class="mb-1"><i class="bi bi-check2-circle me-1"></i> Volume-based discounts for ongoing hiring</li>
            <li class="mb-1"><i class="bi bi-check2-circle me-1"></i> Optimized for active recruitment campaigns</li>
          </ul>
        </div>

        <div class="modal-footer border-0 pt-0">
          <a href="post_job.php" class="btn btn-warning">
            <i class="bi bi-plus-square me-1"></i> Post a Job Now
          </a>
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>



  <!-- Inbox toggle -->
  <script>
    const panel = document.getElementById('inboxPanel');
    const backdrop = document.getElementById('backdrop');
    const btnInbox = document.getElementById('btnInbox');
    const btnClose = document.getElementById('btnCloseInbox');

    function openInbox() {
      panel.classList.add('open');
      backdrop.classList.add('show');
      panel.setAttribute('aria-hidden', 'false');
      document.body.style.overflow = 'hidden';
    }

    function closeInbox() {
      panel.classList.remove('open');
      backdrop.classList.remove('show');
      panel.setAttribute('aria-hidden', 'true');
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
      history.replaceState(null, "", url.pathname + (url.searchParams.toString() ? ('?' + url.searchParams.toString()) : ''));
    })();
    <?php endif; ?>
  </script>

  <!-- One-time notification persistence (localStorage per company) -->
  <script>
    (function() {
      const COMPANY_ID = <?= (int)$company_id ?>;
      const STORAGE_KEY = `jh_c_read_${COMPANY_ID}`; // <-- fixed quotes
      const DID_MARK_ALL = <?= $did_mark_all ? 'true' : 'false' ?>;

      const getMap = () => {
        try {
          return JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');
        } catch {
          return {};
        }
      };
      const setMap = (m) => localStorage.setItem(STORAGE_KEY, JSON.stringify(m || {}));
      const isRead = (id) => !!getMap()[id];
      const markOne = (id) => {
        const m = getMap();
        m[id] = 1;
        setMap(m);
      };

      function paintCardAsRead(card) {
        card.classList.remove('unread');
        card.classList.add('read');
        card.setAttribute('data-unread', '0');
        const btn = card.querySelector('.js-mark-one');
        if (btn) {
          btn.textContent = 'Marked';
          btn.disabled = true;
          btn.classList.add('disabled');
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
          badge.style.display = '';
        } else {
          badge.style.display = 'none';
        }
      }

      function applyStorageToUI() {
        document.querySelectorAll('.app-card').forEach(card => {
          const id = card.getAttribute('data-id');
          if (isRead(id)) paintCardAsRead(card);
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
          paintCardAsRead(card);
        });
        updateBellBadge();
      }, false);

      if (DID_MARK_ALL) {
        document.querySelectorAll('.app-card').forEach(card => {
          const id = card.getAttribute('data-id');
          markOne(id);
          paintCardAsRead(card);
        });
        updateBellBadge();
      }

      document.addEventListener('DOMContentLoaded', applyStorageToUI);
    })();
  </script>

  <!-- Membership modal behavior (auto-appear ~3s after login, or on button click) -->
  <script>
    (function() {
      const modalEl = document.getElementById('memberModal');
      if (!modalEl) return;
      const memberModal = new bootstrap.Modal(modalEl, {
        backdrop: 'static',
        keyboard: true
      });
      const btnMember = document.getElementById('btnMember');

      // open on button
      btnMember?.addEventListener('click', () => {
        memberModal.show();
        sessionStorage.setItem('jh_member_shown', '1');
      });

      // one-time per session; prefer post-login signal, else first time this session
      const JUST_LOGGED_IN = <?= $show_member_after_login ? 'true' : 'false' ?>;
      window.addEventListener('load', () => {
        const hasShown = sessionStorage.getItem('jh_member_shown') === '1';
        if ((JUST_LOGGED_IN || !hasShown)) {
          setTimeout(() => {
            memberModal.show();
            sessionStorage.setItem('jh_member_shown', '1');
          }, 3000);
        }
      });
    })();
  </script>
</body>

</html>