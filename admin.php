<?php
include("connect.php");
session_start();

/* ===== Auth: require logged-in admin ===== */
if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit;
}
$current_admin_id = (int) $_SESSION['user_id'];

/* Load logged-in user and verify role=admin */
try {
  $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
  $stmt->execute([$current_admin_id]);
  $loggedAdmin = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  $loggedAdmin = null;
}
if (!$loggedAdmin || ($loggedAdmin['role'] ?? '') !== 'admin') {
  session_destroy();
  header("Location: login.php?err=unauthorized");
  exit;
}

/* -------------------- Handle Add Admin form (same page) -------------------- */
$admin_success = '';
$admin_error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_admin') {
  $full_name = trim($_POST['full_name'] ?? '');
  $email     = trim($_POST['email'] ?? '');
  $password  = trim($_POST['password'] ?? '');
  $phone     = trim($_POST['phone'] ?? '');
  $address   = trim($_POST['address'] ?? '');
  $profile_picture = null;

  // Required fields
  if ($full_name === '' || $email === '' || $password === '' || $phone === '' || $address === '') {
    $admin_error = "All fields are required.";
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $admin_error = "Email is not valid.";
  }

  // Photo is required
  if ($admin_error === '') {
    if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] === UPLOAD_ERR_NO_FILE) {
      $admin_error = "Photo is required for admin profile.";
    }
  }

  // Validate + save image
  if ($admin_error === '' && isset($_FILES['profile_picture'])) {
    if ($_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
      $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
      $ext  = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
      $size = $_FILES['profile_picture']['size'] ?? 0;

      if (!in_array($ext, $allowed, true)) {
        $admin_error = "Invalid image type. Allowed: " . implode(', ', $allowed);
      } elseif ($size > 3 * 1024 * 1024) {
        $admin_error = "Image too large. Max 3MB.";
      } else {
        $dir = __DIR__ . "/profile_pics";
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $rand     = bin2hex(random_bytes(4));
        $filename = "admin_" . time() . "_" . $rand . "." . $ext;
        $destFS   = $dir . "/" . $filename;

        if (!move_uploaded_file($_FILES['profile_picture']['tmp_name'], $destFS)) {
          $admin_error = "Failed to save uploaded photo.";
        } else {
          $profile_picture = $filename;
        }
      }
    } else {
      $admin_error = "Upload error (code: " . (int)$_FILES['profile_picture']['error'] . ").";
    }
  }

  // Insert admin
  if ($admin_error === '') {
    try {
      $stmtIns = $pdo->prepare("
        INSERT INTO users (full_name, email, password, phone, address, profile_picture, role)
        VALUES (?, ?, ?, ?, ?, ?, 'admin')
      ");
      $stmtIns->execute([$full_name, $email, $password, $phone, $address, $profile_picture]);
      $admin_success = "Admin added successfully.";
    } catch (PDOException $e) {
      if (stripos($e->getMessage(), 'duplicate') !== false || stripos($e->getMessage(), 'unique') !== false) {
        $admin_error = "That email is already registered.";
      } else {
        $admin_error = "Could not add admin: " . $e->getMessage();
      }
    }
  }
}

/* -------------------- Handle CURRENT ADMIN PROFILE UPDATE -------------------- */
$profile_success = '';
$profile_error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_admin_profile') {
  $p_full_name = trim($_POST['p_full_name'] ?? '');
  $p_email     = trim($_POST['p_email'] ?? '');
  $p_phone     = trim($_POST['p_phone'] ?? '');
  $p_address   = trim($_POST['p_address'] ?? '');

  if ($p_full_name === '' || $p_email === '' || $p_address === '') {
    $profile_error = "Full name, email, and address are required.";
  } elseif (!filter_var($p_email, FILTER_VALIDATE_EMAIL)) {
    $profile_error = "Email is not valid.";
  }

  // Load current row for old photo cleanup
  $adminRow = [];
  if ($profile_error === '') {
    try {
      $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id=?");
      $stmt->execute([$current_admin_id]);
      $adminRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
      $profile_error = "Could not load current profile.";
    }
  }

  // Optional photo replacement
  $new_photo = null;
  if ($profile_error === '' && isset($_FILES['p_profile_picture']) && $_FILES['p_profile_picture']['error'] !== UPLOAD_ERR_NO_FILE) {
    if ($_FILES['p_profile_picture']['error'] === UPLOAD_ERR_OK) {
      $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
      $ext  = strtolower(pathinfo($_FILES['p_profile_picture']['name'], PATHINFO_EXTENSION));
      $size = $_FILES['p_profile_picture']['size'] ?? 0;

      if (!in_array($ext, $allowed, true)) {
        $profile_error = "Invalid image type. Allowed: " . implode(', ', $allowed);
      } elseif ($size > 3 * 1024 * 1024) {
        $profile_error = "Image too large. Max 3MB.";
      } else {
        $dir = __DIR__ . "/profile_pics";
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $rand     = bin2hex(random_bytes(4));
        $filename = "admin_" . $current_admin_id . "_" . time() . "_" . $rand . "." . $ext;
        $destFS   = $dir . "/" . $filename;

        if (!move_uploaded_file($_FILES['p_profile_picture']['tmp_name'], $destFS)) {
          $profile_error = "Failed to save uploaded photo.";
        } else {
          $new_photo = $filename;
          if (!empty($adminRow['profile_picture'])) {
            $oldFS = $dir . "/" . $adminRow['profile_picture'];
            if (is_file($oldFS)) @unlink($oldFS);
          }
        }
      }
    } else {
      $profile_error = "Upload error (code: " . (int)$_FILES['p_profile_picture']['error'] . ").";
    }
  }

  // Update DB
  if ($profile_error === '') {
    try {
      $sql = "UPDATE users SET full_name=?, email=?, phone=?, address=?";
      $params = [$p_full_name, $p_email, $p_phone, $p_address];
      if ($new_photo) {
        $sql .= ", profile_picture=?";
        $params[] = $new_photo;
      }
      $sql .= " WHERE user_id=?";
      $params[] = $current_admin_id;

      $upd = $pdo->prepare($sql);
      $upd->execute($params);
      $profile_success = "Profile updated successfully.";
    } catch (PDOException $e) {
      $profile_error = "Could not update profile: " . $e->getMessage();
    }
  }
}

