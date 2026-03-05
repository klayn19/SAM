<?php
session_start();
require_once 'config.php';

// ========== LOGIN ATTEMPT CONSTANTS ==========
define('MAX_LOGIN_ATTEMPTS', 3);
define('LOCKOUT_DURATION', 180); // 3 minutes in seconds

// Password validation function
function validatePassword($password) {
    if (strlen($password) < 8) {
        return "Password must be at least 8 characters long.";
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return "Password must contain at least one uppercase letter.";
    }
    if (!preg_match('/[0-9]/', $password)) {
        return "Password must contain at least one number.";
    }
    if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
        return "Password must contain at least one special character (!@#$%^&*).";
    }
    return true;
}

// ========== HELPER: Get login attempt record for an email ==========
function getLoginAttempts($conn, $email) {
    $stmt = $conn->prepare("SELECT attempts, locked_until FROM login_attempts WHERE email = ? LIMIT 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();
    return $row;
}

// ========== HELPER: Record a failed attempt ==========
function recordFailedAttempt($conn, $email) {
    $now = time();
    $lockUntil = null;

    // Upsert: insert or increment
    $stmt = $conn->prepare("
        INSERT INTO login_attempts (email, attempts, last_attempt, locked_until)
        VALUES (?, 1, ?, NULL)
        ON DUPLICATE KEY UPDATE
            attempts = attempts + 1,
            last_attempt = VALUES(last_attempt),
            locked_until = IF(attempts + 1 >= ?, ?, locked_until)
    ");
    $lockTime = $now + LOCKOUT_DURATION;
    $maxAttempts = MAX_LOGIN_ATTEMPTS;
    $stmt->bind_param('siii', $email, $now, $maxAttempts, $lockTime);
    $stmt->execute();
    $stmt->close();
}

// ========== HELPER: Reset attempts on successful login ==========
function resetLoginAttempts($conn, $email) {
    $stmt = $conn->prepare("DELETE FROM login_attempts WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->close();
}

// ========== HELPER: Check if account is locked ==========
// Returns seconds remaining if locked, 0 if not locked
function getLockoutSecondsRemaining($conn, $email) {
    $record = getLoginAttempts($conn, $email);
    if (!$record) return 0;
    if ($record['locked_until'] && time() < $record['locked_until']) {
        return $record['locked_until'] - time();
    }
    // Lockout expired — clean up
    if ($record['locked_until'] && time() >= $record['locked_until']) {
        $stmt = $conn->prepare("DELETE FROM login_attempts WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->close();
    }
    return 0;
}

// ========== HELPER: Get remaining attempts ==========
function getRemainingAttempts($conn, $email) {
    $record = getLoginAttempts($conn, $email);
    if (!$record) return MAX_LOGIN_ATTEMPTS;
    return max(0, MAX_LOGIN_ATTEMPTS - $record['attempts']);
}

// ========= ENSURE login_attempts TABLE EXISTS ==========
$conn->query("
    CREATE TABLE IF NOT EXISTS login_attempts (
        email VARCHAR(255) PRIMARY KEY,
        attempts INT NOT NULL DEFAULT 0,
        last_attempt INT DEFAULT NULL,
        locked_until INT DEFAULT NULL
    )
");

// ==================== REGISTER ====================
if (isset($_POST['register'])) {
    $firstName     = trim($_POST['firstName']     ?? '');
    $middleName    = trim($_POST['middleName']    ?? '');
    $lastName      = trim($_POST['lastName']      ?? '');
    $email         = trim($_POST['email']         ?? '');
    $contactNumber = trim($_POST['contactNumber'] ?? '');
    $password      = $_POST['password']           ?? '';

    $passwordValidation = validatePassword($password);
    if ($passwordValidation !== true) {
        $_SESSION['register_error'] = $passwordValidation;
        $_SESSION['active_form'] = 'register';
        header("Location: ../index.php");
        exit();
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    if ($stmt = $conn->prepare("SELECT 1 FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1")) {
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $_SESSION['register_error'] = "Email already exists.";
            $_SESSION['active_form'] = 'register';
        } else {
            if ($insert = $conn->prepare("INSERT INTO users (firstName, middleName, lastName, email, contactNumber, Password, userType) VALUES (?, ?, ?, ?, ?, ?, 'student')")) {
                $insert->bind_param('ssssss', $firstName, $middleName, $lastName, $email, $contactNumber, $passwordHash);
                if ($insert->execute()) {
                    $_SESSION['login_error'] = "Registration successful. Please log in.";
                    $_SESSION['active_form'] = 'login';
                } else {
                    $_SESSION['register_error'] = "Registration failed. Please try again.";
                    $_SESSION['active_form'] = 'register';
                }
                $insert->close();
            } else {
                $_SESSION['register_error'] = "Database error.";
                $_SESSION['active_form'] = 'register';
            }
        }
        $stmt->close();
    }
    header("Location: ../index.php");
    exit();
}

// ==================== LOGIN ====================
if (isset($_POST['login'])) {
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    // --- Check lockout FIRST ---
    $secondsRemaining = getLockoutSecondsRemaining($conn, $email);
    if ($secondsRemaining > 0) {
        $minutes = ceil($secondsRemaining / 60);
        $_SESSION['login_error'] = "Account temporarily locked. Please try again in {$secondsRemaining} seconds.";
        $_SESSION['lockout_email']   = $email;
        $_SESSION['lockout_until']   = time() + $secondsRemaining;
        $_SESSION['active_form']     = 'login';
        header("Location: ../index.php");
        exit();
    }

    // --- Verify credentials ---
    $sql = "SELECT ID, firstName, email, Password, userType FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            $user = $result->fetch_assoc();
            if (!empty($user['Password']) && password_verify($password, $user['Password'])) {
                // SUCCESS — reset attempts, start session
                resetLoginAttempts($conn, $email);
                session_regenerate_id(true);

                $_SESSION['ID']         = $user['ID'];
                $_SESSION['user_id']    = $user['ID'];
                $_SESSION['firstName']  = $user['firstName'];
                $_SESSION['email']      = $user['email'];
                $_SESSION['logged_in']  = true;
                $_SESSION['role']       = $user['userType'];

                if ($_SESSION['role'] === 'admin') {
                    header("Location: ../Admin_dashboard.php");
                } elseif ($_SESSION['role'] === 'teacher') {
                    header("Location: ../dashboard_teacher.php");
                } else {
                    header("Location: ../dashboard_student.php");
                }
                $stmt->close();
                exit();
            }
        }
        $stmt->close();
    }

    // FAILED login — record the attempt
    recordFailedAttempt($conn, $email);

    $secondsRemaining = getLockoutSecondsRemaining($conn, $email);
    $remaining        = getRemainingAttempts($conn, $email);

    if ($secondsRemaining > 0) {
        // Just got locked out on this attempt
        $_SESSION['login_error']   = "Too many failed attempts. Your account is locked for " . LOCKOUT_DURATION . " seconds.";
        $_SESSION['lockout_email'] = $email;
        $_SESSION['lockout_until'] = time() + $secondsRemaining;
    } elseif ($remaining > 0) {
        $_SESSION['login_error'] = "Invalid email or password. You have {$remaining} attempt" . ($remaining === 1 ? '' : 's') . " remaining.";
    } else {
        $_SESSION['login_error'] = "Invalid email or password.";
    }

    $_SESSION['active_form'] = 'login';
    header("Location: ../index.php");
    exit();
}
?>