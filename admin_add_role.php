<?php
include("connect.php");
$message = "";
$admin_success = false;

try {
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $full_name = $_POST['fullname'];
        $email     = $_POST['email'];
        $password  = $_POST['password'];
        $phone     = $_POST['phno'];

        // Check if email or phone exists
        $check_sql = "SELECT * FROM users WHERE email = ? OR phone = ?";
        $stmt = $pdo->prepare($check_sql);
        $stmt->execute([$email, $phone]);
        $exists = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($exists) {
            // Username (full_name) not matched!
            if ($exists['full_name'] !== $full_name) {
                $message = "<div class='alert alert-danger custom-error text-center'>
                Email or phone already exists with a different username. Please check your information.
                </div>";
            }
            // If it's already admin
            else if ($exists['role'] == 'admin') {
                $message = "<div class='alert alert-danger custom-error text-center'>
                The user you entered is already an <b>admin</b>. Please use a different email or phone number.
                </div>";
            }
            // Else, full name matches and is not admin: upgrade
            else {
                $update_sql = "UPDATE users SET role = 'admin', password = ? WHERE user_id = ?";
                $update_stmt = $pdo->prepare($update_sql);
                $update_stmt->execute([$password, $exists['user_id']]);
                $message = "<div class='alert alert-success custom-success text-center'>
                Existing user upgraded to <b>Admin</b> successfully!
                </div>";
                $admin_success = true;
            }
        } else {
            // Insert as new admin, show special message
            $insert_sql = "INSERT INTO users (full_name, email, password, phone, role)
                           VALUES (?, ?, ?, ?, 'admin')";
            $insert_stmt = $pdo->prepare($insert_sql);
            $insert_stmt->execute([$full_name, $email, $password, $phone]);
            $message = "<div class='alert alert-success custom-success text-center'>
            No matching user found. <b>New Admin account</b> created successfully!
            </div>";
            $admin_success = true;
        }
    }
} catch(PDOException $e) {
    $message = "<div class='alert alert-danger custom-error text-center'>Fail to Connect: " . $e->getMessage() . "</div>";
}
?>



<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Add Admin | JobHive</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background: #f8fafc;
      font-size: 14px;
    }
    .register-container {
      max-width: 340px;
      margin: 22px auto 35px auto;
      background: #fff;
      padding: 1.3rem 1rem 1.6rem 1rem;
      border-radius: 1rem;
      box-shadow: 0 2px 12px rgba(0,0,0,0.06);
      font-size: 14px;
    }
    .register-title {
      color: #ffaa2b;
      font-weight: 600;
      letter-spacing: 0.7px;
      margin-bottom: 1.1rem;
      text-align: center;
      font-size: 1.25rem;
    }
    .form-label {
      font-size: 0.98rem;
      margin-bottom: 0.2rem;
    }
    .form-control {
      font-size: 0.95rem;
      padding: 0.35rem 0.75rem;
      border-radius: 0.5rem;
      min-height: 34px;
    }
    .form-control:focus {
      border-color: #ffaa2b;
      box-shadow: 0 0 0 0.08rem rgba(255,170,43,0.11);
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
    .alert-success.custom-success {
      background: #fff8ec;
      color: #ffaa2b;
      border: 1px solid #ffaa2b;
      font-weight: 500;
      border-radius: 0.75rem;
      font-size: 1rem;
      margin-top: 12px;
      margin-bottom: 4px;
    }
    .alert-danger.custom-error {
      background: #fbe8e6;
      color: #e25617;
      border: 1px solid #e25617;
      font-weight: 500;
      border-radius: 0.75rem;
      font-size: 1rem;
      margin-top: 12px;
      margin-bottom: 4px;
    }
  </style>
</head>
<body>
  <div class="register-container">
    <div class="register-title">Add Admin Role</div>
    <?php
      if ($message) {
        echo $message;
        if ($admin_success) {
          // Optional: redirect after success
          // echo "<script>setTimeout(function(){ window.location.href = 'admin_seekers.php'; }, 2500);</script>";
        }
      }
    ?>
    <form method="POST" autocomplete="off">
      <div class="mb-2">
        <label for="fullname" class="form-label">Full Name</label>
        <input type="text" class="form-control" id="fullname" name="fullname" required maxlength="80">
      </div>
      <div class="mb-2">
        <label for="email" class="form-label">Email</label>
        <input type="email" class="form-control" id="email" name="email" required maxlength="100">
      </div>
      <div class="mb-2">
        <label for="password" class="form-label">Password</label>
        <input type="password" class="form-control" id="password" name="password" required minlength="6">
      </div>
      <div class="mb-2">
        <label for="phno" class="form-label">Phone Number</label>
        <input type="tel" class="form-control" id="phno" name="phno" required pattern="[0-9]{7,15}" maxlength="15">
        <small class="form-text text-muted">Enter only digits, e.g. 0912345678</small>
      </div>
      <button type="submit" class="btn btn-warning w-100 py-2 mt-2">Add Admin</button>
    </form>
  </div>
</body>
</html>
