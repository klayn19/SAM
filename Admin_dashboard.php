<?php
session_start();
// Set timezone first
date_default_timezone_set('Asia/Manila');

// require logged in AND admin role
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || ($_SESSION['role'] ?? '') !== 'admin') {
    if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']) {
        header('Location: dashboard_student.php');
    } else {
        header('Location: index.php');
    }
    exit();
}

require_once 'backend/config.php';

// Simple routing via query parameter
$view = $_GET['view'] ?? 'home';
$selectedStudentId = $_GET['student_id'] ?? null;

// Fetch attendance data with email-based connection
$attendanceData = [];
$sql = "SELECT 
        a.studentName, 
        a.email,
        a.timeIn, 
        a.timeOut, 
        a.logDate, 
        a.status,
        u.ID as studentID,
        u.firstName,
        u.lastName
        FROM attendance_log a 
        LEFT JOIN users u ON a.email = u.email
        WHERE (u.userType = 'student' OR u.userType IS NULL)
        ORDER BY a.logDate DESC, a.timeIn DESC";

if ($result = $conn->query($sql)) {
    while ($row = $result->fetch_assoc()) {
        // Format time display - handle null/empty values
        $timeIn = $row['timeIn'];
        $timeOut = $row['timeOut'];
        
        // Format timeIn
        if (!empty($timeIn) && $timeIn != '00:00:00' && $timeIn != '0000-00-00 00:00:00' && $timeIn != null) {
            try {
                $timeInObj = DateTime::createFromFormat('H:i:s', $timeIn);
                if ($timeInObj) {
                    $row['timeIn'] = $timeInObj->format('h:i A');
                } else {
                    // Try parsing as string
                    $row['timeIn'] = date('h:i A', strtotime($timeIn));
                }
            } catch (Exception $e) {
                $row['timeIn'] = date('h:i A', strtotime($timeIn));
            }
        } else {
            $row['timeIn'] = 'Not recorded';
        }
        
        // Format timeOut
        if (!empty($timeOut) && $timeOut != '00:00:00' && $timeOut != '0000-00-00 00:00:00' && $timeOut != null) {
            try {
                $timeOutObj = DateTime::createFromFormat('H:i:s', $timeOut);
                if ($timeOutObj) {
                    $row['timeOut'] = $timeOutObj->format('h:i A');
                } else {
                    // Try parsing as string
                    $row['timeOut'] = date('h:i A', strtotime($timeOut));
                }
            } catch (Exception $e) {
                $row['timeOut'] = date('h:i A', strtotime($timeOut));
            }
        } else {
            $row['timeOut'] = 'Not recorded';
        }
        
        // Format date
        $logDate = $row['logDate'];
        if (!empty($logDate) && $logDate != '0000-00-00' && $logDate != null) {
            try {
                $dateObj = DateTime::createFromFormat('Y-m-d', $logDate);
                if ($dateObj) {
                    $row['logDate'] = $dateObj->format('M d, Y');
                } else {
                    $row['logDate'] = date('M d, Y', strtotime($logDate));
                }
            } catch (Exception $e) {
                $row['logDate'] = date('M d, Y', strtotime($logDate));
            }
        } else {
            $row['logDate'] = 'Unknown';
        }
        
        $attendanceData[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SAM Dashboard</title>
    <link rel="stylesheet" href="CSS/admin.css">
    <style>
        /* Add hover effect for clickable rows */
        .table-row {
            cursor: pointer;
            transition: background-color 0.3s, transform 0.2s;
        }
        
        .table-row:hover {
            background-color: rgba(0, 178, 255, 0.1);
            transform: translateX(5px);
        }
        
        .profile-table {
            user-select: none;
        }
        
        /* Style for "Not recorded" text */
        .not-recorded {
            color: #999;
            font-style: italic;
        }

        /* Student Detail View Styles */
        .student-detail-container {
            background: var(--card-bg);
            border-radius: 15px;
            padding: 30px;
            margin: 20px;
            color: var(--text-primary);
        }

        .student-detail-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid transparent;
            border-image: var(--primary-gradient) 1;
        }

        .back-btn {
            background: #6c757d;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.3s;
        }

        .back-btn:hover {
            background: #5a6268;
        }

        .student-info {
            margin-bottom: 30px;
        }

        .student-info h2 {
            color: var(--text-primary);
            margin-bottom: 10px;
            font-size: 28px;
        }

        .student-info p {
            color: var(--text-muted);
            font-size: 14px;
        }

        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .summary-card {
            background: var(--primary-gradient);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }

        .summary-card h3 {
            font-size: 32px;
            margin-bottom: 10px;
        }

        .summary-card p {
            font-size: 14px;
            opacity: 0.9;
        }

        .attendance-records-title {
            color: var(--text-primary);
            font-size: 24px;
            margin-bottom: 20px;
        }

        .attendance-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            background: var(--card-bg);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .attendance-table thead {
            background: var(--primary-gradient);
        }

        .attendance-table th {
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }

        .attendance-table td {
            padding: 12px 15px;
            border-bottom: 1px solid var(--card-hover);
            color: var(--text-secondary);
        }

        .attendance-table tbody tr:hover {
            background-color: var(--card-hover);
        }

        .attendance-table tbody tr:last-child td {
            border-bottom: none;
        }

        .status-badge {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .status-present {
            background: var(--success-green);
            color: white;
        }

        .status-absent {
            background: var(--danger-red);
            color: white;
        }

        .print-btn-detail {
            background: var(--primary-gradient);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .print-btn-detail:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        @media print {
            .sidebar, .navbar, .back-btn, .print-btn-detail, .no-print {
                display: none !important;
            }
            
            .content-wrapper {
                margin: 0 !important;
                width: 100% !important;
            }

            .student-detail-container {
                box-shadow: none;
                padding: 20px;
            }

            .summary-cards {
                break-inside: avoid;
            }

            .attendance-table {
                page-break-inside: auto;
            }

            .attendance-table tr {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-body">
        <div class="sidebar no-print">
            <div class="logo">
                <img src="Logo2.png" alt="SAM Logo">
            </div>
            <a href="?view=home" class="<?php echo ($view === 'home') ? 'active' : ''; ?>">
                <i class="icon">🏠</i> Home
            </a>
            <a href="?view=profile" class="<?php echo ($view === 'profile') ? 'active' : ''; ?>">
                <i class="icon">👤</i> Profile
            </a>
            <a href="?view=record" class="<?php echo ($view === 'record' && !$selectedStudentId) ? 'active' : ''; ?>">
                <i class="icon">📅</i> Record
            </a>
            <a href="rfid_manage.php">
                <i class="icon">💳</i> RFID Management
            </a>
            <a href="Attendance_manual.php">
                <i class="icon">📝</i> Attendance Manual
            </a>
        </div>

        <div class="content-wrapper">
            <div class="navbar no-print">
                <div class="logo">
                    <span>Smart Attendance Monitoring System</span>
                </div>
                <div class="nav-icons">
                    <!-- Theme Toggle Button -->
                    <button class="theme-toggle" id="themeToggle" onclick="toggleTheme()">
                        <span class="icon" id="themeIcon">🌙</span>
                    </button>
                    
                    <div class="user-box">
                        <i class="icon">👤</i>
                        <span><?= htmlspecialchars($_SESSION['firstName'] ?? 'User') ?></span>
                    </div>
                    <form action="backend/logout.php" method="post" class="logout-form" onsubmit="return confirm('Are you sure you want to log out?');">
                        <button type="submit" class="logout-btn">🚪 Logout</button>
                    </form>
                </div>
            </div>

            <div class="main-content">
                <?php if ($view === 'profile'): ?>
                    <?php
                    // Fetch all student records from users table
                    $studentRecords = [];
                    $profileSql = "SELECT ID, firstName, middleName, lastName, userType FROM users WHERE userType = 'student' ORDER BY firstName, lastName";
                    if ($result = $conn->query($profileSql)) {
                        while ($row = $result->fetch_assoc()) {
                            $studentRecords[] = [
                                'id' => $row['ID'],
                                'name' => trim($row['firstName'] . ' ' . $row['middleName'] . ' ' . $row['lastName']),
                                'section' => $row['userType']
                            ];
                        }
                    }
                    ?>
                    <div class="profile-container">
                        <h2>Student Profiles</h2>
                        <p>Click on a student to view their full profile</p>
                        <div class="profile-table">
                            <div class="table-header">
                                <div class="header-cell">Names</div>
                                <div class="header-cell">Student ID</div>
                                <div class="header-cell">Type</div>
                            </div>
                            <div class="table-body">
                                <?php if (empty($studentRecords)): ?>
                                    <div class="empty-message">No student records found</div>
                                <?php else: ?>
                                    <?php foreach ($studentRecords as $student): ?>
                                        <div class="table-row" onclick="viewStudentProfile('<?= htmlspecialchars($student['id']) ?>')">
                                            <div class="table-cell"><?= htmlspecialchars($student['name']) ?></div>
                                            <div class="table-cell"><?= htmlspecialchars($student['id']) ?></div>
                                            <div class="table-cell"><?= htmlspecialchars($student['section']) ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                <?php elseif ($view === 'record' && $selectedStudentId): ?>
                    <?php
                    // Fetch student details
                    $studentSql = "SELECT ID, firstName, middleName, lastName, email FROM users WHERE ID = ? AND userType = 'student'";
                    $studentInfo = null;
                    if ($stmt = $conn->prepare($studentSql)) {
                        $stmt->bind_param('i', $selectedStudentId);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        if ($result->num_rows > 0) {
                            $studentInfo = $result->fetch_assoc();
                        }
                        $stmt->close();
                    }

                    if ($studentInfo):
                        // Fetch attendance records for this student
                        $attendanceSql = "SELECT logDate, timeIn, timeOut, status 
                                         FROM attendance_log 
                                         WHERE email = ? 
                                         ORDER BY logDate DESC";
                        $studentAttendance = [];
                        $daysPresent = 0;
                        $daysAbsent = 0;
                        
                        if ($stmt = $conn->prepare($attendanceSql)) {
                            $stmt->bind_param('s', $studentInfo['email']);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            while ($row = $result->fetch_assoc()) {
                                // Count attendance
                                if ($row['status'] === 'Present') $daysPresent++;
                                if ($row['status'] === 'Absent') $daysAbsent++;
                                
                                // Format times
                                $timeIn = $row['timeIn'];
                                $timeOut = $row['timeOut'];
                                
                                // Format timeIn
                                if (!empty($timeIn) && $timeIn != '00:00:00' && $timeIn != null) {
                                    try {
                                        $timeInObj = DateTime::createFromFormat('H:i:s', $timeIn);
                                        if ($timeInObj) {
                                            $row['timeIn'] = $timeInObj->format('h:i A');
                                        } else {
                                            $row['timeIn'] = date('h:i A', strtotime($timeIn));
                                        }
                                    } catch (Exception $e) {
                                        $row['timeIn'] = date('h:i A', strtotime($timeIn));
                                    }
                                } else {
                                    $row['timeIn'] = 'Not recorded';
                                }
                                
                                // Format timeOut
                                if (!empty($timeOut) && $timeOut != '00:00:00' && $timeOut != null) {
                                    try {
                                        $timeOutObj = DateTime::createFromFormat('H:i:s', $timeOut);
                                        if ($timeOutObj) {
                                            $row['timeOut'] = $timeOutObj->format('h:i A');
                                        } else {
                                            $row['timeOut'] = date('h:i A', strtotime($timeOut));
                                        }
                                    } catch (Exception $e) {
                                        $row['timeOut'] = date('h:i A', strtotime($timeOut));
                                    }
                                } else {
                                    $row['timeOut'] = 'Not recorded';
                                }
                                
                                // Format date
                                $logDate = $row['logDate'];
                                if (!empty($logDate) && $logDate != '0000-00-00' && $logDate != null) {
                                    try {
                                        $dateObj = DateTime::createFromFormat('Y-m-d', $logDate);
                                        if ($dateObj) {
                                            $row['logDate'] = $dateObj->format('M d, Y');
                                        } else {
                                            $row['logDate'] = date('M d, Y', strtotime($logDate));
                                        }
                                    } catch (Exception $e) {
                                        $row['logDate'] = date('M d, Y', strtotime($logDate));
                                    }
                                } else {
                                    $row['logDate'] = 'Unknown';
                                }
                                
                                $studentAttendance[] = $row;
                            }
                            $stmt->close();
                        }
                        $totalDays = $daysPresent + $daysAbsent;
                        $attendanceRate = $totalDays > 0 ? round(($daysPresent / $totalDays) * 100, 1) : 0;
                    ?>
                    
                    <div class="student-detail-container">
                        <div class="student-detail-header no-print">
                            <button class="back-btn" onclick="window.location.href='?view=record'">← Back to Students</button>
                            <button class="print-btn-detail" onclick="window.print()">🖨️ Print Record</button>
                        </div>

                        <div class="student-info">
                            <h2><?= htmlspecialchars($studentInfo['firstName'] . ' ' . $studentInfo['middleName'] . ' ' . $studentInfo['lastName']) ?></h2>
                            <p><strong>Student ID:</strong> <?= htmlspecialchars($studentInfo['ID']) ?></p>
                            <p><strong>Email:</strong> <?= htmlspecialchars($studentInfo['email']) ?></p>
                        </div>

                        <div class="summary-cards">
                            <div class="summary-card">
                                <h3><?= $daysPresent ?></h3>
                                <p>Days Present</p>
                            </div>
                            <div class="summary-card">
                                <h3><?= $daysAbsent ?></h3>
                                <p>Days Absent</p>
                            </div>
                            <div class="summary-card">
                                <h3><?= $attendanceRate ?>%</h3>
                                <p>Attendance Rate</p>
                            </div>
                        </div>

                        <h3 class="attendance-records-title">Attendance Records</h3>
                        
                        <?php if (empty($studentAttendance)): ?>
                            <p style="text-align: center; color: var(--text-muted); padding: 40px;">No attendance records found for this student.</p>
                        <?php else: ?>
                            <table class="attendance-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Time In</th>
                                        <th>Time Out</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($studentAttendance as $record): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($record['logDate']) ?></td>
                                            <td><?= htmlspecialchars($record['timeIn']) ?></td>
                                            <td><?= htmlspecialchars($record['timeOut']) ?></td>
                                            <td>
                                                <span class="status-badge <?= $record['status'] === 'Present' ? 'status-present' : 'status-absent' ?>">
                                                    <?= htmlspecialchars($record['status']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>

                    <?php else: ?>
                        <div class="student-detail-container">
                            <p style="text-align: center; color: var(--text-muted);">Student not found.</p>
                            <div style="text-align: center; margin-top: 20px;">
                                <button class="back-btn" onclick="window.location.href='?view=record'">← Back to Students</button>
                            </div>
                        </div>
                    <?php endif; ?>

                <?php elseif ($view === 'record'): ?>
                    <?php
                    // Fetch all students for record view with RFID UID and Email
                    $studentRecords = [];
                    $recordSql = "SELECT ID, firstName, middleName, lastName, email, rfid_uid FROM users WHERE userType = 'student' ORDER BY firstName, lastName";
                    if ($result = $conn->query($recordSql)) {
                        while ($row = $result->fetch_assoc()) {
                            $studentRecords[] = [
                                'id' => $row['ID'],
                                'name' => trim($row['firstName'] . ' ' . $row['middleName'] . ' ' . $row['lastName']),
                                'email' => $row['email'],
                                'rfid_uid' => $row['rfid_uid']
                            ];
                        }
                    }
                    ?>
                    <div class="profile-container">
                        <h2>Student Records</h2>
                        <p>Click on a student name to view their attendance record</p>
                        <div class="profile-table">
                            <div class="table-header table-header-four">
                                <div class="header-cell">Student Name</div>
                                <div class="header-cell">RFID UID</div>
                                <div class="header-cell">Email</div>
                            </div>
                            <div class="table-body">
                                <?php if (empty($studentRecords)): ?>
                                    <div class="empty-message">No student records found</div>
                                <?php else: ?>
                                    <?php foreach ($studentRecords as $student): ?>
                                        <div class="table-row table-row-four" onclick="viewStudentRecord('<?= htmlspecialchars($student['id']) ?>')">
                                            <div class="table-cell"><?= htmlspecialchars($student['name']) ?></div>
                                            <div class="table-cell">
                                                <?php if (!empty($student['rfid_uid'])): ?>
                                                    <span style="color: #4caf50; font-weight: 600;">✓ <?= htmlspecialchars($student['rfid_uid']) ?></span>
                                                <?php else: ?>
                                                    <span style="color: #f44336; font-style: italic;">✗ Not Assigned</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="table-cell"><?= htmlspecialchars($student['email']) ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                <?php else: ?>
                    <div class="table-container">
                        <div class="table-box-container">
                            <div class="table-box">
                                <h3>Names</h3>
                                <div class="data-blocks scrollable">
                                    <?php if (empty($attendanceData)): ?>
                                        <div class="data-block empty">No records found</div>
                                    <?php else: ?>
                                        <?php foreach ($attendanceData as $row): ?>
                                            <div class="data-block"><?= htmlspecialchars($row['studentName']) ?></div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="table-box-container">
                            <div class="table-box">
                                <h3>Time In</h3>
                                <div class="data-blocks scrollable">
                                    <?php if (empty($attendanceData)): ?>
                                        <div class="data-block empty">No records found</div>
                                    <?php else: ?>
                                        <?php foreach ($attendanceData as $row): ?>
                                            <div class="data-block <?= ($row['timeIn'] === 'Not recorded') ? 'not-recorded' : '' ?>">
                                                <?= htmlspecialchars($row['timeIn']) ?>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="table-box-container">
                            <div class="table-box">
                                <h3>Time Out</h3>
                                <div class="data-blocks scrollable">
                                    <?php if (empty($attendanceData)): ?>
                                        <div class="data-block empty">No records found</div>
                                    <?php else: ?>
                                        <?php foreach ($attendanceData as $row): ?>
                                            <div class="data-block <?= ($row['timeOut'] === 'Not recorded') ? 'not-recorded' : '' ?>">
                                                <?= htmlspecialchars($row['timeOut']) ?>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Theme Toggle Functionality
        function toggleTheme() {
            const html = document.documentElement;
            const themeIcon = document.getElementById('themeIcon');
            const currentTheme = html.getAttribute('data-theme');
            
            if (currentTheme === 'light') {
                html.setAttribute('data-theme', 'dark');
                themeIcon.textContent = '🌙';
                localStorage.setItem('theme', 'dark');
            } else {
                html.setAttribute('data-theme', 'light');
                themeIcon.textContent = '☀️';
                localStorage.setItem('theme', 'light');
            }
        }

        // Load saved theme on page load
        document.addEventListener('DOMContentLoaded', function() {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            const html = document.documentElement;
            const themeIcon = document.getElementById('themeIcon');
            
            html.setAttribute('data-theme', savedTheme);
            themeIcon.textContent = savedTheme === 'light' ? '☀️' : '🌙';
        });

        function viewStudentProfile(studentId) {
            // Redirect to student dashboard with the student ID as a parameter
            window.location.href = 'dashboard_student.php?student_id=' + encodeURIComponent(studentId);
        }

        function viewStudentRecord(studentId) {
            // Redirect to record view with the student ID
            window.location.href = '?view=record&student_id=' + encodeURIComponent(studentId);
        }
    </script>
</body>
</html>