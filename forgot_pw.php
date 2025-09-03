<?php
include("connect.php");
session_start();

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

/* UI/logic constants */
const OTP_MINUTES          = 5;   // OTP lifetime
const RESEND_GRACE_SECONDS = 30;  // extra throttle on top of lifetime
const NEW_PW_MIN_LEN       = 8;   // (was 6) now 8 for strong policy

/* Strong password policy:
   - at least 8 chars
   - at least 1 lowercase, 1 uppercase, 1 digit, 1 special
   - no spaces
*/
const PW_POLICY_REGEX = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()\-\_\=\+\[\]\{\};:,.?~]).{8,}$/';
const PW_POLICY_HUMAN = "At least 8 chars, with 1 uppercase, 1 lowercase, 1 number, and 1 special (!@#\$%^&*() -_=+[]{};:,.?~). No spaces.";

/* Optional: quick blocklist of very weak passwords */
const PW_BLOCKLIST = [
    'password',
    'Password1',
    'Passw0rd',
    '12345678',
    'qwerty123',
    'letmein',
    'admin123',
    'jobhive123'
];

function isStrongPassword(string $pw): array
{
    // No whitespace
    if (preg_match('/\s/', $pw)) {
        return [false, "Password cannot contain spaces."];
    }
    // Blocklist
    foreach (PW_BLOCKLIST as $bad) {
        if (strcasecmp($pw, $bad) === 0) {
            return [false, "That password is too common. Please choose another."];
        }
    }
    // Regex policy
    if (!preg_match(PW_POLICY_REGEX, $pw)) {
        return [false, PW_POLICY_HUMAN];
    }
    // (Optional) prevent 3 identical consecutive chars
    if (preg_match('/(.)\1\1/', $pw)) {
        return [false, "Avoid repeating any character 3+ times in a row."];
    }
    return [true, ""];
}

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
            $mail->Subject = 'Your JobHive password reset code';
            $mail->Body = "
        <div style='font-family:Arial,Helvetica,sans-serif;line-height:1.6;color:#111'>
          <h2 style='margin:0 0 8px;color:#ffaa2b'>Password Reset Code</h2>
          <p>Use this code to reset your password:</p>
          <p style='font-size:22px;font-weight:bold;letter-spacing:4px;margin:12px 0 16px'>{$code}</p>
          <p>This code expires in <b>" . OTP_MINUTES . " minutes</b>.</p>
        </div>";
            $mail->AltBody = "Your JobHive password reset code is: {$code}. It expires in " . OTP_MINUTES . " minutes.";
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
   Issue + send reset OTP for an account
------------------------------------------------------------ */
function issueAndSendResetOtp(string $actor, int $actorId, string $email, ?string &$err = null): bool
{
    global $pdo;
    $otp = (string)random_int(100000, 999999);
    $exp = plusMinutesStr(OTP_MINUTES);

    if ($actor === 'company') {
        $pdo->prepare("UPDATE companies SET reset_otp_code=?, reset_otp_expires=? WHERE company_id=?")
            ->execute([$otp, $exp, $actorId]);
    } else {
        $pdo->prepare("UPDATE users SET reset_otp_code=?, reset_otp_expires=? WHERE user_id=?")
            ->execute([$otp, $exp, $actorId]);
    }

    $ok = sendOtpEmail($email, $otp, $err);
    if ($ok) {
        $_SESSION['reset_last_sent']  = time();
        $_SESSION['reset_expires_at'] = toEpoch($exp); // for client timer
    }
    return $ok;
}

/* ============================================================
   Controller state
   ============================================================ */
$stage         = $_POST['stage'] ?? 'email'; // 'email' | 'otp' | 'resend' | 'setpw'
$alert         = '';     // css class
$msg           = '';     // top message
$detail        = '';     // small subtext

