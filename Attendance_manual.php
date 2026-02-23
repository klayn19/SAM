<?php
session_start();
date_default_timezone_set('Asia/Manila');
require_once 'backend/config.php';

// Handle logout
if (isset($_POST['logout'])) {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

// Handle login
$loginError = '';
if (isset($_POST['login'])) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    $stmt = $conn->prepare("SELECT ID, firstName, lastName, email, Password, userType FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['Password'])) {
            $_SESSION['kiosk_logged_in'] = true;
            $_SESSION['kiosk_user_id'] = $user['ID'];
            $_SESSION['kiosk_email'] = $user['email'];
            $_SESSION['kiosk_name'] = $user['firstName'] . ' ' . $user['lastName'];
            $_SESSION['kiosk_user_type'] = $user['userType'];
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit();
        } else {
            $loginError = 'Invalid password';
        }
    } else {
        $loginError = 'Email not found';
    }
    $stmt->close();
}

// Handle Clock In
$message = '';
$messageType = '';
if (isset($_POST['clock_in']) && isset($_SESSION['kiosk_logged_in'])) {
    $userId = $_SESSION['kiosk_user_id'];
    $email = $_SESSION['kiosk_email'];
    $studentName = $_SESSION['kiosk_name'];
    $logDate = date('Y-m-d');
    $timeIn = date('H:i:s');
    
    // Check if already clocked in today
    $checkStmt = $conn->prepare("SELECT studentid FROM attendance_log WHERE email = ? AND logDate = ?");
    $checkStmt->bind_param("ss", $email, $logDate);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        $message = 'You already clocked in today!';
        $messageType = 'warning';
    } else {
        // Insert new attendance record with proper time
        $stmt = $conn->prepare("INSERT INTO attendance_log (studentid, email, studentName, logDate, timeIn, timeOut, status) VALUES (?, ?, ?, ?, ?, NULL, 'Present')");
        $stmt->bind_param("issss", $userId, $email, $studentName, $logDate, $timeIn);
        
        if ($stmt->execute()) {
            $message = 'Clock In successful! Time: ' . date('h:i A');
            $messageType = 'success';
        } else {
            $message = 'Failed to record attendance: ' . $conn->error;
            $messageType = 'error';
        }
        $stmt->close();
    }
    $checkStmt->close();
}

// Handle Clock Out
if (isset($_POST['clock_out']) && isset($_SESSION['kiosk_logged_in'])) {
    $email = $_SESSION['kiosk_email'];
    $logDate = date('Y-m-d');
    $timeOut = date('H:i:s');
    
    // Check if there's a clock in record first
    $checkStmt = $conn->prepare("SELECT studentid, timeIn, timeOut FROM attendance_log WHERE email = ? AND logDate = ? ORDER BY timeIn DESC LIMIT 1");
    $checkStmt->bind_param("ss", $email, $logDate);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        $message = 'You need to clock in first before clocking out!';
        $messageType = 'warning';
    } else {
        $record = $checkResult->fetch_assoc();
        
        if (!empty($record['timeOut']) && $record['timeOut'] != '00:00:00') {
            $message = 'You have already clocked out today!';
            $messageType = 'warning';
        } else {
            // Update with clock out time
            $stmt = $conn->prepare("UPDATE attendance_log SET timeOut = ? WHERE email = ? AND logDate = ? AND (timeOut IS NULL OR timeOut = '00:00:00') ORDER BY timeIn DESC LIMIT 1");
            $stmt->bind_param("sss", $timeOut, $email, $logDate);
            
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $message = 'Clock Out successful! Time: ' . date('h:i A');
                    $messageType = 'success';
                } else {
                    $message = 'Failed to update clock out time';
                    $messageType = 'error';
                }
            } else {
                $message = 'Failed to update attendance: ' . $conn->error;
                $messageType = 'error';
            }
            $stmt->close();
        }
    }
    $checkStmt->close();
}

