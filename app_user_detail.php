<?php
// app_user_detail.php
require_once "connect.php";
session_start();
/* ===== Auth guard ===== */
$company_id = $_SESSION['company_id'] ?? null;
if (!$company_id) {
    header("Location: login.php");
    exit;
}
/* ===== Sanitize input ===== */
$app_id = isset($_GET['application_id']) ? (int)$_GET['application_id'] : 0;
if ($app_id <= 0) {
    die("Invalid application ID.");
}
/* ===== CSRF token (simple) ===== */
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf'];
/* ===== Handle action: confirm/reject ===== */
$flash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_csrf = $_POST['csrf'] ?? '';
    $action = $_POST['action'] ?? '';
    if (!hash_equals($_SESSION['csrf'], $posted_csrf)) {
        $flash = 'Invalid request. Please try again.';
    } else {
        $newStatus = $action === 'confirm' ? 'Accepted' : ($action === 'reject' ? 'Rejected' : null);
        if ($newStatus) {
            try {
                // Only update if this application's job belongs to the current company
                $sql = "
                    UPDATE application a
                    JOIN jobs j ON a.job_id = j.job_id
                    SET a.status = ?
                    WHERE a.application_id = ? AND j.company_id = ?
                ";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$newStatus, $app_id, $company_id]);
                if ($stmt->rowCount() > 0) {
                    // Redirect to avoid resubmits; carry a small flag
                    header("Location: app_user_detail.php?application_id={$app_id}&ok=1&status={$newStatus}");
                    exit;
                } else {
                    $flash = 'Update failed or not allowed.';
                }
            } catch (PDOException $e) {
                $flash = 'Error: ' . $e->getMessage();
            }
        }
    }
}
/* ===== Fetch application with user info ===== */
$app = null;
try {
    $sql = "
    SELECT 
      a.application_id, a.status, a.applied_at, a.resume,
      u.user_id, u.full_name, u.email, u.phone, u.address AS location, u.profile_picture,
      j.job_title, j.company_id
    FROM application a
    JOIN jobs j  ON a.job_id = j.job_id
    JOIN users u ON a.user_id = u.user_id
    WHERE a.application_id = ? AND j.company_id = ?
  ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$app_id, $company_id]);
    $app = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
