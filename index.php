<?php
session_start();

$errors = [
    'login'    => $_SESSION['login_error']    ?? '',
    'register' => $_SESSION['register_error'] ?? ''
];
$active_form   = $_SESSION['active_form']   ?? 'login';
$lockout_until = $_SESSION['lockout_until'] ?? 0;   // Unix timestamp
$lockout_email = $_SESSION['lockout_email'] ?? '';

unset(
    $_SESSION['login_error'],
    $_SESSION['register_error'],
    $_SESSION['active_form'],
    $_SESSION['lockout_until'],
    $_SESSION['lockout_email']
);

function showerror($error) {
    return !empty($error)
        ? '<p class="error-message">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</p>'
        : '';
}

function isActive($form, $active) {
    return $form === $active ? 'active' : '';
}

// Seconds remaining for lockout (0 = not locked)
$lockout_seconds = ($lockout_until > time()) ? ($lockout_until - time()) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SAM — Sign In</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap');

        :root {
            --bg:        #0d0f14;
            --surface:   #151820;
            --surface2:  #1c2030;
            --border:    rgba(255,255,255,.07);
            --accent:    #00b2ff;
            --accent2:   #7b5cff;
            --danger:    #ff4d6d;
            --success:   #36d68a;
            --warning:   #f5a623;
            --text:      #e8ecf5;
            --muted:     #6b7591;
            --radius:    12px;
            --grad:      linear-gradient(135deg, #00b2ff, #7b5cff);
            --font-head: 'Syne', sans-serif;
            --font-body: 'DM Sans', sans-serif;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: var(--font-body);
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        /* ── Main container ── */
        .login-container {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 20px;
            box-shadow: 0 24px 64px rgba(0,0,0,.6);
            position: relative;
            overflow: hidden;
            width: 100%;
            max-width: 900px;
            min-height: 600px;
            display: flex;
        }

        /* ── Form boxes ── */
        .form-box {
            position: absolute;
            width: 50%;
            height: 100%;
            padding: 56px 44px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            opacity: 0;
            visibility: hidden;
            transition: opacity .6s ease, visibility .6s ease;
            z-index: 1;
            background: var(--surface);
        }

        .form-box.active {
            opacity: 1;
            visibility: visible;
            z-index: 5;
            animation: slideIn .6s ease;
        }

        @keyframes slideIn {
            from { transform: translateX(-40px); opacity: 0; }
            to   { transform: translateX(0);     opacity: 1; }
        }

        .form-box.active#register-form {
            animation: slideInRight .6s ease;
        }

        @keyframes slideInRight {
            from { transform: translateX(40px); opacity: 0; }
            to   { transform: translateX(0);    opacity: 1; }
        }

        #login-form    { left: 0; }
        #register-form { right: 0; }

        /* ── Heading ── */
        .form-box h2 {
            font-family: var(--font-head);
            font-size: 28px;
            font-weight: 800;
            color: var(--text);
            margin-bottom: 6px;
        }

        .form-subtitle {
            font-size: 13px;
            color: var(--muted);
            margin-bottom: 28px;
        }

        /* ── Inputs ── */
        .form-box form {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .form-box input[type="email"],
        .form-box input[type="text"],
        .form-box input[type="password"] {
            background: var(--surface2);
            border: 1px solid var(--border);
            color: var(--text);
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 14px;
            font-family: var(--font-body);
            transition: border-color .2s;
            outline: none;
            width: 100%;
        }

        .form-box input::placeholder { color: var(--muted); }

        .form-box input:focus {
            border-color: var(--accent);
            background: #1e2436;
        }

        /* ── Submit button ── */
        .form-box button[type="submit"] {
            background: var(--grad);
            color: #fff;
            border: none;
            padding: 13px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            font-family: var(--font-head);
            letter-spacing: .06em;
            text-transform: uppercase;
            cursor: pointer;
            margin-top: 6px;
            transition: opacity .2s, transform .1s;
        }

        .form-box button[type="submit"]:hover  { opacity: .88; }
        .form-box button[type="submit"]:active { transform: scale(.98); }
        .form-box button[type="submit"]:disabled {
            opacity: .4;
            cursor: not-allowed;
        }

        /* ── Forgot link ── */
        .forgot-password {
            color: var(--accent);
            font-size: 12px;
            text-decoration: none;
            font-weight: 600;
            transition: color .2s;
            margin-top: 2px;
        }
        .forgot-password:hover { color: var(--accent2); }

        /* ── Password toggle ── */
        .password-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .password-wrapper input {
            width: 100%;
            margin: 0 !important;
            padding-right: 46px !important;
        }

        .toggle-password {
            position: absolute;
            right: 14px;
            background: none !important;
            border: none !important;
            cursor: pointer;
            color: var(--muted);
            font-size: 14px;
            padding: 0 !important;
            margin: 0 !important;
            display: flex;
            align-items: center;
            box-shadow: none !important;
            transform: none !important;
            transition: color .2s;
        }
        .toggle-password:hover { color: var(--accent) !important; }

        /* ── Overlay (right panel) ── */
        .overlay-container {
            position: absolute;
            top: 0;
            left: 50%;
            width: 50%;
            height: 100%;
            overflow: hidden;
            z-index: 10;
            transition: transform .6s ease;
        }

        .overlay {
            background: var(--grad);
            position: relative;
            left: -100%;
            height: 100%;
            width: 200%;
            transform: translateX(0);
            transition: transform .6s ease;
            display: flex;
            align-items: center;
            justify-content: space-around;
        }

        .overlay-panel {
            position: absolute;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px;
            text-align: center;
            top: 0;
            height: 100%;
            width: 50%;
        }

        .overlay-left  { left: 0;  transform: translateX(-20%); }
        .overlay-right { right: 0; transform: translateX(0); }

        #register-form.active ~ .overlay-container                          { transform: translateX(-100%); }
        #register-form.active ~ .overlay-container .overlay                 { transform: translateX(50%); }
        #register-form.active ~ .overlay-container .overlay-left            { transform: translateX(0); }
        #register-form.active ~ .overlay-container .overlay-right           { transform: translateX(20%); }

        .overlay h1 {
            font-family: var(--font-head);
            font-size: 30px;
            font-weight: 800;
            color: #fff;
            margin-bottom: 14px;
            text-shadow: 0 2px 8px rgba(0,0,0,.2);
        }

        .overlay p {
            font-size: 13px;
            line-height: 1.7;
            color: rgba(255,255,255,.85);
            margin-bottom: 28px;
        }

        /* SAM logo mark in overlay */
        .overlay-logo {
            font-family: var(--font-head);
            font-size: 13px;
            font-weight: 800;
            letter-spacing: .15em;
            color: rgba(255,255,255,.5);
            text-transform: uppercase;
            margin-bottom: 28px;
        }

        .ghost {
            background: transparent;
            border: 2px solid rgba(255,255,255,.7);
            color: #fff;
            padding: 11px 36px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
            font-family: var(--font-head);
            letter-spacing: .08em;
            text-transform: uppercase;
            cursor: pointer;
            transition: all .25s;
        }
        .ghost:hover {
            background: rgba(255,255,255,.15);
            border-color: #fff;
            transform: translateY(-2px);
        }

        /* ── Error / Success messages ── */
        .error-message {
            background: rgba(255,77,109,.1);
            border: 1px solid var(--danger);
            color: var(--danger);
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 6px;
        }

        /* ── Lockout banner ── */
        .lockout-banner {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            background: rgba(245,166,35,.1);
            border: 1px solid var(--warning);
            border-radius: 8px;
            padding: 14px 16px;
            margin-bottom: 12px;
            color: var(--warning);
            font-size: 13px;
        }
        .lockout-banner i { font-size: 1.1rem; flex-shrink: 0; margin-top: 2px; }
        .lockout-banner strong { display: block; margin-bottom: 3px; font-weight: 700; }

        #lockout-timer {
            font-family: var(--font-head);
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--danger);
            letter-spacing: 2px;
            margin-top: 4px;
        }

        .login-btn-locked { opacity: .4 !important; cursor: not-allowed !important; pointer-events: none; }

        /* ── Password requirements ── */
        .password-requirements {
            font-size: 11px;
            padding: 10px 14px;
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 8px;
            display: none;
        }

        .password-requirements ul { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 5px; }
        .password-requirements li { display: flex; align-items: center; gap: 8px; color: var(--muted); }
        .password-requirements li.valid   { color: var(--success); }
        .password-requirements li.invalid { color: var(--danger); }
        .password-requirements li i { font-size: 10px; width: 12px; }

        /* ── Responsive ── */
        @media (max-width: 768px) {
            .login-container { min-height: 100vh; border-radius: 0; flex-direction: column; }
            .form-box { position: static; width: 100%; opacity: 0; visibility: hidden; min-height: 100vh; }
            .form-box.active { opacity: 1; visibility: visible; }
            .overlay-container { display: none; }
            .form-box h2 { font-size: 24px; }
        }
    </style>
