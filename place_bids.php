<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/functions.php';

requireUserLogin();

$selectedVehicleId = (int)($_GET['id'] ?? 0);
$selectedVehicle = null;
$topBids = [];

if ($selectedVehicleId > 0) {
    $selectedStmt = db()->prepare(
        "SELECT v.*,
                COALESCE(MAX(b.bid_amount), v.base_price) AS highest_bid
         FROM vehicles v
         LEFT JOIN bids b ON b.vehicle_id = v.vehicle_id
         WHERE v.vehicle_id = :vehicle_id
         GROUP BY v.vehicle_id
         LIMIT 1"
    );
    $selectedStmt->execute(['vehicle_id' => $selectedVehicleId]);
    $selectedVehicle = $selectedStmt->fetch() ?: null;

    if (!$selectedVehicle) {
        flash('error', 'Vehicle not found.');
        redirect('place_bids.php');
    }

    if (($selectedVehicle['auction_status'] ?? '') !== 'open') {
        flash('error', 'Auction is closed for this vehicle.');
        redirect('place_bids.php');
    }

    $topBidStmt = db()->prepare(
        "SELECT u.name, b.bid_amount, b.bid_time
         FROM bids b
         JOIN users u ON u.user_id = b.user_id
         WHERE b.vehicle_id = :vehicle_id
         ORDER BY b.bid_amount DESC, b.bid_time DESC
         LIMIT 10"
    );
    $topBidStmt->execute(['vehicle_id' => $selectedVehicleId]);
    $topBids = $topBidStmt->fetchAll();
}

$sql = "SELECT v.*,
               COALESCE(MAX(b.bid_amount), v.base_price) AS highest_bid
        FROM vehicles v
        LEFT JOIN bids b ON b.vehicle_id = v.vehicle_id
        WHERE v.auction_status = 'open'";

$params = [];
if ($selectedVehicleId > 0) {
    $sql .= ' AND v.vehicle_id <> :selected_vehicle_id';
    $params['selected_vehicle_id'] = $selectedVehicleId;
}

$sql .= ' GROUP BY v.vehicle_id ORDER BY v.vehicle_id DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$openVehicles = $stmt->fetchAll();

$pageTitle = 'Place Bids';
require ROOT_PATH . '/includes/header.php';
?>
<section class="vehicles-page">
    <section class="vehicles-toolbar">
        <div>
            <h2>Place Bids</h2>
            <p>Choose an open auction and place your bid.</p>
        </div>
    </section>

    <?php if ($selectedVehicle): ?>
        <section class="card placebid-layout">
            <div class="placebid-left">
                <h3 class="placebid-title"><?php echo esc($selectedVehicle['brand'] . ' ' . $selectedVehicle['model']); ?></h3>
                <div class="placebid-image-wrap">
                    <?php if (!empty($selectedVehicle['image'])): ?>
                        <img class="vehicle-image" src="<?php echo BASE_URL . '/' . esc($selectedVehicle['image']); ?>" alt="Vehicle">
                    <?php else: ?>
                        <div class="vehicle-image vehicle-image-fallback">No Image</div>
                    <?php endif; ?>
                </div>
                <div class="placebid-details">
                    <h4>Details</h4>
                    <p><strong>Category:</strong> <?php echo esc($selectedVehicle['category']); ?></p>
                    <p><strong>Registration:</strong> <?php echo esc($selectedVehicle['registration_no']); ?></p>
                    <p><strong>Year:</strong> <?php echo esc($selectedVehicle['year']); ?></p>
                    <p><strong>Condition:</strong> <?php echo esc($selectedVehicle['vehicle_condition']); ?></p>
                    <p><strong>Base Price:</strong> Rs <?php echo number_format((float)$selectedVehicle['base_price'], 2); ?></p>
                    <p><strong>Current Highest:</strong> Rs <?php echo number_format((float)$selectedVehicle['highest_bid'], 2); ?></p>
                </div>
            </div>

            <div class="placebid-right">
                <h4>Place Bid</h4>
                <form class="bid-form-box" method="post" action="<?php echo BASE_URL; ?>/user/place_bid.php">
                    <input type="hidden" name="vehicle_id" value="<?php echo (int)$selectedVehicle['vehicle_id']; ?>">
                    <label for="bid_amount_selected">Bid Amount</label>
                    <input id="bid_amount_selected" type="number" step="0.01" min="<?php echo number_format((float)$selectedVehicle['highest_bid'] + 0.01, 2, '.', ''); ?>" name="bid_amount" placeholder="Enter amount greater than Rs <?php echo number_format((float)$selectedVehicle['highest_bid'], 2); ?>" required>
                    <button class="btn vehicle-card-btn" type="submit">Place Bid</button>
                </form>

                <div class="placebid-history">
                    <h4>Top Bid History</h4>
                    <div class="placebid-history-list">
                        <?php if (!$topBids): ?>
                            <p>No bids yet for this vehicle.</p>
                        <?php else: ?>
                            <?php foreach ($topBids as $bid): ?>
                                <div class="placebid-history-item">
                                    <span><?php echo esc($bid['name']); ?></span>
                                    <strong>Rs <?php echo number_format((float)$bid['bid_amount'], 2); ?></strong>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($selectedVehicle && $openVehicles): ?>
        <section class="card">
            <h3>Other Open Auctions</h3>
            <p class="bid-focus-subtitle">Pick another vehicle to open it in the same place-bid layout.</p>
        </section>
    <?php endif; ?>

    <?php if ($openVehicles): ?>
        <div class="grid vehicles-grid">
            <?php foreach ($openVehicles as $vehicle): ?>
                <article class="vehicle-card">
                    <div class="vehicle-card-media">
                        <?php if (!empty($vehicle['image'])): ?>
                            <img class="vehicle-image" src="<?php echo BASE_URL . '/' . esc($vehicle['image']); ?>" alt="Vehicle">
                        <?php else: ?>
                            <div class="vehicle-image vehicle-image-fallback">No Image</div>
                        <?php endif; ?>
                        <span class="status status-open">OPEN</span>
                    </div>
                    <div class="vehicle-card-body">
                        <h3><?php echo esc($vehicle['brand'] . ' ' . $vehicle['model']); ?></h3>
                        <div class="vehicle-tags">
                            <span><?php echo esc($vehicle['category']); ?></span>
                            <span><?php echo esc($vehicle['year']); ?></span>
                            <span><?php echo esc($vehicle['vehicle_condition']); ?></span>
                        </div>
                        <div class="vehicle-details-grid">
                            <p><small>Registration</small><strong><?php echo esc($vehicle['registration_no']); ?></strong></p>
                            <p><small>Base Price</small><strong>Rs <?php echo number_format((float)$vehicle['base_price'], 2); ?></strong></p>
                            <p><small>Current Highest</small><strong>Rs <?php echo number_format((float)$vehicle['highest_bid'], 2); ?></strong></p>
                        </div>
                        <a class="btn vehicle-card-btn" href="<?php echo BASE_URL; ?>/place_bids.php?id=<?php echo (int)$vehicle['vehicle_id']; ?>">Bid Now</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <?php if (!$selectedVehicle): ?>
            <section class="card">
                <p>No open auction is available for bidding right now.</p>
            </section>
        <?php endif; ?>
    <?php endif; ?>
</section>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
