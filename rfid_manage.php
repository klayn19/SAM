<?php
session_start();
require_once 'backend/config.php';

// Check admin access
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: index.php');
    exit();
}

// Handle RFID assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_rfid'])) {
    $studentId = $_POST['student_id'];
    $rfidUid = trim(strtoupper($_POST['rfid_uid']));
    
    // Check if RFID is already assigned
    $check = $conn->prepare("SELECT ID, firstName, lastName FROM users WHERE rfid_uid = ? AND ID != ?");
    $check->bind_param("si", $rfidUid, $studentId);
    $check->execute();
    $result = $check->get_result();
    
    if ($result->num_rows > 0) {
        $existing = $result->fetch_assoc();
        $error = "RFID already assigned to " . $existing['firstName'] . " " . $existing['lastName'];
    } else {
        $stmt = $conn->prepare("UPDATE users SET rfid_uid = ? WHERE ID = ?");
        $stmt->bind_param("si", $rfidUid, $studentId);
        
        if ($stmt->execute()) {
            $success = "RFID assigned successfully!";
        } else {
            $error = "Failed to assign RFID";
        }
        $stmt->close();
    }
    $check->close();
}

// Handle RFID removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_rfid'])) {
    $studentId = $_POST['student_id'];
    $stmt = $conn->prepare("UPDATE users SET rfid_uid = NULL WHERE ID = ?");
    $stmt->bind_param("i", $studentId);
    
    if ($stmt->execute()) {
        $success = "RFID removed successfully!";
    } else {
        $error = "Failed to remove RFID";
    }
    $stmt->close();
}

// Fetch all students
$students = [];
$sql = "SELECT ID, firstName, middleName, lastName, email, rfid_uid FROM users WHERE userType = 'student' ORDER BY firstName, lastName";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $students[] = $row;
}

// Get recent RFID scans
$recentScans = [];
$scanSql = "SELECT rs.rfid_uid, rs.scan_time, rs.action_type, rs.success, rs.message, 
            u.firstName, u.lastName 
            FROM rfid_scans rs 
            LEFT JOIN users u ON rs.rfid_uid = u.rfid_uid 
            ORDER BY rs.scan_time DESC LIMIT 10";
