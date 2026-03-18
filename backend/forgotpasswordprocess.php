<?php
session_start();
require_once 'config.php';

// ══════════════════════════════════════════════════════════════════════════════
//  ✉️  SMTP CONFIGURATION — Fill in your Gmail credentials here
// ══════════════════════════════════════════════════════════════════════════════
define('SMTP_USER',      'klaynsantos19@gmail.com'); // ← your Gmail address
define('SMTP_PASS',      'jbig stub ugqt ezrm');  // ← Gmail App Password (16 chars)
define('SMTP_FROM_NAME', 'SAM');
// ══════════════════════════════════════════════════════════════════════════════

define('TOKEN_EXPIRY_SECONDS', 3600);
define('APP_NAME', 'SAM System');
define('BASE_URL',
    (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
    . '://' . $_SERVER['HTTP_HOST']
    . rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/')
);

// ── Load PHPMailer (manually placed in SAM_system/phpmailer/) ─────────────────
$phpmailerPath = dirname(__DIR__) . '/phpmailer/';
if (!file_exists($phpmailerPath . 'PHPMailer.php')) {
    error_log('PHPMailer not found at: ' . $phpmailerPath);
    $_SESSION['fp_message']      = 'Mail system not configured. Please contact the administrator.';
    $_SESSION['fp_message_type'] = 'error';
    header('Location: ../forgotpassword.php');
    exit();
}

require_once $phpmailerPath . 'PHPMailer.php';
require_once $phpmailerPath . 'SMTP.php';
require_once $phpmailerPath . 'Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ── Auto-create password_resets table ────────────────────────────────────────
$conn->query("
    CREATE TABLE IF NOT EXISTS password_resets (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        email       VARCHAR(255) NOT NULL,
        token       VARCHAR(64)  NOT NULL UNIQUE,
        expires_at  INT          NOT NULL,
        used        TINYINT(1)   NOT NULL DEFAULT 0,
        created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_token (token),
        INDEX idx_email (email)
    )
");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../forgotpassword.php');
    exit();
}

$email = strtolower(trim($_POST['email'] ?? ''));

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['fp_message']      = 'Please enter a valid email address.';
    $_SESSION['fp_message_type'] = 'error';
    header('Location: ../forgotpassword.php');
    exit();
}

$generic_message = 'If that email is registered, a reset link has been sent. Please check your inbox (and spam folder).';

// ── Look up user ──────────────────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT ID, firstName, email FROM users WHERE LOWER(email) = ? LIMIT 1");
$stmt->bind_param('s', $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    $_SESSION['fp_message']      = $generic_message;
    $_SESSION['fp_message_type'] = 'success';
    header('Location: ../forgotpassword.php');
    exit();
}

// ── Rate-limit: max 3 per hour ────────────────────────────────────────────────
$oneHourAgo = time() - 3600;
$rateStmt   = $conn->prepare("SELECT COUNT(*) AS cnt FROM password_resets WHERE email = ? AND created_at >= FROM_UNIXTIME(?)");
$rateStmt->bind_param('si', $email, $oneHourAgo);
$rateStmt->execute();
$rateRow = $rateStmt->get_result()->fetch_assoc();
$rateStmt->close();

if ($rateRow['cnt'] >= 3) {
    $_SESSION['fp_message']      = 'Too many reset requests. Please wait an hour before trying again.';
    $_SESSION['fp_message_type'] = 'error';
    header('Location: ../forgotpassword.php');
    exit();
}

// ── Invalidate old tokens ─────────────────────────────────────────────────────
$inv = $conn->prepare("UPDATE password_resets SET used = 1 WHERE email = ? AND used = 0");
$inv->bind_param('s', $email);
$inv->execute();
$inv->close();

// ── Generate token ────────────────────────────────────────────────────────────
$token     = bin2hex(random_bytes(32));
$expiresAt = time() + TOKEN_EXPIRY_SECONDS;

$ins = $conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
$ins->bind_param('ssi', $email, $token, $expiresAt);
$ins->execute();
$ins->close();

// ── Build email ───────────────────────────────────────────────────────────────
$resetUrl    = BASE_URL . '/resetpassword.php?token=' . urlencode($token);
$firstName   = htmlspecialchars($user['firstName'], ENT_QUOTES, 'UTF-8');
$toEmail     = $user['email'];
$expiryLabel = (TOKEN_EXPIRY_SECONDS / 60) . ' minutes';

$htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8">
<style>
  body    { font-family:Arial,sans-serif; background:#f4f4f4; margin:0; padding:0; }
  .wrap   { max-width:520px; margin:40px auto; background:#fff; border-radius:10px;
            overflow:hidden; box-shadow:0 4px 20px rgba(0,0,0,.1); }
  .header { background:linear-gradient(135deg,#7b61ff,#5a40e0); padding:32px 36px;
            text-align:center; color:#fff; }
  .header h1 { margin:0; font-size:1.6rem; letter-spacing:1px; }
  .body   { padding:32px 36px; color:#333; line-height:1.7; }
  .body p { margin:0 0 16px; }
  .btn    { display:inline-block; padding:13px 28px;
            background:linear-gradient(135deg,#7b61ff,#5a40e0);
            color:#fff !important; text-decoration:none; border-radius:8px;
            font-weight:700; font-size:1rem; margin:8px 0 20px; }
  .note   { font-size:.82rem; color:#888; border-top:1px solid #eee; margin-top:24px; padding-top:16px; }
  .url-box { word-break:break-all; font-size:.78rem; background:#f7f7f7;
             border:1px solid #e0e0e0; border-radius:6px; padding:10px 12px;
             color:#555; margin-top:8px; }
</style></head>
<body>
<div class="wrap">
  <div class="header"><h1>🔐 SAM System</h1></div>
  <div class="body">
    <p>Hello, <strong>{$firstName}</strong>!</p>
    <p>We received a request to reset the password for your SAM account linked to <strong>{$toEmail}</strong>.</p>
    <p>Click the button below to choose a new password:</p>
    <p style="text-align:center;"><a href="{$resetUrl}" class="btn">Reset My Password</a></p>
    <p>This link expires in <strong>{$expiryLabel}</strong>. If you didn't request this, ignore this email — your password won't change.</p>
    <div class="note">
      If the button doesn't work, copy this URL into your browser:<br>
      <div class="url-box">{$resetUrl}</div>
    </div>
  </div>
</div>
</body></html>
HTML;

$plainBody = "Hello {$firstName},\n\nReset your SAM password:\n{$resetUrl}\n\n"
           . "Expires in {$expiryLabel}. If you didn't request this, ignore this email.\n\n— SAM System";

// ── Send via PHPMailer + Gmail SMTP ───────────────────────────────────────────
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = SMTP_PASS;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    $mail->setFrom(SMTP_USER, SMTP_FROM_NAME);
    $mail->addAddress($toEmail, $firstName);
    $mail->addReplyTo(SMTP_USER, SMTP_FROM_NAME);

    $mail->isHTML(true);
    $mail->Subject = APP_NAME . ' – Password Reset Request';
    $mail->Body    = $htmlBody;
    $mail->AltBody = $plainBody;

    $mail->send();

} catch (Exception $e) {
    error_log('PHPMailer error [' . $toEmail . ']: ' . $mail->ErrorInfo);
}

$_SESSION['fp_message']      = $generic_message;
$_SESSION['fp_message_type'] = 'success';
header('Location: ../forgotpassword.php');
exit();
?>