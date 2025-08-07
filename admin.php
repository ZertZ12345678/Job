<?php
include("connect.php");
session_start();

// Fetch companies data for display
try {
    $stmt = $pdo->query("SELECT * FROM companies ORDER BY company_id DESC");
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $companies = [];
    $error_message = "Error loading companies data: " . $e->getMessage();
}

// Fetch seekers (users) data for display
try {
    $stmt2 = $pdo->query("SELECT * FROM users ORDER BY user_id DESC");
    $seekers = $stmt2->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $seekers = [];
    $seekers_error = "Error loading seekers data: " . $e->getMessage();
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
      transition: background 0.13s, color 0.13s;
      margin-bottom: 0.1rem;
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
    .content {
      margin-left: 250px;
      padding: 40px 30px 30px 30px;
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
    .companies-title-bar {
      background: #22223b;
      color: #ffc107;
      padding: 0.85rem 1.2rem;
      border-radius: 0.7rem;
      font-size: 1.35rem;
      font-weight: 700;
      margin-bottom: 1.4rem;
      letter-spacing: 0.2px;
      box-shadow: 0 1px 5px rgba(30,30,55,0.06);
      display: inline-block;
    }
    .dark-table {
      background: #22223b;
      color: #ffc107;
      border-radius: 0.7rem;
      overflow: hidden;
    }
    .dark-table th, .dark-table td {
      background: #22223b !important;
      color: #ffc107 !important;
      border: 1px solid #ffc107 !important;
      font-weight: 500;
      font-size: 1.07rem;
      vertical-align: middle !important;
      padding: 0.7rem 1.1rem;
    }
    .dark-table th {
      font-weight: bold;
      font-size: 1.12rem;
      letter-spacing: 0.03rem;
      border-bottom: 2px solid #ffc107 !important;
      text-align: left;
    }
    .logo-cell {
      text-align: center;
      min-width: 70px;
      max-width: 90px;
      vertical-align: middle !important;
    }
    .company-logo-img {
      height: 52px;
      width: 52px;
      border-radius: 0;
      object-fit: contain;
      background: #fff;
      box-shadow: 0 0 0 1px #f1f1f1;
      display: block;
      margin: 0 auto;
    }
    .dark-table tbody tr:hover td {
      background: #292944 !important;
      color: #ffd966 !important;
      transition: 0.15s;
    }
  </style>
  <script>
    function showSection(sectionId, linkElement) {
      // Hide all sections
      document.querySelectorAll('.section').forEach(el => el.style.display = 'none');
      // Show selected section
      document.getElementById(sectionId).style.display = 'block';

      // Update sidebar active link
      document.querySelectorAll('.sidebar .nav-link').forEach(link => link.classList.remove('active'));
      linkElement.classList.add('active');
    }

    window.addEventListener('DOMContentLoaded', () => {
      // Show dashboard by default
      showSection('dashboardSection', document.getElementById('dashboardLink'));
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
      <a class="nav-link" href="admin_jobs.php">Jobs</a>
      <a class="nav-link" href="admin_add_role.php">Add Admin Role</a>
      <a class="nav-link" href="admin_reports.php">Reports</a>
      <a class="nav-link" href="admin_settings.php">Settings</a>
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
              <div class="display-6 fw-bold text-warning">23</div>
            </div>
          </div>
        </div>
        <div class="col-md-4 mb-4">
          <div class="card shadow-sm rounded-4 border-0">
            <div class="card-body text-center">
              <h5 class="card-title">Total Seekers</h5>
              <div class="display-6 fw-bold text-warning">142</div>
            </div>
          </div>
        </div>
        <div class="col-md-4 mb-4">
          <div class="card shadow-sm rounded-4 border-0">
            <div class="card-body text-center">
              <h5 class="card-title">Jobs Posted</h5>
              <div class="display-6 fw-bold text-warning">55</div>
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
          if ($company_count === 0) {
            echo "No Company Information";
          } elseif ($company_count === 1) {
            echo "1 Company Information";
          } else {
            echo "{$company_count} Companies Information";
          }
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
                      <img src="company_logos/<?php echo htmlspecialchars($company['logo']); ?>"
                        alt="Logo"
                        class="company-logo-img"
                        style="width:60px;height:60px;object-fit:contain;background:#fff;border-radius:10px;">
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
          // Only count users with role "user"
          $user_seekers = array_filter($seekers, function($s) { return ($s['role'] ?? '') === 'user'; });
          $seeker_count = count($user_seekers);
          if ($seeker_count === 0) {
            echo "No Seekers Information";
          } elseif ($seeker_count === 1) {
            echo "1 Seeker Information";
          } else {
            echo "{$seeker_count} Seekers Information";
          }
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
                      <img src="profile_pics/<?php echo htmlspecialchars($seeker['profile_picture']); ?>"
                           alt="Photo"
                           class="company-logo-img"
                           style="width:60px;height:60px;object-fit:contain;background:#fff;border-radius:10px;">
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

  </div>
</body>
</html>
