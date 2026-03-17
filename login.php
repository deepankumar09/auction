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
    $password = $_POST['password'] ?? '';

    $stmt = db()->prepare("SELECT * FROM users WHERE email = :email AND status = 'active' LIMIT 1");
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        flash('error', 'Invalid email or password.');
    } else {
        $_SESSION['user_id'] = (int)$user['user_id'];
        $_SESSION['user_name'] = $user['name'];
        flash('success', 'Login successful.');
        redirect('vehicles.php');
    }
}

$pageTitle = 'User Login';
require ROOT_PATH . '/includes/header.php';
?>
<section class="auth-shell">
    <div class="auth-card user-login-card card">
        <h2>User Login</h2>
        <p class="auth-subtitle">Access your account to place bids and track wins.</p>
        <form method="post" autocomplete="off">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" autocomplete="off" required>

            <label for="password">Password</label>
            <input id="password" name="password" type="password" autocomplete="new-password" required>

            <button class="btn auth-btn" type="submit">Login</button>
        </form>
        <div class="auth-links">
            <a href="<?php echo BASE_URL; ?>/forgot_password.php">Forgot Password?</a>
        </div>
        <p class="auth-switch">New user? <a href="<?php echo BASE_URL; ?>/register.php">Create account</a></p>
    </div>
</section>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
