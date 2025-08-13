<?php
include("connect.php");
session_start();

$login_message = "";
$login_success = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $email    = $_POST['email'];
  $password = $_POST['password'];

  // 1. Check users table
  $sql_user = "SELECT * FROM users WHERE email = ? AND password = ?";
  $stmt_user = $pdo->prepare($sql_user);
  $stmt_user->execute([$email, $password]);
  $user = $stmt_user->fetch(PDO::FETCH_ASSOC);

  if ($user) {
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['user_type'] = $user['role']; // 'user' or 'admin'

    if ($user['role'] === 'admin') {
      $login_message = "<div class='alert alert-success custom-success text-center mt-3' id='login-alert'>Admin Login Successful!</div>";
      $login_success = 'admin';
    } else {
      $login_message = "<div class='alert alert-success custom-success text-center mt-3' id='login-alert'>Login Successful!</div>";
      $login_success = 'user';
    }
  } else {
    // 2. Check companies table
    $sql_company = "SELECT * FROM companies WHERE email = ? AND password = ?";
    $stmt_company = $pdo->prepare($sql_company);
    $stmt_company->execute([$email, $password]);
    $company = $stmt_company->fetch(PDO::FETCH_ASSOC);

    if ($company) {
      $_SESSION['company_id'] = $company['company_id'];
      $_SESSION['user_type'] = 'company';

      $login_message = "<div class='alert alert-success custom-success text-center mt-3' id='login-alert'>Company Login Successful!</div>";
      $login_success = 'company';
    } else {
      $login_message = "<div class='alert alert-danger custom-error text-center mt-3'>Invalid email or password!</div>";
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>JobHive | Login</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <style>
    body {
      background: #f8fafc;
      font-size: 14px;
    }

    .login-container {
      max-width: 320px;
      margin: 24px auto 35px auto;
      background: #fff;
      padding: 1.3rem 1rem 1.6rem 1rem;
      border-radius: 1rem;
      box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
      font-size: 14px;
    }

    .login-title {
      color: #ffaa2b;
      font-weight: 600;
      letter-spacing: 0.7px;
      margin-bottom: 1.1rem;
      text-align: center;
      font-size: 1.25rem;
    }

    .form-label {
      font-size: 0.97rem;
      margin-bottom: 0.2rem;
    }

    .form-control {
      font-size: 0.93rem;
      padding: 0.33rem 0.75rem;
      border-radius: 0.5rem;
      min-height: 34px;
    }

    .form-control:focus {
      border-color: #ffaa2b;
      box-shadow: 0 0 0 0.08rem rgba(255, 170, 43, 0.11);
    }

    .btn-warning {
      background: #ffaa2b;
      border: none;
      font-size: 1rem;
      border-radius: 0.7rem;
      padding: 0.42rem 0;
    }

    .btn-warning:hover {
      background: #ff8800;
    }

    .small-link {
      display: block;
      text-align: center;
      margin-top: 1.1rem;
      font-size: 0.97rem;
    }

    .brand-logo-link {
      text-decoration: none;
    }

    .brand-logo-text {
      font-size: 1.45rem;
      font-weight: bold;
      color: #ffaa2b;
      letter-spacing: 1px;
      display: inline-block;
      margin-bottom: 0.3rem;
      margin-top: 1.25rem;
      text-shadow: 0 1px 3px rgba(255, 170, 43, 0.08);
      transition: color 0.18s;
    }

    .brand-logo-link:hover .brand-logo-text {
      color: #ff8800;
    }

    .alert-success.custom-success {
      background: #fff8ec;
      color: #ffaa2b;
      border: 1px solid #ffaa2b;
      font-weight: 500;
      border-radius: 0.75rem;
      font-size: 1rem;
    }

    .alert-danger.custom-error {
      background: #fbe8e6;
      color: #e25617;
      border: 1px solid #e25617;
      font-weight: 500;
      border-radius: 0.75rem;
      font-size: 1rem;
    }
  </style>
</head>

<body>
  <div class="text-center">
    <a href="index.php" class="brand-logo-link">
      <span class="brand-logo-text">JobHive</span>
    </a>
  </div>
  <div class="login-container">
    <div class="login-title">Login</div>
    <form action="login.php" method="POST" autocomplete="off">
      <div class="mb-2">
        <label for="email" class="form-label">Email address</label>
        <input
          type="email"
          class="form-control"
          id="email"
          name="email"
          required
          maxlength="100" />
      </div>
      <div class="mb-2">
        <label for="password" class="form-label">Password</label>
        <input
          type="password"
          class="form-control"
          id="password"
          name="password"
          required
          minlength="6" />
      </div>
      <button type="submit" class="btn btn-warning w-100 py-2 mt-2">
        Login
      </button>
      <a href="sign_up.php" class="small-link text-decoration-none">Don't have an account? <span class="text-warning">Register</span></a>
    </form>

    <!-- Login message and redirect -->
    <?php
    if ($login_message) {
      echo $login_message;
      if ($login_success === 'user') {
        echo "<script>
            setTimeout(function() {
                window.location.href = 'user_home.php';
            }, 2000);
            </script>";
      }
      if ($login_success === 'company') {
        echo "<script>
            setTimeout(function() {
                window.location.href = 'company_home.php';
            }, 2000);
            </script>";
      }
      if ($login_success === 'admin') {
        echo "<script>
            setTimeout(function() {
                window.location.href = 'admin.php';
            }, 2000);
            </script>";
      }
    }
    ?>
  </div>
</body>

</html>