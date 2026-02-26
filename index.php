<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/functions.php';

$ads = db()->query("SELECT * FROM advertisements WHERE status = 'active' ORDER BY ad_id DESC LIMIT 3")->fetchAll();

$sql = "SELECT v.*,
               COALESCE(MAX(b.bid_amount), v.base_price) AS highest_bid
        FROM vehicles v
        LEFT JOIN bids b ON b.vehicle_id = v.vehicle_id
        WHERE v.auction_status = 'open'
        GROUP BY v.vehicle_id
        ORDER BY v.vehicle_id DESC
        LIMIT 6";
$featuredVehicles = db()->query($sql)->fetchAll();

$pageTitle = 'Home';
require ROOT_PATH . '/includes/header.php';
?>

<section class="hero">
    <h1 class="hero-title">
        <img src="<?php echo BASE_URL; ?>/assets/images/logo.svg" alt="Logo">
        <span>Online Auction For Seized Bikes & Cars</span>
    </h1>
    <p>Browse verified seized vehicles, place live bids, and complete secure payment using Razorpay.</p>
    <a class="btn" href="<?php echo BASE_URL; ?>/vehicles.php">Start Bidding</a>
</section>

<?php if ($ads): ?>
    <section class="card">
        <h2>Latest Advertisements</h2>
        <?php foreach ($ads as $ad): ?>
            <article class="card">
                <h3><?php echo esc($ad['title']); ?></h3>
                <p><?php echo esc($ad['description']); ?></p>
                <?php if (!empty($ad['image'])): ?>
                    <img src="<?php echo BASE_URL . '/' . esc($ad['image']); ?>" alt="Ad Banner" class="ad-banner">
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<section class="featured-auctions">
    <div class="featured-heading">
        <h2>Featured Open Auctions</h2>
        <a class="btn btn-secondary" href="<?php echo BASE_URL; ?>/vehicles.php">View All Auctions</a>
    </div>
    <div class="grid auction-grid">
        <?php foreach ($featuredVehicles as $vehicle): ?>
            <article class="card auction-card">
                <div class="auction-image-wrap">
                    <?php if (!empty($vehicle['image'])): ?>
                        <img class="vehicle-image" src="<?php echo BASE_URL . '/' . esc($vehicle['image']); ?>" alt="Vehicle">
                    <?php endif; ?>
                    <span class="auction-badge">OPEN</span>
                </div>
                <div class="auction-body">
                    <h3><?php echo esc($vehicle['brand'] . ' ' . $vehicle['model']); ?></h3>
                    <div class="auction-meta">
                        <span><?php echo esc($vehicle['category']); ?></span>
                        <span>Reg: <?php echo esc($vehicle['registration_no']); ?></span>
                    </div>
                    <div class="auction-prices">
                        <p><small>Base Price</small><strong>Rs <?php echo number_format((float)$vehicle['base_price'], 2); ?></strong></p>
                        <p><small>Highest Bid</small><strong>Rs <?php echo number_format((float)$vehicle['highest_bid'], 2); ?></strong></p>
                    </div>
                    <?php $auctionEndsAt = strtotime((string)$vehicle['created_at'] . ' +8 hours'); ?>
                    <p class="auction-countdown" data-countdown-end="<?php echo (int)$auctionEndsAt; ?>">
                        Time Left: --
                    </p>
                </div>
                <a class="btn auction-btn" href="<?php echo BASE_URL; ?>/vehicle.php?id=<?php echo (int)$vehicle['vehicle_id']; ?>">View Details</a>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
