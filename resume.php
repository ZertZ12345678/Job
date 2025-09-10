<?php
require_once "connect.php"; // provides $pdo (PDO)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
/* ===== Require login ===== */
if (!isset($_SESSION['user_id'])) {
    $next = 'resume.php' . (isset($_GET['job_id']) ? ('?job_id=' . (int)$_GET['job_id']) : '');
    header("Location: login.php?next=" . urlencode($next));
    exit;
}
$user_id = (int)$_SESSION['user_id'];
/* ===== Helpers ===== */
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
function push_session_notification($type, $title, $message, $link = null)
{
    if (!isset($_SESSION['notifications']) || !is_array($_SESSION['notifications'])) {
        $_SESSION['notifications'] = [];
    }
    $_SESSION['notifications'][] = [
        'id'         => uniqid('n_', true),
        'type'       => $type,
        'title'      => $title,
        'message'    => $message,
        'link'       => $link,
        'created_at' => date('Y-m-d H:i:s'),
        'read'       => 0
    ];
}
/* ===== Resolve job_id (GET/POST) ===== */
$job_id = isset($_GET['job_id']) ? (int)$_GET['job_id']
    : (isset($_POST['job_id']) ? (int)$_POST['job_id'] : 0);
if ($job_id <= 0) {
    header("Location: user_home.php?msg=" . urlencode("Please choose a job first."));
    exit;
}
/* ===== Fetch job ===== */
$job = null;
$error = '';
try {
    $stmt = $pdo->prepare("
      SELECT j.job_id, j.job_title, j.employment_type, j.salary, j.location,
             j.deadline, j.status, c.company_name
      FROM jobs j
      JOIN companies c ON c.company_id = j.company_id
      WHERE j.job_id = ?
      LIMIT 1
    ");
    $stmt->execute([$job_id]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$job) {
        http_response_code(404);
        $error = "Job not found.";
    }
} catch (PDOException $e) {
    http_response_code(500);
    $error = "Failed to load job.";
}
/* ===== Apply (ALWAYS a NEW RECORD) ===== */
$notice = '';
$form_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    // Upload settings
    $upload_dir_fs  = __DIR__ . "/uploads/applications"; // filesystem path
    $upload_dir_web = "uploads/applications";             // web path for links
    if (!is_dir($upload_dir_fs)) {
        @mkdir($upload_dir_fs, 0775, true);
    }
    if (!isset($_FILES['attachment']) || $_FILES['attachment']['error'] === UPLOAD_ERR_NO_FILE) {
        $form_error = "Please select a photo or PDF file.";
    } else {
        $f = $_FILES['attachment'];
        if ($f['error'] !== UPLOAD_ERR_OK) {
            $form_error = "Upload failed (error code {$f['error']}).";
        } else {
            $max_bytes = 5 * 1024 * 1024; // 5MB
            if (($f['size'] ?? 0) > $max_bytes) {
                $form_error = "File too large. Max 5MB.";
            } else {
                $orig = $f['name'] ?? 'file';
                $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
                $allowed_ext  = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];
                $allowed_mime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime  = $finfo->file($f['tmp_name']);
                if (!in_array($ext, $allowed_ext, true) || !in_array($mime, $allowed_mime, true)) {
                    $form_error = "Invalid type. Allowed: JPG, PNG, GIF, WEBP, or PDF.";
                } else {
                    $filename = "app_u{$user_id}_j{$job_id}_" . time() . "." . $ext;
                    $dest_fs  = $upload_dir_fs . "/" . $filename;
                    $dest_web = $upload_dir_web . "/" . $filename;
                    if (!move_uploaded_file($f['tmp_name'], $dest_fs)) {
                        $form_error = "Could not save the uploaded file.";
                    } else {
                        try {
                            $pdo->beginTransaction();
                            // ALWAYS INSERT a new record (no SELECT/UPDATE)
                            $ins = $pdo->prepare("
                                INSERT INTO application (user_id, job_id, resume, applied_at, status)
                                VALUES (?,?,?,?,?)
                            ");
                            $ins->execute([
                                $user_id,
                                $job_id,
                                $dest_web,
                                date('Y-m-d H:i:s'),
                                'Pending'
                            ]);
                            // Notification
                            $title = "Application Submitted";
                            $msg   = "You applied to \"{$job['job_title']}\" at {$job['company_name']}.";
                            $link  = "job_detail.php?id=" . (int)$job_id;
                            push_session_notification('success', $title, $msg, $link);
                            $pdo->commit();
                            $notice = "Application submitted. File: {$dest_web}";
                            // Optional redirect back to home if you want the envelope to show instantly:
                            // header("Location: user_home.php?applied=1");
                            // exit;
                        } catch (PDOException $e) {
                            if ($pdo->inTransaction()) $pdo->rollBack();
                            $form_error = "Database error: " . e($e->getMessage());
                        }
                    }
                }
            }
        }
    }
}
/* ===== Badge class ===== */
$badgeClass = "bg-secondary";
if ($job && isset($job['status'])) {
    $badgeClass = ($job['status'] === 'Active') ? 'bg-success'
        : (($job['status'] === 'Closed') ? 'bg-danger' : 'bg-secondary');
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Apply | JobHive</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
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
            --bg-primary: #000000;
            /* Black background for body */
            --bg-secondary: #0d0d0d;
            /* Very dark gray for cards */
            --bg-tertiary: #1a1a1a;
            /* Slightly lighter for tertiary elements */
            --bg-footer: #000000;
            /* Black footer */
            --text-primary: #ffffff;
            /* All text white in dark mode */
            --text-secondary: #ffffff;
            /* All text white in dark mode */
            --text-muted: #ffffff;
            /* All text white in dark mode */
            --text-white: #ffffff;
            --border-color: #333333;
            --navbar-bg: #0d0d0d;
            --navbar-text: #ffffff;
            --navbar-border: #333333;
            --card-bg: #0d0d0d;
            --card-shadow: 0 6px 24px rgba(0, 0, 0, 0.5);
            --btn-primary-bg: #ffaa2b;
            --btn-primary-text: #000000;
            --btn-primary-hover: #e6991f;
            --page-header-bg: #000000;
            /* Black background for page header */
            --page-header-border: #333333;
        }

        body {
            background-color: var(--bg-primary);
            color: var(--text-primary);
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            transition: background-color 0.3s, color 0.3s;
        }

        .card {
            background-color: var(--card-bg);
            border: 0;
            border-radius: 1rem;
            box-shadow: var(--card-shadow);
            transition: background-color 0.3s, box-shadow 0.3s;
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

        .alert-danger {
            background-color: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }

        [data-theme="dark"] .alert-danger {
            background-color: #721c24;
            border-color: #a71e2a;
            color: #ffffff;
        }

        .alert-success {
            background-color: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }

        [data-theme="dark"] .alert-success {
            background-color: #155724;
            border-color: #0f5132;
            color: #ffffff;
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

        .text-bg-success {
            background-color: #198754 !important;
            color: #fff !important;
        }

        .text-bg-danger {
            background-color: #dc3545 !important;
            color: #fff !important;
        }

        .form-control {
            background-color: var(--bg-secondary);
            color: var(--text-primary);
            border-color: var(--border-color);
        }

        .form-control:focus {
            background-color: var(--bg-secondary);
            color: var(--text-primary);
            border-color: var(--btn-primary-bg);
        }

        .form-label {
            color: var(--text-primary);
        }

        .form-text {
            color: var(--text-muted);
        }

        /* Ensure all text is white in dark mode */
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

        [data-theme="dark"] ol {
            color: #ffffff !important;
        }

        [data-theme="dark"] ol li {
            color: #ffffff !important;
        }

        [data-theme="dark"] .form-text {
            color: #e9ecef !important;
        }

        .border-bottom {
            border-bottom: 1px solid var(--page-header-border) !important;
        }

        /* Apply job section specific styling for dark mode */
        [data-theme="dark"] main {
            background-color: #000000 !important;
        }

        [data-theme="dark"] .container {
            background-color: transparent;
        }

        [data-theme="dark"] .card {
            background-color: #0d0d0d !important;
            border: 1px solid #333333 !important;
        }

        [data-theme="dark"] .form-control {
            background-color: #1a1a1a !important;
            border-color: #333333 !important;
            color: #ffffff !important;
        }

        [data-theme="dark"] .form-control:focus {
            background-color: #1a1a1a !important;
            border-color: #ffaa2b !important;
            color: #ffffff !important;
        }

        [data-theme="dark"] .btn-outline-secondary {
            background-color: transparent !important;
            border-color: #333333 !important;
            color: #ffffff !important;
        }

        [data-theme="dark"] .btn-outline-secondary:hover {
            background-color: #1a1a1a !important;
            border-color: #333333 !important;
            color: #ffffff !important;
        }

        /* Apply to Job header styling */
        [data-theme="dark"] header.py-4 {
            background-color: #000000 !important;
            border-bottom: 1px solid #333333 !important;
        }

        [data-theme="dark"] header.py-4 h1 {
            color: #ffffff !important;
        }

        /* Job details styling */
        [data-theme="dark"] .card .row>div>div {
            color: #ffffff !important;
        }

        [data-theme="dark"] .card .row>div>div>div {
            color: #ffffff !important;
        }

        [data-theme="dark"] .card .row>div>div>span {
            color: #ffffff !important;
        }

        [data-theme="dark"] .card h2 {
            color: #ffffff !important;
        }

        [data-theme="dark"] .card .text-muted {
            color: #e9ecef !important;
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
    <header class="py-4 bg-white border-bottom">
        <div class="container">
            <h1 class="h4 fw-bold mb-0">Apply to Job</h1>
        </div>
    </header>
    <main class="py-4">
        <div class="container">
            <?php if (!empty($error)): ?>
                <div class="alert alert-warning"><?= e($error) ?></div>
            <?php else: ?>
                <?php if (!empty($form_error)): ?>
                    <div class="alert alert-danger"><?= e($form_error) ?></div>
                <?php endif; ?>
                <?php if (!empty($notice)): ?>
                    <div class="alert alert-success"><?= e($notice) ?></div>
                <?php endif; ?>
                <div class="row g-4">
                    <div class="col-12 col-lg-7">
                        <div class="card p-4">
                            <h2 class="h5 fw-bold mb-2"><?= e($job['job_title']) ?></h2>
                            <div class="text-muted mb-3">
                                <?= e($job['company_name']) ?> • <?= e($job['location']) ?>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6 mb-2">
                                    <div class="fw-semibold">Employment Type</div>
                                    <div><?= e($job['employment_type']) ?></div>
                                </div>
                                <div class="col-md-6 mb-2">
                                    <div class="fw-semibold">Salary</div>
                                    <div><?= e($job['salary']) ?> MMK</div>
                                </div>
                                <div class="col-md-6 mb-2">
                                    <div class="fw-semibold">Deadline</div>
                                    <div><?= e(fmt_date($job['deadline'])) ?></div>
                                </div>
                                <div class="col-md-6 mb-2">
                                    <div class="fw-semibold">Status</div>
                                    <span class="badge <?= $badgeClass ?>"><?= e($job['status']) ?></span>
                                </div>
                            </div>
                            <!-- FILE UPLOAD ONLY -->
                            <form method="post" enctype="multipart/form-data">
                                <input type="hidden" name="job_id" value="<?= (int)$job_id ?>">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Attach a resume file (photo or PDF)</label>
                                    <input class="form-control" type="file" name="attachment" accept="image/*,application/pdf" required>
                                    <div class="form-text">Allowed: JPG, PNG, GIF, WEBP, or PDF (max 5MB).</div>
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-warning">Apply Now</button>
                                    <a href="job_detail.php?id=<?= (int)$job_id ?>" class="btn btn-outline-secondary">Back to Job</a>
                                </div>
                            </form>
                        </div>
                    </div>
                    <div class="col-12 col-lg-5">
                        <div class="card p-4">
                            <h3 class="h6 fw-bold mb-3">Resume Requirements</h3>
                            <ol class="mb-0">
                                <li>Upload your resume (PDF) or a clear image of it.</li>
                                <li>Click "Apply Now".</li>
                                <li>Wait for a reply from the company.</li>
                            </ol>
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