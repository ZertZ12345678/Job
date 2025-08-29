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

                $ok = true;
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
        body {
            background: #f8fafc;
        }

        .card-premium {
            border: 1px solid #ffe08a;
            background: linear-gradient(135deg, #fff9e6, #fff);
            border-radius: 1.25rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, .06);
        }

        .price-box {
            background: #fff;
            border: 1px solid #f1e2b4;
            border-radius: .85rem;
            padding: .8rem 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .price-old {
            text-decoration: line-through;
            color: #6c757d;
            margin-right: .6rem;
        }

        .price-new {
            font-weight: 800;
            font-size: 1.35rem;
            color: #198754;
        }

        .badge-save {
            background: #198754;
            color: #fff;
            padding: .25rem .55rem;
            border-radius: .5rem;
            font-weight: 600;
        }

        .sparkle {
            background: linear-gradient(90deg, rgba(255, 193, 7, .25), rgba(255, 193, 7, .65), rgba(255, 193, 7, .25));
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
            background: #fff7e6;
            border: 1px solid #ffd37a;
        }

        .gateway-box {
            background: #eef6ff;
            border: 1px solid #b6d4fe;
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold text-warning" href="user_home.php">JobHive</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav"><span class="navbar-toggler-icon"></span></button>
            <div class="collapse navbar-collapse justify-content-end" id="nav">
                <ul class="navbar-nav">
                    <li class="nav-item"><a class="nav-link" href="user_home.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="user_dashboard.php">Dashboard</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <main class="container py-5" style="max-width:900px;">
        <?php if ($ok): ?>
            <div class="alert alert-success border-success">
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
                                    <div class="col-md-4 d-flex align-items-end">
                                        <div class="small text-muted">Test only — no real charge.</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- PayPal -->
                        <div class="col-12" id="paypalSection" style="display:none;">
                            <div class="p-3 rounded gateway-box">
                                <h6 class="mb-2"><i class="bi bi-paypal me-1"></i> PayPal</h6>
                                <p class="small text-muted mb-2">Enter your PayPal email. We’ll simulate a secure checkout.</p>
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
    </script>
</body>

</html>