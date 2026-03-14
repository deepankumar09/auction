<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/functions.php';

requireAdminLogin();

if (isset($_GET['delete'])) {
    $vehicleId = (int)$_GET['delete'];
    $findStmt = db()->prepare('SELECT image FROM vehicles WHERE vehicle_id = :vehicle_id LIMIT 1');
    $findStmt->execute(['vehicle_id' => $vehicleId]);
    $vehicle = $findStmt->fetch();

    if ($vehicle) {
        $deleteStmt = db()->prepare('DELETE FROM vehicles WHERE vehicle_id = :vehicle_id');
        $deleteStmt->execute(['vehicle_id' => $vehicleId]);
        if (!empty($vehicle['image'])) {
            $imagePath = ROOT_PATH . '/' . ltrim((string)$vehicle['image'], '/');
            if (is_file($imagePath)) {
                @unlink($imagePath);
            }
        }
        flash('success', 'Auction deleted successfully.');
    } else {
        flash('error', 'Auction record not found.');
    }
    redirect('admin/vehicles.php');
}

$sql = "SELECT v.*,
               COALESCE(MAX(b.bid_amount), v.base_price) AS highest_bid,
               u.name AS winner_name
        FROM vehicles v
        LEFT JOIN bids b ON b.vehicle_id = v.vehicle_id
        LEFT JOIN users u ON u.user_id = v.winner_user_id
        GROUP BY v.vehicle_id
        ORDER BY v.vehicle_id DESC";
$vehicles = db()->query($sql)->fetchAll();

$pageTitle = 'Manage Vehicles';
require ROOT_PATH . '/includes/header.php';
?>
<section class="card">
    <h2>Manage Auctions</h2>
    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>Vehicle</th>
            <th>Type</th>
            <th>Base Price</th>
            <th>Highest Bid</th>
            <th>Status</th>
            <th>Winner</th>
            <th>Action</th>
            <th>Delete</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($vehicles as $v): ?>
            <tr>
                <td><?php echo (int)$v['vehicle_id']; ?></td>
                <td><?php echo esc($v['brand'] . ' ' . $v['model']); ?></td>
                <td><?php echo esc($v['category']); ?></td>
                <td>Rs <?php echo number_format((float)$v['base_price'], 2); ?></td>
                <td>Rs <?php echo number_format((float)$v['highest_bid'], 2); ?></td>
                <td><?php echo strtoupper(esc($v['auction_status'])); ?></td>
                <td><?php echo esc($v['winner_name'] ?? '-'); ?></td>
                <td>
                    <div class="inline">
                        <?php if ($v['auction_status'] === 'open'): ?>
                            <a class="btn btn-secondary" data-confirm="Close this auction now?" href="<?php echo BASE_URL; ?>/admin/close_auction.php?id=<?php echo (int)$v['vehicle_id']; ?>">Close Auction</a>
                        <?php else: ?>
                            <span>Closed</span>
                        <?php endif; ?>
                    </div>
                </td>
                <td>
                    <a class="btn btn-danger" data-confirm="Delete this auction from database?" href="<?php echo BASE_URL; ?>/admin/vehicles.php?delete=<?php echo (int)$v['vehicle_id']; ?>">Delete</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
