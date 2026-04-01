<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/functions.php';

$ads = db()->query("SELECT * FROM advertisements WHERE status = 'active' ORDER BY ad_id DESC LIMIT 3")->fetchAll();

$pageTitle = 'Home';
require ROOT_PATH . '/includes/header.php';
?>

<section class="hero">
    <h1 class="hero-title">
        <img src="<?php echo BASE_URL; ?>/assets/images/logo.png" alt="Logo">
        <span>Online Auction Platform For Bank Seized Vehicles</span>
    </h1>
    <p>Browse verified bank seized vehicles, place live bids, and complete secure payment.</p>
    <a class="btn" href="<?php echo BASE_URL; ?>/vehicles.php">Start Bidding</a>
</section>

<?php if ($ads): ?>
    <section class="latest-ads">
        <?php foreach ($ads as $ad): ?>
            <article class="latest-ad-item ad-banner-box">
                <?php if (!empty($ad['title'])): ?>
                    <h3 class="ad-title"><?php echo esc($ad['title']); ?></h3>
                <?php endif; ?>
                <?php if (!empty($ad['description'])): ?>
                    <p class="ad-description"><?php echo esc($ad['description']); ?></p>
                <?php endif; ?>
                <?php if (!empty($ad['image'])): ?>
                    <a class="ad-banner-link" href="<?php echo BASE_URL . '/' . esc($ad['image']); ?>" target="_blank" rel="noopener noreferrer">
                        <img
                            src="<?php echo BASE_URL . '/' . esc($ad['image']); ?>"
                            alt="Ad Banner"
                            class="ad-banner"
                        >
                    </a>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<section class="home-info-grid">
    <article class="card home-info-card">
        <h2>About Bank Auction</h2>
        <p>Bank Auction is a transparent online platform for buying bank-seized bikes and cars through monitored bidding. Every listing includes clear vehicle details, pricing information, and bid status so buyers can participate with confidence.</p>
    </article>
    <article class="card home-info-card">
        <h2>Who Can Use It?</h2>
        <p>Anyone can browse listed vehicles. Registered users can place bids and track their winning vehicles from a single dashboard. Admins can manage auctions, advertisements, bids, and payments from the control panel.</p>
    </article>
</section>

<section class="card home-process">
    <h2>How It Works</h2>
    <div class="home-steps">
        <article>
            <h3>1. Register & Login</h3>
            <p>Create your account to unlock bidding and payment actions.</p>
        </article>
        <article>
            <h3>2. Explore Vehicles</h3>
            <p>Filter bikes and cars, compare base price and current highest bid.</p>
        </article>
        <article>
            <h3>3. Place Competitive Bids</h3>
            <p>Bid on open auctions and monitor progress in real time.</p>
        </article>
        <article>
            <h3>4. Complete Payment</h3>
            <p>Winners can finish payment securely and receive confirmation.</p>
        </article>
    </div>
</section>

<section class="home-highlights">
    <article class="card home-highlight-card">
        <h3>Verified Listings</h3>
        <p>Vehicle details are maintained by admin for clarity and consistency.</p>
    </article>
    <article class="card home-highlight-card">
        <h3>Live Auction Status</h3>
        <p>Open and closed auction states are shown clearly for every listing.</p>
    </article>
    <article class="card home-highlight-card">
        <h3>Secure Workflow</h3>
        <p>From login to payment, each step is organized for a safe experience.</p>
    </article>
</section>

<section class="card home-complaints">
    <div class="home-complaints-head">
        <div>
            <h2>Complaints</h2>
        </div>
    </div>
    <div class="home-complaints-grid">
        <article>
            <h3>1. Raise Complaint</h3>
            <p>Users can report wrong bids or payment issues to admin.</p>
        </article>
        <article>
            <h3>2. Admin Review</h3>
            <p>Admin checks the complaint details and takes action.</p>
        </article>
        <article>
            <h3>3. View Reply</h3>
            <p>Users can track complaint status and see admin reply.</p>
        </article>
    </div>
</section>

<section class="card home-tips">
    <h2>Quick Bidding Tips</h2>
    <ul>
        <li>Check registration details and vehicle condition before bidding.</li>
        <li>Set a budget and increase bids strategically, not emotionally.</li>
        <li>Track auction status and timing to avoid last-minute confusion.</li>
        <li>If you win, complete payment and claim the vehicle.</li>
    </ul>
</section>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
