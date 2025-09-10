<?php
include("connect.php");
if (session_status() === PHP_SESSION_NONE) session_start();
/* =================== Auth: company only =================== */
$company_id = $_SESSION['company_id'] ?? null;
if (!$company_id) {
    header("Location: login.php");
    exit;
}
/* =================== Constants (Promotions) =================== */
const JH_BASE_FEE   = 50000; // MMK base price per post
const TIER_1_MIN    = 5;     // >=5  posts -> 10%
const TIER_2_MIN    = 15;    // >=15 posts -> 15%
const TIER_3_MIN    = 25;    // >=25 posts -> 20%
function promo_for_total_posts(int $total): array
{
    // Returns [discount_rate_float, member_tier]
    if ($total >= TIER_3_MIN) return [0.20, 'diamond'];
    if ($total >= TIER_2_MIN) return [0.15, 'platinum'];
    if ($total >= TIER_1_MIN) return [0.10, 'gold'];
    return [0.00, 'normal'];
}
function price_after_discount(int $base, float $rate): int
{
    return (int) round($base * (1 - $rate), 0);
}
/* =================== Fetch company info =================== */
$st = $pdo->prepare("SELECT company_name, address, member FROM companies WHERE company_id=? LIMIT 1");
$st->execute([$company_id]);
$company = $st->fetch(PDO::FETCH_ASSOC) ?: ['company_name' => '', 'address' => '', 'member' => 'normal'];
/* =================== Compute upcoming promo (for UI preview) =================== */
$stCount = $pdo->prepare("SELECT COUNT(*) FROM jobs WHERE company_id = ?");
$stCount->execute([$company_id]);
$existing_posts = (int) $stCount->fetchColumn();
$future_total   = $existing_posts + 1; // after this post
list($ui_rate, $ui_member) = promo_for_total_posts($future_total);
$ui_fee = price_after_discount(JH_BASE_FEE, $ui_rate);
/* =================== Payment receiver info =================== */
$payee_name  = "Phone Thaw Naing";
$payee_phone = "09957433847";
/* =================== Page state =================== */
$success = '';
$error   = '';
$form_data = $_POST ?? [];
/* =================== Helper =================== */
function e($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
/* =================== Handle POST =================== */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $job_title           = trim($_POST['job_title'] ?? '');
    $job_description     = trim($_POST['job_description'] ?? '');
    $description_detail  = trim($_POST['description_detail'] ?? '');
    $job_type            = $_POST['job_type'] ?? '';
    $employment_type     = $_POST['employment_type'] ?? '';
    $salary              = trim($_POST['salary'] ?? '');
    $requirements        = trim($_POST['requirements'] ?? '');
    $deadline            = $_POST['deadline'] ?? '';
    $posted_at           = date("Y-m-d H:i:s");
    $status              = 'Active';
    // Payment fields
    $payment_method = trim($_POST['payment_method'] ?? '');
    $wallet_txn     = trim($_POST['wallet_txn'] ?? '');
    $card_no        = preg_replace('/\s+/', '', (string)($_POST['card_no'] ?? ''));
    $card_name      = trim($_POST['card_name'] ?? '');
    $card_exp       = trim($_POST['card_exp'] ?? '');
    $card_cvc       = trim($_POST['card_cvc'] ?? '');
    $paypal_email   = trim($_POST['paypal_email'] ?? '');
    // Validate required job fields
    if (!($job_title && $job_description && $description_detail && $job_type && $employment_type && $salary && $requirements && $deadline)) {
        $error = "Please fill in all required fields (including Description Detail and Job Type).";
    }
    // Validate payment method + extras
    $valid_methods = ['KPay', 'AyaPay', 'Wave Pay', 'Visa Card', 'PayPal'];
    if (!$error && !in_array($payment_method, $valid_methods, true)) {
        $error = "Please choose a valid payment method.";
    }
    if (!$error) {
        if (in_array($payment_method, ['KPay', 'AyaPay', 'Wave Pay'], true)) {
            if ($wallet_txn === '') $error = "Please enter your wallet Transaction ID.";
        } elseif ($payment_method === 'Visa Card') {
            if ($card_no === '' || $card_name === '' || $card_exp === '' || $card_cvc === '') {
                $error = "Please fill all card fields.";
            }
        } elseif ($payment_method === 'PayPal') {
            if ($paypal_email === '') $error = "Please enter your PayPal email.";
        }
    }
    if (!$error) {
        try {
            $pdo->beginTransaction();
            // Re-check count in TX
            $st = $pdo->prepare("SELECT COUNT(*) FROM jobs WHERE company_id=? FOR UPDATE");
            $st->execute([$company_id]);
            $current_posts = (int) $st->fetchColumn();
            $new_total_posts = $current_posts + 1;
            list($rate, $memberTierAfter) = promo_for_total_posts($new_total_posts);
            $final_fee = price_after_discount(JH_BASE_FEE, $rate);
            // 1) Insert job
            $ins = $pdo->prepare("
                INSERT INTO jobs
                    (company_id, job_title, job_description, description_detail, job_type, location, salary, employment_type, requirements, posted_at, deadline, status)
                VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $ins->execute([
                $company_id,
                $job_title,
                $job_description,
                $description_detail,
                $job_type,
                $company['address'],
                $salary,
                $employment_type,
                $requirements,
                $posted_at,
                $deadline,
                $status
            ]);
            $job_id = (int)$pdo->lastInsertId();
            // 2) Optionally update company member tier
            // Uncomment if you want the company tier to immediately reflect the new total
            /*
            $upd = $pdo->prepare("UPDATE companies SET member = ? WHERE company_id = ?");
            $upd->execute([$memberTierAfter, $company_id]);
            */
            // 3) Build payment reference
            if (in_array($payment_method, ['KPay', 'AyaPay', 'Wave Pay'], true)) {
                $reference = $wallet_txn;
            } elseif ($payment_method === 'Visa Card') {
                $reference = 'card_last4:' . substr($card_no, -4);
            } else {
                $reference = $paypal_email; // PayPal
            }
            // 4) Insert payment record
            $payment_status = 'Completed';
            $q = "
                INSERT INTO post_payment (company_id, amount, payment_date, payment_status, job_id, payment_method, reference)
                VALUES (?, ?, NOW(), ?, ?, ?, ?)
            ";
            $payment_stmt = $pdo->prepare($q);
            $payment_stmt->execute([
                $company_id,
                $final_fee,
                $payment_status,
                $job_id,
                $payment_method,
                $reference
            ]);
            $pdo->commit();
            $success = "Job posted successfully! You were charged " . number_format($final_fee) . " MMK (" . (int)round($rate * 100) . "% off, tier after post: " . strtoupper($memberTierAfter) . ").";
            $form_data = [];
            // Refresh UI preview vars after commit
            $existing_posts = $new_total_posts;
            $future_total   = $existing_posts + 1;
            list($ui_rate, $ui_member) = promo_for_total_posts($future_total);
            $ui_fee = price_after_discount(JH_BASE_FEE, $ui_rate);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = "Error posting job/payment: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>JobHive | Post Job</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap + Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --bg-primary: #f7f9fb;
            --bg-secondary: #ffffff;
            --bg-tertiary: #f6f8fa;
            --bg-card: #ffffff;
            --bg-promo: #e8f6ff;
            --bg-promo-stat: #ffffffcc;
            --bg-input: #f6f8fa;
            --bg-footer: #212529;
            --text-primary: #22223b;
            --text-secondary: #495057;
            --text-muted: #6c757d;
            --text-white: #ffffff;
            --border-color: #dee2e6;
            --navbar-bg: #ffffff;
            --navbar-text: #22223b;
            --navbar-border: #dee2e6;
            --card-shadow: 0 8px 24px rgba(0, 0, 0, .06);
            --btn-primary-bg: #ffb200;
            --btn-primary-text: #22223b;
            --btn-primary-hover: #ffa500;
            --promo-accent: #0ea5e9;
            --promo-ink: #0f3b57;
            --promo-ring: rgba(14, 165, 233, .35);
            --promo-border: rgba(14, 165, 233, .18);
            --gateway-bg: #eef6ff;
            --gateway-border: #b6d4fe;
            --wallet-bg: #fff7e6;
            --wallet-border: #ffd37a;
        }

        [data-theme="dark"] {
            --bg-primary: #121212;
            --bg-secondary: #1e1e1e;
            --bg-tertiary: #2d2d2d;
            --bg-card: #1e1e1e;
            --bg-promo: #1a3a52;
            --bg-promo-stat: #2a4a62;
            --bg-input: #2d2d2d;
            --bg-footer: #1e1e1e;
            --text-primary: #ffffff;
            --text-secondary: #ffffff;
            --text-muted: #ffffff;
            --text-white: #ffffff;
            --border-color: #343a40;
            --navbar-bg: #1e1e1e;
            --navbar-text: #ffffff;
            --navbar-border: #343a40;
            --card-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
            --btn-primary-bg: #ffb200;
            --btn-primary-text: #22223b;
            --btn-primary-hover: #ffa500;
            --promo-accent: #4fb3d9;
            --promo-ink: #e1f5fe;
            --promo-ring: rgba(79, 179, 217, .35);
            --promo-border: rgba(79, 179, 217, .18);
            --gateway-bg: #1a3a52;
            --gateway-border: #4fb3d9;
            --wallet-bg: #3a2a1a;
            --wallet-border: #d9b34f;
        }

        body {
            background-color: var(--bg-primary);
            color: var(--text-primary);
            font-family: 'Segoe UI', Arial, sans-serif;
            transition: background-color 0.3s, color 0.3s;
        }

        .post-job-container {
            max-width: 720px;
            margin: 60px auto 0;
            background: var(--bg-card);
            padding: 35px 30px 30px;
            border-radius: 22px;
            box-shadow: var(--card-shadow);
            transition: background-color 0.3s, box-shadow 0.3s;
            position: relative;
        }

        h3 {
            font-weight: 700;
            color: var(--btn-primary-bg);
            letter-spacing: .5px;
            margin-bottom: 16px;
            text-align: center;
        }

        label {
            font-weight: 500;
            margin-bottom: 6px;
            color: var(--text-primary);
        }

        .form-control,
        .form-select {
            border-radius: 10px;
            font-size: 1rem;
            background: var(--bg-input);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            transition: background-color 0.3s, border-color 0.3s, color 0.3s;
        }

        .form-control:focus,
        .form-select:focus {
            background-color: var(--bg-input);
            color: var(--text-primary);
            border-color: var(--btn-primary-bg);
        }

        .form-control-plaintext {
            color: var(--text-primary);
        }

        .btn-warning {
            font-weight: 600;
            font-size: 1.05rem;
            padding: 10px 0;
            border-radius: 11px;
            width: 100%;
            background-color: var(--btn-primary-bg);
            border: none;
            box-shadow: 0 2px 8px rgba(255, 178, 0, .10);
            color: var(--btn-primary-text);
            transition: background-color 0.3s, color 0.3s;
        }

        .btn-warning:hover {
            background-color: var(--btn-primary-hover);
            color: var(--text-white);
        }

        .btn-secondary {
            background-color: var(--bg-tertiary);
            border-color: var(--border-color);
            color: var(--text-primary);
            transition: background-color 0.3s, border-color 0.3s, color 0.3s;
        }

        .btn-outline-dark {
            color: var(--text-primary);
            border-color: var(--border-color);
            background-color: transparent;
            transition: all 0.3s;
        }

        .btn-outline-dark:hover {
            background-color: var(--bg-tertiary);
            border-color: var(--border-color);
            color: var(--text-primary);
        }

        .alert-success,
        .alert-danger {
            border-radius: 10px;
            text-align: center;
            font-weight: 500;
            transition: background-color 0.3s, border-color 0.3s, color 0.3s;
        }

        .alert-success {
            background-color: var(--bg-promo);
            border-color: var(--promo-border);
            color: var(--text-primary);
        }

        .alert-danger {
            background-color: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }

        [data-theme="dark"] .alert-danger {
            background-color: #721c24;
            border-color: #a71e2a;
            color: #f8d7da;
        }

        .pay-method-img {
            width: 70px;
            height: 70px;
            object-fit: contain;
            border-radius: 12px;
            cursor: pointer;
            border: 2px solid transparent;
            transition: border-color .2s;
            background: var(--bg-secondary);
        }

        .pay-method-img.selected {
            border: 2px solid var(--btn-primary-bg);
            box-shadow: 0 2px 10px rgba(255, 178, 0, .18);
        }

        #payInfoBox {
            max-width: 420px;
            margin-left: auto;
            margin-right: auto;
        }

        .gateway-box {
            background: var(--gateway-bg);
            border: 1px solid var(--gateway-border);
            transition: background-color 0.3s, border-color 0.3s;
        }

        .wallet-box {
            background: var(--wallet-bg);
            border: 1px solid var(--wallet-border);
            transition: background-color 0.3s, border-color 0.3s;
        }

        .copy-btn {
            font-size: .98rem;
            padding: 1px 8px;
            margin-left: 6px;
        }

        /* ====== Attractive Promo Box for Companies ====== */
        .promo-card {
            background: linear-gradient(135deg, var(--bg-promo), var(--bg-promo));
            border: 1px solid var(--promo-border);
            color: var(--promo-ink);
            border-radius: 18px;
            padding: 18px 18px 14px;
            box-shadow: 0 10px 24px rgba(14, 165, 233, .08);
            margin: 10px auto 24px;
            transition: background-color 0.3s, border-color 0.3s, color 0.3s;
        }

        .promo-top {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 10px;
        }

        .promo-icon {
            width: 38px;
            height: 38px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: var(--bg-secondary);
            color: var(--promo-accent);
            font-size: 20px;
            box-shadow: 0 6px 14px rgba(14, 165, 233, .15);
            border: 1px solid var(--promo-ring);
            transition: background-color 0.3s, color 0.3s;
        }

        .promo-eyebrow {
            font-size: .85rem;
            letter-spacing: .4px;
            text-transform: uppercase;
            color: var(--promo-accent);
            opacity: .85;
            font-weight: 700;
        }

        .promo-title {
            font-weight: 800;
            font-size: 1.1rem;
            line-height: 1.1;
        }

        .promo-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin: 12px 0 6px;
        }

        .promo-stat {
            background: var(--bg-promo-stat);
            border: 1px solid var(--promo-border);
            border-radius: 14px;
            padding: 10px 12px;
            text-align: center;
            transition: background-color 0.3s, border-color 0.3s;
        }

        .promo-stat .label {
            font-size: .8rem;
            color: var(--promo-accent);
            font-weight: 600;
            letter-spacing: .2px;
        }

        .promo-stat .value {
            font-size: 1.15rem;
            font-weight: 800;
            margin-top: 4px;
            color: var(--text-primary);
        }

        .promo-stat .value .unit {
            font-size: .8rem;
            font-weight: 700;
            color: var(--text-secondary);
        }

        .promo-stat .sub {
            font-size: .78rem;
            color: var(--text-muted);
            margin-top: 2px;
        }

        .promo-note {
            margin-top: 6px;
            text-align: center;
            font-size: .92rem;
            color: var(--text-primary);
        }

        .form-text {
            color: var(--text-muted);
        }

        small {
            color: var(--text-muted);
        }

        .text-danger {
            color: #dc3545 !important;
        }

        /* Placeholder text fix for dark mode */
        [data-theme="dark"] input::placeholder,
        [data-theme="dark"] textarea::placeholder {
            color: #ffffff !important;
            opacity: 0.8 !important;
        }

        [data-theme="dark"] .form-control::placeholder {
            color: #ffffff !important;
            opacity: 0.8 !important;
        }

        /* PayPal instruction text fix for dark mode */
        [data-theme="dark"] #paypalSection p.small.text-muted {
            color: #ffffff !important;
        }

        @media (max-width:600px) {
            .promo-stats {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 600px) {
            .post-job-container {
                padding: 20px 7px;
            }

            .pay-method-img {
                width: 48px;
                height: 48px;
            }
        }

        /* Theme toggle button */
        .theme-toggle {
            position: absolute;
            top: 15px;
            right: 15px;
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
            z-index: 10;
        }

        .theme-toggle:hover {
            background: var(--bg-tertiary);
        }
    </style>
</head>

<body>
    <div class="post-job-container">
        <!-- Theme Toggle Button -->
        <button class="theme-toggle" id="themeToggle" aria-label="Toggle theme">
            <i class="bi bi-sun-fill" id="themeIcon"></i>
        </button>

        <h3>Post a Job</h3>
        <!-- Attractive membership/promo banner -->
        <div class="promo-card">
            <div class="promo-top">
                <div class="promo-icon"><i class="bi bi-megaphone-fill"></i></div>
                <div class="promo-headings">
                    <div class="promo-eyebrow">Membership Savings Preview</div>
                    <div class="promo-title">Your next post unlocks better pricing</div>
                </div>
            </div>
            <div class="promo-stats">
                <div class="promo-stat">
                    <div class="label">Next Tier</div>
                    <div class="value"><?= e(strtoupper($ui_member)) ?></div>
                </div>
                <div class="promo-stat">
                    <div class="label">Discount</div>
                    <div class="value"><?= (int)round($ui_rate * 100) ?>%</div>
                </div>
                <div class="promo-stat">
                    <div class="label">Price This Post</div>
                    <div class="value"><?= number_format($ui_fee) ?> <span class="unit">MMK</span></div>
                    <div class="sub">Base <?= number_format(JH_BASE_FEE) ?></div>
                </div>
            </div>
            <div class="promo-note">
                The more you post, the more you save — <strong>10% after 5 posts</strong>,
                <strong>15% after 15 posts</strong>, and <strong>20% once you reach 25 posts</strong>.
            </div>
        </div>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= e($success) ?></div>
        <?php elseif ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>
        <form method="post" autocomplete="off">
            <div class="row g-3">
                <div class="col-md-6">
                    <label>Company Name</label>
                    <input type="text" class="form-control" value="<?= e($company['company_name']) ?>" readonly>
                </div>
                <div class="col-md-6">
                    <label>Location</label>
                    <input type="text" class="form-control" value="<?= e($company['address']) ?>" readonly>
                </div>
                <div class="col-12">
                    <label>Job Title <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="job_title" required value="<?= e($form_data['job_title'] ?? '') ?>">
                </div>
                <div class="col-12">
                    <label>Job Description <span class="text-danger">*</span></label>
                    <textarea class="form-control" name="job_description" rows="4" required><?= e($form_data['job_description'] ?? '') ?></textarea>
                </div>
                <div class="col-12">
                    <label>Description Detail <span class="text-danger">*</span></label>
                    <textarea class="form-control" name="description_detail" rows="4" required><?= e($form_data['description_detail'] ?? '') ?></textarea>
                    <div class="form-text">Add a richer, longer description (e.g., team, project scope, benefits, tech stack, interview process).</div>
                </div>
                <div class="col-md-6">
                    <label>Job Type <span class="text-danger">*</span></label>
                    <select class="form-select" name="job_type" required>
                        <option value="">Select Job Type</option>
                        <option value="Software" <?= (($form_data['job_type'] ?? '') === 'Software') ? 'selected' : '' ?>>Software</option>
                        <option value="Network" <?= (($form_data['job_type'] ?? '') === 'Network')  ? 'selected' : '' ?>>Network</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label>Employment Type <span class="text-danger">*</span></label>
                    <select class="form-select" name="employment_type" required>
                        <option value="">Select Type</option>
                        <option value="Full Time" <?= (($form_data['employment_type'] ?? '') == 'Full Time') ? 'selected' : '' ?>>Full Time</option>
                        <option value="Part Time" <?= (($form_data['employment_type'] ?? '') == 'Part Time') ? 'selected' : '' ?>>Part Time</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label>Salary <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="salary" required value="<?= e($form_data['salary'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label>Deadline <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" name="deadline" required value="<?= e($form_data['deadline'] ?? '') ?>">
                </div>
                <div class="col-12">
                    <label>Requirement <span class="text-danger">*</span></label>
                    <textarea class="form-control" name="requirements" rows="2" required><?= e($form_data['requirements'] ?? '') ?></textarea>
                </div>
                <!-- Dynamic Posting Fee (discounted) -->
                <div class="col-md-6">
                    <label>Posting Fee (MMK)</label>
                    <input type="number" class="form-control" value="<?= $ui_fee ?>" name="posting_fee" readonly>
                </div>
                <!-- Payment Method (images/buttons) -->
                <div class="col-12">
                    <label>Choose Payment Method <span class="text-danger">*</span></label>
                    <div class="d-flex flex-wrap gap-3 align-items-center" id="payment-methods">
                        <img src="payment_logos/kpay.webp" alt="KPay" class="pay-method-img<?= (($form_data['payment_method'] ?? '') == 'KPay')      ? ' selected' : '' ?>" data-method="KPay">
                        <img src="payment_logos/ayapay.png" alt="AyaPay" class="pay-method-img<?= (($form_data['payment_method'] ?? '') == 'AyaPay')    ? ' selected' : '' ?>" data-method="AyaPay">
                        <img src="payment_logos/wavepay.png" alt="Wave Pay" class="pay-method-img<?= (($form_data['payment_method'] ?? '') == 'Wave Pay')  ? ' selected' : '' ?>" data-method="Wave Pay">
                        <img src="payment_logos/visa.png" alt="Visa Card" class="pay-method-img<?= (($form_data['payment_method'] ?? '') == 'Visa Card') ? ' selected' : '' ?>" data-method="Visa Card">
                        <img src="payment_logos/paypal.png" alt="PayPal" class="pay-method-img<?= (($form_data['payment_method'] ?? '') == 'PayPal')    ? ' selected' : '' ?>" data-method="PayPal">
                    </div>
                    <input type="hidden" name="payment_method" id="payment_method_input" required value="<?= e($form_data['payment_method'] ?? '') ?>">
                </div>
                <!-- Wallet instructions -->
                <div class="col-12" id="walletSection" style="display:none;">
                    <div class="p-3 rounded wallet-box">
                        <h6 class="mb-2"><i class="bi bi-phone me-1"></i> Wallet payment instructions</h6>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <div class="form-control-plaintext"><strong>Name:</strong> <?= e($payee_name) ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-control-plaintext"><strong>Phone:</strong> <?= e($payee_phone) ?></div>
                            </div>
                        </div>
                        <ol class="small mt-2 mb-3">
                            <li>Open your selected wallet (KPay / AyaPay / Wave Pay)</li>
                            <li>Send <strong><?= e(number_format($ui_fee)) ?> MMK</strong> to the contact above</li>
                            <li>Paste your <strong>Transaction ID</strong> below and submit</li>
                        </ol>
                        <label class="form-label">Transaction ID</label>
                        <input type="text" class="form-control" name="wallet_txn" id="wallet_txn" placeholder="e.g., KP123456789" value="<?= e($form_data['wallet_txn'] ?? '') ?>">
                    </div>
                </div>
                <!-- Visa -->
                <div class="col-12" id="cardSection" style="display:none;">
                    <div class="p-3 rounded gateway-box">
                        <h6 class="mb-2">Card details</h6>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label">Card number</label>
                                <input type="text" class="form-control" name="card_no" placeholder="4111 1111 1111 1111" inputmode="numeric" value="<?= e($form_data['card_no'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Name on card</label>
                                <input type="text" class="form-control" name="card_name" placeholder="As on card" value="<?= e($form_data['card_name'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Expiry (MM/YY)</label>
                                <input type="text" class="form-control" name="card_exp" placeholder="08/27" value="<?= e($form_data['card_exp'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">CVC</label>
                                <input type="text" class="form-control" name="card_cvc" placeholder="123" inputmode="numeric" value="<?= e($form_data['card_cvc'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                </div>
                <!-- PayPal -->
                <div class="col-12" id="paypalSection" style="display:none;">
                    <div class="p-3 rounded gateway-box">
                        <h6 class="mb-2">PayPal</h6>
                        <p class="small text-muted mb-2">Enter your PayPal email. We'll simulate a secure checkout.</p>
                        <label class="form-label">PayPal Email</label>
                        <input type="email" class="form-control" name="paypal_email" placeholder="you@example.com" value="<?= e($form_data['paypal_email'] ?? '') ?>">
                    </div>
                </div>
                <div class="col-12 d-flex gap-2 mt-2">
                    <button type="submit" class="btn btn-warning flex-fill">Post Job</button>
                    <button type="button" class="btn btn-secondary flex-fill" onclick="clearForm()">Cancel</button>
                    <a href="company_home.php" class="btn btn-outline-dark flex-fill">Go to Home Page</a>
                </div>
            </div>
        </form>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
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

            // Payment method selection
            const payMethods = document.querySelectorAll('.pay-method-img');
            const paymentInput = document.getElementById('payment_method_input');
            const wallet = document.getElementById('walletSection');
            const card = document.getElementById('cardSection');
            const paypal = document.getElementById('paypalSection');
            const txn = document.getElementById('wallet_txn');

            function showSections() {
                const v = (paymentInput.value || '').trim();
                const isWallet = (v === 'KPay' || v === 'AyaPay' || v === 'Wave Pay');
                wallet.style.display = isWallet ? '' : 'none';
                card.style.display = (v === 'Visa Card') ? '' : 'none';
                paypal.style.display = (v === 'PayPal') ? '' : 'none';
                if (txn) {
                    if (isWallet) txn.setAttribute('required', 'required');
                    else txn.removeAttribute('required');
                }
            }

            payMethods.forEach(img => {
                img.addEventListener('click', function() {
                    payMethods.forEach(i => i.classList.remove('selected'));
                    img.classList.add('selected');
                    paymentInput.value = img.getAttribute('data-method');
                    showSections();
                });
            });

            // Initialize on load for server-returned state
            showSections();
        });

        function clearForm() {
            document.querySelectorAll('.post-job-container input:not([readonly]):not([type=hidden]), .post-job-container textarea')
                .forEach(el => el.value = '');
            document.querySelectorAll('.post-job-container select').forEach(el => el.selectedIndex = 0);
            document.querySelectorAll('.pay-method-img').forEach(img => img.classList.remove('selected'));
            document.getElementById('payment_method_input').value = '';
            document.getElementById('walletSection').style.display = 'none';
            document.getElementById('cardSection').style.display = 'none';
            document.getElementById('paypalSection').style.display = 'none';
        }
    </script>
</body>

</html>