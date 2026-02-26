<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/functions.php';

$vehicleId = (int)($_GET['id'] ?? 0);
$vehicle = getVehicleById($vehicleId);

if (!$vehicle) {
    flash('error', 'Vehicle not found.');
    redirect('vehicles.php');
}

$highestBid = getCurrentHighestBid($vehicleId);
$displayBid = max($highestBid, (float)$vehicle['base_price']);
$auctionEndsAt = strtotime((string)$vehicle['created_at'] . ' +8 hours');

$bidHistoryStmt = db()->prepare(
    'SELECT b.bid_amount, b.bid_time, u.name
     FROM bids b
     JOIN users u ON u.user_id = b.user_id
     WHERE b.vehicle_id = :vehicle_id
     ORDER BY b.bid_amount DESC, b.bid_time DESC
     LIMIT 10'
);
$bidHistoryStmt->execute(['vehicle_id' => $vehicleId]);
$bids = $bidHistoryStmt->fetchAll();

$pageTitle = 'Vehicle Details';
require ROOT_PATH . '/includes/header.php';
?>
<section class="card">
    <h2><?php echo esc($vehicle['brand'] . ' ' . $vehicle['model']); ?></h2>
    <?php if (!empty($vehicle['image'])): ?>
        <img class="vehicle-image" src="<?php echo BASE_URL . '/' . esc($vehicle['image']); ?>" alt="Vehicle">
    <?php endif; ?>

    <div class="vehicle-meta">
        <p><strong>Category:</strong> <?php echo esc($vehicle['category']); ?></p>
        <p><strong>Registration No:</strong> <?php echo esc($vehicle['registration_no']); ?></p>
        <p><strong>Year:</strong> <?php echo esc($vehicle['year']); ?></p>
        <p><strong>Condition:</strong> <?php echo esc($vehicle['vehicle_condition']); ?></p>
        <p><strong>Base Price:</strong> Rs <?php echo number_format((float)$vehicle['base_price'], 2); ?></p>
        <p><strong>Current Highest Bid:</strong> Rs <?php echo number_format($displayBid, 2); ?></p>
        <p>
            <strong>Remaining Time:</strong>
            <span class="auction-countdown-inline" data-countdown-end="<?php echo (int)$auctionEndsAt; ?>">--</span>
        </p>
        <p>
            <strong>Auction Status:</strong>
            <span class="status status-<?php echo esc($vehicle['auction_status']); ?>">
                <?php echo strtoupper(esc($vehicle['auction_status'])); ?>
            </span>
        </p>
    </div>
</section>

<?php if ($vehicle['auction_status'] === 'open'): ?>
    <section class="card">
        <h3>Place Bid</h3>
        <?php if (!isUserLoggedIn()): ?>
            <p><a class="btn" href="<?php echo BASE_URL; ?>/login.php">Login to Place Bid</a></p>
        <?php else: ?>
            <form method="post" action="<?php echo BASE_URL; ?>/user/place_bid.php">
                <input type="hidden" name="vehicle_id" value="<?php echo (int)$vehicle['vehicle_id']; ?>">
                <label for="bid_amount">Bid Amount (must be more than Rs <?php echo number_format($displayBid, 2); ?>)</label>
                <input id="bid_amount" type="number" step="0.01" name="bid_amount" required>
                <button class="btn" type="submit">Submit Bid</button>
            </form>
        <?php endif; ?>
    </section>
<?php endif; ?>

<section class="card">
    <h3>Top Bid History</h3>
    <table>
        <thead>
        <tr>
            <th>Bidder</th>
            <th>Amount</th>
            <th>Time</th>
        </tr>
        </thead>
        <tbody>
        <?php if (!$bids): ?>
            <tr><td colspan="3">No bids yet.</td></tr>
        <?php else: ?>
            <?php foreach ($bids as $bid): ?>
                <tr>
                    <td><?php echo esc($bid['name']); ?></td>
                    <td>Rs <?php echo number_format((float)$bid['bid_amount'], 2); ?></td>
                    <td><?php echo esc($bid['bid_time']); ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</section>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
