<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/functions.php';

requireAdminLogin();
$bottomError = '';

function parseVehicleDisplayText(string $vehicleText): ?array
{
    if (!preg_match('/^\s*(.+?)\s*\(\s*([^)]+)\s*\)\s*$/', $vehicleText, $matches)) {
        return null;
    }

    $namePart = trim((string)$matches[1]);
    $registrationNo = trim((string)$matches[2]);
    if ($namePart === '' || $registrationNo === '') {
        return null;
    }

    $parts = preg_split('/\s+/', $namePart) ?: [];
    if (count($parts) < 2) {
        return null;
    }

    $model = (string)array_pop($parts);
    $brand = trim(implode(' ', $parts));
    if ($brand === '' || $model === '') {
        return null;
    }

    return [
        'brand' => $brand,
        'model' => $model,
        'registration_no' => strtoupper($registrationNo),
    ];
}

// Keeps old databases compatible when schema.sql has not been re-imported.
db()->exec(
    "CREATE TABLE IF NOT EXISTS defaulters (
        defaulter_id INT AUTO_INCREMENT PRIMARY KEY,
        vehicle_id INT NOT NULL UNIQUE,
        defaulter_name VARCHAR(150) NOT NULL,
        loan_account_number VARCHAR(100) NOT NULL,
        bank_name VARCHAR(150) NOT NULL,
        loan_amount DECIMAL(12,2) NOT NULL,
        pending_amount DECIMAL(12,2) NOT NULL,
        seizure_date DATE NOT NULL,
        reason_for_seizure TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (vehicle_id) REFERENCES vehicles(vehicle_id) ON DELETE CASCADE
    )"
);

if (isset($_GET['delete'])) {
    $deleteId = (int)$_GET['delete'];
    if ($deleteId > 0) {
        $deleteStmt = db()->prepare('DELETE FROM defaulters WHERE defaulter_id = :defaulter_id');
        $deleteStmt->execute(['defaulter_id' => $deleteId]);
        flash('success', 'Defaulter record deleted successfully.');
    } else {
        flash('error', 'Invalid defaulter record.');
    }
    redirect('admin/defaulters.php');
}

$form = [
    'defaulter_id' => 0,
    'vehicle_id' => 0,
    'vehicle_text' => '',
    'defaulter_name' => '',
    'loan_account_number' => '',
    'bank_name' => '',
    'loan_amount' => '',
    'pending_amount' => '',
    'seizure_date' => '',
    'reason_for_seizure' => '',
];

