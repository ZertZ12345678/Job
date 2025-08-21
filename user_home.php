<?php
require_once "connect.php";
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

/* ===== Guard: require logged-in user ===== */
if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit;
}
$user_id   = (int)($_SESSION['user_id'] ?? 0);
$full_name = trim($_SESSION['full_name'] ?? '');
$email     = trim($_SESSION['email'] ?? '');

/* ===== Paths & helpers ===== */
$LOGO_DIR    = "company_logos/";
$PROFILE_DIR = "profile_pics/";

/* Safe truncate (for display only) */
if (!function_exists('safe_truncate')) {
  function safe_truncate($text, $limit = 160, $ellipsis = '…')
  {
    $text = (string)($text ?? '');
    if (function_exists('mb_strimwidth')) return mb_strimwidth($text, 0, $limit, $ellipsis);
    return (strlen($text) > $limit) ? substr($text, 0, $limit - strlen($ellipsis)) . $ellipsis : $text;
  }
}

/* Helper for initials (avatar fallback) */
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

/* ===== Fetch fresh user row (source of truth) ===== */
$profile_picture = $_SESSION['profile_picture'] ?? null;
try {
  $u = $pdo->prepare("SELECT full_name, email, profile_picture FROM users WHERE user_id = ? LIMIT 1");
  $u->execute([$user_id]);
  $row = $u->fetch(PDO::FETCH_ASSOC);

  if ($row) {
    $full_name        = $row['full_name'] ?: $full_name;
    $email            = $row['email'] ?: $email;
    $profile_picture  = $row['profile_picture'] ?? null;

    // Keep session in sync so other pages also show correct user
    $_SESSION['full_name']        = $full_name;
    $_SESSION['email']            = $email;
    $_SESSION['profile_picture']  = $profile_picture;
  } else {
    // User row missing (deleted?) -> log out safely
    header("Location: index.php");
    exit;
  }
} catch (PDOException $e) {
  // optional: log
}

/* ===== 1) Auto-set past-deadline jobs to Inactive ===== */
try {
  $today = date('Y-m-d');
  $up = $pdo->prepare("UPDATE jobs SET status='Inactive' WHERE status='Active' AND deadline < ?");
  $up->execute([$today]);
} catch (PDOException $e) {
}

/* ===== 2) Build search filters (NO DESCRIPTION SEARCH) ===== */
$q        = '';
$loc      = '';
$isSearch = false;

if (isset($_GET['csearch'])) {
  $q   = trim($_GET['q']   ?? '');
  $loc = trim($_GET['loc'] ?? '');
  $isSearch = true;
} elseif (isset($_GET['q'])) {
  $q = trim($_GET['q'] ?? '');
  $isSearch = true;
}

$conds  = [];
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

$whereSql = $conds ? "WHERE " . implode(" AND ", $conds) : "";

