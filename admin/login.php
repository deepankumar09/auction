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
    $password = $_POST['password'] ?? '';

    $stmt = db()->prepare('SELECT * FROM admin WHERE username = :username LIMIT 1');
    $stmt->execute(['username' => $username]);
    $admin = $stmt->fetch();

    if (!$admin || !password_verify($password, $admin['password'])) {
        flash('error', 'Invalid admin credentials.');
    } else {
        $_SESSION['admin_id'] = (int)$admin['admin_id'];
        $_SESSION['admin_name'] = $admin['username'];
        flash('success', 'Welcome admin.');
        redirect('admin/dashboard.php');
    }
}

$pageTitle = 'Admin Login';
require ROOT_PATH . '/includes/header.php';
?>
<section class="auth-shell">
    <div class="auth-card admin-login-card card">
        <h2>Admin Login</h2>
        <p class="auth-subtitle">Sign in to manage auctions and payments.</p>
        <form method="post">
            <label for="username">Username</label>
            <input id="username" name="username" type="text" required>

            <label for="password">Password</label>
            <input id="password" name="password" type="password" required>

            <button class="btn auth-btn" type="submit">Login</button>
        </form>
        <div class="auth-links">
            <a href="<?php echo BASE_URL; ?>/admin/forgot_password.php">Forgot Username / Password?</a>
        </div>
        <p class="auth-switch"><a href="<?php echo BASE_URL; ?>/index.php">Go to Home</a></p>
    </div>
</section>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
