<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/functions.php';

requireUserLogin();

$paymentId = (int)($_GET['payment_id'] ?? 0);
$userId = (int)$_SESSION['user_id'];

$sql = "SELECT p.*, u.name, u.email, u.phone, v.brand, v.model, v.registration_no
        FROM payments p
        JOIN users u ON u.user_id = p.user_id
        JOIN vehicles v ON v.vehicle_id = p.vehicle_id
        WHERE p.payment_id = :payment_id
          AND p.user_id = :user_id
          AND p.payment_status = 'paid'
        LIMIT 1";
$stmt = db()->prepare($sql);
$stmt->execute([
    'payment_id' => $paymentId,
    'user_id' => $userId,
]);
$invoice = $stmt->fetch();

if (!$invoice) {
    flash('error', 'Invoice not found.');
    redirect('user/my_wins.php');
}

$pageTitle = 'Invoice';
require ROOT_PATH . '/includes/header.php';
?>
<section class="card">
    <h2>Payment Invoice</h2>
    <p><strong>Invoice ID:</strong> INV-<?php echo (int)$invoice['payment_id']; ?></p>
    <p><strong>Date:</strong> <?php echo esc((string)$invoice['payment_date']); ?></p>
    <hr>
    <p><strong>Buyer:</strong> <?php echo esc($invoice['name']); ?></p>
    <p><strong>Email:</strong> <?php echo esc($invoice['email']); ?></p>
    <p><strong>Phone:</strong> <?php echo esc($invoice['phone']); ?></p>
    <hr>
    <p><strong>Vehicle:</strong> <?php echo esc($invoice['brand'] . ' ' . $invoice['model']); ?></p>
    <p><strong>Registration:</strong> <?php echo esc($invoice['registration_no']); ?></p>
    <p><strong>Amount Paid:</strong> Rs <?php echo number_format((float)$invoice['amount'], 2); ?></p>
    <p><strong>Razorpay Order ID:</strong> <?php echo esc($invoice['razorpay_order_id']); ?></p>
    <p><strong>Razorpay Payment ID:</strong> <?php echo esc((string)$invoice['razorpay_payment_id']); ?></p>
    <div class="inline">
        <button class="btn btn-secondary" onclick="window.print()">Print Invoice</button>
        <a class="btn" href="<?php echo BASE_URL; ?>/user/my_wins.php">Back</a>
    </div>
</section>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