/* -------------------- Companies -------------------- */
try {
  $stmt = $pdo->query("SELECT * FROM companies ORDER BY company_id DESC");
  $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  $companies = [];
  $error_message = "Error loading companies data: " . $e->getMessage();
}

/* -------------------- Users (seekers + admins) -------------------- */
try {
  $stmt2 = $pdo->query("SELECT * FROM users ORDER BY user_id DESC");
  $seekers = $stmt2->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  $seekers = [];
  $seekers_error = "Error loading users data: " . $e->getMessage();
}
$user_seekers = array_filter($seekers, fn($s) => ($s['role'] ?? '') === 'user');
$admins       = array_filter($seekers, fn($s) => ($s['role'] ?? '') === 'admin');

/* -------------------- Jobs -------------------- */
try {
  $sql = "SELECT j.job_id, j.company_id, j.job_title, j.job_description, j.location,
                 j.salary, j.employment_type, j.requirements, j.posted_at, j.deadline, j.status,
                 c.company_name
          FROM jobs j
          LEFT JOIN companies c ON c.company_id = j.company_id
          ORDER BY j.job_id DESC";
  $stmt3 = $pdo->query($sql);
  $jobs = $stmt3->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  $jobs = [];
  $jobs_error = "Error loading jobs data: " . $e->getMessage();
}

/* -------------------- Applications (ALL, for Applied Jobs tab) -------------------- */
try {
  $q = "
    SELECT 
      a.application_id,
      a.applied_at,
      a.status,
      u.full_name   AS seeker_name,
      j.job_title,
      c.company_name
    FROM application a
      JOIN jobs j        ON j.job_id = a.job_id
      LEFT JOIN companies c ON c.company_id = j.company_id
      JOIN users u       ON u.user_id = a.user_id
    ORDER BY a.applied_at DESC
  ";
  $stmtA = $pdo->query($q);
  $all_apps = $stmtA->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  $all_apps = [];
  $apps_error = "Error loading applications: " . $e->getMessage();
}

/* -------------------- Load current admin for Profile display -------------------- */
$profile_admin = [];
try {
  $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id=?");
  $stmt->execute([$current_admin_id]);
  $profile_admin = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
  $profile_admin = [];
}

/* -------------------- Feedback (seekers & companies) -------------------- */
if (!function_exists('jh_words_preview')) {
  function jh_words_preview($text, $maxWords = 10, $ellipsis = '…')
  {
    $text = trim(strip_tags((string)$text));
    if ($text === '') return '';
    $parts = preg_split('/\s+/u', $text);
    if (count($parts) <= $maxWords) return $text;
    return implode(' ', array_slice($parts, 0, $maxWords)) . $ellipsis;
  }
}

$fb_seekers = [];
$fb_companies = [];

try {
  // Seekers feedback
  $qS = "
    SELECT f.id, f.user_id, u.full_name, u.email, u.package, f.message, f.submitted_at
    FROM feedback f
    LEFT JOIN users u ON u.user_id = f.user_id
    WHERE f.user_id IS NOT NULL
    ORDER BY f.submitted_at DESC, f.id DESC
  ";
  $fb_seekers = $pdo->query($qS)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  $fb_seekers = [];
}

try {
  // Companies feedback
  $qC = "
    SELECT f.id, f.company_id, c.company_name, c.email, c.member, f.message, f.submitted_at
    FROM feedback f
    LEFT JOIN companies c ON c.company_id = f.company_id
    WHERE f.company_id IS NOT NULL
    ORDER BY f.submitted_at DESC, f.id DESC
  ";
  $fb_companies = $pdo->query($qC)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  $fb_companies = [];
}

