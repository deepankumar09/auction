<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/functions.php';

requireUserLogin();

$vehicleId = (int)($_GET['vehicle_id'] ?? 0);
$userId = (int)$_SESSION['user_id'];

$stmt = db()->prepare('SELECT * FROM vehicles WHERE vehicle_id = :vehicle_id AND winner_user_id = :user_id LIMIT 1');
$stmt->execute(['vehicle_id' => $vehicleId, 'user_id' => $userId]);
$vehicle = $stmt->fetch();

if (!$vehicle) {
    flash('error', 'This payment is not available for your account.');
    redirect('user/my_wins.php');
}

$checkPaid = db()->prepare("SELECT * FROM payments WHERE vehicle_id = :vehicle_id AND user_id = :user_id AND payment_status = 'paid' LIMIT 1");
$checkPaid->execute(['vehicle_id' => $vehicleId, 'user_id' => $userId]);
if ($checkPaid->fetch()) {
    flash('success', 'Payment already completed.');
    redirect('user/my_wins.php');
}

$amount = (float)($vehicle['final_price'] ?? 0);
if ($amount <= 0) {
    flash('error', 'Final price is not set for this vehicle.');
    redirect('user/my_wins.php');
}

$paymentStmt = db()->prepare("SELECT * FROM payments WHERE vehicle_id = :vehicle_id AND user_id = :user_id AND payment_status = 'created' ORDER BY payment_id DESC LIMIT 1");
$paymentStmt->execute(['vehicle_id' => $vehicleId, 'user_id' => $userId]);
$payment = $paymentStmt->fetch();

if (!$payment) {
    $order = createRazorpayOrder((int)round($amount * 100), 'veh_' . $vehicleId . '_user_' . $userId);
    if (!$order || empty($order['id'])) {
        flash('error', 'Unable to create Razorpay order. Check API keys in config/config.php.');
        redirect('user/my_wins.php');
    }

    $insert = db()->prepare('INSERT INTO payments (vehicle_id, user_id, amount, razorpay_order_id, payment_status) VALUES (:vehicle_id, :user_id, :amount, :razorpay_order_id, :payment_status)');
    $insert->execute([
        'vehicle_id' => $vehicleId,
        'user_id' => $userId,
        'amount' => $amount,
        'razorpay_order_id' => $order['id'],
        'payment_status' => 'created',
    ]);

    $paymentId = (int)db()->lastInsertId();
    $payment = [
        'payment_id' => $paymentId,
        'razorpay_order_id' => $order['id'],
        'amount' => $amount,
    ];
}

$userStmt = db()->prepare('SELECT name, email, phone FROM users WHERE user_id = :user_id');
$userStmt->execute(['user_id' => $userId]);
$user = $userStmt->fetch();

$pageTitle = 'Pay Now';
require ROOT_PATH . '/includes/header.php';
?>
<section class="card">
    <h2>Razorpay Payment</h2>
    <p><strong>Vehicle:</strong> <?php echo esc($vehicle['brand'] . ' ' . $vehicle['model']); ?></p>
    <p><strong>Amount:</strong> Rs <?php echo number_format((float)$payment['amount'], 2); ?></p>
    <button id="rzp-button" class="btn">Pay Securely</button>
</section>

<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
    const options = {
        key: "<?php echo esc(RAZORPAY_KEY_ID); ?>",
        amount: "<?php echo (int)round(((float)$payment['amount']) * 100); ?>",
        currency: "INR",
        name: "Seized Vehicle Auction",
        description: "Vehicle Auction Payment",
        order_id: "<?php echo esc($payment['razorpay_order_id']); ?>",
        prefill: {
            name: "<?php echo esc($user['name'] ?? ''); ?>",
            email: "<?php echo esc($user['email'] ?? ''); ?>",
            contact: "<?php echo esc($user['phone'] ?? ''); ?>"
        },
        handler: function (response) {
            const form = document.createElement("form");
            form.method = "POST";
            form.action = "<?php echo BASE_URL; ?>/user/verify_payment.php";
            ["razorpay_payment_id", "razorpay_order_id", "razorpay_signature"].forEach((key) => {
                const input = document.createElement("input");
                input.type = "hidden";
                input.name = key;
                input.value = response[key];
                form.appendChild(input);
            });
            document.body.appendChild(form);
            form.submit();
        }
    };

    const rzp = new Razorpay(options);
    document.getElementById("rzp-button").onclick = function (e) {
        rzp.open();
        e.preventDefault();
    };
</script>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
