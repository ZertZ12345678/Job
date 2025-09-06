<?php
include("connect.php");
if (session_status() === PHP_SESSION_NONE) session_start();

$message = "";
$register_success = false;

/* ================== Strong Password Policy ================== */
const NEW_PW_MIN_LEN   = 8;
const PW_POLICY_REGEX  = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()\-\_\=\+\[\]\{\};:,.?~])(?!.*\s).{8,}$/';
const PW_POLICY_HUMAN  = "At least 8 chars, with 1 uppercase, 1 lowercase, 1 number, and 1 special (!@#\$%^&*() -_=+[]{};:,.?~). No spaces.";
const PW_BLOCKLIST     = ['password', 'Password1', 'Passw0rd', '12345678', 'qwerty123', 'letmein', 'admin123', 'jobhive123'];

function isStrongPassword(string $pw): array
{
  if (strlen($pw) < NEW_PW_MIN_LEN) return [false, "Password must be at least " . NEW_PW_MIN_LEN . " characters."];
  if (preg_match('/\s/', $pw)) return [false, "Password cannot contain spaces."];
  foreach (PW_BLOCKLIST as $bad) if (strcasecmp($pw, $bad) === 0) return [false, "That password is too common."];
  if (!preg_match(PW_POLICY_REGEX, $pw)) return [false, PW_POLICY_HUMAN];
  if (preg_match('/(.)\1\1/', $pw)) return [false, "Avoid repeating any character 3+ times."];
  return [true, ""];
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $full_name = trim($_POST['fullname'] ?? '');
  $email     = strtolower(trim($_POST['email'] ?? ''));
  $password  = $_POST['password'] ?? '';   // plain text
  $phone_raw = trim($_POST['phno'] ?? '');

  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $message = "<div class='alert alert-danger text-center'>Invalid email format.</div>";
  }

  if ($message === "") {
    if ($phone_raw === "") {
      $message = "<div class='alert alert-danger text-center'>Phone is required.</div>";
    } elseif (!ctype_digit($phone_raw)) {
      $message = "<div class='alert alert-danger text-center'>Phone must contain digits only (0–9).</div>";
    } elseif (strlen($phone_raw) < 7 || strlen($phone_raw) > 15) {
      $message = "<div class='alert alert-danger text-center'>Phone must be 7–15 digits.</div>";
    }
  }
  $phone = $phone_raw;

  if ($message === "") {
    [$okStrong, $why] = isStrongPassword($password);
    if (!$okStrong) $message = "<div class='alert alert-danger text-center'>Weak password: " . htmlspecialchars($why) . "</div>";
  }

  if ($message === "") {
    $stmt = $pdo->prepare("SELECT user_id,email,phone FROM users WHERE email=? OR phone=? LIMIT 1");
    $stmt->execute([$email, $phone]);
    $exists = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($exists) {
      if (isset($exists['email']) && strcasecmp($exists['email'], $email) === 0) {
        $message = "<div class='alert alert-danger text-center'>This email is already registered.</div>";
      } else {
        $message = "<div class='alert alert-danger text-center'>This phone number is already registered.</div>";
      }
    }
  }

  if ($message === "") {
    $stmt = $pdo->prepare("INSERT INTO users (full_name,email,password,phone,role) VALUES (?,?,?,?,?)");
    $stmt->execute([$full_name, $email, $password, $phone, 'user']);
    $newId = (int)$pdo->lastInsertId();

    $_SESSION['user_id'] = $newId;
    $_SESSION['full_name'] = $full_name;
    $_SESSION['email'] = $email;
    $_SESSION['role'] = 'user';

    $message = "<div class='alert alert-success text-center'>Registration Successfully and Go to Home Page</div>";
    $register_success = true;
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>JobHive | User SignUp</title>
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
      max-width: 560px;
      background: var(--ink);
      border-radius: 12px;
      padding: 18px 22px;
      margin: 20px 0;
      box-shadow: 0 6px 14px rgba(0, 0, 0, .2);
    }

    .form-title {
      color: var(--gold);
      font-size: 22px;
      font-weight: 700;
      text-align: center;
      margin-bottom: 14px;
    }

    .form-label {
      color: var(--gold);
      font-weight: 600;
      font-size: 13px;
      margin-bottom: 4px;
    }

    .form-control {
      background: #fff;
      border: none;
      border-radius: 6px;
      height: 36px;
      padding: 6px 10px;
      font-size: 14px;
    }

    .form-control:focus {
      box-shadow: 0 0 0 2px rgba(255, 193, 7, .3);
      outline: none;
    }

    .form-hint {
      color: #d9c36a;
      font-size: 11px;
      margin-top: 3px;
    }

    .btn-register {
      background: var(--gold);
      color: var(--ink);
      border: none;
      border-radius: 6px;
      height: 40px;
      font-weight: 600;
      font-size: 14px;
      margin-top: 6px;
      transition: all .3s ease;
      width: 100%;
    }

    .btn-register:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 12px rgba(255, 193, 7, .3);
    }

    .login-link {
      color: #ffe08a;
      text-align: center;
      margin-top: 10px;
      font-size: 12px;
    }

    .login-link a {
      color: #fff;
      font-weight: 700;
      text-decoration: none;
    }

    .login-link a:hover {
      text-decoration: underline;
    }

    .alert {
      border-radius: 6px;
      padding: 10px;
      margin-bottom: 14px;
      font-size: 13px;
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
      margin-top: 6px;
      font-size: 11px;
    }

    .pw-strength ul {
      list-style: none;
      padding: 0;
      margin: 0;
      color: #d9c36a;
    }

    .pw-strength li {
      margin-bottom: 3px;
      padding-left: 16px;
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
      font-size: 16px;
      font-weight: 700;
      text-decoration: none;
      text-align: center;
      display: block;
      margin-top: 12px;
    }

    .brand-logo:hover {
      text-decoration: underline;
    }
  </style>
