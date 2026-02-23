<?php
session_start();

$message = $_SESSION['fp_message'] ?? '';
$message_type = $_SESSION['fp_message_type'] ?? 'info'; // 'success' | 'error' | 'info'
unset($_SESSION['fp_message'], $_SESSION['fp_message_type']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SAM – Forgot Password</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="CSS/style.css">
    <style>
        /* ── Page wrapper ── */
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: var(--bg, #1a1a2e);
        }

        .fp-card {
            background: var(--card-bg, #16213e);
            border-radius: 16px;
            padding: 40px 36px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 8px 32px rgba(0,0,0,.35);
            color: var(--text, #eee);
            animation: fadeIn .4s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .fp-card .logo-row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 24px;
        }

        .fp-card .logo-row i {
            font-size: 1.8rem;
            color: #7b61ff;
        }

        .fp-card h2 {
            margin: 0 0 6px;
            font-size: 1.5rem;
        }

        .fp-card p.subtitle {
            margin: 0 0 24px;
            font-size: 0.875rem;
            opacity: .7;
            line-height: 1.5;
        }

        /* ── Alert banners ── */
        .alert {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            border-radius: 8px;
            padding: 12px 14px;
            margin-bottom: 20px;
            font-size: 0.875rem;
            line-height: 1.5;
        }
        .alert i { margin-top: 2px; flex-shrink: 0; }

        .alert-success { background: #1a3a2a; border: 1px solid #2ecc71; color: #2ecc71; }
        .alert-error   { background: #3a1a1a; border: 1px solid #e74c3c; color: #e74c3c; }
        .alert-info    { background: #1a2a3a; border: 1px solid #3498db; color: #3498db; }

        /* ── Form ── */
        .fp-card label {
            display: block;
            font-size: 0.8rem;
            letter-spacing: .5px;
            margin-bottom: 6px;
            opacity: .8;
            text-transform: uppercase;
        }

        .fp-card .input-group {
            position: relative;
            margin-bottom: 20px;
        }

        .fp-card .input-group i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #7b61ff;
            pointer-events: none;
        }

        .fp-card input[type="email"] {
            width: 100%;
            padding: 12px 14px 12px 40px;
            border-radius: 8px;
            border: 1px solid rgba(255,255,255,.15);
            background: rgba(255,255,255,.06);
            color: inherit;
            font-size: 0.95rem;
            box-sizing: border-box;
            transition: border-color .2s;
            outline: none;
        }

        .fp-card input[type="email"]:focus {
            border-color: #7b61ff;
            background: rgba(123,97,255,.08);
        }

        /* ── Submit button ── */
        .fp-card .btn-primary {
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

        .fp-card .btn-primary:hover  { opacity: .9; }
        .fp-card .btn-primary:active { transform: scale(.98); }
        .fp-card .btn-primary:disabled { opacity: .55; cursor: not-allowed; }

        /* ── Back link ── */
        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            font-size: 0.875rem;
            color: #7b61ff;
            text-decoration: none;
            opacity: .85;
        }
        .back-link:hover { opacity: 1; text-decoration: underline; }

        /* ── Spinner ── */
        .spinner {
            display: none;
            width: 18px;
            height: 18px;
            border: 2px solid rgba(255,255,255,.3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin .6s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
<div class="fp-card">

    <div class="logo-row">
        <i class="fas fa-user-shield"></i>
        <span style="font-size:1.25rem; font-weight:700; letter-spacing:1px;">SAM</span>
    </div>

    <h2>Forgot Password?</h2>
    <p class="subtitle">
        Enter the email address linked to your account and we'll send you a
        password-reset link.
    </p>

    <?php if ($message): ?>
    <div class="alert alert-<?= htmlspecialchars($message_type, ENT_QUOTES, 'UTF-8') ?>">
        <i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : ($message_type === 'error' ? 'exclamation-circle' : 'info-circle') ?>"></i>
        <span><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></span>
    </div>
    <?php endif; ?>

    <form action="backend/forgotpasswordprocess.php" method="post" id="fpForm">
        <label for="fp-email">Your Email Address</label>
        <div class="input-group">
            <i class="fas fa-envelope"></i>
            <input type="email" id="fp-email" name="email"
                   placeholder="you@example.com" required autocomplete="email">
        </div>
        <button type="submit" class="btn-primary" id="fp-submit">
            <span id="btn-text"><i class="fas fa-paper-plane"></i> Send Reset Link</span>
            <span class="spinner" id="btn-spinner"></span>
        </button>
    </form>

    <a href="index.php" class="back-link">
        <i class="fas fa-arrow-left"></i> Back to Sign In
    </a>

</div>

<script>
    document.getElementById('fpForm').addEventListener('submit', function () {
        const btn     = document.getElementById('fp-submit');
        const text    = document.getElementById('btn-text');
        const spinner = document.getElementById('btn-spinner');
        btn.disabled       = true;
        text.style.display = 'none';
        spinner.style.display = 'block';
    });
</script>
</body>
</html>