</head>
<body>

    <div class="login-container">
        <!-- Login Form -->
        <div class="form-box <?= isActive('login', $active_form) ?>" id="login-form">
            <h2>Sign in</h2>
            <p class="form-subtitle">Welcome back to SAM</p>

            <?php if ($lockout_seconds > 0): ?>
            <!-- Lockout Banner (shown when account is suspended) -->
            <div class="lockout-banner" id="lockout-banner">
                <i class="fas fa-lock"></i>
                <div>
                    <strong>Account Temporarily Locked</strong>
                    Too many failed login attempts. Please wait:
                    <div id="lockout-timer"></div>
                    before trying again.
                </div>
            </div>
            <?php else: ?>
            <?= showerror($errors['login']) ?>
            <?php endif; ?>

            <form action="backend/login_register.php" method="post" id="login-form-element">
                <input type="email" name="email" placeholder="Email" required autocomplete="email"
                       value="<?= htmlspecialchars($lockout_email, ENT_QUOTES, 'UTF-8') ?>">
                <div class="password-wrapper">
                    <input type="password" name="password" id="login-password" placeholder="Password"
                           required autocomplete="current-password">
                    <button type="button" class="toggle-password"
                            onclick="togglePassword('login-password', this)"
                            tabindex="-1" aria-label="Show password">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <a href="forgotpassword.php" class="forgot-password">Forgot your password?</a>
                <button type="submit" name="login" id="login-submit-btn"
                        <?= $lockout_seconds > 0 ? 'disabled' : '' ?>>Sign In</button>
            </form>
        </div>

        <!-- Register Form -->
        <div class="form-box <?= isActive('register', $active_form) ?>" id="register-form">
            <h2>Register</h2>
            <p class="form-subtitle">Create your SAM account</p>
            <?= showerror($errors['register']) ?>
            <form action="backend/login_register.php" method="post" id="register-form-element">
                <input type="text" name="firstName" placeholder="First Name" required autocomplete="given-name">
                <input type="text" name="middleName" placeholder="Middle Name" required autocomplete="additional-name">
                <input type="text" name="lastName" placeholder="Last Name" required autocomplete="family-name">
                <input type="text" name="contactNumber" placeholder="Contact Number" required>
                <input type="email" name="email" placeholder="Email" required autocomplete="email">
                <div class="password-wrapper">
                    <input type="password" name="password" id="register-password" placeholder="Password"
                           required autocomplete="new-password">
                    <button type="button" class="toggle-password"
                            onclick="togglePassword('register-password', this)"
                            tabindex="-1" aria-label="Show password">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <div class="password-requirements" id="password-requirements">
                    <ul>
                        <li id="length-req"><i class="fas fa-circle"></i>At least 8 characters</li>
                        <li id="uppercase-req"><i class="fas fa-circle"></i>One uppercase letter</li>
                        <li id="number-req"><i class="fas fa-circle"></i>One number</li>
                        <li id="special-req"><i class="fas fa-circle"></i>One special character (!@#$%^&*)</li>
                    </ul>
                </div>
                <button type="submit" name="register">Sign Up</button>
            </form>
        </div>

        <!-- Right Panel Overlay -->
        <div class="overlay-container">
            <div class="overlay">
                <div class="overlay-panel overlay-left">
                    <div class="overlay-logo">SAM</div>
                    <h1>Register to experience SAM</h1>
                    <p>Join SAM and experience smarter, seamless attendance tracking.</p>
                    <button class="ghost" onclick="showForm('login-form')">Sign In</button>
                </div>
                <div class="overlay-panel overlay-right">
                    <div class="overlay-logo">SAM</div>
                    <h1>Hello, Welcome to SAM!</h1>
                    <p>Track smarter. Attend better. Let SAM simplify your day</p>
                    <button class="ghost" onclick="showForm('register-form')">Sign Up</button>
                </div>
            </div>
        </div>
    </div>

    <script src="script.js"></script>
    <script>
        // ========== SHOW / HIDE PASSWORD ==========
        function togglePassword(inputId, btn) {
            const input = document.getElementById(inputId);
            const icon = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
                btn.setAttribute('aria-label', 'Hide password');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
                btn.setAttribute('aria-label', 'Show password');
            }
        }

        // ========== LOCKOUT COUNTDOWN TIMER ==========
        (function () {
            const secondsRemaining = <?= (int)$lockout_seconds ?>;
            if (secondsRemaining <= 0) return;

            const timerEl  = document.getElementById('lockout-timer');
            const submitBtn = document.getElementById('login-submit-btn');
            const banner   = document.getElementById('lockout-banner');

            let remaining = secondsRemaining;

            function formatTime(s) {
                const m = Math.floor(s / 60);
                const sec = s % 60;
                return (m > 0 ? m + 'm ' : '') + sec + 's';
            }

            function tick() {
                if (timerEl) timerEl.textContent = formatTime(remaining);

                if (remaining <= 0) {
                    // Unlock the button & hide the banner
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.classList.remove('login-btn-locked');
                    }
                    if (banner) {
                        banner.innerHTML = '<i class="fas fa-unlock"></i><div><strong>Account Unlocked</strong>You may now try logging in again.</div>';
                        banner.style.background   = 'rgba(54,214,138,.1)';
                        banner.style.borderColor  = '#36d68a';
                        banner.style.color        = '#36d68a';
                    }
                    return; // stop ticking
                }
                remaining--;
                setTimeout(tick, 1000);
            }

            tick(); // start immediately
        })();

        // ========== PASSWORD VALIDATION ==========
        const registerPassword = document.getElementById('register-password');
        const requirements = document.getElementById('password-requirements');
        const lengthReq    = document.getElementById('length-req');
        const uppercaseReq = document.getElementById('uppercase-req');
        const numberReq    = document.getElementById('number-req');
        const specialReq   = document.getElementById('special-req');
        const registerForm = document.getElementById('register-form-element');

        registerPassword.addEventListener('focus', function () {
            requirements.style.display = 'block';
        });

        document.addEventListener('click', function (e) {
            if (e.target !== registerPassword && !requirements.contains(e.target)) {
                const password = registerPassword.value;
                if (
                    password.length >= 8 &&
                    /[A-Z]/.test(password) &&
                    /[0-9]/.test(password) &&
                    /[!@#$%^&*(),.?":{}|<>_-]/.test(password)
                ) {
                    requirements.style.display = 'none';
                }
            }
        });

        registerPassword.addEventListener('input', function () {
            const password = this.value;

            function setReq(el, valid) {
                el.classList.toggle('valid', valid);
                el.classList.toggle('invalid', !valid);
                el.querySelector('i').className = valid ? 'fas fa-check' : 'fas fa-circle';
            }

            setReq(lengthReq,    password.length >= 8);
            setReq(uppercaseReq, /[A-Z]/.test(password));
            setReq(numberReq,    /[0-9]/.test(password));
            setReq(specialReq,   /[!@#$%^&*(),.?":{}|<>]/.test(password));
        });

        registerForm.addEventListener('submit', function (e) {
            const password = registerPassword.value;
            if (password.length < 8) {
                e.preventDefault(); alert('Password must be at least 8 characters long.');
                registerPassword.focus(); requirements.style.display = 'block'; return false;
            }
            if (!/[A-Z]/.test(password)) {
                e.preventDefault(); alert('Password must contain at least one uppercase letter.');
                registerPassword.focus(); requirements.style.display = 'block'; return false;
            }
            if (!/[0-9]/.test(password)) {
                e.preventDefault(); alert('Password must contain at least one number.');
                registerPassword.focus(); requirements.style.display = 'block'; return false;
            }
            if (!/[!@#$%^&*(),.?":{}|<>]/.test(password)) {
                e.preventDefault(); alert('Password must contain at least one special character (!@#$%^&*).');
                registerPassword.focus(); requirements.style.display = 'block'; return false;
            }
        });
    </script>
</body>
</html>