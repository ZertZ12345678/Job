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
/** Build initials from name (first + last token) */
function name_initials($name)
{
  $name = trim(preg_replace('/\s+/', ' ', (string)$name));
  if ($name === '') return 'U';
  $parts = explode(' ', $name);
  $first = mb_substr($parts[0], 0, 1, 'UTF-8');
  $last  = count($parts) > 1 ? mb_substr(end($parts), 0, 1, 'UTF-8') : '';
  return mb_strtoupper($first . $last, 'UTF-8');
}

/* ===== Load company profile (name + logo) ===== */
$company_name = '';
$company_logo = '';
try {
  $st = $pdo->prepare("SELECT company_name, logo FROM companies WHERE company_id=? LIMIT 1");
  $st->execute([$company_id]);
  if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
    $company_name = (string)($row['company_name'] ?? '');
    $company_logo = (string)($row['logo'] ?? '');
  }
} catch (PDOException $e) {
}

$logo_src = '';
if ($company_logo !== '') {
  if (preg_match('~^https?://~i', $company_logo)) $logo_src = $company_logo;
  else $logo_src = $LOGO_DIR . ltrim($company_logo, '/');
}

/* ===== Applications (ALL statuses) for this company's jobs ===== */
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

/* ===== Session-based read state for company inbox ===== */
$_SESSION['company_app_read'] = $_SESSION['company_app_read'] ?? []; // [application_id] => 1
$readMap = &$_SESSION['company_app_read'];

/* ===== POST handlers (mark read / mark all) ===== */
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

/* If user clicked Mark all, apply now that we have $apps */
if (!empty($_SESSION['c_mark_all_pending'])) {
  foreach ($apps as $a) {
    $readMap[(int)$a['application_id']] = 1;
  }
  unset($_SESSION['c_mark_all_pending']);
}

/* ===== Badge count (# unread) ===== */
$badge_count = 0;
foreach ($apps as $a) {
  $aid = (int)$a['application_id'];
  if (empty($readMap[$aid])) $badge_count++;
}

