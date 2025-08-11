<?php
include("connect.php");
session_start();

/* -------------------- Handle Add Admin form (same page) -------------------- */
$admin_success = '';
$admin_error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_admin') {
  $full_name = trim($_POST['full_name'] ?? '');
  $email     = trim($_POST['email'] ?? '');
  $password  = trim($_POST['password'] ?? '');
  $phone     = trim($_POST['phone'] ?? '');
  $address   = trim($_POST['address'] ?? '');

  if ($full_name === '' || $email === '' || $password === '' || $phone === '' || $address === '') {
    $admin_error = "All fields are required.";
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $admin_error = "Email is not valid.";
  } else {
    try {
      // Insert into users with role='admin' (plaintext as requested)
      $stmtIns = $pdo->prepare("
        INSERT INTO users (full_name, email, password, phone, address, role)
        VALUES (?, ?, ?, ?, ?, 'admin')
      ");
      $stmtIns->execute([$full_name, $email, $password, $phone, $address]);
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
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <style>
    body { background: #f8fafc; }

    .sidebar {
      min-height: 100vh; background: #22223b; color: #fff; padding-top: 30px;
      width: 250px; position: fixed; left: 0; top: 0; z-index: 100;
    }
    .sidebar .nav-link {
      color: #fff; font-size: 1.09rem; padding: 0.9rem 2rem 0.9rem 2.2rem;
      border-radius: 0.8rem 0 0 0.8rem; transition: background 0.13s, color 0.13s;
      margin-bottom: 0.1rem; font-weight: 500; cursor: pointer; text-decoration: none; display: block;
    }
    .sidebar .nav-link.active, .sidebar .nav-link:hover { background: #ffc107; color: #22223b; }
    .sidebar .sidebar-title { font-size: 1.6rem; font-weight: bold; color: #ffc107; padding-left: 2.2rem; margin-bottom: 2.4rem; }

    .content { margin-left: 250px; padding: 40px 30px 30px; min-height: 100vh; }
    @media (max-width: 900px) {
      .sidebar { width: 100%; position: relative; min-height: unset; }
      .content { margin-left: 0; }
    }

    .companies-title-bar {
      background: #22223b; color: #ffc107; padding: 0.85rem 1.2rem; border-radius: 0.7rem;
      font-size: 1.35rem; font-weight: 700; margin-bottom: 1.4rem; letter-spacing: 0.2px;
      box-shadow: 0 1px 5px rgba(30,30,55,0.06); display: inline-block;
    }

    .dark-table { background: #22223b; color: #ffc107; border-radius: 0.7rem; overflow: hidden; }
    .dark-table th, .dark-table td {
      background: #22223b !important; color: #ffc107 !important; border: 1px solid #ffc107 !important;
      font-weight: 500; font-size: 0.9rem; vertical-align: middle !important; padding: 0.5rem 0.8rem;
    }
    .dark-table th { font-weight: 700; font-size: 0.95rem; letter-spacing: 0.03rem; border-bottom: 2px solid #ffc107 !important; text-align: left; }
    .logo-cell { text-align: center; min-width: 70px; max-width: 90px; vertical-align: middle !important; }
    .company-logo-img { height: 52px; width: 52px; object-fit: contain; background: #fff; box-shadow: 0 0 0 1px #f1f1f1; display: block; margin: 0 auto; }
    .dark-table tbody tr:hover td { background: #292944 !important; color: #ffd966 !important; transition: 0.15s; }

    .small-hint { font-size: .86rem; color: #6c757d; }

    /* --- Admin form polish (equal widths, responsive) --- */
    .form-card {
      background: #fff;
      border-radius: 0.9rem;
      padding: 1.25rem;
      box-shadow: 0 2px 14px rgba(0,0,0,.05);
    }
    .form-grid {
      display: grid;
      grid-template-columns: repeat(12, 1fr);
      gap: 16px;
    }
    .form-grid .col-6 { grid-column: span 6; }
    .form-grid .full  { grid-column: 1 / -1; }

    @media (max-width: 768px) {
      .form-grid .col-6 { grid-column: 1 / -1; }
    }

    .form-label { font-weight: 600; margin-bottom: .35rem; }
    .form-control, .form-select { height: 44px; border-radius: .6rem; }
    .input-note { font-size: .85rem; color: #6c757d; }
    .form-actions { display: flex; gap: 10px; align-items: center; }
  </style>

  <script>
    function showSection(sectionId, linkElement) {
      document.querySelectorAll('.section').forEach(el => el.style.display = 'none');
      if (sectionId) document.getElementById(sectionId).style.display = 'block';
      document.querySelectorAll('.sidebar .nav-link').forEach(link => link.classList.remove('active'));
      if (linkElement) linkElement.classList.add('active');

      if (sectionId === 'adminsSection') {
        sessionStorage.setItem('lastSection', 'adminsSection');
      } else {
        sessionStorage.removeItem('lastSection');
      }
    }

    window.addEventListener('DOMContentLoaded', () => {
      const last = sessionStorage.getItem('lastSection');
      if (last) {
        showSection('adminsSection', document.getElementById('adminsLink'));
      } else {
        showSection('dashboardSection', document.getElementById('dashboardLink'));
      }
    });

    // Toggle password visibility (hooked after DOM is ready)
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
  </script>
</head>

<body>
  <!-- Sidebar -->
  <div class="sidebar d-flex flex-column">
    <div class="sidebar-title mb-4">JobHive Admin</div>
    <nav class="nav flex-column">
      <a id="dashboardLink" class="nav-link" onclick="showSection('dashboardSection', this)">Dashboard</a>
      <a id="companiesLink" class="nav-link" onclick="showSection('companiesSection', this)">Companies</a>
      <a id="seekersLink" class="nav-link" onclick="showSection('seekersSection', this)">Seekers</a>
      <a id="jobsLink" class="nav-link" onclick="showSection('jobsSection', this)">Jobs</a>
      <a id="appliedLink" class="nav-link" onclick="showSection('appliedSection', this)">Applied Jobs</a>
      <a id="adminsLink" class="nav-link" onclick="showSection('adminsSection', this)">Add Admin Role</a>
      <a class="nav-link" onclick="showSection('feedbacksSection', this)">Feedbacks</a>
      <a class="nav-link" onclick="showSection('profileSection', this)">Profile</a>
      <a class="nav-link" onclick="showSection('settingsSection', this)">Settings</a>
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
          <table class="table dark-table align-middle" style="font-weight:bold; text-align:center;">
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
                      <img src="company_logos/<?php echo htmlspecialchars($company['logo']); ?>" alt="Logo" class="company-logo-img" style="width:60px;height:60px;object-fit:contain;background:#fff;border-radius:10px;">
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
          <table class="table dark-table align-middle" style="font-weight:bold; text-align:center;">
            <thead>
              <tr>
                <th>User Id</th>
                <th>Full Name</th>
                <th>Email</th>
                <th>Password</th>
                <th>Phone</th>
                <th>Address</th>
                <th>Job Category</th>
                <th>Current Position</th>
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
                  <td><?php echo htmlspecialchars($seeker['current_position'] ?? ''); ?></td>
                  <td class="logo-cell">
                    <?php if (!empty($seeker['profile_picture'])): ?>
                      <img src="profile_pics/<?php echo htmlspecialchars($seeker['profile_picture']); ?>" alt="Photo" class="company-logo-img" style="width:60px;height:60px;object-fit:contain;background:#fff;border-radius:10px;">
                    <?php else: ?>
                      <span style="color:#ccc;">No photo available.</span>
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
          <table class="table dark-table align-middle" style="font-weight:bold; text-align:center;">
            <thead>
              <tr>
                <th>Job Id</th>
                <th>Company</th>
                <th>Title</th>
                <th>Type</th>
                <th>Salary</th>
                <th>Location</th>
                <th>Description</th>
                <th>Requirements</th>
                <th>Posted On</th>
                <th>Deadline</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($jobs as $job): ?>
                <tr>
                  <td><?php echo htmlspecialchars($job['job_id'] ?? ''); ?></td>
                  <td><?php echo htmlspecialchars(!empty($job['company_name']) ? $job['company_name'] : ($job['company_id'] ?? '')); ?></td>
                  <td><?php echo htmlspecialchars($job['job_title'] ?? ''); ?></td>
                  <td><?php echo htmlspecialchars($job['employment_type'] ?? ''); ?></td>
                  <td><?php echo htmlspecialchars($job['salary'] ?? ''); ?></td>
                  <td><?php echo htmlspecialchars($job['location'] ?? ''); ?></td>
                  <td title="<?php echo htmlspecialchars((string)($job['job_description'] ?? '')); ?>">
                    <?php
                      $full = (string)($job['job_description'] ?? '');
                      $short = strlen($full) > 60 ? substr($full, 0, 60) . '…' : $full;
                      echo htmlspecialchars($short);
                    ?>
                  </td>
                  <td title="<?php echo htmlspecialchars((string)($job['requirements'] ?? '')); ?>">
                    <?php
                      $fullReq = (string)($job['requirements'] ?? '');
                      $shortReq = strlen($fullReq) > 60 ? substr($fullReq, 0, 60) . '…' : $fullReq;
                      echo htmlspecialchars($shortReq);
                    ?>
                  </td>
                  <td>
                    <?php
                      $p = $job['posted_at'] ?? '';
                      $pfmt = '';
                      if (!empty($p)) {
                        $pts = strtotime($p);
                        $pfmt = $pts ? date('M d, Y', $pts) : $p;
                      }
                      echo htmlspecialchars($pfmt);
                    ?>
                  </td>
                  <td>
                    <?php
                      $d = $job['deadline'] ?? '';
                      $fmt = '';
                      if (!empty($d)) {
                        $ts = strtotime($d);
                        $fmt = $ts ? date('M d, Y', $ts) : $d;
                      }
                      echo htmlspecialchars($fmt);
                    ?>
                  </td>
                  <td><?php echo htmlspecialchars($job['status'] ?? ''); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <!-- Applied Jobs Placeholder -->
    <div id="appliedSection" class="section" style="display:none;">
      <div class="companies-title-bar mb-4">Applied Jobs</div>
      <p class="small-hint">(Wire this up later.)</p>
    </div>

    <!-- Add Admin Role Section -->
    <div id="adminsSection" class="section" style="display:none;">
      <div class="companies-title-bar mb-4">Add Admin Role</div>

      <?php if ($admin_success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($admin_success); ?></div>
      <?php endif; ?>
      <?php if ($admin_error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($admin_error); ?></div>
      <?php endif; ?>

      <div class="form-card mb-4">
        <form method="post" id="addAdminForm" novalidate>
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

            <div class="full">
              <label class="form-label">Address</label>
              <input type="text" name="address" class="form-control" required>
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
          <table class="table dark-table align-middle" style="font-weight:bold; text-align:center;">
            <thead>
              <tr>
                <th>User Id</th>
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

    <!-- Extras -->
    <div id="feedbacksSection" class="section" style="display:none;">
      <div class="companies-title-bar mb-4">Feedbacks</div>
      <p class="small-hint">(Connect your feedbacks page here if needed.)</p>
    </div>
    <div id="profileSection" class="section" style="display:none;">
      <div class="companies-title-bar mb-4">Profile</div>
      <p class="small-hint">Profile content here.</p>
    </div>
    <div id="settingsSection" class="section" style="display:none;">
      <div class="companies-title-bar mb-4">Settings</div>
      <p class="small-hint">Settings content here.</p>
    </div>
  </div>
</body>
</html>
