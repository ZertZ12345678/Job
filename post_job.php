<?php
include("connect.php");
session_start();

// Ensure company is logged in
$company_id = $_SESSION['company_id'] ?? null;
if (!$company_id) {
    header("Location: login.php");
    exit;
}

// Fetch company info for autofill
$stmt = $pdo->prepare("SELECT company_name, address FROM companies WHERE company_id=?");
$stmt->execute([$company_id]);
$company = $stmt->fetch(PDO::FETCH_ASSOC);

// Payment info
$payment_amount = 50000;
$payee_name = "Phone Thaw Naing";
$payee_phone = "09957433847";

$success = '';
$error = '';

// Keep form data for refill
$form_data = $_POST ?? [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $job_title         = trim($_POST['job_title'] ?? '');
    $job_description   = trim($_POST['job_description'] ?? '');
    $description_detail = trim($_POST['description_detail'] ?? '');  // NEW
    $employment_type   = $_POST['employment_type'] ?? '';
    $salary            = trim($_POST['salary'] ?? '');
    $requirements      = trim($_POST['requirements'] ?? '');
    $deadline          = $_POST['deadline'] ?? '';
    $posted_at         = date("Y-m-d H:i:s");
    $status            = 'Active';
    $payment_method    = $_POST['payment_method'] ?? '';

    if ($job_title && $job_description && $description_detail && $employment_type && $salary && $requirements && $deadline && $payment_method) {
        try {
            // Insert into jobs table (description_detail included)
            $stmt = $pdo->prepare("
                INSERT INTO jobs
                    (company_id, job_title, job_description, description_detail, location, salary, employment_type, requirements, posted_at, deadline, status)
                VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $company_id,
                $job_title,
                $job_description,
                $description_detail,              // NEW
                $company['address'],
                $salary,
                $employment_type,
                $requirements,
                $posted_at,
                $deadline,
                $status
            ]);
            $job_id = $pdo->lastInsertId();

            // Insert payment record (NO remarks column)
            $payment_status = 'Completed';
            $payment_stmt = $pdo->prepare("
                INSERT INTO post_payment (company_id, amount, payment_date, payment_status, job_id, payment_method)
                VALUES (?, ?, NOW(), ?, ?, ?)
            ");
            $payment_stmt->execute([
                $company_id,
                $payment_amount,
                $payment_status,
                $job_id,
                $payment_method
            ]);

            $success = "Job posted successfully!";
            $form_data = [];
        } catch (PDOException $e) {
            $error = "Error posting job/payment: " . $e->getMessage();
        }
    } else {
        $error = "Please fill in all required fields (including Description Detail) and select payment method.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>JobHive | Post Job</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #f7f9fb;
            font-family: 'Segoe UI', Arial, sans-serif;
        }

        .post-job-container {
            max-width: 520px;
            margin: 60px auto 0 auto;
            background: #fff;
            padding: 35px 30px 30px;
            border-radius: 22px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.06);
        }

        h3 {
            font-weight: 700;
            color: #ffb200;
            letter-spacing: .5px;
            margin-bottom: 32px;
            text-align: center;
        }

        label {
            font-weight: 500;
            margin-bottom: 6px;
        }

        .form-control,
        .form-select {
            border-radius: 10px;
            font-size: 1rem;
            background: #f6f8fa;
        }

        .btn-warning {
            font-weight: 600;
            font-size: 1.05rem;
            padding: 10px 0;
            border-radius: 11px;
            width: 100%;
            background-color: #ffb200;
            border: none;
            box-shadow: 0 2px 8px rgba(255, 178, 0, .10);
            transition: background .2s;
        }

        .btn-warning:hover {
            background-color: #ffa500;
        }

        .alert-success,
        .alert-danger {
            border-radius: 10px;
            text-align: center;
            font-weight: 500;
        }

        .pay-method-img {
            width: 70px;
            height: 70px;
            object-fit: contain;
            border-radius: 12px;
            cursor: pointer;
            border: 2px solid transparent;
            transition: border-color .2s;
            background: #fff;
        }

        .pay-method-img.selected {
            border: 2px solid #ffb200;
            box-shadow: 0 2px 10px rgba(255, 178, 0, .18);
        }

        #payInfoBox {
            max-width: 360px;
            margin-left: auto;
            margin-right: auto;
        }

        .copy-btn {
            font-size: .98rem;
            padding: 1px 8px;
            margin-left: 6px;
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
    </style>
</head>

<body>
    <div class="post-job-container">
        <h3>Post a Job</h3>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php elseif ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
            <div class="mb-3">
                <label>Company Name</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($company['company_name']) ?>" readonly>
            </div>
            <div class="mb-3">
                <label>Location</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($company['address']) ?>" readonly>
            </div>

            <div class="mb-3">
                <label>Job Title <span style="color:#dc3545;">*</span></label>
                <input type="text" class="form-control" name="job_title" required
                    value="<?= htmlspecialchars($form_data['job_title'] ?? '') ?>">
            </div>

            <div class="mb-3">
                <label>Job Description <span style="color:#dc3545;">*</span></label>
                <textarea class="form-control" name="job_description" rows="4" required><?= htmlspecialchars($form_data['job_description'] ?? '') ?></textarea>
            </div>

            <!-- NEW: Description Detail under Job Description -->
            <div class="mb-3">
                <label>Description Detail <span style="color:#dc3545;">*</span></label>
                <textarea class="form-control" name="description_detail" rows="4" required><?= htmlspecialchars($form_data['description_detail'] ?? '') ?></textarea>
                <div class="form-text">Add a richer, longer description (e.g., team, project scope, benefits, tech stack, interview process).</div>
            </div>

            <div class="mb-3">
                <label>Employment Type <span style="color:#dc3545;">*</span></label>
                <select class="form-select" name="employment_type" required>
                    <option value="">Select Type</option>
                    <option value="Full Time" <?= (($form_data['employment_type'] ?? '') == 'Full Time') ? 'selected' : '' ?>>Full Time</option>
                    <option value="Part Time" <?= (($form_data['employment_type'] ?? '') == 'Part Time') ? 'selected' : '' ?>>Part Time</option>
                </select>
            </div>

            <div class="mb-3">
                <label>Salary <span style="color:#dc3545;">*</span></label>
                <input type="text" class="form-control" name="salary" required
                    value="<?= htmlspecialchars($form_data['salary'] ?? '') ?>">
            </div>

            <div class="mb-3">
                <label>Requirement <span style="color:#dc3545;">*</span></label>
                <textarea class="form-control" name="requirements" rows="2" required><?= htmlspecialchars($form_data['requirements'] ?? '') ?></textarea>
            </div>

            <div class="mb-3">
                <label>Deadline <span style="color:#dc3545;">*</span></label>
                <input type="date" class="form-control" name="deadline" required
                    value="<?= htmlspecialchars($form_data['deadline'] ?? '') ?>">
            </div>

            <!-- Posting Fee -->
            <div class="mb-3">
                <label>Posting Fee (MMK)</label>
                <input type="number" class="form-control" value="<?= $payment_amount ?>" name="posting_fee" readonly>
            </div>

            <!-- Payment Method (images) -->
            <div class="mb-3">
                <label>Choose Payment Method <span style="color:#dc3545;">*</span></label>
                <div class="d-flex justify-content-between gap-3" id="payment-methods">
                    <img src="payment_logos/kpay.webp" alt="Kpay"
                        class="pay-method-img<?= (($form_data['payment_method'] ?? '') == 'Kpay') ? ' selected' : '' ?>"
                        data-method="Kpay">
                    <img src="payment_logos/ayapay.png" alt="AyaPay"
                        class="pay-method-img<?= (($form_data['payment_method'] ?? '') == 'AyaPay') ? ' selected' : '' ?>"
                        data-method="AyaPay">
                    <img src="payment_logos/wavepay.png" alt="WavePay"
                        class="pay-method-img<?= (($form_data['payment_method'] ?? '') == 'WavePay') ? ' selected' : '' ?>"
                        data-method="WavePay">
                </div>
                <input type="hidden" name="payment_method" id="payment_method_input" required
                    value="<?= htmlspecialchars($form_data['payment_method'] ?? '') ?>">
            </div>

            <!-- Payment Info (show after select) -->
            <div id="payInfoBox" class="border rounded p-3 mt-3 bg-light<?= (($form_data['payment_method'] ?? '') != '') ? '' : ' d-none' ?>">
                <div>
                    <span><b>Name:</b> <span id="acc_name"><?= $payee_name ?></span>
                        <button type="button" class="btn btn-outline-secondary btn-sm copy-btn" onclick="copyToClipboard('acc_name')">Copy</button>
                    </span>
                </div>
                <div class="mt-2">
                    <span><b>Phone No:</b> <span id="acc_phone"><?= $payee_phone ?></span>
                        <button type="button" class="btn btn-outline-secondary btn-sm copy-btn" onclick="copyToClipboard('acc_phone')">Copy</button>
                    </span>
                </div>
            </div>

            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-warning flex-fill">Post Job</button>
                <button type="button" class="btn btn-secondary flex-fill" onclick="clearForm()">Cancel</button>
                <a href="company_home.php" class="btn btn-outline-dark flex-fill">Go to Home Page</a>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const payMethods = document.querySelectorAll('.pay-method-img');
            const paymentInput = document.getElementById('payment_method_input');
            const payInfoBox = document.getElementById('payInfoBox');
            payMethods.forEach(img => {
                img.addEventListener('click', function() {
                    payMethods.forEach(i => i.classList.remove('selected'));
                    img.classList.add('selected');
                    paymentInput.value = img.getAttribute('data-method');
                    payInfoBox.classList.remove('d-none');
                });
            });
            if (paymentInput.value !== '') {
                payInfoBox.classList.remove('d-none');
            }
        });

        function copyToClipboard(id) {
            const text = document.getElementById(id).innerText;
            navigator.clipboard.writeText(text).then(function() {
                alert("Copied: " + text);
            });
        }

        function clearForm() {
            // Clear all input and textarea fields except readonly fields
            document.querySelectorAll('.post-job-container input:not([readonly]):not([type=hidden]), .post-job-container textarea').forEach(function(el) {
                el.value = '';
            });
            // Reset selects
            document.querySelectorAll('.post-job-container select').forEach(function(el) {
                el.selectedIndex = 0;
            });
            // Deselect payment images and clear hidden input
            document.querySelectorAll('.pay-method-img').forEach(function(img) {
                img.classList.remove('selected');
            });
            document.getElementById('payment_method_input').value = '';
            // Hide payment info box
            document.getElementById('payInfoBox').classList.add('d-none');
        }
    </script>
</body>

</html>