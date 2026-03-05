<?php
session_start();
require_once 'backend/config.php';

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: index.php'); exit();
}

$adminName = $_SESSION['firstName'] ?? 'Admin';
$success = $error = '';

// Handle RFID assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_rfid'])) {
    $studentId = $_POST['student_id'];
    $rfidUid   = trim(strtoupper($_POST['rfid_uid']));
    $check = $conn->prepare("SELECT ID, firstName, lastName FROM users WHERE rfid_uid = ? AND ID != ?");
    $check->bind_param("si", $rfidUid, $studentId);
    $check->execute();
    $res = $check->get_result();
    if ($res->num_rows > 0) {
        $ex = $res->fetch_assoc();
        $error = "RFID already assigned to " . $ex['firstName'] . " " . $ex['lastName'];
    } else {
        $stmt = $conn->prepare("UPDATE users SET rfid_uid = ? WHERE ID = ?");
        $stmt->bind_param("si", $rfidUid, $studentId);
        $success = $stmt->execute() ? "RFID assigned successfully!" : "Failed to assign RFID";
        $stmt->close();
    }
    $check->close();
}

// Handle RFID removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_rfid'])) {
    $studentId = $_POST['student_id'];
    $stmt = $conn->prepare("UPDATE users SET rfid_uid = NULL WHERE ID = ?");
    $stmt->bind_param("i", $studentId);
    $success = $stmt->execute() ? "RFID removed successfully!" : "Failed to remove RFID";
    $stmt->close();
}

// Fetch students
$students = [];
$r = $conn->query("SELECT ID, firstName, middleName, lastName, email, rfid_uid FROM users WHERE userType='student' ORDER BY firstName,lastName");
while ($row = $r->fetch_assoc()) $students[] = $row;

// Recent scans
$recentScans = [];
$sr = $conn->query("SELECT rs.rfid_uid, rs.scan_time, rs.action_type, rs.success, rs.message, u.firstName, u.lastName FROM rfid_scans rs LEFT JOIN users u ON rs.rfid_uid = u.rfid_uid ORDER BY rs.scan_time DESC LIMIT 10");
if ($sr) while ($row = $sr->fetch_assoc()) $recentScans[] = $row;

