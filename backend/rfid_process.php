<?php
// backend/rfid_process.php - FIXED VERSION
// This fixes the datetime format issue causing time-in to appear in time-out

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'message' => 'PHP Error: ' . $errstr . ' in ' . basename($errfile) . ':' . $errline
    ]);
    exit();
});

set_exception_handler(function($e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'message' => 'Exception: ' . $e->getMessage()
    ]);
    exit();
});

ob_start();
date_default_timezone_set('Asia/Manila');

function sendJSON($success, $message, $data = []) {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge([
        'success' => $success, 
        'message' => $message
    ], $data));
    exit();
}

// Headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-KEY');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(false, 'Only POST method allowed');
}

// Load config
if (!file_exists(__DIR__ . '/config.php')) {
    sendJSON(false, 'Configuration file not found');
}

require_once __DIR__ . '/config.php';

if (!$conn || $conn->connect_error) {
    sendJSON(false, 'Database connection failed: ' . ($conn->connect_error ?? 'Unknown error'));
}

// Verify API Key
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($apiKey !== 'SAM_RFID_2024_SecureKey_12345') {
    sendJSON(false, 'Invalid or missing API key');
}

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['rfid_uid'])) {
    sendJSON(false, 'Invalid request data. Expected JSON with rfid_uid field');
}

$rfid = trim(strtoupper($data['rfid_uid']));

if (empty($rfid)) {
    sendJSON(false, 'RFID UID cannot be empty');
}

// Find user by RFID
$stmt = $conn->prepare("SELECT ID, firstName, middleName, lastName, email, userType FROM users WHERE rfid_uid = ?");
if (!$stmt) {
    sendJSON(false, 'Database prepare failed: ' . $conn->error);
}

$stmt->bind_param("s", $rfid);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    
    // Log failed scan
    $logStmt = $conn->prepare("INSERT INTO rfid_scans (rfid_uid, action_type, success, message) VALUES (?, 'unknown', 0, 'RFID not registered')");
    if ($logStmt) {
        $logStmt->bind_param("s", $rfid);
        $logStmt->execute();
        $logStmt->close();
    }
    
    sendJSON(false, '❌ RFID not registered in system', ['rfid' => $rfid]);
}

$user = $result->fetch_assoc();
$stmt->close();

// Verify user type
if (strtolower($user['userType']) !== 'student') {
    sendJSON(false, '❌ Access denied. Only students can use this system.');
}

// Extract user information
$userId = (int)$user['ID'];
$email = $user['email'];
$firstName = $user['firstName'];
$fullName = trim($user['firstName'] . ' ' . ($user['middleName'] ?? '') . ' ' . $user['lastName']);

// Current date and time - FIXED: Use proper datetime format
$today = date('Y-m-d');
$currentDateTime = date('Y-m-d H:i:s');  // FULL datetime for database
$displayTime = date('h:i A');             // Display format for user
$displayDate = date('F d, Y');            // Display format for user

// Check if student already has attendance record for today
$checkStmt = $conn->prepare("SELECT id, timeIn, timeOut FROM attendance_log WHERE email = ? AND logDate = ?");
if (!$checkStmt) {
    sendJSON(false, 'Database prepare failed: ' . $conn->error);
}

$checkStmt->bind_param("ss", $email, $today);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();
$existingRecord = $checkResult->fetch_assoc();
$checkStmt->close();

// Helper function to check if datetime value is valid
function isValidDateTime($datetime) {
    if (empty($datetime) || $datetime === null) return false;
    
    // Check for invalid timestamps
    $invalidValues = ['0000-00-00 00:00:00', '00:00:00', '1970-01-01 00:00:00'];
    if (in_array($datetime, $invalidValues)) return false;
    
    // Check if it's a valid date after 2000
    $timestamp = strtotime($datetime);
    return $timestamp !== false && $timestamp > strtotime('2000-01-01');
}

