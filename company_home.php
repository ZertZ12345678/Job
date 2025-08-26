<?php

require_once "connect.php";
session_start();

/* ===== Auth guard ===== */
$company_id = $_SESSION['company_id'] ?? null;
if (!$company_id) {
  header("Location: login.php");
  exit;
}

/* ===== Load PENDING applications for this company (for badge + panel) ===== */
$apps = [];
$pending_count = 0;
try {
  $q = "
    SELECT a.application_id, a.status, a.applied_at, a.resume,
           u.user_id, u.full_name, u.email, u.phone, u.address AS location, u.profile_picture,
           j.job_id, j.job_title
    FROM application a
    JOIN jobs j  ON a.job_id=j.job_id
    JOIN users u ON a.user_id=u.user_id
    WHERE j.company_id=? AND a.status='Pending'
    ORDER BY a.applied_at DESC
  ";
  $st = $pdo->prepare($q);
  $st->execute([$company_id]);
  $apps = $st->fetchAll(PDO::FETCH_ASSOC);
  $pending_count = count($apps);
} catch (PDOException $e) {
  $apps = [];
  $pending_count = 0;
}

/* ===== Helpers ===== */
function e($v)
{
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
/** Build initials from full name (first + last token) */
function name_initials($name)
{
  $name = trim(preg_replace('/\s+/', ' ', (string)$name));
  if ($name === '') return 'U';
  $parts = explode(' ', $name);
  $first = mb_substr($parts[0], 0, 1, 'UTF-8');
  $last  = count($parts) > 1 ? mb_substr(end($parts), 0, 1, 'UTF-8') : '';
  return mb_strtoupper($first . $last, 'UTF-8');
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
    body {
      background: #f8fafc;
    }

    /* Hero/search */
    .hero-section {
      padding: 56px 0 30px;
      text-align: center;
    }

    .search-bar {
      max-width: 700px;
      margin: 22px auto 0;
      box-shadow: 0 2px 16px rgba(0, 0, 0, .06);
      border-radius: 1rem;
      background: #fff;
      padding: 1rem 1.2rem;
      display: flex;
      flex-direction: column;
      gap: .8rem;
    }

    .search-row {
      display: flex;
      flex-wrap: wrap;
      gap: .7rem;
      align-items: center;
    }

    .btn-search {
      min-width: 110px;
      background: #ffc107;
      color: #fff;
      border: none;
      border-radius: .6rem;
      font-weight: 600;
      padding: .45rem .9rem;
    }

    .btn-search:hover {
      background: #ff8800;
    }

    .footer {
      background: #1a202c;
      color: #fff;
      padding: 24px 0 10px;
      text-align: center;
    }

    /* ===== Envelope badge ===== */
    .badge-dot {
      position: absolute;
      top: -6px;
      right: -6px;
      font-size: .70rem;
    }

    /* ===== Slide-in panel (right half) ===== */
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
      /* Firefox hide */
    }

    .inbox-body::-webkit-scrollbar {
      display: none;
    }

    /* Chrome/Edge hide */

    /* Cards */
    .app-card {
      border: 1px solid #e9ecef;
      border-radius: .75rem;
    }

    .app-card .card-body {
      padding: .85rem .9rem;
    }

    /* Photo / Initials avatar */
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

    .app-info .line {
      margin-bottom: 3px;
    }

    .app-actions {
      display: flex;
      gap: .42rem;
      flex-wrap: wrap;
      align-items: center;
      justify-content: flex-end;
      min-width: 120px;
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

    /* Dim background when panel open */
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

    @media (max-width: 992px) {
      .inbox-panel {
        width: 100vw;
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
          <li class="nav-item"><a class="nav-link" href="company_profile.php">Profile</a></li>
          <li class="nav-item"><a class="nav-link" href="c_dashboard.php">Dashboard</a></li>

          <li class="nav-item">
            <a class="btn btn-warning ms-2 text-white fw-bold" href="post_job.php" style="border-radius:0.6rem;">Post Job</a>
          </li>

          <!-- Envelope button toggles panel -->
          <li class="nav-item ms-2">
            <button id="btnInbox" class="btn btn-outline-secondary position-relative" type="button" title="Applications inbox">
              <i class="bi bi-envelope"></i>
              <?php if ($pending_count > 0): ?>
                <span class="badge rounded-pill text-bg-danger badge-dot"><?= $pending_count > 99 ? '99+' : $pending_count ?></span>
              <?php endif; ?>
            </button>
          </li>

          <li class="nav-item"><a class="btn btn-outline-warning ms-2" href="index.php">Logout</a></li>
        </ul>
      </div>
    </div>
  </nav>

  <!-- Hero/Search -->
  <section class="hero-section">
    <div class="container">
      <h1 class="display-6 fw-bold">Welcome to Your Company Hub!</h1>
      <p class="lead mb-4">Search for top talent and post new job opportunities with JobHive.</p>

      <form class="search-bar" autocomplete="off" method="get" action="company_search_seekers.php">
        <input class="form-control mb-2" type="text" name="q" placeholder="Search for job seekers, skills, education...">
        <div class="search-row">
          <select class="form-select" name="dept" style="max-width:230px;">
            <option value="">All Departments</option>
            <option>Engineering</option>
            <option>Marketing</option>
            <option>HR</option>
            <option>Finance</option>
          </select>
          <button class="btn btn-search" type="submit">Search</button>
        </div>
      </form>

      <div class="popular-tags mt-2 d-flex justify-content-center gap-2">
        <span class="popular-label fw-semibold me-1">Popular:</span>
        <button type="button" class="popular-btn btn btn-outline-warning btn-sm">Developer</button>
        <button type="button" class="popular-btn btn btn-outline-warning btn-sm">Sales</button>
        <button type="button" class="popular-btn btn btn-outline-warning btn-sm">Designer</button>
        <button type="button" class="popular-btn btn btn-outline-warning btn-sm">Finance</button>
      </div>
    </div>
  </section>

  <!-- Slide-in inbox -->
  <div id="backdrop" class="backdrop"></div>

  <aside id="inboxPanel" class="inbox-panel" aria-hidden="true">
    <div class="inbox-header">
      <div class="d-flex align-items-center gap-2">
        <i class="bi bi-envelope text-warning fs-5"></i>
        <h5 class="m-0">Applications</h5>
        <span class="text-secondary">(Pending: <?= (int)$pending_count ?>)</span>
      </div>
      <button id="btnCloseInbox" class="btn btn-light btn-sm">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>
    <div class="inbox-body">
      <?php if ($pending_count === 0): ?>
        <p class="text-muted mb-0">No pending applications right now.</p>
        <?php else: foreach ($apps as $a):
          // Resolve profile picture path (DB may store only filename like 'user_4_....jpg')
          $pp = $a['profile_picture'] ?? '';
          if ($pp && !preg_match('~^https?://~', $pp) && !preg_match('~^profile_pics/~', $pp)) {
            $pp = 'profile_pics/' . ltrim($pp, '/');
          }
          $initials = name_initials($a['full_name']);
        ?>
          <div class="card app-card shadow-sm mb-3">
            <div class="card-body d-flex">
              <?php if (!empty($pp)): ?>
                <img class="app-photo me-3" src="<?= e($pp) ?>" alt="photo">
              <?php else: ?>
                <div class="avatar-initials me-3" title="No photo"><?= e($initials) ?></div>
              <?php endif; ?>

              <div class="flex-grow-1 app-info">
                <div class="line fw-semibold"><?= e($a['full_name']) ?></div>
                <div class="line small text-muted">
                  <i class="bi bi-briefcase me-1"></i><?= e($a['job_title']) ?>
                  &nbsp;•&nbsp;<i class="bi bi-geo-alt me-1"></i><?= e($a['location'] ?: '—') ?>
                </div>
                <div class="line small">
                  <i class="bi bi-envelope me-1"></i>
                  <a href="mailto:<?= e($a['email']) ?>"><?= e($a['email']) ?></a>
                  <?php if (!empty($a['phone'])): ?>
                    &nbsp;•&nbsp;<i class="bi bi-telephone me-1"></i>
                    <a href="tel:<?= e($a['phone']) ?>"><?= e($a['phone']) ?></a>
                  <?php endif; ?>
                </div>
                <div class="line small text-muted">
                  Applied: <?= e(date('M d, Y H:i', strtotime($a['applied_at']))) ?>
                </div>
              </div>

              <!-- Detail only -->
              <div class="ms-2 app-actions">
                <a class="btn btn-detail btn-icon-sm" href="app_user_detail.php?application_id=<?= (int)$a['application_id'] ?>">
                  <i class="bi bi-eye me-1"></i>Detail
                </a>
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
      <small>&copy; 2025 JobHive. All rights reserved.</small>
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
      document.body.style.overflow = 'hidden'; // prevent page scroll
    }

    function closeInbox() {
      panel.classList.remove('open');
      backdrop.classList.remove('show');
      panel.setAttribute('aria-hidden', 'true');
      document.body.style.overflow = ''; // restore page scroll
    }
    btnInbox.addEventListener('click', openInbox);
    btnClose.addEventListener('click', closeInbox);
    backdrop.addEventListener('click', closeInbox);
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') closeInbox();
    });
  </script>
</body>

</html>