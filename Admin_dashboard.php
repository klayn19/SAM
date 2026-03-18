<?php
session_start();
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || ($_SESSION['role'] ?? '') !== 'admin') {
    if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']) {
        header('Location: dashboard_student.php');
    } else {
        header('Location: index.php');
    }
    exit();
}

require_once 'backend/config.php';

$view              = $_GET['view']       ?? 'home';
$selectedStudentId = $_GET['student_id'] ?? null;
$adminName         = $_SESSION['firstName'] ?? 'Admin';

// ── Fetch today's attendance for home view ────────────────────────────────────
$attendanceData = [];
$sql = "SELECT a.studentName, a.email, a.timeIn, a.timeOut, a.logDate, a.status,
               u.ID as studentID, u.firstName, u.lastName
        FROM attendance_log a
        LEFT JOIN users u ON a.email = u.email
        WHERE (u.userType = 'student' OR u.userType IS NULL)
        ORDER BY a.logDate DESC, a.timeIn DESC";

function fmtTime($t) {
    if (empty($t) || $t === '00:00:00') return 'Not recorded';
    $obj = DateTime::createFromFormat('H:i:s', $t);
    return $obj ? $obj->format('h:i A') : date('h:i A', strtotime($t));
}
function fmtDate($d) {
    if (empty($d) || $d === '0000-00-00') return 'Unknown';
    $obj = DateTime::createFromFormat('Y-m-d', $d);
    return $obj ? $obj->format('M d, Y') : date('M d, Y', strtotime($d));
}

if ($result = $conn->query($sql)) {
    while ($row = $result->fetch_assoc()) {
        $row['timeIn']  = fmtTime($row['timeIn']);
        $row['timeOut'] = fmtTime($row['timeOut']);
        $row['logDate'] = fmtDate($row['logDate']);
        $attendanceData[] = $row;
    }
}

// ── Stats for home ────────────────────────────────────────────────────────────
$totalStudents = 0;
$r = $conn->query("SELECT COUNT(*) as c FROM users WHERE userType='student'");
if ($r) $totalStudents = $r->fetch_assoc()['c'];

$totalToday = 0;
$today = date('Y-m-d');
$r2 = $conn->query("SELECT COUNT(*) as c FROM attendance_log WHERE logDate='$today'");
if ($r2) $totalToday = $r2->fetch_assoc()['c'];

$totalRecords = count($attendanceData);
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard — SAM</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="./css/admin.css">
</head>
<body>

<nav class="sidebar no-print">
    <div class="sidebar-logo">
        <img src="LOGO.png" alt="SAM" onerror="this.src='Logo2.png'" onerror="this.style.display='none'">
        <span>SAM</span>
    </div>
    <div class="sidebar-label">Menu</div>
    <a href="?view=home"   class="nav-item <?= $view==='home'   ?'active':'' ?>"><span class="icon">🏠</span> Home</a>
    <a href="?view=profile" class="nav-item <?= $view==='profile'?'active':'' ?>"><span class="icon">👤</span> Profile</a>
    <a href="?view=record"  class="nav-item <?= ($view==='record' && !$selectedStudentId)?'active':'' ?>"><span class="icon">📅</span> Record</a>
    <a href="rfid_manage.php"       class="nav-item"><span class="icon">💳</span> RFID Management</a>
    <a href="Attendance_manual.php" class="nav-item"><span class="icon">📝</span> Attendance Manual</a>
    <div class="sidebar-bottom">
        <div class="admin-chip">
            <div class="avatar"><?= strtoupper(substr($adminName,0,1)) ?></div>
            <div class="admin-info">
                <div class="name"><?= htmlspecialchars($adminName) ?></div>
                <div class="role">Administrator</div>
            </div>
        </div>
    </div>
</nav>