// Counts for the title bar (used by JS)
$fb_seekers_count   = isset($fb_seekers)   ? count($fb_seekers)   : 0;
$fb_companies_count = isset($fb_companies) ? count($fb_companies) : 0;

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>JobHive | Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <style>
    body {
      background: #f8fafc;
    }

    .sidebar {
      min-height: 100vh;
      background: #22223b;
      color: #fff;
      padding-top: 30px;
      width: 250px;
      position: fixed;
      left: 0;
      top: 0;
      z-index: 100;
    }

    .sidebar .nav-link {
      color: #fff;
      font-size: 1.09rem;
      padding: 0.9rem 2rem 0.9rem 2.2rem;
      border-radius: 0.8rem 0 0 0.8rem;
      transition: background .13s, color .13s;
      margin-bottom: .1rem;
      font-weight: 500;
      cursor: pointer;
      text-decoration: none;
      display: block;
    }

    .sidebar .nav-link.active,
    .sidebar .nav-link:hover {
      background: #ffc107;
      color: #22223b;
    }

    .sidebar .sidebar-title {
      font-size: 1.6rem;
      font-weight: bold;
      color: #ffc107;
      padding-left: 2.2rem;
      margin-bottom: 2.4rem;
    }

    /* inline edit button inside inputs */
    .field-control {
      position: relative;
    }

    .field-control .form-control {
      padding-right: 5.5rem;
    }

    /* leave room for the button */
    .edit-inline {
      position: absolute;
      right: 10px;
      top: 63%;
      transform: translateY(-50%);
      border: 0;
      background: transparent;
      color: #ffc107;
      font-weight: 600;
      cursor: pointer;
      padding: 0 .25rem;
    }

    .edit-inline:hover {
      color: #ffca2c;
    }

    .edit-inline:focus {
      outline: none;
    }


    .content {
      margin-left: 250px;
      padding: 40px 30px 30px;
      min-height: 100vh;
    }

    @media (max-width: 900px) {
      .sidebar {
        width: 100%;
        position: relative;
        min-height: unset;
      }

      .content {
        margin-left: 0;
      }
    }

    .section .companies-title-bar {
      background: #22223b;
      color: #ffc107;
      padding: 0.9rem 1.3rem;
      border-radius: 0.7rem;
      font-size: 1.35rem;
      font-weight: 700;
      margin-bottom: 1.4rem;
      letter-spacing: 0.03rem;
      box-shadow: 0 1px 5px rgba(30, 30, 55, .06);
      display: inline-block;
    }

    .dark-table {
      background: #22223b;
      color: #ffc107;
      border-radius: 0.7rem;
      overflow: hidden;
      width: 100%;
    }

    .dark-table th,
    .dark-table td {
      background: #22223b !important;
      color: #ffc107 !important;
      border: 1px solid #ffc107 !important;
      font-weight: 500;
      font-size: 0.95rem;
      line-height: 1.4;
      vertical-align: middle !important;
      padding: 0.6rem 0.8rem;
      text-align: left !important;
      word-break: break-word;
      white-space: normal;
    }

    .dark-table th {
      font-weight: 700;
      font-size: 1rem;
      letter-spacing: 0.04rem;
      text-transform: uppercase;
      border-bottom: 2px solid #ffc107 !important;
    }

    .logo-cell {
      text-align: center;
      min-width: 70px;
      max-width: 90px;
      vertical-align: middle !important;
    }

    .thumb {
      width: 60px;
      height: 60px;
      object-fit: cover;
      background: #fff;
      border-radius: 10px;
      display: block;
      margin: 0 auto;
    }

    .form-card {
      background: #fff;
      border-radius: 0.9rem;
      padding: 1.25rem;
      box-shadow: 0 2px 14px rgba(0, 0, 0, .05);
    }

    .form-grid {
      display: grid;
      grid-template-columns: repeat(12, 1fr);
      gap: 16px;
    }

    .form-grid .col-6 {
      grid-column: span 6;
    }

    .form-grid .full {
      grid-column: 1 / -1;
    }

    @media (max-width: 768px) {
      .form-grid .col-6 {
        grid-column: 1 / -1;
      }
    }

    .form-label {
      font-weight: 600;
      margin-bottom: .35rem;
    }

    .form-control,
    .form-select {
      height: 44px;
      border-radius: .6rem;
    }

    .profile-card {
      max-width: 740px;
      margin: 10px auto 0;
      background: #fff;
      border-radius: 1.1rem;
      box-shadow: 0 3px 16px rgba(30, 30, 60, .07);
      padding: 1.5rem;
    }

    .profile-img {
      width: 112px;
      height: 112px;
      object-fit: cover;
      border-radius: 1.2rem;
      border: 3px solid #ffc107;
      background: #fafafa;
      margin-bottom: .8rem;
    }

    .field-label {
      font-weight: 600;
      color: #6c757d;
      margin-bottom: .1rem;
      font-size: 1.02rem;
    }

    .edit-btn {
      margin-left: 8px;
      color: #ffc107;
      background: none;
      border: none;
      cursor: pointer;
      font-size: 1.05rem;
    }

    .jobs-table tr.row-inactive td {
      background: #fde7e7 !important;
      color: #842029 !important;
      border-color: #f5c2c7 !important;
    }

    .jobs-table tr.row-closed td {
      background: #e9ecef !important;
      color: #495057 !important;
      border-color: #d3d6d8 !important;
    }

    .apps-table tr.status-accepted td {
      background: #d1e7dd !important;
      color: #0f5132 !important;
    }

    .apps-table tr.status-rejected td {
      background: #f8d7da !important;
      color: #842029 !important;
    }

    .apps-table tr.status-pending td {
      background: #cff4fc !important;
      color: #055160 !important;
    }
  </style>

  <script>
    // Remember last opened section (admins or feedback) and restore on refresh
    function showSection(sectionId, linkElement) {
      document.querySelectorAll('.section').forEach(el => el.style.display = 'none');
      if (sectionId) document.getElementById(sectionId).style.display = 'block';
      document.querySelectorAll('.sidebar .nav-link').forEach(link => link.classList.remove('active'));
      if (linkElement) linkElement.classList.add('active');

      const remember = ['adminsSection', 'feedbackSection'];
      if (remember.includes(sectionId)) {
        sessionStorage.setItem('lastSection', sectionId);
      } else {
        sessionStorage.removeItem('lastSection');
      }
    }

    window.addEventListener('DOMContentLoaded', () => {
      const last = sessionStorage.getItem('lastSection');
      const linkMap = {
        adminsSection: document.getElementById('adminsLink'),
        feedbackSection: document.getElementById('feedbackLink'),
        dashboardSection: document.getElementById('dashboardLink')
      };
      if (last && document.getElementById(last)) {
        showSection(last, linkMap[last] || linkMap.dashboardSection);
      } else {
        showSection('dashboardSection', linkMap.dashboardSection);
      }
    });

    document.addEventListener('DOMContentLoaded', () => {
      const btn = document.getElementById('togglePwd');
      const input = document.getElementById('adminPwd');
      if (btn && input) {
        btn.addEventListener('click', () => {
          const isText = input.type === 'text';
          input.type = isText ? 'password' : 'text';
          btn.textContent = isText ? 'Show' : 'Hide';
        });
      }
    });

    function toggleEdit(btn) {
      const input = btn.parentNode.querySelector("input, select");
      if (!input) return;
      if (input.hasAttribute("readonly")) input.removeAttribute("readonly");
      if (input.hasAttribute("disabled")) input.removeAttribute("disabled");
      input.focus();
      input.style.backgroundColor = "#fff8ec";
    }

    function previewProfilePic(input) {
      if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => document.getElementById('profilePreview').src = e.target.result;
        reader.readAsDataURL(input.files[0]);
      }
    }
  </script>
