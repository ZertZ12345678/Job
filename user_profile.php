<?php
include("connect.php");
session_start();

$user_id = $_SESSION['user_id'] ?? 1;

// Fetch user data
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id=?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $user = [];
    $error_message = "Could not load profile: " . $e->getMessage();
}

$job_categories = [
    "IT&Hardware" => "IT & Hardware",
    "Finance" => "Finance",
    "Engineering" => "Engineering",
    "Marketing" => "Marketing"
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
            font-size: 1.02rem;
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
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success text-center">Profile updated successfully!</div>
            <?php elseif (isset($_GET['error'])): ?>
                <div class="alert alert-danger text-center">Failed to update profile. Please try again.</div>
            <?php endif; ?>
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>
            <form class="profile-form" method="POST" enctype="multipart/form-data" action="user_profile_update.php">
                <!-- Profile Picture -->
                <div class="text-center mb-3">
                    <img src="<?php echo !empty($user['profile_picture']) ? 'profile_pics/' . htmlspecialchars($user['profile_picture']) : 'default_user.png'; ?>" class="profile-img" id="profilePreview">
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
                                <option value="<?php echo $val; ?>" <?php if (($user['job_category'] ?? '') === $val) echo 'selected'; ?>>
                                    <?php echo $label; ?>
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
    <script>s
        function toggleEdit(btn) {
            const input = btn.parentNode.querySelector("input, select");
            if (input) {
                if (input.hasAttribute("readonly")) input.removeAttribute("readonly");
                if (input.hasAttribute("disabled")) input.removeAttribute("disabled");
                input.focus();
                input.style.backgroundColor = "#fff8ec";
                // Special handling for job category
                if (input.tagName === "SELECT" && input.name === "job_category") {
                    var hidden = document.getElementById('job_category_hidden');
                    if (hidden) hidden.disabled = true;
                }
            }
        }
        // Image preview for profile pic
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