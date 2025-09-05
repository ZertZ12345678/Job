<?php
include("connect.php");
session_start(); // <<— make sure session is started
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
  if (strlen($pw) < NEW_PW_MIN_LEN) {
    return [false, "Password must be at least " . NEW_PW_MIN_LEN . " characters."];
  }
  if (preg_match('/\s/', $pw)) {
    return [false, "Password cannot contain spaces."];
  }
  foreach (PW_BLOCKLIST as $bad) {
    if (strcasecmp($pw, $bad) === 0) {
      return [false, "That password is too common. Please choose another."];
    }
  }
  if (!preg_match(PW_POLICY_REGEX, $pw)) {
    return [false, PW_POLICY_HUMAN];
  }
  if (preg_match('/(.)\1\1/', $pw)) {
    return [false, "Avoid repeating any character 3+ times in a row."];
  }
  return [true, ""];
}
try {
  if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Basic sanitization
    $full_name = trim($_POST['fullname'] ?? '');
    $email     = strtolower(trim($_POST['email'] ?? ''));
    $password  = $_POST['password'] ?? '';
    $phone     = trim($_POST['phno'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      throw new Exception("Invalid email format.");
    }
    [$okStrong, $why] = isStrongPassword($password);
    if (!$okStrong) {
      throw new Exception("Weak password: " . $why);
    }
    $check_sql = "SELECT user_id, email, phone FROM users WHERE email = ? OR phone = ?";
    $stmt = $pdo->prepare($check_sql);
    $stmt->execute([$email, $phone]);
    $exists = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($exists) {
      if (isset($exists['email']) && strcasecmp($exists['email'], $email) === 0) {
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
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>JobHive | User SignUp</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <style>
    /* --- Smaller form and brand card within same container --- */
    :root {
      --gold: #22223b;
      --ink: #ffc107;
      --muted: #f8fafc;
      /* Fixed container dimensions - NOT CHANGING */
      --layout-width: 1550px;
      --layout-height: 800px;
      --white-space: 15px;
      /* Smaller font sizes */
      --font-size-title: 1.3rem;
      --font-size-label: 0.95rem;
      --font-size-input: 0.95rem;
      --font-size-button: 1rem;
      --font-size-link: 0.95rem;
      --font-size-helper: 0.8rem;
      /* Smaller spacing */
      --spacing-title: 15px;
      --spacing-input: 10px;
      --spacing-button: 12px;
      --spacing-link: 10px;
      /* Added red color for errors */
      --danger-red: #e74c3c;
      --danger-dark: #c0392b;
      --danger-light: #fadbd8;
    }

    html,
    body {
      height: 100%;
      margin: 0;
      padding: 0;
      font-size: 14px;
      /* Changed to gradient background matching gold and ink colors */
      background: linear-gradient(135deg, var(--gold), var(--ink));
      color: #2b2b2b;
      overflow: hidden;
    }

    /* Container that centers the fixed layout */
    .auth-viewport {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
      /* Removed background to show gradient through */
      padding: var(--white-space);
      box-sizing: border-box;
    }

    /* Fixed size container for the entire layout - NOT CHANGING */
    .auth-container {
      position: relative;
      width: var(--layout-width);
      height: var(--layout-height);
      overflow: hidden;
      border-radius: 18px;
      box-shadow: 0 18px 60px rgba(0, 0, 0, .18);
    }

    /* Background layers */
    .auth-back {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: var(--gold);
      border-radius: 18px;
    }

    .auth-ink {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: var(--ink);
      border-radius: 18px;
      clip-path: polygon(27% 0, 100% 0, 100% 100%, 17% 100%);
    }

    /* Content grid */
    .auth-grid {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      display: grid;
      grid-template-columns: 1fr 1.8fr;
      gap: 0;
      z-index: 2;
    }

    /* Left side - Logo/brand card - SMALLER */
    .auth-left {
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }

    .brand-card {
      position: relative;
      width: 100%;
      left: 48px;
      /* Shifted right */
      max-width: 210px;
      /* Reduced from 300px */
      background: #ffc107;
      border-radius: 14px;
      box-shadow: 0 6px 18px rgba(0, 0, 0, .15);
      padding: 15px 12px;
      /* Reduced padding */
      text-align: center;
      color: #fff;
      overflow: hidden;
    }

    .brand-card::after {
      content: "";
      position: absolute;
      inset: 0;
      background: #22223b;
      clip-path: polygon(63% 0, 100% 0, 100% 100%, 49% 100%);
      border-radius: inherit;
      pointer-events: none;
    }

    .brand-card>* {
      position: relative;
      z-index: 1;
    }

    .brand-card img {
      width: 120px;
      /* Reduced from 160px */
      left: 16px;
      height: auto;
      object-fit: contain;
      display: block;
      margin: 6px auto 8px;
      /* Reduced margins */
    }

    .brand-title {
      margin-top: 6px;
      font-weight: 700;
      font-size: 1rem;
      /* Reduced from 1.2rem */
      letter-spacing: .3px;
      color: #fff;
    }

    .brand-sub {
      font-size: 0.75rem;
      /* Reduced from 0.85rem */
      color: #eee;
      margin-top: 3px;
    }

    /* Right side - Form - SMALLER */
    .auth-right {
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }

    .form-card {
      width: 100%;
      max-width: 500px;
      background: var(--gold);
      border: 1px solid var(--ink);
      border-radius: 16px;
      padding: 20px 15px;
      color: var(--ink);
      box-shadow: 0 8px 24px rgba(0, 0, 0, .25);
    }

    .form-card h2 {
      font-weight: 700;
      font-size: var(--font-size-title);
      letter-spacing: .4px;
      margin: 0 0 var(--spacing-title);
      color: var(--ink);
      text-align: center;
    }

    .form-label {
      color: var(--ink);
      margin-bottom: 4px;
      font-size: var(--font-size-label);
      font-weight: 600;
    }

    .form-control {
      background: #fff;
      border: 1px solid transparent;
      color: var(--gold);
      padding: 8px 10px;
      height: 38px;
      border-radius: 10px;
      font-size: var(--font-size-input);
    }

    .form-control:focus {
      border-color: var(--ink);
      box-shadow: 0 0 0 .2rem rgba(255, 193, 7, .25);
    }

    .btn-gold {
      background: var(--ink);
      border: none;
      border-radius: 10px;
      height: 40px;
      font-weight: 700;
      letter-spacing: .4px;
      color: var(--gold);
      font-size: var(--font-size-button);
      margin-top: var(--spacing-button);
    }

    .btn-gold:hover {
      background: #e0a800;
      color: var(--gold);
    }

    .small-link {
      color: var(--ink);
      text-decoration: none;
      display: block;
      text-align: center;
      margin-top: var(--spacing-link);
      font-size: var(--font-size-link);
    }

    .small-link .hl {
      color: var(--ink);
      font-weight: 600;
    }

    /* Alerts */
    .alert-success.custom-success {
      background: #fff8e6;
      color: var(--gold);
      border: 1px solid var(--ink);
      font-weight: 600;
      border-radius: .9rem;
      font-size: var(--font-size-input);
    }

    .alert-danger.custom-error {
      background: var(--danger-red);
      /* Changed to strong red */
      color: white;
      /* Changed to white for contrast */
      border: 1px solid var(--danger-dark);
      /* Darker red border */
      font-weight: 600;
      border-radius: .9rem;
      font-size: var(--font-size-input);
      box-shadow: 0 4px 8px rgba(231, 76, 60, 0.3);
      /* Added subtle red shadow */
    }

    /* Password helper */
    .form-text {
      color: var(--ink);
      opacity: .85;
      font-size: var(--font-size-helper);
    }

    #pw-req {
      margin: 6px 0 0 14px;
      padding: 0;
      list-style: square;
    }

    #pw-req li {
      color: var(--ink);
      font-size: var(--font-size-helper);
      margin-bottom: 1px;
    }

    /* Responsive adjustments */
    @media (max-width: 1400px) {
      :root {
        --layout-width: 1100px;
        --layout-height: 650px;
      }

      .brand-card {
        max-width: 200px;
        padding: 12px 10px;
      }

      .brand-card img {
        width: 100px;
      }

      .form-card {
        max-width: 450px;
        padding: 18px 12px;
      }
    }

    @media (max-width: 1200px) {
      :root {
        --layout-width: 900px;
        --layout-height: 600px;
      }

      .auth-grid {
        grid-template-columns: 1fr;
      }

      .auth-ink {
        clip-path: polygon(0 0, 100% 0, 100% 100%, 0 100%);
      }

      .brand-card {
        background: #22223b;
        color: #ffc107;
        max-width: 180px;
        padding: 10px 8px;
      }

      .brand-card img {
        width: 80px;
      }

      .form-card {
        max-width: 400px;
        padding: 15px 10px;
      }
    }

    @media (max-width: 768px) {
      :root {
        --layout-width: 95%;
        --layout-height: auto;
        max-height: 90vh;
        --white-space: 10px;
        --font-size-title: 1.2rem;
        --font-size-label: 0.9rem;
        --font-size-input: 0.9rem;
        --font-size-button: 0.95rem;
        --font-size-link: 0.9rem;
        --font-size-helper: 0.75rem;
        --spacing-title: 12px;
        --spacing-input: 8px;
        --spacing-button: 10px;
        --spacing-link: 8px;
      }

      .auth-container {
        overflow-y: auto;
        height: auto;
      }

      .brand-card {
        max-width: 160px;
        padding: 10px 8px;
      }

      .brand-card img {
        width: 70px;
      }

      .form-card {
        max-width: 100%;
        padding: 15px 10px;
      }

      .form-control {
        height: 36px;
      }

      .btn-gold {
        height: 38px;
      }
    }
  </style>
</head>

<body>
  <div class="auth-viewport">
    <div class="auth-container">
      <div class="auth-back"></div>
      <div class="auth-ink"></div>
      <div class="auth-grid">
        <!-- LEFT: Logo / brand -->
        <div class="auth-left">
          <div class="brand-card">
            <!-- Replace src with your final logo path when you send it -->
            <img src="jobhive_logo\jobhive.png" alt="JobHive Logo">
          </div>
        </div>
        <!-- RIGHT: Sign up form -->
        <div class="auth-right">
          <div class="form-card">
            <h2>Create your JobHive account</h2>
            <?php
            if ($message) {
              echo $message;
              if ($register_success) {
                echo "<script>
                  setTimeout(function(){ window.location.href = 'user_home.php'; }, 3000);
                </script>";
              }
            }
            ?>
            <form method="POST" autocomplete="off" novalidate>
              <div class="mb-2">
                <label for="fullname" class="form-label">Full Name</label>
                <input type="text" class="form-control" id="fullname" name="fullname" required maxlength="80"
                  value="<?php echo htmlspecialchars($_POST['fullname'] ?? ''); ?>">
              </div>
              <div class="mb-2">
                <label for="email" class="form-label">Your email</label>
                <input type="email" class="form-control" id="email" name="email" required maxlength="100"
                  value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
              </div>
              <div class="mb-2">
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
                <div class="form-text mt-1">
                  <?php echo htmlspecialchars(PW_POLICY_HUMAN); ?>
                </div>
              </div>
              <div class="mb-2">
                <label for="phno" class="form-label">Phone Number</label>
                <input type="tel" class="form-control" id="phno" name="phno" required pattern="[0-9]{7,15}" maxlength="15"
                  value="<?php echo htmlspecialchars($_POST['phno'] ?? ''); ?>">
                <div class="form-text">Enter only digits, e.g. 0912345678</div>
              </div>
              <button type="submit" class="btn btn-gold w-100">REGISTER</button>
              <a href="login.php" class="small-link">Already have an account? <span class="hl">Login</span></a>
            </form>
            <!-- Live password checklist -->
            <div class="form-text mt-1" id="pw-helper-wrap"></div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <script>
    (function() {
      var pw = document.getElementById('password');
      if (!pw) return;
      var wrap = document.getElementById('pw-helper-wrap');
      wrap.innerHTML = `
      <ul id="pw-req">
        <li data-k="len">≥ 8 characters</li>
        <li data-k="low">1 lowercase</li>
        <li data-k="upp">1 uppercase</li>
        <li data-k="dig">1 digit</li>
        <li data-k="spe">1 special (!@#$%^&*() -_=+[]{};:,.?~)</li>
        <li data-k="spc">no spaces</li>
        <li data-k="rep">no 3 repeated chars</li>
      </ul>`;
      var li = {};
      ['len', 'low', 'upp', 'dig', 'spe', 'spc', 'rep'].forEach(function(k) {
        li[k] = wrap.querySelector('[data-k="' + k + '"]');
      });

      function setOK(el, ok) {
        el.style.color = ok ? '#9fe870' : '#cfd3ea';
        el.style.fontWeight = ok ? '700' : '400';
      }

      function check() {
        var v = pw.value || '';
        var okLen = v.length >= 8;
        var okLow = /[a-z]/.test(v);
        var okUpp = /[A-Z]/.test(v);
        var okDig = /\d/.test(v);
        var okSpe = /[!@#$%^&*()\-\_\=\+\[\]\{\};:,.?~]/.test(v);
        var okSpc = !/\s/.test(v);
        var okRep = !/(.)\1\1/.test(v);
        setOK(li.len, okLen);
        setOK(li.low, okLow);
        setOK(li.upp, okUpp);
        setOK(li.dig, okDig);
        setOK(li.spe, okSpe);
        setOK(li.spc, okSpc);
        setOK(li.rep, okRep);
      }
      pw.addEventListener('input', check);
      check();
    })();
  </script>
</body>

</html>