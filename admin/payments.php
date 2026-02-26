<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/functions.php';

requireAdminLogin();

$sql = "SELECT p.*, u.name AS user_name, u.email, v.brand, v.model, v.auction_status
        FROM payments p
        JOIN users u ON u.user_id = p.user_id
        JOIN vehicles v ON v.vehicle_id = p.vehicle_id
        ORDER BY p.payment_id DESC";
$payments = db()->query($sql)->fetchAll();

$pageTitle = 'Payment Verification';
require ROOT_PATH . '/includes/header.php';
?>
<section class="card">
    <h2>Razorpay Payment Status</h2>
    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>Vehicle</th>
            <th>User</th>
            <th>Amount</th>
            <th>Order ID</th>
            <th>Payment ID</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
        </thead>
        <tbody>
        <?php if (!$payments): ?>
            <tr><td colspan="8">No payment records.</td></tr>
        <?php else: ?>
            <?php foreach ($payments as $payment): ?>
                <tr>
                    <td><?php echo (int)$payment['payment_id']; ?></td>
                    <td><?php echo esc($payment['brand'] . ' ' . $payment['model']); ?></td>
                    <td><?php echo esc($payment['user_name']); ?><br><?php echo esc($payment['email']); ?></td>
                    <td>Rs <?php echo number_format((float)$payment['amount'], 2); ?></td>
                    <td><?php echo esc($payment['razorpay_order_id']); ?></td>
                    <td><?php echo esc((string)$payment['razorpay_payment_id']); ?></td>
                    <td><?php echo strtoupper(esc($payment['payment_status'])); ?></td>
                    <td>
                        <?php if ($payment['payment_status'] === 'paid' && $payment['auction_status'] === 'closed'): ?>
                            <a class="btn" data-confirm="Mark this vehicle as SOLD?" href="<?php echo BASE_URL; ?>/admin/mark_sold.php?vehicle_id=<?php echo (int)$payment['vehicle_id']; ?>">Mark Sold</a>
                        <?php elseif ($payment['auction_status'] === 'sold'): ?>
                            SOLD
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</section>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
