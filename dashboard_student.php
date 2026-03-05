<?php
session_start();
require_once 'backend/config.php';

$viewingAsAdmin = false;
$studentId = null;

if (isset($_GET['student_id'])) {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        $viewingAsAdmin = true;
        $studentId = $_GET['student_id'];
    } else {
        header('Location: index.php'); exit();
    }
} else {
    if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
        header('Location: index.php'); exit();
    }
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        header('Location: Admin_dashboard.php'); exit();
    }
    $studentId = $_SESSION['ID'] ?? null;
}

$view = $_GET['view'] ?? 'profile';

// Handle profile picture upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_picture']) && !$viewingAsAdmin && $studentId) {
    $uploadDir = 'uploads/profiles/';
    if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);
    $file    = $_FILES['profile_picture'];
    $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
    if (in_array($fileExt, $allowed) && $file['error'] === 0) {
        foreach (glob($uploadDir . 'profile_' . $studentId . '.*') as $oldFile) unlink($oldFile);
        $fileName = 'profile_' . $studentId . '.' . $fileExt;
        if (move_uploaded_file($file['tmp_name'], $uploadDir . $fileName)) {
            $stmt = $conn->prepare("UPDATE users SET profilePicture = ? WHERE ID = ?");
            $stmt->bind_param("si", $fileName, $studentId);
            $stmt->execute(); $stmt->close();
            header('Location: ' . $_SERVER['PHP_SELF'] . '?view=profile'); exit();
        }
    }
}

// Fetch student info
$studentInfo = null;
if ($studentId) {
    $stmt = $conn->prepare("SELECT ID, firstName, middleName, lastName, email, contactNumber, userType, profilePicture FROM users WHERE ID = ?");
    $stmt->bind_param("i", $studentId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) $studentInfo = $result->fetch_assoc();
    $stmt->close();
}

// Profile picture
$profilePicture = 'default-avatar.png';
if ($studentInfo && !empty($studentInfo['profilePicture'])) {
    $fp = 'uploads/profiles/' . $studentInfo['profilePicture'];
    if (file_exists($fp)) $profilePicture = $fp;
} elseif ($studentId) {
    foreach (['jpg','jpeg','png','gif'] as $ext) {
        $fp = 'uploads/profiles/profile_' . $studentId . '.' . $ext;
        if (file_exists($fp)) { $profilePicture = $fp; break; }
    }
}

// Fetch attendance
$attendanceRecords = [];
if ($studentInfo && !empty($studentInfo['email'])) {
    $stmt = $conn->prepare("SELECT logDate, timeIn, timeOut, status FROM attendance_log WHERE email = ? ORDER BY logDate DESC, timeIn DESC");
    $stmt->bind_param("s", $studentInfo['email']);
    $stmt->execute(); $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        $stmt->close();
        $stmt = $conn->prepare("SELECT logDate, timeIn, timeOut, status FROM attendance_log WHERE user_id = ? ORDER BY logDate DESC, timeIn DESC");
        $stmt->bind_param("i", $studentId); $stmt->execute(); $result = $stmt->get_result();
    }
    if ($result->num_rows === 0) {
        $stmt->close();
        $fullName = trim($studentInfo['firstName'] . ' ' . $studentInfo['lastName']);
        $stmt = $conn->prepare("SELECT logDate, timeIn, timeOut, status FROM attendance_log WHERE studentName LIKE ? ORDER BY logDate DESC, timeIn DESC");
        $sn = "%{$fullName}%"; $stmt->bind_param("s", $sn); $stmt->execute(); $result = $stmt->get_result();
    }
    while ($row = $result->fetch_assoc()) {
        if (!empty($row['timeIn']) && $row['timeIn'] != '00:00:00') {
            $t = DateTime::createFromFormat('H:i:s', $row['timeIn']);
            $row['timeIn'] = $t ? $t->format('h:i A') : $row['timeIn'];
        } else { $row['timeIn'] = 'Not recorded'; }
        if (!empty($row['timeOut']) && $row['timeOut'] != '00:00:00') {
            $t = DateTime::createFromFormat('H:i:s', $row['timeOut']);
            $row['timeOut'] = $t ? $t->format('h:i A') : $row['timeOut'];
        } else { $row['timeOut'] = 'Not yet'; }
        if (!empty($row['logDate']) && $row['logDate'] != '0000-00-00')
            $row['logDate'] = date('M d, Y', strtotime($row['logDate']));
        $attendanceRecords[] = $row;
    }
    $stmt->close();
}

