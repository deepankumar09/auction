<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/functions.php';

requireUserLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('user/my_wins.php');
}

$paymentId = trim($_POST['razorpay_payment_id'] ?? '');
$orderId = trim($_POST['razorpay_order_id'] ?? '');
$signature = trim($_POST['razorpay_signature'] ?? '');
$userId = (int)$_SESSION['user_id'];

if ($paymentId === '' || $orderId === '' || $signature === '') {
    flash('error', 'Invalid payment response.');
    redirect('user/my_wins.php');
}

$paymentStmt = db()->prepare('SELECT * FROM payments WHERE razorpay_order_id = :order_id AND user_id = :user_id LIMIT 1');
$paymentStmt->execute(['order_id' => $orderId, 'user_id' => $userId]);
$payment = $paymentStmt->fetch();

if (!$payment) {
    flash('error', 'Payment record not found.');
    redirect('user/my_wins.php');
}

if (!verifyRazorpaySignature($orderId, $paymentId, $signature)) {
    $fail = db()->prepare("UPDATE payments SET payment_status = 'failed' WHERE payment_id = :payment_id");
    $fail->execute(['payment_id' => $payment['payment_id']]);
    flash('error', 'Payment verification failed.');
    redirect('user/my_wins.php');
}

$update = db()->prepare("UPDATE payments
                         SET razorpay_payment_id = :payment_id,
                             razorpay_signature = :signature,
                             payment_status = 'paid',
                             payment_date = NOW()
                         WHERE payment_id = :row_id");
$update->execute([
    'payment_id' => $paymentId,
    'signature' => $signature,
    'row_id' => $payment['payment_id'],
]);

flash('success', 'Payment successful. You can download invoice now.');
redirect('user/my_wins.php');
?>
