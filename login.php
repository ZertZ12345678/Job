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
   Mailer helper
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
      $mail->setFrom(SMTP_USERNAME, SMTP_FROM_NAME);
      $mail->addAddress($to);
      $mail->isHTML(true);
      $mail->Subject = 'Your JobHive verification code';
      $mail->Body = "<h2 style='color:#ffc107'>Your Login Code</h2>
        <p>Use this code to continue:</p>
        <p style='font-size:22px;font-weight:bold;letter-spacing:4px'>{$code}</p>
        <p>This code expires in <b>5 minutes</b>.</p>";
      $mail->AltBody = "Your JobHive code is {$code} (expires in 5 minutes).";
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
   Issue OTP
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
   Controller
   ============================================================ */
$login_message = '';
$login_detail  = '';
$alert_class   = '';
$stage         = $_POST['stage'] ?? 'password';

/* Stage 1 — password */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $stage === 'password') {
  $postedEmail = trim($_POST['email'] ?? '');
  $password    = (string)($_POST['password'] ?? '');
  $authed = false;
  $actor = null;
  $actorId = null;
  $accountEmail = null;

  $st = $pdo->prepare("SELECT * FROM users WHERE email=? LIMIT 1");
  $st->execute([$postedEmail]);
  $user = $st->fetch(PDO::FETCH_ASSOC);
  if ($user && hash_equals($user['password'], $password)) {
    $authed = true;
    $actor = ($user['role'] === 'admin') ? 'admin' : 'user';
    $actorId = (int)$user['user_id'];
    $accountEmail = $user['email'];
  }
  if (!$authed) {
    $sc = $pdo->prepare("SELECT * FROM companies WHERE email=? LIMIT 1");
    $sc->execute([$postedEmail]);
    $company = $sc->fetch(PDO::FETCH_ASSOC);
    if ($company && hash_equals($company['password'], $password)) {
      $authed = true;
      $actor = 'company';
      $actorId = (int)$company['company_id'];
      $accountEmail = $company['email'];
    }
  }
  if (!$authed) {
    $login_message = "Invalid email or password!";
    $alert_class = "alert-danger custom-error";
  } else {
    if (USE_LOGIN_OTP) {
      $err = '';
      $sent = issueAndSendOtp($actor, $actorId, $accountEmail, $err);
      if (!$sent) {
        $login_message = "Couldn't send the code.";
        $login_detail = $err;
        $alert_class = "alert-danger custom-error";
      } else {
        $_SESSION['otp_actor'] = $actor;
        $_SESSION['otp_id'] = $actorId;
        $_SESSION['otp_email'] = $accountEmail;
        $login_message = "We sent a 6-digit code to your email.";
        $alert_class = "alert-success custom-success";
        $stage = 'otp';
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
/* Stage 2 — resend */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $stage === 'resend') {
  if (!USE_LOGIN_OTP) {
    header("Location: login.php");
    exit;
  }
  $actor = $_SESSION['otp_actor'] ?? null;
  $actorId = $_SESSION['otp_id'] ?? null;
  $email = $_SESSION['otp_email'] ?? null;
  $expires = $_SESSION['otp_expires_at'] ?? 0;
  if (!$actor || !$actorId || !$email) {
    $login_message = "Session expired.";
    $alert_class = "alert-danger custom-error";
    $stage = 'password';
  } else {
    $now = time();
    if ($expires > $now) {
      $remaining = $expires - $now;
      $mm = str_pad(floor($remaining / 60), 2, '0', STR_PAD_LEFT);
      $ss = str_pad($remaining % 60, 2, '0', STR_PAD_LEFT);
      $login_message = "Your current code is still valid. Wait {$mm}:{$ss}.";
      $alert_class = "alert-warning custom-warn";
      $stage = 'otp';
    } else {
      $err = '';
      $sent = issueAndSendOtp($actor, (int)$actorId, $email, $err);
      $login_message = $sent ? "A new code has been sent." : "Couldn't resend.";
      $login_detail = $err;
      $alert_class = $sent ? "alert-success custom-success" : "alert-danger custom-error";
      $stage = 'otp';
    }
  }
}
/* Stage 3 — verify OTP */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $stage === 'otp') {
  if (!USE_LOGIN_OTP) {
    header("Location: login.php");
    exit;
  }
  $raw = $_POST['otp'] ?? '';
  $code = preg_replace('/\D/', '', $raw);
  if ($raw === '' || $code === '') {
    $login_message = "Please enter the 6-digit code.";
    $alert_class = "alert-warning custom-warn";
    $stage = 'otp';
  } elseif (!preg_match('/^\d{6}$/', $code)) {
    $login_message = "Code must be 6 digits.";
    $alert_class = "alert-warning custom-warn";
    $stage = 'otp';
  } else {
    $actor = $_SESSION['otp_actor'] ?? null;
    $actorId = $_SESSION['otp_id'] ?? null;
    if (!$actor || !$actorId) {
      $login_message = "Session expired.";
      $alert_class = "alert-danger custom-error";
      $stage = 'password';
    } else {
      if ($actor === 'company') {
        $st = $pdo->prepare("SELECT otp_login_code,otp_login_expires FROM companies WHERE company_id=?");
        $st->execute([$actorId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
      } else {
        $st = $pdo->prepare("SELECT role,otp_login_code,otp_login_expires FROM users WHERE user_id=?");
        $st->execute([$actorId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
      }
      $now = new DateTime();
      $expires = isset($row['otp_login_expires']) ? new DateTime($row['otp_login_expires']) : null;
      if ($expires) $_SESSION['otp_expires_at'] = $expires->getTimestamp();
      $correct = $row && !empty($row['otp_login_code']) && hash_equals($row['otp_login_code'], $code);
      $fresh = $expires && ($now <= $expires);
      if ($correct && $fresh) {
        if ($actor === 'company') {
          $pdo->prepare("UPDATE companies SET otp_login_code=NULL,otp_login_expires=NULL WHERE company_id=?")->execute([$actorId]);
          $_SESSION['company_id'] = $actorId;
          $_SESSION['user_type'] = 'company';
          $target = 'company_home.php';
        } else {
          $role = $row['role'] ?? 'user';
          $pdo->prepare("UPDATE users SET otp_login_code=NULL,otp_login_expires=NULL WHERE user_id=?")->execute([$actorId]);
          $_SESSION['user_id'] = $actorId;
          $_SESSION['user_type'] = $role;
          $target = ($role === 'admin') ? 'admin.php' : 'user_home.php';
        }
        unset($_SESSION['otp_actor'], $_SESSION['otp_id'], $_SESSION['otp_email'], $_SESSION['otp_last_sent'], $_SESSION['otp_expires_at']);
        echo "<script>setTimeout(function(){ window.location.href='{$target}'; },600);</script>";
        $login_message = "Login successful!";
        $alert_class = "alert-success custom-success";
      } else {
        $login_message = "Invalid or expired code.";
        $alert_class = "alert-danger custom-error";
        $stage = 'otp';
      }
    }
  }
}
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
    :root {
      --ink: #22223b;
      --gold: #ffc107;
    }

    html,
    body {
      height: 100%;
    }

    body {
      margin: 0;
      padding: 0;
      font-family: 'Helvetica Neue', Arial, sans-serif;
      background: var(--gold);
      display: flex;
      justify-content: center;
      align-items: flex-start;
    }

    .login-wrapper {
      width: 95%;
      max-width: 560px;
      margin-top: 20px;
    }

    .form-container {
      width: 100%;
      background: var(--ink);
      border-radius: 12px;
      padding: 18px 22px;
      box-shadow: 0 6px 14px rgba(0, 0, 0, .2);
      position: relative;
    }

    .login-title {
      color: var(--gold);
      font-weight: 700;
      margin-bottom: 14px;
      text-align: center;
      font-size: 22px;
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
      margin-bottom: 12px;
    }

    .form-control:focus {
      box-shadow: 0 0 0 2px rgba(255, 193, 7, .3);
      outline: none;
    }

    .btn-warning {
      background: var(--gold);
      color: var(--ink);
      border: none;
      border-radius: 6px;
      padding: 12px 0;
      font-weight: 600;
      font-size: 14px;
      width: 100%;
      margin-bottom: 12px;
    }

    .btn-warning:hover {
      transform: translateY(-1px);
      box-shadow: 0 6px 12px rgba(255, 193, 7, .25);
    }

    .btn-outline-secondary {
      border: 1px solid var(--gold);
      color: var(--gold);
      background: transparent;
      font-weight: 600;
      border-radius: 6px;
      padding: 10px 0;
    }

    .btn-outline-secondary:hover {
      background: var(--gold);
      color: var(--ink);
    }

    /* Make "Send OTP" match "Verify & Sign In" size */
    #btnResend {
      display: block;
      /* not inline */
      width: 100%;
      /* full width like .btn-warning */
      padding: 12px 0;
      /* same vertical padding */
      border-radius: 6px;
      /* same corners */
      font-weight: 600;
      /* same weight */
      box-sizing: border-box;
      margin-bottom: 12px;
      /* optional: same spacing */
    }

    /* Optional: keep disabled look but same size */
    #btnResend:disabled {
      opacity: .65;
      cursor: not-allowed;
    }


    .alert {
      border-radius: 6px;
      padding: 10px 12px;
      margin-bottom: 14px;
      font-size: 13px;
    }

    .alert-success.custom-success {
      background: rgba(47, 197, 94, .18);
      color: #2fc55e;
      border: 1px solid #2fc55e;
    }

    .alert-danger.custom-error {
      background: rgba(239, 68, 68, .18);
      color: #ef4444;
      border: 1px solid #ef4444;
    }

    .alert-warning.custom-warn {
      background: rgba(255, 193, 7, .18);
      color: var(--gold);
      border: 1px solid var(--gold);
    }

    .forgot-password {
      text-align: center;
      margin-top: 8px;
    }

    .forgot-password a {
      color: #ffe08a;
      text-decoration: none;
      font-size: 12px;
      font-weight: 700;
    }

    .forgot-password a:hover {
      text-decoration: underline;
    }

    .otp-timer-container {
      text-align: center;
      margin-bottom: 10px;
    }

    #otp-timer {
      font-size: 13px;
      color: #d9c36a;
    }

    .badge-mode {
      position: absolute;
      right: 12px;
      top: 12px;
      font-size: 11px;
      background: var(--ink) !important;
      color: var(--gold) !important;
      padding: 5px 10px;
      border-radius: 20px;
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

<body data-otp-expires="<?= htmlspecialchars((string)$otpExpiresEpoch) ?>">
  <div class="login-wrapper">
    <div class="form-container">
      <span class="badge bg-secondary badge-mode">OTP: <?= USE_LOGIN_OTP ? 'ON' : 'OFF' ?></span>
      <div class="login-title">Login</div>

      <?php if ($login_message): ?>
        <div class="alert <?= htmlspecialchars($alert_class) ?> text-center"><?= htmlspecialchars($login_message) ?>
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
          <button type="submit" class="btn btn-warning">LOGIN</button>
          <div class="forgot-password"><a href="forgot_pw.php">Forgot Password?</a></div>
        </form>
        <div class="d-grid gap-2 mt-2">
          <a class="btn btn-outline-secondary" href="sign_up.php">Register as User</a>
          <a class="btn btn-outline-secondary" href="c_sign_up.php">Register as Company</a>
        </div>
      <?php else: ?>
        <form action="login.php" method="POST" autocomplete="off" class="mb-2">
          <input type="hidden" name="stage" value="otp" />
          <div class="mb-2">
            <label for="otp" class="form-label">Enter 6-digit code</label>
            <input type="text" pattern="\d{6}" maxlength="6" class="form-control" id="otp" name="otp" required />
          </div>
          <div class="otp-timer-container">
            <div id="otp-timer"></div>
          </div>
          <button type="submit" class="btn btn-warning">Verify &amp; Sign In</button>
        </form>
        <form action="login.php" method="POST">
          <input type="hidden" name="stage" value="resend" />
          <button id="btnResend" type="submit" class="btn btn-outline-secondary" disabled>Send OTP</button>
          <div class="small muted mt-1">We’ll send a new code when the timer hits 0:00.</div>
        </form>
        <div class="d-grid gap-2 mt-3">
          <a class="btn btn-outline-secondary" href="sign_up.php">Register as User</a>
          <a class="btn btn-outline-secondary" href="c_sign_up.php">Register as Company</a>
        </div>
      <?php endif; ?>

      <!-- Brand link like signup -->
      <a href="index.php" class="brand-logo">JobHive</a>
    </div>
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