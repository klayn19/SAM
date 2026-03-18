<?php
session_start();
require_once 'config.php';

// ── Only accept POST ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit();
}

$token        = trim($_POST['token']           ?? '');
$newPassword  = $_POST['new_password']         ?? '';
$confirmPass  = $_POST['confirm_password']     ?? '';

// ── Basic input checks ────────────────────────────────────────────────────────
if (empty($token) || empty($newPassword) || empty($confirmPass)) {
    $_SESSION['rp_message']      = 'All fields are required.';
    $_SESSION['rp_message_type'] = 'error';
    header('Location: ../resetpassword.php?token=' . urlencode($token));
    exit();
}

if ($newPassword !== $confirmPass) {
    $_SESSION['rp_message']      = 'Passwords do not match.';
    $_SESSION['rp_message_type'] = 'error';
    header('Location: ../resetpassword.php?token=' . urlencode($token));
    exit();
}

// ── Password strength validation ──────────────────────────────────────────────
function validatePassword($password) {
    if (strlen($password) < 8)
        return 'Password must be at least 8 characters long.';
    if (!preg_match('/[A-Z]/', $password))
        return 'Password must contain at least one uppercase letter.';
    if (!preg_match('/[0-9]/', $password))
        return 'Password must contain at least one number.';
    if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password))
        return 'Password must contain at least one special character (!@#$%^&*).';
    return true;
}

$validation = validatePassword($newPassword);
if ($validation !== true) {
    $_SESSION['rp_message']      = $validation;
    $_SESSION['rp_message_type'] = 'error';
    header('Location: ../resetpassword.php?token=' . urlencode($token));
    exit();
}

// ── Look up the token ─────────────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT email, expires_at, used FROM password_resets WHERE token = ? LIMIT 1");
$stmt->bind_param('s', $token);
$stmt->execute();
$result    = $stmt->get_result();
$tokenData = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$tokenData || $tokenData['used'] || time() > $tokenData['expires_at']) {
    $_SESSION['rp_message']      = 'This reset link is invalid or has expired. Please request a new one.';
    $_SESSION['rp_message_type'] = 'error';
    header('Location: ../forgotpassword.php');
    exit();
}

$email = $tokenData['email'];

// ── Update the user's password ────────────────────────────────────────────────
$passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

$upd = $conn->prepare("UPDATE users SET Password = ? WHERE LOWER(email) = LOWER(?)");
$upd->bind_param('ss', $passwordHash, $email);
$success = $upd->execute() && $upd->affected_rows > 0;
$upd->close();

if (!$success) {
    $_SESSION['rp_message']      = 'Something went wrong. Please try again.';
    $_SESSION['rp_message_type'] = 'error';
    header('Location: ../resetpassword.php?token=' . urlencode($token));
    exit();
}

// ── Mark token as used ────────────────────────────────────────────────────────
$mark = $conn->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
$mark->bind_param('s', $token);
$mark->execute();
$mark->close();

// ── Clear any login lockout for this email ────────────────────────────────────
$del = $conn->prepare("DELETE FROM login_attempts WHERE email = ?");
$del->bind_param('s', $email);
$del->execute();
$del->close();

// ── Redirect to login with success message ────────────────────────────────────
$_SESSION['login_error']  = '✔ Password reset successfully! You can now sign in with your new password.';
$_SESSION['active_form']  = 'login';
header('Location: ../index.php');
exit();
?>