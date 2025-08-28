<?php
require_once "connect.php";
if (session_status() === PHP_SESSION_NONE) session_start();

/* ---------- Helpers ---------- */
function e($v)
{
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
function safe_truncate($t, $n = 140, $el = '…')
{
  $t = (string)($t ?? '');
  return function_exists('mb_strimwidth') ? mb_strimwidth($t, 0, $n, $el) : ((strlen($t) > $n) ? substr($t, 0, $n - strlen($el)) . $el : $t);
}

/* ---------- Inputs (GET) ---------- */
$keyword  = trim($_GET['keyword'] ?? '');
$location = trim($_GET['location'] ?? 'All Locations');
$job_type = trim($_GET['job_type'] ?? '');   // Software | Network | ''

if ($job_type === 'All') $job_type = '';     // treat All as no filter

/* ---------- Build query ---------- */
$sql = "SELECT j.job_id, j.job_title, j.job_description, j.location, j.status, j.job_type,
               j.posted_at, c.company_name, c.logo
        FROM jobs j
        JOIN companies c ON c.company_id = j.company_id
        WHERE j.status = 'Active'";
$params = [];

/* Keyword search */
if ($keyword !== '') {
  $sql .= " AND (j.job_title LIKE ? OR c.company_name LIKE ?)";
  $params[] = "%$keyword%";
  $params[] = "%$keyword%";
}

/* Location filter */
if ($location !== '' && $location !== 'All Locations') {
  $sql .= " AND j.location LIKE ?";
  $params[] = "%$location%";
}

/* job_type filter */
if ($job_type !== '') {
  $sql .= " AND j.job_type = ?";
  $params[] = $job_type;
}

/* newest first; limit 6 */
$sql .= " ORDER BY j.posted_at DESC, j.job_id DESC LIMIT 6";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Preserve keyword/location when clicking Popular */
$baseQuery = ['keyword' => $keyword, 'location' => $location];
function url_with($base, $extra)
{
  return '?' . http_build_query(array_merge($base, $extra));
}

$LOGO_DIR = "company_logos/";
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>JobHive | Home</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <style>
    body {
      background: #f8fafc;
    }

    .navbar .btn-outline-warning {
      border-width: 2px;
    }

    /* ===== Navbar link underline on hover ===== */
    .navbar-nav .nav-link {
      position: relative;
      padding-bottom: 4px;
      /* space for underline */
      transition: color 0.2s ease-in-out;
    }

    .navbar-nav .nav-link::after {
      content: "";
      position: absolute;
      left: 0;
      bottom: 0;
      width: 0%;
      height: 2px;
      background-color: #ffaa2b;
      transition: width 0.25s ease-in-out;
    }

    .navbar-nav .nav-link:hover::after {
      width: 100%;
    }


    .hero-section {
      background: #f8fafc;
      padding: 70px 0 30px;
      text-align: center;
    }

    .search-card {
      max-width: 900px;
      margin: 0 auto;
      border-radius: 1.5rem;
      background: #fff;
      box-shadow: 0 20px 60px rgba(2, 8, 20, 0.08);
      padding: 1.25rem 1.5rem;
    }

    .search-card .form-control {
      min-height: 52px;
      border-radius: .8rem;
      font-size: 1rem;
      padding: .65rem .9rem;
    }

    .search-row {
      display: flex;
      flex-wrap: wrap;
      gap: .75rem;
      align-items: center;
      margin-top: .35rem;
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

    .popular-wrap {
      margin-top: 1rem
    }

    .popular-label {
      font-size: 1.05rem;
      color: #22223b;
      font-weight: 600;
      margin-right: 16px
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

    .popular-btn.active {
      background: #fff8ec;
      border-color: #ff8800;
      color: #ff8800
    }

    .job-card {
      border-radius: 1.25rem;
      box-shadow: 0 2px 16px rgba(0, 0, 0, .06);
      border: 0;
    }

    .company-logo {
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
  </style>
</head>

<body>

  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg bg-white shadow-sm">
    <div class="container">
      <a class="navbar-brand fw-bold text-warning" href="index.php">JobHive</a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"><span class="navbar-toggler-icon"></span></button>
      <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
        <ul class="navbar-nav">
          <li class="nav-item"><a class="nav-link" href="index_all_companies.php">All Companies</a></li>
          <li class="nav-item"><a class="nav-link" href="login.php">Login</a></li>
          <li class="nav-item"><a class="btn btn-warning ms-2 text-white" href="sign_up.php">Register</a></li>
          <li class="nav-item"><a class="btn btn-outline-warning ms-2" href="c_sign_up.php">Company Register</a></li>
        </ul>
      </div>
    </div>
  </nav>

  <!-- Hero + Search -->
  <section class="hero-section">
    <div class="container">
      <h1 class="display-5 fw-bold">Find Your Dream Job on JobHive</h1>
      <p class="lead mb-4">Connecting you to thousands of opportunities across Myanmar.</p>

      <div class="search-card">
        <form method="get" class="w-100">
          <div class="row g-2">
            <div class="col-12">
              <input class="form-control form-control-lg" name="keyword" value="<?= e($keyword) ?>"
                placeholder="Job title, skills, company..." />
            </div>
          </div>
          <div class="search-row">
            <select class="form-select form-select-lg" name="location">
              <option <?= ($location === 'All Locations' ? 'selected' : '') ?>>All Locations</option>
              <option <?= ($location === 'Yangon' ? 'selected' : '') ?>>Yangon</option>
              <option <?= ($location === 'Mandalay' ? 'selected' : '') ?>>Mandalay</option>
              <option <?= ($location === 'Naypyidaw' ? 'selected' : '') ?>>Naypyidaw</option>
            </select>
            <?php if ($job_type !== ''): ?><input type="hidden" name="job_type" value="<?= e($job_type) ?>"><?php endif; ?>
            <button class="btn btn-search" type="submit">Search</button>
          </div>
        </form>
      </div>

      <!-- Popular chips: Software, Network, All Jobs -->
      <div class="popular-wrap">
        <span class="popular-label">Popular:</span>
        <a href="<?= url_with($baseQuery, ['job_type' => 'Software']) ?>"
          class="btn popular-btn <?= ($job_type === 'Software' ? 'active' : '') ?>">Software</a>
        <a href="<?= url_with($baseQuery, ['job_type' => 'Network']) ?>"
          class="btn popular-btn <?= ($job_type === 'Network' ? 'active' : '') ?>">Network</a>
        <a href="<?= url_with($baseQuery, ['job_type' => 'All']) ?>"
          class="btn popular-btn <?= ($job_type === '' ? 'active' : '') ?>">All Jobs</a>
      </div>
    </div>
  </section>

  <!-- Featured Jobs -->
  <section class="container py-4">
    <h2 class="mb-4 fw-semibold text-center">Featured Jobs</h2>
    <?php if (!$jobs): ?>
      <div class="alert alert-danger text-center rounded-4 shadow-sm mx-auto" role="alert" style="max-width:1200px;">
        No jobs to show for your selection.
      </div>
    <?php else: ?>
      <div class="row g-4">
        <?php foreach ($jobs as $job): ?>
          <div class="col-12 col-md-6 col-lg-4">
            <div class="card job-card h-100">
              <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                  <?php $logo = trim((string)$job['logo']); ?>
                  <img class="company-logo" src="<?= e($logo ? ($LOGO_DIR . $logo) : 'https://via.placeholder.com/56') ?>"
                    alt="Company logo" onerror="this.src='https://via.placeholder.com/56'">
                  <div class="ms-3">
                    <h5 class="mb-0"><?= e($job['job_title']) ?></h5>
                    <small class="text-muted"><?= e($job['company_name']) ?></small>
                  </div>
                </div>
                <div class="mb-2">
                  <span class="badge bg-light text-dark border job-badge"><?= e($job['location']) ?></span>
                  <?php if (!empty($job['job_type'])): ?>
                    <span class="badge bg-light text-dark border job-badge"><?= e($job['job_type']) ?></span>
                  <?php endif; ?>
                </div>
                <p class="text-muted small mb-3"><?= e(safe_truncate($job['job_description'], 160, '…')) ?></p>
                <div class="d-flex justify-content-between align-items-center">
                  <span class="badge job-badge bg-success">Active</span>
                  <a class="btn btn-outline-warning" href="login.php">Apply</a>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <!-- How it Works -->
  <section class="bg-light py-5">
    <div class="container">
      <h2 class="text-center mb-5 fw-semibold">How JobHive Works</h2>
      <div class="row text-center">
        <div class="col-md-4">
          <div class="fs-1 mb-2" style="color:#ffaa2b;">🔎</div>
          <h5>1. Search Jobs</h5>
          <p>Browse thousands of jobs by title, skill, or location to find your fit.</p>
        </div>
        <div class="col-md-4">
          <div class="fs-1 mb-2" style="color:#ffaa2b;">📄</div>
          <h5>2. Apply Online</h5>
          <p>Apply to jobs easily with your JobHive profile and resume.</p>
        </div>
        <div class="col-md-4">
          <div class="fs-1 mb-2" style="color:#ffaa2b;">💼</div>
          <h5>3. Get Hired</h5>
          <p>Connect with top companies and land your next career move.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- Featured Companies -->
  <section class="container py-5">
    <h2 class="mb-4 fw-semibold text-center">Featured Companies</h2>
    <div class="d-flex flex-wrap justify-content-center gap-4">
      <a href="https://ayabank.com/"><img src="company_logos/logo_688bd678d33ad.png" class="company-logo" alt="AYA"></a>
      <a href="https://www.kbzbank.com/en/"><img src="company_logos/logo_688bd4015182f.png" class="company-logo" alt="KBZ"></a>
      <a href="https://shwebank.com/"><img src="company_logos/logo_6894abc08379b.png" class="company-logo" alt="Shwe"></a>
      <a href="https://www.uab.com.mm/"><img src="company_logos/logo_6894ab92e37cb.jpg" class="company-logo" alt="UAB"></a>
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