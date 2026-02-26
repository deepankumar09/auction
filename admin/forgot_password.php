<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/functions.php';

if (isAdminLoggedIn()) {
    redirect('admin/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($username === '' || $newPassword === '' || $confirmPassword === '') {
        flash('error', 'All fields are required.');
    } elseif (strlen($newPassword) < 6) {
        flash('error', 'Password must be at least 6 characters.');
    } elseif ($newPassword !== $confirmPassword) {
        flash('error', 'New password and confirm password do not match.');
    } else {
        $check = db()->prepare('SELECT admin_id FROM admin WHERE username = :username LIMIT 1');
        $check->execute(['username' => $username]);
        $admin = $check->fetch();

        if (!$admin) {
            flash('error', 'Admin username not found.');
        } else {
            $update = db()->prepare('UPDATE admin SET password = :password WHERE admin_id = :admin_id');
            $update->execute([
                'password' => password_hash($newPassword, PASSWORD_DEFAULT),
                'admin_id' => $admin['admin_id'],
            ]);
            flash('success', 'Password reset successful. Login with your new password.');
            redirect('admin/login.php');
        }
    }
}

$pageTitle = 'Admin Forgot Password';
require ROOT_PATH . '/includes/header.php';
?>
<section class="auth-shell">
    <div class="auth-card card">
        <h2>Forgot Admin Password</h2>
        <p class="auth-subtitle">Reset your admin account password.</p>
        <form method="post">
            <label for="username">Admin Username</label>
            <input id="username" name="username" type="text" required>

            <label for="new_password">New Password</label>
            <input id="new_password" name="new_password" type="password" required>

            <label for="confirm_password">Confirm Password</label>
            <input id="confirm_password" name="confirm_password" type="password" required>

            <button class="btn auth-btn" type="submit">Reset Password</button>
        </form>
        <p class="auth-switch"><a href="<?php echo BASE_URL; ?>/admin/login.php">Back to Admin Login</a></p>
    </div>
</section>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
