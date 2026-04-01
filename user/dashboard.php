<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/functions.php';

requireUserLogin();

$userId = (int)$_SESSION['user_id'];
$userName = trim((string)($_SESSION['user_name'] ?? 'User'));

$statsStmt = db()->prepare(
    "SELECT
        (SELECT COUNT(*) FROM bids WHERE user_id = :user_id) AS total_bids,
        (SELECT COUNT(DISTINCT b.vehicle_id)
         FROM bids b
         JOIN vehicles v ON v.vehicle_id = b.vehicle_id
         WHERE b.user_id = :user_id AND v.auction_status = 'open') AS active_bids,
        (SELECT COUNT(*) FROM vehicles WHERE winner_user_id = :user_id) AS wins,
        (SELECT COUNT(*)
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
           AND COALESCE(p.payment_status, 'pending') <> 'paid') AS pending_payments,
        (SELECT COALESCE(SUM(amount), 0)
         FROM payments
         WHERE user_id = :user_id AND payment_status = 'paid') AS total_paid"
);
$statsStmt->execute(['user_id' => $userId]);
$stats = $statsStmt->fetch() ?: [];

$recentBidsStmt = db()->prepare(
    "SELECT b.bid_amount, b.bid_time, v.vehicle_id, v.brand, v.model, v.auction_status
     FROM bids b
     JOIN vehicles v ON v.vehicle_id = b.vehicle_id
     WHERE b.user_id = :user_id
     ORDER BY b.bid_time DESC
     LIMIT 6"
);
$recentBidsStmt->execute(['user_id' => $userId]);
$recentBids = $recentBidsStmt->fetchAll();

$winsStmt = db()->prepare(
    "SELECT v.vehicle_id, v.brand, v.model, v.registration_no,
            COALESCE(v.final_price, hb.max_bid, v.base_price) AS payable_amount,
            p.payment_id, p.payment_status
     FROM vehicles v
     LEFT JOIN (
         SELECT vehicle_id, MAX(bid_amount) AS max_bid
         FROM bids
         GROUP BY vehicle_id
     ) hb ON hb.vehicle_id = v.vehicle_id
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
     ORDER BY v.vehicle_id DESC
     LIMIT 6"
);
$winsStmt->execute(['user_id' => $userId]);
$wins = $winsStmt->fetchAll();

$openAuctionsStmt = db()->prepare(
    "SELECT v.vehicle_id, v.brand, v.model, v.category, v.base_price
     FROM vehicles v
     WHERE v.auction_status = 'open'
       AND v.vehicle_id NOT IN (
           SELECT b.vehicle_id
           FROM bids b
           WHERE b.user_id = :user_id
       )
     ORDER BY v.vehicle_id DESC
     LIMIT 4"
);
$openAuctionsStmt->execute(['user_id' => $userId]);
$openAuctions = $openAuctionsStmt->fetchAll();

ensureComplaintTable();
$complaintsCountStmt = db()->prepare(
    "SELECT COUNT(*)
     FROM complaint
     WHERE user_id = :user_id
       AND status <> 'resolved'"
);
$complaintsCountStmt->execute(['user_id' => $userId]);
$openComplaintsCount = (int)$complaintsCountStmt->fetchColumn();

$pageTitle = 'User Dashboard';
require ROOT_PATH . '/includes/header.php';
?>
<section class="user-dash-hero card">
    <div>
        <h2>Welcome, <?php echo esc($userName); ?></h2>
        <p>Track your bids, payments, and won auctions in one place.</p>
    </div>
    <a class="btn" href="<?php echo BASE_URL; ?>/place_bids.php">Explore Live Auctions</a>
</section>

<section class="user-dash-stats">
    <article class="card user-stat-card">
        <p class="user-stat-label">Total Bids</p>
        <p class="user-stat-value"><?php echo (int)($stats['total_bids'] ?? 0); ?></p>
    </article>
    <article class="card user-stat-card">
        <p class="user-stat-label">Active Bids</p>
        <p class="user-stat-value"><?php echo (int)($stats['active_bids'] ?? 0); ?></p>
    </article>
    <article class="card user-stat-card">
        <p class="user-stat-label">Won Auctions</p>
        <p class="user-stat-value"><?php echo (int)($stats['wins'] ?? 0); ?></p>
    </article>
    <article class="card user-stat-card">
        <p class="user-stat-label">Pending Payments</p>
        <p class="user-stat-value"><?php echo (int)($stats['pending_payments'] ?? 0); ?></p>
    </article>
    <article class="card user-stat-card">
        <p class="user-stat-label">Total Paid</p>
        <p class="user-stat-value">Rs <?php echo number_format((float)($stats['total_paid'] ?? 0), 2); ?></p>
    </article>
    <article class="card user-stat-card">
        <p class="user-stat-label">Open Complaints</p>
        <p class="user-stat-value"><?php echo $openComplaintsCount; ?></p>
    </article>