$scanResult = $conn->query($scanSql);
if ($scanResult) {
    while ($row = $scanResult->fetch_assoc()) {
        $recentScans[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RFID Management - SAM</title>
    <link rel="stylesheet" href="CSS/admin.css">
    <style>
        .rfid-container {
            padding: 20px;
            background: var(--card-bg);
            border-radius: 10px;
            margin: 20px;
            border: 1px solid var(--border-color);
            transition: background-color 0.3s ease;
        }
        
        .rfid-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: var(--primary-gradient);
            padding: 20px;
            border-radius: 10px;
            color: white;
            text-align: center;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }
        
        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 0.9em;
            opacity: 0.9;
        }
        
        .rfid-table {
            width: 100%;
            background: var(--card-bg);
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 30px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            transition: background-color 0.3s ease;
        }

        [data-theme="light"] .rfid-table {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }
        
        .rfid-header {
            display: grid;
            grid-template-columns: 2fr 2fr 1.5fr 1.5fr;
            padding: 15px;
            background: var(--primary-gradient);
            font-weight: bold;
            color: white;
        }
        
        .rfid-row {
            display: grid;
            grid-template-columns: 2fr 2fr 1.5fr 1.5fr;
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            align-items: center;
            color: var(--text-secondary);
            transition: background-color 0.2s ease;
        }
        
        .rfid-row:hover {
            background: var(--card-hover);
        }
        
        .rfid-status {
            padding: 5px 10px;
            border-radius: 5px;
            display: inline-block;
            font-size: 0.9em;
        }
        
        .assigned {
            background: var(--success-green);
            color: white;
        }
        
        .not-assigned {
            background: var(--danger-red);
            color: white;
        }
        
        .btn-group {
            display: flex;
            gap: 5px;
        }
        
        .btn-small {
            padding: 5px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9em;
            transition: all 0.3s;
            font-weight: 600;
        }
        
        .btn-assign {
            background: var(--primary-purple);
            color: white;
        }
        
        .btn-assign:hover {
            background: var(--primary-blue);
            transform: translateY(-2px);
        }
        
        .btn-remove {
            background: var(--danger-red);
            color: white;
        }
        
        .btn-remove:hover {
            background: #d32f2f;
            transform: translateY(-2px);
        }

        .btn-scanner {
            background: var(--success-green);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1em;
            margin-bottom: 20px;
            text-decoration: none;
            display: inline-block;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-scanner:hover {
            background: #45a049;
            transform: translateY(-2px);
        }
        
        .alert {
            padding: 15px;
            margin: 20px;
            border-radius: 5px;
            font-weight: bold;
        }
        
        .alert-success {
            background: rgba(76, 175, 80, 0.2);
            color: var(--success-green);
            border: 1px solid var(--success-green);
        }
        
        .alert-error {
            background: rgba(244, 67, 54, 0.2);
            color: var(--danger-red);
            border: 1px solid var(--danger-red);
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
        }
        
        .modal-content {
            background: var(--card-bg);
            margin: 5% auto;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            color: var(--text-primary);
            border: 2px solid var(--border-color);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
            transition: background-color 0.3s ease;
        }

        .modal-content h2 {
            color: var(--text-primary);
            margin-bottom: 15px;
        }

        .modal-content p {
            color: var(--text-secondary);
            margin-bottom: 15px;
        }
        
        .modal input {
            width: 100%;
            padding: 12px;
            margin: 10px 0;
            border-radius: 5px;
            border: 2px solid var(--border-color);
            font-size: 1em;
            background: var(--card-hover);
            color: var(--text-primary);
            transition: all 0.3s ease;
        }

        .modal input:focus {
            outline: none;
            border-color: var(--primary-cyan);
            box-shadow: 0 0 10px rgba(0, 212, 255, 0.3);
        }

        .modal label {
            color: var(--text-primary);
        }

        .modal small {
            color: var(--text-muted);
        }
        
        .modal-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .modal-buttons button {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1em;
            font-weight: bold;
            transition: all 0.3s ease;
        }

        .scan-logs {
            background: var(--card-bg);
            border-radius: 10px;
            padding: 20px;
            margin-top: 30px;
            border: 1px solid var(--border-color);
            transition: background-color 0.3s ease;
        }

        .scan-logs h3 {
            color: var(--text-primary);
            margin-bottom: 15px;
        }

        .scan-log-item {
            background: var(--card-hover);
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 10px;
            display: grid;
            grid-template-columns: 2fr 1.5fr 1fr 1fr;
            gap: 10px;
            font-size: 0.9em;
            color: var(--text-secondary);
            transition: background-color 0.2s ease;
        }

        .scan-log-item.success {
            border-left: 4px solid var(--success-green);
        }

        .scan-log-item.failed {
            border-left: 4px solid var(--danger-red);
        }

        .waiting-indicator {
            display: none;
            color: var(--primary-cyan);
            font-weight: bold;
            margin: 10px 0;
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        h2 {
            color: var(--text-primary);
        }

        @media (max-width: 768px) {
            .rfid-header,
            .rfid-row {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .rfid-header > div:not(:first-child),
            .rfid-row > div:not(:first-child) {
                padding-left: 20px;
            }

            .scan-log-item {
                grid-template-columns: 1fr;
            }

            .btn-group {
                flex-direction: column;
            }

            .btn-small {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-body">
        <div class="sidebar">
            <div class="logo">
                <img src="LOGO.png" alt="SAM Logo">
            </div>
            <a href="Admin_dashboard.php">
                <i class="icon">🏠</i> Home
            </a>
            <a href="Admin_dashboard.php?view=profile">
                <i class="icon">👤</i> Profile
            </a>
            <a href="Admin_dashboard.php?view=record">
                <i class="icon">📅</i> Record
            </a>
            <a href="rfid_manage.php" class="active">
                <i class="icon">💳</i> RFID Management
            </a>
        </div>

        <div class="content-wrapper">
            <div class="navbar">
                <div class="logo">
                    <span>RFID Management System</span>
                </div>
                <div class="nav-icons">
                    <!-- Theme Toggle Button -->
                    <button class="theme-toggle" id="themeToggle" onclick="toggleTheme()">
                        <span class="icon" id="themeIcon">🌙</span>
                    </button>
                    
                    <div class="user-box">
                        <i class="icon">👤</i>
                        <span><?= htmlspecialchars($_SESSION['firstName'] ?? 'Admin') ?></span>
                    </div>
                    <form action="backend/logout.php" method="post" class="logout-form" onsubmit="return confirm('Are you sure you want to log out?');">
                        <button type="submit" class="logout-btn">🚪 Logout</button>
                    </form>
                </div>
            </div>

            <div class="main-content">
                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <div class="rfid-container">
                    <a href="rfid_scanner.php" class="btn-scanner">🔍 Open RFID Scanner</a>

                    <?php
                    $totalStudents = count($students);
                    $assignedCount = count(array_filter($students, fn($s) => !empty($s['rfid_uid'])));
                    $unassignedCount = $totalStudents - $assignedCount;
                    ?>

                    <div class="rfid-stats">
                        <div class="stat-card">
                            <div class="stat-number"><?= $totalStudents ?></div>
                            <div class="stat-label">Total Students</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?= $assignedCount ?></div>
                            <div class="stat-label">RFID Assigned</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?= $unassignedCount ?></div>
                            <div class="stat-label">Not Assigned</div>
                        </div>
                    </div>

                    <h2 style="margin-bottom: 20px;">Student RFID Cards</h2>
                    
                    <div class="rfid-table">
                        <div class="rfid-header">
                            <div>Student Name</div>
                            <div>Email</div>
                            <div>RFID Status</div>
                            <div>Actions</div>
                        </div>
                        
                        <?php foreach ($students as $student): ?>
                            <div class="rfid-row">
                                <div><?= htmlspecialchars(trim($student['firstName'] . ' ' . $student['middleName'] . ' ' . $student['lastName'])) ?></div>
                                <div><?= htmlspecialchars($student['email']) ?></div>
                                <div>
                                    <?php if (!empty($student['rfid_uid'])): ?>
                                        <span class="rfid-status assigned">
                                            ✓ <?= htmlspecialchars($student['rfid_uid']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="rfid-status not-assigned">✗ Not Assigned</span>
                                    <?php endif; ?>
                                </div>
                                <div class="btn-group">
                                    <button class="btn-small btn-assign" onclick="openAssignModal(<?= $student['ID'] ?>, '<?= htmlspecialchars($student['firstName']) ?>', '<?= htmlspecialchars($student['rfid_uid'] ?? '') ?>')">
                                        <?= empty($student['rfid_uid']) ? 'Assign' : 'Update' ?>
                                    </button>
                                    <?php if (!empty($student['rfid_uid'])): ?>
                                        <form method="post" style="display: inline;">
                                            <input type="hidden" name="student_id" value="<?= $student['ID'] ?>">
                                            <button type="submit" name="remove_rfid" class="btn-small btn-remove" onclick="return confirm('Remove RFID from this student?')">Remove</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if (!empty($recentScans)): ?>
                    <div class="scan-logs">
                        <h3>Recent RFID Scans</h3>
                        <?php foreach ($recentScans as $scan): ?>
                            <div class="scan-log-item <?= $scan['success'] ? 'success' : 'failed' ?>">
                                <div>
                                    <?php if ($scan['firstName']): ?>
                                        <?= htmlspecialchars($scan['firstName'] . ' ' . $scan['lastName']) ?>
                                    <?php else: ?>
                                        <span style="color: var(--danger-red);">Unknown</span>
                                    <?php endif; ?>
                                </div>
                                <div><?= htmlspecialchars($scan['rfid_uid']) ?></div>
                                <div><?= htmlspecialchars(ucfirst($scan['action_type'])) ?></div>
                                <div style="font-size: 0.85em; opacity: 0.8;">
                                    <?= date('M d, h:i A', strtotime($scan['scan_time'])) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Assignment Modal -->
    <div id="assignModal" class="modal">
        <div class="modal-content">
            <h2 id="modalTitle">Assign RFID Card</h2>
            <p id="modalStudent"></p>
            <form method="post">
                <input type="hidden" name="student_id" id="modalStudentId">
                <label style="display: block; margin-bottom: 5px;">RFID UID:</label>
                <input type="text" name="rfid_uid" id="modalRfidUid" placeholder="Tap RFID card on reader..." required autocomplete="off">
                <div class="waiting-indicator" id="waitingIndicator">⏳ Waiting for RFID scan...</div>
                <small style="display: block; margin-top: 5px;">
                    The RFID will be automatically captured when you scan the card
                </small>
                
                <div class="modal-buttons">
                    <button type="submit" name="assign_rfid" class="btn-assign">Assign RFID</button>
                    <button type="button" onclick="closeModal()" class="btn-remove">Cancel</button>
                </div>
            </form>
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

        let modalInputTimeout;

        function openAssignModal(studentId, studentName, currentRfid) {
            document.getElementById('assignModal').style.display = 'block';
            document.getElementById('modalStudentId').value = studentId;
            document.getElementById('modalStudent').textContent = 'Student: ' + studentName;
            document.getElementById('modalRfidUid').value = '';
            document.getElementById('modalTitle').textContent = currentRfid ? 'Update RFID Card' : 'Assign RFID Card';
            
            // Focus and show waiting indicator
            setTimeout(() => {
                const input = document.getElementById('modalRfidUid');
                input.focus();
                document.getElementById('waitingIndicator').style.display = 'block';
            }, 100);
        }
        
        function closeModal() {
            document.getElementById('assignModal').style.display = 'none';
            document.getElementById('waitingIndicator').style.display = 'none';
            if (modalInputTimeout) clearTimeout(modalInputTimeout);
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('assignModal');
            if (event.target == modal) {
                closeModal();
            }
        }
        
        // Auto-format RFID input
        const rfidInput = document.getElementById('modalRfidUid');
        rfidInput.addEventListener('input', function(e) {
            this.value = this.value.toUpperCase().trim();
            
            // Hide waiting indicator when input detected
            if (this.value.length > 0) {
                document.getElementById('waitingIndicator').style.display = 'none';
            }
            
            // Auto-submit if RFID looks complete (10+ characters)
            if (modalInputTimeout) clearTimeout(modalInputTimeout);
            
            if (this.value.length >= 8) {
                modalInputTimeout = setTimeout(() => {
                    // Optional: Auto-submit the form
                    // this.closest('form').submit();
                }, 500);
            }
        });

        // Refocus input if focus is lost while modal is open
        setInterval(() => {
            const modal = document.getElementById('assignModal');
            if (modal.style.display === 'block') {
                const input = document.getElementById('modalRfidUid');
                if (document.activeElement !== input) {
                    input.focus();
                }
            }
        }, 1000);
    </script>
</body>
</html>