// Handle Mark Absent (Admin only)
if (isset($_POST['mark_absent']) && isset($_SESSION['kiosk_logged_in']) && $_SESSION['kiosk_user_type'] === 'admin') {
    $targetEmail = $_POST['student_email'];
    $logDate = date('Y-m-d');
    
    // Get student info
    $stmt = $conn->prepare("SELECT ID, firstName, lastName FROM users WHERE email = ?");
    $stmt->bind_param("s", $targetEmail);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $student = $result->fetch_assoc();
        $studentId = $student['ID'];
        $studentName = $student['firstName'] . ' ' . $student['lastName'];
        
        // Check if record exists for today
        $checkStmt = $conn->prepare("SELECT studentid FROM attendance_log WHERE email = ? AND logDate = ?");
        $checkStmt->bind_param("ss", $targetEmail, $logDate);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            $message = 'Student already has an attendance record for today';
            $messageType = 'warning';
        } else {
            $insertStmt = $conn->prepare("INSERT INTO attendance_log (studentid, email, studentName, logDate, timeIn, timeOut, status) VALUES (?, ?, ?, ?, NULL, NULL, 'Absent')");
            $insertStmt->bind_param("isss", $studentId, $targetEmail, $studentName, $logDate);
            
            if ($insertStmt->execute()) {
                $message = 'Marked ' . $studentName . ' as Absent';
                $messageType = 'success';
            } else {
                $message = 'Failed to mark absent: ' . $conn->error;
                $messageType = 'error';
            }
            $insertStmt->close();
        }
        $checkStmt->close();
    } else {
        $message = 'Student not found';
        $messageType = 'error';
    }
    $stmt->close();
}

// Get today's attendance records
$todayRecords = [];
if (isset($_SESSION['kiosk_logged_in'])) {
    $logDate = date('Y-m-d');
    
    if ($_SESSION['kiosk_user_type'] === 'admin') {
        // Admin sees all records
        $result = $conn->query("SELECT a.*, u.firstName, u.lastName FROM attendance_log a LEFT JOIN users u ON a.email = u.email WHERE a.logDate = '$logDate' ORDER BY a.timeIn DESC");
    } else {
        // Student sees only their records
        $email = $_SESSION['kiosk_email'];
        $stmt = $conn->prepare("SELECT * FROM attendance_log WHERE email = ? AND logDate = ? ORDER BY timeIn DESC");
        $stmt->bind_param("ss", $email, $logDate);
        $stmt->execute();
        $result = $stmt->get_result();
    }
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $todayRecords[] = $row;
        }
    }
}

