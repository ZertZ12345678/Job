<?php
include("connect.php");
$message = "";
$register_success = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $company_name = $_POST['company_name'];
  $email        = $_POST['email'];
  $password     = $_POST['password'];
  $phone        = $_POST['phone'];
  $address      = $_POST['address'];
  $logo         = "";

  // Validate logo upload
  if (isset($_FILES['logo']) && $_FILES['logo']['error'] == 0) {
    $allowed = ["jpg" => "image/jpeg", "jpeg" => "image/jpeg", "png" => "image/png"];
    $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
    $filetype = $_FILES['logo']['type'];

    if (!array_key_exists($ext, $allowed) || !in_array($filetype, $allowed)) {
      $message = "<div class='alert alert-danger custom-error text-center'>Invalid logo file type. Only JPG and PNG allowed.</div>";
    } else {
      $target_dir = "company_logos/";
      if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
      $filename = uniqid("logo_") . "." . $ext;
      $target_file = $target_dir . $filename;
      if (move_uploaded_file($_FILES["logo"]["tmp_name"], $target_file)) {
        $logo = $filename;
      } else {
        $message = "<div class='alert alert-danger custom-error text-center'>Failed to upload logo.</div>";
      }
    }
  }

  // Only continue if logo is uploaded or no error
  if ($logo !== "" && $message === "") {
    // Unique email & phone check
    $stmt = $pdo->prepare("SELECT * FROM companies WHERE email = ? OR phone = ?");
    $stmt->execute([$email, $phone]);
    $exists = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($exists) {
      if ($exists['email'] == $email) {
        $message = "<div class='alert alert-danger custom-error text-center'>This email is already registered.</div>";
      } else {
        $message = "<div class='alert alert-danger custom-error text-center'>This phone number is already registered.</div>";
      }
    } else {
      $stmt = $pdo->prepare("INSERT INTO companies (company_name, email, password, phone, address, logo)
                                   VALUES (?, ?, ?, ?, ?, ?)");
      $stmt->execute([$company_name, $email, $password, $phone, $address, $logo]);
      $message = "<div class='alert alert-success custom-success text-center' id='register-alert'>Registration Successful!</div>";
      $register_success = true;
    }
  } elseif ($logo === "") {
    $message = "<div class='alert alert-danger custom-error text-center'>Please upload a company logo.</div>";
  }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Company Register</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background: #f8fafc;
      font-size: 14px;
    }

    .register-container {
      max-width: 370px;
      margin: 32px auto 35px auto;
      background: #fff;
      padding: 1.3rem 1rem 1.6rem 1rem;
      border-radius: 1rem;
      box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
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
      font-size: 0.96rem;
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

    .form-text {
      font-size: 0.81rem;
      margin-top: 0.1rem;
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
  <div class="text-center">
    <a href="index.php" class="brand-logo-link">
      <span class="brand-logo-text">JobHive</span>
    </a>
  </div>
  <div class="register-container">
    <div class="register-title">Company Registration</div>
    <?php
    if ($message) {
      echo $message;
      if ($register_success) {
        echo "<script>
          setTimeout(function() {
            window.location.href = 'company_home.php';
          }, 3000);
          </script>";
      }
    }
    ?>
    <form method="POST" enctype="multipart/form-data" autocomplete="off">
      <div class="mb-2">
        <label for="company_name" class="form-label">Company Name</label>
        <input type="text" class="form-control" id="company_name" name="company_name" required maxlength="80">
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
        <label for="phone" class="form-label">Phone Number</label>
        <input type="tel" class="form-control" id="phone" name="phone" required pattern="[0-9]{7,15}" maxlength="15">
        <small class="form-text text-muted">Enter only digits, e.g. 0912345678</small>
      </div>
      <div class="mb-2">
        <label for="address" class="form-label">Company Address</label>
        <input type="text" class="form-control" id="address" name="address" required maxlength="180">
      </div>
      <div class="mb-2">
        <label for="logo" class="form-label">Company Logo</label>
        <input type="file" class="form-control" id="logo" name="logo" required accept="image/png, image/jpeg">
        <small class="form-text text-muted">Upload JPG or PNG only. Max size ~2MB.</small>
      </div>
      <button type="submit" class="btn btn-warning w-100 py-2 mt-2">Register Company</button>
      <a href="login.php" class="small-link text-decoration-none">Already registered? <span class="text-warning">Login</span></a>
    </form>
  </div>
</body>

</html>