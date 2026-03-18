<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/functions.php';

requireAdminLogin();

$stats = [
    'vehicles' => (int)db()->query('SELECT COUNT(*) FROM vehicles')->fetchColumn(),
    'open' => (int)db()->query("SELECT COUNT(*) FROM vehicles WHERE auction_status = 'open'")->fetchColumn(),
    'closed' => (int)db()->query("SELECT COUNT(*) FROM vehicles WHERE auction_status = 'closed'")->fetchColumn(),
    'sold' => (int)db()->query("SELECT COUNT(*) FROM vehicles WHERE auction_status = 'sold'")->fetchColumn(),
    'bids' => (int)db()->query('SELECT COUNT(*) FROM bids')->fetchColumn(),
    'paid' => (int)db()->query("SELECT COUNT(*) FROM payments WHERE payment_status = 'paid'")->fetchColumn(),
    'users' => (int)db()->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn(),
    'ads' => (int)db()->query("SELECT COUNT(*) FROM advertisements WHERE status = 'active'")->fetchColumn(),
    'revenue' => (float)db()->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payment_status = 'paid'")->fetchColumn(),
];
$totalVehicles = max((int)$stats['vehicles'], 1);
$openPercent = (int)round(((int)$stats['open'] / $totalVehicles) * 100);
$closedPercent = (int)round(((int)$stats['closed'] / $totalVehicles) * 100);
$soldPercent = (int)round(((int)$stats['sold'] / $totalVehicles) * 100);

$pageTitle = 'Admin Dashboard';
require ROOT_PATH . '/includes/header.php';
?>
<section class="card admin-dash-hero">
    <div>
        <h2>Admin Dashboard</h2>
        <p>Monitor auctions, payments, bidding activity, and platform growth in one dashboard.</p>
    </div>
    <div class="admin-dash-quick-actions">
        <a class="btn" href="<?php echo BASE_URL; ?>/admin/add_vehicle.php">Add Vehicle</a>
        <a class="btn btn-secondary" href="<?php echo BASE_URL; ?>/admin/vehicles.php">Manage Auctions</a>
        <a class="btn btn-secondary" href="<?php echo BASE_URL; ?>/index.php">Go to Home Page</a>
    </div>
</section>

<section class="admin-dash-stats">
    <article class="card admin-dash-stat-card">
        <p>Total Vehicles</p>
        <strong><?php echo (int)$stats['vehicles']; ?></strong>
    </article>
    <article class="card admin-dash-stat-card">
        <p>Open Auctions</p>
        <strong><?php echo (int)$stats['open']; ?></strong>
    </article>
    <article class="card admin-dash-stat-card">
        <p>Total Bids</p>
        <strong><?php echo (int)$stats['bids']; ?></strong>
    </article>
    <article class="card admin-dash-stat-card">
        <p>Paid Orders</p>
        <strong><?php echo (int)$stats['paid']; ?></strong>
    </article>
    <article class="card admin-dash-stat-card">
        <p>Active Users</p>
        <strong><?php echo (int)$stats['users']; ?></strong>
    </article>
    <article class="card admin-dash-stat-card">
        <p>Active Ads</p>
        <strong><?php echo (int)$stats['ads']; ?></strong>
    </article>
    <article class="card admin-dash-stat-card admin-dash-stat-revenue">
        <p>Revenue Collected</p>
        <strong>Rs <?php echo number_format((float)$stats['revenue'], 2); ?></strong>
    </article>
</section>

<section class="admin-dash-simple-grid">
    <article class="card admin-dash-simple-card">
        <h3>Quick Actions</h3>
        <div class="admin-dash-simple-links">
            <a href="<?php echo BASE_URL; ?>/admin/add_vehicle.php">Add New Vehicle</a>
            <a href="<?php echo BASE_URL; ?>/admin/manage_bids.php">Review Bids</a>
            <a href="<?php echo BASE_URL; ?>/admin/payments.php">Check Payments</a>
            <a href="<?php echo BASE_URL; ?>/admin/defaulter_records.php">Defaulter Records</a>
            <a href="<?php echo BASE_URL; ?>/admin/ads.php">Manage Ads</a>
            <a href="<?php echo BASE_URL; ?>/admin/vehicles.php">Control Auctions</a>
        </div>
    </article>

    <article class="card admin-dash-simple-card">
        <h3>Auction Summary</h3>
        <div class="admin-dash-simple-metric">
            <span>Open</span>
            <div><i style="width: <?php echo $openPercent; ?>%;"></i></div>
            <strong><?php echo (int)$stats['open']; ?> (<?php echo $openPercent; ?>%)</strong>
        </div>
        <div class="admin-dash-simple-metric">
            <span>Closed</span>
            <div><i class="is-closed" style="width: <?php echo $closedPercent; ?>%;"></i></div>
            <strong><?php echo (int)$stats['closed']; ?> (<?php echo $closedPercent; ?>%)</strong>
        </div>
        <div class="admin-dash-simple-metric">
            <span>Sold</span>
            <div><i class="is-sold" style="width: <?php echo $soldPercent; ?>%;"></i></div>
            <strong><?php echo (int)$stats['sold']; ?> (<?php echo $soldPercent; ?>%)</strong>
        </div>
    </article>
</section>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