if (!$existingRecord) {
    // ============================================
    // NO RECORD EXISTS = ALWAYS TIME IN
    // ============================================
    
    $insertStmt = $conn->prepare(
        "INSERT INTO attendance_log (user_id, studentid, email, studentName, logDate, timeIn, status) 
         VALUES (?, ?, ?, ?, ?, ?, 'Present')"
    );
    
    if (!$insertStmt) {
        sendJSON(false, 'Database prepare failed: ' . $conn->error);
    }
    
    $insertStmt->bind_param("iissss", $userId, $userId, $email, $fullName, $today, $currentDateTime);
    
    if ($insertStmt->execute()) {
        $insertStmt->close();
        
        // Log successful scan
        $logStmt = $conn->prepare("INSERT INTO rfid_scans (rfid_uid, action_type, success, message) VALUES (?, 'time_in', 1, 'Time in recorded successfully')");
        if ($logStmt) {
            $logStmt->bind_param("s", $rfid);
            $logStmt->execute();
            $logStmt->close();
        }
        
        sendJSON(true, '✅ Time In Recorded - Welcome, ' . $firstName . '!', [
            'action' => 'time_in',
            'student_name' => $fullName,
            'student_id' => $userId,
            'time' => $displayTime,
            'date' => $displayDate
        ]);
    } else {
        $error = $insertStmt->error;
        $insertStmt->close();
        sendJSON(false, 'Failed to record time in: ' . $error);
    }
    
} else {
    // ============================================
    // RECORD EXISTS = CHECK TIME IN/OUT STATUS
    // ============================================
    
    $hasTimeIn = isValidDateTime($existingRecord['timeIn']);
    $hasTimeOut = isValidDateTime($existingRecord['timeOut']);
    
    if (!$hasTimeIn) {
        // ============================================
        // HAS RECORD BUT NO TIME IN = UPDATE TIME IN
        // ============================================
        
        $updateStmt = $conn->prepare(
            "UPDATE attendance_log 
             SET timeIn = ?, status = 'Present', user_id = ?, studentid = ?, studentName = ?
             WHERE id = ?"
        );
        
        if (!$updateStmt) {
            sendJSON(false, 'Database prepare failed: ' . $conn->error);
        }
        
        $updateStmt->bind_param("siisi", $currentDateTime, $userId, $userId, $fullName, $existingRecord['id']);
        
        if ($updateStmt->execute()) {
            $updateStmt->close();
            
            // Log successful scan
            $logStmt = $conn->prepare("INSERT INTO rfid_scans (rfid_uid, action_type, success, message) VALUES (?, 'time_in', 1, 'Time in updated successfully')");
            if ($logStmt) {
                $logStmt->bind_param("s", $rfid);
                $logStmt->execute();
                $logStmt->close();
            }
            
            sendJSON(true, '✅ Time In Recorded - Welcome, ' . $firstName . '!', [
                'action' => 'time_in',
                'student_name' => $fullName,
                'student_id' => $userId,
                'time' => $displayTime,
                'date' => $displayDate
            ]);
        } else {
            $error = $updateStmt->error;
            $updateStmt->close();
            sendJSON(false, 'Failed to update time in: ' . $error);
        }
        
    } elseif ($hasTimeIn && !$hasTimeOut) {
        // ============================================
        // HAS TIME IN, NO TIME OUT = RECORD TIME OUT
        // ============================================
        
        // Calculate time difference to prevent accidental double scans
        $timeInTimestamp = strtotime($existingRecord['timeIn']);
        $currentTimestamp = time();
        $minutesDiff = ($currentTimestamp - $timeInTimestamp) / 60;
        
        // Require at least 5 minutes between time in and time out
        if ($minutesDiff < 5) {
            sendJSON(false, '⚠️ You just timed in ' . round($minutesDiff) . ' minutes ago. Wait a bit before timing out.', [
                'student_name' => $fullName,
                'time_in' => date('h:i A', $timeInTimestamp)
            ]);
        }
        
        $updateStmt = $conn->prepare(
            "UPDATE attendance_log 
             SET timeOut = ?, user_id = ?, studentid = ?, studentName = ?
             WHERE id = ?"
        );
        
        if (!$updateStmt) {
            sendJSON(false, 'Database prepare failed: ' . $conn->error);
        }
        
        $updateStmt->bind_param("siisi", $currentDateTime, $userId, $userId, $fullName, $existingRecord['id']);
        
        if ($updateStmt->execute()) {
            $updateStmt->close();
            
            // Log successful scan
            $logStmt = $conn->prepare("INSERT INTO rfid_scans (rfid_uid, action_type, success, message) VALUES (?, 'time_out', 1, 'Time out recorded successfully')");
            if ($logStmt) {
                $logStmt->bind_param("s", $rfid);
                $logStmt->execute();
                $logStmt->close();
            }
            
            sendJSON(true, '✅ Time Out Recorded - Goodbye, ' . $firstName . '!', [
                'action' => 'time_out',
                'student_name' => $fullName,
                'student_id' => $userId,
                'time' => $displayTime,
                'date' => $displayDate,
                'time_in' => date('h:i A', $timeInTimestamp)
            ]);
        } else {
            $error = $updateStmt->error;
            $updateStmt->close();
            sendJSON(false, 'Failed to record time out: ' . $error);
        }
        
    } elseif ($hasTimeIn && $hasTimeOut) {
        // ============================================
        // BOTH TIME IN AND TIME OUT EXIST = ALREADY COMPLETE
        // ============================================
        
        sendJSON(false, '⚠️ You have already completed attendance for today', [
            'student_name' => $fullName,
            'time_in' => date('h:i A', strtotime($existingRecord['timeIn'])),
            'time_out' => date('h:i A', strtotime($existingRecord['timeOut']))
        ]);
        
    } else {
        // This shouldn't happen, but just in case
        sendJSON(false, '⚠️ Unexpected attendance state. Please contact administrator.');
    }
}

$conn->close();
?>