// Fetch grades
$gradesData = [];
if ($studentId) {
    $res = $conn->prepare("
        SELECT g.period, g.grade, g.remarks, g.encoded_at,
               s.code AS subject_code, s.name AS subject_name,
               u.firstName AS teacher_first, u.lastName AS teacher_last
        FROM grades g
        JOIN subjects s ON g.subject_id = s.id
        JOIN users u ON s.teacher_id = u.ID
        WHERE g.student_id = ?
        ORDER BY s.code, FIELD(g.period,'Prelim','Midterm','Pre-Final','Final')
    ");
    $res->bind_param("i", $studentId);
    $res->execute();
    $gr = $res->get_result();
    while ($row = $gr->fetch_assoc()) {
        $key = $row['subject_code'] . '||' . $row['subject_name'] . '||' . $row['teacher_first'] . ' ' . $row['teacher_last'];
        $gradesData[$key][$row['period']] = $row;
    }
    $res->close();
}

$studentName = $studentInfo ? htmlspecialchars(trim($studentInfo['firstName'] . ' ' . $studentInfo['middleName'] . ' ' . $studentInfo['lastName'])) : 'Student';
$initial  = $studentInfo ? strtoupper(substr($studentInfo['firstName'], 0, 1)) : 'S';
$periods  = ['Prelim', 'Midterm', 'Pre-Final', 'Final'];
$adminParam = $viewingAsAdmin ? '&student_id=' . $studentId : '';
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student Dashboard — SAM</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
:root{--bg:#0d0f14;--surface:#151820;--surface2:#1c2030;--border:rgba(255,255,255,.07);--accent:#00b2ff;--accent2:#7b5cff;--danger:#ff4d6d;--success:#36d68a;--warning:#f5a623;--text:#e8ecf5;--muted:#6b7591;--radius:12px;--font-head:'Syne',sans-serif;--font-body:'DM Sans',sans-serif;--grad:linear-gradient(135deg,var(--accent),var(--accent2))}
[data-theme="light"]{--bg:#f0f2f8;--surface:#ffffff;--surface2:#e8ecf5;--border:rgba(0,0,0,.08);--text:#1a1d2e;--muted:#7a84a0}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:var(--font-body);background:var(--bg);color:var(--text);min-height:100vh;display:flex;transition:background .3s,color .3s}
.sidebar{width:240px;min-height:100vh;background:var(--surface);border-right:1px solid var(--border);display:flex;flex-direction:column;padding:28px 0;position:fixed;top:0;left:0;bottom:0;z-index:100}
.sidebar-logo{display:flex;align-items:center;gap:10px;padding:0 24px 28px;border-bottom:1px solid var(--border)}
.sidebar-logo img{width:38px;height:38px;border-radius:8px;object-fit:contain}
.sidebar-logo span{font-family:var(--font-head);font-size:18px;font-weight:800;background:var(--grad);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.sidebar-label{font-family:var(--font-head);font-size:10px;font-weight:700;letter-spacing:.12em;color:var(--muted);padding:22px 24px 8px;text-transform:uppercase}
.nav-item{display:flex;align-items:center;gap:12px;padding:12px 24px;cursor:pointer;color:var(--muted);font-size:14px;font-weight:500;border-left:3px solid transparent;transition:all .2s;text-decoration:none}
.nav-item:hover{color:var(--text);background:var(--surface2)}
.nav-item.active{color:var(--accent);border-left-color:var(--accent);background:rgba(0,178,255,.07)}
.nav-item .icon{font-size:18px;width:22px;text-align:center}
.sidebar-bottom{margin-top:auto;padding:20px 24px;border-top:1px solid var(--border)}
.student-chip{display:flex;align-items:center;gap:10px}
.avatar{width:36px;height:36px;background:var(--grad);border-radius:50%;display:flex;align-items:center;justify-content:center;font-family:var(--font-head);font-weight:800;font-size:14px;color:#fff;flex-shrink:0}
.student-info{flex:1;overflow:hidden}
.student-info .name{font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.student-info .role{font-size:11px;color:var(--muted)}
.main-content{margin-left:240px;flex:1;display:flex;flex-direction:column;min-height:100vh}
.topbar{background:var(--surface);border-bottom:1px solid var(--border);height:60px;display:flex;align-items:center;justify-content:space-between;padding:0 32px;position:sticky;top:0;z-index:90}
.topbar-title{font-family:var(--font-head);font-size:15px;font-weight:700;color:var(--muted)}
.topbar-buttons{display:flex;align-items:center;gap:12px}
.theme-toggle{width:36px;height:36px;background:var(--surface2);border:1px solid var(--border);border-radius:8px;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:16px;transition:background .2s;color:var(--text);padding:0}
.theme-toggle:hover{background:var(--border)}
.topbar button{height:36px;padding:0 16px;background:var(--surface2);border:1px solid var(--border);border-radius:8px;color:var(--text);font-family:var(--font-body);font-size:13px;font-weight:500;cursor:pointer;transition:background .2s;display:inline-flex;align-items:center;gap:6px}
.topbar button:hover{background:var(--border)}
.topbar button[onclick*="logout"],.topbar button[onclick*="Admin"]{background:rgba(255,77,109,.12);border-color:rgba(255,77,109,.25);color:var(--danger)}
.topbar button[onclick*="logout"]:hover,.topbar button[onclick*="Admin"]:hover{background:rgba(255,77,109,.22)}
.content{padding:32px;flex:1}
.page-header{margin-bottom:28px}
.page-header h1{font-family:var(--font-head);font-size:26px;font-weight:800}
.page-header p{color:var(--muted);font-size:13px;margin-top:4px}
.stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px}
.stat-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:20px 22px;position:relative;overflow:hidden}
.stat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px}
.stat-card.blue::before{background:var(--accent)}
.stat-card.green::before{background:var(--success)}
.stat-card.yellow::before{background:var(--warning)}
.stat-card.red::before{background:var(--danger)}
.stat-card.purple::before{background:var(--accent2)}
.stat-value{font-family:var(--font-head);font-size:28px;font-weight:800}
.stat-label{font-size:12px;color:var(--muted);margin-top:4px}
.card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden}
.card-head{padding:18px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between}
.card-head h2{font-family:var(--font-head);font-size:14px;font-weight:700}
.card-body{padding:24px}
.table-wrap{overflow-x:auto}
.data-table{width:100%;border-collapse:collapse}
.data-table th{font-family:var(--font-head);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);padding:10px 14px;border-bottom:1px solid var(--border);text-align:left;background:var(--surface2)}
.data-table td{padding:11px 14px;font-size:13px;border-bottom:1px solid var(--border);vertical-align:middle}
.data-table tbody tr:hover{background:rgba(0,178,255,.04)}
.data-table tbody tr:last-child td{border-bottom:none}
.empty-row td{text-align:center;color:var(--muted);padding:40px;font-style:italic}
.profile-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:24px;margin-bottom:20px;display:flex;align-items:center;gap:24px;position:relative;overflow:hidden}
.profile-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:var(--grad)}
.profile-card h2{font-family:var(--font-head);font-size:20px;font-weight:800}
.profile-picture-container{position:relative;width:80px;height:80px;flex-shrink:0}
.profile-picture-container img{width:80px;height:80px;border-radius:50%;object-fit:cover;background:var(--surface2);border:2px solid var(--border);display:block}
.upload-label{position:absolute;bottom:0;right:0;width:26px;height:26px;background:var(--grad);border:2px solid var(--surface);border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:12px;transition:transform .2s;color:#fff}
.upload-label:hover{transform:scale(1.1)}
#profile-upload{display:none}
.info-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;margin-bottom:20px}
.info-card-head{padding:14px 24px;border-bottom:1px solid var(--border);background:var(--surface2)}
.info-card-head h2{font-family:var(--font-head);font-size:13px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.06em}
.info-grid{display:grid;grid-template-columns:1fr 1fr}
.info-item{display:flex;flex-direction:column;gap:4px;padding:16px 24px;border-bottom:1px solid var(--border)}
.info-item:nth-child(odd){border-right:1px solid var(--border)}
.info-item:nth-last-child(-n+2){border-bottom:none}
.info-label{font-family:var(--font-head);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--muted)}
.info-value{font-size:14px;color:var(--text)}
.subject-code{font-family:var(--font-head);font-size:11px;font-weight:700;color:var(--accent);background:rgba(0,178,255,.1);padding:2px 8px;border-radius:4px}
.period-badge{font-size:11px;font-weight:700;padding:3px 9px;border-radius:5px;background:rgba(123,92,255,.15);color:var(--accent2)}
.grade-pill{display:inline-block;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:700}
.grade-pass{background:rgba(54,214,138,.15);color:var(--success)}
.grade-fail{background:rgba(255,77,109,.15);color:var(--danger)}
.status-pill{display:inline-block;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:700}
.status-present{background:rgba(54,214,138,.15);color:var(--success)}
.status-late{background:rgba(245,166,35,.15);color:var(--warning)}
.status-absent{background:rgba(255,77,109,.15);color:var(--danger)}
.not-recorded{color:var(--muted);font-style:italic;font-size:12px}
@media(max-width:1024px){.stats-row{grid-template-columns:repeat(2,1fr)}}
@media(max-width:900px){.info-grid{grid-template-columns:1fr}.info-item:nth-child(odd){border-right:none}.info-item:nth-last-child(-n+2){border-bottom:1px solid var(--border)}.info-item:last-child{border-bottom:none}}
@media(max-width:768px){.sidebar{width:60px;padding:16px 0}.sidebar-logo span,.sidebar-label,.nav-item span:not(.icon),.sidebar-bottom .student-info{display:none}.sidebar-logo{padding:0 11px 16px;justify-content:center}.sidebar-logo img{width:32px;height:32px}.nav-item{padding:12px;justify-content:center}.sidebar-bottom{padding:12px}.student-chip{justify-content:center}.main-content{margin-left:60px}.profile-card{flex-direction:column;text-align:center}.topbar{padding:0 16px}.topbar-title{font-size:12px}.content{padding:16px}.stats-row{grid-template-columns:repeat(2,1fr)}}
@media(max-width:480px){.profile-card h2{font-size:16px}.profile-picture-container,.profile-picture-container img{width:64px;height:64px}.data-table th,.data-table td{padding:8px 10px;font-size:12px}.stats-row{grid-template-columns:1fr 1fr;gap:10px}}
</style>
</head>
<body>

