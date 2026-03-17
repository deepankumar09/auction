<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/functions.php';

if (isUserLoggedIn()) {
    redirect('vehicles.php');
}

if (isset($_GET['reset_otp'])) {
    unset($_SESSION['register_pending'], $_SESSION['register_otp'], $_SESSION['register_otp_expires']);
    flash('success', 'Registration details reset. Please enter details again.');
    redirect('register.php');
}

$name = '';
$email = '';
$rawPhone = '';

if (!empty($_SESSION['register_pending']) && is_array($_SESSION['register_pending'])) {
    $name = (string)($_SESSION['register_pending']['name'] ?? '');
    $email = (string)($_SESSION['register_pending']['email'] ?? '');
    $rawPhone = (string)($_SESSION['register_pending']['raw_phone'] ?? '');
}

$isOtpStage = !empty($_SESSION['register_pending']) && !empty($_SESSION['register_otp']) && !empty($_SESSION['register_otp_expires']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['verify_otp'])) {
        $otp = trim((string)($_POST['otp'] ?? ''));

        if (!$isOtpStage) {
            flash('error', 'No OTP request found. Please submit your details first.');
        } elseif (!preg_match('/^[0-9]{6}$/', $otp)) {
            flash('error', 'Enter a valid 6-digit OTP.');
        } elseif (time() > (int)($_SESSION['register_otp_expires'] ?? 0)) {
            unset($_SESSION['register_pending'], $_SESSION['register_otp'], $_SESSION['register_otp_expires']);
            flash('error', 'OTP expired. Please register again to get a new OTP.');
        } elseif (!hash_equals((string)$_SESSION['register_otp'], $otp)) {
            flash('error', 'Invalid OTP. Please try again.');
        } else {
            $pending = $_SESSION['register_pending'];
            $exists = db()->prepare('SELECT user_id FROM users WHERE email = :email LIMIT 1');
            $exists->execute(['email' => $pending['email']]);
            if ($exists->fetch()) {
                unset($_SESSION['register_pending'], $_SESSION['register_otp'], $_SESSION['register_otp_expires']);
                flash('error', 'Email already registered. Please login.');
            } else {
                $sql = 'INSERT INTO users (name, email, phone, password, status) VALUES (:name, :email, :phone, :password, :status)';
                $insert = db()->prepare($sql);
                $insert->execute([
                    'name' => $pending['name'],
                    'email' => $pending['email'],
                    'phone' => $pending['phone'],
                    'password' => $pending['password_hash'],
                    'status' => 'active',
                ]);

                unset($_SESSION['register_pending'], $_SESSION['register_otp'], $_SESSION['register_otp_expires']);
                flash('success', 'Registration successful. Login now.');
                redirect('login.php');
            }
        }
    } else {
        $name = trim((string)($_POST['name'] ?? ''));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $rawPhone = trim((string)($_POST['phone'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');
        $digitsPhone = preg_replace('/\D+/', '', $rawPhone) ?? '';
        $phone = $digitsPhone;

        if ($name === '' || $email === '' || $rawPhone === '' || $password === '' || $confirmPassword === '') {
            flash('error', 'All fields are required.');
        } elseif (!preg_match('/^[A-Za-z ]{3,60}$/', $name)) {
            flash('error', 'Name must be 3-60 characters and contain only letters and spaces.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}$/i', $email)) {
            flash('error', 'Enter a valid email address.');
        } elseif (!preg_match('/^[6-9][0-9]{9}$/', $digitsPhone)) {
                flash('error', 'Enter exactly 10 digits for phone number.');
        } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d]).{8,}$/', $password)) {
            flash('error', 'Password must be at least 8 characters with uppercase, lowercase, number, and special character.');
        } elseif ($password !== $confirmPassword) {
            flash('error', 'Password and confirm password do not match.');
        } else {
            $stmt = db()->prepare('SELECT user_id FROM users WHERE email = :email LIMIT 1');
            $stmt->execute(['email' => $email]);
            if ($stmt->fetch()) {
                flash('error', 'Email already registered.');
            } else {
                $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $subject = 'Your Registration OTP - ' . APP_NAME;
                $message = "Hello {$name},\n\n"
                    . "Use this OTP to complete your registration on " . APP_NAME . ":\n\n"
                    . $otp . "\n\n"
                    . "This OTP is valid for 10 minutes.\n"
                    . "If you did not request this, please ignore this email.\n\n"
                    . "Regards,\n" . APP_NAME . " Team";

                if (!sendEmailWithNodemailer($email, $subject, $message)) {
                    $error = getLastEmailError();
                    flash('error', $error !== '' ? 'Unable to send OTP email: ' . $error : 'Unable to send OTP email. Please try again.');
                } else {
                    $_SESSION['register_pending'] = [
                        'name' => $name,
                        'email' => $email,
                        'raw_phone' => $rawPhone,
                        'phone' => $phone,
                        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    ];
                    $_SESSION['register_otp'] = $otp;
                    $_SESSION['register_otp_expires'] = time() + (10 * 60);

                    flash('success', 'OTP sent to your email. Enter OTP to complete registration.');
                    redirect('register.php');
                }
            }
        }
    }
}

$pageTitle = 'User Registration';
require ROOT_PATH . '/includes/header.php';
?>
<section class="auth-shell">
    <div class="auth-card user-register-card card">
        <h2>Create User Account</h2>
        <p class="auth-subtitle">Register to join bank seized vehicle auctions with email OTP verification.</p>
        <?php if ($isOtpStage): ?>
            <form method="post" class="form-grid">
                <div>
                    <label>Name</label>
                    <input type="text" value="<?php echo esc($name); ?>" readonly>
                </div>
                <div>
                    <label>Email</label>
                    <input type="text" value="<?php echo esc($email); ?>" readonly>
                </div>
                <div>
                    <label>Phone</label>
                    <input type="text" value="<?php echo esc($rawPhone); ?>" readonly>
                </div>
                <div>
                    <label for="otp">OTP</label>
                    <input id="otp" name="otp" type="text" maxlength="6" pattern="[0-9]{6}" required>
                </div>
                <button class="btn auth-btn form-span" type="submit" name="verify_otp">Verify OTP & Register</button>
            </form>
        <?php else: ?>
            <form method="post" class="form-grid">
                <div>
                    <label for="name">Name</label>
                    <input id="name" name="name" type="text" value="<?php echo esc($name); ?>" required>
                </div>

                <div>
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" value="<?php echo esc($email); ?>" pattern="^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$" required>
                </div>

                <div>
                    <label for="phone">Phone</label>
                    <input id="phone" name="phone" type="text" value="<?php echo esc($rawPhone); ?>" maxlength="10" pattern="[0-9]{10}" inputmode="numeric" required>
                </div>

                <div>
                    <label for="password">Password</label>
                    <input id="password" name="password" type="password" required>
                </div>

                <div>
                    <label for="confirm_password">Confirm Password</label>
                    <input id="confirm_password" name="confirm_password" type="password" required>
                </div>

                <button class="btn auth-btn form-span" type="submit">Send OTP</button>
            </form>
        <?php endif; ?>
        <p class="auth-switch">Already registered? <a href="<?php echo BASE_URL; ?>/login.php">Login</a></p>
    </div>
</section>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
