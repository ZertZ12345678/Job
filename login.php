<?php
include("connect.php");
session_start();

/* ============================================================
   OTP toggle
   ============================================================ */
define('USE_LOGIN_OTP', false);
if (isset($_GET['otp'])) {
  if ($_GET['otp'] === '1') define('USE_LOGIN_OTP', true);
  if ($_GET['otp'] === '0') define('USE_LOGIN_OTP', false);
}

/* ============================================================
   PHPMailer (Composer)
   ============================================================ */
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

/* ============================================================
   SMTP settings 
   ============================================================ */

const SMTP_USERNAME  = 'phonethawnaing11305@gmail.com';
const SMTP_PASSWORD  = 'iuwdzyrnczhmdzyn';
const SMTP_FROM_NAME = 'JobHive';

/* Helpers */
function plusMinutesStr($m)
{
  return (new DateTime("+$m minutes"))->format('Y-m-d H:i:s');
}
function toEpoch(string $dt)
{
  return (new DateTime($dt))->getTimestamp();
}

/* ------------------------------------------------------------
   Mailer helper (tries TLS:587 then SSL:465)
------------------------------------------------------------ */
function sendOtpEmail(string $to, string $code, ?string &$err = null): bool
{
  $try = function (string $host, int $port, string $secure) use ($to, $code, &$err) {
    $mail = new PHPMailer(true);
    try {
      $mail->isSMTP();
      $mail->Host       = $host;
      $mail->SMTPAuth   = true;
      $mail->Username   = SMTP_USERNAME;
      $mail->Password   = SMTP_PASSWORD;
      $mail->CharSet    = 'UTF-8';
      if ($secure === 'ssl') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;
      } else {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
      }
      $mail->SMTPDebug   = SMTP::DEBUG_OFF;
      $mail->Debugoutput = 'html';

      $mail->setFrom(SMTP_USERNAME, SMTP_FROM_NAME);
      $mail->addAddress($to);
      $mail->isHTML(true);
      $mail->Subject = 'Your JobHive verification code';
      $mail->Body = "
        <div style='font-family:Arial,Helvetica,sans-serif;line-height:1.6;color:#111'>
          <h2 style='margin:0 0 8px;color:#ffaa2b'>Your Login Code</h2>
          <p>Use this code to continue:</p>
          <p style='font-size:22px;font-weight:bold;letter-spacing:4px;margin:12px 0 16px'>{$code}</p>
          <p>This code expires in <b>5 minutes</b>.</p>
        </div>";
      $mail->AltBody = "Your JobHive verification code is: {$code} (expires in 5 minutes).";
      $mail->send();
      return true;
    } catch (Exception $e) {
      $err = $mail->ErrorInfo;
      return false;
    }
  };
  if ($try('smtp.gmail.com', 587, 'tls')) return true;
  return $try('smtp.gmail.com', 465, 'ssl');
}

/* ------------------------------------------------------------
   Issue a new OTP for current actor/id and send it
------------------------------------------------------------ */
function issueAndSendOtp(string $actor, int $actorId, string $accountEmail, ?string &$err = null): bool
{
  global $pdo;
  $otp = (string)random_int(100000, 999999);
  $exp = plusMinutesStr(5);

  if ($actor === 'company') {
    $pdo->prepare("UPDATE companies SET otp_login_code=?, otp_login_expires=? WHERE company_id=?")
      ->execute([$otp, $exp, $actorId]);
  } else {
    $pdo->prepare("UPDATE users SET otp_login_code=?, otp_login_expires=? WHERE user_id=?")
      ->execute([$otp, $exp, $actorId]);
  }

  $ok = sendOtpEmail($accountEmail, $otp, $err);
  if ($ok) {
    $_SESSION['otp_last_sent']   = time();
    $_SESSION['otp_expires_at']  = toEpoch($exp);
  }
  return $ok;
}

/* ============================================================
   Controller state
   ============================================================ */
$login_message = '';
$login_detail  = '';
$alert_class   = '';
$stage         = $_POST['stage'] ?? 'password'; // 'password' | 'otp' | 'resend'

