<?php
declare(strict_types=1);

require_once ROOT_PATH . '/includes/db.php';

function esc(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function getCurrentHighestBid(int $vehicleId): float
{
    $stmt = db()->prepare('SELECT MAX(bid_amount) AS highest_bid FROM bids WHERE vehicle_id = :vehicle_id');
    $stmt->execute(['vehicle_id' => $vehicleId]);
    $row = $stmt->fetch();
    return (float)($row['highest_bid'] ?? 0);
}

function getVehicleById(int $vehicleId): ?array
{
    $stmt = db()->prepare('SELECT * FROM vehicles WHERE vehicle_id = :vehicle_id LIMIT 1');
    $stmt->execute(['vehicle_id' => $vehicleId]);
    $vehicle = $stmt->fetch();
    return $vehicle ?: null;
}

function getHighestBidRow(int $vehicleId): ?array
{
    $sql = 'SELECT b.*, u.name, u.email
            FROM bids b
            JOIN users u ON u.user_id = b.user_id
            WHERE b.vehicle_id = :vehicle_id
            ORDER BY b.bid_amount DESC, b.bid_time ASC
            LIMIT 1';
    $stmt = db()->prepare($sql);
    $stmt->execute(['vehicle_id' => $vehicleId]);
    $bid = $stmt->fetch();
    return $bid ?: null;
}

function autoCloseExpiredAuctions(): void
{
    static $alreadyRun = false;
    if ($alreadyRun) {
        return;
    }
    $alreadyRun = true;

    $expiredStmt = db()->query(
        "SELECT vehicle_id
         FROM vehicles
         WHERE auction_status = 'open'
           AND created_at <= DATE_SUB(NOW(), INTERVAL 8 HOUR)"
    );
    $expiredVehicles = $expiredStmt->fetchAll();
    if (!$expiredVehicles) {
        return;
    }

    foreach ($expiredVehicles as $row) {
        $vehicleId = (int)$row['vehicle_id'];
        $highestBid = getHighestBidRow($vehicleId);

        if ($highestBid) {
            $closeStmt = db()->prepare(
                "UPDATE vehicles
                 SET auction_status = 'closed',
                     winner_user_id = :winner_user_id,
                     final_price = :final_price
                 WHERE vehicle_id = :vehicle_id
                   AND auction_status = 'open'"
            );
            $closeStmt->execute([
                'winner_user_id' => (int)$highestBid['user_id'],
                'final_price' => (float)$highestBid['bid_amount'],
                'vehicle_id' => $vehicleId,
            ]);
        } else {
            $closeStmt = db()->prepare(
                "UPDATE vehicles
                 SET auction_status = 'closed',
                     winner_user_id = NULL,
                     final_price = NULL
                 WHERE vehicle_id = :vehicle_id
                   AND auction_status = 'open'"
            );
            $closeStmt->execute(['vehicle_id' => $vehicleId]);
        }
    }
}

function handleImageUpload(string $inputName, string $folder): ?string
{
    if (empty($_FILES[$inputName]['name']) || !is_uploaded_file($_FILES[$inputName]['tmp_name'])) {
        return null;
    }

    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    $ext = strtolower(pathinfo($_FILES[$inputName]['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) {
        return null;
    }

    $filename = $folder . '_' . uniqid('', true) . '.' . $ext;
    $targetPath = ROOT_PATH . '/uploads/' . $folder . '/' . $filename;
    if (!move_uploaded_file($_FILES[$inputName]['tmp_name'], $targetPath)) {
        return null;
    }

    return 'uploads/' . $folder . '/' . $filename;
}

function createRazorpayOrder(int $amountInPaise, string $receipt): ?array
{
    if (!function_exists('curl_init')) {
        return null;
    }

    $payload = json_encode([
        'amount' => $amountInPaise,
        'currency' => 'INR',
        'receipt' => $receipt,
        'payment_capture' => 1,
    ]);

    $ch = curl_init('https://api.razorpay.com/v1/orders');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_USERPWD => RAZORPAY_KEY_ID . ':' . RAZORPAY_KEY_SECRET,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 && $httpCode !== 201) {
        return null;
    }

    $result = json_decode((string)$response, true);
    return is_array($result) ? $result : null;
}

function verifyRazorpaySignature(string $orderId, string $paymentId, string $signature): bool
{
    $payload = $orderId . '|' . $paymentId;
    $generated = hash_hmac('sha256', $payload, RAZORPAY_KEY_SECRET);
    return hash_equals($generated, $signature);
}
?>