$totalStudents  = count($students);
$assignedCount  = count(array_filter($students, fn($s) => !empty($s['rfid_uid'])));
$unassignedCount = $totalStudents - $assignedCount;
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>RFID Management — SAM</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
:root{--bg:#0d0f14;--surface:#151820;--surface2:#1c2030;--border:rgba(255,255,255,.07);--accent:#00b2ff;--accent2:#7b5cff;--danger:#ff4d6d;--success:#36d68a;--warning:#f5a623;--text:#e8ecf5;--muted:#6b7591;--radius:12px;--font-head:'Syne',sans-serif;--font-body:'DM Sans',sans-serif;--grad:linear-gradient(135deg,var(--accent),var(--accent2))}
[data-theme="light"]{--bg:#f0f2f8;--surface:#fff;--surface2:#e8ecf5;--border:rgba(0,0,0,.08);--text:#1a1d2e;--muted:#7a84a0}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:var(--font-body);background:var(--bg);color:var(--text);min-height:100vh;display:flex;transition:background .3s,color .3s}

/* SIDEBAR */
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
.admin-chip{display:flex;align-items:center;gap:10px}
.avatar{width:36px;height:36px;background:var(--grad);border-radius:50%;display:flex;align-items:center;justify-content:center;font-family:var(--font-head);font-weight:800;font-size:14px;color:#fff;flex-shrink:0}
.admin-info{flex:1;overflow:hidden}
.admin-info .name{font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.admin-info .role{font-size:11px;color:var(--muted)}

/* MAIN */
.main{margin-left:240px;flex:1;display:flex;flex-direction:column;min-height:100vh}

/* TOPBAR */
.topbar{background:var(--surface);border-bottom:1px solid var(--border);height:60px;display:flex;align-items:center;justify-content:space-between;padding:0 32px;position:sticky;top:0;z-index:90}
.topbar-title{font-family:var(--font-head);font-size:15px;font-weight:700;color:var(--muted)}
.topbar-actions{display:flex;align-items:center;gap:10px}
.btn-icon{width:36px;height:36px;background:var(--surface2);border:1px solid var(--border);border-radius:8px;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:16px;transition:background .2s;color:var(--text);padding:0}
.btn-icon:hover{background:var(--border)}
.btn-user{height:36px;padding:0 14px;background:var(--surface2);border:1px solid var(--border);border-radius:8px;display:flex;align-items:center;gap:8px;font-size:13px;font-weight:500;color:var(--text)}
.btn-logout{height:36px;padding:0 16px;background:rgba(255,77,109,.12);border:1px solid rgba(255,77,109,.25);border-radius:8px;color:var(--danger);font-family:var(--font-body);font-size:13px;font-weight:500;cursor:pointer;transition:background .2s;display:inline-flex;align-items:center;gap:6px}
.btn-logout:hover{background:rgba(255,77,109,.22)}

/* CONTENT */
.content{padding:32px;flex:1}
.page-header{margin-bottom:28px}
.page-header h1{font-family:var(--font-head);font-size:26px;font-weight:800}
.page-header p{color:var(--muted);font-size:13px;margin-top:4px}

/* STATS */
.stats-row{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px}
.stat-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:20px 22px;position:relative;overflow:hidden}
.stat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px}
.stat-card.blue::before{background:var(--accent)}
.stat-card.green::before{background:var(--success)}
.stat-card.red::before{background:var(--danger)}
.stat-value{font-family:var(--font-head);font-size:28px;font-weight:800}
.stat-label{font-size:12px;color:var(--muted);margin-top:4px}

/* ALERTS */
.alert{padding:12px 18px;border-radius:var(--radius);font-size:13px;font-weight:500;margin-bottom:20px;border:1px solid}
.alert-success{background:rgba(54,214,138,.1);border-color:var(--success);color:var(--success)}
.alert-error{background:rgba(255,77,109,.1);border-color:var(--danger);color:var(--danger)}

/* CARD */
.card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;margin-bottom:20px}
.card-head{padding:18px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
.card-head h2{font-family:var(--font-head);font-size:14px;font-weight:700}
.table-wrap{overflow-x:auto}

/* TABLE */
.data-table{width:100%;border-collapse:collapse}
.data-table th{font-family:var(--font-head);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);padding:10px 14px;border-bottom:1px solid var(--border);text-align:left;background:var(--surface2)}
.data-table td{padding:12px 14px;font-size:13px;border-bottom:1px solid var(--border);vertical-align:middle}
.data-table tbody tr:hover{background:rgba(0,178,255,.04)}
.data-table tbody tr:last-child td{border-bottom:none}
.empty-row td{text-align:center;color:var(--muted);padding:40px;font-style:italic}

/* BADGES */
.rfid-pill{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:700}
.rfid-assigned{background:rgba(54,214,138,.15);color:var(--success)}
.rfid-unassigned{background:rgba(255,77,109,.15);color:var(--danger)}
.scan-pill{display:inline-block;padding:3px 9px;border-radius:5px;font-size:11px;font-weight:700;background:rgba(123,92,255,.15);color:var(--accent2)}

/* BUTTONS */
.btn{padding:7px 14px;border-radius:7px;border:none;font-family:var(--font-body);font-size:12px;font-weight:600;cursor:pointer;transition:all .2s;display:inline-flex;align-items:center;gap:5px}
.btn:active{transform:scale(.97)}
.btn-primary{background:var(--grad);color:#fff}
.btn-primary:hover{opacity:.9}
.btn-danger-sm{background:rgba(255,77,109,.12);border:1px solid rgba(255,77,109,.3);color:var(--danger)}
.btn-danger-sm:hover{background:rgba(255,77,109,.22)}
.btn-scanner{height:36px;padding:0 18px;background:rgba(54,214,138,.12);border:1px solid rgba(54,214,138,.3);border-radius:8px;color:var(--success);font-family:var(--font-body);font-size:13px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:7px;text-decoration:none;transition:all .2s}
.btn-scanner:hover{background:rgba(54,214,138,.22)}

/* MODAL */
.modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:1000;align-items:center;justify-content:center}
.modal.open{display:flex}
.modal-content{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:32px;width:90%;max-width:440px;position:relative}
.modal-content::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:var(--grad);border-radius:var(--radius) var(--radius) 0 0}
.modal-content h2{font-family:var(--font-head);font-size:18px;font-weight:800;margin-bottom:6px}
.modal-content p{font-size:13px;color:var(--muted);margin-bottom:20px}
.form-group{margin-bottom:16px}
.form-group label{display:block;font-family:var(--font-head);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:6px}
.form-control{width:100%;padding:10px 14px;background:var(--surface2);border:1px solid var(--border);border-radius:8px;color:var(--text);font-family:var(--font-body);font-size:14px;outline:none;transition:border-color .2s}
.form-control:focus{border-color:var(--accent)}
.form-control::placeholder{color:var(--muted)}
.modal-buttons{display:flex;gap:10px;margin-top:20px}
.modal-buttons .btn{flex:1;justify-content:center;padding:10px}
.waiting{display:none;font-size:12px;color:var(--accent);margin-top:6px;animation:pulse 1.5s infinite}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.4}}

