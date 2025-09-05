<?php
include("connect.php");
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
  if (strlen($pw) < NEW_PW_MIN_LEN) return [false, "Password must be at least " . NEW_PW_MIN_LEN . " characters."];
  if (preg_match('/\s/', $pw))      return [false, "Password cannot contain spaces."];
  foreach (PW_BLOCKLIST as $bad) {
    if (strcasecmp($pw, $bad) === 0) return [false, "That password is too common. Please choose another."];
  }
  if (!preg_match(PW_POLICY_REGEX, $pw)) return [false, PW_POLICY_HUMAN];
  // Optional: disallow 3 same chars in a row
  if (preg_match('/(.)\1\1/', $pw)) return [false, "Avoid repeating any character 3+ times in a row."];
  return [true, ""];
}
if ($_SERVER["REQUEST_METHOD"] == "POST") {
  // ---- Inputs ----
  $company_name = trim($_POST['company_name'] ?? '');
  $email        = strtolower(trim($_POST['email'] ?? ''));
  $password     = $_POST['password'] ?? '';
  $phone        = trim($_POST['phone'] ?? '');
  $address      = trim($_POST['address'] ?? '');
  $c_detail     = trim($_POST['c_detail'] ?? ''); // Company Detail
  $logo         = "";
  // ---- Basic validation ----
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $message = "<div class='alert alert-danger custom-error text-center'>Invalid email format.</div>";
  } else {
    // Strong password (server-side gatekeeper)
    [$okStrong, $why] = isStrongPassword($password);
    if (!$okStrong) {
      $message = "<div class='alert alert-danger custom-error text-center'>Weak password: " . htmlspecialchars($why) . "</div>";
    }
  }
  // ---- Validate logo upload (required) ----
  if ($message === "") {
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] == 0) {
      $allowed  = ["jpg" => "image/jpeg", "jpeg" => "image/jpeg", "png" => "image/png"];
      $ext      = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
      $filetype = $_FILES['logo']['type'];
      if (!array_key_exists($ext, $allowed) || !in_array($filetype, $allowed)) {
        $message = "<div class='alert alert-danger custom-error text-center'>Invalid logo file type. Only JPG and PNG allowed.</div>";
      } else {
        $target_dir = "company_logos/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        $filename    = uniqid("logo_") . "." . $ext;
        $target_file = $target_dir . $filename;
        if (move_uploaded_file($_FILES["logo"]["tmp_name"], $target_file)) {
          $logo = $filename;
        } else {
          $message = "<div class='alert alert-danger custom-error text-center'>Failed to upload logo.</div>";
        }
      }
    } else {
      $message = "<div class='alert alert-danger custom-error text-center'>Please upload a company logo.</div>";
    }
  }
  // ---- Continue only if no prior error ----
  if ($message === "") {
    // Unique email & phone check
    $stmt = $pdo->prepare("SELECT company_id, email, phone FROM companies WHERE email = ? OR phone = ? LIMIT 1");
    $stmt->execute([$email, $phone]);
    $exists = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($exists) {
      if (isset($exists['email']) && strcasecmp($exists['email'], $email) === 0) {
        $message = "<div class='alert alert-danger custom-error text-center'>This email is already registered.</div>";
      } else {
        $message = "<div class='alert alert-danger custom-error text-center'>This phone number is already registered.</div>";
      }
    } else {
      // NOTE: For real apps, hash the password (e.g., password_hash). You asked to keep plain text.
      $stmt = $pdo->prepare("
        INSERT INTO companies (company_name, email, password, phone, address, c_detail, logo)
        VALUES (?, ?, ?, ?, ?, ?, ?)
      ");
      $stmt->execute([$company_name, $email, $password, $phone, $address, $c_detail, $logo]);
      $message = "<div class='alert alert-success custom-success text-center' id='register-alert'>Registration Successful!</div>";
      $register_success = true;
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>JobHive | Company SignUp</title>
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
      --layout-height: 855px;
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
      padding: 30px 20px;
      /* Increased padding for more space */
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

    /* Right side - Form - WIDER */
    .auth-right {
      display: flex;
      align-items: flex-start;
      /* Changed from center to flex-start */
      justify-content: center;
      padding: 30px 20px;
      /* Increased padding for more space */
      height: 100%;
      /* No scrollbar for desktop */
      overflow: hidden;
    }

    .form-card {
      width: 100%;
      max-width: 800px;
      /* Increased from 500px to 800px */
      background: var(--gold);
      border: 1px solid var(--ink);
      border-radius: 16px;
      padding: 30px 20px;
      /* Increased padding for more space */
      color: var(--ink);
      box-shadow: 0 8px 24px rgba(0, 0, 0, .25);
      /* No scrollbar for desktop */
      overflow: hidden;
      /* Ensure form fits in container */
      height: calc(100% - 60px);
      /* Account for padding */
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

    textarea.form-control {
      min-height: 90px;
      resize: vertical;
      max-height: 150px;
      /* Set max height for textarea */
      overflow-y: auto;
      /* Add scrollbar when content exceeds max height */
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

    /* File input styling */
    .form-control[type="file"] {
      padding: 6px 10px;
    }

    /* Form column layout */
    .form-columns {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 15px;
      margin-bottom: 10px;
    }

    .form-column {
      display: flex;
      flex-direction: column;
      gap: 10px;
    }

    .form-group {
      margin-bottom: 0;
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
        max-width: 700px;
        /* Adjusted for smaller screens */
        padding: 25px 15px;
        /* Adjusted padding */
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
        max-width: 600px;
        /* Adjusted for smaller screens */
        padding: 20px 15px;
        /* Adjusted padding */
      }

      .form-columns {
        grid-template-columns: 1fr;
      }

      /* Add scrollbar for medium screens */
      .auth-right {
        overflow-y: auto;
      }

      .form-card {
        overflow-y: auto;
        height: auto;
        max-height: calc(100% - 40px);
      }
    }

    @media (max-width: 768px) {
      :root {
        --layout-width: 95%;
        --layout-height: auto;
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

      /* Make the entire viewport scrollable on mobile */
      html,
      body {
        overflow: auto;
        height: auto;
      }

      .auth-viewport {
        position: relative;
        height: auto;
        min-height: 100vh;
        padding: 10px;
        overflow: visible;
        display: block;
      }

      .auth-container {
        position: relative;
        width: 100%;
        height: auto;
        min-height: auto;
        overflow: visible;
        border-radius: 16px;
        box-shadow: 0 8px 30px rgba(0, 0, 0, .15);
      }

      .auth-grid {
        position: relative;
        grid-template-columns: 1fr;
        height: auto;
      }

      /* Make brand card sticky on mobile */
      .auth-left {
        position: sticky;
        top: 0;
        z-index: 100;
        background: linear-gradient(135deg, var(--gold), var(--ink));
        padding: 15px 0;
        margin-bottom: 10px;
        justify-content: center;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
      }

      .brand-card {
        max-width: 160px;
        padding: 10px 8px;
        margin: 0 auto;
        left: 0;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
      }

      .brand-card img {
        width: 70px;
      }

      .auth-right {
        padding: 15px 10px;
        height: auto;
        overflow: visible;
        align-items: flex-start;
        padding-top: 20px;
        padding-bottom: 20px;
      }

      .form-card {
        width: 100%;
        max-width: 100%;
        padding: 20px 15px;
        /* Increased padding */
        border-radius: 12px;
        overflow: visible;
        height: auto;
        max-height: none;
        margin-top: 0;
        margin-bottom: 0;
      }

      .form-control {
        height: 36px;
      }

      textarea.form-control {
        max-height: 120px;
        /* Slightly smaller max height on mobile */
        overflow-y: auto;
        /* Ensure scrollbar appears when needed */
      }

      .btn-gold {
        height: 38px;
        margin-top: 15px;
        margin-bottom: 10px;
      }

      .form-columns {
        grid-template-columns: 1fr;
        gap: 8px;
      }

      .form-group {
        margin-bottom: 5px;
      }

      .small-link {
        margin-top: 5px;
        padding-bottom: 10px;
      }
    }

    /* ============== COMPLETELY NEW MOBILE LAYOUT ============== */
    @media (max-width: 480px) {

      /* Reset everything to basics */
      html,
      body {
        overflow: auto !important;
        height: auto !important;
        position: relative !important;
        margin: 0 !important;
        padding: 0 !important;
        background: #ffc107 !important;
        font-size: 14px !important;
      }

      /* Hide the complex layout */
      .auth-viewport {
        display: none !important;
      }

      /* Create a simple mobile container */
      .mobile-container {
        display: block !important;
        width: 100% !important;
        min-height: 100vh !important;
        background: #f8f9fa !important;
        padding: 0 !important;
        margin: 0 !important;
      }

      /* Mobile header with logo */
      .mobile-header {
        background: #22223b !important;
        padding: 30px 0 !important;
        text-align: center !important;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2) !important;
        position: relative !important;
        z-index: 100 !important;
      }

      .mobile-logo {
        width: 120px !important;
        height: 120px !important;
        margin: 0 auto 15px !important;
        background: #ffc107 !important;
        border-radius: 50% !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3) !important;
      }

      .mobile-logo img {
        width: 80px !important;
        height: 80px !important;
        object-fit: contain !important;
      }

      .mobile-title {
        color: #ffc107 !important;
        font-size: 1.8rem !important;
        font-weight: 700 !important;
        margin: 0 !important;
      }

      .mobile-subtitle {
        color: #f8f9fa !important;
        font-size: 1rem !important;
        margin: 5px 0 0 0 !important;
        opacity: 0.9 !important;
      }

      /* Mobile form section */
      .mobile-form {
        padding: 30px 20px !important;
      }

      .mobile-form-card {
        background: white !important;
        border-radius: 16px !important;
        padding: 25px 20px !important;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1) !important;
        margin-bottom: 30px !important;
      }

      .mobile-form-title {
        font-size: 1.5rem !important;
        color: #22223b !important;
        font-weight: 700 !important;
        margin: 0 0 25px 0 !important;
        text-align: center !important;
      }

      .mobile-form-group {
        margin-bottom: 20px !important;
      }

      .mobile-form-label {
        display: block !important;
        color: #22223b !important;
        font-weight: 600 !important;
        margin-bottom: 8px !important;
        font-size: 1rem !important;
      }

      .mobile-form-control {
        width: 100% !important;
        padding: 12px 15px !important;
        border: 1px solid #ddd !important;
        border-radius: 10px !important;
        font-size: 1rem !important;
        height: auto !important;
        background: white !important;
        color: #22223b !important;
        box-sizing: border-box !important;
      }

      .mobile-form-control:focus {
        border-color: #ffc107 !important;
        box-shadow: 0 0 0 0.2rem rgba(255, 193, 7, 0.25) !important;
        outline: none !important;
      }

      .mobile-textarea {
        min-height: 100px !important;
        resize: vertical !important;
      }

      .mobile-form-text {
        color: #666 !important;
        font-size: 0.85rem !important;
        margin-top: 5px !important;
      }

      .mobile-btn {
        width: 100% !important;
        padding: 14px 20px !important;
        background: #ffc107 !important;
        color: #22223b !important;
        border: none !important;
        border-radius: 10px !important;
        font-size: 1.1rem !important;
        font-weight: 700 !important;
        margin-top: 25px !important;
        margin-bottom: 15px !important;
        cursor: pointer !important;
        box-shadow: 0 4px 15px rgba(255, 193, 7, 0.3) !important;
      }

      .mobile-btn:hover {
        background: #e0a800 !important;
        color: #22223b !important;
      }

      .mobile-link {
        display: block !important;
        text-align: center !important;
        color: #22223b !important;
        font-size: 1rem !important;
        margin-top: 20px !important;
        padding-bottom: 30px !important;
        text-decoration: none !important;
      }

      .mobile-link .hl {
        font-weight: 700 !important;
        color: #22223b !important;
      }

      .mobile-alert {
        padding: 15px 20px !important;
        border-radius: 10px !important;
        margin-bottom: 20px !important;
        font-weight: 600 !important;
        text-align: center !important;
      }

      .mobile-alert-success {
        background: #fff8e6 !important;
        color: #22223b !important;
        border: 1px solid #ffc107 !important;
      }

      .mobile-alert-danger {
        background: #e74c3c !important;
        color: white !important;
        border: 1px solid #c0392b !important;
      }

      .mobile-pw-req {
        margin: 20px 0 !important;
        padding-left: 25px !important;
      }

      .mobile-pw-req li {
        margin-bottom: 8px !important;
        color: #666 !important;
        font-size: 0.9rem !important;
      }
    }
  </style>
</head>

<body>
  <!-- Original Desktop Layout -->
  <div class="auth-viewport">
    <div class="auth-container">
      <div class="auth-back"></div>
      <div class="auth-ink"></div>
      <div class="auth-grid">
        <!-- LEFT: Logo / brand -->
        <div class="auth-left">
          <div class="brand-card">
            <!-- Replace src with your final logo path when you send it -->
            <img src="jobhive_logo/jobhive.png" alt="JobHive Logo">
          </div>
        </div>
        <!-- RIGHT: Sign up form -->
        <div class="auth-right">
          <div class="form-card">
            <h2>Create your Company account</h2>
            <?php
            if ($message) {
              echo $message;
              if ($register_success) {
                echo "<script>
                  setTimeout(function(){ window.location.href = 'company_home.php'; }, 3000);
                </script>";
              }
            }
            ?>
            <form method="POST" enctype="multipart/form-data" autocomplete="off" novalidate>
              <!-- Two Column Layout -->
              <div class="form-columns">
                <!-- First Column: Company Name, Email, Password, Phone, Address -->
                <div class="form-column">
                  <div class="form-group">
                    <label for="company_name" class="form-label">Company Name</label>
                    <input type="text" class="form-control" id="company_name" name="company_name" required maxlength="80"
                      value="<?php echo htmlspecialchars($_POST['company_name'] ?? ''); ?>">
                  </div>
                  <div class="form-group">
                    <label for="email" class="form-label">Company Email</label>
                    <input type="email" class="form-control" id="email" name="email" required maxlength="100"
                      value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                  </div>
                  <div class="form-group">
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
                  </div>
                  <div class="form-group">
                    <label for="phone" class="form-label">Company Phone</label>
                    <input type="tel" class="form-control" id="phone" name="phone" required pattern="[0-9]{7,15}" maxlength="15"
                      value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                    <div class="form-text">Enter only digits, e.g. 0912345678</div>
                  </div>
                  <div class="form-group">
                    <label for="address" class="form-label">Company Address</label>
                    <input type="text" class="form-control" id="address" name="address" required maxlength="180"
                      value="<?php echo htmlspecialchars($_POST['address'] ?? ''); ?>">
                  </div>
                </div>
                <!-- Second Column: Company Details and Company Logo -->
                <div class="form-column">
                  <div class="form-group">
                    <label for="c_detail" class="form-label">Company Details</label>
                    <textarea class="form-control" id="c_detail" name="c_detail" placeholder="Brief company description, services, branches, etc." maxlength="5000"><?php echo htmlspecialchars($_POST['c_detail'] ?? ''); ?></textarea>
                    <div class="form-text">Brief company description, services, branches, etc.</div>
                  </div>
                  <div class="form-group">
                    <label for="logo" class="form-label">Company Logo</label>
                    <input type="file" class="form-control" id="logo" name="logo" required accept="image/png, image/jpeg">
                    <div class="form-text">Upload JPG or PNG only. Max size ~2MB.</div>
                  </div>
                </div>
              </div>
              <div class="form-text mt-1">
                <?php echo htmlspecialchars(PW_POLICY_HUMAN); ?>
              </div>
              <button type="submit" class="btn btn-gold w-100">REGISTER COMPANY</button>
              <a href="login.php" class="small-link">Already have an account? <span class="hl">Login</span></a>
            </form>
            <!-- Live password checklist -->
            <div class="form-text mt-1" id="pw-helper-wrap"></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Mobile Layout (only visible on small screens) -->
  <div class="mobile-container" style="display: none;">
    <div class="mobile-header">
      <div class="mobile-logo">
        <img src="jobhive_logo/jobhive.png" alt="JobHive Logo">
      </div>
      <h1 class="mobile-title">JobHive</h1>
      <p class="mobile-subtitle">Find Your Dream Job</p>
    </div>

    <div class="mobile-form">
      <div class="mobile-form-card">
        <h2 class="mobile-form-title">Create your Company account</h2>

        <?php
        if ($message) {
          $mobile_class = strpos($message, 'alert-success') !== false ? 'mobile-alert-success' : 'mobile-alert-danger';
          echo '<div class="mobile-alert ' . $mobile_class . '">' . strip_tags($message, '<div><span><a><strong><em>') . '</div>';

          if ($register_success) {
            echo "<script>
              setTimeout(function(){ window.location.href = 'company_home.php'; }, 3000);
            </script>";
          }
        }
        ?>

        <form method="POST" enctype="multipart/form-data" autocomplete="off" novalidate>
          <div class="mobile-form-group">
            <label for="mobile_company_name" class="mobile-form-label">Company Name</label>
            <input type="text" class="mobile-form-control" id="mobile_company_name" name="company_name" required maxlength="80"
              value="<?php echo htmlspecialchars($_POST['company_name'] ?? ''); ?>">
          </div>

          <div class="mobile-form-group">
            <label for="mobile_email" class="mobile-form-label">Company Email</label>
            <input type="email" class="mobile-form-control" id="mobile_email" name="email" required maxlength="100"
              value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
          </div>

          <div class="mobile-form-group">
            <label for="mobile_password" class="mobile-form-label">Password</label>
            <input type="password" class="mobile-form-control" id="mobile_password" name="password" required
              minlength="<?php echo NEW_PW_MIN_LEN; ?>"
              pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()\-\_\=\+\[\]\{\};:,.?~])(?!.*\s).{8,}"
              title="<?php echo htmlspecialchars(PW_POLICY_HUMAN); ?>">
          </div>

          <div class="mobile-form-group">
            <label for="mobile_phone" class="mobile-form-label">Company Phone</label>
            <input type="tel" class="mobile-form-control" id="mobile_phone" name="phone" required pattern="[0-9]{7,15}" maxlength="15"
              value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
            <div class="mobile-form-text">Enter only digits, e.g. 0912345678</div>
          </div>

          <div class="mobile-form-group">
            <label for="mobile_address" class="mobile-form-label">Company Address</label>
            <input type="text" class="mobile-form-control" id="mobile_address" name="address" required maxlength="180"
              value="<?php echo htmlspecialchars($_POST['address'] ?? ''); ?>">
          </div>

          <div class="mobile-form-group">
            <label for="mobile_c_detail" class="mobile-form-label">Company Details</label>
            <textarea class="mobile-form-control mobile-textarea" id="mobile_c_detail" name="c_detail" placeholder="Brief company description, services, branches, etc." maxlength="5000"><?php echo htmlspecialchars($_POST['c_detail'] ?? ''); ?></textarea>
            <div class="mobile-form-text">Brief company description, services, branches, etc.</div>
          </div>

          <div class="mobile-form-group">
            <label for="mobile_logo" class="mobile-form-label">Company Logo</label>
            <input type="file" class="mobile-form-control" id="mobile_logo" name="logo" required accept="image/png, image/jpeg">
            <div class="mobile-form-text">Upload JPG or PNG only. Max size ~2MB.</div>
          </div>

          <div class="mobile-form-text">
            <?php echo htmlspecialchars(PW_POLICY_HUMAN); ?>
          </div>

          <ul class="mobile-pw-req">
            <li>≥ 8 characters</li>
            <li>1 lowercase</li>
            <li>1 uppercase</li>
            <li>1 digit</li>
            <li>1 special (!@#$%^&*() -_=+[]{};:,.?~)</li>
            <li>no spaces</li>
            <li>no 3 repeated chars</li>
          </ul>

          <button type="submit" class="mobile-btn">REGISTER COMPANY</button>
        </form>

        <a href="login.php" class="mobile-link">Already have an account? <span class="hl">Login</span></a>
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