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
    :root {
      --gold: #22223b;
      /* swapped */
      --ink: #ffc107;
      /* swapped */
      --muted: #f8fafc;
    }

    html,
    body {
      height: 100%;
    }

    body {
      margin: 0;
      font-size: 14px;
      background: var(--muted);
      color: #2b2b2b;
    }

    /* ==== Shell with diagonal split ==== */
    .auth-shell {
      position: relative;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
      padding: 24px;
    }

    .auth-back {
      position: absolute;
      inset: 40px 24px;
      background: var(--gold);
      /* now dark ink */
      border-radius: 18px;
      box-shadow: 0 18px 60px rgba(0, 0, 0, .18);
    }

    .auth-ink {
      position: absolute;
      inset: 40px 24px;
      background: var(--ink);
      /* now gold */
      border-radius: 18px;
      box-shadow: 0 8px 40px rgba(0, 0, 0, .22);
      clip-path: polygon(40% 0, 100% 0, 100% 100%, 30% 100%);
    }

    /* ==== Content grid (logo | form) ==== */
    .auth-grid {
      position: relative;
      z-index: 2;
      width: 100%;
      max-width: 1100px;
      min-height: 540px;
      display: grid;
      grid-template-columns: 1.1fr 1fr;
      gap: 0;
    }

    .auth-left {
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px;
    }

    .brand-card {
      width: 100%;
      max-width: 430px;
      background: linear-gradient(135deg, #ffc107 50%, #22223b 50%, );
      border-radius: 18px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, .08);
      padding: 40px 28px;
      text-align: center;
      color: #fff;
    }


    .brand-card img {
      width: 220px;
      height: auto;
      object-fit: contain;
      display: block;
      margin: 6px auto 10px;
    }

    .brand-title {
      margin-top: 6px;
      font-weight: 800;
      font-size: 1.4rem;
      letter-spacing: .4px;
      color: #ffc107;
      /* swapped */
    }

    .brand-sub {
      font-size: .9rem;
      color: #f8eec2;
    }

    /* ==== Form card on gold side ==== */
    .auth-right {
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px;
    }

    .form-card {
      width: 100%;
      max-width: 420px;
      background: #ffc107;
      /* swapped */
      border: 1px solid #e6b800;
      border-radius: 16px;
      padding: 26px 22px;
      color: #22223b;
      /* swapped */
      box-shadow: 0 8px 24px rgba(0, 0, 0, .25);
    }

    .form-card h2 {
      font-weight: 700;
      font-size: 1.1rem;
      letter-spacing: .4px;
      margin: 6px 2px 14px;
      color: #22223b;
      /* swapped */
    }

    .form-label {
      color: #22223b;
      /* swapped */
      margin-bottom: .25rem;
      font-size: .94rem;
    }

    .form-control {
      background: #fff;
      border: 1px solid transparent;
      color: #22223b;
      padding: .5rem .75rem;
      height: 40px;
      border-radius: 10px;
      font-size: .95rem;
    }

    .form-control:focus {
      border-color: var(--ink);
      box-shadow: 0 0 0 .2rem rgba(255, 193, 7, .25);
    }

    .btn-gold {
      background: var(--ink);
      /* now gold */
      border: none;
      border-radius: 10px;
      height: 42px;
      font-weight: 700;
      letter-spacing: .4px;
      color: #22223b;
      /* dark text */
    }

    .btn-gold:hover {
      background: #e0a800;
      color: #22223b;
    }

    .small-link {
      color: #22223b;
      text-decoration: none;
      display: block;
      text-align: center;
      margin-top: .8rem;
    }

    .small-link .hl {
      color: var(--ink);
    }

    /* Alerts */
    .alert-success.custom-success {
      background: #fff8e6;
      color: #22223b;
      border: 1px solid var(--ink);
      font-weight: 600;
      border-radius: .9rem;
      font-size: .98rem;
    }

    .alert-danger.custom-error {
      background: #fdecea;
      color: #b02a1e;
      border: 1px solid #f5c2c7;
      font-weight: 600;
      border-radius: .9rem;
      font-size: .98rem;
    }

    /* Password helper */
    .form-text {
      color: #22223b;
      opacity: .85;
      font-size: .82rem;
    }

    #pw-req li {
      color: #22223b;
    }

    /* === Responsive === */
    @media (max-width: 992px) {
      .auth-grid {
        grid-template-columns: 1fr;
        min-height: auto;
      }

      .auth-ink {
        clip-path: polygon(0 0, 100% 0, 100% 100%, 0 100%);
      }

      .brand-card {
        background: #22223b;
        color: #ffc107;
      }
    }
  </style>
</head>

<body>
  <div class="auth-shell">
    <div class="auth-back"></div>
    <div class="auth-ink"></div>

    <div class="auth-grid">
      <!-- LEFT: Logo / brand -->
      <div class="auth-left">
        <div class="brand-card">
          <!-- Replace src with your final logo path when you send it -->
          <img src="jobhive_logo\jobhive.png" alt="JobHive Logo">
          <div class="brand-sub">Find work. Post jobs. Hire faster.</div>
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

            <button type="submit" class="btn btn-gold w-100 mt-2">REGISTER</button>
            <a href="login.php" class="small-link">Already have an account? <span class="hl">Login</span></a>
          </form>

          <!-- Live password checklist -->
          <div class="form-text mt-2" id="pw-helper-wrap"></div>
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
      <ul id="pw-req" style="margin:.25rem 0 0 1rem; padding:0; list-style:square;">
        <li data-k="len">≥ 8 characters</li>
        <li data-k="low">1 lowercase</li>
        <li data-k="upp">1 uppercase</li>
        <li data-k="dig">1 digit</li>
        <li data-k="spe">1 special (!@#$%^&*() -_=+[]{};:,.?~)</li>
        <li data-k="spc">no spaces</li>
        <li data-k="rep">no 3 repeated chars (e.g., aaa)</li>
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