/* ===== 3) Fetch jobs: Active → Inactive → Closed; newest first in each ===== */
try {
  $sql = "
    SELECT
      j.job_id, j.job_title, j.job_description, j.location,
      j.status, j.posted_at, c.company_name, c.logo
    FROM jobs j
    JOIN companies c ON c.company_id = j.company_id
    $whereSql
    ORDER BY 
      CASE j.status
        WHEN 'Active' THEN 1
        WHEN 'Inactive' THEN 2
        WHEN 'Closed' THEN 3
        ELSE 4
      END,
      j.posted_at DESC
    LIMIT 60
  ";
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  $jobs = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>JobHive | User Home</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
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

    /* === Search area sizing & look === */
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
      transition: background .18s;
    }

    .btn-search:hover {
      background: #ff9800;
      color: #fff;
    }

    .search-bar input[type="text"] {
      flex: 1 1 auto;
      min-width: 280px;
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
      transition: background .12s, color .12s, border-color .12s;
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
  </style>
</head>

<body>
  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg bg-white shadow-sm">
    <div class="container">
      <a class="navbar-brand fw-bold text-warning" href="user_home.php">JobHive</a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
        <ul class="navbar-nav align-items-lg-center">
          <li class="nav-item"><a class="nav-link active" aria-current="page" href="user_home.php">Home</a></li>
          <li class="nav-item"><a class="nav-link" href="user_dashboard.php">Dashboard</a></li>
          <li class="nav-item"><a class="nav-link" href="recommended.php">Recommended Jobs</a></li>
          <li class="nav-item"><a class="nav-link" href="companies.php">All Companies</a></li>

          <!-- User dropdown -->
          <li class="nav-item dropdown ms-lg-2">
            <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              <?php if (!empty($profile_picture)): ?>
                <img src="<?= htmlspecialchars($PROFILE_DIR . $profile_picture) ?>" alt="Me"
                  style="width:32px;height:32px;border-radius:50%;border:1px solid rgba(0,0,0,.06);object-fit:cover;">
              <?php else: ?>
                <span class="avatar"><?= htmlspecialchars(initials($full_name)) ?></span>
              <?php endif; ?>
              <span class="d-none d-lg-inline"><?= htmlspecialchars($full_name ?: $email) ?></span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><a class="dropdown-item" href="user_profile.php">My Profile</a></li>
              <li><a class="dropdown-item" href="user_dashboard.php">My Dashboard</a></li>
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
      <h1 class="display-6 fw-bold">Welcome back<?= $full_name ? ', ' . htmlspecialchars($full_name) : '' ?>!</h1>
      <p class="lead mb-4">Ready to discover your next opportunity?</p>

      <!-- Search form -->
      <form class="search-bar" autocomplete="off" method="get" action="user_home.php">
        <input class="form-control mb-2" type="text" name="q" placeholder="Job title or company..." value="<?= htmlspecialchars($q) ?>">
        <div class="search-row">
          <select class="form-select" name="loc" style="max-width:230px;">
            <?php
            $locs = ['', 'Yangon', 'Mandalay', 'Naypyidaw'];
            foreach ($locs as $L) {
              $sel = ($loc === $L) ? 'selected' : '';
              $label = $L === '' ? 'All Locations' : $L;
              echo "<option value=\"" . htmlspecialchars($L) . "\" $sel>" . htmlspecialchars($label) . "</option>";
            }
            ?>
          </select>
          <button class="btn btn-search" type="submit" name="csearch" value="1">Search</button>
        </div>
      </form>

      <!-- Popular tags -->
      <div class="popular-tags">
        <span class="popular-label">Popular:</span>
        <form method="get" action="user_home.php" style="display:inline;">
          <input type="hidden" name="q" value="Software">
          <button type="submit" class="popular-btn">Software</button>
        </form>
        <form method="get" action="user_home.php" style="display:inline;">
          <input type="hidden" name="q" value="Network">
          <button type="submit" class="popular-btn">Network</button>
        </form>
      </div>
    </div>
  </section>

  <!-- Featured Jobs -->
  <section class="py-5">
    <div class="container">
      <h2 class="text-center fw-bold mb-4">Featured Jobs</h2>

      <?php if (empty($jobs)): ?>
        <?php if ($isSearch): ?>
          <div class="alert alert-danger text-center" role="alert">
            No jobs to show for your search.
          </div>
        <?php else: ?>
          <div class="alert alert-light border text-center" role="alert">
            No jobs to show yet.
          </div>
        <?php endif; ?>
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
                    <img class="logo"
                      src="<?= htmlspecialchars($logoPath !== '' ? $logoPath : 'https://via.placeholder.com/56') ?>"
                      alt="Company logo"
                      onerror="this.src='https://via.placeholder.com/56'">
                    <div class="ms-3">
                      <h5 class="mb-0"><?= htmlspecialchars($job['job_title']) ?></h5>
                      <small class="text-muted"><?= htmlspecialchars($job['company_name']) ?></small>
                    </div>
                  </div>

                  <span class="badge bg-light text-dark border job-badge mb-2">
                    <?= htmlspecialchars($job['location']) ?>
                  </span>

                  <p class="text-muted small mb-3">
                    <?= htmlspecialchars(safe_truncate($job['job_description'], 160, '…')) ?>
                  </p>

                  <div class="d-flex justify-content-between align-items-center">
                    <?php
                    $status = (string)$job['status'];
                    $statusClass = ($status === 'Active') ? 'bg-success'
                      : (($status === 'Inactive') ? 'bg-secondary' : 'bg-danger');
                    ?>
                    <span class="badge job-badge <?= $statusClass ?>">
                      <?= htmlspecialchars($status) ?>
                    </span>
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
</body>

</html>