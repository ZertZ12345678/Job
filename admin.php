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
          <h2 style='margin:0 0 8px;color:#ffc107'>Your Login Code</h2>
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
      margin: 0;
      padding: 0;
      font-family: 'Helvetica Neue', Arial, sans-serif;
      height: 100vh;
      overflow: hidden;
      background-color: #ffc107; /* Changed to #ffc107 */
      display: flex;
      justify-content: center;
      align-items: center;
    }
    
    .login-wrapper {
      width: 90%;
      max-width: 1200px;
      height: 90vh;
      max-height: 800px;
      border: 20px solid #ffc107;
      box-shadow: 0 0 25px rgba(0, 0, 0, 0.2);
      display: flex;
    }
    
    .login-container {
      display: flex;
      width: 100%;
      height: 100%;
    }
    
    .login-left {
      width: 50%;
      position: relative;
      overflow: hidden;
      background-color: #ffd1dc; /* Light pink background behind the photo */
    }
    
    .login-image {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    
    .login-right {
      width: 50%;
      background-color: #ffc107; /* Changed to #ffc107 */
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      padding: 40px;
      color: #22223b;
    }
    
    .form-container {
      width: 100%;
      max-width: 400px;
      padding: 25px;
      background-color: #ffc107; /* Changed to gold color */
      border-radius: 16px;
      box-shadow: 0 8px 30px rgba(0, 0, 0, 0);
    }
    
    .login-title {
      color: #22223b;
      font-weight: 700;
      margin-bottom: 25px;
      text-align: center;
      font-size: 28px;
    }
    
    .form-label {
      font-size: 16px;
      margin-bottom: 8px;
      color: #22223b;
      font-weight: 500;
    }
    
    .form-control {
      font-size: 16px;
      padding: 12px 15px;
      border-radius: 8px;
      border: 1px solid #22223b;
      margin-bottom: 20px;
      background-color: #fff;
      color: #22223b;
      transition: all 0.3s ease;
    }
    
    .form-control:focus {
      border-color: #22223b;
      box-shadow: 0 0 0 0.2rem rgba(34, 34, 59, 0.25);
      background-color: #fff;
    }
    
    .btn-warning {
      background: #22223b;
      border: none;
      font-size: 16px;
      border-radius: 8px;
      padding: 12px 0;
      font-weight: 600;
      color: #ffc107;
      width: 100%;
      margin-bottom: 15px;
      transition: all 0.3s ease;
    }
    
    .btn-warning:hover {
      background: #1a1a2e;
      color: #ffc107;
    }
    
    .btn-outline-secondary {
      border: 1px solid #22223b;
      color: #22223b;
      background: transparent;
      font-weight: 500;
      border-radius: 8px;
      padding: 10px 0;
      margin-bottom: 10px;
      transition: all 0.3s ease;
    }
    
    .btn-outline-secondary:hover {
      background: #22223b;
      color: #ffc107;
    }
    
    .alert-success.custom-success {
      background: #22223b;
      color: #ffc107;
      border: 1px solid #22223b;
      font-weight: 500;
      border-radius: 8px;
      font-size: 14px;
      padding: 12px 15px;
      margin-bottom: 20px;
    }
    
    .alert-danger.custom-error {
      background: #22223b;
      color: #ff6b6b;
      border: 1px solid #ff6b6b;
      font-weight: 500;
      border-radius: 8px;
      font-size: 14px;
      padding: 12px 15px;
      margin-bottom: 20px;
    }
    
    .alert-warning.custom-warn {
      background: #22223b;
      color: #ffc107;
      border: 1px solid #ffc107;
      font-weight: 500;
      border-radius: 8px;
      font-size: 14px;
      padding: 12px 15px;
      margin-bottom: 20px;
    }
    
    .badge-mode {
      position: absolute;
      right: 20px;
      top: 20px;
      font-size: 12px;
      background: rgba(34, 34, 59, 0.8) !important;
      color: #ffc107 !important;
      padding: 5px 10px;
      border-radius: 20px;
      z-index: 10;
    }
    
    .muted {
      color: #22223b;
      font-size: 14px;
      text-align: center;
      margin-bottom: 15px;
      opacity: 0.8;
    }
    
    .reg-buttons {
      margin-top: 20px;
    }
    
    .forgot-password {
      text-align: center;
      margin-top: 15px;
    }
    
    .forgot-password a {
      color: #22223b;
      text-decoration: none;
      font-size: 14px;
      font-weight: 500;
    }
    
    .forgot-password a:hover {
      color: #22223b;
      text-decoration: underline;
    }
    
    .otp-timer-container {
      text-align: center;
      margin-bottom: 15px;
    }
    
    #otp-timer {
      font-size: 14px;
      color: #22223b;
      opacity: 0.8;
    }
    
    .resend-container {
      margin-top: 15px;
    }
    
    .resend-container button {
      width: 100%;
    }
    
    .resend-container .small {
      font-size: 12px;
      color: #22223b;
      margin-top: 8px;
      text-align: center;
      opacity: 0.8;
    }
    
    .brand-logo {
      position: absolute;
      bottom: 30px;
      left: 30px;
      font-size: 24px;
      font-weight: bold;
      color: #ffc107;
      z-index: 10;
      text-shadow: 0 2px 4px rgba(0, 0, 0, 0.5);
    }
    
    @media (max-width: 768px) {
      .login-wrapper {
        width: 95%;
        height: 95vh;
        border-width: 12px;
        flex-direction: column;
      }
      
      .login-container {
        flex-direction: column;
      }
      
      .login-left, .login-right {
        width: 100%;
        height: 50%;
      }
      
      .login-right {
        padding: 20px;
      }
      
      .form-container {
        max-width: 100%;
        padding: 20px;
      }
      
      .badge-mode {
        right: 15px;
        top: 15px;
      }
      
      .brand-logo {
        bottom: 15px;
        left: 15px;
        font-size: 20px;
      }
    }
  </style>
