<?php
session_start();
require_once 'backend/config.php';

// ── Ensure table exists (safe guard) ─────────────────────────────────────────
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

$token     = trim($_GET['token'] ?? '');
$tokenData = null;
$tokenError = '';

if (empty($token)) {
    $tokenError = 'No reset token provided.';
} else {
    $stmt = $conn->prepare("SELECT email, expires_at, used FROM password_resets WHERE token = ? LIMIT 1");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $result    = $stmt->get_result();
    $tokenData = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$tokenData) {
        $tokenError = 'This reset link is invalid or has already been used.';
    } elseif ($tokenData['used']) {
        $tokenError = 'This reset link has already been used. Please request a new one.';
    } elseif (time() > $tokenData['expires_at']) {
        $tokenError = 'This reset link has expired. Please request a new one.';
    }
}

// ── Pull flash messages ───────────────────────────────────────────────────────
$message      = $_SESSION['rp_message']      ?? '';
$message_type = $_SESSION['rp_message_type'] ?? 'info';
unset($_SESSION['rp_message'], $_SESSION['rp_message_type']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SAM – Reset Password</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="CSS/style.css">
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: var(--bg, #1a1a2e);
        }

        .rp-card {
            background: var(--card-bg, #16213e);
            border-radius: 16px;
            padding: 40px 36px;
            width: 100%;
            max-width: 440px;
            box-shadow: 0 8px 32px rgba(0,0,0,.35);
            color: var(--text, #eee);
            animation: fadeIn .4s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .logo-row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 24px;
        }

        .logo-row i   { font-size: 1.8rem; color: #7b61ff; }
        .logo-row span{ font-size: 1.25rem; font-weight: 700; letter-spacing: 1px; }

        h2            { margin: 0 0 6px; font-size: 1.5rem; }

        p.subtitle {
            margin: 0 0 24px;
            font-size: 0.875rem;
            opacity: .7;
            line-height: 1.5;
        }

        /* ── Alert ── */
        .alert {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            border-radius: 8px;
            padding: 12px 14px;
            margin-bottom: 20px;
            font-size: .875rem;
            line-height: 1.5;
        }
        .alert i { margin-top: 2px; flex-shrink: 0; }
        .alert-success { background:#1a3a2a; border:1px solid #2ecc71; color:#2ecc71; }
        .alert-error   { background:#3a1a1a; border:1px solid #e74c3c; color:#e74c3c; }
        .alert-info    { background:#1a2a3a; border:1px solid #3498db; color:#3498db; }

        /* ── Invalid-token state ── */
        .token-error-box {
            text-align: center;
            padding: 10px 0 20px;
        }
        .token-error-box i {
            font-size: 3rem;
            color: #e74c3c;
            margin-bottom: 16px;
        }
        .token-error-box p { opacity: .8; line-height: 1.6; }

        /* ── Form inputs ── */
        label {
            display: block;
            font-size: .8rem;
            letter-spacing: .5px;
            margin-bottom: 6px;
            opacity: .8;
            text-transform: uppercase;
        }

        .input-group {
            position: relative;
            margin-bottom: 18px;
        }

        .input-group > i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #7b61ff;
            pointer-events: none;
        }

        .input-group input {
            width: 100%;
            padding: 12px 44px 12px 40px;
            border-radius: 8px;
            border: 1px solid rgba(255,255,255,.15);
            background: rgba(255,255,255,.06);
            color: inherit;
            font-size: .95rem;
            box-sizing: border-box;
            transition: border-color .2s;
            outline: none;
        }

        .input-group input:focus {
            border-color: #7b61ff;
            background: rgba(123,97,255,.08);
        }

        .toggle-eye {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: rgba(255,255,255,.4);
            padding: 4px;
            transition: color .2s;
        }
        .toggle-eye:hover { color: #7b61ff; }

        /* ── Strength meter ── */
        .strength-bar-wrap {
            height: 5px;
            background: rgba(255,255,255,.1);
            border-radius: 4px;
            margin: -10px 0 14px;
            overflow: hidden;
        }
        .strength-bar {
            height: 100%;
            border-radius: 4px;
            width: 0;
            transition: width .3s, background .3s;
        }

        /* ── Requirements list ── */
        .req-list {
            list-style: none;
            padding: 0;
            margin: 0 0 20px;
            font-size: .8rem;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4px 10px;
        }
        .req-list li { display: flex; align-items: center; gap: 6px; opacity: .6; transition: opacity .2s, color .2s; }
        .req-list li.valid   { color: #2ecc71; opacity: 1; }
        .req-list li.invalid { color: #e74c3c; opacity: 1; }

        /* ── Submit button ── */
        .btn-primary {
            width: 100%;
            padding: 13px;
            border: none;
            border-radius: 8px;
            background: linear-gradient(135deg, #7b61ff, #5a40e0);
            color: #fff;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: opacity .2s, transform .1s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-primary:hover   { opacity: .9; }
        .btn-primary:active  { transform: scale(.98); }
        .btn-primary:disabled{ opacity: .5; cursor: not-allowed; }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            font-size: .875rem;
            color: #7b61ff;
            text-decoration: none;
            opacity: .85;
        }
        .back-link:hover { opacity: 1; text-decoration: underline; }

        /* ── Match indicator ── */
        .match-hint {
            font-size: .78rem;
            margin: -12px 0 14px 2px;
            min-height: 16px;
            transition: color .2s;
        }

        /* ── Spinner ── */
        .spinner {
            display: none;
            width: 18px; height: 18px;
            border: 2px solid rgba(255,255,255,.3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin .6s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
<div class="rp-card">

    <div class="logo-row">
        <i class="fas fa-user-shield"></i>
        <span>SAM</span>
    </div>

    <?php if ($tokenError): ?>
    <!-- ── Invalid / expired token ── -->
    <h2>Link Invalid</h2>
    <div class="token-error-box">
        <i class="fas fa-exclamation-triangle"></i>
        <p><?= htmlspecialchars($tokenError, ENT_QUOTES, 'UTF-8') ?></p>
    </div>
    <a href="forgotpassword.php" class="btn-primary" style="text-decoration:none; margin-bottom:12px;">
        <i class="fas fa-redo"></i> Request a New Link
    </a>

    <?php else: ?>
    <!-- ── Valid token – show form ── -->
    <h2>Reset Your Password</h2>
    <p class="subtitle">Choose a strong new password for your account.</p>

    <?php if ($message): ?>
    <div class="alert alert-<?= htmlspecialchars($message_type, ENT_QUOTES, 'UTF-8') ?>">
        <i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
        <span><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></span>
    </div>
    <?php endif; ?>

    <form action="backend/resetpasswordprocess.php" method="post" id="rpForm"
          autocomplete="off" novalidate>
        <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">

        <label for="new-password">New Password</label>
        <div class="input-group">
            <i class="fas fa-lock"></i>
            <input type="password" id="new-password" name="new_password"
                   placeholder="Enter new password" required autocomplete="new-password">
            <button type="button" class="toggle-eye"
                    onclick="togglePwd('new-password', this)" aria-label="Show password">
                <i class="fas fa-eye"></i>
            </button>
        </div>
        <div class="strength-bar-wrap">
            <div class="strength-bar" id="strength-bar"></div>
        </div>

        <ul class="req-list" id="req-list">
            <li id="r-len"><i class="fas fa-circle"></i> 8+ characters</li>
            <li id="r-upper"><i class="fas fa-circle"></i> Uppercase letter</li>
            <li id="r-num"><i class="fas fa-circle"></i> Number</li>
            <li id="r-special"><i class="fas fa-circle"></i> Special character</li>
        </ul>

        <label for="confirm-password">Confirm New Password</label>
        <div class="input-group">
            <i class="fas fa-lock"></i>
            <input type="password" id="confirm-password" name="confirm_password"
                   placeholder="Repeat new password" required autocomplete="new-password">
            <button type="button" class="toggle-eye"
                    onclick="togglePwd('confirm-password', this)" aria-label="Show password">
                <i class="fas fa-eye"></i>
            </button>
        </div>
        <div class="match-hint" id="match-hint"></div>

        <button type="submit" class="btn-primary" id="rp-submit">
            <span id="btn-text"><i class="fas fa-key"></i> Reset Password</span>
            <span class="spinner" id="btn-spinner"></span>
        </button>
    </form>
    <?php endif; ?>

    <a href="index.php" class="back-link">
        <i class="fas fa-arrow-left"></i> Back to Sign In
    </a>

</div>

<script>
// ── Toggle password visibility ────────────────────────────────────────────────
function togglePwd(id, btn) {
    const input = document.getElementById(id);
    const icon  = btn.querySelector('i');
    const show  = input.type === 'password';
    input.type  = show ? 'text' : 'password';
    icon.className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
    btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
}

// ── Requirement helpers ───────────────────────────────────────────────────────
const rLen     = document.getElementById('r-len');
const rUpper   = document.getElementById('r-upper');
const rNum     = document.getElementById('r-num');
const rSpecial = document.getElementById('r-special');
const bar      = document.getElementById('strength-bar');
const matchHint = document.getElementById('match-hint');
const pwdInput  = document.getElementById('new-password');
const cfmInput  = document.getElementById('confirm-password');
const submitBtn = document.getElementById('rp-submit');

function setReq(el, valid) {
    el.classList.toggle('valid',   valid);
    el.classList.toggle('invalid', !valid);
    el.querySelector('i').className = valid ? 'fas fa-check-circle' : 'fas fa-circle';
}

function checkStrength(pwd) {
    const checks = [
        pwd.length >= 8,
        /[A-Z]/.test(pwd),
        /[0-9]/.test(pwd),
        /[!@#$%^&*(),.?":{}|<>]/.test(pwd)
    ];
    const score = checks.filter(Boolean).length;
    const colours = ['#e74c3c', '#e67e22', '#f1c40f', '#2ecc71'];
    bar.style.width      = (score * 25) + '%';
    bar.style.background = score > 0 ? colours[score - 1] : 'transparent';
    return checks;
}

function validateAll() {
    const pwd = pwdInput ? pwdInput.value : '';
    const cfm = cfmInput ? cfmInput.value : '';
    const checks = checkStrength(pwd);
    setReq(rLen,     checks[0]);
    setReq(rUpper,   checks[1]);
    setReq(rNum,     checks[2]);
    setReq(rSpecial, checks[3]);

    const allPass = checks.every(Boolean);
    const matches = pwd === cfm && cfm.length > 0;

    if (cfm.length > 0) {
        matchHint.textContent = matches ? '✔ Passwords match' : '✖ Passwords do not match';
        matchHint.style.color = matches ? '#2ecc71' : '#e74c3c';
    } else {
        matchHint.textContent = '';
    }

    if (submitBtn) submitBtn.disabled = !(allPass && matches);
}

if (pwdInput)  pwdInput.addEventListener('input', validateAll);
if (cfmInput)  cfmInput.addEventListener('input', validateAll);

// Disable submit initially
if (submitBtn) submitBtn.disabled = true;

// ── Loading spinner on submit ─────────────────────────────────────────────────
const rpForm = document.getElementById('rpForm');
if (rpForm) {
    rpForm.addEventListener('submit', function (e) {
        const pwd = pwdInput ? pwdInput.value : '';
        const cfm = cfmInput ? cfmInput.value : '';
        const checks = [
            pwd.length >= 8,
            /[A-Z]/.test(pwd),
            /[0-9]/.test(pwd),
            /[!@#$%^&*(),.?":{}|<>]/.test(pwd),
            pwd === cfm
        ];

        if (!checks.every(Boolean)) {
            e.preventDefault();
            validateAll();
            return;
        }

        const btn     = document.getElementById('rp-submit');
        const text    = document.getElementById('btn-text');
        const spinner = document.getElementById('btn-spinner');
        if (btn && text && spinner) {
            btn.disabled          = true;
            text.style.display    = 'none';
            spinner.style.display = 'block';
        }
    });
}
</script>
</body>
</html>