$editId = (int)($_GET['edit'] ?? 0);
if ($editId > 0) {
    $editStmt = db()->prepare(
        'SELECT d.*, v.brand, v.model, v.registration_no
         FROM defaulters d
         JOIN vehicles v ON v.vehicle_id = d.vehicle_id
         WHERE d.defaulter_id = :defaulter_id
         LIMIT 1'
    );
    $editStmt->execute(['defaulter_id' => $editId]);
    $editRow = $editStmt->fetch();

    if (!$editRow) {
        flash('error', 'Defaulter record not found.');
        redirect('admin/defaulters.php');
    }

    $form = [
        'defaulter_id' => (int)$editRow['defaulter_id'],
        'vehicle_id' => (int)$editRow['vehicle_id'],
        'vehicle_text' => trim((string)$editRow['brand'] . ' ' . (string)$editRow['model']) . ' (' . (string)$editRow['registration_no'] . ')',
        'defaulter_name' => (string)$editRow['defaulter_name'],
        'loan_account_number' => (string)$editRow['loan_account_number'],
        'bank_name' => (string)$editRow['bank_name'],
        'loan_amount' => (string)$editRow['loan_amount'],
        'pending_amount' => (string)$editRow['pending_amount'],
        'seizure_date' => (string)$editRow['seizure_date'],
        'reason_for_seizure' => (string)$editRow['reason_for_seizure'],
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = [
        'defaulter_id' => (int)($_POST['defaulter_id'] ?? 0),
        'vehicle_id' => (int)($_POST['vehicle_id'] ?? 0),
        'vehicle_text' => trim($_POST['vehicle_text'] ?? ''),
        'defaulter_name' => trim($_POST['defaulter_name'] ?? ''),
        'loan_account_number' => trim($_POST['loan_account_number'] ?? ''),
        'bank_name' => trim($_POST['bank_name'] ?? ''),
        'loan_amount' => trim($_POST['loan_amount'] ?? ''),
        'pending_amount' => trim($_POST['pending_amount'] ?? ''),
        'seizure_date' => trim($_POST['seizure_date'] ?? ''),
        'reason_for_seizure' => trim($_POST['reason_for_seizure'] ?? ''),
    ];

    $isEdit = $form['defaulter_id'] > 0;
    $loanAmount = (float)$form['loan_amount'];
    $pendingAmount = (float)$form['pending_amount'];
    $parsedDate = DateTime::createFromFormat('Y-m-d', $form['seizure_date']);
    $isDateValid = $parsedDate && $parsedDate->format('Y-m-d') === $form['seizure_date'];
    $parsedVehicle = parseVehicleDisplayText($form['vehicle_text']);

    if (
        $parsedVehicle === null ||
        $form['defaulter_name'] === '' ||
        $form['loan_account_number'] === '' ||
        $form['bank_name'] === '' ||
        $loanAmount <= 0 ||
        $pendingAmount < 0 ||
        !$isDateValid ||
        $form['reason_for_seizure'] === ''
    ) {
        $bottomError = 'Please enter valid details. Vehicle format: Brand Model (REG NO).';
    } elseif ($pendingAmount > $loanAmount) {
        $bottomError = 'Pending amount cannot be greater than loan amount.';
    } else {
        try {
            db()->beginTransaction();

            if ($isEdit) {
                $updateVehicleStmt = db()->prepare(
                    'UPDATE vehicles
                     SET brand = :brand,
                         model = :model,
                         registration_no = :registration_no
                     WHERE vehicle_id = :vehicle_id'
                );
                $updateVehicleStmt->execute([
                    'brand' => $parsedVehicle['brand'],
                    'model' => $parsedVehicle['model'],
                    'registration_no' => $parsedVehicle['registration_no'],
                    'vehicle_id' => $form['vehicle_id'],
                ]);

                $updateDefaulterStmt = db()->prepare(
                    'UPDATE defaulters
                     SET defaulter_name = :defaulter_name,
                         loan_account_number = :loan_account_number,
                         bank_name = :bank_name,
                         loan_amount = :loan_amount,
                         pending_amount = :pending_amount,
                         seizure_date = :seizure_date,
                         reason_for_seizure = :reason_for_seizure
                     WHERE defaulter_id = :defaulter_id'
                );
                $updateDefaulterStmt->execute([
                    'defaulter_name' => $form['defaulter_name'],
                    'loan_account_number' => $form['loan_account_number'],
                    'bank_name' => $form['bank_name'],
                    'loan_amount' => $loanAmount,
                    'pending_amount' => $pendingAmount,
                    'seizure_date' => $form['seizure_date'],
                    'reason_for_seizure' => $form['reason_for_seizure'],
                    'defaulter_id' => $form['defaulter_id'],
                ]);

                flash('success', 'Defaulter and vehicle updated successfully.');
            } else {
                $insertVehicleStmt = db()->prepare(
                    'INSERT INTO vehicles
                        (category, brand, model, registration_no, year, vehicle_condition, base_price, image)
                     VALUES
                        (:category, :brand, :model, :registration_no, :year, :vehicle_condition, :base_price, NULL)'
                );
                $insertVehicleStmt->execute([
                    'category' => 'Bike',
                    'brand' => $parsedVehicle['brand'],
                    'model' => $parsedVehicle['model'],
                    'registration_no' => $parsedVehicle['registration_no'],
                    'year' => (int)date('Y'),
                    'vehicle_condition' => 'Seized',
                    'base_price' => $pendingAmount > 0 ? $pendingAmount : 1,
                ]);

                $vehicleId = (int)db()->lastInsertId();

                $insertDefaulterStmt = db()->prepare(
                    'INSERT INTO defaulters
                        (vehicle_id, defaulter_name, loan_account_number, bank_name, loan_amount, pending_amount, seizure_date, reason_for_seizure)
                     VALUES
                        (:vehicle_id, :defaulter_name, :loan_account_number, :bank_name, :loan_amount, :pending_amount, :seizure_date, :reason_for_seizure)'
                );
                $insertDefaulterStmt->execute([
                    'vehicle_id' => $vehicleId,
                    'defaulter_name' => $form['defaulter_name'],
                    'loan_account_number' => $form['loan_account_number'],
                    'bank_name' => $form['bank_name'],
                    'loan_amount' => $loanAmount,
                    'pending_amount' => $pendingAmount,
                    'seizure_date' => $form['seizure_date'],
                    'reason_for_seizure' => $form['reason_for_seizure'],
                ]);

                flash('success', 'Defaulter and vehicle added successfully.');
            }

            db()->commit();
            redirect('admin/defaulters.php');
        } catch (PDOException $e) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }

            if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'registration_no')) {
                $bottomError = 'Registration number already exists.';
            } elseif ($e->getCode() === '23000') {
                $bottomError = 'This vehicle already has a defaulter record.';
            } else {
                throw $e;
            }
        }
    }
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
<section class="form-shell">
    <div class="card form-card">
        <h2><?php echo $form['defaulter_id'] > 0 ? 'Edit Defaulter' : 'Add Loan Defaulter'; ?></h2>
        <form method="post" class="form-grid">
            <input type="hidden" name="defaulter_id" value="<?php echo (int)$form['defaulter_id']; ?>">
            <input type="hidden" name="vehicle_id" value="<?php echo (int)$form['vehicle_id']; ?>">

            <div class="form-span">
                <label for="vehicle_text">Vehicle</label>
                <input id="vehicle_text" name="vehicle_text" type="text" value="<?php echo esc((string)$form['vehicle_text']); ?>" required>
            </div>

            <div>
                <label for="defaulter_name">Defaulter Name</label>
                <input id="defaulter_name" name="defaulter_name" type="text" value="<?php echo esc((string)$form['defaulter_name']); ?>" required>
            </div>

            <div>
                <label for="loan_account_number">Loan Account Number</label>
                <input id="loan_account_number" name="loan_account_number" type="text" value="<?php echo esc((string)$form['loan_account_number']); ?>" required>
            </div>

            <div>
                <label for="bank_name">Bank Name</label>
                <input id="bank_name" name="bank_name" type="text" value="<?php echo esc((string)$form['bank_name']); ?>" required>
            </div>

            <div>
                <label for="loan_amount">Loan Amount</label>
                <input id="loan_amount" name="loan_amount" type="number" step="0.01" min="0" value="<?php echo esc((string)$form['loan_amount']); ?>" required>
            </div>

            <div>
                <label for="pending_amount">Pending Amount</label>
                <input id="pending_amount" name="pending_amount" type="number" step="0.01" min="0" value="<?php echo esc((string)$form['pending_amount']); ?>" required>
            </div>

            <div>
                <label for="seizure_date">Seizure Date</label>
                <input id="seizure_date" name="seizure_date" type="date" value="<?php echo esc((string)$form['seizure_date']); ?>" required>
            </div>

            <div class="form-span">
                <label for="reason_for_seizure">Reason for Seizure</label>
                <textarea id="reason_for_seizure" name="reason_for_seizure" rows="3" required><?php echo esc((string)$form['reason_for_seizure']); ?></textarea>
            </div>

            <div class="form-span inline">
                <button class="btn" type="submit"><?php echo $form['defaulter_id'] > 0 ? 'Update Defaulter' : 'Add Defaulter'; ?></button>
                <?php if ($form['defaulter_id'] > 0): ?>
                    <a class="btn btn-danger" data-confirm="Delete this defaulter record?" href="<?php echo BASE_URL; ?>/admin/defaulters.php?delete=<?php echo (int)$form['defaulter_id']; ?>">Delete</a>
                    <a class="btn btn-secondary" href="<?php echo BASE_URL; ?>/admin/defaulters.php">Cancel Edit</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    <?php if ($bottomError !== ''): ?>
        <p class="form-bottom-error"><?php echo esc($bottomError); ?></p>
    <?php endif; ?>