</head>
<body data-otp-expires="<?= htmlspecialchars((string)$otpExpiresEpoch) ?>">
  <div class="login-wrapper">
    <div class="login-container">
      <!-- Left Section with Full Size Image and Light Pink Background -->
      <div class="login-left">
        <img src="https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1000&q=80" alt="Job search illustration" class="login-image">
        <div class="brand-logo">JobHive</div>
        
      </div>
      
      <!-- Right Section with Gold Background -->
      <div class="login-right">
        <span class="badge bg-secondary badge-mode">OTP: <?= USE_LOGIN_OTP ? 'ON' : 'OFF' ?></span>
        
        <div class="form-container">
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
              <div class="mb-3">
                <label for="email" class="form-label">Email address</label>
                <input type="email" class="form-control" id="email" name="email" required maxlength="100" />
              </div>
              <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required minlength="6" />
              </div>
              <button type="submit" class="btn btn-warning">Continue</button>
              <div class="forgot-password">
                <a href="forgot_pw.php">Forgot password?</a>
              </div>
            </form>
            
            <div class="reg-buttons d-grid gap-2">
              <a class="btn btn-outline-secondary" href="sign_up.php">Register as User</a>
              <a class="btn btn-outline-secondary" href="c_sign_up.php">Register as Company</a>
            </div>
          <?php else: /* OTP stage */ ?>
            <form action="login.php" method="POST" autocomplete="off" class="mb-3">
              <input type="hidden" name="stage" value="otp" />
              <div class="mb-3">
                <label for="otp" class="form-label">Enter 6-digit code</label>
                <input type="text" pattern="\d{6}" maxlength="6" class="form-control" id="otp" name="otp" required />
              </div>
              <div class="otp-timer-container">
                <div id="otp-timer" class="muted"></div>
              </div>
              <button type="submit" class="btn btn-warning">Verify &amp; Sign In</button>
            </form>
            
            <form action="login.php" method="POST" class="resend-container">
              <input type="hidden" name="stage" value="resend" />
              <button id="btnResend" type="submit" class="btn btn-outline-secondary" disabled>Send OTP</button>
              <div class="small text-muted">We'll send a new code when the timer hits 0:00.</div>
            </form>
            
            <div class="reg-buttons d-grid gap-2 mt-3">
              <a class="btn btn-outline-secondary" href="sign_up.php">Register as User</a>
              <a class="btn btn-outline-secondary" href="c_signup.php">Register as Company</a>
            </div>
          <?php endif; ?>
        </div>
      </div>
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