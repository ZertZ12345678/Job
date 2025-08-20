<?php
// resume.php
require_once "connect.php"; // provides $pdo (PDO)
session_start();

// OPTIONAL: require login for applicants
// if (!isset($_SESSION['user_id'])) {
//     header("Location: login.php?next=" . urlencode($_SERVER['REQUEST_URI']));
//     exit;
// }

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

$job_id = isset($_GET['job_id']) ? (int)$_GET['job_id'] : 0;
if ($job_id <= 0) {
    http_response_code(400);
    $error = "Invalid job id.";
}

$job = null;
if (empty($error)) {
    try {
        $sql = "
          SELECT
            j.job_id, j.job_title, j.employment_type, j.salary, j.location,
            j.deadline, j.status, c.company_name
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
        $error = "Failed to load job.";
    }
}

// Demo “submit” handler (replace with your real application insertion)
$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    // Example: save to applications table here...
    // $user_id = $_SESSION['user_id'];
    // $cover   = trim($_POST['cover'] ?? '');
    // $stmt = $pdo->prepare("INSERT INTO applications (user_id, job_id, cover_letter, applied_at) VALUES (?,?,?,NOW())");
    // $stmt->execute([$user_id, $job_id, $cover]);

    $notice = "Your interest has been recorded (demo). Implement DB insert here.";
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
            <a class="navbar-brand fw-bold text-warning" href="home.php">JobHive</a>
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
                                    <span class="badge <?= $job['status'] === 'Active' ? 'bg-success' : ($job['status'] === 'Closed' ? 'bg-danger' : 'bg-secondary') ?>">
                                        <?= e($job['status']) ?>
                                    </span>
                                </div>
                            </div>

                            <form method="post">
                                <input type="hidden" name="job_id" value="<?= (int)$job_id ?>">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Short note / cover (optional)</label>
                                    <textarea class="form-control" name="cover" rows="4" placeholder="Write a short note to the employer..."></textarea>
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
                            <h3 class="h6 fw-bold mb-3">Next steps</h3>
                            <ol class="mb-0">
                                <li>Review the job information.</li>
                                <li>Write your resume and all your information.</li>
                                <li>Click on the "Apply Now" button.</li>
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