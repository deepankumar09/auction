<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/functions.php';

requireUserLogin();

$stmt = db()->query("SELECT vehicle_id
                     FROM vehicles
                     WHERE auction_status = 'open'
                     ORDER BY vehicle_id DESC
                     LIMIT 1");
$vehicleId = (int)($stmt->fetchColumn() ?: 0);

if ($vehicleId <= 0) {
    flash('error', 'No open auction is available for bidding right now.');
    redirect('vehicles.php');
}

redirect('vehicle.php?id=' . $vehicleId);
?>
