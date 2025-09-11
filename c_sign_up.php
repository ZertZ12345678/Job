<?php
include("connect.php");
if (session_status() === PHP_SESSION_NONE) session_start();

$message = "";
$register_success = false;

/* ================== Strong Password Policy ==================
   - at least 8 characters
   - at least 1 lowercase, 1 uppercase, 1 digit, 1 special
   - no spaces
============================================================= */
const NEW_PW_MIN_LEN   = 8;
const PW_POLICY_REGEX  = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()\-\_\=\+\[\]\{\};:,.?~])(?!.*\s).{8,}$/';
const PW_POLICY_HUMAN  = "At least 8 chars, with 1 uppercase, 1 lowercase, 1 number, and 1 special (!@#\$%^&*() -_=+[]{};:,.?~). No spaces.";
const PW_BLOCKLIST     = ['password', 'Password1', 'Passw0rd', '12345678', 'qwerty123', 'letmein', 'admin123', 'jobhive123'];

function isStrongPassword(string $pw): array
{
  if (strlen($pw) < NEW_PW_MIN_LEN) return [false, "Password must be at least " . NEW_PW_MIN_LEN . " characters."];
  if (preg_match('/\s/', $pw))      return [false, "Password cannot contain spaces."];
  foreach (PW_BLOCKLIST as $bad) if (strcasecmp($pw, $bad) === 0) return [false, "That password is too common. Please choose another."];
  if (!preg_match(PW_POLICY_REGEX, $pw)) return [false, PW_POLICY_HUMAN];
  if (preg_match('/(.)\1\1/', $pw)) return [false, "Avoid repeating any character 3+ times in a row."];
  return [true, ""];
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $company_name = trim($_POST['company_name'] ?? '');
  $email        = strtolower(trim($_POST['email'] ?? ''));
  $password     = $_POST['password'] ?? '';           // PLAIN TEXT as requested
  $phone_raw    = trim($_POST['phone'] ?? '');
  $address      = trim($_POST['address'] ?? '');
  $c_detail     = trim($_POST['c_detail'] ?? '');
  $logo         = "";

  // Email format
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $message = "<div class='alert alert-danger custom-error text-center'>Invalid email format.</div>";
  }

  // Phone validation: digits only, length 7–15
  if ($message === "") {
    if ($phone_raw === "") {
      $message = "<div class='alert alert-danger custom-error text-center'>Phone number is required.</div>";
    } elseif (!ctype_digit($phone_raw)) {
      $message = "<div class='alert alert-danger custom-error text-center'>Phone must contain digits only (0–9).</div>";
    } elseif (strlen($phone_raw) < 7 || strlen($phone_raw) > 15) {
      $message = "<div class='alert alert-danger custom-error text-center'>Phone must be 7–15 digits.</div>";
    }
  }
  $phone = $phone_raw;

  // Password policy
  if ($message === "") {
    [$okStrong, $why] = isStrongPassword($password);
    if (!$okStrong) $message = "<div class='alert alert-danger custom-error text-center'>Weak password: " . htmlspecialchars($why) . "</div>";
  }

  // Logo upload
  if ($message === "") {
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] == 0) {
      $allowed  = ["jpg" => "image/jpeg", "jpeg" => "image/jpeg", "png" => "image/png"];
      $ext      = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
      $filetype = $_FILES['logo']['type'];

      if (!array_key_exists($ext, $allowed) || !in_array($filetype, $allowed, true)) {
        $message = "<div class='alert alert-danger custom-error text-center'>Invalid logo file type. Only JPG and PNG allowed.</div>";
      } else {
        $dir = "company_logos/";
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        $filename = uniqid("logo_") . "." . $ext;
        if (move_uploaded_file($_FILES["logo"]["tmp_name"], $dir . $filename)) {
          $logo = $filename;
        } else {
          $message = "<div class='alert alert-danger custom-error text-center'>Failed to upload logo.</div>";
        }
      }
    } else {
      $message = "<div class='alert alert-danger custom-error text-center'>Please upload a company logo.</div>";
    }
  }

  // Uniqueness checks (PHP level only; no ALTER TABLE needed)
  if ($message === "") {
    $stmt = $pdo->prepare("SELECT company_id, email, phone FROM companies WHERE email = ? OR phone = ? LIMIT 1");
    $stmt->execute([$email, $phone]);
    $exists = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($exists) {
      if (isset($exists['email']) && strcasecmp($exists['email'], $email) === 0) {
        $message = "<div class='alert alert-danger custom-error text-center'>This email is already registered.</div>";
      } else {
        $message = "<div class='alert alert-danger custom-error text-center'>This phone number is already registered.</div>";
      }
    }
  }

  // Insert (password in PLAIN TEXT)
  if ($message === "") {
    $stmt = $pdo->prepare("
      INSERT INTO companies (company_name, email, password, phone, address, c_detail, logo)
      VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$company_name, $email, $password, $phone, $address, $c_detail, $logo]);

    // Set session & redirect to company_home
    $newId = (int)$pdo->lastInsertId();
    $_SESSION['company_id']   = $newId;
    $_SESSION['company_name'] = $company_name;
    $_SESSION['email']        = $email;

    header("Location: company_home.php");
    exit;
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>JobHive | Company SignUp</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <style>
    :root {
      --ink: #22223b;
      --gold: #ffc107;
    }

    html,
    body {
      height: 100%;
      margin: 0;
      padding: 0;
      font-family: 'Helvetica Neue', Arial, sans-serif;
      background: var(--gold);
    }

    body {
      display: flex;
      justify-content: center;
      align-items: flex-start;
    }

    .signup-container {
      width: 95%;
      max-width: 400px;
      background: var(--ink);
      border-radius: 8px;
      padding: 15px 18px;
      margin: 15px 0;
      box-shadow: 0 4px 10px rgba(0, 0, 0, .2);
    }

    .form-title {
      color: var(--gold);
      font-size: 18px;
      font-weight: 700;
      text-align: center;
      margin-bottom: 14px;
    }

    .form-group {
      margin-bottom: 10px;
    }

    .form-label {
      color: var(--gold);
      font-weight: 600;
      font-size: 12px;
      margin-bottom: 3px;
    }

    .form-control {
      background: #fff;
      border: none;
      border-radius: 5px;
      height: 32px;
      padding: 5px 8px;
      font-size: 13px;
    }

    .form-control:focus {
      box-shadow: 0 0 0 2px rgba(255, 193, 7, .3);
      outline: none;
    }

    textarea.form-control {
      min-height: 50px;
      font-size: 13px;
    }

    .form-hint {
      color: #d9c36a;
      font-size: 10px;
      margin-top: 2px;
    }

    .btn-register {
      background: var(--gold);
      color: var(--ink);
      border: none;
      border-radius: 5px;
      height: 34px;
      font-weight: 600;
      font-size: 13px;
      margin-top: 6px;
      transition: all .3s ease;
      width: 100%;
    }

    .btn-register:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 8px rgba(255, 193, 7, .3);
    }

    .login-link {
      color: #ffe08a;
      text-align: center;
      margin-top: 10px;
      font-size: 12px;
    }

    .login-link a {
      color: #fff;
      font-weight: 600;
      text-decoration: none;
    }

    .login-link a:hover {
      text-decoration: underline;
    }

    .alert {
      border-radius: 5px;
      padding: 8px;
      margin-bottom: 12px;
      font-size: 12px;
    }

    .alert-success {
      background: rgba(47, 197, 94, .2);
      color: #2fc55e;
      border: 1px solid #2fc55e;
    }

    .alert-danger {
      background: rgba(239, 68, 68, .2);
      color: #ef4444;
      border: 1px solid #ef4444;
    }

    .pw-strength {
      margin-top: 4px;
      font-size: 10px;
    }

    .pw-strength ul {
      list-style: none;
      padding: 0;
      margin: 0;
      color: #d9c36a;
    }

    .pw-strength li {
      margin-bottom: 2px;
      padding-left: 14px;
      position: relative;
    }

    .pw-strength li:before {
      content: "✓";
      position: absolute;
      left: 0;
      color: #d9c36a;
    }

    .pw-strength li.valid,
    .pw-strength li.valid:before {
      color: #2fc55e;
    }

    .brand-logo {
      color: var(--gold);
      font-size: 14px;
      font-weight: 700;
      text-decoration: none;
      text-align: center;
      display: block;
      margin-top: 12px;
    }

    .brand-logo:hover {
      text-decoration: underline;
    }

    @media (max-width: 768px) {
      .signup-container {
        width: 96%;
        padding: 12px;
      }

      .form-title {
        font-size: 16px;
      }
    }
  </style>
