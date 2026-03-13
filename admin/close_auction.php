<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/functions.php';

requireAdminLogin();

$vehicleId = (int)($_GET['id'] ?? 0);
$vehicle = getVehicleById($vehicleId);

if (!$vehicle) {
    flash('error', 'Vehicle not found.');
    redirect('admin/vehicles.php');
}

if ($vehicle['auction_status'] !== 'open') {
    flash('error', 'Auction is already closed.');
    redirect('admin/vehicles.php');
}

$highestBid = getHighestBidRow($vehicleId);

if ($highestBid) {
    $stmt = db()->prepare("UPDATE vehicles
                           SET auction_status = 'closed',
                               winner_user_id = :winner_user_id,
                               final_price = :final_price
                           WHERE vehicle_id = :vehicle_id");
    $stmt->execute([
        'winner_user_id' => $highestBid['user_id'],
        'final_price' => $highestBid['bid_amount'],
        'vehicle_id' => $vehicleId,
    ]);

    $smsSent = false;
    if (!empty($highestBid['phone'])) {
        $vehicleName = trim((string)($vehicle['brand'] ?? '') . ' ' . (string)($vehicle['model'] ?? ''));
        $registrationNo = (string)($vehicle['registration_no'] ?? '');
        $finalPrice = number_format((float)$highestBid['bid_amount'], 2);
        $smsMessage = 'Congratulations! You won the auction for ' . $vehicleName . ' (' . $registrationNo . ') at Rs ' . $finalPrice . '. Please complete payment in your account.';
        $smsSent = sendSmsToPhone((string)$highestBid['phone'], $smsMessage);
    }

    if (!$smsSent) {
        error_log('Winner SMS not sent for vehicle_id=' . $vehicleId . ' winner_user_id=' . (int)$highestBid['user_id']);
    }
    flash('success', 'Auction closed. Winner selected successfully.');
} else {
    $stmt = db()->prepare("UPDATE vehicles SET auction_status = 'closed', winner_user_id = NULL, final_price = NULL WHERE vehicle_id = :vehicle_id");
    $stmt->execute(['vehicle_id' => $vehicleId]);
    flash('success', 'Auction closed with no bids.');
}

redirect('admin/vehicles.php');
?>
