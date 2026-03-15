<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/functions.php';

requireAdminLogin();

if (isset($_GET['delete'])) {
    $bidId = (int)$_GET['delete'];
    $findStmt = db()->prepare('SELECT bid_id FROM bids WHERE bid_id = :bid_id LIMIT 1');
    $findStmt->execute(['bid_id' => $bidId]);
    $bidRow = $findStmt->fetch();

    if (!$bidRow) {
        flash('error', 'Bid record not found.');
        redirect('admin/manage_bids.php');
    }

    $deleteStmt = db()->prepare('DELETE FROM bids WHERE bid_id = :bid_id LIMIT 1');
    $deleteStmt->execute(['bid_id' => $bidId]);

    if ($deleteStmt->rowCount() > 0) {
        flash('success', 'Bid record deleted successfully.');
    } else {
        flash('error', 'Unable to delete bid record.');
    }
    redirect('admin/manage_bids.php');
}

$sql = "SELECT b.*, u.name AS bidder_name, v.brand, v.model, v.registration_no
        FROM bids b
        JOIN users u ON u.user_id = b.user_id
        JOIN vehicles v ON v.vehicle_id = b.vehicle_id
        ORDER BY b.bid_time DESC";
$bids = db()->query($sql)->fetchAll();

$pageTitle = 'Manage Bids';
require ROOT_PATH . '/includes/header.php';
?>
<section class="card">
    <h2>Manage Bid Records</h2>
    <table>
        <thead>
        <tr>
            <th>Bid ID</th>
            <th>Vehicle</th>
            <th>Registration</th>
            <th>Bidder</th>
            <th>Amount</th>
            <th>Time</th>
            <th>Delete</th>
        </tr>
        </thead>
        <tbody>
        <?php if (!$bids): ?>
            <tr><td colspan="7">No bids available.</td></tr>
        <?php else: ?>
            <?php foreach ($bids as $bid): ?>
                <tr>
                    <td><?php echo (int)$bid['bid_id']; ?></td>
                    <td><?php echo esc($bid['brand'] . ' ' . $bid['model']); ?></td>
                    <td><?php echo esc($bid['registration_no']); ?></td>
                    <td><?php echo esc($bid['bidder_name']); ?></td>
                    <td>Rs <?php echo number_format((float)$bid['bid_amount'], 2); ?></td>
                    <td><?php echo esc($bid['bid_time']); ?></td>
                    <td>
                        <a
                            class="btn btn-danger"
                            data-confirm="Delete this bid record?"
                            href="<?php echo BASE_URL; ?>/admin/manage_bids.php?delete=<?php echo (int)$bid['bid_id']; ?>"
                        >
                            Delete
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</section>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
