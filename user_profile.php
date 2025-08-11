<?php
include("connect.php");
session_start();

// ====== 0) Auth / session ======
$user_id = $_SESSION['user_id'] ?? 1;

// TODO: replace fallback with redirect if desired
// if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

// ====== 1) Fetch current user (also used for old photo cleanup) ======
$user = [];
$error_message = '';
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    $error_message = "Could not load profile: " . $e->getMessage();
}

// ====== 2) Handle POST (update) ======
$success_message = '';
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Collect fields
    $full_name        = trim($_POST['full_name'] ?? '');
    $email            = trim($_POST['email'] ?? '');
    $phone            = trim($_POST['phone'] ?? '');
    $address          = trim($_POST['address'] ?? '');
    $job_category     = trim($_POST['job_category'] ?? '');
    $current_position = trim($_POST['current_position'] ?? '');

    // ---- File upload (optional) ----
    $profile_picture = null;
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
        $size = $_FILES['profile_picture']['size'] ?? 0;

        if (!in_array($ext, $allowed, true)) {
            $error_message = "Invalid image type. Allowed: " . implode(', ', $allowed);
        } elseif ($size > 3 * 1024 * 1024) {
            $error_message = "Image too large. Max 3MB.";
        } else {
            $dir = __DIR__ . "/profile_pics";
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $filename = "user_" . $user_id . "_" . time() . "." . $ext;
            $destFS   = $dir . "/" . $filename;        // filesystem path
            $destWeb  = "profile_pics/" . $filename;   // web path to save in DB

            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $destFS)) {
                $profile_picture = $filename;

                // Optional: remove old picture if exists and different
                if (!empty($user['profile_picture'])) {
                    $oldFS = $dir . "/" . $user['profile_picture'];
                    if (is_file($oldFS)) {
                        @unlink($oldFS);
                    }
                }
            } else {
                $error_message = "Failed to upload image.";
            }
        }
    }

    // ---- Update DB if no upload error ----
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
            $ok = $upd->execute($params);
            if ($ok) {
                $success_message = "Profile updated successfully!";
                // Reload user after update
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

// ====== 3) Options / helpers ======
$job_categories = [
    "IT&Hardware" => "IT & Hardware",
    "Finance"     => "Finance",
    "Engineering" => "Engineering",
    "Marketing"   => "Marketing"
];

// Helper for readonly/disabled if value exists
function field_edit_attr($val, $type = 'input')
{
    if (empty($val)) return '';
    return $type === 'select' ? 'disabled' : 'readonly';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>JobHive | User Profile</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #f8fafc;
        }

        .profile-card {
            max-width: 680px;
            margin: 40px auto;
            background: #fff;
            border-radius: 1.5rem;
            box-shadow: 0 3px 16px rgba(30, 30, 60, 0.07);
            padding: 2.5rem 2rem 2rem 2rem;
        }

        .profile-img {
            width: 112px;
            height: 112px;
            object-fit: cover;
            border-radius: 1.2rem;
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
            transition: color 0.13s;
        }

        .edit-btn:hover {
            color: #ff8800;
        }

        .field-label {
            font-weight: 600;
            color: #6c757d;
            margin-bottom: 0.1rem;
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
            <a class="navbar-brand fw-bold text-warning" href="home.php">JobHive</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item"><a class="nav-link" aria-current="page" href="user_home.php">Home</a></li>
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
                <div class="alert alert-success text-center"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger text-center"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <form class="profile-form" method="POST" enctype="multipart/form-data" action="user_profile.php">
                <!-- Profile Picture -->
                <div class="text-center mb-3">
                    <img src="<?php echo !empty($user['profile_picture']) ? 'profile_pics/' . htmlspecialchars($user['profile_picture']) : 'default_user.png'; ?>" class="profile-img" id="profilePreview" alt="Profile">
                    <div>
                        <input type="file" name="profile_picture" accept="image/*" class="form-control mt-2" style="max-width:260px; margin:0 auto;" onchange="previewProfilePic(this)">
                    </div>
                </div>

                <!-- Full Name -->
                <div class="form-edit-row">
                    <div style="flex:1">
                        <div class="field-label">Full Name</div>
                        <input type="text" name="full_name" class="form-control"
                            value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>"
                            <?php echo field_edit_attr($user['full_name']); ?> required>
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
                            value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>"
                            <?php echo field_edit_attr($user['email']); ?> required>
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
                            value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                            <?php echo field_edit_attr($user['phone']); ?>>
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
                            value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>"
                            <?php echo field_edit_attr($user['address']); ?>>
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
                            <?php echo field_edit_attr($user['job_category'], 'select'); ?>>
                            <option value="">Select Category</option>
                            <?php foreach ($job_categories as $val => $label): ?>
                                <option value="<?php echo htmlspecialchars($val); ?>"
                                    <?php if (($user['job_category'] ?? '') === $val) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!empty($user['job_category'])): ?>
                            <input type="hidden" name="job_category" id="job_category_hidden" value="<?php echo htmlspecialchars($user['job_category']); ?>">
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
                            value="<?php echo htmlspecialchars($user['current_position'] ?? ''); ?>"
                            <?php echo field_edit_attr($user['current_position']); ?>>
                    </div>
                    <?php if (!empty($user['current_position'])): ?>
                        <button type="button" class="edit-btn" onclick="toggleEdit(this)">✎ Edit</button>
                    <?php endif; ?>
                </div>

                <!-- Submit -->
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

            // For job category select: disable hidden field so only select value submits
            if (input.tagName === "SELECT" && input.name === "job_category") {
                var hidden = document.getElementById('job_category_hidden');
                if (hidden) hidden.disabled = true;
            }
        }

        // Preview chosen profile picture
        function previewProfilePic(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = e => document.getElementById('profilePreview').src = e.target.result;
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>