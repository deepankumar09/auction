<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/functions.php';

if (isAdminLoggedIn()) {
    redirect('admin/dashboard.php');
}

function maskEmail(string $email): string
{
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return '';
    }
    [$user, $domain] = explode('@', $email, 2);
    $visible = substr($user, 0, 2);
    $masked = $visible . str_repeat('*', max(1, strlen($user) - 2));
    return $masked . '@' . $domain;
}

$recovery = $_SESSION['admin_recovery'] ?? [];
$stage = (string)($recovery['stage'] ?? 'request');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'request_otp') {
        $adminStmt = db()->query('SELECT admin_id, username FROM admin ORDER BY admin_id ASC LIMIT 1');
        $admin = $adminStmt->fetch();
        if (!$admin) {
            flash('error', 'Admin account not found.');
            redirect('admin/forgot_password.php');
        }

        $otp = (string)random_int(100000, 999999);
        $sent = false;
        $contactMasked = '';
        $email = (string)(defined('ADMIN_RECOVERY_EMAIL') ? ADMIN_RECOVERY_EMAIL : '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Set valid ADMIN_RECOVERY_EMAIL in config first.');
            redirect('admin/forgot_password.php');
        }
        $subject = 'Admin Recovery OTP';
        $message = "Your admin recovery OTP is {$otp}. Valid for 5 minutes.";
        $headers = "From: noreply@localhost\r\nContent-Type: text/plain; charset=UTF-8";
        $sent = function_exists('mail') ? @mail($email, $subject, $message, $headers) : false;
        $contactMasked = maskEmail($email);

        if (!$sent) {
            flash('error', 'OTP could not be sent. Check SMS/Email configuration.');
            redirect('admin/forgot_password.php');
        }

        $_SESSION['admin_recovery'] = [
            'stage' => 'verify',
            'method' => 'email',
            'admin_id' => (int)$admin['admin_id'],
            'username' => (string)$admin['username'],
            'otp_hash' => password_hash($otp, PASSWORD_DEFAULT),
            'expires_at' => time() + 300,
            'attempts' => 0,
            'contact_masked' => $contactMasked,
            'verified' => false,
        ];
        flash('success', 'OTP sent successfully.');
        redirect('admin/forgot_password.php');
    }

    if ($action === 'verify_otp') {
        $recovery = $_SESSION['admin_recovery'] ?? [];
        if (($recovery['stage'] ?? '') !== 'verify') {
            flash('error', 'Request OTP first.');
            redirect('admin/forgot_password.php');
        }
        if ((int)($recovery['expires_at'] ?? 0) < time()) {
            unset($_SESSION['admin_recovery']);
            flash('error', 'OTP expired. Request again.');
            redirect('admin/forgot_password.php');
        }
        if ((int)($recovery['attempts'] ?? 0) >= 5) {
            unset($_SESSION['admin_recovery']);
            flash('error', 'Too many attempts. Request new OTP.');
            redirect('admin/forgot_password.php');
        }

        $otpInput = trim((string)($_POST['otp'] ?? ''));
        if ($otpInput === '' || !password_verify($otpInput, (string)$recovery['otp_hash'])) {
            $_SESSION['admin_recovery']['attempts'] = (int)($recovery['attempts'] ?? 0) + 1;
            flash('error', 'Invalid OTP.');
            redirect('admin/forgot_password.php');
        }

        $_SESSION['admin_recovery']['stage'] = 'reset';
        $_SESSION['admin_recovery']['verified'] = true;
        flash('success', 'OTP verified. You can reset username/password now.');
        redirect('admin/forgot_password.php');
    }

    if ($action === 'reset_account') {
        $recovery = $_SESSION['admin_recovery'] ?? [];
        if (($recovery['stage'] ?? '') !== 'reset' || empty($recovery['verified'])) {
            flash('error', 'OTP verification required.');
            redirect('admin/forgot_password.php');
        }
        if ((int)($recovery['expires_at'] ?? 0) < time()) {
            unset($_SESSION['admin_recovery']);
            flash('error', 'Recovery session expired. Request OTP again.');
            redirect('admin/forgot_password.php');
        }

        $newUsername = trim((string)($_POST['new_username'] ?? ''));
        $newPassword = (string)($_POST['new_password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');
        $adminId = (int)($recovery['admin_id'] ?? 0);

        if ($newUsername === '' && $newPassword === '') {
            flash('error', 'Enter new username or new password.');
            redirect('admin/forgot_password.php');
        }

        $updates = [];
        $params = ['admin_id' => $adminId];

        if ($newUsername !== '') {
            if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $newUsername)) {
                flash('error', 'Username must be 3-50 chars (letters, numbers, _ . -).');
                redirect('admin/forgot_password.php');
            }
            $checkStmt = db()->prepare('SELECT admin_id FROM admin WHERE username = :username AND admin_id <> :admin_id LIMIT 1');
            $checkStmt->execute(['username' => $newUsername, 'admin_id' => $adminId]);
            if ($checkStmt->fetch()) {
                flash('error', 'Username already exists.');
                redirect('admin/forgot_password.php');
            }
            $updates[] = 'username = :username';
            $params['username'] = $newUsername;
        }

        if ($newPassword !== '') {
            if (strlen($newPassword) < 6) {
                flash('error', 'Password must be at least 6 characters.');
                redirect('admin/forgot_password.php');
            }
            if ($newPassword !== $confirmPassword) {
                flash('error', 'Confirm password does not match.');
                redirect('admin/forgot_password.php');
            }
            $updates[] = 'password = :password';
            $params['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
        }

        if ($updates) {
            $sql = 'UPDATE admin SET ' . implode(', ', $updates) . ' WHERE admin_id = :admin_id';
            $stmt = db()->prepare($sql);
            $stmt->execute($params);
        }

        unset($_SESSION['admin_recovery']);
        flash('success', 'Admin account updated. Login with new credentials.');
        redirect('admin/login.php');
    }
}

