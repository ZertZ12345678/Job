<?php
include("connect.php");
$message = "";
$register_success = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  // ---- Inputs ----
  $company_name = trim($_POST['company_name'] ?? '');
  $email        = trim($_POST['email'] ?? '');
  $password     = $_POST['password'] ?? '';
  $phone        = trim($_POST['phone'] ?? '');
  $address      = trim($_POST['address'] ?? '');
  $c_detail     = trim($_POST['c_detail'] ?? ''); // <-- NEW: Company Detail
  $logo         = "";

  // ---- Validate logo upload (required) ----
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

  // ---- Continue only if no logo error and logo present ----
  if ($logo !== "" && $message === "") {
    // Unique email & phone check
    $stmt = $pdo->prepare("SELECT company_id, email, phone FROM companies WHERE email = ? OR phone = ? LIMIT 1");
    $stmt->execute([$email, $phone]);
    $exists = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($exists) {
      if ($exists['email'] == $email) {
        $message = "<div class='alert alert-danger custom-error text-center'>This email is already registered.</div>";
      } else {
        $message = "<div class='alert alert-danger custom-error text-center'>This phone number is already registered.</div>";
      }
    } else {
      // ---- INSERT including c_detail ----
      $stmt = $pdo->prepare("
        INSERT INTO companies (company_name, email, password, phone, address, c_detail, logo)
        VALUES (?, ?, ?, ?, ?, ?, ?)
      ");
      $stmt->execute([$company_name, $email, $password, $phone, $address, $c_detail, $logo]);

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
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Company Register</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <style>
    body {
      background: #f8fafc;
      font-size: 14px;
    }

    .register-container {
      max-width: 370px;
      margin: 32px auto 35px;
      background: #fff;
      padding: 1.3rem 1rem 1.6rem;
      border-radius: 1rem;
      box-shadow: 0 2px 12px rgba(0, 0, 0, .06);
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

    .form-control,
    .form-control:focus,
    .form-select {
      font-size: .95rem;
    }

    .form-control {
      padding: .35rem .75rem;
      border-radius: .5rem;
      min-height: 34px;
    }

    .form-control:focus {
      border-color: #ffaa2b;
      box-shadow: 0 0 0 .08rem rgba(255, 170, 43, .11);
    }

    textarea.form-control {
      min-height: 90px;
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
    <div class="register-title">Company Registration</div>

    <?php
    if ($message) {
      echo $message;
      if ($register_success) {
        echo "<script>
            setTimeout(function(){ window.location.href = 'company_home.php'; }, 3000);
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

      <!-- NEW: Company Detail (after Address) -->
      <div class="mb-2">
        <label for="c_detail" class="form-label">Company Detail</label>
        <textarea class="form-control" id="c_detail" name="c_detail" placeholder="Brief company description, services, branches, etc." maxlength="5000"></textarea>
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