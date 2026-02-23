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
    <title>SAM LOG IN</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="CSS/style.css">
    <style>
        /* ===== Lockout Banner ===== */
        .lockout-banner {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 12px;
            color: #856404;
            font-size: 0.9rem;
        }
        .lockout-banner i {
            font-size: 1.2rem;
            color: #e67e00;
            flex-shrink: 0;
        }
        .lockout-banner strong {
            display: block;
            margin-bottom: 2px;
        }
        #lockout-timer {
            font-size: 1.5rem;
            font-weight: 700;
            color: #c0392b;
            letter-spacing: 1px;
        }
        /* Disable login button while locked */
        .login-btn-locked {
            opacity: 0.5;
            cursor: not-allowed !important;
            pointer-events: none;
        }
        /* Attempt warning colours for error-message */
        .error-message {
            color: #c0392b;
        }
    </style>
</head>
<body>

    <div class="login-container">
        <!-- Login Form -->
        <div class="form-box <?= isActive('login', $active_form) ?>" id="login-form">
            <h2>Sign in</h2>

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
                    <h1>Register to experience SAM</h1>
                    <p>Join SAM and experience smarter, seamless attendance tracking.</p>
                    <button class="ghost" onclick="showForm('login-form')">Sign In</button>
                </div>
                <div class="overlay-panel overlay-right">
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
                        banner.style.background = '#d4edda';
                        banner.style.borderColor = '#28a745';
                        banner.style.color       = '#155724';
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