/* ============================================================
   Stage A — request OTP by email
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $stage === 'email') {
    $entered = trim($_POST['email'] ?? '');

    if ($entered === '') {
        $msg = "Please enter your email address.";
        $alert = "alert-warning custom-warn";
    } else {
        // Look up in users then companies
        $actor = null;
        $actorId = null;
        $accountEmail = null;

        $st = $pdo->prepare("SELECT user_id, email FROM users WHERE email=? LIMIT 1");
        $st->execute([$entered]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $actor = 'user';
            $actorId = (int)$row['user_id'];
            $accountEmail = $row['email'];
        } else {
            $sc = $pdo->prepare("SELECT company_id, email FROM companies WHERE email=? LIMIT 1");
            $sc->execute([$entered]);
            $c = $sc->fetch(PDO::FETCH_ASSOC);
            if ($c) {
                $actor = 'company';
                $actorId = (int)$c['company_id'];
                $accountEmail = $c['email'];
            }
        }

        // Security: don't reveal if email exists. Show generic success if sent; generic message even if not found.
        if (!$actor) {
            $msg   = "If that email exists in our system, we’ve sent a code.";
            $alert = "alert-success custom-success";
        } else {
            $err = '';
            $sent = issueAndSendResetOtp($actor, $actorId, $accountEmail, $err);
            if ($sent) {
                $_SESSION['reset_actor'] = $actor;
                $_SESSION['reset_id']    = $actorId;
                $_SESSION['reset_email'] = $accountEmail;
                $msg   = "We sent a 6-digit code to your email. Enter it below.";
                $alert = "alert-success custom-success";
                $stage = 'otp';
            } else {
                $msg    = "Couldn't send the code. Please try again.";
                $detail = $err ? "SMTP Error: " . $err : '';
                $alert  = "alert-danger custom-error";
            }
        }
    }
}

/* ============================================================
   Stage B — resend OTP (only after expiry, plus small throttle)
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $stage === 'resend') {
    $actor = $_SESSION['reset_actor'] ?? null;
    $id    = $_SESSION['reset_id'] ?? null;
    $email = $_SESSION['reset_email'] ?? null;
    $expAt = $_SESSION['reset_expires_at'] ?? 0;
    $last  = $_SESSION['reset_last_sent'] ?? 0;

    if (!$actor || !$id || !$email) {
        $msg = "Session expired. Please start again.";
        $alert = "alert-danger custom-error";
        $stage = 'email';
    } else {
        $now = time();
        if ($expAt > $now) {
            $rem = $expAt - $now;
            $mm = str_pad(intval($rem / 60), 2, '0', STR_PAD_LEFT);
            $ss = str_pad($rem % 60, 2, '0', STR_PAD_LEFT);
            $msg = "Your current code is still valid. Wait {$mm}:{$ss} to request a new one.";
            $alert = "alert-warning custom-warn";
            $stage = 'otp';
        } elseif ($now - $last < RESEND_GRACE_SECONDS) {
            $wait = RESEND_GRACE_SECONDS - ($now - $last);
            $msg = "Please wait {$wait}s before requesting another code.";
            $alert = "alert-warning custom-warn";
            $stage = 'otp';
        } else {
            $err = '';
            $sent = issueAndSendResetOtp($actor, (int)$id, $email, $err);
            if ($sent) {
                $msg = "A new code has been sent.";
                $alert = "alert-success custom-success";
            } else {
                $msg = "Couldn't resend the code. Please try again.";
                $detail = $err ? "SMTP Error: " . $err : '';
                $alert = "alert-danger custom-error";
            }
            $stage = 'otp';
        }
    }
}

/* ============================================================
   Stage C — verify OTP and show new password form
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $stage === 'otp') {
    $raw = $_POST['otp'] ?? '';
    $code = preg_replace('/\D/', '', $raw);
    if ($raw === '' || $code === '') {
        $msg = "Please enter the 6-digit code.";
        $alert = "alert-warning custom-warn";
        $stage = 'otp';
    } elseif (!preg_match('/^\d{6}$/', $code)) {
        $msg = "Code must be exactly 6 digits.";
        $alert = "alert-warning custom-warn";
        $stage = 'otp';
    } else {
        $actor = $_SESSION['reset_actor'] ?? null;
        $id    = $_SESSION['reset_id'] ?? null;
        if (!$actor || !$id) {
            $msg = "Session expired. Please start again.";
            $alert = "alert-danger custom-error";
            $stage = 'email';
        } else {
            if ($actor === 'company') {
                $st = $pdo->prepare("SELECT reset_otp_code, reset_otp_expires FROM companies WHERE company_id=?");
                $st->execute([$id]);
                $row = $st->fetch(PDO::FETCH_ASSOC);
            } else {
                $st = $pdo->prepare("SELECT reset_otp_code, reset_otp_expires FROM users WHERE user_id=?");
                $st->execute([$id]);
                $row = $st->fetch(PDO::FETCH_ASSOC);
            }
            $now = new DateTime();
            $expires = isset($row['reset_otp_expires']) ? new DateTime($row['reset_otp_expires']) : null;
            if ($expires) $_SESSION['reset_expires_at'] = $expires->getTimestamp();
            $ok = $row && !empty($row['reset_otp_code']) && hash_equals($row['reset_otp_code'], $code) && $expires && ($now <= $expires);
            if ($ok) {
                $_SESSION['reset_verified'] = true;
                $msg = "Code verified. Set your new password.";
                $alert = "alert-success custom-success";
                $stage = 'setpw';
            } else {
                $msg = "Invalid or expired code. Please try again.";
                $alert = "alert-danger custom-error";
                $stage = 'otp';
            }
        }
    }
}

/* ============================================================
   Stage D — set new password (with strong policy)
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $stage === 'setpw') {
    $actor = $_SESSION['reset_actor'] ?? null;
    $id    = $_SESSION['reset_id'] ?? null;
    $ok    = $_SESSION['reset_verified'] ?? false;

    $p1 = $_POST['new_password'] ?? '';
    $p2 = $_POST['confirm_password'] ?? '';

    if (!$actor || !$id || !$ok) {
        $msg = "Session expired. Please start again.";
        $alert = "alert-danger custom-error";
        $stage = 'email';
    } elseif (strlen($p1) < NEW_PW_MIN_LEN) {
        $msg = "Password must be at least " . NEW_PW_MIN_LEN . " characters.";
        $alert = "alert-warning custom-warn";
        $stage = 'setpw';
    } elseif ($p1 !== $p2) {
        $msg = "Passwords do not match.";
        $alert = "alert-warning custom-warn";
        $stage = 'setpw';
    } else {
        // NEW: strong password check
        [$okStrong, $why] = isStrongPassword($p1);
        if (!$okStrong) {
            $msg = "Weak password: " . $why;
            $alert = "alert-warning custom-warn";
            $stage = 'setpw';
        } else {
            // Plain-text as requested (no hashing)
            if ($actor === 'company') {
                $pdo->prepare("UPDATE companies SET password=?, reset_otp_code=NULL, reset_otp_expires=NULL WHERE company_id=?")
                    ->execute([$p1, $id]);
            } else {
                $pdo->prepare("UPDATE users SET password=?, reset_otp_code=NULL, reset_otp_expires=NULL WHERE user_id=?")
                    ->execute([$p1, $id]);
            }
            // Clear session
            unset(
                $_SESSION['reset_actor'],
                $_SESSION['reset_id'],
                $_SESSION['reset_email'],
                $_SESSION['reset_last_sent'],
                $_SESSION['reset_expires_at'],
                $_SESSION['reset_verified']
            );

            $msg = "Password changed successfully! Redirecting to login…";
            $alert = "alert-success custom-success";
            echo "<script>setTimeout(function(){ window.location.href='login.php'; }, 1200);</script>";
        }
    }
}

/* For client timer (OTP stage) */
$resetExpiresEpoch = $_SESSION['reset_expires_at'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>JobHive | Forgot password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        body {
            background: #f8fafc;
            font-size: 14px;
        }

        .box {
            max-width: 380px;
            margin: 28px auto;
            background: #fff;
            padding: 1.3rem 1rem 1.6rem;
            border-radius: 1rem;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06)
        }

        .title {
            color: #ffaa2b;
            font-weight: 600;
            letter-spacing: .7px;
            text-align: center;
            font-size: 1.25rem;
            margin-bottom: 1rem;
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

        .brand {
            text-align: center;
            font-size: 1.45rem;
            font-weight: bold;
            color: #ffaa2b;
            letter-spacing: 1px;
            margin-top: .8rem;
        }

        .muted {
            color: #666;
            font-size: 12px;
        }

        .back {
            text-align: center;
            margin-top: .5rem;
        }
    </style>
</head>

<body data-reset-expires="<?= htmlspecialchars((string)$resetExpiresEpoch) ?>">
    <div class="brand">JobHive</div>
    <div class="box">
        <div class="title">Forgot password</div>

        <?php if ($msg): ?>
            <div class="alert <?= htmlspecialchars($alert) ?> text-center" role="alert">
                <?= htmlspecialchars($msg) ?>
                <?php if ($detail): ?><br><small><?= htmlspecialchars($detail) ?></small><?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($stage === 'email'): ?>
            <form action="forgot_pw.php" method="POST" autocomplete="off">
                <input type="hidden" name="stage" value="email" />
                <div class="mb-2">
                    <label for="email" class="form-label">Email address</label>
                    <input type="email" class="form-control" id="email" name="email" required maxlength="100" />
                </div>
                <button type="submit" class="btn btn-warning w-100 py-2 mt-2">Send code</button>
            </form>
            <div class="back"><a href="login.php" class="text-decoration-none">Back to login</a></div>

        <?php elseif ($stage === 'otp'): ?>
            <form action="forgot_pw.php" method="POST" autocomplete="off" class="mb-2">
                <input type="hidden" name="stage" value="otp" />
                <div class="mb-2">
                    <label for="otp" class="form-label">Enter 6-digit code</label>
                    <input type="text" pattern="\d{6}" maxlength="6" class="form-control" id="otp" name="otp" required />
                </div>
                <div id="otp-timer" class="muted mb-2"></div>
                <button type="submit" class="btn btn-warning w-100 py-2 mt-2">Verify code</button>
            </form>
            <form action="forgot_pw.php" method="POST" class="text-center">
                <input type="hidden" name="stage" value="resend" />
                <button id="btnResend" type="submit" class="btn btn-outline-secondary w-100 py-2" disabled>Send OTP</button>
                <div class="small text-muted mt-1">We’ll send a new code when the timer hits 0:00.</div>
            </form>
            <div class="back"><a href="login.php" class="text-decoration-none">Back to login</a></div>

        <?php elseif ($stage === 'setpw'): ?>
            <form action="forgot_pw.php" method="POST" autocomplete="off">
                <input type="hidden" name="stage" value="setpw" />
                <div class="mb-2">
                    <label for="npw" class="form-label">New password</label>
                    <input type="password"
                        class="form-control"
                        id="npw"
                        name="new_password"
                        required
                        minlength="<?= NEW_PW_MIN_LEN ?>"
                        pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()\-\_\=\+\[\]\{\};:,.?~]).{8,}"
                        title="<?= htmlspecialchars(PW_POLICY_HUMAN) ?>" />
                </div>
                <div class="mb-2">
                    <label for="cpw" class="form-label">Confirm password</label>
                    <input type="password"
                        class="form-control"
                        id="cpw"
                        name="confirm_password"
                        required
                        minlength="<?= NEW_PW_MIN_LEN ?>"
                        pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()\-\_\=\+\[\]\{\};:,.?~]).{8,}"
                        title="<?= htmlspecialchars(PW_POLICY_HUMAN) ?>" />
                </div>
                <button type="submit" class="btn btn-warning w-100 py-2 mt-2">Change password</button>
                <div class="muted mt-2"><?= htmlspecialchars(PW_POLICY_HUMAN) ?></div>
            </form>
            <div class="back"><a href="login.php" class="text-decoration-none">Back to login</a></div>
        <?php endif; ?>
    </div>

    <?php if ($stage === 'otp'): ?>
        <script>
            (function() {
                var exp = parseInt(document.body.dataset.resetExpires || "0", 10);
                var elTimer = document.getElementById('otp-timer');
                var btn = document.getElementById('btnResend');
                if (!exp || !elTimer || !btn) return;

                function fmt(ms) {
                    var s = Math.max(0, Math.floor(ms / 1000));
                    var mm = String(Math.floor(s / 60)).padStart(2, '0');
                    var ss = String(s % 60).padStart(2, '0');
                    return mm + ':' + ss;
                }

                function tick() {
                    var msLeft = exp * 1000 - Date.now();
                    if (msLeft > 0) {
                        elTimer.textContent = 'Code expires in ' + fmt(msLeft);
                        btn.disabled = true;
                        setTimeout(tick, 250);
                    } else {
                        elTimer.textContent = 'Code expired. You can request a new one.';
                        btn.disabled = false;
                    }
                }
                tick();
            })();
        </script>
    <?php endif; ?>

    <?php if ($stage === 'setpw'): ?>
        <!-- Optional live checklist for password strength -->
        <script>
            (function() {
                var npw = document.getElementById('npw');
                var cpw = document.getElementById('cpw');
                if (!npw || !cpw) return;

                var box = document.createElement('div');
                box.className = 'muted mt-1';
                box.innerHTML = `
            <ul id="pw-req" style="margin:.4rem 0 0 1rem; padding:0; list-style:square;">
              <li data-k="len">≥ 8 characters</li>
              <li data-k="low">1 lowercase</li>
              <li data-k="upp">1 uppercase</li>
              <li data-k="dig">1 digit</li>
              <li data-k="spe">1 special (!@#$%^&*() -_=+[]{};:,.?~)</li>
              <li data-k="spc">no spaces</li>
              <li data-k="rep">no 3 repeated chars (e.g., aaa)</li>
              <li data-k="match">confirm matches</li>
            </ul>`;
                npw.parentNode.appendChild(box);

                var ul = document.getElementById('pw-req');
                var li = {};
                ['len', 'low', 'upp', 'dig', 'spe', 'spc', 'rep', 'match'].forEach(k => {
                    li[k] = ul.querySelector('[data-k="' + k + '"]');
                });

                function setOK(el, ok) {
                    el.style.color = ok ? '#198754' : '#666';
                    el.style.fontWeight = ok ? '600' : '400';
                }

                function check() {
                    var v = npw.value || '';
                    var c = cpw.value || '';
                    var okLen = v.length >= 8;
                    var okLow = /[a-z]/.test(v);
                    var okUpp = /[A-Z]/.test(v);
                    var okDig = /\d/.test(v);
                    var okSpe = /[!@#$%^&*()\-\_\=\+\[\]\{\};:,.?~]/.test(v);
                    var okSpc = !/\s/.test(v);
                    var okRep = !/(.)\1\1/.test(v);
                    var okMatch = (v !== '' && v === c);

                    setOK(li.len, okLen);
                    setOK(li.low, okLow);
                    setOK(li.upp, okUpp);
                    setOK(li.dig, okDig);
                    setOK(li.spe, okSpe);
                    setOK(li.spc, okSpc);
                    setOK(li.rep, okRep);
                    setOK(li.match, okMatch);
                }

                npw.addEventListener('input', check);
                cpw.addEventListener('input', check);
                check();
            })();
        </script>
    <?php endif; ?>
</body>

</html>