$recovery = $_SESSION['admin_recovery'] ?? [];
$stage = (string)($recovery['stage'] ?? 'request');
$method = (string)($recovery['method'] ?? 'email');
$contactMasked = (string)($recovery['contact_masked'] ?? '');
$currentUsername = (string)($recovery['username'] ?? '');

$pageTitle = 'Admin Recovery';
require ROOT_PATH . '/includes/header.php';
?>
<section class="auth-shell">
    <div class="auth-card admin-login-card card">
        <h2>Admin Recovery</h2>
        <p class="auth-subtitle">Recover admin username or reset password with OTP verification.</p>

        <?php if ($stage === 'request'): ?>
            <form method="post">
                <input type="hidden" name="action" value="request_otp">
                <button class="btn auth-btn" type="submit">Send Email OTP</button>
            </form>
        <?php elseif ($stage === 'verify'): ?>
            <p class="auth-subtitle">OTP sent to <?php echo esc($contactMasked); ?> via EMAIL.</p>
            <form method="post">
                <input type="hidden" name="action" value="verify_otp">
                <label for="otp">Enter OTP</label>
                <input id="otp" name="otp" type="text" maxlength="6" required>
                <button class="btn auth-btn" type="submit">Verify OTP</button>
            </form>
        <?php else: ?>
            <p class="auth-subtitle">Current username: <strong><?php echo esc($currentUsername); ?></strong></p>
            <form method="post">
                <input type="hidden" name="action" value="reset_account">
                <label for="new_username">New Username (optional)</label>
                <input id="new_username" name="new_username" type="text">

                <label for="new_password">New Password (optional)</label>
                <input id="new_password" name="new_password" type="password">

                <label for="confirm_password">Confirm New Password</label>
                <input id="confirm_password" name="confirm_password" type="password">

                <button class="btn auth-btn" type="submit">Update Admin Account</button>
            </form>
        <?php endif; ?>

        <p class="auth-switch"><a href="<?php echo BASE_URL; ?>/admin/login.php">Back to Admin Login</a></p>
    </div>
</section>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