</head>

<body>
  <!-- Sidebar -->
  <div class="sidebar d-flex flex-column">
    <div class="sidebar-title mb-4">JobHive Admin</div>
    <nav class="nav flex-column">
      <a id="dashboardLink" class="nav-link" onclick="showSection('dashboardSection', this)">Dashboard</a>
      <a id="companiesLink" class="nav-link" onclick="showSection('companiesSection', this)">Companies</a>
      <a id="seekersLink" class="nav-link" onclick="showSection('seekersSection', this)">Job Seekers</a>
      <a id="jobsLink" class="nav-link" onclick="showSection('jobsSection', this)">Jobs</a>
      <a id="appliedLink" class="nav-link" onclick="showSection('appliedSection', this)">Applied Jobs</a>
      <a id="adminsLink" class="nav-link" onclick="showSection('adminsSection', this)">Add Admin Role</a>
      <a class="nav-link" onclick="showSection('profileSection', this)">Profile</a>
      <a id="feedbackLink" class="nav-link" onclick="showSection('feedbackSection', this)">Feedback</a>
      <a class="nav-link" href="index.php">Logout</a>
    </nav>
  </div>

  <!-- Content -->
  <div class="content">
    <!-- Dashboard Section -->
    <div id="dashboardSection" class="section" style="display:none;">
      <h2>Welcome, Admin!</h2>
      <p>Select an option from the sidebar to manage the platform.</p>
      <div class="row">
        <div class="col-md-4 mb-4">
          <div class="card shadow-sm rounded-4 border-0">
            <div class="card-body text-center">
              <h5 class="card-title">Total Companies</h5>
              <div class="display-6 fw-bold text-warning"><?php echo count($companies); ?></div>
            </div>
          </div>
        </div>
        <div class="col-md-4 mb-4">
          <div class="card shadow-sm rounded-4 border-0">
            <div class="card-body text-center">
              <h5 class="card-title">Total Seekers</h5>
              <div class="display-6 fw-bold text-warning"><?php echo count($user_seekers); ?></div>
            </div>
          </div>
        </div>
        <div class="col-md-4 mb-4">
          <div class="card shadow-sm rounded-4 border-0">
            <div class="card-body text-center">
              <h5 class="card-title">Jobs Posted</h5>
              <div class="display-6 fw-bold text-warning"><?php echo count($jobs); ?></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Companies Section -->
    <div id="companiesSection" class="section" style="display:none;">
      <div class="companies-title-bar mb-4">
        <?php
        $company_count = count($companies);
        echo $company_count === 0 ? "No Company Information"
          : ($company_count === 1 ? "1 Company Information" : "{$company_count} Companies Information");
        ?>
      </div>
      <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
      <?php endif; ?>

      <?php if (empty($companies)): ?>
        <div class="alert alert-info">No companies found.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table dark-table align-middle">
            <thead>
              <tr>
                <th>Company Id</th>
                <th>Company Name</th>
                <th>Email</th>
                <th>Password</th>
                <th>Phone</th>
                <th>Address</th>
                <th>Logo</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($companies as $company): ?>
                <tr>
                  <td><?php echo htmlspecialchars($company['company_id'] ?? ''); ?></td>
                  <td><?php echo htmlspecialchars($company['company_name'] ?? ''); ?></td>
                  <td><?php echo htmlspecialchars($company['email'] ?? ''); ?></td>
                  <td><?php echo htmlspecialchars($company['password'] ?? ''); ?></td>
                  <td><?php echo htmlspecialchars($company['phone'] ?? ''); ?></td>
                  <td><?php echo htmlspecialchars($company['address'] ?? ''); ?></td>
                  <td class="logo-cell">
                    <?php if (!empty($company['logo'])): ?>
                      <img src="company_logos/<?php echo htmlspecialchars($company['logo']); ?>" alt="Logo" class="thumb" style="object-fit:contain;">
                    <?php else: ?>
                      <span style="color:#ccc;">—</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <!-- Seekers Section -->
    <div id="seekersSection" class="section" style="display:none;">
      <div class="companies-title-bar mb-4">
        <?php
        $seeker_count = count($user_seekers);
        echo $seeker_count === 0 ? "No Seekers Information"
          : ($seeker_count === 1 ? "1 Seeker Information" : "{$seeker_count} Seekers Information");
        ?>
      </div>
      <?php if (!empty($seekers_error)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($seekers_error); ?></div>
      <?php endif; ?>

      <?php if (empty($user_seekers)): ?>
        <div class="alert alert-info">No seekers found.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table dark-table align-middle">
            <thead>
              <tr>
                <th>User Id</th>
                <th>Full Name</th>
                <th>Email</th>
                <th>Password</th>
                <th>Phone</th>
                <th>Address</th>
                <th>Job Category</th>
                <th>Profile Picture</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($user_seekers as $seeker): ?>
                <tr>
                  <td><?php echo htmlspecialchars($seeker['user_id'] ?? ''); ?></td>
                  <td><?php echo htmlspecialchars($seeker['full_name'] ?? ''); ?></td>
                  <td><?php echo htmlspecialchars($seeker['email'] ?? ''); ?></td>
                  <td><?php echo htmlspecialchars($seeker['password'] ?? ''); ?></td>
                  <td><?php echo htmlspecialchars($seeker['phone'] ?? ''); ?></td>
                  <td><?php echo htmlspecialchars($seeker['address'] ?? ''); ?></td>
                  <td><?php echo htmlspecialchars($seeker['job_category'] ?? ''); ?></td>
                  <td class="logo-cell">
                    <?php if (!empty($seeker['profile_picture'])): ?>
                      <img src="profile_pics/<?php echo htmlspecialchars($seeker['profile_picture']); ?>" alt="Photo" class="thumb">
                    <?php else: ?>
                      <span style="color:#ccc;">No photo</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <!-- Jobs Section -->
    <div id="jobsSection" class="section" style="display:none;">
      <div class="companies-title-bar mb-4">
        <?php
        $job_count = count($jobs);
        echo $job_count === 0 ? "No Jobs Information"
          : ($job_count === 1 ? "1 Job Information" : "{$job_count} Jobs Information");
        ?>
      </div>

      <?php if (!empty($jobs_error)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($jobs_error); ?></div>
      <?php endif; ?>

      <?php if (empty($jobs)): ?>
        <div class="alert alert-info">No jobs found.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table dark-table jobs-table align-middle">
            <thead>
              <tr>
                <th>Job Id</th>
                <th>Company</th>
                <th>Title</th>
                <th>Type</th>
                <th>Salary</th>
                <th>Location</th>
                <th>Posted On</th>
                <th>Deadline</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($jobs as $job): ?>
                <?php
                $statusRaw = strtolower(trim((string)($job['status'] ?? '')));
                $rowClass = ($statusRaw === 'inactive') ? 'row-inactive' : (($statusRaw === 'closed') ? 'row-closed' : '');
                ?>
                <tr class="<?php echo $rowClass; ?>">
                  <td><?php echo htmlspecialchars($job['job_id'] ?? ''); ?></td>
                  <td><?php echo htmlspecialchars(!empty($job['company_name']) ? $job['company_name'] : ($job['company_id'] ?? '')); ?></td>
                  <td><?php echo htmlspecialchars($job['job_title'] ?? ''); ?></td>
                  <td><?php echo htmlspecialchars($job['employment_type'] ?? ''); ?></td>
                  <td><?php echo htmlspecialchars($job['salary'] ?? ''); ?></td>
                  <td><?php echo htmlspecialchars($job['location'] ?? ''); ?></td>
                  <td><?php $p = $job['posted_at'] ?? '';
                      echo htmlspecialchars($p ? date('M d, Y', strtotime($p)) : ''); ?></td>
                  <td><?php $d = $job['deadline'] ?? '';
                      echo htmlspecialchars($d ? date('M d, Y', strtotime($d)) : ''); ?></td>
                  <td><?php echo htmlspecialchars($job['status'] ?? ''); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <!-- Applied Jobs (ALL with status colors) -->
    <div id="appliedSection" class="section" style="display:none;">
      <div class="companies-title-bar mb-4">
        <?php
        $app_count = isset($all_apps) ? count($all_apps) : 0;
        echo $app_count === 0 ? "No Applications"
          : ($app_count === 1 ? "1 Application" : "{$app_count} Applications");
        ?>
      </div>

      <?php if (!empty($apps_error)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($apps_error); ?></div>
      <?php endif; ?>

      <?php if (empty($all_apps)): ?>
        <div class="alert alert-info">There are no applications yet.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table dark-table apps-table align-middle">
            <thead>
              <tr>
                <th>No</th>
                <th>Company Name</th>
                <th>Job Title</th>
                <th>Username</th>
                <th>Status</th>
                <th>Applied On</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($all_apps as $idx => $row): ?>
                <?php
                $status = strtolower(trim($row['status'] ?? ''));
                $rowClass = $status === 'accepted' ? 'status-accepted'
                  : ($status === 'rejected' ? 'status-rejected'
                    : ($status === 'pending' ? 'status-pending' : ''));
                ?>
                <tr class="<?php echo $rowClass; ?>">
                  <td><?php echo $idx + 1; ?></td>
                  <td><?php echo htmlspecialchars($row['company_name'] ?? ''); ?></td>
                  <td><?php echo htmlspecialchars($row['job_title'] ?? ''); ?></td>
                  <td><?php echo htmlspecialchars($row['seeker_name'] ?? ''); ?></td>
                  <td class="fw-bold"><?php echo htmlspecialchars($row['status'] ?? ''); ?></td>
                  <td>
                    <?php
                    $ap = $row['applied_at'] ?? '';
                    echo htmlspecialchars($ap ? date('M d, Y H:i', strtotime($ap)) : '');
                    ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <!-- Add Admin Role Section -->
    <div id="adminsSection" class="section" style="display:none;">
      <div class="companies-title-bar mb-4">Add Admin Role</div>

      <?php if ($admin_success): ?><div class="alert alert-success"><?php echo htmlspecialchars($admin_success); ?></div><?php endif; ?>
      <?php if ($admin_error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($admin_error); ?></div><?php endif; ?>

      <div class="form-card mb-4">
        <form method="post" id="addAdminForm" enctype="multipart/form-data" novalidate>
          <input type="hidden" name="action" value="add_admin">

          <div class="form-grid">
            <div class="col-6">
              <label class="form-label">Full Name</label>
              <input type="text" name="full_name" class="form-control" required>
            </div>

            <div class="col-6">
              <label class="form-label">Email</label>
              <input type="email" name="email" class="form-control" required>
            </div>

            <div class="col-6">
              <label class="form-label">Password</label>
              <div class="input-group">
                <input type="text" name="password" id="adminPwd" class="form-control" required>
                <button class="btn btn-outline-secondary" type="button" id="togglePwd">Show</button>
              </div>
            </div>

            <div class="col-6">
              <label class="form-label">Phone</label>
              <input type="text" name="phone" class="form-control" pattern="[0-9+\-()\s]{6,20}" title="Phone only" required>
            </div>

            <div class="col-6">
              <label class="form-label">Address</label>
              <input type="text" name="address" class="form-control" required>
            </div>

            <div class="col-6">
              <label class="form-label">Photo (JPG/PNG/GIF/WebP, max 3MB)</label>
              <input type="file" name="profile_picture" class="form-control" accept=".jpg,.jpeg,.png,.gif,.webp,image/*" required>
            </div>

            <div class="full form-actions">
              <button type="submit" class="btn btn-warning fw-bold">Add Admin Role</button>
              <button type="reset" class="btn btn-outline-secondary">Clear</button>
            </div>
          </div>
        </form>
      </div>

      <div class="companies-title-bar mb-3">Admins</div>
      <?php if (empty($admins)): ?>
        <div class="alert alert-info">No admins yet.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table dark-table align-middle">
            <thead>
              <tr>
                <th>User Id</th>
                <th>Photo</th>
                <th>Full Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Address</th>
                <th>Role</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($admins as $a): ?>
                <tr>
                  <td><?php echo htmlspecialchars($a['user_id'] ?? ''); ?></td>
                  <td class="logo-cell">
                    <?php if (!empty($a['profile_picture'])): ?>
                      <img src="profile_pics/<?php echo htmlspecialchars($a['profile_picture']); ?>" alt="Admin" class="thumb">
                    <?php else: ?>
                      <span style="color:#ccc;">No photo</span>
                    <?php endif; ?>
                  </td>
                  <td><?php echo htmlspecialchars($a['full_name'] ?? ''); ?></td>
                  <td><?php echo htmlspecialchars($a['email'] ?? ''); ?></td>
                  <td><?php echo htmlspecialchars($a['phone'] ?? ''); ?></td>
                  <td><?php echo htmlspecialchars($a['address'] ?? ''); ?></td>
                  <td><?php echo htmlspecialchars($a['role'] ?? ''); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <!-- Profile (for CURRENT ADMIN only) -->
    <div id="profileSection" class="section" style="display:none;">
      <div class="companies-title-bar mb-4">Profile</div>

      <?php if ($profile_success): ?><div class="alert alert-success"><?php echo htmlspecialchars($profile_success); ?></div><?php endif; ?>
      <?php if ($profile_error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($profile_error); ?></div><?php endif; ?>

      <div class="profile-card">
        <form method="post" enctype="multipart/form-data">
          <input type="hidden" name="action" value="update_admin_profile">

          <div class="text-center mb-3">
            <img src="<?php echo !empty($profile_admin['profile_picture']) ? 'profile_pics/' . htmlspecialchars($profile_admin['profile_picture']) : 'default_user.png'; ?>"
              class="profile-img" id="profilePreview" alt="Profile">
            <div style="max-width:320px;margin:6px auto 0;">
              <input type="file" name="p_profile_picture" accept="image/*" class="form-control" onchange="previewProfilePic(this)">
            </div>
          </div>

          <div class="form-edit-row">
            <div style="flex:1" class="field-control">
              <div class="field-label">Full Name</div>
              <input type="text" name="p_full_name" class="form-control"
                value="<?php echo htmlspecialchars($profile_admin['full_name'] ?? ''); ?>"
                <?php echo !empty($profile_admin['full_name']) ? 'readonly' : ''; ?> required>
              <?php if (!empty($profile_admin['full_name'])): ?>
                <button type="button" class="edit-inline" onclick="toggleEdit(this)">✎ Edit</button>
              <?php endif; ?>
            </div>
          </div>

          <div class="form-edit-row">
            <div style="flex:1" class="field-control">
              <div class="field-label">Email</div>
              <input type="email" name="p_email" class="form-control"
                value="<?php echo htmlspecialchars($profile_admin['email'] ?? ''); ?>"
                <?php echo !empty($profile_admin['email']) ? 'readonly' : ''; ?> required>
              <?php if (!empty($profile_admin['email'])): ?>
                <button type="button" class="edit-inline" onclick="toggleEdit(this)">✎ Edit</button>
              <?php endif; ?>
            </div>
          </div>

          <div class="form-edit-row">
            <div style="flex:1" class="field-control">
              <div class="field-label">Phone</div>
              <input type="text" name="p_phone" class="form-control"
                value="<?php echo htmlspecialchars($profile_admin['phone'] ?? ''); ?>"
                <?php echo !empty($profile_admin['phone']) ? 'readonly' : ''; ?>>
              <?php if (!empty($profile_admin['phone'])): ?>
                <button type="button" class="edit-inline" onclick="toggleEdit(this)">✎ Edit</button>
              <?php endif; ?>
            </div>
          </div>


          <div class="form-edit-row">
            <div style="flex:1" class="field-control">
              <div class="field-label">Address</div>
              <input type="text" name="p_address" class="form-control"
                value="<?php echo htmlspecialchars($profile_admin['address'] ?? ''); ?>"
                <?php echo !empty($profile_admin['address']) ? 'readonly' : ''; ?> required>
              <?php if (!empty($profile_admin['address'])): ?>
                <button type="button" class="edit-inline" onclick="toggleEdit(this)">✎ Edit</button>
              <?php endif; ?>
            </div>
          </div>


          <div class="mt-3 text-center">
            <button type="submit" class="btn btn-warning px-4">Save Changes</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Feedback Section -->
    <div id="feedbackSection" class="section" style="display:none;">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <div id="fbCountTitle"
          class="companies-title-bar mb-0"
          data-seekers-count="<?= (int)$fb_seekers_count ?>"
          data-companies-count="<?= (int)$fb_companies_count ?>">
          <?= (int)$fb_seekers_count ?> Feedback Messages
        </div>
        <div class="btn-group">
          <button type="button" class="btn btn-warning fw-semibold js-fb-tab" data-tab="seekers">Seekers</button>
          <button type="button" class="btn btn-outline-warning fw-semibold js-fb-tab" data-tab="companies">Companies</button>
        </div>
      </div>

      <!-- SEEKERS TABLE -->
      <div id="fbSeekersWrap">
        <?php if (empty($fb_seekers)): ?>
          <div class="alert alert-info">No seekers feedback yet.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table dark-table align-middle">
              <thead>
                <tr>
                  <th style="width:70px;">ID</th>
                  <th style="width:120px;">USER ID</th>
                  <th>NAME</th>
                  <th>EMAIL</th>
                  <th style="width:140px;">PACKAGE</th>
                  <th>MESSAGE (PREVIEW)</th>
                  <th style="width:120px;">SUBMITTED</th>
                  <th style="width:90px;">ACTION</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($fb_seekers as $r): ?>
                  <?php $ts = $r['submitted_at'] ?? ''; ?>
                  <tr>
                    <td><?= (int)$r['id'] ?></td>
                    <td><?= $r['user_id'] !== null ? ('#' . (int)$r['user_id']) : '—' ?></td>
                    <td><?= htmlspecialchars($r['full_name'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($r['email'] ?? '—') ?></td>
                    <td class="fw-semibold text-uppercase"><?= htmlspecialchars($r['package'] ?? 'normal') ?></td>
                    <td><?= htmlspecialchars(jh_words_preview($r['message'] ?? '', 10)) ?></td>
                    <td><?= htmlspecialchars($ts ? date('M d, Y H:i', strtotime($ts)) : '') ?></td>
                    <td>
                      <button
                        class="btn btn-warning btn-sm js-fb-view"
                        data-fb-type="seeker"
                        data-id="<?= (int)$r['user_id'] ?>"
                        data-name="<?= htmlspecialchars($r['full_name'] ?? '—', ENT_QUOTES) ?>"
                        data-email="<?= htmlspecialchars($r['email'] ?? '—', ENT_QUOTES) ?>"
                        data-tier="<?= htmlspecialchars($r['package'] ?? 'normal', ENT_QUOTES) ?>"
                        data-when="<?= htmlspecialchars($ts ? date('M d, Y H:i', strtotime($ts)) : '—', ENT_QUOTES) ?>"
                        data-msg="<?= htmlspecialchars($r['message'] ?? '', ENT_QUOTES) ?>">View</button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

      <!-- COMPANIES TABLE -->
      <div id="fbCompaniesWrap" class="d-none">
        <?php if (empty($fb_companies)): ?>
          <div class="alert alert-info">No companies feedback yet.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table dark-table align-middle">
              <thead>
                <tr>
                  <th style="width:70px;">ID</th>
                  <th style="width:140px;">COMPANY ID</th>
                  <th>NAME</th>
                  <th>EMAIL</th>
                  <th style="width:160px;">MEMBER</th>
                  <th>MESSAGE (PREVIEW)</th>
                  <th style="width:120px;">SUBMITTED</th>
                  <th style="width:90px;">ACTION</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($fb_companies as $r): ?>
                  <?php $ts = $r['submitted_at'] ?? ''; ?>
                  <tr>
                    <td><?= (int)$r['id'] ?></td>
                    <td><?= $r['company_id'] !== null ? ('#' . (int)$r['company_id']) : '—' ?></td>
                    <td><?= htmlspecialchars($r['company_name'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($r['email'] ?? '—') ?></td>
                    <td class="fw-semibold text-capitalize"><?= htmlspecialchars($r['member'] ?? 'normal') ?></td>
                    <td><?= htmlspecialchars(jh_words_preview($r['message'] ?? '', 10)) ?></td>
                    <td><?= htmlspecialchars($ts ? date('M d, Y H:i', strtotime($ts)) : '') ?></td>
                    <td>
                      <button
                        class="btn btn-warning btn-sm js-fb-view"
                        data-fb-type="company"
                        data-id="<?= (int)$r['company_id'] ?>"
                        data-name="<?= htmlspecialchars($r['company_name'] ?? '—', ENT_QUOTES) ?>"
                        data-email="<?= htmlspecialchars($r['email'] ?? '—', ENT_QUOTES) ?>"
                        data-tier="<?= htmlspecialchars($r['member'] ?? 'normal', ENT_QUOTES) ?>"
                        data-when="<?= htmlspecialchars($ts ? date('M d, Y H:i', strtotime($ts)) : '—', ENT_QUOTES) ?>"
                        data-msg="<?= htmlspecialchars($r['message'] ?? '', ENT_QUOTES) ?>">View</button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- View Feedback Modal -->
    <div class="modal fade" id="fbViewModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header" style="background:#22223b;color:#ffc107;">
            <h5 class="modal-title"><span id="fbModalTitle">Feedback</span></h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-md-4">
                <div class="small text-muted">ID</div>
                <div id="fbMetaId" class="fw-semibold">—</div>
              </div>
              <div class="col-md-4">
                <div class="small text-muted">Name</div>
                <div id="fbMetaName" class="fw-semibold">—</div>
              </div>
              <div class="col-md-4">
                <div class="small text-muted">Email</div>
                <div id="fbMetaEmail" class="fw-semibold">—</div>
              </div>
              <div class="col-md-4">
                <div class="small text-muted" id="fbTierLabel">Package</div>
                <div id="fbMetaTier" class="fw-semibold text-uppercase">—</div>
              </div>
              <div class="col-md-4">
                <div class="small text-muted">Submitted</div>
                <div id="fbMetaWhen" class="fw-semibold">—</div>
              </div>
            </div>
            <hr>
            <div class="small text-muted mb-1">Message</div>
            <div id="fbMessage" style="white-space:pre-wrap;"></div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
          </div>
        </div>
      </div>
    </div>

    <script>
      // Feedback tab switcher (no navigation)
      (function() {
        const seekersBtn = document.querySelector('.js-fb-tab[data-tab="seekers"]');
        const companiesBtn = document.querySelector('.js-fb-tab[data-tab="companies"]');
        const seekersWrap = document.getElementById('fbSeekersWrap');
        const companiesWrap = document.getElementById('fbCompaniesWrap');
        const countEl = document.getElementById('fbCountTitle');

        function updateCount(tab) {
          if (!countEl) return;
          const s = parseInt(countEl.dataset.seekersCount || '0', 10);
          const c = parseInt(countEl.dataset.companiesCount || '0', 10);
          countEl.textContent = (tab === 'companies' ? c : s) + ' Feedback Messages';
        }

        function setTab(tab) {
          if (tab === 'companies') {
            companiesWrap?.classList.remove('d-none');
            seekersWrap?.classList.add('d-none');
            companiesBtn?.classList.remove('btn-outline-warning');
            companiesBtn?.classList.add('btn-warning');
            seekersBtn?.classList.remove('btn-warning');
            seekersBtn?.classList.add('btn-outline-warning');
          } else {
            seekersWrap?.classList.remove('d-none');
            companiesWrap?.classList.add('d-none');
            seekersBtn?.classList.remove('btn-outline-warning');
            seekersBtn?.classList.add('btn-warning');
            companiesBtn?.classList.remove('btn-warning');
            companiesBtn?.classList.add('btn-outline-warning');
            tab = 'seekers';
          }
          sessionStorage.setItem('fb_tab', tab);
          updateCount(tab);
        }

        seekersBtn?.addEventListener('click', () => setTab('seekers'));
        companiesBtn?.addEventListener('click', () => setTab('companies'));

        // When clicking the sidebar "Feedback", default to seekers
        document.getElementById('feedbackLink')?.addEventListener('click', () => {
          setTimeout(() => setTab(sessionStorage.getItem('fb_tab') || 'seekers'), 0);
        });

        // If feedback is already visible (restored by lastSection), apply saved tab
        window.addEventListener('DOMContentLoaded', () => {
          const fbSec = document.getElementById('feedbackSection');
          if (fbSec && fbSec.style.display !== 'none') {
            setTab(sessionStorage.getItem('fb_tab') || 'seekers');
          }
        });
      })();

      // Feedback view modal
      document.addEventListener('click', function(e) {
        const btn = e.target.closest('.js-fb-view');
        if (!btn) return;

        const type = btn.getAttribute('data-fb-type') || 'seeker';
        const id = btn.getAttribute('data-id') || '—';
        const name = btn.getAttribute('data-name') || '—';
        const email = btn.getAttribute('data-email') || '—';
        const tier = btn.getAttribute('data-tier') || '—';
        const when = btn.getAttribute('data-when') || '—';
        const msg = btn.getAttribute('data-msg') || '';

        document.getElementById('fbModalTitle').textContent = type === 'company' ? 'Company Feedback' : 'Seeker Feedback';
        document.getElementById('fbTierLabel').textContent = type === 'company' ? 'Member' : 'Package';

        document.getElementById('fbMetaId').textContent = (type === 'company' ? 'Company #' : 'User #') + id;
        document.getElementById('fbMetaName').textContent = name;
        document.getElementById('fbMetaEmail').textContent = email;
        document.getElementById('fbMetaTier').textContent = tier;
        document.getElementById('fbMetaWhen').textContent = when;
        document.getElementById('fbMessage').textContent = msg;

        const modalEl = document.getElementById('fbViewModal');
        const m = new bootstrap.Modal(modalEl);
        m.show();
      });
    </script>

  </div>
</body>

</html>