</head>

<body>
  <div class="signup-container">
    <h1 class="form-title">Create your JobHive account</h1>

    <?php
    if ($message) {
      echo $message;
      if ($register_success) {
        echo "<script>setTimeout(function(){ window.location.href = 'user_home.php'; }, 3000);</script>";
      }
    }
    ?>

    <form method="POST" autocomplete="off" novalidate>
      <div class="row">
        <div class="col-md-6">
          <div class="mb-3">
            <label for="fullname" class="form-label">Full Name</label>
            <input type="text" class="form-control" id="fullname" name="fullname" required maxlength="80"
              value="<?php echo htmlspecialchars($_POST['fullname'] ?? ''); ?>">
          </div>
        </div>
        <div class="col-md-6">
          <div class="mb-3">
            <label for="email" class="form-label">Your Email</label>
            <input type="email" class="form-control" id="email" name="email" required maxlength="100"
              value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-md-6">
          <div class="mb-3">
            <label for="phno" class="form-label">Phone Number</label>
            <input type="tel" class="form-control" id="phno" name="phno" required pattern="[0-9]{7,15}" maxlength="15"
              value="<?php echo htmlspecialchars($_POST['phno'] ?? ''); ?>"
              oninput="this.value=this.value.replace(/[^0-9]/g,'');">
            <div class="form-hint">Digits only, 7–15 (e.g., 0912345678)</div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <input type="password" class="form-control" id="password" name="password"
              required minlength="<?php echo NEW_PW_MIN_LEN; ?>"
              pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()\-\_\=\+\[\]\{\};:,.?~])(?!.*\s).{8,}"
              title="<?php echo htmlspecialchars(PW_POLICY_HUMAN); ?>">
          </div>
        </div>
      </div>

      <div class="pw-strength" id="pw-strength">
        <ul>
          <li id="pw-length">At least 8 characters</li>
          <li id="pw-lower">1 lowercase letter</li>
          <li id="pw-upper">1 uppercase letter</li>
          <li id="pw-number">1 number</li>
          <li id="pw-special">1 special character</li>
          <li id="pw-space">No spaces</li>
          <li id="pw-repeat">No 3+ repeats</li>
        </ul>
      </div>

      <button type="submit" class="btn-register">REGISTER</button>

      <div class="login-link">
        Already have an account? <a href="login.php">Login</a>
      </div>
    </form>

    <a href="index.php" class="brand-logo">JobHive</a>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const pw = document.getElementById('password');
      const checks = {
        length: document.getElementById('pw-length'),
        lower: document.getElementById('pw-lower'),
        upper: document.getElementById('pw-upper'),
        number: document.getElementById('pw-number'),
        special: document.getElementById('pw-special'),
        space: document.getElementById('pw-space'),
        repeat: document.getElementById('pw-repeat')
      };

      function setValid(el, ok) {
        el.classList.toggle('valid', ok);
      }

      function check() {
        const v = pw.value || '';
        setValid(checks.length, v.length >= 8);
        setValid(checks.lower, /[a-z]/.test(v));
        setValid(checks.upper, /[A-Z]/.test(v));
        setValid(checks.number, /\d/.test(v));
        setValid(checks.special, /[\!\@\#\$\%\^\&\*\(\)\-\_\=\+\[\]\{\};:,.?~]/.test(v));
        setValid(checks.space, !/\s/.test(v));
        setValid(checks.repeat, !/(.)\1\1/.test(v));
      }
      pw.addEventListener('input', check);
      check();
    });
  </script>
</body>

</html>