/* SCAN LOGS */
.scan-item{display:grid;grid-template-columns:2fr 1.5fr 1fr 1.2fr;gap:12px;padding:12px 14px;border-bottom:1px solid var(--border);font-size:13px;align-items:center;border-left:3px solid transparent}
.scan-item:last-child{border-bottom:none}
.scan-item.s-success{border-left-color:var(--success)}
.scan-item.s-failed{border-left-color:var(--danger)}
.scan-name{font-weight:500}
.scan-unknown{color:var(--danger);font-style:italic}
.scan-uid{font-family:monospace;font-size:12px;color:var(--muted)}
.scan-time{font-size:12px;color:var(--muted);text-align:right}

/* RESPONSIVE */
@media(max-width:900px){.stats-row{grid-template-columns:repeat(2,1fr)}}
@media(max-width:768px){
    .sidebar{width:60px;padding:16px 0}
    .sidebar-logo span,.sidebar-label,.nav-item span:not(.icon),.admin-info{display:none}
    .sidebar-logo{padding:0 11px 16px;justify-content:center}
    .sidebar-logo img{width:32px;height:32px}
    .nav-item{padding:12px;justify-content:center}
    .sidebar-bottom{padding:12px}
    .admin-chip{justify-content:center}
    .main{margin-left:60px}
    .topbar{padding:0 16px}
    .topbar-title{font-size:12px}
    .content{padding:16px}
    .scan-item{grid-template-columns:1fr 1fr}
    .btn-user span{display:none}
}
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
    <a href="Admin_dashboard.php"              class="nav-item"><span class="icon">🏠</span> Home</a>
    <a href="Admin_dashboard.php?view=profile" class="nav-item"><span class="icon">👤</span> Profile</a>
    <a href="Admin_dashboard.php?view=record"  class="nav-item"><span class="icon">📅</span> Record</a>
    <a href="rfid_manage.php"                  class="nav-item active"><span class="icon">💳</span> RFID Management</a>
    <a href="Attendance_manual.php"            class="nav-item"><span class="icon">📝</span> Attendance Manual</a>
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

<!-- MAIN -->
<div class="main">
    <div class="topbar">
        <span class="topbar-title">RFID Management System</span>
        <div class="topbar-actions">
            <button class="btn-icon" onclick="toggleTheme()" id="themeToggle" title="Toggle theme">🌙</button>
            <div class="btn-user"><span>👤</span><span><?= htmlspecialchars($adminName) ?></span></div>
            <form action="backend/logout.php" method="post" style="margin:0;" onsubmit="return confirm('Log out?')">
                <button type="submit" class="btn-logout">🚪 Logout</button>
            </form>
        </div>
    </div>

    <div class="content">
        <div class="page-header">
            <h1>RFID Management</h1>
            <p>Assign and manage RFID cards for students.</p>
        </div>

        <?php if ($success): ?><div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>
        <?php if ($error):   ?><div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>

        <!-- Stats -->
        <div class="stats-row">
            <div class="stat-card blue">
                <div class="stat-value"><?= $totalStudents ?></div>
                <div class="stat-label">Total Students</div>
            </div>
            <div class="stat-card green">
                <div class="stat-value"><?= $assignedCount ?></div>
                <div class="stat-label">RFID Assigned</div>
            </div>
            <div class="stat-card red">
                <div class="stat-value"><?= $unassignedCount ?></div>
                <div class="stat-label">Not Assigned</div>
            </div>
        </div>

        <!-- Student RFID Table -->
        <div class="card">
            <div class="card-head">
                <h2>💳 Student RFID Cards</h2>
                <a href="rfid_scanner.php" class="btn-scanner">🔍 Open RFID Scanner</a>
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Student Name</th>
                            <th>Email</th>
                            <th>RFID Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($students)): ?>
                            <tr class="empty-row"><td colspan="5">No students found.</td></tr>
                        <?php else: foreach ($students as $i => $s):
                            $hasRfid = !empty($s['rfid_uid']);
                            $fullName = htmlspecialchars(trim($s['firstName'].' '.$s['middleName'].' '.$s['lastName']));
                        ?>
                        <tr>
                            <td style="color:var(--muted);"><?= $i+1 ?></td>
                            <td><strong><?= $fullName ?></strong></td>
                            <td style="font-size:12px;color:var(--muted);"><?= htmlspecialchars($s['email']) ?></td>
                            <td>
                                <?php if ($hasRfid): ?>
                                    <span class="rfid-pill rfid-assigned">✓ <?= htmlspecialchars($s['rfid_uid']) ?></span>
                                <?php else: ?>
                                    <span class="rfid-pill rfid-unassigned">✗ Not Assigned</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                    <button class="btn btn-primary"
                                        onclick="openModal(<?= $s['ID'] ?>, '<?= htmlspecialchars($s['firstName'], ENT_QUOTES) ?>', '<?= htmlspecialchars($s['rfid_uid'] ?? '', ENT_QUOTES) ?>')">
                                        <?= $hasRfid ? '✏️ Update' : '➕ Assign' ?>
                                    </button>
                                    <?php if ($hasRfid): ?>
                                    <form method="post" style="margin:0;">
                                        <input type="hidden" name="student_id" value="<?= $s['ID'] ?>">
                                        <button type="submit" name="remove_rfid" class="btn btn-danger-sm"
                                            onclick="return confirm('Remove RFID from this student?')">🗑 Remove</button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Scans -->
        <?php if (!empty($recentScans)): ?>
        <div class="card">
            <div class="card-head">
                <h2>🕒 Recent RFID Scans</h2>
                <span style="font-size:12px;color:var(--muted);"><?= count($recentScans) ?> recent</span>
            </div>
            <div>
                <?php foreach ($recentScans as $scan): ?>
                <div class="scan-item <?= $scan['success'] ? 's-success' : 's-failed' ?>">
                    <div class="scan-name">
                        <?php if ($scan['firstName']): ?>
                            <?= htmlspecialchars($scan['firstName'].' '.$scan['lastName']) ?>
                        <?php else: ?>
                            <span class="scan-unknown">Unknown</span>
                        <?php endif; ?>
                    </div>
                    <div class="scan-uid"><?= htmlspecialchars($scan['rfid_uid']) ?></div>
                    <div><span class="scan-pill"><?= htmlspecialchars(ucfirst($scan['action_type'])) ?></span></div>
                    <div class="scan-time"><?= date('M d, h:i A', strtotime($scan['scan_time'])) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </div><!-- .content -->
