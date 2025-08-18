<?php
require_once "connect.php";

if (!isset($_SESSION)) {
  session_start();
}

$LOGO_DIR = "company_logos/";

// --- Helper: safe text truncation (for display only) ---
if (!function_exists('safe_truncate')) {
  function safe_truncate($text, $limit = 160, $ellipsis = '…')
  {
    $text = (string)($text ?? '');
    if (function_exists('mb_strimwidth')) {
      return mb_strimwidth($text, 0, $limit, $ellipsis);
    }
    return (strlen($text) > $limit)
      ? substr($text, 0, $limit - strlen($ellipsis)) . $ellipsis
      : $text;
  }
}

// --- 1) Auto-update jobs whose deadline has passed -> Inactive ---
try {
  $today = date('Y-m-d');
  $up = $pdo->prepare("UPDATE jobs SET status='Inactive' WHERE status='Active' AND deadline < ?");
  $up->execute([$today]);
} catch (PDOException $e) {
  // optional: log
}

// --- 2) Build search filters (NO DESCRIPTION SEARCH) ---
$q        = '';
$loc      = '';
$isSearch = false;

if (isset($_GET['csearch'])) {
  // Form’s Search button pressed
  $q   = trim($_GET['q']   ?? '');
  $loc = trim($_GET['loc'] ?? '');
  $isSearch = true;
} elseif (isset($_GET['q'])) {
  // Popular quick tag (only q)
  $q = trim($_GET['q']);
  $isSearch = true;
}

$conds  = [];
$params = [];

/* Only job title and company name */
if ($q !== '') {
  $conds[] = "(j.job_title LIKE ? OR c.company_name LIKE ?)";
  $like = "%{$q}%";
  array_push($params, $like, $like);
}

/* Location filter (partial match allowed) */
if ($loc !== '') {
  $conds[] = "j.location LIKE ?";
  $params[] = "%{$loc}%";
}

$whereSql = $conds ? "WHERE " . implode(" AND ", $conds) : "";

// --- 3) Fetch jobs: Active first; Inactive & Closed last; newest first within each group ---
try {
  $sql = "
    SELECT
      j.job_id,
      j.job_title,
      j.job_description,
      j.location,
      j.status,
      j.posted_at,
      c.company_name,
      c.logo
    FROM jobs j
    JOIN companies c ON c.company_id = j.company_id
    $whereSql
    ORDER BY 
      CASE j.status
        WHEN 'Active' THEN 1
        WHEN 'Inactive' THEN 2
        WHEN 'Closed' THEN 2
        ELSE 3
      END,
      j.posted_at DESC
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
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>JobHive | User Home</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <style>
    body {
      background: #f8fafc;
    }

    .hero-section {
      background: #f8fafc;
      padding: 70px 0 50px 0;
      text-align: center;
    }

    .search-bar {
      max-width: 700px;
      margin: 0 auto;
      margin-top: 30px;
      box-shadow: 0 2px 16px rgba(0, 0, 0, .06);
      border-radius: 1.5rem;
      background: #fff;
      padding: 1.5rem 2rem;
      display: flex;
      flex-direction: column;
      gap: 1rem;
    }

    .search-row {
      display: flex;
      flex-wrap: wrap;
      gap: 1rem;
      align-items: center;
      margin-top: .3rem;
    }

    .search-bar input[type="text"] {
      flex: 1 1 auto;
      min-width: 180px;
    }

    .search-bar select {
      min-width: 170px;
      max-width: 230px;
    }

    .btn-search {
      min-width: 110px;
      background: #ffc107;
      color: #fff;
      border: none;
      border-radius: .7rem;
      font-weight: 500;
      font-size: 1rem;
      padding: .45rem 0;
      transition: background .18s;
    }

    .btn-search:hover {
      background: #ff8800;
      color: #fff;
    }

    .popular-label {
      font-size: 1.12rem;
      color: #22223b;
      font-weight: 500;
      margin-right: 50px;
    }

    .popular-tags {
      margin-top: 1rem;
      display: flex;
      gap: .7rem;
      justify-content: center;
    }

    .popular-btn {
      border: 1.7px solid #ffc107;
      color: #ffc107;
      background: #fff;
      font-size: 1.01rem;
      border-radius: .55rem;
      padding: .33rem 1.25rem;
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
      padding: 30px 0 10px 0;
      text-align: center;
    }
  </style>
</head>

<body>
  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
    <div class="container">
      <a class="navbar-brand fw-bold text-warning" href="home.php">JobHive</a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
        <ul class="navbar-nav">
          <li class="nav-item"><a class="nav-link active" aria-current="page" href="home.php">Home</a></li>
          <li class="nav-item"><a class="nav-link" href="user_dashboard.php">Dashboard</a></li>
          <li class="nav-item"><a class="nav-link" href="recommended.php">Recommended Jobs</a></li>
          <li class="nav-item"><a class="nav-link" href="companies.php">All Companies</a></li>
          <li class="nav-item"><a class="btn btn-outline-warning ms-2" href="index.php">Logout</a></li>
        </ul>
      </div>
    </div>
  </nav>

  <!-- Hero Section -->
  <section class="hero-section">
    <div class="container">
      <h1 class="display-5 fw-bold">Welcome back to JobHive!</h1>
      <p class="lead mb-4">Ready to discover your next opportunity?</p>

      <!-- Search form (returns to this page) -->
      <form class="search-bar" autocomplete="off" method="get" action="user_home.php">
        <input class="form-control mb-2" type="text" name="q" placeholder="Job title or company..."
          value="<?= htmlspecialchars($q) ?>">
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
          <!-- submit button named csearch -->
          <button class="btn btn-search" type="submit" name="csearch" value="1">Search</button>
        </div>
      </form>

      <!-- Popular quick tags -->
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
          <!-- RED warning when a search returns nothing -->
          <div class="alert alert-danger text-center" role="alert">
            No jobs to show for your search.
          </div>
        <?php else: ?>
          <!-- Neutral info when simply no data yet -->
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
                    <img
                      class="logo"
                      src="<?= htmlspecialchars($LOGO_DIR . $job['logo']) ?>"
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
                    <span class="badge job-badge 
                      <?= $job['status'] === 'Active' ? 'bg-success' : ($job['status'] === 'Inactive' ? 'bg-secondary' : 'bg-danger') ?>">
                      <?= htmlspecialchars($job['status']) ?>
                    </span>
                    <a class="btn btn-outline-warning" href="job_detail.php?id=<?= (int)$job['job_id'] ?>">
                      Detail
                    </a>
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
      <small>&copy; 2025 JobHive. All rights reserved.</small>
    </div>
  </footer>
</body>

</html>