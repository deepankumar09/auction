<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/functions.php';

requireAdminLogin();

$sql = "SELECT v.vehicle_id,
               v.brand,
               v.model,
               v.auction_status,
               v.final_price,
               u.name AS user_name,
               u.email,
               p.payment_id,
               p.amount,
               p.razorpay_order_id,
               p.razorpay_payment_id,
               p.payment_status
        FROM vehicles v
        LEFT JOIN users u ON u.user_id = v.winner_user_id
        LEFT JOIN (
            SELECT p1.*
            FROM payments p1
            JOIN (
                SELECT vehicle_id, user_id, MAX(payment_id) AS max_payment_id
                FROM payments
                GROUP BY vehicle_id, user_id
            ) p2 ON p2.max_payment_id = p1.payment_id
        ) p ON p.vehicle_id = v.vehicle_id AND p.user_id = v.winner_user_id
        WHERE v.winner_user_id IS NOT NULL
          AND v.auction_status IN ('closed', 'sold')
        ORDER BY v.vehicle_id DESC";
$payments = db()->query($sql)->fetchAll();

$pageTitle = 'Payment Verification';
require ROOT_PATH . '/includes/header.php';
?>
<section class="card">
    <h2>Payment Status</h2>
    <table>
        <thead>
        <tr>
            <th>Vehicle ID</th>
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
                <?php $status = (string)($payment['payment_status'] ?? 'pending'); ?>
                <tr>
                    <td><?php echo (int)$payment['vehicle_id']; ?></td>
                    <td><?php echo esc($payment['brand'] . ' ' . $payment['model']); ?></td>
                    <td><?php echo esc((string)($payment['user_name'] ?? '-')); ?></td>
                    <td>Rs <?php echo number_format((float)($payment['amount'] ?? $payment['final_price'] ?? 0), 2); ?></td>
                    <td><?php echo esc((string)($payment['razorpay_order_id'] ?? '-')); ?></td>
                    <td><?php echo esc((string)$payment['razorpay_payment_id']); ?></td>
                    <td><?php echo strtoupper(esc($status)); ?></td>
                    <td>
                        <?php if ($status === 'paid' && $payment['auction_status'] === 'closed'): ?>
                            <a class="btn" data-confirm="Mark this vehicle as SOLD?" href="<?php echo BASE_URL; ?>/admin/mark_sold.php?vehicle_id=<?php echo (int)$payment['vehicle_id']; ?>">Mark Sold</a>
                        <?php elseif ($payment['auction_status'] === 'closed' && $status !== 'paid'): ?>
                            <a class="btn btn-danger" data-confirm="Winner has not paid. Move this vehicle back to auction?" href="<?php echo BASE_URL; ?>/admin/reauction_vehicle.php?vehicle_id=<?php echo (int)$payment['vehicle_id']; ?>">Re-Auction</a>
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