</div><!-- .main -->

<!-- ASSIGN MODAL -->
<div id="assignModal" class="modal">
    <div class="modal-content">
        <h2 id="modalTitle">Assign RFID Card</h2>
        <p id="modalStudent"></p>
        <form method="post">
            <input type="hidden" name="student_id" id="modalStudentId">
            <div class="form-group">
                <label>RFID UID</label>
                <input type="text" name="rfid_uid" id="modalRfidUid"
                       class="form-control" placeholder="Tap RFID card on reader…"
                       required autocomplete="off">
                <div class="waiting" id="waitingIndicator">⏳ Waiting for RFID scan…</div>
            </div>
            <div class="modal-buttons">
                <button type="submit" name="assign_rfid" class="btn btn-primary">💾 Save</button>
                <button type="button" onclick="closeModal()" class="btn btn-danger-sm">✕ Cancel</button>
            </div>
        </form>
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
document.addEventListener('DOMContentLoaded', function() {
    const saved = localStorage.getItem('theme') || 'dark';
    document.documentElement.setAttribute('data-theme', saved);
    const btn = document.getElementById('themeToggle');
    if (btn) btn.textContent = saved === 'light' ? '☀️' : '🌙';
});

function openModal(id, name, current) {
    document.getElementById('modalStudentId').value = id;
    document.getElementById('modalStudent').textContent = 'Student: ' + name;
    document.getElementById('modalTitle').textContent = current ? 'Update RFID Card' : 'Assign RFID Card';
    document.getElementById('modalRfidUid').value = '';
    document.getElementById('assignModal').classList.add('open');
    setTimeout(() => {
        document.getElementById('modalRfidUid').focus();
        document.getElementById('waitingIndicator').style.display = 'block';
    }, 100);
}
function closeModal() {
    document.getElementById('assignModal').classList.remove('open');
    document.getElementById('waitingIndicator').style.display = 'none';
}
window.addEventListener('click', e => {
    if (e.target === document.getElementById('assignModal')) closeModal();
});
document.getElementById('modalRfidUid').addEventListener('input', function() {
    this.value = this.value.toUpperCase().trim();
    document.getElementById('waitingIndicator').style.display = this.value ? 'none' : 'block';
});
setInterval(() => {
    const modal = document.getElementById('assignModal');
    if (modal.classList.contains('open')) {
        const inp = document.getElementById('modalRfidUid');
        if (document.activeElement !== inp) inp.focus();
    }
}, 1000);
</script>
</body>
</html>