if (!$app) {
    die("Application not found.");
}
/* ===== Helpers ===== */
function e($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
function name_initials($name)
{
    $name = trim(preg_replace('/\s+/', ' ', (string)$name));
    if ($name === '') return 'U';
    $parts = explode(' ', $name);
    $first = mb_substr($parts[0], 0, 1, 'UTF-8');
    $last  = count($parts) > 1 ? mb_substr(end($parts), 0, 1, 'UTF-8') : '';
    return mb_strtoupper($first . $last, 'UTF-8');
}
function badgeClass($status)
{
    switch ($status) {
        case 'Accepted':
            return 'bg-success';
        case 'Rejected':
            return 'bg-danger';
        default:
            return 'bg-secondary';
    }
}
/* --- Resolve profile picture path --- */
$pp = $app['profile_picture'] ?? '';
if ($pp && !preg_match('~^https?://~i', $pp) && !preg_match('~^profile_pics/~i', $pp)) {
    $pp = 'profile_pics/' . ltrim($pp, '/');
}
/* --- Resolve resume source (image/data/pdf) --- */
function resolve_resume_src($val)
{
    $val = trim((string)$val);
    if ($val === '') return '';
    // data URI (image)
    if (preg_match('~^data:image/[^;]+;base64,~i', $val)) return $val;
    // absolute http(s)
    if (preg_match('~^https?://~i', $val)) return $val;
    // local path — default under 'resumes/'
    if (!preg_match('~^(resumes/|uploads/|files/|assets/|profile_pics/)~i', $val)) {
        $val = 'resumes/' . ltrim($val, '/');
    }
    return $val;
}
function resume_is_image($src)
{
    if ($src === '') return false;
    if (preg_match('~^data:image/~i', $src)) return true;
    $path = parse_url($src, PHP_URL_PATH) ?: $src;
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
}
function resume_is_pdf($src)
{
    $path = parse_url($src, PHP_URL_PATH) ?: $src;
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    return $ext === 'pdf';
}
$initials = name_initials($app['full_name']);
$ok = isset($_GET['ok']) ? (int)$_GET['ok'] : 0;
$newStatusShown = $_GET['status'] ?? '';
$resume_raw = $app['resume'] ?? '';
$resume_src = resolve_resume_src($resume_raw);
$is_resume_img = resume_is_image($resume_src);
$is_resume_pdf = resume_is_pdf($resume_src);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applicant Detail | JobHive</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            /* Light mode variables */
            --bg-color: #f8fafc;
            --card-bg: #ffffff;
            --text-color: #334155;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --shadow: 0 8px 24px rgba(0, 0, 0, .06);
            --avatar-bg: #fff4d6;
            --avatar-text: #ff8a00;
            --avatar-border: #ffe5a3;
            --action-border: #e9ecef;
            --toast-bg: #111827;
            --toast-text: #ffffff;
            --resume-border: #e9ecef;
            --resume-shadow: 0 6px 18px rgba(0, 0, 0, .05);
            --btn-primary-bg: #ffaa2b;
            --btn-primary-text: #212529;
            --btn-primary-hover: #e6991f;
            --transition-speed: 0.3s;
        }

        /* Dark mode variables */
        [data-theme="dark"] {
            --bg-color: #121212;
            --card-bg: #1e1e1e;
            --text-color: #e0e0e0;
            --text-muted: #a0a0a0;
            --border-color: rgba(255, 255, 255, 0.1);
            --shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
            --avatar-bg: #2d2d2d;
            --avatar-text: #ffcc66;
            --avatar-border: #444444;
            --action-border: rgba(255, 255, 255, 0.1);
            --toast-bg: #333333;
            --toast-text: #ffffff;
            --resume-border: rgba(255, 255, 255, 0.1);
            --resume-shadow: 0 6px 18px rgba(0, 0, 0, 0.3);
            --btn-primary-bg: #ffaa2b;
            --btn-primary-text: #000000;
            --btn-primary-hover: #e6991f;
        }

        /* Global transitions */
        body {
            transition: background-color var(--transition-speed) ease, color var(--transition-speed) ease;
        }

        body {
            background: var(--bg-color);
            color: var(--text-color);
        }

        .profile-card {
            max-width: 760px;
            margin: 48px auto;
            background: var(--card-bg);
            padding: 28px;
            border-radius: 1rem;
            box-shadow: var(--shadow);
            transition: background-color var(--transition-speed) ease, color var(--transition-speed) ease, box-shadow var(--transition-speed) ease;
        }

        .avatar,
        .avatar-initials {
            width: 96px;
            height: 96px;
            border-radius: 50%;
            object-fit: cover;
        }

        .avatar-initials {
            background: var(--avatar-bg);
            color: var(--avatar-text);
            border: 1px solid var(--avatar-border);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 1.6rem;
            transition: background-color var(--transition-speed) ease, color var(--transition-speed) ease, border-color var(--transition-speed) ease;
        }

        .meta li {
            padding: 6px 0;
        }

        .meta li i {
            color: var(--text-muted);
        }

        .meta li a {
            color: var(--text-color);
            text-decoration: none;
            transition: color var(--transition-speed) ease;
        }

        .meta li a:hover {
            color: var(--btn-primary-bg);
        }

        .action-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            padding-top: 8px;
            margin-top: 16px;
            border-top: 1px dashed var(--action-border);
            transition: border-color var(--transition-speed) ease;
        }

        .btn-pill {
            border-radius: 999px;
            padding: .55rem 1.05rem;
            font-weight: 600;
        }

        .toast-lite {
            position: fixed;
            right: 20px;
            bottom: 20px;
            z-index: 1050;
            background: var(--toast-bg);
            color: var(--toast-text);
            padding: 12px 16px;
            border-radius: 10px;
            box-shadow: 0 8px 22px rgba(0, 0, 0, .2);
            transition: background-color var(--transition-speed) ease, color var(--transition-speed) ease;
        }

        /* Resume preview */
        .resume-preview {
            margin-top: 8px;
        }

        .resume-photo {
            display: block;
            max-width: 100%;
            width: 100%;
            border: 1px solid var(--resume-border);
            border-radius: 12px;
            box-shadow: var(--resume-shadow);
            transition: border-color var(--transition-speed) ease, box-shadow var(--transition-speed) ease;
        }

        .resume-embed {
            width: 100%;
            height: 560px;
            border: 1px solid var(--resume-border);
            border-radius: 12px;
            box-shadow: var(--resume-shadow);
            transition: border-color var(--transition-speed) ease, box-shadow var(--transition-speed) ease;
        }

        /* Theme toggle button */
        .theme-toggle {
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-color);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }

        .theme-toggle:hover {
            background: var(--avatar-bg);
        }

        /* Status badge adjustments for dark mode */
        [data-theme="dark"] .badge.bg-success {
            background-color: #198754 !important;
            color: white !important;
        }

        [data-theme="dark"] .badge.bg-danger {
            background-color: #dc3545 !important;
            color: white !important;
        }

        [data-theme="dark"] .badge.bg-secondary {
            background-color: #6c757d !important;
            color: white !important;
        }
    </style>