// Get all students (for admin)
$allStudents = [];
if (isset($_SESSION['kiosk_logged_in']) && $_SESSION['kiosk_user_type'] === 'admin') {
    $result = $conn->query("SELECT ID, firstName, lastName, email FROM users WHERE userType = 'student' ORDER BY firstName, lastName");
    while ($row = $result->fetch_assoc()) {
        $allStudents[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SAM Attendance Kiosk</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #7C4DFF 0%, #00BCD4 100%);
            min-height: 100vh;
            padding: 20px;
            position: relative;
        }
        
        .back-to-dashboard {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }
        
        .back-to-dashboard a {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            background: white;
            color: #7C4DFF;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transition: all 0.3s;
        }
        
        .back-to-dashboard a:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.2);
            background: #f8f8f8;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding-top: 20px;
        }
        
        .header {
            text-align: center;
            color: white;
            margin-bottom: 30px;
        }
        
        .header h1 {
            font-size: 48px;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .header p {
            font-size: 18px;
            opacity: 0.9;
        }
        
        .card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .login-form {
            max-width: 400px;
            margin: 0 auto;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
        }
        
        .form-group input, .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #7C4DFF;
        }
        
        .btn {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #7C4DFF 0%, #00BCD4 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(124, 77, 255, 0.4);
        }
        
        .btn-success {
            background: #4CAF50;
            color: white;
        }
        
        .btn-success:hover {
            background: #45a049;
        }
        
        .btn-danger {
            background: #f44336;
            color: white;
        }
        
        .btn-danger:hover {
            background: #da190b;
        }
        
        .btn-warning {
            background: #ff9800;
            color: white;
        }
        
        .btn-warning:hover {
            background: #e68900;
        }
        
        .btn-small {
            width: auto;
            padding: 8px 16px;
            font-size: 14px;
        }
        
        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .message.warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .user-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 20px;
            background: linear-gradient(135deg, #7C4DFF 0%, #00BCD4 100%);
            border-radius: 10px;
            color: white;
        }
        
        .user-info h2 {
            font-size: 24px;
        }
        
        .user-info p {
            opacity: 0.9;
            margin-top: 5px;
        }
        
        .clock-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .current-time {
            text-align: center;
            font-size: 48px;
            font-weight: bold;
            color: #7C4DFF;
            margin: 30px 0;
            font-family: 'Courier New', monospace;
        }
        
        .current-date {
            text-align: center;
            font-size: 24px;
            color: #666;
            margin-bottom: 30px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        table th, table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        table th {
            background: #f5f5f5;
            font-weight: 600;
            color: #333;
        }
        
        table tr:hover {
            background: #f9f9f9;
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-badge.present {
            background: #d4edda;
            color: #155724;
        }
        
        .status-badge.absent {
            background: #f8d7da;
            color: #721c24;
        }
        
        .admin-section {
            margin-top: 30px;
            padding-top: 30px;
            border-top: 2px solid #e0e0e0;
        }
        
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        @media (max-width: 768px) {
            .clock-buttons, .grid-2 {
                grid-template-columns: 1fr;
            }
            
            .header h1 {
                font-size: 32px;
            }
            
            .current-time {
                font-size: 36px;
            }
            
            .back-to-dashboard {
                position: static;
                margin-bottom: 20px;
                text-align: center;
            }
            
            .back-to-dashboard a {
                display: inline-flex;
            }
        }
    </style>
</head>
<body>
    <!-- Back to Dashboard Button - Always Visible -->
    <div class="back-to-dashboard">
        <a href="javascript:history.back()">
            <span>←</span>
            <span>Back to Dashboard</span>
        </a>
    </div>

    <div class="container">
        <div class="header">
            <h1>🎯 SAM Attendance Kiosk</h1>
            <p>Smart Attendance Monitoring System</p>
        </div>
        
        <?php if (!isset($_SESSION['kiosk_logged_in'])): ?>
            <!-- Login Form -->
            <div class="card login-form">
                <h2 style="text-align: center; margin-bottom: 30px; color: #7C4DFF;">Login to Clock In/Out</h2>
                
                <?php if ($loginError): ?>
                    <div class="message error">❌ <?= htmlspecialchars($loginError) ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" required placeholder="your.email@example.com">
                    </div>
                    
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" required placeholder="Enter your password">
                    </div>
                    
                    <button type="submit" name="login" class="btn btn-primary">Login</button>
                </form>
                
                <p style="text-align: center; margin-top: 20px; color: #666;">
                    Don't have an account? <a href="index.php" style="color: #7C4DFF;">Register here</a>
                </p>
            </div>
        <?php else: ?>
            <!-- Logged In View -->
            <div class="card">
                <div class="user-info">
                    <div>
                        <h2>👤 <?= htmlspecialchars($_SESSION['kiosk_name']) ?></h2>
                        <p><?= htmlspecialchars($_SESSION['kiosk_email']) ?> | <?= htmlspecialchars(ucfirst($_SESSION['kiosk_user_type'])) ?></p>
                    </div>
                    <form method="POST" style="margin: 0;">
                        <button type="submit" name="logout" class="btn btn-danger btn-small">Logout</button>
                    </form>
                </div>
                
                <?php if ($message): ?>
                    <div class="message <?= $messageType ?>">
                        <?php if ($messageType === 'success'): ?>✅<?php elseif ($messageType === 'error'): ?>❌<?php else: ?>⚠️<?php endif; ?>
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>
                
                <div class="current-time" id="current-time"></div>
                <div class="current-date"><?= date('l, F j, Y') ?></div>
                
                <?php if ($_SESSION['kiosk_user_type'] === 'student'): ?>
                    <!-- Student Clock In/Out -->
                    <div class="clock-buttons">
                        <form method="POST">
                            <button type="submit" name="clock_in" class="btn btn-success" style="height: 80px; font-size: 20px;">
                                🕐 Clock In
                            </button>
                        </form>
                        
                        <form method="POST">
                            <button type="submit" name="clock_out" class="btn btn-warning" style="height: 80px; font-size: 20px;">
                                🕐 Clock Out
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
                
                <!-- Today's Records -->
                <h3 style="margin-top: 30px; margin-bottom: 15px; color: #7C4DFF;">
                    📋 Today's Attendance Records
                </h3>
                
                <?php if (empty($todayRecords)): ?>
                    <p style="text-align: center; color: #999; padding: 40px;">No attendance records for today</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Student Name</th>
                                <th>Email</th>
                                <th>Time In</th>
                                <th>Time Out</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($todayRecords as $record): ?>
                                <tr>
                                    <td><?= htmlspecialchars($record['studentName']) ?></td>
                                    <td><?= htmlspecialchars($record['email'] ?? 'N/A') ?></td>
                                    <td>
                                        <?php 
                                        $timeIn = $record['timeIn'];
                                        if (!empty($timeIn) && $timeIn != '00:00:00' && $timeIn != null) {
                                            try {
                                                $timeInObj = DateTime::createFromFormat('H:i:s', $timeIn);
                                                if ($timeInObj) {
                                                    echo $timeInObj->format('h:i A');
                                                } else {
                                                    // Try parsing as string
                                                    echo date('h:i A', strtotime($timeIn));
                                                }
                                            } catch (Exception $e) {
                                                echo date('h:i A', strtotime($timeIn));
                                            }
                                        } else {
                                            echo '<span style="color: #999;">-</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $timeOut = $record['timeOut'];
                                        if (!empty($timeOut) && $timeOut != '00:00:00' && $timeOut != null) {
                                            try {
                                                $timeOutObj = DateTime::createFromFormat('H:i:s', $timeOut);
                                                if ($timeOutObj) {
                                                    echo $timeOutObj->format('h:i A');
                                                } else {
                                                    // Try parsing as string
                                                    echo date('h:i A', strtotime($timeOut));
                                                }
                                            } catch (Exception $e) {
                                                echo date('h:i A', strtotime($timeOut));
                                            }
                                        } else {
                                            echo '<span style="color: #999;">Not yet</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <span class="status-badge <?= strtolower($record['status']) ?>">
                                            <?= htmlspecialchars($record['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                
                <?php if ($_SESSION['kiosk_user_type'] === 'admin'): ?>
                    <!-- Admin: Mark Student as Absent -->
                    <div class="admin-section">
                        <h3 style="margin-bottom: 20px; color: #7C4DFF;">👨‍💼 Admin: Mark Student Absent</h3>
                        
                        <form method="POST" class="grid-2">
                            <div class="form-group">
                                <label>Select Student</label>
                                <select name="student_email" required>
                                    <option value="">-- Choose Student --</option>
                                    <?php foreach ($allStudents as $student): ?>
                                        <option value="<?= htmlspecialchars($student['email']) ?>">
                                            <?= htmlspecialchars($student['firstName'] . ' ' . $student['lastName']) ?> 
                                            (<?= htmlspecialchars($student['email']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div style="display: flex; align-items: flex-end;">
                                <button type="submit" name="mark_absent" class="btn btn-danger">
                                    ❌ Mark as Absent
                                </button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        // Live clock
        function updateTime() {
            const now = new Date();
            const hours = now.getHours();
            const minutes = now.getMinutes();
            const seconds = now.getSeconds();
            const ampm = hours >= 12 ? 'PM' : 'AM';
            const displayHours = hours % 12 || 12;
            
            const timeString = 
                String(displayHours).padStart(2, '0') + ':' +
                String(minutes).padStart(2, '0') + ':' +
                String(seconds).padStart(2, '0') + ' ' +
                ampm;
            
            const timeElement = document.getElementById('current-time');
            if (timeElement) {
                timeElement.textContent = timeString;
            }
        }
        
        // Update every second
        setInterval(updateTime, 1000);
        updateTime(); // Initial call
    </script>
</body>
</html>
<?php $conn->close(); ?>