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

// Resolve profile picture path
$pp = $app['profile_picture'] ?? '';
if ($pp && !preg_match('~^https?://~', $pp) && !preg_match('~^profile_pics/~', $pp)) {
    $pp = 'profile_pics/' . ltrim($pp, '/');
}
$initials = name_initials($app['full_name']);
$ok = isset($_GET['ok']) ? (int)$_GET['ok'] : 0;
$newStatusShown = $_GET['status'] ?? '';
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
        body {
            background: #f8fafc;
        }

        .profile-card {
            max-width: 760px;
            margin: 48px auto;
            background: #fff;
            padding: 28px;
            border-radius: 1rem;
            box-shadow: 0 8px 24px rgba(0, 0, 0, .06);
        }

        .avatar,
        .avatar-initials {
            width: 96px;
            height: 96px;
            border-radius: 50%;
            object-fit: cover;
        }

        .avatar-initials {
            background: #fff4d6;
            color: #ff8a00;
            border: 1px solid #ffe5a3;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 1.6rem;
        }

        .resume-link {
            font-weight: 600;
        }

        .meta li {
            padding: 6px 0;
        }

        .action-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            padding-top: 8px;
            margin-top: 16px;
            border-top: 1px dashed #e9ecef;
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
            background: #111827;
            color: #fff;
            padding: 12px 16px;
            border-radius: 10px;
            box-shadow: 0 8px 22px rgba(0, 0, 0, .2);
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
            <div class="d-flex align-items-center mb-4">
                <?php if (!empty($pp)): ?>
                    <img src="<?= e($pp) ?>" class="avatar me-3" alt="profile photo">
                <?php else: ?>
                    <div class="avatar-initials me-3"><?= e($initials) ?></div>
                <?php endif; ?>
                <div class="flex-grow-1">
                    <h3 class="mb-1"><?= e($app['full_name']) ?></h3>
                    <div class="d-flex align-items-center gap-2">
                        <span class="text-muted"><?= e($app['job_title']) ?></span>
                        <span class="badge <?= badgeClass($app['status']) ?> ms-2 px-3 py-2" style="font-size:.85rem">
                            <?= e($app['status']) ?>
                        </span>
                    </div>
                </div>
            </div>

            <ul class="list-unstyled meta mb-4">
                <li><i class="bi bi-envelope me-2"></i><a href="mailto:<?= e($app['email']) ?>"><?= e($app['email']) ?></a></li>
                <?php if (!empty($app['phone'])): ?>
                    <li><i class="bi bi-telephone me-2"></i><a href="tel:<?= e($app['phone']) ?>"><?= e($app['phone']) ?></a></li>
                <?php endif; ?>
                <li><i class="bi bi-geo-alt me-2"></i><?= e($app['location'] ?: '—') ?></li>
                <li><i class="bi bi-calendar-check me-2"></i>Applied: <?= e(date('M d, Y H:i', strtotime($app['applied_at']))) ?></li>
                <?php if (!empty($app['resume'])): ?>
                    <li><i class="bi bi-file-earmark-text me-2"></i>
                        Resume: <a class="resume-link" href="<?= e($app['resume']) ?>" target="_blank">View / Download</a>
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
</body>

</html>