</head>

<body>
  <div class="signup-container">
    <h1 class="form-title">Create Your Company Account</h1>

    <?php if (!empty($message)) echo $message; ?>

    <form method="POST" enctype="multipart/form-data" autocomplete="off" novalidate>
      <div class="row">
        <div class="col-md-6">
          <div class="form-group">
            <label for="company_name" class="form-label">Company Name</label>
            <input type="text" class="form-control" id="company_name" name="company_name" required maxlength="80"
              value="<?php echo htmlspecialchars($_POST['company_name'] ?? ''); ?>">
          </div>
        </div>
        <div class="col-md-6">
          <div class="form-group">
            <label for="email" class="form-label">Company Email</label>
            <input type="email" class="form-control" id="email" name="email" required maxlength="100"
              value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-md-6">
          <div class="form-group">
            <label for="phone" class="form-label">Company Phone</label>
            <input
              type="tel"
              class="form-control"
              id="phone"
              name="phone"
              required
              inputmode="numeric"
              pattern="[0-9]{7,15}"
              maxlength="15"
              value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"
              oninput="this.value=this.value.replace(/[^0-9]/g,'');"
              title="Digits only, 7–15 characters">
            <div class="form-hint">Digits only, 7–15 (e.g., 0912345678)</div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="form-group">
            <label for="address" class="form-label">Company Address</label>
            <input type="text" class="form-control" id="address" name="address" required maxlength="180"
              value="<?php echo htmlspecialchars($_POST['address'] ?? ''); ?>">
          </div>
        </div>
      </div>

      <div class="form-group">
        <label for="password" class="form-label">Password</label>
        <input
          type="password"
          class="form-control"
          id="password"
          name="password"
          required
          minlength="<?php echo NEW_PW_MIN_LEN; ?>"
          pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()\-\_\=\+\[\]\{\};:,.?~])(?!.*\s).{8,}"
          title="<?php echo htmlspecialchars(PW_POLICY_HUMAN); ?>">
        <div class="pw-strength" id="pw-strength">
          <ul>
            <li id="pw-length">At least 8 characters</li>
            <li id="pw-lower">1 lowercase letter</li>
            <li id="pw-upper">1 uppercase letter</li>
            <li id="pw-number">1 number</li>
            <li id="pw-special">1 special character</li>
            <li id="pw-space">No spaces</li>
          </ul>
        </div>
      </div>

      <div class="form-group">
        <label for="c_detail" class="form-label">Company Details</label>
        <textarea class="form-control" id="c_detail" name="c_detail" rows="3" maxlength="5000"
          placeholder="Brief company description, services, branches, etc."><?php echo htmlspecialchars($_POST['c_detail'] ?? ''); ?></textarea>
        <div class="form-hint">Brief company description, services, branches, etc.</div>
      </div>

      <div class="form-group">
        <label for="logo" class="form-label">Company Logo</label>
        <input type="file" class="form-control" id="logo" name="logo" required accept="image/png, image/jpeg">
        <div class="form-hint">Upload JPG or PNG only. Max size ~2MB.</div>
      </div>

      <button type="submit" class="btn-register">REGISTER COMPANY</button>

      <div class="login-link">
        Already have an account? <a href="login.php">Login</a>
      </div>
    </form>

    <a href="index.php" class="brand-logo">JobHive</a>
  </div>

  <script>
    // Password strength checker
    document.addEventListener('DOMContentLoaded', function() {
      const passwordInput = document.getElementById('password');
      const lengthCheck = document.getElementById('pw-length');
      const lowerCheck = document.getElementById('pw-lower');
      const upperCheck = document.getElementById('pw-upper');
      const numberCheck = document.getElementById('pw-number');
      const specialCheck = document.getElementById('pw-special');
      const spaceCheck = document.getElementById('pw-space');

      function checkPasswordStrength() {
        const password = passwordInput.value;
        lengthCheck.classList.toggle('valid', password.length >= 8);
        lowerCheck.classList.toggle('valid', /[a-z]/.test(password));
        upperCheck.classList.toggle('valid', /[A-Z]/.test(password));
        numberCheck.classList.toggle('valid', /\d/.test(password));
        specialCheck.classList.toggle('valid', /[!@#$%^&*()\-\_\=\+\[\]\{\};:,.?~]/.test(password));
        spaceCheck.classList.toggle('valid', !/\s/.test(password));
      }
      passwordInput.addEventListener('input', checkPasswordStrength);
      checkPasswordStrength();
    });
  </script>
</body>

</html>