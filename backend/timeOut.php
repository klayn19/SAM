<?php
date_default_timezone_set('Asia/Manila');

$studentId = $id;
$logDate = date('Y-m-d');
$timeOut = date('H:i:s'); // 24-hour format (HH:MM:SS) for database storage

// Update the most recent attendance record for this student on today's date
$stmt = $conn->prepare("UPDATE attendance_log SET timeOut = ? WHERE studentid = ? AND logDate = ? AND timeOut IS NULL ORDER BY timeIn DESC LIMIT 1");
$stmt->bind_param("sss", $timeOut, $studentId, $logDate);
$stmt->execute();
$stmt->close();
?>