/* Auto-open inbox param */
$open_inbox = (isset($_GET['inbox']) && $_GET['inbox'] == '1');
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
    body {
      background: #f8fafc;
    }

    /* Avatar pill (match user navbar) */
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

    /* Underline only on main nav (not profile dropdown) */
    .navbar-nav .nav-item:not(.dropdown) .nav-link {
      position: relative;
      padding-bottom: 4px;
      transition: color .2s ease-in-out;
    }

    .navbar-nav .nav-item:not(.dropdown) .nav-link::after {
      content: "";
      position: absolute;
      left: 0;
      bottom: 0;
      width: 0%;
      height: 2px;
      background-color: #ffaa2b;
      transition: width .25s ease-in-out;
    }

    .navbar-nav .nav-item:not(.dropdown) .nav-link:hover::after {
      width: 100%;
    }

    /* Hero */
    .hero-section {
      padding: 56px 0 30px;
      text-align: center;
    }

    .footer {
      background: #1a202c;
      color: #fff;
      padding: 24px 0 10px;
      text-align: center;
    }

    /* Envelope badge */
    .badge-dot {
      position: absolute;
      top: -6px;
      right: -6px;
      font-size: .70rem;
    }

    /* Slide-in panel */
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
      scrollbar-width: none;
    }

    .inbox-body::-webkit-scrollbar {
      display: none;
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

    /* Cards */
    .app-card {
      border: 1px solid #e9ecef;
      border-radius: .75rem;
    }

    .app-card .card-body {
      padding: .85rem .9rem;
    }

    .app-card.unread {
      background: #fffdf5;
      border-left: 4px solid #ffc107;
    }

    .app-card.read {
      background: #f8f9fa;
      border-left: 4px solid #e9ecef;
    }

    .status-badge {
      font-size: .82rem;
    }

    .app-photo {
      width: 50px;
      height: 50px;
      border-radius: 50%;
      object-fit: cover;
      background: #eee;
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
      flex: 0 0 50px;
    }

    .app-actions {
      display: flex;
      gap: .42rem;
      flex-wrap: wrap;
      align-items: center;
      justify-content: flex-end;
      min-width: 160px;
    }

    .btn-icon-sm {
      padding: .28rem .55rem;
      font-size: .84rem;
      line-height: 1;
    }

    .btn-detail {
      background: #0dcaf0;
      color: #fff;
    }

    .btn-detail:hover {
      background: #0bb8db;
      color: #fff;
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

          <li class="nav-item">
            <a class="btn btn-warning ms-2 text-white fw-bold" href="post_job.php" style="border-radius:0.6rem;">Post Job</a>
          </li>

          <!-- Envelope button toggles panel -->
          <li class="nav-item ms-2">
            <button id="btnInbox" class="btn btn-outline-secondary position-relative" type="button" title="Applications inbox">
              <i class="bi bi-envelope"></i>
              <?php if ($badge_count > 0): ?>
                <span class="badge rounded-pill text-bg-danger badge-dot"><?= $badge_count > 99 ? '99+' : $badge_count ?></span>
              <?php endif; ?>
            </button>
          </li>

          <!-- Company dropdown (logo/initials + name) -->
          <li class="nav-item dropdown ms-lg-2">
            <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              <?php if ($logo_src !== ''): ?>
                <img src="<?= e($logo_src) ?>" alt="Company" class="avatar-img">
              <?php else: ?>
                <span class="avatar"><?= e(name_initials($company_name)) ?></span>
              <?php endif; ?>
              <span class="d-none d-lg-inline"><?= e($company_name ?: 'Company') ?></span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><a class="dropdown-item" href="company_profile.php">Profile</a></li>
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
      <h1 class="display-6 fw-bold">Welcome back<?= $company_name ? ', ' . e($company_name) : '' ?>!</h1>
      <p class="lead mb-4">Search for top talent and post new job opportunities with JobHive.</p>
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
          <button name="mark_all_company" value="1" class="btn btn-light btn-sm">Mark all read</button>
        </form>
        <button id="btnCloseInbox" class="btn btn-light btn-sm"><i class="bi bi-x-lg"></i></button>
      </div>
    </div>
    <div class="inbox-body">
      <?php if (empty($apps)): ?>
        <p class="text-muted mb-0">No applications yet.</p>
        <?php else:
        foreach ($apps as $a):
          $aid = (int)$a['application_id'];
          $isUnread = empty($readMap[$aid]);
          $cardCls  = $isUnread ? 'unread' : 'read';

          // Resolve user photo path
          $pp = $a['profile_picture'] ?? '';
          if ($pp && !preg_match('~^https?://~', $pp) && !preg_match('~^profile_pics/~', $pp)) {
            $pp = 'profile_pics/' . ltrim($pp, '/');
          }
          $initials = name_initials($a['full_name']);

          // Status badge class
          $st = (string)$a['status'];
          $stCls = ($st === 'Pending') ? 'bg-warning' : (($st === 'Accepted') ? 'bg-success' : 'bg-secondary');
        ?>
          <div class="card app-card <?= $cardCls ?> shadow-sm mb-3">
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
                  <div class="text-muted">
                    <?= e($a['full_name']) ?> applied<?= $a['location'] ? ' from ' . e($a['location']) : '' ?>.
                    <?php if (!empty($a['email'])): ?>
                      <span class="ms-1"><i class="bi bi-envelope me-1"></i><a href="mailto:<?= e($a['email']) ?>"><?= e($a['email']) ?></a></span>
                    <?php endif; ?>
                    <?php if (!empty($a['phone'])): ?>
                      <span class="ms-2"><i class="bi bi-telephone me-1"></i><a href="tel:<?= e($a['phone']) ?>"><?= e($a['phone']) ?></a></span>
                    <?php endif; ?>
                  </div>
                </div>
              </div>

              <div class="ms-3 app-actions">
                <a class="btn btn-detail btn-icon-sm" href="app_user_detail.php?application_id=<?= (int)$a['application_id'] ?>">
                  <i class="bi bi-eye me-1"></i>Detail
                </a>

                <?php if ($isUnread): ?>
                  <form method="post" class="m-0">
                    <button class="btn btn-light btn-icon-sm" name="mark_one_company" value="<?= (int)$a['application_id'] ?>">Mark read</button>
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

  <!-- Footer -->
  <footer class="footer mt-4">
    <div class="container">
      <div class="mb-2">
        <a href="#" class="text-white text-decoration-none me-3">About</a>
        <a href="#" class="text-white text-decoration-none me-3">Contact</a>
        <a href="#" class="text-white text-decoration-none">Privacy Policy</a>
      </div>
      <small>&copy; <?= date('Y') ?> JobHive. All rights reserved.</small>
    </div>
  </footer>

  <!-- Toggle logic -->
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
      const qs = url.searchParams.toString();
      history.replaceState(null, "", url.pathname + (qs ? ('?' + qs) : ''));
    })();
    <?php endif; ?>
  </script>
</body>

</html>