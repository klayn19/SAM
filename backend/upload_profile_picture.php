<?php
session_start();
require_once 'config.php';

$response = ['success' => false, 'message' => ''];

// Check if user is logged in
if (!isset($_SESSION['email'])) {
    $response['message'] = 'User not authenticated';
    echo json_encode($response);
    exit();
}

// Check if file is uploaded
if (!isset($_FILES['profilePicture'])) {
    $response['message'] = 'No file uploaded';
    echo json_encode($response);
    exit();
}

$file = $_FILES['profilePicture'];
$email = $_SESSION['email'];

// Validate file
$allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$max_size = 5 * 1024 * 1024; // 5MB

if (!in_array($file['type'], $allowed_types)) {
    $response['message'] = 'Invalid file type. Only JPEG, PNG, GIF, and WebP allowed';
    echo json_encode($response);
    exit();
}

if ($file['size'] > $max_size) {
    $response['message'] = 'File size exceeds 5MB limit';
    echo json_encode($response);
    exit();
}

if ($file['error'] !== UPLOAD_ERR_OK) {
    $response['message'] = 'File upload error';
    echo json_encode($response);
    exit();
}

// Create uploads/profiles directory if not exists
$upload_dir = 'uploads/profiles/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Generate unique filename
$file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = uniqid('profile_') . '.' . $file_extension;
$filepath = $upload_dir . $filename;

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    $response['message'] = 'Failed to save file';
    echo json_encode($response);
    exit();
}

// Update database
$stmt = $conn->prepare("UPDATE users SET profilePicture = ? WHERE email = ?");
$stmt->bind_param("ss", $filename, $email);

if ($stmt->execute()) {
    $response['success'] = true;
    $response['message'] = 'Profile picture updated successfully';
    $response['imagePath'] = $filepath;
} else {
    $response['message'] = 'Database update failed';
    unlink($filepath); // delete file if DB update fails
}

$stmt->close();
echo json_encode($response);
?>
