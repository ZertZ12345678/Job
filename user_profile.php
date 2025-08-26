<?php

include("connect.php");
session_start();

/* ===== 0) Auth / session ===== */
$user_id = $_SESSION['user_id'] ?? 1; // In production, redirect to login if missing

/* ===== Helpers ===== */
function e($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

/** Return 1–2 letter initials from a full name */
function initials_from_name($name): string
{
    $name = trim((string)$name);
    if ($name === '') return 'U';
    $parts = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY);
    $ini = '';
    foreach ($parts as $p) {
        $ini .= mb_strtoupper(mb_substr($p, 0, 1));
        if (mb_strlen($ini) >= 2) break;
    }
    return $ini ?: 'U';
}

/**
 * Build a square SVG avatar (as data: URI) showing initials.
 * Keeps the same visual size you use (default 112px).
 */
function svg_avatar_data_uri(string $name, int $size = 112): string
{
    $ini    = initials_from_name($name);
    $bg     = '#FFF8E6';   // soft background
    $ring   = '#FFC107';   // yellow ring (JobHive)
    $txt    = '#FF8A00';   // warm orange letters
    $font   = (int) round($size * 0.42);
    $radius = 16;          // rounded-corner square like your UI
    $inner  = $size - 4;   // <-- compute first; can't do {$size-4} inside heredoc

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

/* ===== 1) Fetch current user ===== */
$user = [];
$error_message = '';
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    $error_message = "Could not load profile: " . $e->getMessage();
}

/* ===== 2) Handle POST (update) ===== */
$success_message = '';
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $full_name        = trim($_POST['full_name'] ?? '');
    $email            = trim($_POST['email'] ?? '');
    $phone            = trim($_POST['phone'] ?? '');
    $address          = trim($_POST['address'] ?? '');
    $job_category     = trim($_POST['job_category'] ?? '');
    $current_position = trim($_POST['current_position'] ?? '');

    // ---- Optional: photo upload ----
    $profile_picture = null;
    if (isset($_FILES['profile_picture']) && ($_FILES['profile_picture']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext  = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
        $sizeB = $_FILES['profile_picture']['size'] ?? 0;

        if (!in_array($ext, $allowed, true)) {
            $error_message = "Invalid image type. Allowed: " . implode(', ', $allowed);
        } elseif ($sizeB > 3 * 1024 * 1024) {
            $error_message = "Image too large. Max 3MB.";
        } else {
            $dir = __DIR__ . "/profile_pics";
            if (!is_dir($dir)) @mkdir($dir, 0775, true);

            $filename = "user_" . $user_id . "_" . time() . "." . $ext;
            $destFS   = $dir . "/" . $filename;

            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $destFS)) {
                $profile_picture = $filename;
                // Remove old photo if any
                if (!empty($user['profile_picture'])) {
                    $oldFS = $dir . "/" . $user['profile_picture'];
                    if (is_file($oldFS)) @unlink($oldFS);
                }
            } else {
                $error_message = "Failed to upload image.";
            }
        }
    }

    if ($error_message === '') {
        $sql = "UPDATE users SET 
                  full_name = :full_name,
                  email = :email,
                  phone = :phone,
                  address = :address,
                  job_category = :job_category,
                  current_position = :current_position";
        $params = [
            ':full_name'        => $full_name,
            ':email'            => $email,
            ':phone'            => $phone,
            ':address'          => $address,
            ':job_category'     => $job_category,
            ':current_position' => $current_position,
            ':user_id'          => $user_id
        ];
        if ($profile_picture) {
            $sql .= ", profile_picture = :profile_picture";
            $params[':profile_picture'] = $profile_picture;
        }
        $sql .= " WHERE user_id = :user_id";

        try {
            $upd = $pdo->prepare($sql);
            if ($upd->execute($params)) {
                $success_message = "Profile updated successfully!";
                // Reload user so the new image (or fields) show immediately
                $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            } else {
                $error_message = "Failed to update profile. Please try again.";
            }
        } catch (PDOException $e) {
            $error_message = "DB error while updating: " . $e->getMessage();
        }
    }
}

/* ===== 3) Options / helpers ===== */
$job_categories = [
    "IT&Hardware" => "IT & Hardware",
    "Finance"     => "Finance",
    "Engineering" => "Engineering",
    "Marketing"   => "Marketing"
];

function field_edit_attr($val, $type = 'input')
{
    if (empty($val)) return '';
    return $type === 'select' ? 'disabled' : 'readonly';
}