<!-- ═══ MAIN ═══ -->
<div class="main">
    <div class="topbar no-print">
        <span class="topbar-title">Smart Attendance Monitoring System</span>
        <div class="topbar-actions">
            <button class="btn-icon" onclick="toggleTheme()" id="themeToggle" title="Toggle theme">🌙</button>
            <div class="btn-user"><span class="icon">👤</span><span><?= htmlspecialchars($adminName) ?></span></div>
            <form action="backend/logout.php" method="post" style="margin:0;" onsubmit="return confirm('Log out?')">
                <button type="submit" class="btn-logout">🚪 Logout</button>
            </form>
        </div>
    </div>

    <div class="content">

    <!-- ══ HOME ══ -->
    <?php if ($view === 'home'): ?>
    <div class="page-header">
        <h1>Dashboard</h1>
        <p>Welcome back, <?= htmlspecialchars($adminName) ?>. Here's today's overview.</p>
    </div>

    <div class="stats-row">
        <div class="stat-card blue">
            <div class="stat-value"><?= $totalStudents ?></div>
            <div class="stat-label">Total Students</div>
        </div>
        <div class="stat-card green">
            <div class="stat-value"><?= $totalToday ?></div>
            <div class="stat-label">Logged Today</div>
        </div>
        <div class="stat-card purple">
            <div class="stat-value"><?= $totalRecords ?></div>
            <div class="stat-label">Total Records</div>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <h2>📋 Recent Attendance Log</h2>
            <span style="font-size:12px;color:var(--muted);"><?= count($attendanceData) ?> records</span>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Student Name</th>
                        <th>Date</th>
                        <th>Time In</th>
                        <th>Time Out</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($attendanceData)): ?>
                        <tr class="empty-row"><td colspan="5">No attendance records found.</td></tr>
                    <?php else: foreach (array_slice($attendanceData,0,50) as $row): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($row['studentName']) ?></strong></td>
                            <td style="color:var(--muted);font-size:12px;"><?= htmlspecialchars($row['logDate']) ?></td>
                            <td class="<?= $row['timeIn']==='Not recorded'?'not-recorded':'' ?>"><?= htmlspecialchars($row['timeIn']) ?></td>
                            <td class="<?= $row['timeOut']==='Not recorded'?'not-recorded':'' ?>"><?= htmlspecialchars($row['timeOut']) ?></td>
                            <td><?php
                                $s = strtolower($row['status'] ?? '');
                                $cls = str_contains($s,'present')?'status-present':(str_contains($s,'late')?'status-late':'status-absent');
                            ?><span class="status-pill <?= $cls ?>"><?= htmlspecialchars($row['status'] ?? '—') ?></span></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ══ PROFILE ══ -->
    <?php elseif ($view === 'profile'): ?>
    <?php
    $studentRecords = [];
    $r = $conn->query("SELECT ID, firstName, middleName, lastName, userType FROM users WHERE userType='student' ORDER BY firstName,lastName");
    if ($r) while ($row = $r->fetch_assoc()) $studentRecords[] = $row;
    ?>
    <div class="page-header">
        <h1>Student Profiles</h1>
        <p>Click a student row to view their full profile.</p>
    </div>
    <div class="card">
        <div class="card-head">
            <h2>👤 All Students</h2>
            <span style="font-size:12px;color:var(--muted);"><?= count($studentRecords) ?> students</span>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>#</th><th>Student Name</th><th>ID Number</th><th>Type</th></tr></thead>
                <tbody>
                    <?php if (empty($studentRecords)): ?>
                        <tr class="empty-row"><td colspan="4">No students found.</td></tr>
                    <?php else: foreach ($studentRecords as $i => $s): ?>
                        <tr class="clickable" onclick="viewStudentProfile('<?= htmlspecialchars($s['ID']) ?>')" title="View profile">
                            <td style="color:var(--muted);"><?= $i+1 ?></td>
                            <td><strong><?= htmlspecialchars(trim($s['firstName'].' '.$s['middleName'].' '.$s['lastName'])) ?></strong></td>
                            <td><?= htmlspecialchars($s['ID']) ?></td>
                            <td><span class="status-pill status-present"><?= htmlspecialchars($s['userType']) ?></span></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ══ RECORD — STUDENT DETAIL ══ -->
    <?php elseif ($view === 'record' && $selectedStudentId): ?>
    <?php
    $studentInfo = null;
    $stmt = $conn->prepare("SELECT ID, firstName, middleName, lastName, email FROM users WHERE ID=? AND userType='student'");
    $stmt->bind_param('i', $selectedStudentId);
    $stmt->execute();
    $r = $stmt->get_result();
    if ($r->num_rows > 0) $studentInfo = $r->fetch_assoc();
    $stmt->close();

    $studentAttendance = [];
    $daysPresent = $daysAbsent = $daysLate = 0;

    if ($studentInfo) {
        $stmt2 = $conn->prepare("SELECT logDate, timeIn, timeOut, status FROM attendance_log WHERE email=? ORDER BY logDate DESC");
        $stmt2->bind_param('s', $studentInfo['email']);
        $stmt2->execute();
        $r2 = $stmt2->get_result();
        while ($row = $r2->fetch_assoc()) {
            $s = strtolower($row['status'] ?? '');
            if (str_contains($s,'present')) $daysPresent++;
            elseif (str_contains($s,'absent')) $daysAbsent++;
            elseif (str_contains($s,'late'))   $daysLate++;
            $row['timeIn']  = fmtTime($row['timeIn']);
            $row['timeOut'] = fmtTime($row['timeOut']);
            $row['logDate'] = fmtDate($row['logDate']);
            $studentAttendance[] = $row;
        }
        $stmt2->close();
    }
    $totalDays = $daysPresent + $daysAbsent + $daysLate;
    $rate = $totalDays > 0 ? round(($daysPresent / $totalDays) * 100, 1) : 0;
    ?>

    <div class="back-row no-print">
        <button class="btn-back" onclick="window.location.href='?view=record'">← Back to Students</button>
        <button class="btn-print" onclick="window.print()">🖨️ Print Record</button>
    </div>

    <?php if ($studentInfo): ?>
    <div class="detail-header-card">
        <h2><?= htmlspecialchars($studentInfo['firstName'].' '.$studentInfo['middleName'].' '.$studentInfo['lastName']) ?></h2>
        <p>Student ID: <?= htmlspecialchars($studentInfo['ID']) ?> &nbsp;|&nbsp; Email: <?= htmlspecialchars($studentInfo['email']) ?></p>
    </div>

    <div class="stats-row">
        <div class="stat-card green">
            <div class="stat-value"><?= $daysPresent ?></div>
            <div class="stat-label">Days Present</div>
        </div>
        <div class="stat-card yellow">
            <div class="stat-value"><?= $daysLate ?></div>
            <div class="stat-label">Days Late</div>
        </div>
        <div class="stat-card blue" style="--c:var(--danger)">
            <div class="stat-value"><?= $daysAbsent ?></div>
            <div class="stat-label">Days Absent</div>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <h2>📅 Attendance Records</h2>
            <span style="font-size:12px;color:var(--muted);"><?= count($studentAttendance) ?> records · Attendance Rate: <strong style="color:var(--success)"><?= $rate ?>%</strong></span>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Date</th><th>Time In</th><th>Time Out</th><th>Status</th></tr></thead>
                <tbody>
                    <?php if (empty($studentAttendance)): ?>
                        <tr class="empty-row"><td colspan="4">No attendance records found.</td></tr>
                    <?php else: foreach ($studentAttendance as $rec): ?>
                        <tr>
                            <td><?= htmlspecialchars($rec['logDate']) ?></td>
                            <td class="<?= $rec['timeIn']==='Not recorded'?'not-recorded':'' ?>"><?= htmlspecialchars($rec['timeIn']) ?></td>
                            <td class="<?= $rec['timeOut']==='Not recorded'?'not-recorded':'' ?>"><?= htmlspecialchars($rec['timeOut']) ?></td>
                            <td><?php
                                $s = strtolower($rec['status'] ?? '');
                                $cls = str_contains($s,'present')?'status-present':(str_contains($s,'late')?'status-late':'status-absent');
                            ?><span class="status-pill <?= $cls ?>"><?= htmlspecialchars($rec['status']) ?></span></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php else: ?>
    <div class="card"><div class="card-body" style="text-align:center;padding:60px;color:var(--muted);">Student not found.</div></div>
    <?php endif; ?>

    <!-- ══ RECORD — STUDENT LIST ══ -->
    <?php elseif ($view === 'record'): ?>
    <?php
    $studentRecords = [];
    $r = $conn->query("SELECT ID, firstName, middleName, lastName, email, rfid_uid FROM users WHERE userType='student' ORDER BY firstName,lastName");
    if ($r) while ($row = $r->fetch_assoc()) $studentRecords[] = $row;
    ?>
    <div class="page-header">
        <h1>Student Records</h1>
        <p>Click a student to view their attendance history.</p>
    </div>
    <div class="card">
        <div class="card-head">
            <h2>📅 All Students</h2>
            <span style="font-size:12px;color:var(--muted);"><?= count($studentRecords) ?> students</span>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>#</th><th>Student Name</th><th>Email</th><th>RFID UID</th></tr></thead>
                <tbody>
                    <?php if (empty($studentRecords)): ?>
                        <tr class="empty-row"><td colspan="4">No students found.</td></tr>
                    <?php else: foreach ($studentRecords as $i => $s): ?>
                        <tr class="clickable" onclick="viewStudentRecord('<?= htmlspecialchars($s['ID']) ?>')" title="View attendance">
                            <td style="color:var(--muted);"><?= $i+1 ?></td>
                            <td><strong><?= htmlspecialchars(trim($s['firstName'].' '.$s['middleName'].' '.$s['lastName'])) ?></strong></td>
                            <td style="font-size:12px;color:var(--muted);"><?= htmlspecialchars($s['email']) ?></td>
                            <td><?php if (!empty($s['rfid_uid'])): ?>
                                <span class="rfid-yes">✓ <?= htmlspecialchars($s['rfid_uid']) ?></span>
                            <?php else: ?>
                                <span class="rfid-no">✗ Not Assigned</span>
                            <?php endif; ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php endif; ?>
    </div><!-- .content -->
</div><!-- .main -->

<script>
function toggleTheme() {
    const html = document.documentElement;
    const btn  = document.getElementById('themeToggle');
    const next = html.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
    html.setAttribute('data-theme', next);
    btn.textContent = next === 'dark' ? '🌙' : '☀️';
    try { localStorage.setItem('theme', next); } catch(e) {}
}
document.addEventListener('DOMContentLoaded', function() {
    const saved = localStorage.getItem('theme') || 'dark';
    document.documentElement.setAttribute('data-theme', saved);
    const btn = document.getElementById('themeToggle');
    if (btn) btn.textContent = saved === 'light' ? '☀️' : '🌙';
});
function viewStudentProfile(id) {
    window.location.href = 'dashboard_student.php?student_id=' + encodeURIComponent(id);
}
function viewStudentRecord(id) {
    window.location.href = '?view=record&student_id=' + encodeURIComponent(id);
}
</script>
</body>
</html>