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

/* ===== Fetch fresh user ===== */
$profile_picture = $_SESSION['profile_picture'] ?? null;
try {
  $st = $pdo->prepare("SELECT full_name,email,profile_picture FROM users WHERE user_id=? LIMIT 1");
  $st->execute([$user_id]);
  if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
    $full_name = $row['full_name'] ?: $full_name;
    $email = $row['email'] ?: $email;
    $profile_picture = $row['profile_picture'] ?? null;
    $_SESSION['full_name'] = $full_name;
    $_SESSION['email'] = $email;
    $_SESSION['profile_picture'] = $profile_picture;
  } else {
    header("Location: index.php");
    exit;
  }
} catch (PDOException $e) {
}

/* ===== Handle POST: mark all / mark one (session only) ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $_SESSION['app_seen_status'] = $_SESSION['app_seen_status'] ?? [];

  if (isset($_POST['mark_all'])) {
    $_SESSION['mark_all_pending'] = 1; // apply after loading apps
    header("Location: user_home.php?inbox=1");
    exit;
  }

  if (isset($_POST['mark_one'])) {
    $aid = (int)($_POST['mark_one'] ?? 0);
    $status = trim($_POST['status_now'] ?? '');
    if ($aid > 0 && ($status === 'Accepted' || $status === 'Rejected')) {
      $_SESSION['app_seen_status'][$aid] = $status;
    }
    header("Location: user_home.php?inbox=1");
    exit;
  }
}

/* ===== Optional: auto-set past-deadline jobs to Inactive ===== */
try {
  $today = date('Y-m-d');
  $pdo->prepare("UPDATE jobs SET status='Inactive' WHERE status='Active' AND deadline<?")->execute([$today]);
} catch (PDOException $e) {
}

/* ===== Search filters (jobs list) ===== */
$q = '';
$loc = '';
$isSearch = false;
if (isset($_GET['csearch'])) {
  $q = trim($_GET['q'] ?? '');
  $loc = trim($_GET['loc'] ?? '');
  $isSearch = true;
} elseif (isset($_GET['q'])) {
  $q = trim($_GET['q'] ?? '');
  $isSearch = true;
}
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
$whereSql = $conds ? ("WHERE " . implode(" AND ", $conds)) : "";

