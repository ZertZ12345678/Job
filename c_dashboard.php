<?php

include("connect.php");
session_start();

/* ===== Auth guard ===== */
$company_id = $_SESSION['company_id'] ?? null;
if (!$company_id) {
    header("Location: login.php");
    exit;
}

/* ===== Helpers ===== */
function h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function buildImageUrl(string $dir, ?string $filename, ?string $fallbackName, string $fallbackInitial): string
{
    $dir = rtrim($dir, '/');
    $file = trim((string)$filename);
    if ($file !== '') {
        $file = basename($file);
        $webPath = "{$dir}/{$file}";
        $fsPath  = __DIR__ . "/{$webPath}";
        if (is_file($fsPath)) return htmlspecialchars($webPath, ENT_QUOTES, 'UTF-8');
    }
    $name = trim((string)$fallbackName);
    if ($name === '') $name = $fallbackInitial;
    return "https://ui-avatars.com/api/?name=" . urlencode($name) . "&background=FFC107&color=22223b";
}

function job_badge($status)
{
    $s = strtolower(trim((string)$status));
    $map = [
        'active'   => 'bg-success',
        'open'     => 'bg-success',
        'paused'   => 'bg-warning',
        'inactive' => 'bg-secondary',
        'closed'   => 'bg-secondary'
    ];
    $cls = $map[$s] ?? 'bg-secondary';
    return '<span class="badge ' . $cls . '">' . h(ucfirst($s)) . '</span>';
}

