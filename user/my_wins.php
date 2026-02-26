<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/functions.php';

requireUserLogin();

$userId = (int)$_SESSION['user_id'];

$sql = "SELECT v.vehicle_id, v.brand, v.model, v.category, v.registration_no, v.auction_status,
               v.final_price, p.payment_id, p.payment_status
        FROM vehicles v
        LEFT JOIN (
            SELECT p1.*
            FROM payments p1
            JOIN (
                SELECT vehicle_id, user_id, MAX(payment_id) AS max_payment_id
                FROM payments
                GROUP BY vehicle_id, user_id
            ) p2 ON p2.max_payment_id = p1.payment_id
        ) p ON p.vehicle_id = v.vehicle_id AND p.user_id = :user_id
        WHERE v.winner_user_id = :user_id
        ORDER BY v.vehicle_id DESC";
$stmt = db()->prepare($sql);
$stmt->execute(['user_id' => $userId]);
$wins = $stmt->fetchAll();

$pageTitle = 'My Wins';
require ROOT_PATH . '/includes/header.php';
?>
<section class="card">
    <h2>My Won Auctions</h2>
    <table>
        <thead>
        <tr>
            <th>Vehicle</th>
            <th>Category</th>
            <th>Registration</th>
            <th>Amount</th>
            <th>Auction</th>
            <th>Payment</th>
            <th>Action</th>
        </tr>
        </thead>
        <tbody>
        <?php if (!$wins): ?>
            <tr><td colspan="7">No won auctions yet.</td></tr>
        <?php else: ?>
            <?php foreach ($wins as $win): ?>
                <tr>
                    <td><?php echo esc($win['brand'] . ' ' . $win['model']); ?></td>
                    <td><?php echo esc($win['category']); ?></td>
                    <td><?php echo esc($win['registration_no']); ?></td>
                    <td>Rs <?php echo number_format((float)$win['final_price'], 2); ?></td>
                    <td><?php echo strtoupper(esc($win['auction_status'])); ?></td>
                    <td><?php echo strtoupper(esc($win['payment_status'] ?? 'pending')); ?></td>
                    <td>
                        <?php if (($win['payment_status'] ?? '') === 'paid'): ?>
                            <a class="btn btn-secondary" href="<?php echo BASE_URL; ?>/user/invoice.php?payment_id=<?php echo (int)$win['payment_id']; ?>">Invoice</a>
                        <?php else: ?>
                            <a class="btn" href="<?php echo BASE_URL; ?>/user/pay.php?vehicle_id=<?php echo (int)$win['vehicle_id']; ?>">Pay Now</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</section>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
