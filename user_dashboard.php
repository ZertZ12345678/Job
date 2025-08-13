<?php
// user_dashboard.php
include("connect.php");
session_start();

// ===== Auth guard =====
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header("Location: login.php");
    exit;
}

/*
  Profile completion logic (5 fields):
  full_name, email, phone, address, current_position
  You can change the set by editing the IF() lines and the divisor (/ 5).
*/

// ===== Fetch current user with computed profile % =====
try {
    $stmt = $pdo->prepare("
    SELECT 
      u.user_id,
      u.full_name,
      u.email,
      u.phone,
      u.address,
      u.job_category,
      u.current_position,
      u.profile_picture AS photo,
      ROUND((
        IF(u.full_name IS NULL OR u.full_name='',0,1) +
        IF(u.email IS NULL OR u.email='',0,1) +
        IF(u.phone IS NULL OR u.phone='',0,1) +
        IF(u.address IS NULL OR u.address='',0,1) +
        IF(u.current_position IS NULL OR u.current_position='',0,1)
      ) / 5 * 100) AS profile_pct
    FROM users u
    WHERE u.user_id = ?
    LIMIT 1
  ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    $user = [];
}

$avatar_url = !empty($user['photo'])
    ? "profile_pics/" . htmlspecialchars($user['photo'])
    : "https://ui-avatars.com/api/?name=" . urlencode($user['full_name'] ?? "U") . "&background=FFC107&color=22223b";

// ===== Aggregate counts =====
$counts = [
    'applications' => 0,
    'saved'        => 0,
    'interviews'   => 0
];

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $counts['applications'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM saved_jobs WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $counts['saved'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE user_id = ? AND status IN ('Interview','Phone Screen')");
    $stmt->execute([$user_id]);
    $counts['interviews'] = (int)$stmt->fetchColumn();
} catch (PDOException $e) {
    // keep defaults
}

// ===== Recent Applications (last 5) =====
$recent_apps = [];
try {
    $sql = "SELECT a.application_id, a.job_id, a.status, a.applied_at,
                 j.job_title, j.location, c.company_name
          FROM applications a
          JOIN jobs j ON a.job_id = j.job_id
          JOIN companies c ON j.company_id = c.company_id
          WHERE a.user_id = ?
          ORDER BY a.applied_at DESC
          LIMIT 5";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    $recent_apps = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    $recent_apps = [];
}

// ===== Saved Jobs (last 5) =====
$saved_jobs = [];
try {
    $sql = "SELECT s.id as saved_id, j.job_id, j.job_title, j.location, c.company_name, s.saved_at
          FROM saved_jobs s
          JOIN jobs j ON s.job_id = j.job_id
          JOIN companies c ON j.company_id = c.company_id
          WHERE s.user_id = ?
          ORDER BY s.saved_at DESC
          LIMIT 5";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    $saved_jobs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    $saved_jobs = [];
}

// ===== Determine progress bar color =====
$pp = (int)($user['profile_pct'] ?? 0);
$barClass = 'bg-danger';        // 0–30% red
if ($pp > 30) $barClass = 'bg-warning';   // 31–60% yellow
if ($pp > 60) $barClass = 'bg-info';      // 61–85% blue
if ($pp > 85) $barClass = 'bg-success';   // 86–100% green
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>JobHive | Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        body {
            background: #f8fafc;
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
        }

        .brand {
            font-weight: 700;
            color: #ffc107;
            letter-spacing: .3px;
            text-decoration: none;
            display: inline-block;
            margin-bottom: 10px;
        }



        .sidebar .nav-link {
            color: #fff;
            border-radius: .6rem;
            padding: .7rem .9rem;
            font-weight: 500;
        }

        .sidebar .nav-link:hover {
            background: rgba(255, 255, 255, .08);
            color: #fff;
        }

        .sidebar .nav-link.active {
            background: #ffc107;
            color: #22223b;
        }

        .sidebar {
            display: flex;
            flex-direction: column;
        }

        .sidebar .nav-link {
            color: #fff;
            border-radius: .6rem;
            padding: .7rem .9rem;
            font-weight: 500;
        }

        .sidebar .nav-link:hover {
            background: rgba(255, 255, 255, .08);
            color: #fff;
        }

        .sidebar .nav-link.active {
            background: #ffc107;
            color: #22223b;
        }



        .content-wrapper {
            margin-left: 260px;
            min-height: 100vh;
        }

        .topbar {
            position: sticky;
            top: 0;
            z-index: 1010;
            background: #fff;
            border-bottom: 1px solid #eaeaea;
        }

        .kpi-card {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 4px 18px rgba(0, 0, 0, .05);
        }

        .table thead th {
            background: #f3f4f6;
        }

        .avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #fff;
            box-shadow: 0 2px 6px rgba(0, 0, 0, .1);
        }

        @media (max-width: 991.98px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform .2s ease;
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .content-wrapper {
                margin-left: 0;
            }
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <aside id="sidebar" class="sidebar d-flex flex-column">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <a class="brand" href="home.php">JobHive</a>
            <button class="btn btn-sm btn-light d-lg-none" id="closeSidebar"><i class="bi bi-x-lg"></i></button>
        </div>

        <!-- TOP: main menu grows -->
        <div class="flex-grow-1">
            <div class="small text-white-50 mb-2 px-2">User Menu</div>
            <nav class="nav flex-column">
                <a class="nav-link active" href="#overview"><i class="bi bi-speedometer2 me-2"></i> Overview</a>
                <a class="nav-link" href="#applications"><i class="bi bi-briefcase me-2"></i> Applications</a>
                <a class="nav-link" href="#saved"><i class="bi bi-bookmark-heart me-2"></i> Saved Jobs</a>
                <a class="nav-link" href="#profile"><i class="bi bi-person-circle me-2"></i> Profile</a>
                <a class="nav-link" href="#settings"><i class="bi bi-gear me-2"></i> Settings</a>
                <a class="nav-link" href="user_home.php"><i class="bi bi-house-door me-2"></i> Home Page</a>
            </nav>
        </div>

        <!-- BOTTOM: divider + Logout (always just below menu) -->
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
                        <button class="btn btn-outline-secondary d-lg-none" id="openSidebar">
                            <i class="bi bi-list"></i>
                        </button>
                        <h5 class="mb-0 fw-semibold">Dashboard</h5>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <a href="user_profile.php" class="text-muted small d-none d-sm-inline text-decoration-none">
                            <?php echo htmlspecialchars($user['full_name'] ?? 'User'); ?>
                        </a>
                        <a href="user_profile.php" class="d-inline-block"><img src="<?php echo $avatar_url; ?>" class="avatar" alt="Avatar"></a>
                    </div>
                </div>
            </div>
        </div>

        <main class="container py-4">
            <!-- Overview -->
            <section id="overview" class="mb-4">
                <div class="row g-3">
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="card kpi-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <div class="text-muted small">Applications</div>
                                        <div class="h4 mb-0"><?php echo $counts['applications']; ?></div>
                                    </div>
                                    <i class="bi bi-briefcase fs-2 text-warning"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="card kpi-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <div class="text-muted small">Saved Jobs</div>
                                        <div class="h4 mb-0"><?php echo $counts['saved']; ?></div>
                                    </div>
                                    <i class="bi bi-bookmark-heart fs-2 text-warning"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="card kpi-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <div class="text-muted small">Interviews</div>
                                        <div class="h4 mb-0"><?php echo $counts['interviews']; ?></div>
                                    </div>
                                    <i class="bi bi-people fs-2 text-warning"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="card kpi-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="w-100">
                                        <div class="d-flex justify-content-between">
                                            <div class="text-muted small">Profile Completion</div>
                                            <div class="small fw-semibold"><?php echo $pp; ?>%</div>
                                        </div>
                                        <div class="progress mt-2" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo $pp; ?>">
                                            <div class="progress-bar <?php echo $barClass; ?>" style="width: <?php echo $pp; ?>%; transition: width .4s ease;"></div>
                                        </div>
                                    </div>
                                    <i class="bi bi-person-badge fs-2 text-warning ms-2"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Recent Applications -->
            <section id="applications" class="mb-4">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-white py-3">
                        <div class="d-flex align-items-center justify-content-between">
                            <h6 class="mb-0">Recent Applications</h6>
                            <a href="applications.php" class="btn btn-sm btn-outline-secondary">View all</a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Job</th>
                                        <th>Company</th>
                                        <th>Location</th>
                                        <th>Status</th>
                                        <th>Applied At</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recent_apps)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">No applications yet.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($recent_apps as $a): ?>
                                            <tr>
                                                <td class="fw-semibold"><?php echo htmlspecialchars($a['job_title']); ?></td>
                                                <td><?php echo htmlspecialchars($a['company_name']); ?></td>
                                                <td><?php echo htmlspecialchars($a['location']); ?></td>
                                                <td>
                                                    <?php
                                                    $status = $a['status'] ?? 'Submitted';
                                                    $badge = 'secondary';
                                                    if ($status === 'Submitted') $badge = 'secondary';
                                                    elseif ($status === 'Under Review') $badge = 'info';
                                                    elseif ($status === 'Interview' || $status === 'Phone Screen') $badge = 'warning';
                                                    elseif ($status === 'Offer') $badge = 'success';
                                                    elseif ($status === 'Rejected') $badge = 'danger';
                                                    ?>
                                                    <span class="badge text-bg-<?php echo $badge; ?>"><?php echo htmlspecialchars($status); ?></span>
                                                </td>
                                                <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($a['applied_at']))); ?></td>
                                                <td class="text-end">
                                                    <a class="btn btn-sm btn-outline-warning" href="job_detail.php?job_id=<?php echo (int)$a['job_id']; ?>">
                                                        View
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Saved Jobs -->
            <section id="saved" class="mb-4">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-white py-3">
                        <div class="d-flex align-items-center justify-content-between">
                            <h6 class="mb-0">Saved Jobs</h6>
                            <a href="saved_jobs.php" class="btn btn-sm btn-outline-secondary">Manage</a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Job</th>
                                        <th>Company</th>
                                        <th>Location</th>
                                        <th>Saved At</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($saved_jobs)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-4">No saved jobs yet.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($saved_jobs as $s): ?>
                                            <tr>
                                                <td class="fw-semibold"><?php echo htmlspecialchars($s['job_title']); ?></td>
                                                <td><?php echo htmlspecialchars($s['company_name']); ?></td>
                                                <td><?php echo htmlspecialchars($s['location']); ?></td>
                                                <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($s['saved_at']))); ?></td>
                                                <td class="text-end">
                                                    <a class="btn btn-sm btn-outline-warning" href="job_detail.php?job_id=<?php echo (int)$s['job_id']; ?>">View</a>
                                                    <a class="btn btn-sm btn-outline-danger ms-1" href="unsave_job.php?id=<?php echo (int)$s['saved_id']; ?>" onclick="return confirm('Remove from saved?');">Unsave</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Profile quick card -->
            <section id="profile" class="mb-4">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3">
                            <a href="user_profile.php" class="d-inline-block">
                                <img src="<?php echo $avatar_url; ?>" class="avatar" alt="Avatar" style="width:60px;height:60px;">
                            </a>
                            <div>
                                <div class="h6 mb-0">
                                    <a href="user_profile.php" class="text-decoration-none">
                                        <?php echo htmlspecialchars($user['full_name'] ?? 'Your Name'); ?>
                                    </a>
                                </div>
                                <div class="text-muted small"><?php echo htmlspecialchars($user['email'] ?? ''); ?></div>
                                <div class="text-muted small">
                                    <?php echo htmlspecialchars($user['current_position'] ?? ''); ?><?php
                                                                                                    echo (!empty($user['current_position']) && !empty($user['address'])) ? ' • ' : '';
                                                                                                    echo htmlspecialchars($user['address'] ?? '');
                                                                                                    ?>
                                </div>
                            </div>
                            <div class="ms-auto">
                                <a href="user_profile.php" class="btn btn-warning">Edit Profile</a>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Settings -->
            <section id="settings" class="mb-5">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body">
                        <h6 class="mb-3">Quick Settings</h6>
                        <form action="update_settings.php" method="post" class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Job Alert Email</label>
                                <select class="form-select" name="alert_frequency">
                                    <option value="none">Off</option>
                                    <option value="daily" selected>Daily</option>
                                    <option value="weekly">Weekly</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Make Profile Public</label>
                                <select class="form-select" name="profile_public">
                                    <option value="0">No</option>
                                    <option value="1">Yes</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <button class="btn btn-warning" type="submit">Save Settings</button>
                            </div>
                        </form>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <script>
        // Mobile sidebar toggle
        const sidebar = document.getElementById('sidebar');
        document.getElementById('openSidebar')?.addEventListener('click', () => sidebar.classList.add('show'));
        document.getElementById('closeSidebar')?.addEventListener('click', () => sidebar.classList.remove('show'));

        // Smooth scroll for in-page sidebar links
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