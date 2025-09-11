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
/** Square SVG initials avatar as data: URI */
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
    $b_date           = trim($_POST['b_date'] ?? '');
    $gender           = trim($_POST['gender'] ?? '');        // NEW
    $education        = trim($_POST['education'] ?? '');     // NEW
    $job_category     = trim($_POST['job_category'] ?? '');
    $current_position = trim($_POST['current_position'] ?? '');
    if ($b_date !== '') {
        $ts = strtotime($b_date);
        $b_date = $ts ? date('Y-m-d', $ts) : '';
    }
    // ---- Photo upload (optional) ----
    $profile_picture = null;
    if (isset($_FILES['profile_picture']) && ($_FILES['profile_picture']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext   = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
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
                  b_date = :b_date,
                  gender = :gender,                -- NEW
                  education = :education,          -- NEW
                  job_category = :job_category,
                  current_position = :current_position";
        $params = [
            ':full_name'        => $full_name,
            ':email'            => $email,
            ':phone'            => $phone,
            ':address'          => $address,
            ':b_date'           => ($b_date === '' ? null : $b_date),
            ':gender'           => ($gender === '' ? null : $gender),
            ':education'        => ($education === '' ? null : $education),
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
    "Software" => "Software",
    "Network"  => "Network",
];

$education_options = [        // used for the dropdown
    ""             => "Select Education",
    "High School"  => "High School",
    "Diploma"      => "Diploma",
    "Bachelor"     => "Bachelor",
    "Master"       => "Master",
    "PhD"          => "PhD"
];
function field_edit_attr($val, $type = 'input')
{
    if (empty($val)) return '';
    return $type === 'select' ? 'disabled' : 'readonly';
}
/* ===== 4) Avatar ===== */
$hasPhoto  = !empty($user['profile_picture']) && is_file(__DIR__ . '/profile_pics/' . $user['profile_picture']);
$avatarSrc = $hasPhoto ? ('profile_pics/' . e($user['profile_picture'])) : svg_avatar_data_uri($user['full_name'] ?? '', 112);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>JobHive | User Profile</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        :root {
            /* Light mode variables */
            --bg-color: #f8fafc;
            --text-color: #334155;
            --card-bg: #ffffff;
            --border-color: rgba(15, 23, 42, 0.06);
            --header-bg: #ffffff;
            --input-bg: #ffffff;
            --button-bg: #ffc107;
            --button-text: #000000;
            --link-color: #ffaa2b;
            --section-bg: #f8fafc;
            --card-shadow: 0 8px 30px rgba(0, 0, 0, .06);
            --transition-speed: 0.3s;
            --bg-tertiary: #f3f4f6;
        }

        /* Dark mode variables */
        [data-theme="dark"] {
            --bg-color: #121212;
            --text-color: #e0e0e0;
            --card-bg: #1e1e1e;
            --border-color: rgba(255, 255, 255, 0.1);
            --header-bg: #1a1a1a;
            --input-bg: #2d2d2d;
            --button-bg: #ffc107;
            --button-text: #000000;
            --link-color: #ffaa2b;
            --section-bg: #1a1a1a;
            --card-shadow: 0 8px 30px rgba(0, 0, 0, 0.3);
            --bg-tertiary: #2d2d2d;
        }

        /* Global transitions */
        body,
        .navbar,
        .card,
        .form-control,
        .form-select,
        .btn {
            transition: background-color var(--transition-speed) ease,
                color var(--transition-speed) ease,
                border-color var(--transition-speed) ease,
                box-shadow var(--transition-speed) ease;
        }

        /* Page fits in one screen on typical laptop widths (≥1200px) */
        html,
        body {
            height: 100%;
            background-color: var(--bg-color);
            color: var(--text-color);
        }

        /* hide page scrollbar */
        .navbar-nav .nav-item:not(.dropdown) .nav-link {
            position: relative;
            padding-bottom: 4px;
            transition: color .2s
        }

        .navbar-nav .nav-item:not(.dropdown) .nav-link::after {
            content: "";
            position: absolute;
            left: 0;
            bottom: 0;
            width: 0;
            height: 2px;
            background-color: #ffaa2b;
            transition: width .25s
        }

        .navbar-nav .nav-item:not(.dropdown) .nav-link:hover::after {
            width: 100%
        }

        .profile-card {
            width: min(1100px, 96vw);
            margin: 24px auto;
            background: var(--card-bg);
            border-radius: 1.25rem;
            box-shadow: var(--card-shadow);
            padding: 1.5rem 1.5rem 1rem;
            border: 1px solid var(--border-color);
        }

        /* 2-column grid on lg+ to reduce height */
        .grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 14px;
        }

        @media (min-width: 992px) {
            .grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        .profile-img {
            width: 112px;
            height: 112px;
            object-fit: cover;
            border-radius: 16px;
            border: 3px solid #ffc107;
            background: var(--card-bg);
            margin-bottom: .5rem;
        }

        .edit-btn {
            margin-left: 8px;
            color: #ffc107;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1.02rem;
        }

        .edit-btn:hover {
            color: #ff8800;
        }

        .field-label {
            font-weight: 600;
            color: var(--text-color);
            margin-bottom: .1rem;
            font-size: .98rem;
            opacity: 0.8;
        }

        .profile-form input[readonly],
        .profile-form select[disabled] {
            background: var(--bg-tertiary);
            cursor: not-allowed;
            color: var(--text-color);
        }

        .form-edit-row {
            display: flex;
            align-items: center;
            gap: .75rem;
        }

        .sticky-actions {
            position: sticky;
            bottom: 0;
            background: var(--card-bg);
            padding-top: .5rem;
            margin-top: .5rem;
        }

        /* compact controls for height */
        .form-control,
        .form-select {
            padding: .45rem .6rem;
            background-color: var(--input-bg);
            color: var(--text-color);
            border-color: var(--border-color);
        }

        .navbar {
            position: sticky;
            top: 0;
            z-index: 10;
            background-color: var(--header-bg) !important;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        [data-theme="dark"] .navbar {
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        .navbar-brand {
            color: var(gold) !important;
        }

        .navbar-nav .nav-link {
            color: var(--text-color) !important;
        }

        .navbar-toggler {
            border-color: var(--border-color) !important;
        }

        .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%2333, 0.75)' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e") !important;
        }

        [data-theme="dark"] .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%23255, 255, 255, 0.75)' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e") !important;
        }

        /* Alert styles */
        .alert-success {
            background-color: #d1e7dd;
            border-color: #badbcc;
            color: #0f5132;
        }

        [data-theme="dark"] .alert-success {
            background-color: #0f5132;
            border-color: #198754;
            color: #d1e7dd;
        }

        .alert-danger {
            background-color: #f8d7da;
            border-color: #f5c2c7;
            color: #842029;
        }

        [data-theme="dark"] .alert-danger {
            background-color: #842029;
            border-color: #f5c2c7;
            color: #f8d7da;
        }

        /* Button styles */
        .btn-warning {
            background-color: var(--button-bg);
            color: var(--button-text);
        }

        .btn-outline-warning {
            color: var(--button-bg);
            border-color: var(--button-bg);
        }

        [data-theme="dark"] .btn-outline-warning {
            color: #ffc107;
            border-color: #ffc107;
        }

        [data-theme="dark"] .btn-outline-warning:hover {
            background-color: #ffc107;
            color: #000000;
        }

        /* Theme Toggle Button */
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
            background: var(--bg-tertiary);
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
                    <li class="nav-item"><a class="nav-link" href="all_companies.php">All Companies</a></li>
                    
                    <li class="nav-item"><a class="btn btn-outline-warning ms-2" href="index.php">Logout</a></li>

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
    <div class="profile-card">
        <h4 class="fw-bold mb-2 text-center">Profile</h4>
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success py-2 text-center"><?= e($success_message) ?></div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger py-2 text-center"><?= e($error_message) ?></div>
        <?php endif; ?>
        <form class="profile-form" method="POST" enctype="multipart/form-data" action="user_profile.php">
            <!-- Header: Avatar + upload sits full width -->
            <div class="text-center mb-2">
                <img src="<?= $avatarSrc ?>" class="profile-img" id="profilePreview" alt="Profile">
                <div>
                    <input type="file" name="profile_picture" accept="image/*" class="form-control mt-2"
                        style="max-width:260px; margin:0 auto;" onchange="previewProfilePic(this)">
                </div>
            </div>
            <!-- Two-column grid of fields to reduce height -->
            <div class="grid">
                <!-- Full Name -->
                <div class="form-edit-row">
                    <div style="flex:1">
                        <div class="field-label">Full Name</div>
                        <input type="text" name="full_name" class="form-control"
                            value="<?= e($user['full_name'] ?? '') ?>" <?= field_edit_attr($user['full_name']) ?> required>
                    </div>
                    <?php if (!empty($user['full_name'])): ?><button type="button" class="edit-btn" onclick="toggleEdit(this)">✎ Edit</button><?php endif; ?>
                </div>
                <!-- Email -->
                <div class="form-edit-row">
                    <div style="flex:1">
                        <div class="field-label">Email</div>
                        <input type="email" name="email" class="form-control"
                            value="<?= e($user['email'] ?? '') ?>" <?= field_edit_attr($user['email']) ?> required>
                    </div>
                    <?php if (!empty($user['email'])): ?><button type="button" class="edit-btn" onclick="toggleEdit(this)">✎ Edit</button><?php endif; ?>
                </div>
                <!-- Phone -->
                <div class="form-edit-row">
                    <div style="flex:1">
                        <div class="field-label">Phone</div>
                        <input type="text" name="phone" class="form-control"
                            value="<?= e($user['phone'] ?? '') ?>" <?= field_edit_attr($user['phone']) ?>>
                    </div>
                    <?php if (!empty($user['phone'])): ?><button type="button" class="edit-btn" onclick="toggleEdit(this)">✎ Edit</button><?php endif; ?>
                </div>
                <!-- Address -->
                <div class="form-edit-row">
                    <div style="flex:1">
                        <div class="field-label">Address</div>
                        <input type="text" name="address" class="form-control"
                            value="<?= e($user['address'] ?? '') ?>" <?= field_edit_attr($user['address']) ?>>
                    </div>
                    <?php if (!empty($user['address'])): ?><button type="button" class="edit-btn" onclick="toggleEdit(this)">✎ Edit</button><?php endif; ?>
                </div>
                <!-- Birth Date -->
                <div class="form-edit-row">
                    <div style="flex:1">
                        <div class="field-label">Birth Date</div>
                        <input type="date" name="b_date" class="form-control"
                            value="<?= e($user['b_date'] ?? '') ?>" <?= field_edit_attr($user['b_date']) ?>>
                    </div>
                    <?php if (!empty($user['b_date'])): ?><button type="button" class="edit-btn" onclick="toggleEdit(this)">✎ Edit</button><?php endif; ?>
                </div>
                <!-- Gender (NEW) -->
                <div class="form-edit-row">
                    <div style="flex:1">
                        <div class="field-label">Gender</div>
                        <select name="gender" class="form-select" <?= field_edit_attr($user['gender'] ?? '', 'select') ?>>
                            <option value="">Select Gender</option>
                            <option value="Male" <?= (($user['gender'] ?? '') === 'Male')   ? 'selected' : '' ?>>Male</option>
                            <option value="Female" <?= (($user['gender'] ?? '') === 'Female') ? 'selected' : '' ?>>Female</option>
                        </select>
                        <?php if (!empty($user['gender'])): ?>
                            <input type="hidden" name="gender" id="gender_hidden" value="<?= e($user['gender']) ?>">
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($user['gender'])): ?><button type="button" class="edit-btn" onclick="toggleEdit(this)">✎ Edit</button><?php endif; ?>
                </div>
                <!-- Education (NEW) -->
                <div class="form-edit-row">
                    <div style="flex:1">
                        <div class="field-label">Education</div>
                        <select name="education" class="form-select" <?= field_edit_attr($user['education'] ?? '', 'select') ?>>
                            <?php foreach ($education_options as $val => $label): ?>
                                <option value="<?= e($val) ?>" <?= (($user['education'] ?? '') === $val) ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!empty($user['education'])): ?>
                            <input type="hidden" name="education" id="education_hidden" value="<?= e($user['education']) ?>">
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($user['education'])): ?><button type="button" class="edit-btn" onclick="toggleEdit(this)">✎ Edit</button><?php endif; ?>
                </div>
                <!-- Job Category -->
                <div class="form-edit-row">
                    <div style="flex:1">
                        <div class="field-label">Job Category</div>
                        <select name="job_category" id="job_category_select" class="form-select"
                            <?= field_edit_attr($user['job_category'], 'select') ?>>
                            <option value="">Select Category</option>
                            <?php foreach ($job_categories as $val => $label): ?>
                                <option value="<?= e($val) ?>" <?= (($user['job_category'] ?? '') === $val) ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!empty($user['job_category'])): ?>
                            <input type="hidden" name="job_category" id="job_category_hidden" value="<?= e($user['job_category']) ?>">
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($user['job_category'])): ?><button type="button" class="edit-btn" onclick="toggleEdit(this)">✎ Edit</button><?php endif; ?>
                </div>
                <!-- Current Position -->
                <div class="form-edit-row">
                    <div style="flex:1">
                        <div class="field-label">Current Position</div>
                        <input type="text" name="current_position" class="form-control"
                            value="<?= e($user['current_position'] ?? '') ?>" <?= field_edit_attr($user['current_position']) ?>>
                    </div>
                    <?php if (!empty($user['current_position'])): ?><button type="button" class="edit-btn" onclick="toggleEdit(this)">✎ Edit</button><?php endif; ?>
                </div>
            </div><!-- /.grid -->
            <div class="text-center sticky-actions">
                <button type="submit" class="btn btn-warning px-4">Save Changes</button>
            </div>
        </form>
    </div>
    <script>
        // Theme toggle functionality - matching about.php
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

        function toggleEdit(btn) {
            const input = btn.parentNode.querySelector("input, select");
            if (!input) return;
            if (input.hasAttribute("readonly")) input.removeAttribute("readonly");
            if (input.hasAttribute("disabled")) input.removeAttribute("disabled");
            input.focus();
            input.style.backgroundColor = "#fff8ec";
            // handle hidden mirrors for selects
            if (input.tagName === "SELECT") {
                const name = input.name;
                const hidden = document.querySelector(`input[type="hidden"][name="${name}"]`);
                if (hidden) hidden.disabled = true;
            }
        }

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