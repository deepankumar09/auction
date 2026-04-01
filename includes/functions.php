<?php
declare(strict_types=1);

require_once ROOT_PATH . '/includes/db.php';

function setLastEmailError(string $message): void
{
    $GLOBALS['LAST_EMAIL_ERROR'] = $message;
}

function getLastEmailError(): string
{
    $value = $GLOBALS['LAST_EMAIL_ERROR'] ?? '';
    return is_string($value) ? $value : '';
}

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

function ensureComplaintTable(): void
{
    static $alreadyEnsured = false;
    if ($alreadyEnsured) {
        return;
    }

    $tableExists = static function (string $tableName): bool {
        $stmt = db()->prepare('SHOW TABLES LIKE :table_name');
        $stmt->execute(['table_name' => $tableName]);
        return (bool)$stmt->fetchColumn();
    };

    if (!$tableExists('complaint')) {
        if ($tableExists('complaint_messages')) {
            db()->exec('RENAME TABLE complaint_messages TO complaint');
            $alreadyEnsured = true;
            return;
        }

        if ($tableExists('complaints')) {
            db()->exec('RENAME TABLE complaints TO complaint');
            $alreadyEnsured = true;
            return;
        }
    }

    db()->exec(
        "CREATE TABLE IF NOT EXISTS complaint (
            complaint_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            bid_id INT DEFAULT NULL,
            vehicle_id INT DEFAULT NULL,
            issue_type VARCHAR(50) NOT NULL,
            description TEXT NOT NULL,
            admin_reply TEXT DEFAULT NULL,
            status ENUM('open', 'in_progress', 'resolved') NOT NULL DEFAULT 'open',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
            FOREIGN KEY (bid_id) REFERENCES bids(bid_id) ON DELETE SET NULL,
            FOREIGN KEY (vehicle_id) REFERENCES vehicles(vehicle_id) ON DELETE SET NULL
        )"
    );

    $alreadyEnsured = true;
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
    $sql = 'SELECT b.*, u.name, u.email, u.phone
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

function sendWinnerEmailNotification(array $vehicle, array $highestBid): bool
{
    $winnerEmail = trim((string)($highestBid['email'] ?? ''));
    if (!filter_var($winnerEmail, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $winnerName = trim((string)($highestBid['name'] ?? 'Bidder'));
    $brand = trim((string)($vehicle['brand'] ?? ''));
    $model = trim((string)($vehicle['model'] ?? ''));
    $registrationNo = trim((string)($vehicle['registration_no'] ?? ''));
    $vehicleId = (int)($vehicle['vehicle_id'] ?? 0);
    $winningBid = (float)($highestBid['bid_amount'] ?? 0);
    $vehicleLabel = trim($brand . ' ' . $model);
    if ($vehicleLabel === '') {
        $vehicleLabel = 'Vehicle';
    }

    $subject = 'Congratulations! You won an auction on ' . APP_NAME;
    $message = "Hello {$winnerName},\n\n"
        . "Congratulations! You are the winning bidder.\n\n"
        . "Vehicle: {$vehicleLabel}\n"
        . "Registration No: {$registrationNo}\n"
        . "Vehicle ID: {$vehicleId}\n"
        . 'Winning Bid: Rs ' . number_format($winningBid, 2) . "\n\n"
        . "Please login and complete payment from My Wins page:\n"
        . BASE_URL . "/user/my_wins.php\n\n"
        . "Regards,\n"
        . APP_NAME . " Team";

    return sendEmailWithNodemailer($winnerEmail, $subject, $message);
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
            if ($closeStmt->rowCount() > 0) {
                $vehicle = getVehicleById($vehicleId);
                if ($vehicle && !sendWinnerEmailNotification($vehicle, $highestBid)) {
                    error_log('Winner email could not be sent for vehicle_id=' . $vehicleId . ' winner_user_id=' . (int)$highestBid['user_id']);
                }
            }
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

function handleImageUpload(
    string $inputName,
    string $folder,
    int $minBytes = 10240,
    int $maxBytes = 20971520
): ?string
{
    if (empty($_FILES[$inputName]['name']) || !is_uploaded_file($_FILES[$inputName]['tmp_name'])) {
        return null;
    }

    $size = (int)($_FILES[$inputName]['size'] ?? 0);
    if ($size < $minBytes || $size > $maxBytes) {
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

function sendEmailWithNodemailer(string $to, string $subject, string $text): bool
{
    setLastEmailError('');
    $nodeBin = (string)(defined('NODE_BIN') ? NODE_BIN : 'node');
    $scriptPath = ROOT_PATH . '/mailer/send-email.js';

    if (!is_file($scriptPath)) {
        $msg = 'Nodemailer script not found at: ' . $scriptPath;
        error_log($msg);
        setLastEmailError($msg);
        return false;
    }

    $smtpPort = (int)(defined('SMTP_PORT') ? SMTP_PORT : 587);
    $smtpSecureRaw = defined('SMTP_SECURE') ? SMTP_SECURE : false;
    $smtpSecure = false;
    if (is_bool($smtpSecureRaw)) {
        $smtpSecure = $smtpSecureRaw;
    } elseif (is_numeric($smtpSecureRaw)) {
        $smtpSecure = ((int)$smtpSecureRaw) === 1;
    } elseif (is_string($smtpSecureRaw)) {
        $normalized = strtolower(trim($smtpSecureRaw));
        $smtpSecure = in_array($normalized, ['1', 'true', 'yes', 'on', 'ssl', 'smtps'], true);
    }
    $smtpHost = (string)(defined('SMTP_HOST') ? SMTP_HOST : '');
    $smtpUser = (string)(defined('SMTP_USER') ? SMTP_USER : '');
    $smtpPass = (string)(defined('SMTP_PASS') ? SMTP_PASS : '');
    $fromEmail = (string)(defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : $smtpUser);
    $fromName = (string)(defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : APP_NAME);

    if ($smtpHost === '' || $smtpUser === '' || $smtpPass === '' || $fromEmail === '') {
        $msg = 'SMTP settings are incomplete in config/config.php';
        error_log($msg);
        setLastEmailError($msg);
        return false;
    }

    $payload = [
        'smtp' => [
            'host' => $smtpHost,
            'port' => $smtpPort,
            'secure' => $smtpSecure,
            'user' => $smtpUser,
            'pass' => $smtpPass,
            'fromEmail' => $fromEmail,
            'fromName' => $fromName,
        ],
        'mail' => [
            'to' => $to,
            'subject' => $subject,
            'text' => $text,
        ],
    ];

    $encoded = base64_encode((string)json_encode($payload));
    if ($encoded === '') {
        setLastEmailError('Failed to encode email payload.');
        return false;
    }

    $command = escapeshellarg($nodeBin) . ' ' . escapeshellarg($scriptPath) . ' ' . escapeshellarg($encoded) . ' 2>&1';
    $output = [];
    $exitCode = 1;
    exec($command, $output, $exitCode);

    if ($exitCode !== 0) {
        $rawOutput = trim(implode(PHP_EOL, $output));
        $msg = 'Nodemailer send failed. Output: ' . $rawOutput;
        error_log($msg);
        setLastEmailError($rawOutput !== '' ? $rawOutput : 'Unknown mail send error.');
        return false;
    }

    return true;
}
?>