</head>

<body>
    <div class="container">
        <?php if ($ok && $newStatusShown): ?>
            <div class="toast-lite">
                Status updated to <strong><?= e($newStatusShown) ?></strong>.
            </div>
        <?php elseif (!empty($flash)): ?>
            <div class="toast-lite" style="background:#b91c1c"> <?= e($flash) ?> </div>
        <?php endif; ?>
        <div class="profile-card">
            <div class="d-flex justify-content-between align-items-start mb-4">
                <div class="d-flex align-items-center">
                    <?php if (!empty($pp)): ?>
                        <img src="<?= e($pp) ?>" class="avatar me-3" alt="profile photo">
                    <?php else: ?>
                        <div class="avatar-initials me-3"><?= e($initials) ?></div>
                    <?php endif; ?>
                    <div>
                        <h3 class="mb-1"><?= e($app['full_name']) ?></h3>
                        <div class="d-flex align-items-center gap-2">
                            <span class="text-muted"><?= e($app['job_title']) ?></span>
                            <span class="badge <?= badgeClass($app['status']) ?> ms-2 px-3 py-2" style="font-size:.85rem">
                                <?= e($app['status']) ?>
                            </span>
                        </div>
                    </div>
                </div>
                <!-- Theme Toggle Button -->
                <button class="theme-toggle" id="themeToggle" aria-label="Toggle theme">
                    <i class="bi bi-sun-fill" id="themeIcon"></i>
                </button>
            </div>
            <ul class="list-unstyled meta mb-4">
                <li><i class="bi bi-envelope me-2"></i><a href="mailto:<?= e($app['email']) ?>"><?= e($app['email']) ?></a></li>
                <?php if (!empty($app['phone'])): ?>
                    <li><i class="bi bi-telephone me-2"></i><a href="tel:<?= e($app['phone']) ?>"><?= e($app['phone']) ?></a></li>
                <?php endif; ?>
                <li><i class="bi bi-geo-alt me-2"></i><?= e($app['location'] ?: '—') ?></li>
                <li><i class="bi bi-calendar-check me-2"></i>Applied: <?= e(date('M d, Y H:i', strtotime($app['applied_at']))) ?></li>
                <?php if ($resume_src !== ''): ?>
                    <li>
                        <i class="bi bi-file-earmark-text me-2"></i>Resume:
                        <div class="resume-preview">
                            <?php if ($is_resume_img): ?>
                                <img class="resume-photo" src="<?= e($resume_src) ?>" alt="Applicant Resume">
                            <?php elseif ($is_resume_pdf): ?>
                                <iframe class="resume-embed" src="<?= e($resume_src) ?>" title="Applicant Resume (PDF)"></iframe>
                            <?php else: ?>
                                <!-- Fallback: try embedding anyway without offering a link -->
                                <iframe class="resume-embed" src="<?= e($resume_src) ?>" title="Applicant Resume"></iframe>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endif; ?>
            </ul>
            <div class="action-bar">
                <a href="company_home.php?inbox=1" class="btn btn-outline-secondary btn-pill">
                    <i class="bi bi-arrow-left me-1"></i>Back
                </a>
                <?php if ($app['status'] === 'Pending'): ?>
                    <form method="post" class="m-0">
                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="action" value="confirm">
                        <button type="submit" class="btn btn-success btn-pill">
                            <i class="bi bi-check2-circle me-1"></i>Confirm
                        </button>
                    </form>
                    <form method="post" class="m-0">
                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="action" value="reject">
                        <button type="submit" class="btn btn-danger btn-pill">
                            <i class="bi bi-x-circle me-1"></i>Reject
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

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