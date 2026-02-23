<?php
session_start();
require_once 'backend/config.php';

// Check if viewing as admin (with student_id parameter)
$viewingAsAdmin = false;
$studentId = null;

if (isset($_GET['student_id'])) {
    // Admin is viewing a student's profile
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        $viewingAsAdmin = true;
        $studentId = $_GET['student_id'];
    } else {
        // Not admin, redirect
        header('Location: index.php');
        exit();
    }
} else {
    // Regular student login check
    if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
        header('Location: index.php');
        exit();
    }
    
    // If admin tries to access without student_id, redirect to admin dashboard
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        header('Location: Admin_dashboard.php');
        exit();
    }
    
    // Get student ID from session
    $studentId = $_SESSION['ID'] ?? null;
}

// Handle profile picture upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_picture']) && !$viewingAsAdmin && $studentId) {
    $uploadDir = 'uploads/profiles/';
    
    // Create directory if it doesn't exist
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $file = $_FILES['profile_picture'];
    $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
    
    if (in_array($fileExt, $allowed) && $file['error'] === 0) {
        // Remove old profile pictures for this student
        foreach (glob($uploadDir . 'profile_' . $studentId . '.*') as $oldFile) {
            unlink($oldFile);
        }
        
        $fileName = 'profile_' . $studentId . '.' . $fileExt;
        $filePath = $uploadDir . $fileName;
        
        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            // Update profilePicture column in database
            $stmt = $conn->prepare("UPDATE users SET profilePicture = ? WHERE ID = ?");
            $stmt->bind_param("si", $fileName, $studentId);
            $stmt->execute();
            $stmt->close();
            
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit();
        }
    }
}

// Fetch student information from users table
$studentInfo = null;
if ($studentId) {
    $stmt = $conn->prepare("SELECT ID, firstName, middleName, lastName, email, contactNumber, userType, profilePicture FROM users WHERE ID = ?");
    $stmt->bind_param("i", $studentId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $studentInfo = $result->fetch_assoc();
    }
    $stmt->close();
}

// Get profile picture path
$profilePicture = 'default-avatar.png';
if ($studentInfo && !empty($studentInfo['profilePicture'])) {
    $filePath = 'uploads/profiles/' . $studentInfo['profilePicture'];
    if (file_exists($filePath)) {
        $profilePicture = $filePath;
    }
} elseif ($studentId) {
    // Fallback: check for any profile picture with this ID
    $possibleExtensions = ['jpg', 'jpeg', 'png', 'gif'];
    foreach ($possibleExtensions as $ext) {
        $filePath = 'uploads/profiles/profile_' . $studentId . '.' . $ext;
        if (file_exists($filePath)) {
            $profilePicture = $filePath;
            break;
        }
    }
}

