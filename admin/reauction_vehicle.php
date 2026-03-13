<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/functions.php';

requireAdminLogin();

$vehicleId = (int)($_GET['vehicle_id'] ?? 0);
if ($vehicleId <= 0) {
    flash('error', 'Invalid vehicle.');
    redirect('admin/payments.php');
}

$vehicleStmt = db()->prepare('SELECT vehicle_id, auction_status FROM vehicles WHERE vehicle_id = :vehicle_id LIMIT 1');
$vehicleStmt->execute(['vehicle_id' => $vehicleId]);
$vehicle = $vehicleStmt->fetch();

if (!$vehicle) {
    flash('error', 'Vehicle not found.');
    redirect('admin/payments.php');
}

if ($vehicle['auction_status'] === 'sold') {
    flash('error', 'Sold vehicles cannot be reopened.');
    redirect('admin/payments.php');
}

$paidStmt = db()->prepare("SELECT payment_id FROM payments WHERE vehicle_id = :vehicle_id AND payment_status = 'paid' LIMIT 1");
$paidStmt->execute(['vehicle_id' => $vehicleId]);
if ($paidStmt->fetch()) {
    flash('error', 'Payment already completed. Cannot re-auction this vehicle.');
    redirect('admin/payments.php');
}

try {
    db()->beginTransaction();

    $deletePayments = db()->prepare('DELETE FROM payments WHERE vehicle_id = :vehicle_id');
    $deletePayments->execute(['vehicle_id' => $vehicleId]);

    $deleteBids = db()->prepare('DELETE FROM bids WHERE vehicle_id = :vehicle_id');
    $deleteBids->execute(['vehicle_id' => $vehicleId]);

    $reopen = db()->prepare(
        "UPDATE vehicles
         SET auction_status = 'open',
             winner_user_id = NULL,
             final_price = NULL,
             created_at = NOW()
         WHERE vehicle_id = :vehicle_id"
    );
    $reopen->execute(['vehicle_id' => $vehicleId]);

    db()->commit();
    flash('success', 'Vehicle moved back to auction successfully.');
} catch (PDOException $e) {
    if (db()->inTransaction()) {
        db()->rollBack();
    }
    throw $e;
}

redirect('admin/payments.php');
?>
