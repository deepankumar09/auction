<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/functions.php';

requireAdminLogin();

db()->exec(
    "CREATE TABLE IF NOT EXISTS defaulters (
        defaulter_id INT AUTO_INCREMENT PRIMARY KEY,
        vehicle_id INT NOT NULL UNIQUE,
        defaulter_name VARCHAR(150) NOT NULL,
        loan_account_number VARCHAR(100) NOT NULL,
        bank_name VARCHAR(150) NOT NULL,
        loan_amount DECIMAL(12,2) NOT NULL,
        paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        pending_amount DECIMAL(12,2) NOT NULL,
        seizure_date DATE NOT NULL,
        reason_for_seizure TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (vehicle_id) REFERENCES vehicles(vehicle_id) ON DELETE CASCADE
    )"
);

$paidAmountColumnStmt = db()->query("SHOW COLUMNS FROM defaulters LIKE 'paid_amount'");
if (!$paidAmountColumnStmt->fetch()) {
    db()->exec("ALTER TABLE defaulters ADD COLUMN paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER loan_amount");
}

if (isset($_GET['delete'])) {
    $deleteId = (int)$_GET['delete'];
    if ($deleteId > 0) {
        $deleteStmt = db()->prepare('DELETE FROM defaulters WHERE defaulter_id = :defaulter_id');
        $deleteStmt->execute(['defaulter_id' => $deleteId]);
        flash('success', 'Defaulter record deleted successfully.');
    } else {
        flash('error', 'Invalid defaulter record.');
    }
    redirect('admin/defaulter_records.php');
}

$defaulters = db()->query(
    'SELECT d.*, v.brand, v.model, v.registration_no
     FROM defaulters d
     JOIN vehicles v ON v.vehicle_id = d.vehicle_id
     ORDER BY d.defaulter_id DESC'
)->fetchAll();

$pageTitle = 'Defaulter Records';
require ROOT_PATH . '/includes/header.php';
?>
<section class="card defaulter-records-card defaulter-records-card-wide">
    <div class="defaulter-records-head">
        <h2>Defaulter Records</h2>
    </div>

    <div class="defaulter-records-table-wrap">
        <table class="defaulter-records-table">
            <thead>
            <tr>
                <th class="defaulter-col-id">ID</th>
                <th class="defaulter-col-vehicle">Vehicle</th>
                <th class="defaulter-col-name">Defaulter</th>
                <th class="defaulter-col-account">Loan Account</th>
                <th class="defaulter-col-bank">Bank</th>
                <th class="defaulter-col-money">Loan</th>
                <th class="defaulter-col-money">Paid</th>
                <th class="defaulter-col-money">Pending</th>
                <th class="defaulter-col-date">Seizure Date</th>
                <th class="defaulter-col-reason">Reason</th>
                <th class="defaulter-col-action">Edit</th>
                <th class="defaulter-col-action">Delete</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$defaulters): ?>
                <tr><td colspan="12">No defaulter records available.</td></tr>
            <?php else: ?>
                <?php foreach ($defaulters as $row): ?>
                    <tr>
                        <td class="defaulter-col-id"><?php echo (int)$row['defaulter_id']; ?></td>
                        <td class="defaulter-col-vehicle"><?php echo esc($row['brand'] . ' ' . $row['model'] . ' (' . $row['registration_no'] . ')'); ?></td>
                        <td class="defaulter-col-name"><?php echo esc($row['defaulter_name']); ?></td>
                        <td class="defaulter-col-account"><?php echo esc($row['loan_account_number']); ?></td>
                        <td class="defaulter-col-bank"><?php echo esc($row['bank_name']); ?></td>
                        <td class="defaulter-col-money">Rs <?php echo number_format((float)$row['loan_amount'], 2); ?></td>
                        <td class="defaulter-col-money">Rs <?php echo number_format((float)($row['paid_amount'] ?? 0), 2); ?></td>
                        <td class="defaulter-col-money">Rs <?php echo number_format((float)$row['pending_amount'], 2); ?></td>
                        <td class="defaulter-col-date"><?php echo esc($row['seizure_date']); ?></td>
                        <td class="defaulter-col-reason"><?php echo esc($row['reason_for_seizure']); ?></td>
                        <td class="defaulter-col-action">
                            <a class="btn btn-secondary defaulter-record-btn" href="<?php echo BASE_URL; ?>/admin/defaulters.php?edit=<?php echo (int)$row['defaulter_id']; ?>">Edit</a>
                        </td>
                        <td class="defaulter-col-action">
                            <a class="btn btn-danger defaulter-record-btn" data-confirm="Delete this defaulter record?" href="<?php echo BASE_URL; ?>/admin/defaulter_records.php?delete=<?php echo (int)$row['defaulter_id']; ?>">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
