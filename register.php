<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/functions.php';

if (isUserLoggedIn()) {
    redirect('vehicles.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($name === '' || $email === '' || $phone === '' || $password === '') {
        flash('error', 'All fields are required.');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('error', 'Enter a valid email address.');
    } else {
        $stmt = db()->prepare('SELECT user_id FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);

        if ($stmt->fetch()) {
            flash('error', 'Email already registered.');
        } else {
            $sql = 'INSERT INTO users (name, email, phone, password, status) VALUES (:name, :email, :phone, :password, :status)';
            $insert = db()->prepare($sql);
            $insert->execute([
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'status' => 'active',
            ]);

            flash('success', 'Registration successful. Login now.');
            redirect('login.php');
        }
    }
}

$pageTitle = 'User Registration';
require ROOT_PATH . '/includes/header.php';
?>
<section class="auth-shell">
    <div class="auth-card card">
        <h2>Create User Account</h2>
        <p class="auth-subtitle">Register to join seized vehicle auctions.</p>
        <form method="post" class="form-grid">
            <div>
                <label for="name">Name</label>
                <input id="name" name="name" type="text" required>
            </div>

            <div>
                <label for="email">Email</label>
                <input id="email" name="email" type="email" required>
            </div>

            <div>
                <label for="phone">Phone</label>
                <input id="phone" name="phone" type="text" required>
            </div>

            <div>
                <label for="password">Password</label>
                <input id="password" name="password" type="password" required>
            </div>

            <button class="btn auth-btn form-span" type="submit">Register</button>
        </form>
        <p class="auth-switch">Already registered? <a href="<?php echo BASE_URL; ?>/login.php">Login</a></p>
    </div>
</section>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
