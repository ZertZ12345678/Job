<?php
include("connect.php");
if (session_status() === PHP_SESSION_NONE) session_start();

/* ===== 0) Auth / session ===== */
$company_id = $_SESSION['company_id'] ?? null;
if (!$company_id) {
    header("Location: login.php");
    exit;
}

/* ===== Helpers ===== */
function e($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

/** Return 1–2 letter initials from a name */
function initials_from_name($name): string
{
    $name = trim((string)$name);
    if ($name === '') return 'C';
    $parts = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY);
    $ini = '';
    foreach ($parts as $p) {
        $ini .= mb_strtoupper(mb_substr($p, 0, 1));
        if (mb_strlen($ini) >= 2) break;
    }
    return $ini ?: 'C';
}

/** Build square SVG avatar (data: URI) for initials */
function svg_avatar_data_uri(string $name, int $size = 112): string
{
    $ini    = initials_from_name($name);
    $bg     = '#FFF8E6';
    $ring   = '#FFC107';
    $txt    = '#FF8A00';
    $font   = (int) round($size * 0.42);
    $radius = 16;
    $inner  = $size - 4;

    $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="$size" height="$size" viewBox="0 0 $size $size">
  <rect x="2" y="2" width="$inner" height="$inner" rx="$radius" ry="$radius"
        fill="$bg" stroke="$ring" stroke-width="4"/>
  <text x="50%" y="50%" dy="0.32em" text-anchor="middle"
        font-family="Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif"
        font-weight="700" font-size="$font" fill="$txt">$ini</text>
</svg>
SVG;
    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

/** Disable attribute when field already has a value (same behavior as user profile) */
function field_edit_attr($val, $type = 'input')
{
    if (empty($val)) return '';
    return $type === 'select' ? 'disabled' : 'readonly';
}

/* ===== 1) Fetch current company ===== */
$company = [];
$error_message = '';
try {
    $stmt = $pdo->prepare("SELECT * FROM companies WHERE company_id = ?");
    $stmt->execute([$company_id]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    $error_message = "Could not load company profile: " . $e->getMessage();
}

/* ===== 2) Handle POST (update) ===== */
$success_message = '';
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $company_name = trim($_POST['company_name'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $phone        = trim($_POST['phone'] ?? '');
    $address      = trim($_POST['address'] ?? '');
    $c_detail     = trim($_POST['c_detail'] ?? '');   // <-- NEW: Detail Company

    // ---- Optional: logo upload ----
    $logo = null;
    if (isset($_FILES['logo']) && ($_FILES['logo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext  = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        $size = $_FILES['logo']['size'] ?? 0;

        if (!in_array($ext, $allowed, true)) {
            $error_message = "Invalid image type. Allowed: " . implode(', ', $allowed);
        } elseif ($size > 3 * 1024 * 1024) {
            $error_message = "Image too large. Max 3MB.";
        } else {
            $dir = __DIR__ . "/company_logos";
            if (!is_dir($dir)) @mkdir($dir, 0775, true);

            $filename = "logo_c{$company_id}_" . time() . "." . $ext;
            $destFS   = $dir . "/" . $filename;

            if (move_uploaded_file($_FILES['logo']['tmp_name'], $destFS)) {
                $logo = $filename;
                // Remove old logo if any
                if (!empty($company['logo'])) {
                    $oldFS = $dir . "/" . $company['logo'];
                    if (is_file($oldFS)) @unlink($oldFS);
                }
            } else {
                $error_message = "Failed to upload image.";
            }
        }
    }

    if ($error_message === '') {
        $sql = "UPDATE companies SET 
                  company_name = :company_name,
                  email        = :email,
                  phone        = :phone,
                  address      = :address,
                  c_detail     = :c_detail";          // <-- include detail
        $params = [
            ':company_name' => $company_name,
            ':email'        => $email,
            ':phone'        => $phone,
            ':address'      => $address,
            ':c_detail'     => $c_detail,
            ':company_id'   => $company_id
        ];
        if ($logo) {
            $sql .= ", logo = :logo";
            $params[':logo'] = $logo;
        }
        $sql .= " WHERE company_id = :company_id";

        try {
            $upd = $pdo->prepare($sql);
            if ($upd->execute($params)) {
                $success_message = "Company profile updated successfully!";
                // Refresh data & session display fields
                $stmt = $pdo->prepare("SELECT * FROM companies WHERE company_id = ?");
                $stmt->execute([$company_id]);
                $company = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
                $_SESSION['company_name'] = $company['company_name'] ?? ($_SESSION['company_name'] ?? null);
                $_SESSION['email']        = $company['email'] ?? ($_SESSION['email'] ?? null);
            } else {
                $error_message = "Failed to update. Please try again.";
            }
        } catch (PDOException $e) {
            $error_message = "DB error while updating: " . $e->getMessage();
        }
    }
}

/* ===== 3) Compute avatar source (logo OR initials SVG) ===== */
$hasLogo  = !empty($company['logo']) && is_file(__DIR__ . '/company_logos/' . $company['logo']);
$avatarSrc = $hasLogo ? ('company_logos/' . e($company['logo'])) : svg_avatar_data_uri($company['company_name'] ?? '', 112);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>JobHive | Company Profile</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        body {
            background: #f8fafc;
        }

        .profile-card {
            max-width: 760px;
            margin: 40px auto;
            background: #fff;
            border-radius: 1.5rem;
            box-shadow: 0 3px 16px rgba(30, 30, 60, .07);
            padding: 2.5rem 2rem 2rem;
        }

        .profile-img {
            width: 112px;
            height: 112px;
            object-fit: cover;
            border-radius: 16px;
            border: 3px solid #ffc107;
            background: #fafafa;
            margin-bottom: 1rem;
        }

        .edit-btn {
            margin-left: 8px;
            color: #ffc107;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1.12rem;
        }

        .edit-btn:hover {
            color: #ff8800;
        }

        .field-label {
            font-weight: 600;
            color: #6c757d;
            margin-bottom: .1rem;
            font-size: 1.04rem;
        }


        /* ===== Navbar link underline on hover ===== */
        .navbar-nav .nav-link {
            position: relative;
            padding-bottom: 4px;
            /* space for underline */
            transition: color 0.2s ease-in-out;
        }

        .navbar-nav .nav-link::after {
            content: "";
            position: absolute;
            left: 0;
            bottom: 0;
            width: 0%;
            height: 2px;
            background-color: #ffaa2b;
            transition: width 0.25s ease-in-out;
        }

        .navbar-nav .nav-link:hover::after {
            width: 100%;
        }

        .profile-form input[readonly],
        .profile-form textarea[readonly] {
            background: #f7f7fa;
            cursor: not-allowed;
        }

        .form-edit-row {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.2rem;
        }

        textarea.form-control {
            min-height: 120px;
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold text-warning" href="company_home.php">JobHive</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item"><a class="nav-link" href="c_dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link active" href="company_profile.php">Profile</a></li>
                    <li class="nav-item"><a class="nav-link" href="post_job.php">Post Job</a></li>
                    <li class="nav-item"><a class="btn btn-outline-warning ms-2" href="logout.php">Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="profile-card">
            <h3 class="fw-bold mb-3 text-center">Company Profile</h3>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success text-center"><?= e($success_message) ?></div>
            <?php endif; ?>
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger text-center"><?= e($error_message) ?></div>
            <?php endif; ?>

            <form class="profile-form" method="POST" enctype="multipart/form-data" action="company_profile.php">
                <!-- Logo (photo or initials) -->
                <div class="text-center mb-3">
                    <img src="<?= $avatarSrc ?>" class="profile-img" id="logoPreview" alt="Logo">
                    <div>
                        <input type="file" name="logo" accept="image/*" class="form-control mt-2" style="max-width:260px; margin:0 auto;"
                            onchange="previewLogo(this)">
                    </div>
                </div>

                <!-- Company Name -->
                <div class="form-edit-row">
                    <div style="flex:1">
                        <div class="field-label">Company Name</div>
                        <input type="text" name="company_name" class="form-control"
                            value="<?= e($company['company_name'] ?? '') ?>" <?= field_edit_attr($company['company_name']) ?> required>
                    </div>
                    <?php if (!empty($company['company_name'])): ?><button type="button" class="edit-btn" onclick="toggleEdit(this)">✎ Edit</button><?php endif; ?>
                </div>

                <!-- Email -->
                <div class="form-edit-row">
                    <div style="flex:1">
                        <div class="field-label">Email</div>
                        <input type="email" name="email" class="form-control"
                            value="<?= e($company['email'] ?? '') ?>" <?= field_edit_attr($company['email']) ?> required>
                    </div>
                    <?php if (!empty($company['email'])): ?><button type="button" class="edit-btn" onclick="toggleEdit(this)">✎ Edit</button><?php endif; ?>
                </div>

                <!-- Phone -->
                <div class="form-edit-row">
                    <div style="flex:1">
                        <div class="field-label">Phone</div>
                        <input type="text" name="phone" class="form-control"
                            value="<?= e($company['phone'] ?? '') ?>" <?= field_edit_attr($company['phone']) ?>>
                    </div>
                    <?php if (!empty($company['phone'])): ?><button type="button" class="edit-btn" onclick="toggleEdit(this)">✎ Edit</button><?php endif; ?>
                </div>

                <!-- Address -->
                <div class="form-edit-row">
                    <div style="flex:1">
                        <div class="field-label">Address</div>
                        <input type="text" name="address" class="form-control"
                            value="<?= e($company['address'] ?? '') ?>" <?= field_edit_attr($company['address']) ?>>
                    </div>
                    <?php if (!empty($company['address'])): ?><button type="button" class="edit-btn" onclick="toggleEdit(this)">✎ Edit</button><?php endif; ?>
                </div>

                <!-- Detail Company (c_detail) -->
                <div class="form-edit-row" style="align-items:flex-start">
                    <div style="flex:1">
                        <div class="field-label">Detail Company</div>
                        <textarea name="c_detail" class="form-control" <?= field_edit_attr($company['c_detail']) ?>><?= e($company['c_detail'] ?? '') ?></textarea>
                        <small class="text-muted">Brief description, services, branches, mission, etc.</small>
                    </div>
                    <?php if (!empty($company['c_detail'])): ?><button type="button" class="edit-btn" onclick="toggleEdit(this)">✎ Edit</button><?php endif; ?>
                </div>

                <div class="mt-4 text-center">
                    <button type="submit" class="btn btn-warning px-4">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleEdit(btn) {
            const input = btn.parentNode.querySelector("input, select, textarea");
            if (!input) return;
            if (input.hasAttribute("readonly")) input.removeAttribute("readonly");
            if (input.hasAttribute("disabled")) input.removeAttribute("disabled");
            input.focus();
            input.style.backgroundColor = "#fff8ec";
        }

        function previewLogo(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = e => document.getElementById('logoPreview').src = e.target.result;
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>