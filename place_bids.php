<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/functions.php';

requireUserLogin();

$sql = "SELECT v.*,
               COALESCE(MAX(b.bid_amount), v.base_price) AS highest_bid
        FROM vehicles v
        LEFT JOIN bids b ON b.vehicle_id = v.vehicle_id
        WHERE v.auction_status = 'open'
        GROUP BY v.vehicle_id
        ORDER BY v.vehicle_id DESC";
$openVehicles = db()->query($sql)->fetchAll();

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
                        <a class="btn vehicle-card-btn" href="<?php echo BASE_URL; ?>/vehicle.php?id=<?php echo (int)$vehicle['vehicle_id']; ?>">Bid Now</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <section class="card">
            <p>No open auction is available for bidding right now.</p>
        </section>
    <?php endif; ?>
</section>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
?>
