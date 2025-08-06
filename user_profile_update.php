<?php
include("connect.php");
session_start();

$user_id = $_SESSION['user_id'] ?? 1;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $full_name = $_POST['full_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? '';
    $job_category = $_POST['job_category'] ?? '';
    $current_position = $_POST['current_position'] ?? '';

    // 1. File upload handling
    $profile_picture = null;
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
        // Make sure the upload folder exists and is writable!
        $ext = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
        $filename = "user_" . $user_id . "_" . time() . "." . $ext;
        if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], "profile_pics/" . $filename)) {
            $profile_picture = $filename;
        }
    }

    // 2. Update SQL and params
    $sql = "UPDATE users SET 
        full_name = :full_name, 
        email = :email, 
        phone = :phone, 
        address = :address, 
        job_category = :job_category, 
        current_position = :current_position";
    $params = [
        ':full_name' => $full_name,
        ':email' => $email,
        ':phone' => $phone,
        ':address' => $address,
        ':job_category' => $job_category,
        ':current_position' => $current_position,
        ':user_id' => $user_id
    ];
    if ($profile_picture) {
        $sql .= ", profile_picture = :profile_picture";
        $params[':profile_picture'] = $profile_picture;
    }
    $sql .= " WHERE user_id = :user_id";

    $stmt = $pdo->prepare($sql);
    if ($stmt->execute($params)) {
        header("Location: user_profile.php?success=1");
        exit();
    } else {
        header("Location: user_profile.php?error=1");
        exit();
    }
} else {
    header("Location: user_profile.php");
    exit();
}
?>