<!-- SIDEBAR -->
<nav class="sidebar">
    <div class="sidebar-logo">
        <img src="LOGO.png" alt="SAM" onerror="this.style.display='none'">
        <span>SAM</span>
    </div>
    <div class="sidebar-label">Menu</div>
    <a href="?view=profile<?= $adminParam ?>"    class="nav-item <?= $view==='profile'    ? 'active':'' ?>"><span class="icon">👤</span> Profile</a>
    <a href="?view=attendance<?= $adminParam ?>"  class="nav-item <?= $view==='attendance' ? 'active':'' ?>"><span class="icon">📅</span> Attendance</a>
    <a href="?view=grades<?= $adminParam ?>"      class="nav-item <?= $view==='grades'     ? 'active':'' ?>"><span class="icon">📊</span> Grades</a>
    <div class="sidebar-bottom">
        <div class="student-chip">
            <div class="avatar"><?= $initial ?></div>
            <div class="student-info">
                <div class="name"><?= $studentName ?></div>
                <div class="role"><?= $viewingAsAdmin ? 'Admin View' : 'Student' ?></div>
            </div>
        </div>
    </div>
</nav>

<!-- MAIN -->
<div class="main-content">
    <div class="topbar">
        <span class="topbar-title">Smart Attendance Monitoring System</span>
        <div class="topbar-buttons">
            <button class="theme-toggle" id="themeToggle" onclick="toggleTheme()" title="Toggle theme">
                <span id="themeIcon">🌙</span>
            </button>
            <?php if ($viewingAsAdmin): ?>
                <button onclick="window.location.href='Admin_dashboard.php?view=profile'">← Back to Admin</button>
            <?php else: ?>
                <button onclick="location.href='backend/logout.php'">🚪 Logout</button>
            <?php endif; ?>
        </div>
    </div>

    <div class="content">
    <?php if ($studentInfo): ?>

        <!-- ══ PROFILE ══ -->
        <?php if ($view === 'profile'): ?>
        <div class="page-header">
            <h1>My Profile</h1>
            <p>Your personal information and account details.</p>
        </div>

        <div class="profile-card">
            <div class="profile-picture-container">
                <img src="<?= htmlspecialchars($profilePicture) ?>?v=<?= time() ?>" alt="Profile" id="profile-img"
                     onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22100%22 height=%22100%22%3E%3Crect width=%22100%22 height=%22100%22 fill=%22%231c2030%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 dominant-baseline=%22middle%22 text-anchor=%22middle%22 font-size=%2232%22 fill=%22%236b7591%22%3E<?= $initial ?>%3C/text%3E%3C/svg%3E'">
                <?php if (!$viewingAsAdmin): ?>
                    <label for="profile-upload" class="upload-label" title="Change photo">+</label>
                    <input type="file" id="profile-upload" accept="image/*" name="profile_picture">
                <?php endif; ?>
            </div>
            <div>
                <h2><?= $studentName ?></h2>
                <p style="color:var(--muted);font-size:13px;margin-top:4px;"><?= htmlspecialchars($studentInfo['userType']) ?></p>
            </div>
        </div>

        <div class="info-card">
            <div class="info-card-head"><h2>Account Information</h2></div>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">🆔 ID Number</span>
                    <span class="info-value"><?= htmlspecialchars($studentInfo['ID']) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">📚 User Type</span>
                    <span class="info-value"><?= htmlspecialchars($studentInfo['userType']) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">📞 Phone</span>
                    <span class="info-value"><?= htmlspecialchars($studentInfo['contactNumber'] ?: 'N/A') ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">📧 Email</span>
                    <span class="info-value"><?= htmlspecialchars($studentInfo['email'] ?: 'N/A') ?></span>
                </div>
            </div>
        </div>

        <!-- ══ ATTENDANCE ══ -->
        <?php elseif ($view === 'attendance'): ?>
        <div class="page-header">
            <h1>Attendance Records</h1>
            <p>Your complete attendance history.</p>
        </div>
        <?php
        $present = count(array_filter($attendanceRecords, fn($r) => stripos($r['status'],'present') !== false));
        $late    = count(array_filter($attendanceRecords, fn($r) => stripos($r['status'],'late')    !== false));
        $absent  = count(array_filter($attendanceRecords, fn($r) => stripos($r['status'],'absent')  !== false));
        ?>
        <div class="stats-row">
            <div class="stat-card blue">  <div class="stat-value"><?= count($attendanceRecords) ?></div><div class="stat-label">Total Records</div></div>
            <div class="stat-card green"> <div class="stat-value"><?= $present ?></div><div class="stat-label">Present</div></div>
            <div class="stat-card yellow"><div class="stat-value"><?= $late ?></div><div class="stat-label">Late</div></div>
            <div class="stat-card red">   <div class="stat-value"><?= $absent ?></div><div class="stat-label">Absent</div></div>
        </div>
        <div class="card">
            <div class="card-head"><h2>📅 Attendance Log</h2></div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Date</th><th>Time In</th><th>Time Out</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php if (empty($attendanceRecords)): ?>
                            <tr class="empty-row"><td colspan="4">No attendance records found.</td></tr>
                        <?php else: foreach ($attendanceRecords as $r): ?>
                            <tr>
                                <td><?= htmlspecialchars($r['logDate']) ?></td>
                                <td class="<?= $r['timeIn']==='Not recorded'?'not-recorded':'' ?>"><?= htmlspecialchars($r['timeIn']) ?></td>
                                <td class="<?= $r['timeOut']==='Not yet'?'not-recorded':'' ?>"><?= htmlspecialchars($r['timeOut']) ?></td>
                                <td><?php
                                    $s = strtolower($r['status']);
                                    $cls = str_contains($s,'present')?'status-present':(str_contains($s,'late')?'status-late':'status-absent');
                                ?><span class="status-pill <?= $cls ?>"><?= htmlspecialchars($r['status']) ?></span></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ══ GRADES ══ -->
        <?php elseif ($view === 'grades'): ?>
        <div class="page-header">
            <h1>My Grades</h1>
            <p>Academic grades per subject and grading period.</p>
        </div>

        <?php if (empty($gradesData)): ?>
            <div class="card">
                <div class="card-body empty-row" style="padding:60px;">No grades have been recorded yet.</div>
            </div>
        <?php else:
            $allGrades = [];
            foreach ($gradesData as $rows) foreach ($rows as $r) $allGrades[] = (float)$r['grade'];
            $avg    = count($allGrades) ? array_sum($allGrades)/count($allGrades) : 0;
            $passed = count(array_filter($allGrades, fn($g) => $g >= 75));
            $failed = count($allGrades) - $passed;
        ?>
        <div class="stats-row">
            <div class="stat-card blue">  <div class="stat-value"><?= count($allGrades) ?></div><div class="stat-label">Total Grades</div></div>
            <div class="stat-card green"> <div class="stat-value"><?= $passed ?></div><div class="stat-label">Passed</div></div>
            <div class="stat-card red">   <div class="stat-value"><?= $failed ?></div><div class="stat-label">Failed</div></div>
            <div class="stat-card purple"><div class="stat-value"><?= number_format($avg,2) ?></div><div class="stat-label">Overall Average</div></div>
        </div>

        <?php foreach ($gradesData as $key => $periodRows):
            [$subCode, $subName, $teacherName] = explode('||', $key);
            $sg = array_column($periodRows,'grade');
            $subAvg = count($sg) ? array_sum($sg)/count($sg) : null;
        ?>
        <div class="card" style="margin-bottom:20px;">
            <div class="card-head">
                <div style="display:flex;align-items:center;gap:10px;">
                    <span class="subject-code"><?= htmlspecialchars($subCode) ?></span>
                    <h2><?= htmlspecialchars($subName) ?></h2>
                </div>
                <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                    <span style="font-size:12px;color:var(--muted);">👨‍🏫 <?= htmlspecialchars($teacherName) ?></span>
                    <?php if ($subAvg !== null): ?>
                        <span class="grade-pill <?= $subAvg>=75?'grade-pass':'grade-fail' ?>">Avg: <?= number_format($subAvg,2) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Period</th><th>Grade</th><th>Remarks</th><th>Date Encoded</th></tr></thead>
                    <tbody>
                        <?php foreach ($periods as $period): ?>
                        <?php if (isset($periodRows[$period])): $r = $periodRows[$period]; ?>
                        <tr>
                            <td><span class="period-badge"><?= $period ?></span></td>
                            <td><strong style="font-size:15px;"><?= number_format($r['grade'],2) ?></strong></td>
                            <td><span class="grade-pill <?= $r['remarks']==='Passed'?'grade-pass':'grade-fail' ?>"><?= htmlspecialchars($r['remarks']) ?></span></td>
                            <td style="color:var(--muted);font-size:12px;"><?= date('M d, Y', strtotime($r['encoded_at'])) ?></td>
                        </tr>
                        <?php else: ?>
                        <tr>
                            <td><span class="period-badge"><?= $period ?></span></td>
                            <td colspan="3" class="not-recorded">Not yet encoded</td>
                        </tr>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

    <?php endif; // end view ?>

    <?php else: ?>
        <div style="text-align:center;margin-top:80px;">
            <h2>Student not found</h2>
            <p style="color:var(--muted);margin-top:8px;">Unable to load student information.</p>
            <?php if ($viewingAsAdmin): ?>
                <button onclick="window.location.href='Admin_dashboard.php?view=profile'"
                    style="margin-top:20px;padding:10px 20px;background:linear-gradient(135deg,#00b2ff,#7b5cff);border:none;border-radius:8px;color:#fff;cursor:pointer;font-family:inherit;">
                    ← Back to Admin
                </button>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    </div><!-- .content -->