/* ============================================================
   Stage 1 — email+password → issue OTP (or login)
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $stage === 'password') {
  $postedEmail = trim($_POST['email'] ?? '');
  $password    = (string)($_POST['password'] ?? '');

  $authed = false;
  $actor  = null;       // 'user' | 'admin' | 'company'
  $actorId = null;
  $accountEmail = null;

  // Try users
  $st = $pdo->prepare("SELECT * FROM users WHERE email=? LIMIT 1");
  $st->execute([$postedEmail]);
  $user = $st->fetch(PDO::FETCH_ASSOC);
  if ($user && !empty($user['password']) && hash_equals($user['password'], $password)) {
    $authed       = true;
    $actor        = ($user['role'] === 'admin') ? 'admin' : 'user';
    $actorId      = (int)$user['user_id'];
    $accountEmail = $user['email'];
  }

  // Try companies
  if (!$authed) {
    $sc = $pdo->prepare("SELECT * FROM companies WHERE email=? LIMIT 1");
    $sc->execute([$postedEmail]);
    $company = $sc->fetch(PDO::FETCH_ASSOC);
    if ($company && !empty($company['password']) && hash_equals($company['password'], $password)) {
      $authed       = true;
      $actor        = 'company';
      $actorId      = (int)$company['company_id'];
      $accountEmail = $company['email'];
    }
  }

  if (!$authed) {
    $login_message = "Invalid email or password!";
    $alert_class   = "alert-danger custom-error";
  } else {
    if (USE_LOGIN_OTP) {
      $err = '';
      $sent = issueAndSendOtp($actor, $actorId, $accountEmail, $err);
      if (!$sent) {
        $login_message = "Couldn't send the code. Please check SMTP settings and try again.";
        $login_detail  = $err ? "SMTP Error: " . $err : '';
        $alert_class   = "alert-danger custom-error";
      } else {
        $_SESSION['otp_actor'] = $actor;
        $_SESSION['otp_id']    = $actorId;
        $_SESSION['otp_email'] = $accountEmail;
        $login_message = "We sent a 6-digit code to your email. Please enter it below.";
        $alert_class   = "alert-success custom-success";
        $stage         = 'otp';
      }
    } else {
      if ($actor === 'company') {
        $_SESSION['company_id'] = $actorId;
        $_SESSION['user_type'] = 'company';
        header("Location: company_home.php");
        exit;
      } else {
        $_SESSION['user_id'] = $actorId;
        $_SESSION['user_type'] = ($actor === 'admin') ? 'admin' : 'user';
        header("Location: " . ($_SESSION['user_type'] === 'admin' ? 'admin.php' : 'user_home.php'));
        exit;
      }
    }
  }
}

/* ============================================================
   Stage 2 — RESEND OTP (only after expiry)
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $stage === 'resend') {
  if (!USE_LOGIN_OTP) {
    header("Location: login.php");
    exit;
  }

  $actor   = $_SESSION['otp_actor'] ?? null;
  $actorId = $_SESSION['otp_id'] ?? null;
  $email   = $_SESSION['otp_email'] ?? null;
  $expires = $_SESSION['otp_expires_at'] ?? 0;

  if (!$actor || !$actorId || !$email) {
    $login_message = "Session expired. Please start over.";
    $alert_class   = "alert-danger custom-error";
    $stage         = 'password';
  } else {
    $now = time();
    if ($expires > $now) {
      $remaining = $expires - $now;
      $mm = str_pad(floor($remaining / 60), 2, '0', STR_PAD_LEFT);
      $ss = str_pad($remaining % 60, 2, '0', STR_PAD_LEFT);
      $login_message = "Your current code is still valid. Please wait {$mm}:{$ss} to request a new one.";
      $alert_class   = "alert-warning custom-warn";
      $stage         = 'otp';
    } else {
      $err = '';
      $sent = issueAndSendOtp($actor, (int)$actorId, $email, $err);
      if ($sent) {
        $login_message = "A new code has been sent to your email.";
        $alert_class   = "alert-success custom-success";
      } else {
        $login_message = "Couldn't resend the code. Please try again.";
        $login_detail  = $err ? "SMTP Error: " . $err : '';
        $alert_class   = "alert-danger custom-error";
      }
      $stage = 'otp';
    }
  }
}

/* ============================================================
   Stage 3 — VERIFY OTP
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $stage === 'otp') {
  if (!USE_LOGIN_OTP) {
    header("Location: login.php");
    exit;
  }

  $raw   = $_POST['otp'] ?? '';
  $code  = preg_replace('/\D/', '', $raw);

  if ($raw === '' || $code === '') {
    $login_message = "Please enter the 6-digit code.";
    $alert_class   = "alert-warning custom-warn";
    $stage         = 'otp';
  } elseif (!preg_match('/^\d{6}$/', $code)) {
    $login_message = "Code must be exactly 6 digits.";
    $alert_class   = "alert-warning custom-warn";
    $stage         = 'otp';
  } else {
    $actor   = $_SESSION['otp_actor'] ?? null;
    $actorId = $_SESSION['otp_id'] ?? null;

    if (!$actor || !$actorId) {
      $login_message = "Session expired. Please log in again.";
      $alert_class   = "alert-danger custom-error";
      $stage         = 'password';
    } else {
      if ($actor === 'company') {
        $st = $pdo->prepare("SELECT otp_login_code, otp_login_expires FROM companies WHERE company_id=?");
        $st->execute([$actorId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
      } else {
        $st = $pdo->prepare("SELECT role, otp_login_code, otp_login_expires FROM users WHERE user_id=?");
        $st->execute([$actorId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
      }

      $now     = new DateTime();
      $expires = isset($row['otp_login_expires']) ? new DateTime($row['otp_login_expires']) : null;
      if ($expires) $_SESSION['otp_expires_at'] = $expires->getTimestamp();

      $correct = $row && !empty($row['otp_login_code']) && hash_equals($row['otp_login_code'], $code);
      $fresh   = $expires && ($now <= $expires);

      if ($correct && $fresh) {
        if ($actor === 'company') {
          $pdo->prepare("UPDATE companies SET otp_login_code=NULL, otp_login_expires=NULL WHERE company_id=?")->execute([$actorId]);
          $_SESSION['company_id'] = $actorId;
          $_SESSION['user_type']  = 'company';
          $target = 'company_home.php';
        } else {
          $role = ($row['role'] ?? 'user');
          $pdo->prepare("UPDATE users SET otp_login_code=NULL, otp_login_expires=NULL WHERE user_id=?")->execute([$actorId]);
          $_SESSION['user_id']    = $actorId;
          $_SESSION['user_type']  = $role;
          $target = ($role === 'admin') ? 'admin.php' : 'user_home.php';
        }
        unset($_SESSION['otp_actor'], $_SESSION['otp_id'], $_SESSION['otp_email'], $_SESSION['otp_last_sent'], $_SESSION['otp_expires_at']);
        echo "<script>setTimeout(function(){ window.location.href = '{$target}'; }, 600);</script>";
        $login_message = "Login successful!";
        $alert_class   = "alert-success custom-success";
      } else {
        $login_message = "Invalid or expired code. Please try again.";
        $alert_class   = "alert-danger custom-error";
        $stage         = 'otp';
      }
    }
  }
}

/* For the client timer (OTP stage) */
$otpExpiresEpoch = $_SESSION['otp_expires_at'] ?? 0;
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
      max-width: 360px;
      margin: 24px auto 35px;
      background: #fff;
      padding: 1.3rem 1rem 1.6rem;
      border-radius: 1rem;
      box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06)
    }

    .login-title {
      color: #ffaa2b;
      font-weight: 600;
      letter-spacing: .7px;
      margin-bottom: 1.1rem;
      text-align: center;
      font-size: 1.25rem;
    }

    .form-label {
      font-size: .97rem;
      margin-bottom: .2rem;
    }

    .form-control {
      font-size: .93rem;
      padding: .33rem .75rem;
      border-radius: .5rem;
      min-height: 34px;
    }

    .form-control:focus {
      border-color: #ffaa2b;
      box-shadow: 0 0 0 .08rem rgba(255, 170, 43, .11)
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

    .brand-logo-text {
      font-size: 1.45rem;
      font-weight: bold;
      color: #ffaa2b;
      letter-spacing: 1px;
      display: inline-block;
      margin: .9rem 0;
      text-shadow: 0 1px 3px rgba(255, 170, 43, .08)
    }

    .alert-success.custom-success {
      background: #fff8ec;
      color: #ffaa2b;
      border: 1px solid #ffaa2b;
      font-weight: 500;
      border-radius: .75rem;
      font-size: 1rem;
    }

    .alert-danger.custom-error {
      background: #fbe8e6;
      color: #e25617;
      border: 1px solid #e25617;
      font-weight: 500;
      border-radius: .75rem;
      font-size: 1rem;
    }

    .alert-warning.custom-warn {
      background: #fff7e6;
      color: #9a6b00;
      border: 1px solid #ffcc80;
      font-weight: 500;
      border-radius: .75rem;
      font-size: 1rem;
    }

    .badge-mode {
      position: absolute;
      right: 12px;
      top: 12px;
      font-size: 11px;
    }

    .muted {
      color: #666;
      font-size: 12px;
    }

    .reg-buttons .btn {
      border-radius: .6rem;
    }
  </style>
</head>

<body data-otp-expires="<?= htmlspecialchars((string)$otpExpiresEpoch) ?>">
  <div class="text-center position-relative">
    <a href="index.php" class="text-decoration-none"><span class="brand-logo-text">JobHive</span></a>
    <span class="badge bg-secondary badge-mode">OTP: <?= USE_LOGIN_OTP ? 'ON' : 'OFF' ?></span>
  </div>

  <div class="login-container">
    <div class="login-title">Login</div>

    <?php if ($login_message): ?>
      <div class="alert <?= htmlspecialchars($alert_class) ?> text-center" role="alert">
        <?= htmlspecialchars($login_message) ?>
        <?php if ($login_detail): ?><br><small><?= htmlspecialchars($login_detail) ?></small><?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if ($stage === 'password' || !USE_LOGIN_OTP): ?>
      <form action="login.php" method="POST" autocomplete="off">
        <input type="hidden" name="stage" value="password" />
        <div class="mb-2">
          <label for="email" class="form-label">Email address</label>
          <input type="email" class="form-control" id="email" name="email" required maxlength="100" />
        </div>
        <div class="mb-2">
          <label for="password" class="form-label">Password</label>
          <input type="password" class="form-control" id="password" name="password" required minlength="6" />
        </div>
        <button type="submit" class="btn btn-warning w-100 py-2 mt-2">Continue</button>
        <a href="forgot_pw.php" class="small d-block text-center mt-2 text-decoration-none">Forgot password?</a>
      </form>

      <!-- New: two register buttons -->
      <div class="reg-buttons d-grid gap-2 mt-3">
        <a class="btn btn-outline-secondary w-100" href="sign_up.php">Register as User</a>
        <a class="btn btn-outline-secondary w-100" href="c_sign_up.php">Register as Company</a>
      </div>

    <?php else: /* OTP stage */ ?>
      <form action="login.php" method="POST" autocomplete="off" class="mb-2">
        <input type="hidden" name="stage" value="otp" />
        <div class="mb-2">
          <label for="otp" class="form-label">Enter 6-digit code</label>
          <input type="text" pattern="\d{6}" maxlength="6" class="form-control" id="otp" name="otp" required />
        </div>
        <div id="otp-timer" class="muted mb-2"></div>
        <button type="submit" class="btn btn-warning w-100 py-2 mt-2">Verify &amp; Sign In</button>
      </form>

      <form action="login.php" method="POST" class="text-center">
        <input type="hidden" name="stage" value="resend" />
        <button id="btnResend" type="submit" class="btn btn-outline-secondary w-100 py-2" disabled>Send OTP</button>
        <div class="small text-muted mt-1">We’ll send a new code when the timer hits 0:00.</div>
      </form>

      <!-- New: two register buttons also visible in OTP stage -->
      <div class="reg-buttons d-grid gap-2 mt-3">
        <a class="btn btn-outline-secondary w-100" href="sign_up.php">Register as User</a>
        <a class="btn btn-outline-secondary w-100" href="c_signup.php">Register as Company</a>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($stage === 'otp'): ?>
    <script>
      (function() {
        var expiresEpoch = parseInt(document.body.dataset.otpExpires || "0", 10);
        var elTimer = document.getElementById('otp-timer');
        var btnResend = document.getElementById('btnResend');
        if (!expiresEpoch || !elTimer || !btnResend) return;

        function fmt(ms) {
          var s = Math.max(0, Math.floor(ms / 1000));
          var mm = String(Math.floor(s / 60)).padStart(2, '0');
          var ss = String(s % 60).padStart(2, '0');
          return mm + ':' + ss;
        }

        function tick() {
          var msLeft = (expiresEpoch * 1000) - Date.now();
          if (msLeft > 0) {
            elTimer.textContent = 'Code expires in ' + fmt(msLeft);
            btnResend.disabled = true;
            setTimeout(tick, 250);
          } else {
            elTimer.textContent = 'Code expired. You can request a new one.';
            btnResend.disabled = false;
          }
        }
        tick();
      })();
    </script>
  <?php endif; ?>
</body>

</html>