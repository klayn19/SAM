<?php
session_start();
require_once 'backend/config.php';

// Check if teacher is logged in
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || ($_SESSION['role'] ?? '') !== 'teacher') {
    die("Unauthorized access.");
}

$teacherId = $_SESSION['ID'];
$gradeIds = $_POST['grade_ids'] ?? [];

if (empty($gradeIds)) {
    echo "<script>alert('No grades selected for printing.'); window.close();</script>";
    exit();
}

// Prepare placeholders for IN clause
$placeholders = implode(',', array_fill(0, count($gradeIds), '?'));
$types = str_repeat('i', count($gradeIds));

// Fetch the selected grades, but ensure they belong to this teacher
$sql = "
    SELECT g.period, g.school_year, g.grade, g.remarks, g.encoded_at,
           u.firstName, u.middleName, u.lastName, u.ID as student_id_number,
           s.code as subject_code, s.name as subject_name
    FROM grades g
    JOIN users u ON g.student_id = u.ID
    JOIN subjects s ON g.subject_id = s.id
    WHERE s.teacher_id = ? AND g.id IN ($placeholders)
    ORDER BY u.lastName, u.firstName, s.code, g.period
";

$params = array_merge([$teacherId], $gradeIds);
$types = 'i' . $types;

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$grades = [];
while ($row = $result->fetch_assoc()) {
    $grades[] = $row;
}
$stmt->close();

if (empty($grades)) {
    echo "<script>alert('No valid grades found to print.'); window.close();</script>";
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Print Grades</title>
<style>
    body {
        font-family: 'Helvetica Neue', Arial, sans-serif;
        color: #000;
        margin: 0;
        padding: 20px;
        background: #fff;
    }
    .print-header {
        text-align: center;
        margin-bottom: 30px;
        border-bottom: 2px solid #000;
        padding-bottom: 15px;
    }
    .print-header h1 {
        margin: 0;
        font-size: 24px;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    .print-header p {
        margin: 5px 0 0;
        font-size: 14px;
        color: #333;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 20px;
        font-size: 13px;
    }
    th, td {
        border: 1px solid #000;
        padding: 8px 10px;
        text-align: left;
    }
    th {
        background: #f0f0f0;
        font-weight: bold;
        text-transform: uppercase;
    }
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    
    .signature-section {
        margin-top: 50px;
        display: flex;
        justify-content: flex-end;
    }
    .signature-box {
        text-align: center;
        width: 250px;
    }
    .signature-line {
        border-bottom: 1px solid #000;
        margin-bottom: 5px;
        height: 30px;
    }
    
    @media print {
        body { padding: 0; }
        .no-print { display: none; }
    }
</style>
</head>
<body onload="window.print()">

    <div class="no-print" style="margin-bottom:20px; text-align:right;">
        <button onclick="window.print()" style="padding:8px 15px; cursor:pointer;">🖨️ Print Document</button>
        <button onclick="window.close()" style="padding:8px 15px; cursor:pointer;">❌ Close</button>
    </div>

    <div class="print-header">
        <h1>Smart Attendance & Grading System</h1>
        <p>Official Grade Report</p>
        <p>Printed on: <?= date('F d, Y h:i A') ?></p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Student Name</th>
                <th>Subject</th>
                <th>School Year</th>
                <th>Period</th>
                <th class="text-center">Grade</th>
                <th class="text-center">Remarks</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($grades as $g): 
                $studentName = trim($g['lastName'] . ', ' . $g['firstName'] . ' ' . $g['middleName']);
            ?>
            <tr>
                <td><strong><?= htmlspecialchars($studentName) ?></strong><br><small style="color:#555;">ID: <?= htmlspecialchars($g['student_id_number']) ?></small></td>
                <td>[<?= htmlspecialchars($g['subject_code']) ?>] <?= htmlspecialchars($g['subject_name']) ?></td>
                <td><?= htmlspecialchars($g['school_year']) ?></td>
                <td><?= htmlspecialchars($g['period']) ?></td>
                <td class="text-center"><strong><?= number_format($g['grade'], 2) ?></strong></td>
                <td class="text-center"><?= htmlspecialchars($g['remarks']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="signature-section">
        <div class="signature-box">
            <div class="signature-line"></div>
            <strong><?= htmlspecialchars($_SESSION['firstName'] . ' ' . ($_SESSION['lastName'] ?? '')) ?></strong><br>
            <small>Teacher / Instructor</small>
        </div>
    </div>

</body>
</html>