</section>

<section class="user-dash-panels">
    <article class="card user-dash-panel">
        <div class="user-dash-panel-head">
            <h3>Recent Bids</h3>
            <a href="<?php echo BASE_URL; ?>/place_bids.php">Place Bid</a>
        </div>
        <?php if (!$recentBids): ?>
            <p class="user-dash-empty">No bids yet. Start with an open auction.</p>
        <?php else: ?>
            <div class="user-bid-list">
                <?php foreach ($recentBids as $bid): ?>
                    <div class="user-bid-item">
                        <div>
                            <strong><?php echo esc($bid['brand'] . ' ' . $bid['model']); ?></strong>
                            <small><?php echo esc($bid['bid_time']); ?></small>
                        </div>
                        <div class="user-bid-right">
                            <strong>Rs <?php echo number_format((float)$bid['bid_amount'], 2); ?></strong>
                            <span class="status status-<?php echo esc((string)$bid['auction_status']); ?>">
                                <?php echo strtoupper(esc((string)$bid['auction_status'])); ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </article>

    <article class="card user-dash-panel">
        <div class="user-dash-panel-head">
            <h3>My Wins & Payments</h3>
            <a href="<?php echo BASE_URL; ?>/user/my_wins.php">View All</a>
        </div>
        <?php if (!$wins): ?>
            <p class="user-dash-empty">No won auctions yet.</p>
        <?php else: ?>
            <div class="user-bid-list">
                <?php foreach ($wins as $win): ?>
                    <div class="user-bid-item">
                        <div>
                            <strong><?php echo esc($win['brand'] . ' ' . $win['model']); ?></strong>
                            <small><?php echo esc($win['registration_no']); ?></small>
                        </div>
                        <div class="user-bid-right">
                            <strong>Rs <?php echo number_format((float)$win['payable_amount'], 2); ?></strong>
                            <?php if (($win['payment_status'] ?? '') === 'paid'): ?>
                                <a class="btn btn-secondary" href="<?php echo BASE_URL; ?>/user/invoice.php?payment_id=<?php echo (int)$win['payment_id']; ?>">Invoice</a>
                            <?php else: ?>
                                <a class="btn" href="<?php echo BASE_URL; ?>/user/pay.php?vehicle_id=<?php echo (int)$win['vehicle_id']; ?>">Pay Now</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </article>
</section>

<section class="card user-dash-panel">
    <div class="user-dash-panel-head">
        <h3>Suggested Open Auctions</h3>
        <a href="<?php echo BASE_URL; ?>/vehicles.php">Browse Vehicles</a>
    </div>
    <?php if (!$openAuctions): ?>
        <p class="user-dash-empty">You have explored most current open auctions.</p>
    <?php else: ?>
        <div class="user-suggest-grid">
            <?php foreach ($openAuctions as $auction): ?>
                <article class="user-suggest-card">
                    <h4><?php echo esc($auction['brand'] . ' ' . $auction['model']); ?></h4>
                    <p><?php echo esc($auction['category']); ?></p>
                    <p>Starts at Rs <?php echo number_format((float)$auction['base_price'], 2); ?></p>
                    <a class="btn" href="<?php echo BASE_URL; ?>/place_bids.php?id=<?php echo (int)$auction['vehicle_id']; ?>">Bid Now</a>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="card user-dash-panel">
    <div class="user-dash-panel-head">
        <h3>Support Messages</h3>
        <a href="<?php echo BASE_URL; ?>/user/complaints.php">Open Module</a>
    </div>
    <p class="user-dash-empty">
        Report wrong bids, payment issues, or other problems. Admin replies will appear in your complaint history.
    </p>
</section>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
