<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/functions.php';

requireAdminLogin();

$vehicleId = (int)($_GET['vehicle_id'] ?? 0);
$vehicle = getVehicleById($vehicleId);

if (!$vehicle) {
    flash('error', 'Vehicle not found.');
    redirect('admin/payments.php');
}

$paidStmt = db()->prepare("SELECT payment_id FROM payments WHERE vehicle_id = :vehicle_id AND payment_status = 'paid' LIMIT 1");
$paidStmt->execute(['vehicle_id' => $vehicleId]);

if (!$paidStmt->fetch()) {
    flash('error', 'No successful payment found for this vehicle.');
    redirect('admin/payments.php');
}

$update = db()->prepare("UPDATE vehicles SET auction_status = 'sold' WHERE vehicle_id = :vehicle_id");
$update->execute(['vehicle_id' => $vehicleId]);

$winnerUserId = (int)($vehicle['winner_user_id'] ?? 0);
$smsSent = false;

if ($winnerUserId > 0) {
    $winnerStmt = db()->prepare('SELECT name, phone FROM users WHERE user_id = :user_id LIMIT 1');
    $winnerStmt->execute(['user_id' => $winnerUserId]);
    $winner = $winnerStmt->fetch();

    if ($winner && !empty($winner['phone'])) {
        $vehicleName = trim((string)($vehicle['brand'] ?? '') . ' ' . (string)($vehicle['model'] ?? ''));
        $registrationNo = (string)($vehicle['registration_no'] ?? '');
        $smsMessage = 'Congratulations! You won ' . $vehicleName . ' (' . $registrationNo . '). The vehicle is now marked as sold.';
        $smsSent = sendSmsToPhone((string)$winner['phone'], $smsMessage);
    }
}

if ($winnerUserId > 0 && !$smsSent) {
    flash('success', 'Vehicle marked as SOLD. Winner SMS could not be sent (check SMS settings).');
} else {
    flash('success', 'Vehicle marked as SOLD.');
}
redirect('admin/payments.php');
?>