</section>

<section class="card">
    <h2>Defaulter Records</h2>
    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>Vehicle</th>
            <th>Defaulter</th>
            <th>Loan Account</th>
            <th>Bank</th>
            <th>Loan</th>
            <th>Pending</th>
            <th>Seizure Date</th>
            <th>Reason</th>
            <th>Edit</th>
            <th>Delete</th>
        </tr>
        </thead>
        <tbody>
        <?php if (!$defaulters): ?>
            <tr><td colspan="11">No defaulter records available.</td></tr>
        <?php else: ?>
            <?php foreach ($defaulters as $row): ?>
                <tr>
                    <td><?php echo (int)$row['defaulter_id']; ?></td>
                    <td><?php echo esc($row['brand'] . ' ' . $row['model'] . ' (' . $row['registration_no'] . ')'); ?></td>
                    <td><?php echo esc($row['defaulter_name']); ?></td>
                    <td><?php echo esc($row['loan_account_number']); ?></td>
                    <td><?php echo esc($row['bank_name']); ?></td>
                    <td>Rs <?php echo number_format((float)$row['loan_amount'], 2); ?></td>
                    <td>Rs <?php echo number_format((float)$row['pending_amount'], 2); ?></td>
                    <td><?php echo esc($row['seizure_date']); ?></td>
                    <td><?php echo esc($row['reason_for_seizure']); ?></td>
                    <td>
                        <a class="btn btn-secondary" href="<?php echo BASE_URL; ?>/admin/defaulters.php?edit=<?php echo (int)$row['defaulter_id']; ?>">Edit</a>
                    </td>
                    <td>
                        <a class="btn btn-danger" data-confirm="Delete this defaulter record?" href="<?php echo BASE_URL; ?>/admin/defaulters.php?delete=<?php echo (int)$row['defaulter_id']; ?>">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</section>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