/* ===== 4) Compute avatar source (photo OR initials SVG) ===== */
$hasPhoto  = !empty($user['profile_picture']) && is_file(__DIR__ . '/profile_pics/' . $user['profile_picture']);
$avatarSrc = $hasPhoto ? ('profile_pics/' . e($user['profile_picture']))
    : svg_avatar_data_uri($user['full_name'] ?? '', 112);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>JobHive | User Profile</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        body {
            background: #f8fafc;
        }

        .profile-card {
            max-width: 680px;
            margin: 40px auto;
            background: #fff;
            border-radius: 1.5rem;
            box-shadow: 0 3px 16px rgba(30, 30, 60, .07);
            padding: 2.5rem 2rem 2rem;
        }

        /* Square avatar, rounded corners, yellow border */
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

        .profile-form input[readonly],
        .profile-form select[disabled] {
            background: #f7f7fa;
            cursor: not-allowed;
        }

        .form-edit-row {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.2rem;
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold text-warning" href="user_home.php">JobHive</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item"><a class="nav-link" href="user_home.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link active" href="user_profile.php">Profile</a></li>
                    <li class="nav-item"><a class="nav-link" href="recommended.php">Recommended Jobs</a></li>
                    <li class="nav-item"><a class="nav-link" href="companies.php">All Companies</a></li>
                    <li class="nav-item"><a class="btn btn-outline-warning ms-2" href="index.php">Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="profile-card">
            <h3 class="fw-bold mb-3 text-center">Profile</h3>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success text-center"><?= e($success_message) ?></div>
            <?php endif; ?>
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger text-center"><?= e($error_message) ?></div>
            <?php endif; ?>

            <form class="profile-form" method="POST" enctype="multipart/form-data" action="user_profile.php">
                <!-- Avatar (photo or initials) -->
                <div class="text-center mb-3">
                    <img src="<?= $avatarSrc ?>" class="profile-img" id="profilePreview" alt="Profile">
                    <div>
                        <input type="file" name="profile_picture" accept="image/*" class="form-control mt-2" style="max-width:260px; margin:0 auto;"
                            onchange="previewProfilePic(this)">
                    </div>
                </div>

                <!-- Full Name -->
                <div class="form-edit-row">
                    <div style="flex:1">
                        <div class="field-label">Full Name</div>
                        <input type="text" name="full_name" class="form-control"
                            value="<?= e($user['full_name'] ?? '') ?>" <?= field_edit_attr($user['full_name']) ?> required>
                    </div>
                    <?php if (!empty($user['full_name'])): ?>
                        <button type="button" class="edit-btn" onclick="toggleEdit(this)">✎ Edit</button>
                    <?php endif; ?>
                </div>

                <!-- Email -->
                <div class="form-edit-row">
                    <div style="flex:1">
                        <div class="field-label">Email</div>
                        <input type="email" name="email" class="form-control"
                            value=" <?= e($user['email'] ?? '') ?>" <?= field_edit_attr($user['email']) ?> required>
                    </div>
                    <?php if (!empty($user['email'])): ?>
                        <button type="button" class="edit-btn" onclick="toggleEdit(this)">✎ Edit</button>
                    <?php endif; ?>
                </div>

                <!-- Phone -->
                <div class="form-edit-row">
                    <div style="flex:1">
                        <div class="field-label">Phone</div>
                        <input type="text" name="phone" class="form-control"
                            value="<?= e($user['phone'] ?? '') ?>" <?= field_edit_attr($user['phone']) ?>>
                    </div>
                    <?php if (!empty($user['phone'])): ?>
                        <button type="button" class="edit-btn" onclick="toggleEdit(this)">✎ Edit</button>
                    <?php endif; ?>
                </div>

                <!-- Address -->
                <div class="form-edit-row">
                    <div style="flex:1">
                        <div class="field-label">Address</div>
                        <input type="text" name="address" class="form-control"
                            value="<?= e($user['address'] ?? '') ?>" <?= field_edit_attr($user['address']) ?>>
                    </div>
                    <?php if (!empty($user['address'])): ?>
                        <button type="button" class="edit-btn" onclick="toggleEdit(this)">✎ Edit</button>
                    <?php endif; ?>
                </div>

                <!-- Job Category -->
                <div class="form-edit-row">
                    <div style="flex:1">
                        <div class="field-label">Job Category</div>
                        <select name="job_category" id="job_category_select" class="form-select"
                            <?= field_edit_attr($user['job_category'], 'select') ?>>
                            <option value="">Select Category</option>
                            <?php foreach ($job_categories as $val => $label): ?>
                                <option value="<?= e($val) ?>" <?= (($user['job_category'] ?? '') === $val) ? 'selected' : '' ?>>
                                    <?= e($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!empty($user['job_category'])): ?>
                            <input type="hidden" name="job_category" id="job_category_hidden" value="<?= e($user['job_category']) ?>">
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($user['job_category'])): ?>
                        <button type="button" class="edit-btn" onclick="toggleEdit(this)">✎ Edit</button>
                    <?php endif; ?>
                </div>

                <!-- Current Position -->
                <div class="form-edit-row">
                    <div style="flex:1">
                        <div class="field-label">Current Position</div>
                        <input type="text" name="current_position" class="form-control"
                            value="<?= e($user['current_position'] ?? '') ?>" <?= field_edit_attr($user['current_position']) ?>>
                    </div>
                    <?php if (!empty($user['current_position'])): ?>
                        <button type="button" class="edit-btn" onclick="toggleEdit(this)">✎ Edit</button>
                    <?php endif; ?>
                </div>

                <div class="mt-4 text-center">
                    <button type="submit" class="btn btn-warning px-4">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleEdit(btn) {
            const input = btn.parentNode.querySelector("input, select");
            if (!input) return;
            if (input.hasAttribute("readonly")) input.removeAttribute("readonly");
            if (input.hasAttribute("disabled")) input.removeAttribute("disabled");
            input.focus();
            input.style.backgroundColor = "#fff8ec";
            if (input.tagName === "SELECT" && input.name === "job_category") {
                const hidden = document.getElementById('job_category_hidden');
                if (hidden) hidden.disabled = true;
            }
        }

        // Live preview when choosing a real photo — keeps the square box
        function previewProfilePic(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = e => document.getElementById('profilePreview').src = e.target.result;
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
    
</body>

</html>