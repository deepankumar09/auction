<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/functions.php';

requireUserLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('place_bids.php');
}

$vehicleId = (int)($_POST['vehicle_id'] ?? 0);
$bidAmount = (float)($_POST['bid_amount'] ?? 0);
$userId = (int)$_SESSION['user_id'];

$userStmt = db()->prepare("SELECT user_id FROM users WHERE user_id = :user_id AND status = 'active' LIMIT 1");
$userStmt->execute(['user_id' => $userId]);
if (!$userStmt->fetch()) {
    unset($_SESSION['user_id'], $_SESSION['user_name']);
    flash('error', 'Your account session is invalid. Please login again.');
    redirect('login.php');
}

$vehicle = getVehicleById($vehicleId);
if (!$vehicle) {
    flash('error', 'Vehicle not found.');
    redirect('place_bids.php');
}

if ($vehicle['auction_status'] !== 'open') {
    flash('error', 'Auction is closed for this vehicle.');
    redirect('place_bids.php?id=' . $vehicleId);
}

$highest = getCurrentHighestBid($vehicleId);
$minimumAllowed = max($highest, (float)$vehicle['base_price']);
if ($bidAmount <= $minimumAllowed) {
    flash('error', 'Bid must be greater than current highest bid.');
    redirect('place_bids.php?id=' . $vehicleId);
}

try {
    $stmt = db()->prepare('INSERT INTO bids (vehicle_id, user_id, bid_amount) VALUES (:vehicle_id, :user_id, :bid_amount)');
    $stmt->execute([
        'vehicle_id' => $vehicleId,
        'user_id' => $userId,
        'bid_amount' => $bidAmount,
    ]);
} catch (PDOException $e) {
    flash('error', 'Unable to place bid right now. Please login again and retry.');
    redirect('login.php');
}

flash('success', 'Bid placed successfully.');
redirect('place_bids.php?id=' . $vehicleId);
?>
