<?php
// job_detail.php (fixed)
require_once "connect.php";
if (session_status() === PHP_SESSION_NONE) session_start();
$LOGO_DIR = "company_logos/";
/* ---------- Helpers ---------- */
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
function app_badge($s)
{
    $map = ['Pending' => 'secondary', 'Submitted' => 'secondary', 'Under Review' => 'info', 'Interview' => 'warning', 'Phone Screen' => 'warning', 'Accepted' => 'success', 'Offer' => 'success', 'Rejected' => 'danger'];
    $c = $map[$s] ?? 'secondary';
    return '<span class="badge text-bg-' . $c . '">' . e($s) . '</span>';
}
/* ---------- Viewer info (for premium routing) ---------- */
$is_logged_in = isset($_SESSION['user_id']);
$user_id = $_SESSION['user_id'] ?? null;
$package = $_SESSION['package'] ?? null;
if ($is_logged_in && ($package === null || $package === '')) {
    try {
        $st = $pdo->prepare("SELECT package FROM users WHERE user_id=? LIMIT 1");
        $st->execute([$user_id]);
        $package = (string)($st->fetchColumn() ?: 'normal');
        $_SESSION['package'] = $package;
    } catch (PDOException $e) {
        $package = 'normal';
    }
}
$is_premium = (strtolower((string)$package) === 'premium');
/* ---------- Accept both ?job_id= and ?id= ---------- */
$job_id = 0;
if (isset($_GET['job_id'])) {
    $job_id = (int)$_GET['job_id'];
} elseif (isset($_GET['id'])) {
    $job_id = (int)$_GET['id'];
}
if ($job_id <= 0) {
    http_response_code(400);
    $error = "Invalid job id.";
}
/* ---------- Auto-inactivate if past deadline ---------- */
if (empty($error)) {
    try {
        $today = date('Y-m-d');
        $up = $pdo->prepare("UPDATE jobs SET status='Inactive' WHERE job_id=? AND status='Active' AND deadline < ?");
        $up->execute([$job_id, $today]);
    } catch (PDOException $e) { /* optional: log */
    }
}
/* ---------- Fetch job + company ---------- */
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
/* ---------- Fetch MY application to this job (if any) ---------- */
$myApp = null;
if (empty($error) && $is_logged_in) {
    try {
        $s = $pdo->prepare("
      SELECT application_id, status, applied_at, resume
      FROM application
      WHERE user_id=? AND job_id=?
      ORDER BY applied_at DESC
      LIMIT 1
    ");
        $s->execute([$user_id, $job_id]);
        $myApp = $s->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (PDOException $e) {
        $myApp = null;
    }
}
/* ---------- Status badge (job status) ---------- */
$jobBadge = "bg-secondary";
if ($job && isset($job['status'])) {
    $jobBadge = ($job['status'] === 'Active') ? 'bg-success' : (($job['status'] === 'Closed') ? 'bg-danger' : 'bg-secondary');
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Job Details | JobHive</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />
    <style>
        :root {
            --bg-primary: #f8fafc;
            --bg-secondary: #ffffff;
            --bg-tertiary: #f3f4f6;
            --bg-footer: #212529;
            --text-primary: #22223b;
            --text-secondary: #495057;
            --text-muted: #6c757d;
            --text-white: #ffffff;
            --border-color: #dee2e6;
            --navbar-bg: #ffffff;
            --navbar-text: #22223b;
            --navbar-border: #dee2e6;
            --card-bg: #ffffff;
            --card-shadow: 0 6px 24px rgba(0, 0, 0, 0.06);
            --btn-primary-bg: #ffaa2b;
            --btn-primary-text: #22223b;
            --btn-primary-hover: #e6991f;
            --page-header-bg: #ffffff;
            --page-header-border: rgba(0, 0, 0, .06);
        }

        [data-theme="dark"] {
            --bg-primary: #121212;
            --bg-secondary: #1e1e1e;
            --bg-tertiary: #2d2d2d;
            --bg-footer: #1e1e1e;
            --text-primary: #ffffff;
            --text-secondary: #ffffff;
            --text-muted: #ffffff;
            --text-white: #ffffff;
            --border-color: #343a40;
            --navbar-bg: #1e1e1e;
            --navbar-text: #ffffff;
            --navbar-border: #343a40;
            --card-bg: #1e1e1e;
            --card-shadow: 0 6px 24px rgba(0, 0, 0, 0.3);
            --btn-primary-bg: #ffaa2b;
            --btn-primary-text: #22223b;
            --btn-primary-hover: #e6991f;
            --page-header-bg: #2d2d2d;
            --page-header-border: #343a40;
        }

        body {
            background-color: var(--bg-primary);
            color: var(--text-primary);
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            transition: background-color 0.3s, color 0.3s;
        }

        .page-header {
            background: var(--page-header-bg);
            border-bottom: 1px solid var(--page-header-border);
            transition: background-color 0.3s, border-color 0.3s;
        }

        .logo {
            width: 72px;
            height: 72px;
            object-fit: cover;
            border-radius: .75rem;
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            transition: background-color 0.3s, border-color 0.3s;
        }

        .card {
            background-color: var(--card-bg);
            border: 0;
            border-radius: 1rem;
            box-shadow: var(--card-shadow);
            transition: background-color 0.3s, box-shadow 0.3s;
        }

        .meta small {
            color: var(--text-muted);
            transition: color 0.3s;
        }

        .navbar {
            background-color: var(--navbar-bg) !important;
            border-bottom: 1px solid var(--navbar-border);
            transition: background-color 0.3s, border-color 0.3s;
        }

        .navbar-brand {
            font-weight: 700;
            color: var(--btn-primary-bg) !important;
            transition: color 0.3s;
        }

        .navbar-nav .nav-link {
            color: var(--navbar-text) !important;
            transition: color 0.2s ease-in-out;
        }

        .navbar-nav .nav-item .nav-link {
            position: relative;
            padding-bottom: 4px;
        }

        .navbar-nav .nav-item .nav-link::after {
            content: "";
            position: absolute;
            left: 0;
            bottom: 0;
            width: 0%;
            height: 2px;
            background-color: var(--btn-primary-bg);
            transition: width 0.25s ease-in-out;
        }

        .navbar-nav .nav-item .nav-link:hover::after,
        .navbar-nav .nav-item .nav-link.active::after {
            width: 100%;
        }

        .navbar-nav .nav-item .nav-link.active {
            font-weight: bold;
            color: var(--btn-primary-bg) !important;
        }

        .theme-toggle {
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }

        .theme-toggle:hover {
            background: var(--bg-tertiary);
        }

        .btn-warning {
            background-color: var(--btn-primary-bg);
            border: none;
            color: var(--btn-primary-text);
            font-weight: 600;
            transition: background-color 0.3s, color 0.3s;
        }

        .btn-warning:hover {
            background-color: var(--btn-primary-hover);
            color: var(--text-white);
        }

        .btn-outline-secondary {
            color: var(--text-primary);
            border-color: var(--border-color);
            background-color: transparent;
            transition: all 0.3s;
        }

        .btn-outline-secondary:hover {
            background-color: var(--bg-tertiary);
            border-color: var(--border-color);
            color: var(--text-primary);
        }

        .navbar-toggler {
            border-color: var(--text-primary);
        }

        .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%2834, 34, 59, 0.75%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
        }

        [data-theme="dark"] .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%28255, 255, 255, 0.75%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
        }

        footer {
            background-color: var(--bg-footer) !important;
            transition: background-color 0.3s;
        }

        footer a {
            color: var(--text-white);
            transition: color 0.3s;
        }

        footer a:hover {
            color: var(--btn-primary-bg);
        }

        .alert-warning {
            background-color: var(--bg-tertiary);
            border-color: var(--btn-primary-bg);
            color: var(--text-primary);
            transition: background-color 0.3s, border-color 0.3s, color 0.3s;
        }

        .badge {
            padding: 0.35em 0.65em;
            font-weight: 700;
            line-height: 1;
            border-radius: 0.25rem;
        }

        .text-bg-secondary {
            background-color: var(--text-secondary) !important;
            color: #000 !important;
        }

        .text-bg-info {
            background-color: #0dcaf0 !important;
            color: #000 !important;
        }

        .text-bg-warning {
            background-color: var(--btn-primary-bg) !important;
            color: var(--btn-primary-text) !important;
        }

        .text-bg-success {
            background-color: #198754 !important;
            color: #fff !important;
        }

        .text-bg-danger {
            background-color: #dc3545 !important;
            color: #fff !important;
        }

        hr {
            border-color: var(--border-color);
            transition: border-color 0.3s;
        }

        /* Dark Mode Text Fixes */
        [data-theme="dark"] .text-muted {
            color: #ffffff !important;
        }

        [data-theme="dark"] .fw-semibold {
            color: #ffffff !important;
        }

        [data-theme="dark"] small {
            color: #ffffff !important;
        }

        [data-theme="dark"] p {
            color: #ffffff !important;
        }

        [data-theme="dark"] h1,
        [data-theme="dark"] h2,
        [data-theme="dark"] h3,
        [data-theme="dark"] h4,
        [data-theme="dark"] h5,
        [data-theme="dark"] h6 {
            color: #ffffff !important;
        }

        [data-theme="dark"] .btn-secondary {
            background-color: #6c757d;
            border-color: #6c757d;
            color: #ffffff;
        }

        [data-theme="dark"] .btn-secondary:disabled {
            background-color: #495057;
            border-color: #495057;
            color: #adb5bd;
        }

        /* Specific fixes for job details fields in dark mode */
        [data-theme="dark"] .card .row .col-md-6 div {
            color: #ffffff !important;
        }

        [data-theme="dark"] .card .row .col-md-6 .fw-semibold {
            color: #ffffff !important;
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-light shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="user_home.php">JobHive</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-end" id="nav">
                <ul class="navbar-nav">
                    <li class="nav-item"><a class="nav-link" href="user_home.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="user_dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="companies.php">All Companies</a></li>
                    <!-- Theme Toggle Button -->
                    <li class="nav-item">
                        <button class="theme-toggle ms-3" id="themeToggle" aria-label="Toggle theme">
                            <i class="bi bi-sun-fill" id="themeIcon"></i>
                        </button>
                    </li>
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
                                <img class="logo" src="<?= e($LOGO_DIR . ($job['company_logo'] ?? '')) ?>" alt="Company Logo"
                                    onerror="this.src='https://via.placeholder.com/72'">
                                <div class="ms-3">
                                    <h2 class="h4 mb-1"><?= e($job['job_title']) ?></h2>
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <span class="text-muted"><?= e($job['company_name']) ?></span>
                                        <span class="badge <?= $jobBadge ?>"><?= e($job['status']) ?></span>
                                        <?php if ($myApp): ?>
                                            <!-- show my application status -->
                                            <span class="ms-1">
                                                <?= app_badge($myApp['status']) ?>
                                            </span>
                                            <small class="text-muted">Applied: <?= e(date('Y-m-d', strtotime($myApp['applied_at']))) ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="meta mt-1">
                                        <small>Location: <?= e($job['location']) ?></small><br>
                                        <?php if (!empty($job['posted_at'])): ?>
                                            <small>Posted: <?= e(fmt_date($job['posted_at'])) ?></small>
                                        <?php endif; ?>
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
                                    <div><span class="badge <?= $jobBadge ?>"><?= e($job['status']) ?></span></div>
                                </div>
                            </div>
                            <div class="mt-3">
                                <div class="fw-semibold mb-1">Job Description</div>
                                <p class="mb-3" style="text-align: justify;"><?= nl2br(e($job['description_detail'])) ?></p>
                                <div class="fw-semibold mb-1">Requirements</div>
                                <p class="mb-0" style="text-align: justify;"><?= nl2br(e($job['requirements'])) ?></p>
                            </div>
                            <!-- APPLY BUTTONS -->
                            <?php $can_apply = (isset($job['status']) && $job['status'] === 'Active'); ?>
                            <div class="mt-4 d-flex gap-2 align-items-center">
                                <?php if ($can_apply): ?>
                                    <?php
                                    if (!$is_logged_in) {
                                        $apply_href = "login.php?next=" . urlencode('resume_entry.php?job_id=' . (int)$job['job_id']);
                                    } else {
                                        $apply_href = ($is_premium ? 'resume_premium.php?job_id=' : 'resume.php?job_id=') . (int)$job['job_id'];
                                    }
                                    ?>
                                    <a class="btn btn-warning" href="<?= e($apply_href) ?>">
                                        I'm interested<?= ($is_logged_in && $is_premium) ? ' ⭐' : '' ?>
                                    </a>
                                <?php else: ?>
                                    <button class="btn btn-secondary" type="button" disabled aria-disabled="true" title="Applications are closed">
                                        I'm interested
                                    </button>
                                <?php endif; ?>
                                <button type="button" class="btn btn-outline-secondary" onclick="history.back()">Back</button>
                                <?php if (!$can_apply): ?>
                                    <span class="align-self-center text-muted small">Applications are closed for this job.</span>
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
                            <a class="btn btn-outline-secondary w-100" href="all_companies.php">View all companies</a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
    <footer class="mt-5 py-4 text-white">
        <div class="container d-flex flex-column align-items-center">
            <div class="mb-2">
                <a href="#" class="text-white text-decoration-none me-3">About</a>
                <a href="#" class="text-white text-decoration-none me-3">Contact</a>
                <a href="#" class="text-white text-decoration-none">Privacy Policy</a>
            </div>
            <small>&copy; <?= date('Y') ?> JobHive. All rights reserved.</small>
        </div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Theme toggle functionality
        const themeToggle = document.getElementById('themeToggle');
        const themeIcon = document.getElementById('themeIcon');
        const html = document.documentElement;
        // Check for saved theme preference or default to light
        const currentTheme = localStorage.getItem('theme') || 'light';
        html.setAttribute('data-theme', currentTheme);
        updateThemeIcon(currentTheme);
        themeToggle.addEventListener('click', () => {
            const theme = html.getAttribute('data-theme');
            const newTheme = theme === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateThemeIcon(newTheme);
        });

        function updateThemeIcon(theme) {
            if (theme === 'dark') {
                themeIcon.classList.remove('bi-sun-fill');
                themeIcon.classList.add('bi-moon-fill');
            } else {
                themeIcon.classList.remove('bi-moon-fill');
                themeIcon.classList.add('bi-sun-fill');
            }
        }
    </script>
</body>

</html>