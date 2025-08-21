<?php
include("connect.php");
session_start(); // <<— make sure session is started

$message = "";
$register_success = false;

try {
  if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Basic sanitization
    $full_name = trim($_POST['fullname'] ?? '');
    $email     = strtolower(trim($_POST['email'] ?? ''));
    $password  = $_POST['password'] ?? '';
    $phone     = trim($_POST['phno'] ?? '');

    // (Optional but recommended) validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      throw new Exception("Invalid email format.");
    }

    // Check if email or phone already exists
    $check_sql = "SELECT user_id, email, phone FROM users WHERE email = ? OR phone = ?";
    $stmt = $pdo->prepare($check_sql);
    $stmt->execute([$email, $phone]);
    $exists = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($exists) {
      if ($exists['email'] === $email) {
        $message = "<div class='alert alert-danger custom-error text-center'>This email is already registered.</div>";
      } else {
        $message = "<div class='alert alert-danger custom-error text-center'>This phone number is already registered.</div>";
      }
    } else {
      // NOTE: For real apps, please hash the password!
      // $password_hash = password_hash($password, PASSWORD_DEFAULT);

      $sql = "INSERT INTO users (full_name, email, password, phone, role)
              VALUES (?, ?, ?, ?, ?)";
      $stmt = $pdo->prepare($sql);
      $stmt->execute([$full_name, $email, $password, $phone, 'user']);

      // Get new user id and log them in
      $new_user_id = (int)$pdo->lastInsertId();
      $_SESSION['user_id']   = $new_user_id;
      $_SESSION['full_name'] = $full_name;
      $_SESSION['email']     = $email;
      $_SESSION['role']      = 'user';

      $message = "<div class='alert alert-success custom-success text-center' id='register-alert'>Registration Successful! Going to your home...</div>";
      $register_success = true;
    }
  }
} catch (PDOException $e) {
  $message = "<div class='alert alert-danger custom-error text-center'>Fail to Connect: " . htmlspecialchars($e->getMessage()) . "</div>";
} catch (Exception $e) {
  $message = "<div class='alert alert-danger custom-error text-center'>" . htmlspecialchars($e->getMessage()) . "</div>";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>JobHive | User SignUp</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <style>
    body {
      background: #f8fafc;
      font-size: 14px;
    }

    .register-container {
      max-width: 340px;
      margin: 22px auto 35px;
      background: #fff;
      padding: 1.3rem 1rem 1.6rem;
      border-radius: 1rem;
      box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
      font-size: 14px;
    }

    .register-title {
      color: #ffaa2b;
      font-weight: 600;
      letter-spacing: .7px;
      margin-bottom: 1.1rem;
      text-align: center;
      font-size: 1.25rem;
    }

    .form-label {
      font-size: .98rem;
      margin-bottom: .2rem;
    }

    .form-control {
      font-size: .95rem;
      padding: .35rem .75rem;
      border-radius: .5rem;
      min-height: 34px;
    }

    .form-control:focus {
      border-color: #ffaa2b;
      box-shadow: 0 0 0 .08rem rgba(255, 170, 43, .11);
    }

    .btn-warning {
      background: #ffaa2b;
      border: none;
      font-size: 1rem;
      border-radius: .7rem;
      padding: .42rem 0;
    }

    .btn-warning:hover {
      background: #ff8800;
    }

    .small-link {
      display: block;
      text-align: center;
      margin-top: 1.1rem;
      font-size: .96rem;
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
      margin-bottom: .3rem;
      margin-top: 1.25rem;
      text-shadow: 0 1px 3px rgba(255, 170, 43, .08);
      transition: color .18s;
    }

    .brand-logo-link:hover .brand-logo-text {
      color: #ff8800;
    }

    .form-text {
      font-size: .81rem;
      margin-top: .1rem;
    }

    .alert-success.custom-success {
      background: #fff8ec;
      color: #ffaa2b;
      border: 1px solid #ffaa2b;
      font-weight: 500;
      border-radius: .75rem;
      font-size: 1rem;
      margin-top: 12px;
      margin-bottom: 4px;
    }

    .alert-danger.custom-error {
      background: #fbe8e6;
      color: #e25617;
      border: 1px solid #e25617;
      font-weight: 500;
      border-radius: .75rem;
      font-size: 1rem;
      margin-top: 12px;
      margin-bottom: 4px;
    }
  </style>
</head>

<body>
  <div class="text-center">
    <a href="index.php" class="brand-logo-link">
      <span class="brand-logo-text">JobHive</span>
    </a>
  </div>

  <div class="register-container">
    <div class="register-title">Create Your JobHive Account</div>

    <?php
    if ($message) {
      echo $message;
      if ($register_success) {
        // Redirect after 3 seconds to the logged-in user's home
        echo "<script>
            setTimeout(function() {
              window.location.href = 'user_home.php';
            }, 3000);
          </script>";
      }
    }
    ?>

    <form method="POST" autocomplete="off" novalidate>
      <div class="mb-2">
        <label for="fullname" class="form-label">Full Name</label>
        <input type="text" class="form-control" id="fullname" name="fullname" required maxlength="80" value="<?php echo htmlspecialchars($_POST['fullname'] ?? ''); ?>">
      </div>
      <div class="mb-2">
        <label for="email" class="form-label">Email</label>
        <input type="email" class="form-control" id="email" name="email" required maxlength="100" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
      </div>
      <div class="mb-2">
        <label for="password" class="form-label">Password</label>
        <input type="password" class="form-control" id="password" name="password" required minlength="6">
      </div>
      <div class="mb-2">
        <label for="phno" class="form-label">Phone Number</label>
        <input type="tel" class="form-control" id="phno" name="phno" required pattern="[0-9]{7,15}" maxlength="15" value="<?php echo htmlspecialchars($_POST['phno'] ?? ''); ?>">
        <small class="form-text text-muted">Enter only digits, e.g. 0912345678</small>
      </div>
      <button type="submit" class="btn btn-warning w-100 py-2 mt-2">Register</button>
      <a href="login.php" class="small-link text-decoration-none">Already have an account? <span class="text-warning">Login</span></a>
    </form>
  </div>
</body>

</html>