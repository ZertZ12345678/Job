<?php
// job_detail.php
require_once "connect.php"; // provides $pdo (PDO)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$LOGO_DIR = "company_logos/";

// --- Helpers ---
function e($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
function fmt_date($d)
{
    if (!$d) return '';
    $ts = strtotime($d);
    return $ts ? date('M d, Y', $ts) : $d;
}

// --- 1) Validate job id ---
$job_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($job_id <= 0) {
    http_response_code(400);
    $error = "Invalid job id.";
}

// --- 2) Auto-update this job to Inactive if deadline passed ---
if (empty($error)) {
    try {
        $today = date('Y-m-d');
        $up = $pdo->prepare("UPDATE jobs SET status='Inactive' WHERE job_id=? AND status='Active' AND deadline < ?");
        $up->execute([$job_id, $today]);
    } catch (PDOException $e) { /* log if needed */
    }
}

// --- 3) Fetch job + company ---
$job = null;
if (empty($error)) {
    try {
        $sql = "
      SELECT j.job_id, j.job_title, j.description_detail, j.employment_type, j.requirements,
             j.salary, j.location, j.deadline, j.status, j.posted_at,
             c.company_id, c.company_name, c.email AS company_email, c.phone AS company_phone, c.logo AS company_logo
      FROM jobs j
      JOIN companies c ON c.company_id = j.company_id
      WHERE j.job_id = ?
      LIMIT 1
    ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$job_id]);
        $job = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$job) {
            http_response_code(404);
            $error = "Job not found.";
        }
    } catch (PDOException $e) {
        http_response_code(500);
        $error = "Failed to load job details.";
    }
}

// --- 4) Determine badge class for status ---
$badgeClass = "bg-secondary";
if ($job && isset($job['status'])) {
    $badgeClass = ($job['status'] === 'Active') ? 'bg-success' : (($job['status'] === 'Closed') ? 'bg-danger' : 'bg-secondary');
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Job Details | JobHive</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        body {
            background: #f8fafc;
        }

        .page-header {
            background: #fff;
            border-bottom: 1px solid rgba(0, 0, 0, .06);
        }

        .logo {
            width: 72px;
            height: 72px;
            object-fit: cover;
            border-radius: .75rem;
            background: #fff;
            border: 1px solid rgba(0, 0, 0, .06);
        }

        .card {
            border: 0;
            border-radius: 1rem;
            box-shadow: 0 6px 24px rgba(0, 0, 0, .06);
        }

        .meta small {
            color: #6c757d;
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold text-warning" href="user_home.php">JobHive</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-end" id="nav">
                <ul class="navbar-nav">
                    <li class="nav-item"><a class="nav-link" href="user_home.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="user_dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="companies.php">All Companies</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <header class="page-header py-4">
        <div class="container">
            <h1 class="h3 fw-bold mt-2 mb-0">Job Details</h1>
        </div>
    </header>

    <main class="py-4">
        <div class="container">
            <?php if (!empty($error)): ?>
                <div class="alert alert-warning"><?= e($error) ?></div>
            <?php else: ?>
                <div class="row g-4">
                    <div class="col-12 col-lg-8">
                        <div class="card p-4">
                            <div class="d-flex align-items-start">
                                <img class="logo" src="<?= e($LOGO_DIR . $job['company_logo']) ?>" alt="Company Logo" onerror="this.src='https://via.placeholder.com/72'">
                                <div class="ms-3">
                                    <h2 class="h4 mb-1"><?= e($job['job_title']) ?></h2>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="text-muted"><?= e($job['company_name']) ?></span>
                                        <span class="badge <?= $badgeClass ?>"><?= e($job['status']) ?></span>
                                    </div>
                                    <div class="meta mt-1">
                                        <small>Location: <?= e($job['location']) ?></small><br>
                                        <?php if (!empty($job['posted_at'])): ?><small>Posted: <?= e(fmt_date($job['posted_at'])) ?></small><?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <hr class="my-4">

                            <div class="row">
                                <div class="col-12 col-md-6 mb-3">
                                    <div class="fw-semibold">Employment Type</div>
                                    <div><?= e($job['employment_type']) ?></div>
                                </div>
                                <div class="col-12 col-md-6 mb-3">
                                    <div class="fw-semibold">Salary</div>
                                    <div><?= e($job['salary']) ?> MMK</div>
                                </div>
                                <div class="col-12 col-md-6 mb-3">
                                    <div class="fw-semibold">Deadline</div>
                                    <div><?= e(fmt_date($job['deadline'])) ?></div>
                                </div>
                                <div class="col-12 col-md-6 mb-3">
                                    <div class="fw-semibold">Status</div>
                                    <div><span class="badge <?= $badgeClass ?>"><?= e($job['status']) ?></span></div>
                                </div>
                            </div>

                            <div class="mt-3">
                                <div class="fw-semibold mb-1">Job Description</div>
                                <p class="mb-3" style="text-align: justify;"><?= nl2br(e($job['description_detail'])) ?></p>
                                <div class="fw-semibold mb-1">Requirements</div>
                                <p class="mb-0" style="text-align: justify;"><?= nl2br(e($job['requirements'])) ?></p>
                            </div>

                            <!-- >>> FIXED BUTTON BLOCK <<< -->
                            <div class="mt-4 d-flex gap-2 align-items-center">
                                <?php if (!isset($_SESSION['user_id'])): ?>
                                    <a class="btn btn-warning"
                                        href="login.php?next=<?= urlencode('resume.php?job_id=' . (int)$job['job_id']) ?>">
                                        I’m interested
                                    </a>
                                <?php else: ?>
                                    <a class="btn btn-warning"
                                        href="resume_premium.php?job_id=<?= (int)$job['job_id'] ?>">
                                        I’m interested
                                    </a>
                                <?php endif; ?>

                                <button type="button" class="btn btn-outline-secondary" onclick="history.back()">Back</button>

                                <?php if ($job['status'] !== 'Active'): ?>
                                    <span class="align-self-center text-muted small">This job is not active (you can still view/apply).</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Company Contact -->
                    <div class="col-12 col-lg-4">
                        <div class="card p-4">
                            <h3 class="h6 fw-bold mb-3">Company Contact</h3>
                            <div class="mb-2"><span class="fw-semibold">Company:</span> <?= e($job['company_name']) ?></div>
                            <div class="mb-2"><span class="fw-semibold">Email:</span> <?= e($job['company_email']) ?></div>
                            <div class="mb-2"><span class="fw-semibold">Phone:</span> <?= e($job['company_phone']) ?></div>
                            <div class="mb-2"><span class="fw-semibold">Location:</span> <?= e($job['location']) ?></div>
                            <hr>
                            <a class="btn btn-outline-secondary w-100" href="companies.php">View all companies</a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <footer class="mt-5 py-4 bg-dark text-white">
        <div class="container d-flex flex-column align-items-center">
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