// Fetch attendance records for this student using email
$attendanceRecords = [];
if ($studentInfo && !empty($studentInfo['email'])) {
    // First, try to fetch by email
    $stmt = $conn->prepare("SELECT logDate, timeIn, timeOut, status FROM attendance_log WHERE email = ? ORDER BY logDate DESC, timeIn DESC");
    $stmt->bind_param("s", $studentInfo['email']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // If no records found by email, try by user_id
    if ($result->num_rows === 0) {
        $stmt->close();
        $stmt = $conn->prepare("SELECT logDate, timeIn, timeOut, status FROM attendance_log WHERE user_id = ? ORDER BY logDate DESC, timeIn DESC");
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        $result = $stmt->get_result();
    }
    
    // If still no records, try by name matching as fallback
    if ($result->num_rows === 0) {
        $stmt->close();
        $fullName = trim($studentInfo['firstName'] . ' ' . $studentInfo['lastName']);
        $stmt = $conn->prepare("SELECT logDate, timeIn, timeOut, status FROM attendance_log WHERE studentName LIKE ? ORDER BY logDate DESC, timeIn DESC");
        $searchName = "%{$fullName}%";
        $stmt->bind_param("s", $searchName);
        $stmt->execute();
        $result = $stmt->get_result();
    }
    
    while ($row = $result->fetch_assoc()) {
        // Format times for display
        if (!empty($row['timeIn']) && $row['timeIn'] != '00:00:00') {
            $timeIn = DateTime::createFromFormat('H:i:s', $row['timeIn']);
            $row['timeIn'] = $timeIn ? $timeIn->format('h:i A') : $row['timeIn'];
        } else {
            $row['timeIn'] = 'Not recorded';
        }
        
        if (!empty($row['timeOut']) && $row['timeOut'] != '00:00:00') {
            $timeOut = DateTime::createFromFormat('H:i:s', $row['timeOut']);
            $row['timeOut'] = $timeOut ? $timeOut->format('h:i A') : $row['timeOut'];
        } else {
            $row['timeOut'] = 'Not yet';
        }
        
        // Format date
        if (!empty($row['logDate']) && $row['logDate'] != '0000-00-00') {
            $row['logDate'] = date('M d, Y', strtotime($row['logDate']));
        }
        
        $attendanceRecords[] = $row;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - SAM</title>
    <link rel="stylesheet" href="CSS/Student_dashboard.css">
    <style>
        .not-recorded {
            color: #999;
            font-style: italic;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <img src="LOGO.png" alt="SAM Logo">
        
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="topbar">
            <h3>Smart Attendance Monitoring System</h3>
            <div class="topbar-buttons">
                <!-- Theme Toggle Button -->
                <button class="theme-toggle" id="themeToggle" onclick="toggleTheme()">
                    <span id="themeIcon">🌙</span>
                </button>
                
                <?php if ($viewingAsAdmin): ?>
                    <button onclick="window.location.href='Admin_dashboard.php?view=profile'">← Back to Admin</button>
                <?php else: ?>
                    <button onclick="location.href='backend/logout.php'">Logout</button>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($studentInfo): ?>
            <!-- Profile Card -->
            <div class="profile-card">
                <div class="profile-picture-container">
                    <img src="<?= htmlspecialchars($profilePicture) ?>?v=<?= time() ?>" alt="Profile Picture" id="profile-img" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22150%22 height=%22150%22%3E%3Crect width=%22150%22 height=%22150%22 fill=%22%23ddd%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 dominant-baseline=%22middle%22 text-anchor=%22middle%22 font-family=%22Arial%22 font-size=%2220%22 fill=%22%23999%22%3ENo Image%3C/text%3E%3C/svg%3E'">
                    <?php if (!$viewingAsAdmin): ?>
                        <label for="profile-upload" class="upload-label">+</label>
                        <input type="file" id="profile-upload" accept="image/*" name="profile_picture">
                    <?php endif; ?>
                </div>
                <div>
                    <h2><?= htmlspecialchars(trim($studentInfo['firstName'] . ' ' . $studentInfo['middleName'] . ' ' . $studentInfo['lastName'])) ?></h2>
                </div>
            </div>

            <!-- Info Section -->
            <div class="info-section">
                <p><strong>ID Number:</strong> <?= htmlspecialchars($studentInfo['ID']) ?></p>
                <p><strong>User Type:</strong> <?= htmlspecialchars($studentInfo['userType']) ?></p>
                <p><strong>Phone:</strong> <?= htmlspecialchars($studentInfo['contactNumber'] ?: 'N/A') ?></p>
                <p><strong>Email:</strong> <?= htmlspecialchars($studentInfo['email'] ?: 'N/A') ?></p>
            </div>

            <!-- Attendance Section -->
            <div class="attendance-section">
                <h3>Attendance Records</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time In</th>
                            <th>Time Out</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($attendanceRecords)): ?>
                            <tr>
                                <td colspan="4" style="text-align: center;">No attendance records found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($attendanceRecords as $record): ?>
                                <tr>
                                    <td><?= htmlspecialchars($record['logDate']) ?></td>
                                    <td class="<?= ($record['timeIn'] === 'Not recorded') ? 'not-recorded' : '' ?>">
                                        <?= htmlspecialchars($record['timeIn']) ?>
                                    </td>
                                    <td class="<?= ($record['timeOut'] === 'Not yet') ? 'not-recorded' : '' ?>">
                                        <?= htmlspecialchars($record['timeOut']) ?>
                                    </td>
                                    <td><?= htmlspecialchars($record['status']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div style="text-align: center; margin-top: 50px; color: var(--text-primary);">
                <h2>Student not found</h2>
                <p>Unable to load student information. Please contact your administrator.</p>
                <?php if ($viewingAsAdmin): ?>
                    <button onclick="window.location.href='Admin_dashboard.php?view=profile'" style="margin-top: 20px; padding: 10px 20px; background: #00b2ff; border: none; border-radius: 5px; color: #fff; cursor: pointer;">← Back to Admin</button>
                <?php else: ?>
                    <p>Student ID: <?= htmlspecialchars($studentId ?? 'Not set') ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
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

     
        document.addEventListener('DOMContentLoaded', function() {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            const html = document.documentElement;
            const themeIcon = document.getElementById('themeIcon');
            
            html.setAttribute('data-theme', savedTheme);
            themeIcon.textContent = savedTheme === 'light' ? '☀️' : '🌙';
        });

        // Profile picture upload functionality (only for non-admin view)
        <?php if (!$viewingAsAdmin): ?>
        const uploadInput = document.getElementById('profile-upload');
        if (uploadInput) {
            uploadInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    // Create FormData and upload
                    const formData = new FormData();
                    formData.append('profile_picture', file);
                    
                    // Show preview immediately
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        document.getElementById('profile-img').src = e.target.result;
                    };
                    reader.readAsDataURL(file);
                    
                    // Upload to server
                    fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        if (response.ok) {
                            console.log('Profile picture uploaded successfully');
                            // Reload page to show updated image
                            setTimeout(() => location.reload(), 500);
                        }
                    })
                    .catch(error => {
                        console.error('Upload error:', error);
                        alert('Failed to upload profile picture');
                    });
                }
            });
        }
        <?php endif; ?>
    </script>
</body>
</html>