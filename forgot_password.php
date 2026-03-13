<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/functions.php';

if (isUserLoggedIn()) {
    redirect('vehicles.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($email === '' || $newPassword === '' || $confirmPassword === '') {
        flash('error', 'All fields are required.');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('error', 'Please enter a valid email address.');
    } elseif (strlen($newPassword) < 6) {
        flash('error', 'Password must be at least 6 characters.');
    } elseif ($newPassword !== $confirmPassword) {
        flash('error', 'New password and confirm password do not match.');
    } else {
        $check = db()->prepare("SELECT user_id FROM users WHERE email = :email AND status = 'active' LIMIT 1");
        $check->execute(['email' => $email]);
        $user = $check->fetch();

        if (!$user) {
            flash('error', 'Active user with this email was not found.');
        } else {
            $update = db()->prepare('UPDATE users SET password = :password WHERE user_id = :user_id');
            $update->execute([
                'password' => password_hash($newPassword, PASSWORD_DEFAULT),
                'user_id' => $user['user_id'],
            ]);
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
        <h2>Forgot User Password</h2>
        <p class="auth-subtitle">Reset your user account password.</p>
        <form method="post">
            <label for="email">Registered Email</label>
            <input id="email" name="email" type="email" required>

            <label for="new_password">New Password</label>
            <input id="new_password" name="new_password" type="password" required>

            <label for="confirm_password">Confirm Password</label>
            <input id="confirm_password" name="confirm_password" type="password" required>

            <button class="btn auth-btn" type="submit">Reset Password</button>
        </form>
        <p class="auth-switch"><a href="<?php echo BASE_URL; ?>/login.php">Back to User Login</a></p>
    </div>
</section>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
