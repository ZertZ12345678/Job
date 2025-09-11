<?php
include("connect.php");
session_start();
/* ===== Auth guard ===== */
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header("Location: login.php");
    exit;
}
/* ===== Fetch current user with computed profile % =====
   Fields counted (8): full_name, email, phone, address, current_position, b_date, gender, education */
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
          u.b_date,
          u.gender,
          u.education,
          u.profile_picture AS photo,
          ROUND((
            IF(u.full_name         IS NULL OR u.full_name        ='',0,1) +
            IF(u.email             IS NULL OR u.email            ='',0,1) +
            IF(u.phone             IS NULL OR u.phone            ='',0,1) +
            IF(u.address           IS NULL OR u.address          ='',0,1) +
            IF(u.current_position  IS NULL OR u.current_position ='',0,1) +
            IF(u.b_date            IS NULL OR u.b_date           ='',0,1) +
            IF(u.gender            IS NULL OR u.gender           ='',0,1) +
            IF(u.education         IS NULL OR u.education        ='',0,1)
          ) / 8 * 100) AS profile_pct
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
/* ===== KPI: Accepted Job & All Applications (this user) ===== */
$accepted_jobs = 0;
$all_apps = 0;
try {
    // Accepted Job
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM application WHERE user_id = ? AND status = 'Accepted'");
    $stmt->execute([$user_id]);
    $accepted_jobs = (int)$stmt->fetchColumn();
    // All Applications
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM application WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $all_apps = (int)$stmt->fetchColumn();
} catch (PDOException $e) {
    $accepted_jobs = 0;
    $all_apps = 0;
}
/* ===== Recent Applications (latest 5 for this user) ===== */
$recent_apps = [];
try {
    $sql = "SELECT 
                a.application_id, a.job_id, a.status, a.applied_at, a.resume,
                j.job_title, j.location,
                c.company_name
            FROM application a
            JOIN jobs j      ON a.job_id = j.job_id
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
/* ===== Progress bar color ===== */
$pp = (int)($user['profile_pct'] ?? 0);
$pp = max(0, min(100, $pp)); // clamp between 0–100
$barClass = 'bg-danger';
if ($pp > 30) $barClass = 'bg-warning';
if ($pp > 60) $barClass = 'bg-info';
if ($pp > 85) $barClass = 'bg-success';
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
        :root {
            --bg-primary: #f8fafc;
            --bg-secondary: #ffffff;
            --bg-tertiary: #f3f4f6;
            --text-primary: #212529;
            --text-secondary: #6c757d;
            --text-muted: #6c757d;
            --border-color: #eaeaea;
            --sidebar-bg: #22223b;
            --sidebar-text: #ffffff;
            --sidebar-hover: rgba(255, 255, 255, .08);
            --sidebar-active: #ffc107;
            --sidebar-active-text: #22223b;
            --card-shadow: 0 4px 18px rgba(0, 0, 0, .05);
            --avatar-border: #ffffff;
            --avatar-shadow: 0 2px 6px rgba(0, 0, 0, .1);
        }

        [data-theme="dark"] {
            --bg-primary: #121212;
            --bg-secondary: #1e1e1e;
            --bg-tertiary: #2d2d2d;
            --text-primary: #e9ecef;
            --text-secondary: #adb5bd;
            --text-muted: #6c757d;
            --border-color: #343a40;
            --sidebar-bg: #1a1a1a;
            --sidebar-text: #e9ecef;
            --sidebar-hover: rgba(255, 255, 255, .1);
            --sidebar-active: #ffc107;
            --sidebar-active-text: #22223b;
            --card-shadow: 0 4px 18px rgba(0, 0, 0, .3);
            --avatar-border: #343a40;
            --avatar-shadow: 0 2px 6px rgba(0, 0, 0, .3);
        }

        body {
            background: var(--bg-primary);
            color: var(--text-primary);
            transition: background-color 0.3s, color 0.3s;
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: 260px;
            background: var(--sidebar-bg);
            color: var(--sidebar-text);
            padding: 20px 14px;
            z-index: 1020;
            display: flex;
            flex-direction: column;
            transition: background-color 0.3s;
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
            color: var(--sidebar-text);
            border-radius: .6rem;
            padding: .7rem .9rem;
            font-weight: 500;
            transition: background-color 0.3s;
        }

        .sidebar .nav-link:hover {
            background: var(--sidebar-hover);
            color: var(--sidebar-text);
        }

        .sidebar .nav-link.active {
            background: var(--sidebar-active);
            color: var(--sidebar-active-text);
        }

        .content-wrapper {
            margin-left: 260px;
            min-height: 100vh;
            transition: margin-left 0.3s;
        }

        .topbar {
            position: sticky;
            top: 0;
            z-index: 1010;
            background: var(--bg-secondary);
            border-bottom: 1px solid var(--border-color);
            transition: background-color 0.3s, border-color 0.3s;
        }

        .kpi-card {
            border: none;
            border-radius: 1rem;
            box-shadow: var(--card-shadow);
            background: var(--bg-secondary);
            transition: background-color 0.3s, box-shadow 0.3s;
        }

        .table thead th {
            background: var(--bg-tertiary);
            transition: background-color 0.3s;
        }

        .table {
            color: var(--text-primary);
        }

        .avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--avatar-border);
            box-shadow: var(--avatar-shadow);
            transition: border-color 0.3s, box-shadow 0.3s;
        }

        .text-muted {
            color: var(--text-muted) !important;
        }

        .card {
            background: var(--bg-secondary);
            border: none;
            transition: background-color 0.3s;
        }

        .card-header {
            background: var(--bg-secondary);
            border-bottom: 1px solid var(--border-color);
            transition: background-color 0.3s, border-color 0.3s;
        }

        .btn-outline-secondary {
            color: var(--text-secondary);
            border-color: var(--border-color);
            transition: color 0.3s, border-color 0.3s;
        }

        .btn-outline-secondary:hover {
            color: var(--text-primary);
            background-color: var(--bg-tertiary);
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

        /* Dark mode specific styles */
        [data-theme="dark"] .kpi-card .text-muted {
            color: white !important;
        }

        [data-theme="dark"] .kpi-card .h4 {
            color: white !important;
        }

        [data-theme="dark"] .kpi-card .small.fw-semibold {
            color: white !important;
        }

        [data-theme="dark"] #applications .table {
            background-color:  black !important;
            color: #ffffff !important;
        }

        [data-theme="dark"] #applications .table thead th {
            background-color: black !important;
            color: #ffffff !important;
            border-color: #DAA520 !important;
        }

        [data-theme="dark"] #applications .table tbody tr {
            background-color: black !important;
            color: #ffffff !important;
            border-color: #DAA520 !important;
        }

        [data-theme="dark"] #applications .table tbody tr:hover {
            background-color: #DAA520 !important;
            color: black !important;
        }

        [data-theme="dark"] #applications .table tbody td {
            color: black !important;
            border-color: #DAA520 !important;
        }

        [data-theme="dark"] #applications .table tbody tr td.text-center {
            color: black !important;
        }

        [data-theme="dark"] #applications .badge {
            color: black !important;
        }

        [data-theme="dark"] #applications .btn-outline-warning {
            color: black !important;
            border-color: black !important;
        }

        [data-theme="dark"] #applications .btn-outline-warning:hover {
            background-color: black !important;
            color: gold !important;
        }

        @media (max-width:991.98px) {
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
    <aside id="sidebar" class="sidebar">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <a class="brand" href="user_home.php">JobHive</a>
            <button class="btn btn-sm btn-light d-lg-none" id="closeSidebar"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="flex-grow-1">
            <div class="small text-white-50 mb-2 px-2">User Menu</div>
            <nav class="nav flex-column">
                <a class="nav-link active" href="#overview"><i class="bi bi-speedometer2 me-2"></i> Overview</a>
                <a class="nav-link" href="#applications"><i class="bi bi-briefcase me-2"></i> Applications</a>
                <a class="nav-link" href="user_profile.php"><i class="bi bi-person-circle me-2"></i> Profile</a>
                <a class="nav-link" href="user_home.php"><i class="bi bi-house-door me-2"></i> Home Page</a>
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
                        <h5 class="mb-0 fw-semibold">Dashboard</h5>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <button class="theme-toggle" id="themeToggle" aria-label="Toggle theme">
                            <i class="bi bi-sun-fill" id="themeIcon"></i>
                        </button>
                        <a href="user_profile.php" class="text-muted small d-none d-sm-inline text-decoration-none">
                            <?php echo htmlspecialchars($user['full_name'] ?? 'User'); ?>
                        </a>
                        <a href="user_profile.php" class="d-inline-block"><img src="<?php echo $avatar_url; ?>" class="avatar" alt="Avatar"></a>
                    </div>
                </div>
            </div>
        </div>
        <main class="container py-4">
            <!-- Overview: 3 KPIs -->
            <section id="overview" class="mb-4">
                <div class="row g-3">
                    <!-- Accepted Job -->
                    <div class="col-12 col-sm-6 col-xl-4">
                        <div class="card kpi-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <div class="text-muted small">Accepted Job</div>
                                        <div class="h4 mb-0"><?php echo $accepted_jobs; ?></div>
                                    </div>
                                    <i class="bi bi-check-circle fs-2 text-warning"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- All Applications -->
                    <div class="col-12 col-sm-6 col-xl-4">
                        <div class="card kpi-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <div class="text-muted small">All Applications</div>
                                        <div class="h4 mb-0"><?php echo $all_apps; ?></div>
                                    </div>
                                    <i class="bi bi-briefcase fs-2 text-warning"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Profile Completion -->
                    <div class="col-12 col-sm-6 col-xl-4">
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
                                                    $status = (string)($a['status'] ?? 'Pending');
                                                    $badge = 'secondary';
                                                    if ($status === 'Pending' || $status === 'Submitted') $badge = 'secondary';
                                                    elseif ($status === 'Under Review') $badge = 'info';
                                                    elseif ($status === 'Interview' || $status === 'Phone Screen') $badge = 'warning';
                                                    elseif ($status === 'Accepted' || $status === 'Offer') $badge = 'success';
                                                    elseif ($status === 'Rejected') $badge = 'danger';
                                                    ?>
                                                    <span class="badge text-bg-<?php echo $badge; ?>"><?php echo htmlspecialchars($status); ?></span>
                                                </td>
                                                <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($a['applied_at']))); ?></td>
                                                <td class="text-end">
                                                    <a class="btn btn-sm btn-outline-warning" href="job_detail.php?job_id=<?php echo (int)$a['job_id']; ?>">View</a>
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
        </main>
    </div>
    <script>
        // Mobile sidebar toggle
        const sidebar = document.getElementById('sidebar');
        document.getElementById('openSidebar')?.addEventListener('click', () => sidebar.classList.add('show'));
        document.getElementById('closeSidebar')?.addEventListener('click', () => sidebar.classList.remove('show'));

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

        // Smooth scroll for in-page links
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