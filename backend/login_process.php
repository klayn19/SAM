<?php
session_start();
$_SESSION['user_id'] = $user['id'];
$_SESSION['role'] = $user['role']; 


if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    header('Location: /SAM_system/dashboard_admin.php');
    exit;
} elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'student') {
    header('Location: /SAM_system/dashboard_student.php');
    exit;
} else {
    header('Location: /SAM_system/login.php?error=role');
    exit;
}
?>