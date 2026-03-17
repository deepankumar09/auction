<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/functions.php';

$category = $_GET['category'] ?? '';

$sql = "SELECT v.*,
               COALESCE(MAX(b.bid_amount), v.base_price) AS highest_bid
        FROM vehicles v
        LEFT JOIN bids b ON b.vehicle_id = v.vehicle_id";

$params = [];
if (in_array($category, ['Bike', 'Car'], true)) {
    $sql .= ' WHERE v.category = :category';
    $params['category'] = $category;
}

$sql .= ' GROUP BY v.vehicle_id ORDER BY v.vehicle_id DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$vehicles = $stmt->fetchAll();

$pageTitle = APP_NAME;
require ROOT_PATH . '/includes/header.php';
?>
<section class="vehicles-page">
    <section class="vehicles-toolbar">
        <div>
            <h2><?php echo esc(APP_NAME); ?></h2>
            <p>Browse all listed bikes and cars available for auction bidding.</p>
        </div>
        <div class="vehicles-filter">
            <a class="vehicle-filter-pill<?php echo $category === '' ? ' is-active' : ''; ?>" href="<?php echo BASE_URL; ?>/vehicles.php">All</a>
            <a class="vehicle-filter-pill<?php echo $category === 'Bike' ? ' is-active' : ''; ?>" href="<?php echo BASE_URL; ?>/vehicles.php?category=Bike">Bike</a>
            <a class="vehicle-filter-pill<?php echo $category === 'Car' ? ' is-active' : ''; ?>" href="<?php echo BASE_URL; ?>/vehicles.php?category=Car">Car</a>
        </div>
    </section>

    <div class="grid vehicles-grid">
        <?php foreach ($vehicles as $vehicle): ?>
            <article class="vehicle-card">
                <div class="vehicle-card-media">
                    <?php if (!empty($vehicle['image'])): ?>
                        <img class="vehicle-image" src="<?php echo BASE_URL . '/' . esc($vehicle['image']); ?>" alt="Vehicle">
                    <?php else: ?>
                        <div class="vehicle-image vehicle-image-fallback">No Image</div>
                    <?php endif; ?>
                    <span class="status status-<?php echo esc($vehicle['auction_status']); ?>">
                        <?php echo strtoupper(esc($vehicle['auction_status'])); ?>
                    </span>
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
                        <p><small>Market Value</small><strong>Rs <?php echo number_format((float)($vehicle['market_value'] ?? 0), 2); ?></strong></p>
                        <p><small>Highest Bid</small><strong>Rs <?php echo number_format((float)$vehicle['highest_bid'], 2); ?></strong></p>
                    </div>
                    <?php if (($vehicle['auction_status'] ?? '') === 'open'): ?>
                        <a class="btn vehicle-card-btn" href="<?php echo BASE_URL; ?>/place_bids.php?id=<?php echo (int)$vehicle['vehicle_id']; ?>">View & Bid</a>
                    <?php else: ?>
                        <span class="btn vehicle-card-btn btn-disabled">
                            <?php echo strtoupper(esc($vehicle['auction_status'])); ?> - Bidding Closed
                        </span>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
    <?php if (!$vehicles): ?>
        <section class="card">
            <p>No vehicles found for this filter.</p>
        </section>
    <?php endif; ?>
</section>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