</div><!-- .main-content -->

<script>
function toggleTheme() {
    const html = document.documentElement;
    const icon = document.getElementById('themeIcon');
    const next = html.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
    html.setAttribute('data-theme', next);
    icon.textContent = next === 'dark' ? '🌙' : '☀️';
    try { localStorage.setItem('theme', next); } catch(e) {}
}
document.addEventListener('DOMContentLoaded', function() {
    const saved = localStorage.getItem('theme') || 'dark';
    document.documentElement.setAttribute('data-theme', saved);
    const icon = document.getElementById('themeIcon');
    if (icon) icon.textContent = saved === 'light' ? '☀️' : '🌙';
});
<?php if (!$viewingAsAdmin): ?>
const uploadInput = document.getElementById('profile-upload');
if (uploadInput) {
    uploadInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;
        const formData = new FormData();
        formData.append('profile_picture', file);
        const reader = new FileReader();
        reader.onload = e => document.getElementById('profile-img').src = e.target.result;
        reader.readAsDataURL(file);
        fetch(window.location.href, { method: 'POST', body: formData })
            .then(r => { if (r.ok) setTimeout(() => location.reload(), 500); })
            .catch(() => alert('Failed to upload profile picture'));
    });
}
<?php endif; ?>
</script>
</body>
</html>