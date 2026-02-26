<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/functions.php';

requireAdminLogin();

$stats = [
    'vehicles' => (int)db()->query('SELECT COUNT(*) FROM vehicles')->fetchColumn(),
    'open' => (int)db()->query("SELECT COUNT(*) FROM vehicles WHERE auction_status = 'open'")->fetchColumn(),
    'bids' => (int)db()->query('SELECT COUNT(*) FROM bids')->fetchColumn(),
    'paid' => (int)db()->query("SELECT COUNT(*) FROM payments WHERE payment_status = 'paid'")->fetchColumn(),
];

$recentVehicles = db()->query('SELECT * FROM vehicles ORDER BY vehicle_id DESC LIMIT 8')->fetchAll();

$pageTitle = 'Admin Dashboard';
require ROOT_PATH . '/includes/header.php';
?>
<section class="card">
    <h2>Dashboard</h2>
    <div class="grid">
        <div class="card"><h3>Total Vehicles</h3><p><?php echo $stats['vehicles']; ?></p></div>
        <div class="card"><h3>Open Auctions</h3><p><?php echo $stats['open']; ?></p></div>
        <div class="card"><h3>Total Bids</h3><p><?php echo $stats['bids']; ?></p></div>
        <div class="card"><h3>Paid Orders</h3><p><?php echo $stats['paid']; ?></p></div>
    </div>
</section>

<section class="card">
    <h3>Recent Vehicles</h3>
    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>Vehicle</th>
            <th>Category</th>
            <th>Reg No</th>
            <th>Status</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($recentVehicles as $v): ?>
            <tr>
                <td><?php echo (int)$v['vehicle_id']; ?></td>
                <td><?php echo esc($v['brand'] . ' ' . $v['model']); ?></td>
                <td><?php echo esc($v['category']); ?></td>
                <td><?php echo esc($v['registration_no']); ?></td>
                <td><?php echo strtoupper(esc($v['auction_status'])); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
