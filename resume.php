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
                            $msg   = "You applied to “{$job['job_title']}” at {$job['company_name']}.";
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        body {
            background: #f8fafc;
        }

        .card {
            border: 0;
            border-radius: 1rem;
            box-shadow: 0 6px 24px rgba(0, 0, 0, .06);
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
                                <li>Click “Apply Now”.</li>
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
            <small>&copy; 2025 JobHive. All rights reserved.</small>
        </div>
    </footer>
</body>

</html>