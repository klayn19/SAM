<?php
session_start();
require_once 'backend/config.php';

// Auth check — only teachers allowed
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header('Location: index.php');
    exit();
}
if (($_SESSION['role'] ?? '') !== 'teacher') {
    if ($_SESSION['role'] === 'admin') {
        header('Location: Admin_dashboard.php');
    } else {
        header('Location: dashboard_student.php');
    }
    exit();
}

$teacherId   = $_SESSION['ID']        ?? null;
$teacherName = $_SESSION['firstName'] ?? 'Teacher';

// ── Ensure tables exist ──────────────────────────────────────────────────────
$conn->query("
    CREATE TABLE IF NOT EXISTS subjects (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        code        VARCHAR(20)  NOT NULL,
        name        VARCHAR(100) NOT NULL,
        teacher_id  INT          NOT NULL,
        created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

$conn->query("
    CREATE TABLE IF NOT EXISTS grades (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        student_id  INT          NOT NULL,
        subject_id  INT          NOT NULL,
        period      ENUM('Prelim','Midterm','Pre-Final','Final') NOT NULL,
        grade       DECIMAL(5,2) NOT NULL,
        remarks     VARCHAR(50)  DEFAULT NULL,
        encoded_by  INT          NOT NULL,
        encoded_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_grade (student_id, subject_id, period)
    )
");

// ── Handle POST actions ──────────────────────────────────────────────────────
$successMsg = '';
$errorMsg   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'save_grade') {
        $studentId = (int)($_POST['student_id'] ?? 0);
        $subjectId = (int)($_POST['subject_id'] ?? 0);
        $period    = $_POST['period'] ?? '';
        $grade     = (float)($_POST['grade'] ?? 0);
        $validPeriods = ['Prelim', 'Midterm', 'Pre-Final', 'Final'];

        if ($studentId && $subjectId && in_array($period, $validPeriods) && $grade >= 0 && $grade <= 100) {
            $chk = $conn->prepare("SELECT id FROM subjects WHERE id = ? AND teacher_id = ?");
            $chk->bind_param('ii', $subjectId, $teacherId);
            $chk->execute();
            $chk->store_result();
            if ($chk->num_rows > 0) {
                $remarks = $grade >= 75 ? 'Passed' : 'Failed';
                $stmt = $conn->prepare("
                    INSERT INTO grades (student_id, subject_id, period, grade, remarks, encoded_by)
                    VALUES (?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE grade = VALUES(grade), remarks = VALUES(remarks), encoded_by = VALUES(encoded_by), encoded_at = NOW()
                ");
                $stmt->bind_param('iisdsi', $studentId, $subjectId, $period, $grade, $remarks, $teacherId);
                $stmt->execute();
                $stmt->close();
                $successMsg = "Grade saved successfully.";
            } else {
                $errorMsg = "You are not authorized to encode grades for this subject.";
            }
            $chk->close();
        } else {
            $errorMsg = "Invalid input. Please check all fields.";
        }
    }

    if ($_POST['action'] === 'add_subject') {
        $code = trim($_POST['subject_code'] ?? '');
        $name = trim($_POST['subject_name'] ?? '');
        if ($code && $name) {
            $stmt = $conn->prepare("INSERT INTO subjects (code, name, teacher_id) VALUES (?, ?, ?)");
            $stmt->bind_param('ssi', $code, $name, $teacherId);
            $stmt->execute();
            $stmt->close();
            $successMsg = "Subject \"{$name}\" added.";
        } else {
            $errorMsg = "Subject code and name are required.";
        }
    }

    if ($_POST['action'] === 'delete_grade') {
        $gradeId = (int)($_POST['grade_id'] ?? 0);
        if ($gradeId) {
            $stmt = $conn->prepare("DELETE FROM grades WHERE id = ? AND encoded_by = ?");
            $stmt->bind_param('ii', $gradeId, $teacherId);
            $stmt->execute();
            $stmt->close();
            $successMsg = "Grade entry deleted.";
        }
    }
}

// ── Fetch data ───────────────────────────────────────────────────────────────
$subjects = [];
$res = $conn->prepare("SELECT id, code, name FROM subjects WHERE teacher_id = ? ORDER BY code");
$res->bind_param('i', $teacherId);
$res->execute();
$subResult = $res->get_result();
while ($r = $subResult->fetch_assoc()) { $subjects[] = $r; }
$res->close();

$students = [];
$res2 = $conn->query("SELECT ID, firstName, middleName, lastName, email FROM users WHERE userType = 'student' ORDER BY firstName, lastName");
if ($res2) { while ($r = $res2->fetch_assoc()) { $students[] = $r; } }

$ledger = [];
if (!empty($subjects)) {
    $subIds = implode(',', array_column($subjects, 'id'));
    $lRes = $conn->query("
        SELECT g.id as grade_id, g.student_id, g.subject_id, g.period, g.grade, g.remarks, g.encoded_at,
               u.firstName, u.middleName, u.lastName,
               s.code as subject_code, s.name as subject_name
        FROM grades g
        JOIN users    u ON g.student_id  = u.ID
        JOIN subjects s ON g.subject_id  = s.id
        WHERE s.teacher_id = {$teacherId}
        ORDER BY u.firstName, u.lastName, s.code, FIELD(g.period,'Prelim','Midterm','Pre-Final','Final')
    ");
    if ($lRes) { while ($r = $lRes->fetch_assoc()) { $ledger[] = $r; } }
}

$view = $_GET['view'] ?? 'encode';
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Teacher Dashboard — SAM</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
:root {
    --bg:          #0d0f14;
    --surface:     #151820;
    --surface2:    #1c2030;
    --border:      rgba(255,255,255,.07);
    --accent:      #00b2ff;
    --accent2:     #7b5cff;
    --danger:      #ff4d6d;
    --success:     #36d68a;
    --warning:     #f5a623;
    --text:        #e8ecf5;
    --muted:       #6b7591;
    --radius:      12px;
    --font-head:   'Syne', sans-serif;
    --font-body:   'DM Sans', sans-serif;
    --grad:        linear-gradient(135deg, var(--accent), var(--accent2));
}
[data-theme="light"] {
    --bg:       #f0f2f8;
    --surface:  #ffffff;
    --surface2: #e8ecf5;
    --border:   rgba(0,0,0,.08);
    --text:     #1a1d2e;
    --muted:    #7a84a0;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: var(--font-body); background: var(--bg); color: var(--text); min-height: 100vh; display: flex; }

/* Sidebar */
.sidebar { width: 240px; min-height: 100vh; background: var(--surface); border-right: 1px solid var(--border); display: flex; flex-direction: column; padding: 28px 0; position: fixed; top: 0; left: 0; bottom: 0; z-index: 100; }
.sidebar-logo { display: flex; align-items: center; gap: 10px; padding: 0 24px 28px; border-bottom: 1px solid var(--border); }
.sidebar-logo img { width: 38px; height: 38px; border-radius: 8px; object-fit: contain; }
.sidebar-logo span { font-family: var(--font-head); font-size: 18px; font-weight: 800; background: var(--grad); background-clip: text; -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
.sidebar-label { font-family: var(--font-head); font-size: 10px; font-weight: 700; letter-spacing: .12em; color: var(--muted); padding: 22px 24px 8px; text-transform: uppercase; }
.nav-item { display: flex; align-items: center; gap: 12px; padding: 12px 24px; cursor: pointer; color: var(--muted); font-size: 14px; font-weight: 500; border-left: 3px solid transparent; transition: all .2s; text-decoration: none; }
.nav-item:hover { color: var(--text); background: var(--surface2); }
.nav-item.active { color: var(--accent); border-left-color: var(--accent); background: rgba(0,178,255,.07); }
.nav-item .icon { font-size: 18px; width: 22px; text-align: center; }
.sidebar-bottom { margin-top: auto; padding: 20px 24px; border-top: 1px solid var(--border); }
.teacher-chip { display: flex; align-items: center; gap: 10px; }
.avatar { width: 36px; height: 36px; background: var(--grad); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-family: var(--font-head); font-weight: 800; font-size: 14px; color: #fff; flex-shrink: 0; }
.teacher-info { flex: 1; overflow: hidden; }
.teacher-info .name { font-size: 13px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.teacher-info .role { font-size: 11px; color: var(--muted); }

/* Main */
.main { margin-left: 240px; flex: 1; display: flex; flex-direction: column; min-height: 100vh; }
.topbar { background: var(--surface); border-bottom: 1px solid var(--border); height: 60px; display: flex; align-items: center; justify-content: space-between; padding: 0 32px; position: sticky; top: 0; z-index: 90; }
.topbar-title { font-family: var(--font-head); font-size: 15px; font-weight: 700; color: var(--muted); }
.topbar-actions { display: flex; align-items: center; gap: 12px; }
.btn-icon { width: 36px; height: 36px; background: var(--surface2); border: 1px solid var(--border); border-radius: 8px; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 16px; transition: background .2s; color: var(--text); text-decoration: none; }
.btn-icon:hover { background: var(--border); }
.content { padding: 32px; flex: 1; }
.page-header { margin-bottom: 28px; }
.page-header h1 { font-family: var(--font-head); font-size: 26px; font-weight: 800; }
.page-header p  { color: var(--muted); font-size: 13px; margin-top: 4px; }

/* Alerts */
.alert { padding: 12px 18px; border-radius: var(--radius); font-size: 13px; font-weight: 500; margin-bottom: 20px; border: 1px solid; }
.alert-success { background: rgba(54,214,138,.1); border-color: var(--success); color: var(--success); }
.alert-error   { background: rgba(255,77,109,.1);  border-color: var(--danger);  color: var(--danger);  }

/* Layout */
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
@media (max-width: 900px) { .grid-2 { grid-template-columns: 1fr; } }

/* Card */
.card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; }
.card-head { padding: 18px 24px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
.card-head h2 { font-family: var(--font-head); font-size: 14px; font-weight: 700; }
.card-body { padding: 24px; }

/* Forms */
.form-group { margin-bottom: 16px; }
.form-group label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px; text-transform: uppercase; letter-spacing: .05em; }
.form-control { width: 100%; padding: 10px 14px; background: var(--surface2); border: 1px solid var(--border); border-radius: 8px; color: var(--text); font-family: var(--font-body); font-size: 14px; outline: none; transition: border-color .2s; }
.form-control:focus { border-color: var(--accent); }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

/* Buttons */
.btn { padding: 10px 20px; border-radius: 8px; border: none; font-family: var(--font-body); font-size: 13px; font-weight: 600; cursor: pointer; transition: opacity .2s, transform .1s; display: inline-flex; align-items: center; gap: 6px; }
.btn:active { transform: scale(.97); }
.btn-primary { background: var(--grad); color: #fff; }
.btn-primary:hover { opacity: .9; }
.btn-sm { padding: 6px 12px; font-size: 12px; border-radius: 6px; }
.btn-danger { background: rgba(255,77,109,.15); color: var(--danger); border: 1px solid var(--danger); }
.btn-danger:hover { background: rgba(255,77,109,.25); }

/* Grade pill */
.grade-pill { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 700; }
.grade-pass { background: rgba(54,214,138,.15); color: var(--success); }
.grade-fail { background: rgba(255,77,109,.15);  color: var(--danger);  }

/* Table */
.data-table { width: 100%; border-collapse: collapse; }
.data-table th { font-family: var(--font-head); font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: var(--muted); padding: 10px 14px; border-bottom: 1px solid var(--border); text-align: left; background: var(--surface2); }
.data-table td { padding: 11px 14px; font-size: 13px; border-bottom: 1px solid var(--border); vertical-align: middle; }
.data-table tbody tr:hover { background: rgba(0,178,255,.04); }
.data-table tbody tr:last-child td { border-bottom: none; }
.empty-row td { text-align: center; color: var(--muted); padding: 40px; font-style: italic; }

/* Subject list */
.subject-list { display: flex; flex-direction: column; gap: 8px; }
.subject-item { display: flex; align-items: center; justify-content: space-between; background: var(--surface2); border: 1px solid var(--border); border-radius: 8px; padding: 10px 14px; font-size: 13px; }
.subject-code { font-family: var(--font-head); font-size: 11px; font-weight: 700; color: var(--accent); background: rgba(0,178,255,.1); padding: 2px 8px; border-radius: 4px; margin-right: 8px; }
.period-badge { font-size: 11px; font-weight: 700; padding: 3px 9px; border-radius: 5px; background: rgba(123,92,255,.15); color: var(--accent2); }

/* Section divider */
.section-divider { margin: 32px 0 20px; display: flex; align-items: center; gap: 12px; }
.section-divider h2 { font-family: var(--font-head); font-size: 16px; font-weight: 800; }
.section-divider::after { content: ''; flex: 1; height: 1px; background: var(--border); }

/* Stats */
.stats-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 28px; }
.stat-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 20px 22px; position: relative; overflow: hidden; }
.stat-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; }
.stat-card.blue::before   { background: var(--accent); }
.stat-card.purple::before { background: var(--accent2); }
.stat-card.green::before  { background: var(--success); }
.stat-value { font-family: var(--font-head); font-size: 28px; font-weight: 800; }
.stat-label { font-size: 12px; color: var(--muted); margin-top: 4px; }
.table-wrap { overflow-x: auto; }
</style>
</head>
<body>

<!-- ═══ SIDEBAR ═══ -->
<nav class="sidebar">
    <div class="sidebar-logo">
        <img src="LOGO.png" alt="SAM" onerror="this.style.display='none'">
        <span>SAM</span>
    </div>
    <div class="sidebar-label">Menu</div>
    <a href="?view=encode"   class="nav-item <?= $view==='encode'   ? 'active':'' ?>"><span class="icon">✏️</span> Encode Grades</a>
    <a href="?view=ledger"   class="nav-item <?= $view==='ledger'   ? 'active':'' ?>"><span class="icon">📋</span> Grade Ledger</a>
    <a href="?view=subjects" class="nav-item <?= $view==='subjects' ? 'active':'' ?>"><span class="icon">📚</span> My Subjects</a>
    <div class="sidebar-bottom">
        <div class="teacher-chip">
            <div class="avatar"><?= strtoupper(substr($teacherName,0,1)) ?></div>
            <div class="teacher-info">
                <div class="name"><?= htmlspecialchars($teacherName) ?></div>
                <div class="role">Teacher</div>
            </div>
        </div>
    </div>
</nav>

<!-- ═══ MAIN ═══ -->
<div class="main">
    <div class="topbar">
        <span class="topbar-title">Smart Attendance Monitoring System</span>
        <div class="topbar-actions">
            <button class="btn-icon" onclick="toggleTheme()" id="themeToggle" title="Toggle theme">🌙</button>
            <a href="backend/logout.php" class="btn-icon" title="Logout">🚪</a>
        </div>
    </div>

    <div class="content">
        <?php if ($successMsg): ?><div class="alert alert-success">✅ <?= htmlspecialchars($successMsg) ?></div><?php endif; ?>
        <?php if ($errorMsg):   ?><div class="alert alert-error">⚠️ <?= htmlspecialchars($errorMsg) ?></div><?php endif; ?>

        <!-- ══ ENCODE ══ -->
        <?php if ($view === 'encode'): ?>
        <div class="page-header">
            <h1>Encode Grades</h1>
            <p>Select a student, subject, and period to enter or update a grade.</p>
        </div>

        <?php
        $totalEncoded = count($ledger);
        $passCount    = count(array_filter($ledger, fn($r) => $r['remarks'] === 'Passed'));
        $failCount    = $totalEncoded - $passCount;
        ?>
        <div class="stats-row">
            <div class="stat-card blue">
                <div class="stat-value"><?= $totalEncoded ?></div>
                <div class="stat-label">Total Grades Encoded</div>
            </div>
            <div class="stat-card green">
                <div class="stat-value"><?= $passCount ?></div>
                <div class="stat-label">Passed</div>
            </div>
            <div class="stat-card purple">
                <div class="stat-value"><?= $failCount ?></div>
                <div class="stat-label">Failed</div>
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h2>📝 Grade Entry Form</h2></div>
            <div class="card-body">
                <?php if (empty($subjects)): ?>
                    <p style="color:var(--muted);text-align:center;padding:20px 0;">
                        No subjects yet. <a href="?view=subjects" style="color:var(--accent)">Add a subject first →</a>
                    </p>
                <?php elseif (empty($students)): ?>
                    <p style="color:var(--muted);text-align:center;padding:20px 0;">No students registered in the system.</p>
                <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="action" value="save_grade">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Student</label>
                            <select name="student_id" class="form-control" required>
                                <option value="">— Select student —</option>
                                <?php foreach ($students as $s): ?>
                                    <option value="<?= $s['ID'] ?>"><?= htmlspecialchars(trim($s['firstName'].' '.$s['middleName'].' '.$s['lastName'])) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Subject</label>
                            <select name="subject_id" class="form-control" required>
                                <option value="">— Select subject —</option>
                                <?php foreach ($subjects as $sub): ?>
                                    <option value="<?= $sub['id'] ?>">[<?= htmlspecialchars($sub['code']) ?>] <?= htmlspecialchars($sub['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Grading Period</label>
                            <select name="period" class="form-control" required>
                                <option value="">— Select period —</option>
                                <option value="Prelim">Prelim</option>
                                <option value="Midterm">Midterm</option>
                                <option value="Pre-Final">Pre-Final</option>
                                <option value="Final">Final</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Grade (0 – 100)</label>
                            <input type="number" name="grade" class="form-control" min="0" max="100" step="0.01" placeholder="e.g. 87.50" required>
                        </div>
                    </div>
                    <p style="font-size:12px;color:var(--muted);margin-bottom:16px;">
                        Passing mark: <strong style="color:var(--success)">75.00</strong> — Grade is auto-marked <em>Passed</em> or <em>Failed</em>.
                        Re-encoding an existing entry will overwrite it.
                    </p>
                    <button type="submit" class="btn btn-primary">💾 Save Grade</button>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($ledger)): ?>
        <div class="section-divider"><h2>Recent Entries</h2></div>
        <div class="card">
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Student</th><th>Subject</th><th>Period</th><th>Grade</th><th>Remarks</th><th>Encoded At</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach (array_slice(array_reverse($ledger),0,10) as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars(trim($row['firstName'].' '.$row['middleName'].' '.$row['lastName'])) ?></td>
                            <td><span class="subject-code"><?= htmlspecialchars($row['subject_code']) ?></span><?= htmlspecialchars($row['subject_name']) ?></td>
                            <td><span class="period-badge"><?= htmlspecialchars($row['period']) ?></span></td>
                            <td><strong><?= number_format($row['grade'],2) ?></strong></td>
                            <td><span class="grade-pill <?= $row['remarks']==='Passed'?'grade-pass':'grade-fail' ?>"><?= htmlspecialchars($row['remarks']) ?></span></td>
                            <td style="color:var(--muted);font-size:12px;"><?= date('M d, Y h:i A', strtotime($row['encoded_at'])) ?></td>
                            <td>
                                <form method="POST" onsubmit="return confirm('Delete this grade entry?')">
                                    <input type="hidden" name="action"   value="delete_grade">
                                    <input type="hidden" name="grade_id" value="<?= $row['grade_id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">🗑</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- ══ LEDGER ══ -->
        <?php elseif ($view === 'ledger'): ?>
        <div class="page-header">
            <h1>Grade Ledger</h1>
            <p>Complete list of all grades you have encoded.</p>
        </div>
        <div class="card">
            <div class="card-head">
                <h2>All Encoded Grades</h2>
                <?php if (!empty($ledger)): ?><span style="font-size:12px;color:var(--muted);"><?= count($ledger) ?> records</span><?php endif; ?>
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>#</th><th>Student Name</th><th>Subject</th><th>Period</th><th>Grade</th><th>Remarks</th><th>Encoded At</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php if (empty($ledger)): ?>
                            <tr class="empty-row"><td colspan="8">No grades encoded yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($ledger as $i => $row): ?>
                            <tr>
                                <td style="color:var(--muted);"><?= $i+1 ?></td>
                                <td><?= htmlspecialchars(trim($row['firstName'].' '.$row['middleName'].' '.$row['lastName'])) ?></td>
                                <td><span class="subject-code"><?= htmlspecialchars($row['subject_code']) ?></span><?= htmlspecialchars($row['subject_name']) ?></td>
                                <td><span class="period-badge"><?= htmlspecialchars($row['period']) ?></span></td>
                                <td><strong><?= number_format($row['grade'],2) ?></strong></td>
                                <td><span class="grade-pill <?= $row['remarks']==='Passed'?'grade-pass':'grade-fail' ?>"><?= htmlspecialchars($row['remarks']) ?></span></td>
                                <td style="color:var(--muted);font-size:12px;"><?= date('M d, Y h:i A', strtotime($row['encoded_at'])) ?></td>
                                <td>
                                    <form method="POST" onsubmit="return confirm('Delete this grade entry?')">
                                        <input type="hidden" name="action"   value="delete_grade">
                                        <input type="hidden" name="grade_id" value="<?= $row['grade_id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">🗑 Delete</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ══ SUBJECTS ══ -->
        <?php elseif ($view === 'subjects'): ?>
        <div class="page-header">
            <h1>My Subjects</h1>
            <p>Manage subjects you teach. Only your subjects appear in the grade encoder.</p>
        </div>
        <div class="grid-2">
            <div class="card">
                <div class="card-head"><h2>➕ Add New Subject</h2></div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="add_subject">
                        <div class="form-group">
                            <label>Subject Code</label>
                            <input type="text" name="subject_code" class="form-control" placeholder="e.g. MATH101" maxlength="20" required>
                        </div>
                        <div class="form-group">
                            <label>Subject Name</label>
                            <input type="text" name="subject_name" class="form-control" placeholder="e.g. College Algebra" maxlength="100" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Add Subject</button>
                    </form>
                </div>
            </div>
            <div class="card">
                <div class="card-head">
                    <h2>📚 Subject List</h2>
                    <span style="font-size:12px;color:var(--muted);"><?= count($subjects) ?> subject<?= count($subjects)!==1?'s':'' ?></span>
                </div>
                <div class="card-body">
                    <?php if (empty($subjects)): ?>
                        <p style="color:var(--muted);text-align:center;padding:20px 0;font-style:italic;">No subjects added yet.</p>
                    <?php else: ?>
                        <div class="subject-list">
                            <?php foreach ($subjects as $sub): ?>
                                <div class="subject-item">
                                    <div><span class="subject-code"><?= htmlspecialchars($sub['code']) ?></span><?= htmlspecialchars($sub['name']) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<script>
function toggleTheme() {
    const html = document.documentElement;
    const btn  = document.getElementById('themeToggle');
    const next = html.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
    html.setAttribute('data-theme', next);
    btn.textContent = next === 'dark' ? '🌙' : '☀️';
    try { localStorage.setItem('theme', next); } catch(e) {}
}
document.addEventListener('DOMContentLoaded', function () {
    const saved = localStorage.getItem('theme') || 'dark';
    document.documentElement.setAttribute('data-theme', saved);
    const btn = document.getElementById('themeToggle');
    if (btn) btn.textContent = saved === 'light' ? '☀️' : '🌙';
});
document.querySelectorAll('.alert').forEach(el => {
    setTimeout(() => { el.style.transition='opacity .5s'; el.style.opacity='0'; setTimeout(()=>el.remove(),500); }, 4000);
});
</script>
</body>
</html>