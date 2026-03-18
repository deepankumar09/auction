<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/functions.php';

if (isUserLoggedIn()) {
    redirect('vehicles.php');
}

if (isset($_GET['reset_otp'])) {
    unset($_SESSION['forgot_otp_email'], $_SESSION['forgot_otp_code'], $_SESSION['forgot_otp_expires']);
    flash('success', 'Forgot password flow reset. Please enter your email again.');
    redirect('forgot_password.php');
}

$email = '';
$isOtpStage = false;

if (!empty($_SESSION['forgot_otp_email']) && !empty($_SESSION['forgot_otp_code']) && !empty($_SESSION['forgot_otp_expires'])) {
    $email = (string)$_SESSION['forgot_otp_email'];
    $isOtpStage = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['send_otp'])) {
        $email = strtolower(trim((string)($_POST['email'] ?? '')));

        if ($email === '') {
            flash('error', 'Email is required.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Please enter a valid email address.');
        } else {
            $check = db()->prepare("SELECT user_id, name FROM users WHERE email = :email AND status = 'active' LIMIT 1");
            $check->execute(['email' => $email]);
            $user = $check->fetch();

            if (!$user) {
                flash('error', 'Active user with this email was not found.');
            } else {
                $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $subject = 'Your Password Reset OTP - ' . APP_NAME;
                $userName = trim((string)($user['name'] ?? 'User'));
                $message = "Hello {$userName},\n\n"
                    . "Use this OTP to reset your password on " . APP_NAME . ":\n\n"
                    . $otp . "\n\n"
                    . "This OTP is valid for 10 minutes.\n"
                    . "If you did not request this, please ignore this email.\n\n"
                    . "Regards,\n" . APP_NAME . " Team";

                if (!sendEmailWithNodemailer($email, $subject, $message)) {
                    $error = getLastEmailError();
                    flash('error', $error !== '' ? 'Unable to send OTP email: ' . $error : 'Unable to send OTP email. Please try again.');
                } else {
                    $_SESSION['forgot_otp_email'] = $email;
                    $_SESSION['forgot_otp_code'] = $otp;
                    $_SESSION['forgot_otp_expires'] = time() + (10 * 60);
                    flash('success', 'OTP sent to your email. Enter OTP and new password.');
                    redirect('forgot_password.php');
                }
            }
        }
    } else {
        $otp = trim((string)($_POST['otp'] ?? ''));
        $newPassword = (string)($_POST['new_password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');

        if (!$isOtpStage) {
            flash('error', 'No OTP request found. Please send OTP first.');
        } elseif ($otp === '' || $newPassword === '' || $confirmPassword === '') {
            flash('error', 'All fields are required.');
        } elseif (!preg_match('/^[0-9]{6}$/', $otp)) {
            flash('error', 'Enter a valid 6-digit OTP.');
        } elseif (time() > (int)($_SESSION['forgot_otp_expires'] ?? 0)) {
            unset($_SESSION['forgot_otp_email'], $_SESSION['forgot_otp_code'], $_SESSION['forgot_otp_expires']);
            flash('error', 'OTP expired. Please request a new OTP.');
        } elseif (!hash_equals((string)$_SESSION['forgot_otp_code'], $otp)) {
            flash('error', 'Invalid OTP. Please try again.');
        } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d]).{8,}$/', $newPassword)) {
            flash('error', 'Password must be at least 8 characters with uppercase, lowercase, number, and special character.');
        } elseif ($newPassword !== $confirmPassword) {
            flash('error', 'New password and confirm password do not match.');
        } else {
            $email = (string)$_SESSION['forgot_otp_email'];
            $check = db()->prepare("SELECT user_id FROM users WHERE email = :email AND status = 'active' LIMIT 1");
            $check->execute(['email' => $email]);
            $user = $check->fetch();
            if (!$user) {
                unset($_SESSION['forgot_otp_email'], $_SESSION['forgot_otp_code'], $_SESSION['forgot_otp_expires']);
                flash('error', 'Active user with this email was not found.');
                redirect('forgot_password.php');
            }
            $update = db()->prepare('UPDATE users SET password = :password WHERE user_id = :user_id');
            $update->execute([
                'password' => password_hash($newPassword, PASSWORD_DEFAULT),
                'user_id' => $user['user_id'],
            ]);
            unset($_SESSION['forgot_otp_email'], $_SESSION['forgot_otp_code'], $_SESSION['forgot_otp_expires']);
            flash('success', 'Password reset successful. Login with your new password.');
            redirect('login.php');
        }
    }
}

$pageTitle = 'Forgot Password';
require ROOT_PATH . '/includes/header.php';
?>
<section class="auth-shell">
    <div class="auth-card card">
        <h2>Forgot Password (Email OTP)</h2>
        <p class="auth-subtitle">Send OTP to your registered email and reset your password securely.</p>
        <?php if ($isOtpStage): ?>
            <form method="post">
                <label>Email</label>
                <input type="text" value="<?php echo esc($email); ?>" readonly>

                <label for="otp">OTP</label>
                <input id="otp" name="otp" type="text" maxlength="6" pattern="[0-9]{6}" required>

                <label for="new_password">New Password</label>
                <input id="new_password" name="new_password" type="password" required>

                <label for="confirm_password">Confirm Password</label>
                <input id="confirm_password" name="confirm_password" type="password" required>

                <div class="forgot-password-actions">
                    <button class="btn auth-btn" type="submit" name="reset_password">Verify OTP & Reset</button>
                    <a class="btn btn-secondary forgot-password-secondary-btn" href="<?php echo BASE_URL; ?>/forgot_password.php?reset_otp=1">Send New OTP</a>
                </div>
            </form>
        <?php else: ?>
            <form method="post">
                <label for="email">Registered Email</label>
                <input id="email" name="email" type="email" required>

                <button class="btn auth-btn" type="submit" name="send_otp">Send OTP</button>
            </form>
        <?php endif; ?>
        <p class="auth-switch"><a href="<?php echo BASE_URL; ?>/login.php">Back to User Login</a></p>
    </div>
</section>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
