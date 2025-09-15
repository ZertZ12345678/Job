<?php
// premium.php
require_once "connect.php";
if (session_status() === PHP_SESSION_NONE) session_start();
/* ===== Require login ===== */
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?next=" . urlencode("premium.php"));
    exit;
}
$user_id = (int)$_SESSION['user_id'];
/* ===== Helpers ===== */
function e($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
/* ===== Fetch current user (for prefills) ===== */
$full_name = trim($_SESSION['full_name'] ?? '');
$email     = trim($_SESSION['email'] ?? '');
$profile   = $_SESSION['profile_picture'] ?? null;
$package   = $_SESSION['package'] ?? null;
try {
    $st = $pdo->prepare("SELECT full_name, email, profile_picture, package FROM users WHERE user_id=? LIMIT 1");
    $st->execute([$user_id]);
    if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
        $full_name = $row['full_name'] ?: $full_name;
        $email     = $row['email']     ?: $email;
        $profile   = $row['profile_picture'] ?? $profile;
        $package   = $row['package'] ?? $package;
        $_SESSION['full_name'] = $full_name;
        $_SESSION['email']     = $email;
        $_SESSION['profile_picture'] = $profile;
        $_SESSION['package'] = $package;
    }
} catch (PDOException $ex) {
}
/* ===== Handle POST (upgrade) ===== */
$ok = false;
$err = '';
$method_post = '';
// keep posted fields so the UI can re-render correctly after a validation error
$wallet_txn = '';
$card_no = $card_name = $card_exp = $card_cvc = '';
$pp_email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $method_post = trim($_POST['payment_method'] ?? '');
    $valid  = ['KPay', 'AyaPay', 'Wave Pay', 'Visa Card', 'PayPal'];
    if (!in_array($method_post, $valid, true)) {
        $err = 'Please choose a valid payment method.';
    } else {
        // Validate per-method extras
        if (in_array($method_post, ['KPay', 'AyaPay', 'Wave Pay'], true)) {
            $wallet_txn = trim($_POST['wallet_txn'] ?? '');
            if ($wallet_txn === '') $err = 'Please enter your transaction ID.';
        } elseif ($method_post === 'Visa Card') {
            $card_no   = preg_replace('/\s+/', '', (string)($_POST['card_no'] ?? ''));
            $card_name = trim($_POST['card_name'] ?? '');
            $card_exp  = trim($_POST['card_exp'] ?? '');
            $card_cvc  = trim($_POST['card_cvc'] ?? '');
            if ($card_no === '' || $card_name === '' || $card_exp === '' || $card_cvc === '') {
                $err = 'Please fill all card fields.';
            }
        } elseif ($method_post === 'PayPal') {
            $pp_email = trim($_POST['paypal_email'] ?? '');
            if ($pp_email === '') $err = 'Please enter your PayPal email.';
        }
        if ($err === '') {
            try {
                // 1) Update package to premium
                $up = $pdo->prepare("UPDATE users SET package='premium' WHERE user_id=?");
                $up->execute([$user_id]);
                $_SESSION['package'] = 'premium';
                // 2) Insert into premium_payment dataset
                $description = 'Premium';
                $amount = 30000.00;
                $status = 'paid';
                $reference = null;
                if (in_array($method_post, ['KPay', 'AyaPay', 'Wave Pay'], true)) {
                    $reference = $wallet_txn; // store provided transaction ID
                } elseif ($method_post === 'Visa Card') {
                    $reference = 'card_last4:' . substr($card_no, -4);
                } else { // PayPal
                    $reference = $pp_email;
                }
                $pay = $pdo->prepare("
  INSERT INTO premium_payment (user_id, payment_method, amount, description, reference, status)
  VALUES (?, ?, ?, ?, ?, ?)
");
$pay->execute([$user_id, $method_post, $amount, $description, $reference, $status]);

// mark session package
$_SESSION['package'] = 'premium';

// optional flash message
$_SESSION['flash_success'] = "Upgraded to Premium. Enjoy resume templates and auto-fill.";

// redirect straight to home
header("Location: user_home.php?upgraded=1", true, 303);
exit;

            } catch (PDOException $ex) {
                $err = 'Could not upgrade at the moment. Please try again.';
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>JobHive | Upgrade to Premium</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />
    <style>
        :root {
            /* Light mode variables */
            --bg-color: #f8fafc;
            --card-bg: #ffffff;
            --text-color: #334155;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --navbar-bg: #ffffff;
            --navbar-text: #334155;
            --input-bg: #ffffff;
            --btn-primary-bg: #ffc107;
            --btn-primary-text: #212529;
            --btn-primary-hover: #e0a800;
            --btn-secondary-bg: #6c757d;
            --btn-secondary-text: #ffffff;
            --card-shadow: 0 10px 30px rgba(0, 0, 0, .06);
            --premium-border: #ffe08a;
            --premium-gradient-start: #fff9e6;
            --premium-gradient-end: #ffffff;
            --price-bg: #ffffff;
            --price-border: #f1e2b4;
            --price-old-color: #6c757d;
            --price-new-color: #198754;
            --badge-save-bg: #198754;
            --badge-save-text: #ffffff;
            --sparkle-start: rgba(255, 193, 7, .25);
            --sparkle-middle: rgba(255, 193, 7, .65);
            --sparkle-end: rgba(255, 193, 7, .25);
            --wallet-bg: #fff7e6;
            --wallet-border: #ffd37a;
            --gateway-bg: #eef6ff;
            --gateway-border: #b6d4fe;
            --alert-success-bg: #d1e7dd;
            --alert-success-border: #badbcc;
            --alert-success-text: #0f5132;
            --alert-danger-bg: #f8d7da;
            --alert-danger-border: #f5c2c7;
            --alert-danger-text: #842029;
            --alert-info-bg: #cff4fc;
            --alert-info-border: #b6effb;
            --alert-info-text: #055160;
            --transition-speed: 0.3s;
        }

        /* Dark mode variables */
        [data-theme="dark"] {
            --bg-color: #121212;
            --card-bg: #1e1e1e;
            --text-color: #e0e0e0;
            --text-muted: #a0a0a0;
            --border-color: rgba(255, 255, 255, 0.1);
            --navbar-bg: #1a1a1a;
            --navbar-text: #e0e0e0;
            --input-bg: #2d2d2d;
            --btn-primary-bg: #ffc107;
            --btn-primary-text: #000000;
            --btn-primary-hover: #e0a800;
            --btn-secondary-bg: #6c757d;
            --btn-secondary-text: #ffffff;
            --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            --premium-border: #e6b800;
            --premium-gradient-start: #2d2d2d;
            --premium-gradient-end: #1a1a1a;
            --price-bg: #2d2d2d;
            --price-border: rgba(255, 193, 7, 0.3);
            --price-old-color: #a0a0a0;
            --price-new-color: #4caf50;
            --badge-save-bg: #4caf50;
            --badge-save-text: #ffffff;
            --sparkle-start: rgba(255, 193, 7, .3);
            --sparkle-middle: rgba(255, 193, 7, .7);
            --sparkle-end: rgba(255, 193, 7, .3);
            --wallet-bg: #2d2d2d;
            --wallet-border: rgba(255, 193, 7, 0.3);
            --gateway-bg: #2d2d2d;
            --gateway-border: rgba(100, 149, 237, 0.3);
            --alert-success-bg: #1b5e20;
            --alert-success-border: #2e7d32;
            --alert-success-text: #c8e6c9;
            --alert-danger-bg: #b71c1c;
            --alert-danger-border: #c62828;
            --alert-danger-text: #ffcdd2;
            --alert-info-bg: #01579b;
            --alert-info-border: #0277bd;
            --alert-info-text: #b3e5fc;
        }

        /* Global transitions */
        body {
            transition: background-color var(--transition-speed) ease, color var(--transition-speed) ease;
        }

        body {
            background: var(--bg-color);
            color: var(--text-color);
        }

        .card-premium {
            border: 1px solid var(--premium-border);
            background: linear-gradient(135deg, var(--premium-gradient-start), var(--premium-gradient-end));
            border-radius: 1.25rem;
            box-shadow: var(--card-shadow);
            transition: background var(--transition-speed) ease, border-color var(--transition-speed) ease, box-shadow var(--transition-speed) ease;
        }

        .price-box {
            background: var(--price-bg);
            border: 1px solid var(--price-border);
            border-radius: .85rem;
            padding: .8rem 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: background-color var(--transition-speed) ease, border-color var(--transition-speed) ease;
        }

        .price-old {
            text-decoration: line-through;
            color: var(--price-old-color);
            margin-right: .6rem;
        }

        .price-new {
            font-weight: 800;
            font-size: 1.35rem;
            color: var(--price-new-color);
        }

        .badge-save {
            background: var(--badge-save-bg);
            color: var(--badge-save-text);
            padding: .25rem .55rem;
            border-radius: .5rem;
            font-weight: 600;
        }

        .sparkle {
            background: linear-gradient(90deg, var(--sparkle-start), var(--sparkle-middle), var(--sparkle-end));
            background-size: 200% 100%;
            animation: shine 2.2s ease-in-out infinite;
            border-radius: .6rem;
            padding: .25rem .5rem;
            display: inline-block;
        }

        @keyframes shine {
            0% {
                background-position: 200% 0
            }

            100% {
                background-position: 0 0
            }
        }

        .wallet-box {
            background: var(--wallet-bg);
            border: 1px solid var(--wallet-border);
            transition: background-color var(--transition-speed) ease, border-color var(--transition-speed) ease;
        }

        .gateway-box {
            background: var(--gateway-bg);
            border: 1px solid var(--gateway-border);
            transition: background-color var(--transition-speed) ease, border-color var(--transition-speed) ease;
        }

        /* Navbar styling */
        .navbar {
            background-color: var(--navbar-bg) !important;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: background-color var(--transition-speed) ease;
        }

        [data-theme="dark"] .navbar {
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        .navbar-brand {
            color: var(--btn-primary-bg) !important;
            font-weight: 700;
        }

        .navbar-nav .nav-link {
            color: var(--navbar-text) !important;
        }

        .navbar-toggler {
            border-color: var(--border-color) !important;
        }

        .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%2833, 0.75%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e") !important;
        }

        [data-theme="dark"] .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%28255, 255, 255, 0.75%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e") !important;
        }

        /* Dark mode form text adjustments */

        
        [data-theme="dark"] .form-label {
            color: #ffffff !important;
        }

        [data-theme="dark"] .form-control[readonly] {
            color: #ffffff !important;
        }

        [data-theme="dark"] .form-select {
            color: #ffffff !important;
        }

        [data-theme="dark"] .form-control-plaintext {
            color: #ffffff !important;
        }

        [data-theme="dark"] .form-select option {
            background-color: #2d2d2d;
            color: #ffffff;
        }

        /* Dark mode text adjustments for the description paragraph */
        [data-theme="dark"] .card-premium .card-body p.text-muted {
            color: #ffffff !important;
        }

        /* Dark mode wallet box text adjustments */
        [data-theme="dark"] .wallet-box h6,
        [data-theme="dark"] .wallet-box .form-control-plaintext,
        [data-theme="dark"] .wallet-box ol,
        [data-theme="dark"] .wallet-box li,
        [data-theme="dark"] .wallet-box label {
            color: #ffffff !important;
        }

        [data-theme="dark"] .wallet-box .bi {
            color: #ffffff !important;
        }

        /* Form controls */
        .form-control,
        .form-select {
            background-color: var(--input-bg);
            color: var(--text-color);
            border-color: var(--border-color);
            transition: background-color var(--transition-speed) ease, color var(--transition-speed) ease, border-color var(--transition-speed) ease;
        }

        .form-control:focus,
        .form-select:focus {
            background-color: var(--input-bg);
            color: var(--text-color);
            border-color: var(--btn-primary-bg);
        }

        .form-control-plaintext {
            color: var(--text-color);
        }

        /* Buttons */
        .btn-warning {
            background-color: var(--btn-primary-bg);
            color: var(--btn-primary-text);
            border-color: var(--btn-primary-bg);
        }

        .btn-warning:hover {
            background-color: var(--btn-primary-hover);
            color: var(--btn-primary-text);
            border-color: var(--btn-primary-hover);
        }

        .btn-outline-secondary {
            background-color: transparent;
            color: var(--btn-secondary-bg);
            border-color: var(--btn-secondary-bg);
        }

        .btn-outline-secondary:hover {
            background-color: var(--btn-secondary-bg);
            color: var(--btn-secondary-text);
            border-color: var(--btn-secondary-bg);
        }

        /* Alerts */
        .alert-success {
            background-color: var(--alert-success-bg);
            border-color: var(--alert-success-border);
            color: var(--alert-success-text);
        }

        .alert-danger {
            background-color: var(--alert-danger-bg);
            border-color: var(--alert-danger-border);
            color: var(--alert-danger-text);
        }

        .alert-info {
            background-color: var(--alert-info-bg);
            border-color: var(--alert-info-border);
            color: var(--alert-info-text);
        }

        /* Theme toggle button */
        .theme-toggle {
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-color);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }

        .theme-toggle:hover {
            background: var(--input-bg);
        }


        [data-theme="dark"] .gateway-box h6,
        [data-theme="dark"] .gateway-box .form-label,
        [data-theme="dark"] .gateway-box p.text-muted {
            color: #ffffff !important;
        }

        [data-theme="dark"] .gateway-box .bi {
            color: #ffffff !important;
        }


        [data-theme="dark"] input::placeholder,
        [data-theme="dark"] textarea::placeholder,
        [data-theme="dark"] select::placeholder {
            color: rgba(255, 255, 255, 0.7) !important;
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="user_home.php">JobHive</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav"><span class="navbar-toggler-icon"></span></button>
            <div class="collapse navbar-collapse justify-content-end" id="nav">
                <ul class="navbar-nav">
                    <li class="nav-item"><a class="nav-link" href="user_home.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="user_dashboard.php">Dashboard</a></li>
                    <!-- Theme Toggle Button -->
                    <li class="nav-item">
                        <button class="theme-toggle ms-3" id="themeToggle" aria-label="Toggle theme">
                            <i class="bi bi-sun-fill" id="themeIcon"></i>
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <main class="container py-5" style="max-width:900px;">
        <?php if ($ok): ?>
            <div class="alert alert-success">
                <strong><i class="bi bi-check-circle-fill me-1"></i>Upgraded!</strong>
                Your package is now <b>Premium</b>. Enjoy premium resume templates and auto-fill.
            </div>
            <a href="user_home.php" class="btn btn-warning"><i class="bi bi-house-door me-1"></i> Back to Home</a>
        <?php else: ?>
            <?php if ($err): ?>
                <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-1"></i><?= e($err) ?></div>
            <?php endif; ?>
            <div class="card card-premium">
                <div class="card-body p-4 p-md-5">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h3 class="mb-0">
                            <span class="sparkle"><i class="bi bi-stars me-2"></i>Upgrade to Premium</span>
                        </h3>
                        <div class="price-box">
                            <div>
                                <span class="price-old">50,000 MMK</span>
                                <span class="price-new">30,000 MMK</span>
                            </div>
                            <span class="badge-save">Save 20,000 MMK</span>
                        </div>
                    </div>
                    <p class="text-muted mb-4">
                        Premium gives you <strong>ATS-friendly resume templates</strong> and <strong>auto-fill</strong> from your profile for faster, cleaner applications.
                    </p>
                    <form method="post" class="row g-3" id="premiumForm" novalidate>
                        <div class="col-md-6">
                            <label class="form-label">Full name</label>
                            <input type="text" class="form-control" value="<?= e($full_name) ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" value="<?= e($email) ?>" readonly>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <input type="text" class="form-control" value="Premium" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Payment method</label>
                            <select name="payment_method" id="payment_method" class="form-select" required>
                                <option value="" <?= $method_post === '' ? 'selected' : ''; ?> disabled>Choose…</option>
                                <option value="KPay" <?= $method_post === 'KPay' ? 'selected' : ''; ?>>KPay</option>
                                <option value="AyaPay" <?= $method_post === 'AyaPay' ? 'selected' : ''; ?>>AyaPay</option>
                                <option value="Wave Pay" <?= $method_post === 'Wave Pay' ? 'selected' : ''; ?>>Wave Pay</option>
                                <option value="Visa Card" <?= $method_post === 'Visa Card' ? 'selected' : ''; ?>>Visa Card</option>
                                <option value="PayPal" <?= $method_post === 'PayPal' ? 'selected' : ''; ?>>PayPal</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Amount</label>
                            <input type="text" class="form-control" value="30,000 MMK (discount applied)" readonly>
                        </div>
                        <!-- Wallet methods (KPay/AyaPay/Wave Pay) -->
                        <div class="col-12" id="walletSection" style="display:none;">
                            <div class="p-3 rounded wallet-box">
                                <h6 class="mb-2"><i class="bi bi-phone me-1"></i> Wallet payment instructions</h6>
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <div class="form-control-plaintext"><strong>Name:</strong> Phone Thaw Naing</div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-control-plaintext"><strong>Phone:</strong> 09957433847</div>
                                    </div>
                                </div>
                                <ol class="small mt-2 mb-3">
                                    <li>Open your selected wallet (KPay / AyaPay / Wave Pay)</li>
                                    <li>Send <strong>30,000 MMK</strong> to the contact above</li>
                                    <li>Paste your <strong>Transaction ID</strong> below and submit</li>
                                </ol>
                                <label class="form-label">Transaction ID</label>
                                <input type="text" class="form-control" name="wallet_txn" id="wallet_txn" placeholder="e.g., KP123456789" value="<?= e($wallet_txn) ?>" />
                            </div>
                        </div>
                        <!-- Visa Card -->
                        <div class="col-12" id="cardSection" style="display:none;">
                            <div class="p-3 rounded gateway-box">
                                <h6 class="mb-2"><i class="bi bi-credit-card-2-front me-1"></i> Card details</h6>
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <label class="form-label">Card number</label>
                                        <input type="text" class="form-control" name="card_no" placeholder="4111 1111 1111 1111" inputmode="numeric" value="<?= e($card_no) ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Name on card</label>
                                        <input type="text" class="form-control" name="card_name" placeholder="As on card" value="<?= e($card_name) ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Expiry (MM/YY)</label>
                                        <input type="text" class="form-control" name="card_exp" placeholder="08/27" value="<?= e($card_exp) ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">CVC</label>
                                        <input type="text" class="form-control" name="card_cvc" placeholder="123" inputmode="numeric" value="<?= e($card_cvc) ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- PayPal -->
                        <div class="col-12" id="paypalSection" style="display:none;">
                            <div class="p-3 rounded gateway-box">
                                <h6 class="mb-2"><i class="bi bi-paypal me-1"></i> PayPal</h6>
                                <p class="small text-muted mb-2">Enter your PayPal email. We'll simulate a secure checkout.</p>
                                <label class="form-label">PayPal Email</label>
                                <input type="email" class="form-control" name="paypal_email" placeholder="you@example.com" value="<?= e($pp_email) ?>">
                            </div>
                        </div>
                        <div class="col-12 d-flex gap-2 pt-2">
                            <button type="submit" class="btn btn-warning">
                                <i class="bi bi-lightning-charge-fill me-1"></i> Upgrade Now
                            </button>
                            <a href="user_home.php" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                    <?php if ($package === 'premium'): ?>
                        <div class="alert alert-info mt-4 mb-0">
                            <i class="bi bi-info-circle me-1"></i>You already have <strong>Premium</strong>.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (function() {
            const select = document.getElementById('payment_method');
            const wallet = document.getElementById('walletSection');
            const card = document.getElementById('cardSection');
            const paypal = document.getElementById('paypalSection');
            const txn = document.getElementById('wallet_txn');

            function updateUI() {
                const v = (select.value || '').trim();
                const isWallet = (v === 'KPay' || v === 'AyaPay' || v === 'Wave Pay');
                wallet.style.display = isWallet ? '' : 'none';
                card.style.display = (v === 'Visa Card') ? '' : 'none';
                paypal.style.display = (v === 'PayPal') ? '' : 'none';
                // Toggle required on wallet txn
                if (isWallet) {
                    txn && txn.setAttribute('required', 'required');
                } else {
                    txn && txn.removeAttribute('required');
                }
            }
            select && select.addEventListener('change', updateUI);
            // Initialize on load (handles server validation return too)
            updateUI();
        })();

        // Theme toggle functionality
        const themeToggle = document.getElementById('themeToggle');
        const themeIcon = document.getElementById('themeIcon');
        const html = document.documentElement;

        // Check for saved theme preference or default to light
        const currentTheme = localStorage.getItem('theme') || 'light';
        html.setAttribute('data-theme', currentTheme);
        updateThemeIcon(currentTheme);

        themeToggle.addEventListener('click', () => {
            const theme = html.getAttribute('data-theme');
            const newTheme = theme === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateThemeIcon(newTheme);
        });

        function updateThemeIcon(theme) {
            if (theme === 'dark') {
                themeIcon.classList.remove('bi-sun-fill');
                themeIcon.classList.add('bi-moon-fill');
            } else {
                themeIcon.classList.remove('bi-moon-fill');
                themeIcon.classList.add('bi-sun-fill');
            }
        }
    </script>
</body>

</html>