/* ===== Jobs ===== */
try {
  $sql = "SELECT j.job_id,j.job_title,j.job_description,j.location,j.status,j.posted_at,c.company_name,c.logo
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

/* ===== Read/Unread state in SESSION (keep history) ===== */
$_SESSION['app_seen_status'] = $_SESSION['app_seen_status'] ?? [];
$seen  = &$_SESSION['app_seen_status']; // application_id => last seen status
$items = [];                              // all Accepted/Rejected items with unread flag

foreach ($apps as $a) {
  $aid = (int)$a['application_id'];
  $st  = (string)$a['status'];
  if ($st === 'Accepted' || $st === 'Rejected') {
    $a['_unread'] = !isset($seen[$aid]) || $seen[$aid] !== $st;
    $items[] = $a;
  }
}

/* Apply "Mark all read" (keeps history; just flips flags) */
if (!empty($_SESSION['mark_all_pending'])) {
  foreach ($items as &$it) {
    $seen[(int)$it['application_id']] = (string)$it['status'];
    $it['_unread'] = false;
  }
  unset($_SESSION['mark_all_pending']);
}

/* Badge + shake */
$badge_count  = 0;
foreach ($items as $it) {
  if (!empty($it['_unread'])) $badge_count++;
}
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

    .hero-section {
      background: #f8fafc;
      padding: 64px 0 44px;
      text-align: center;
    }

    .hero-section h1 {
      font-weight: 700;
    }

    .hero-section .lead {
      color: #556;
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
      gap: .9rem;
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
      padding: .65rem .9rem;
    }

    .search-row .form-select {
      min-width: 220px;
      max-width: 260px;
      min-height: 48px;
      border-radius: .8rem;
      font-size: .98rem;
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
      padding: 0 .9rem;
    }

    .btn-search:hover {
      background: #ff9800;
      color: #fff;
    }

    .popular-label {
      font-size: 1.05rem;
      color: #22223b;
      font-weight: 600;
      margin-right: 36px;
    }

    .popular-tags {
      margin-top: 1rem;
      display: flex;
      gap: .6rem;
      justify-content: center;
      flex-wrap: wrap;
    }

    .popular-btn {
      border: 1.6px solid #ffc107;
      color: #ffc107;
      background: #fff;
      font-size: .98rem;
      border-radius: .55rem;
      padding: .3rem 1.15rem;
      font-weight: 500;
    }

    .popular-btn:hover {
      background: #fff8ec;
      color: #ff8800;
      border-color: #ff8800;
    }

    .job-card {
      border: 0;
      border-radius: 1.25rem;
    }

    .job-card .logo {
      width: 56px;
      height: 56px;
      object-fit: cover;
      border-radius: .75rem;
      background: #fff;
      border: 1px solid rgba(0, 0, 0, .05);
    }

    .job-badge {
      font-size: .82rem;
    }

    .footer {
      background: #1a202c;
      color: #fff;
      padding: 30px 0 10px;
      text-align: center;
    }

    /* Envelope + slide-in panel */
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
      transition: transform .28s ease;
      display: flex;
      flex-direction: column;
      z-index: 1080;
    }

    .inbox-panel.open {
      transform: translateX(0);
    }

    .inbox-header {
      padding: 14px 18px;
      border-bottom: 1px solid #eef0f2;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .inbox-body {
      padding: 14px 16px;
      overflow-y: auto;
      height: 100%;
    }

    .backdrop {
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, .25);
      opacity: 0;
      pointer-events: none;
      transition: opacity .28s ease;
      z-index: 1079;
    }

    .backdrop.show {
      opacity: 1;
      pointer-events: auto;
    }

    .notif-card {
      border: 1px solid #e9ecef;
      border-radius: .75rem;
    }

    .notif-card .card-body {
      padding: .85rem .9rem;
    }

    @media (max-width:992px) {
      .inbox-panel {
        width: 100vw;
      }
    }

    /* Unread vs Read history styling */
    .notif-card.unread {
      background: #fffdf5;
      border-left: 4px solid #ffc107;
    }

    .notif-card.read {
      background: #f8f9fa;
      border-left: 4px solid #e9ecef;
    }

    .notif-chip {
      font-size: .72rem;
    }

    /* Shake when count increases (on page load only) */
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
      transform-origin: 50% 0%;
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
          <li class="nav-item"><a class="nav-link" href="recommended.php">Recommended Jobs</a></li>
          <li class="nav-item"><a class="nav-link" href="companies.php">All Companies</a></li>

          <!-- Envelope -->
          <li class="nav-item ms-lg-2">
            <button id="btnInbox" class="btn btn-outline-secondary position-relative <?= $should_shake ? 'btn-bell-shake' : '' ?>" type="button" title="Notifications">
              <i class="bi bi-envelope"></i>
              <?php if ($badge_count > 0): ?>
                <span id="notifBadge" class="badge rounded-pill text-bg-danger badge-dot"><?= $badge_count > 99 ? '99+' : $badge_count ?></span>
              <?php endif; ?>
            </button>
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
              <li><a class="dropdown-item text-danger" href="index.php">Logout</a></li>
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
      <p class="lead mb-4">Ready to discover your next opportunity?</p>

      <!-- Search -->
      <form class="search-bar" autocomplete="off" method="get" action="user_home.php">
        <input class="form-control mb-2" type="text" name="q" placeholder="Job title or company..." value="<?= e($q) ?>">
        <div class="search-row">
          <select class="form-select" name="loc" style="max-width:230px;">
            <?php foreach (['', 'Yangon', 'Mandalay', 'Naypyidaw'] as $L): $sel = ($loc === $L) ? 'selected' : ''; ?>
              <option value="<?= e($L) ?>" <?= $sel ?>><?= e($L === '' ? 'All Locations' : $L) ?></option>
            <?php endforeach; ?>
          </select>
          <button class="btn btn-search" type="submit" name="csearch" value="1">Search</button>
        </div>
      </form>

      <div class="popular-tags">
        <span class="popular-label">Popular:</span>
        <form method="get" action="user_home.php" style="display:inline;"><input type="hidden" name="q" value="Software"><button type="submit" class="popular-btn">Software</button></form>
        <form method="get" action="user_home.php" style="display:inline;"><input type="hidden" name="q" value="Network"><button type="submit" class="popular-btn">Network</button></form>
      </div>
    </div>
  </section>

  <!-- Slide-in Notifications (history kept, color changes on read) -->
  <div id="backdrop" class="backdrop"></div>
  <aside id="inboxPanel" class="inbox-panel" aria-hidden="true">
    <div class="inbox-header">
      <div class="d-flex align-items-center gap-2">
        <i class="bi bi-envelope text-warning fs-5"></i>
        <h5 class="m-0">Notifications</h5>
      </div>
      <div class="d-flex gap-2">
        <form method="post" class="m-0">
          <button name="mark_all" value="1" class="btn btn-light btn-sm">Mark all read</button>
        </form>
        <button id="btnCloseInbox" class="btn btn-light btn-sm"><i class="bi bi-x-lg"></i></button>
      </div>
    </div>
    <div class="inbox-body">
      <?php if (empty($items)): ?>
        <p class="text-muted mb-0">No application updates yet. When a company Accepts or Rejects, it will appear here.</p>
        <?php else: foreach ($items as $n):
          $isUnread = !empty($n['_unread']);
          $cardCls  = $isUnread ? 'unread' : 'read';
        ?>
          <div class="card notif-card <?= $cardCls ?> shadow-sm mb-2">
            <div class="card-body d-flex justify-content-between align-items-start">
              <div>
                <div class="fw-semibold">
                  <?= e($n['status']) ?> — <?= e($n['job_title']) ?> (<?= e($n['company_name']) ?>)
                  <?php if ($isUnread): ?>
                    <span class="badge text-bg-warning notif-chip ms-2">New</span>
                  <?php else: ?>
                    <span class="badge text-bg-secondary notif-chip ms-2">Read</span>
                  <?php endif; ?>
                </div>
                <div class="small text-muted">Applied: <?= e(date('M d, Y H:i', strtotime($n['applied_at']))) ?></div>
              </div>

              <div class="d-flex gap-2">
                <a class="btn btn-sm btn-outline-warning" href="job_detail.php?id=<?= (int)$n['job_id'] ?>">Open</a>

                <?php if ($isUnread): ?>
                  <form method="post" class="m-0">
                    <input type="hidden" name="status_now" value="<?= e($n['status']) ?>">
                    <button class="btn btn-sm btn-light" name="mark_one" value="<?= (int)$n['application_id'] ?>">Mark read</button>
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

  <!-- Featured Jobs -->
  <section class="py-5">
    <div class="container">
      <h2 class="text-center fw-bold mb-4">Featured Jobs</h2>
      <?php if (empty($jobs)): ?>
        <?= $isSearch
          ? '<div class="alert alert-danger text-center" role="alert">No jobs to show for your search.</div>'
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

  <!-- Panel toggling -->
  <script>
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

    // Auto-open once if ?inbox=1, then strip the param so future reloads won't re-open it
    <?php if ($open_inbox): ?>
      openInbox();
      (function removeInboxParam() {
        const url = new URL(window.location.href);
        url.searchParams.delete('inbox');
        const qs = url.searchParams.toString();
        history.replaceState(null, "", url.pathname + (qs ? '?' + qs : ''));
      })();
    <?php endif; ?>
  </script>
</body>

</html>