/* ===== Load company + profile % ===== */
try {
    $stmt = $pdo->prepare("
        SELECT c.company_id, c.company_name, c.email, c.phone, c.address, c.logo,
               ROUND((
                 IF(c.company_name IS NULL OR c.company_name='',0,1) +
                 IF(c.email        IS NULL OR c.email='',0,1) +
                 IF(c.phone        IS NULL OR c.phone='',0,1) +
                 IF(c.address      IS NULL OR c.address='',0,1) +
                 IF(c.logo         IS NULL OR c.logo='',0,1)
               ) / 5 * 100) AS profile_pct
        FROM companies c
        WHERE c.company_id = ?
        LIMIT 1
    ");
    $stmt->execute([$company_id]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    $company = [];
}

$logo_url = buildImageUrl('company_logos', $company['logo'] ?? '', $company['company_name'] ?? '', 'C');

/* ===== KPIs ===== */
$counts = ['active_jobs' => 0, 'total_applicants' => 0, 'employees' => 0];
try {
    // Active jobs (any case of 'active'/'open')
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM jobs WHERE company_id=? AND LOWER(status) IN ('active','open')");
    $stmt->execute([$company_id]);
    $counts['active_jobs'] = (int)$stmt->fetchColumn();

    // Total applicants to this company's jobs
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM application a
        JOIN jobs j ON j.job_id = a.job_id
        WHERE j.company_id = ?
    ");
    $stmt->execute([$company_id]);
    $counts['total_applicants'] = (int)$stmt->fetchColumn();

    // Employees = accepted applications for this company's jobs
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM application a
        JOIN jobs j ON j.job_id = a.job_id
        WHERE j.company_id = ?
          AND UPPER(a.status) = 'ACCEPTED'
    ");
    $stmt->execute([$company_id]);
    $counts['employees'] = (int)$stmt->fetchColumn();
} catch (PDOException $e) {
}

/* ===== Recent Jobs (any status) ===== */
$recent_jobs = [];
try {
    $stmt = $pdo->prepare("
        SELECT j.job_id, j.job_title, j.employment_type, j.location, j.deadline, j.status,
               (SELECT COUNT(*) FROM application a WHERE a.job_id=j.job_id) AS applicants
        FROM jobs j
        WHERE j.company_id=?
        ORDER BY (j.posted_at IS NULL), j.posted_at DESC, (j.deadline IS NULL), j.deadline DESC, j.job_id DESC
        LIMIT 5
    ");
    $stmt->execute([$company_id]);
    $recent_jobs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
}

/* ===== Recent Applicants ===== */
$recent_apps = [];
try {
    $stmt = $pdo->prepare("
        SELECT a.application_id, a.status AS app_status, a.applied_at AS created_at,
               u.user_id, u.full_name, u.email, u.phone,
               j.job_id, j.job_title
        FROM application a
        JOIN jobs j  ON j.job_id = a.job_id
        JOIN users u ON u.user_id = a.user_id
        WHERE j.company_id = ?
        ORDER BY a.applied_at DESC
        LIMIT 5
    ");
    $stmt->execute([$company_id]);
    $recent_apps = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
}

$pp = (int)($company['profile_pct'] ?? 0);
$barClass = $pp > 85 ? 'bg-success' : ($pp > 60 ? 'bg-info' : ($pp > 30 ? 'bg-warning' : 'bg-danger'));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>JobHive | Company Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        body {
            background: #f8fafc
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: 260px;
            background: #22223b;
            color: #fff;
            padding: 20px 14px;
            z-index: 1020;
            display: flex;
            flex-direction: column
        }

        .brand {
            font-weight: 700;
            color: #ffc107;
            letter-spacing: .3px;
            text-decoration: none;
            display: inline-block;
            margin-bottom: 10px
        }

        .sidebar .nav-link {
            color: #fff;
            border-radius: .6rem;
            padding: .7rem .9rem;
            font-weight: 500
        }

        .sidebar .nav-link:hover {
            background: rgba(255, 255, 255, .08);
            color: #fff
        }

        .sidebar .nav-link.active {
            background: #ffc107;
            color: #22223b
        }

        .content-wrapper {
            margin-left: 260px;
            min-height: 100vh
        }

        .topbar {
            position: sticky;
            top: 0;
            z-index: 1010;
            background: #fff;
            border-bottom: 1px solid #eaeaea
        }

        .kpi-card {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 4px 18px rgba(0, 0, 0, .05)
        }

        .table thead th {
            background: #f3f4f6
        }

        .avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #fff;
            box-shadow: 0 2px 6px rgba(0, 0, 0, .1)
        }

        @media (max-width:991.98px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform .2s ease
            }

            .sidebar.show {
                transform: translateX(0)
            }

            .content-wrapper {
                margin-left: 0
            }
        }
    </style>
</head>

<body>

    <!-- Sidebar -->
    <aside id="sidebar" class="sidebar">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <a class="brand" href="company_home.php">JobHive</a>
            <button class="btn btn-sm btn-light d-lg-none" id="closeSidebar"><i class="bi bi-x-lg"></i></button>
        </div>

        <div class="mb-3 d-flex align-items-center gap-2 px-2">
            <img src="<?= $logo_url ?>" class="avatar" alt="Logo">
            <div>
                <div class="fw-semibold"><?= h($company['company_name'] ?? 'Company') ?></div>
                <small class="text-white-50"><?= h($company['email'] ?? '') ?></small>
            </div>
        </div>

        <div class="flex-grow-1">
            <div class="small text-white-50 mb-2 px-2">Company Menu</div>
            <nav class="nav flex-column">
                <a class="nav-link active" href="#overview"><i class="bi bi-speedometer2 me-2"></i> Overview</a>
                <a class="nav-link" href="#applicants"><i class="bi bi-people me-2"></i> Applicants</a>
                <a class="nav-link" href="company_profile.php"><i class="bi bi-building me-2"></i> Company Profile</a>
                <a class="nav-link" href="post_job.php"><i class="bi bi-plus-square me-2"></i> Post Job</a>
                <a class="nav-link" href="company_home.php"><i class="bi bi-house-door me-2"></i> Home Page</a>
            </nav>
        </div>

        <div class="mt-3">
            <hr class="border-secondary my-2" />
            <a class="nav-link text-danger" href="index.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a>
        </div>
    </aside>

    <!-- Main content -->
    <div class="content-wrapper">
        <!-- Topbar -->
        <div class="topbar py-2">
            <div class="container-fluid">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <button class="btn btn-outline-secondary d-lg-none" id="openSidebar"><i class="bi bi-list"></i></button>
                        <h5 class="mb-0 fw-semibold">Company Dashboard</h5>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <a href="company_profile.php" class="text-muted small d-none d-sm-inline text-decoration-none">
                            <?= h($company['company_name'] ?? 'Company'); ?>
                        </a>
                        <a href="company_profile.php" class="d-inline-block"><img src="<?= $logo_url; ?>" class="avatar" alt="Logo"></a>
                    </div>
                </div>
            </div>
        </div>

        <main class="container py-4">
            <!-- Overview with KPIs + Recent Jobs table -->
            <section id="overview" class="mb-4">
                <div class="row g-3">
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="card kpi-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <div class="text-muted small">Active Jobs</div>
                                        <div class="h4 mb-0"><?= $counts['active_jobs'] ?></div>
                                    </div>
                                    <i class="bi bi-megaphone fs-2 text-warning"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="card kpi-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <div class="text-muted small">Total Applicants</div>
                                        <div class="h4 mb-0"><?= $counts['total_applicants'] ?></div>
                                    </div>
                                    <i class="bi bi-people fs-2 text-warning"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- NEW KPI: Employees (Accepted) -->
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="card kpi-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <div class="text-muted small">Employees</div>
                                        <div class="h4 mb-0"><?= $counts['employees'] ?></div>
                                    </div>
                                    <i class="bi bi-person-check fs-2 text-warning"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="card kpi-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center w-100">
                                    <div class="w-100">
                                        <div class="d-flex justify-content-between">
                                            <div class="text-muted small">Profile Completion</div>
                                            <div class="small fw-semibold"><?= (int)($company['profile_pct'] ?? 0); ?>%</div>
                                        </div>
                                        <div class="progress mt-2" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= (int)($company['profile_pct'] ?? 0); ?>">
                                            <div class="progress-bar <?= $barClass; ?>" style="width: <?= (int)($company['profile_pct'] ?? 0); ?>%; transition: width .4s;"></div>
                                        </div>
                                    </div>
                                    <i class="bi bi-building fs-2 text-warning ms-2"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Jobs (no action buttons) -->
                <div class="card border-0 shadow-sm rounded-4 mt-3">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">Recent Jobs</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Type</th>
                                        <th>Location</th>
                                        <th>Applicants</th>
                                        <th>Deadline</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recent_jobs)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">No jobs yet.</td>
                                        </tr>
                                        <?php else: foreach ($recent_jobs as $j): ?>
                                            <tr>
                                                <td class="fw-semibold"><?= h($j['job_title']) ?></td>
                                                <td><?= h($j['employment_type']) ?></td>
                                                <td><?= h($j['location']) ?></td>
                                                <td><?= (int)$j['applicants'] ?></td>
                                                <td><?= h($j['deadline']) ?></td>
                                                <td><?= job_badge($j['status']) ?></td>
                                            </tr>
                                    <?php endforeach;
                                    endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Recent Applicants (no buttons) -->
            <section id="applicants" class="mb-4">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">Recent Applicants</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Candidate</th>
                                        <th>Job</th>
                                        <th>Status</th>
                                        <th>Applied</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recent_apps)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-4">No applications yet.</td>
                                        </tr>
                                        <?php else: foreach ($recent_apps as $a): ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-semibold"><?= h($a['full_name']) ?></div>
                                                    <div class="small text-muted">
                                                        <?= h($a['email']) ?><?= !empty($a['phone']) ? ' • ' . h($a['phone']) : '' ?>
                                                    </div>
                                                </td>
                                                <td><?= h($a['job_title']) ?></td>
                                                <td>
                                                    <?php
                                                    $status = $a['app_status'] ?? 'Submitted';
                                                    $badge = 'secondary';
                                                    if ($status === 'Submitted') $badge = 'secondary';
                                                    elseif ($status === 'Under Review') $badge = 'info';
                                                    elseif ($status === 'Interview' || $status === 'Phone Screen') $badge = 'warning';
                                                    elseif ($status === 'Offer') $badge = 'success';
                                                    elseif ($status === 'Rejected') $badge = 'danger';
                                                    elseif ($status === 'Shortlisted') $badge = 'primary';
                                                    ?>
                                                    <span class="badge text-bg-<?= $badge ?>"><?= h($status) ?></span>
                                                </td>
                                                <td><?= h(date('Y-m-d', strtotime($a['created_at']))) ?></td>
                                            </tr>
                                    <?php endforeach;
                                    endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <script>
        const sidebar = document.getElementById('sidebar');
        document.getElementById('openSidebar')?.addEventListener('click', () => sidebar.classList.add('show'));
        document.getElementById('closeSidebar')?.addEventListener('click', () => sidebar.classList.remove('show'));
        document.querySelectorAll('.sidebar .nav-link[href^="#"]').forEach(a => {
            a.addEventListener('click', (e) => {
                e.preventDefault();
                const id = a.getAttribute('href').substring(1);
                const el = document.getElementById(id);
                if (el) {
                    window.scrollTo({
                        top: el.getBoundingClientRect().top + window.scrollY - 70,
                        behavior: 'smooth'
                    });
                    document.querySelectorAll('.sidebar .nav-link').forEach(n => n.classList.remove('active'));
                    a.classList.add('active');
                    sidebar.classList.remove('show');
                }
            });
        });
    </script>
</body>

</html>