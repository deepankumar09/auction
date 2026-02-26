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

flash('success', 'Vehicle marked as SOLD.');
redirect('admin/payments.php');
?>
