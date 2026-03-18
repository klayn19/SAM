<?php
date_default_timezone_set('Asia/Manila');

// Assuming these variables are passed in or from session
$userId = $id;  // User ID from users table
$email = $userEmail;  // User email from users table
$studentName = $name;  // Full name

$logDate = date('Y-m-d');
$timeIn = date('H:i:s'); // 24-hour format (HH:MM:SS) for database storage

// Check if student already clocked in today
$checkStmt = $conn->prepare("SELECT id FROM attendance_log WHERE email = ? AND logDate = ?");
$checkStmt->bind_param("ss", $email, $logDate);
$checkStmt->execute();
$result = $checkStmt->get_result();

if ($result->num_rows > 0) {
    // Already clocked in today
    echo json_encode(['success' => false, 'message' => 'Already clocked in today']);
} else {
    // Insert new attendance record with email
    $stmt = $conn->prepare("INSERT INTO attendance_log (user_id, email, studentName, logDate, timeIn, status) VALUES (?, ?, ?, ?, ?, 'Present')");
    $stmt->bind_param("issss", $userId, $email, $studentName, $logDate, $timeIn);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Clock in successful', 'time' => date('h:i A')]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to record attendance']);
    }
    $stmt->close();
}
$checkStmt->close();
?>