<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/functions.php';

requireAdminLogin();
$bottomError = '';

// Keeps old databases compatible when schema.sql has not been re-imported.
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

// Keeps old databases compatible when paid_amount column does not exist yet.
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
    'paid_amount' => '',
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
        'vehicle_text' => trim((string)$editRow['brand'] . ' ' . (string)$editRow['model'] . ' ' . (string)$editRow['registration_no']),
        'defaulter_name' => (string)$editRow['defaulter_name'],
        'loan_account_number' => (string)$editRow['loan_account_number'],
        'bank_name' => (string)$editRow['bank_name'],
        'loan_amount' => (string)$editRow['loan_amount'],
        'paid_amount' => isset($editRow['paid_amount']) ? (string)$editRow['paid_amount'] : '0',
        'pending_amount' => (string)$editRow['pending_amount'],
        'seizure_date' => (string)$editRow['seizure_date'],
        'reason_for_seizure' => (string)$editRow['reason_for_seizure'],
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = [
        'defaulter_id' => (int)($_POST['defaulter_id'] ?? 0),
        'vehicle_id' => 0,
        'vehicle_text' => trim($_POST['vehicle_text'] ?? ''),
        'defaulter_name' => trim($_POST['defaulter_name'] ?? ''),
        'loan_account_number' => trim($_POST['loan_account_number'] ?? ''),
        'bank_name' => trim($_POST['bank_name'] ?? ''),
        'loan_amount' => trim($_POST['loan_amount'] ?? ''),
        'paid_amount' => trim($_POST['paid_amount'] ?? ''),
        'pending_amount' => trim($_POST['pending_amount'] ?? ''),
        'seizure_date' => trim($_POST['seizure_date'] ?? ''),
        'reason_for_seizure' => trim($_POST['reason_for_seizure'] ?? ''),
    ];

    $isEdit = $form['defaulter_id'] > 0;
    $loanAmount = (float)$form['loan_amount'];
    $paidAmount = (float)$form['paid_amount'];
    $pendingAmount = (float)$form['pending_amount'];
    $parsedDate = DateTime::createFromFormat('Y-m-d', $form['seizure_date']);
    $isDateValid = $parsedDate && $parsedDate->format('Y-m-d') === $form['seizure_date'];
    $normalizedVehicleKey = strtolower((string)preg_replace('/[^a-z0-9]/i', '', $form['vehicle_text']));
    $vehicleCheckSql = $isEdit
        ? 'SELECT v.vehicle_id
           FROM vehicles v
           LEFT JOIN defaulters d
             ON d.vehicle_id = v.vehicle_id
            AND d.defaulter_id <> :defaulter_id
           WHERE (
                LOWER(REPLACE(REPLACE(REPLACE(REPLACE(CONCAT(v.brand, v.model, v.registration_no), " ", ""), "-", ""), ".", ""), "/", "")) = :vehicle_key
                OR LOWER(REPLACE(REPLACE(REPLACE(REPLACE(v.registration_no, " ", ""), "-", ""), ".", ""), "/", "")) = :vehicle_key
                OR :vehicle_key LIKE CONCAT("%", LOWER(REPLACE(REPLACE(REPLACE(REPLACE(v.registration_no, " ", ""), "-", ""), ".", ""), "/", "")), "%")
            )
             AND d.vehicle_id IS NULL
           ORDER BY
             (LOWER(REPLACE(REPLACE(REPLACE(REPLACE(CONCAT(v.brand, v.model, v.registration_no), " ", ""), "-", ""), ".", ""), "/", "")) = :vehicle_key_exact) DESC,
             (LOWER(REPLACE(REPLACE(REPLACE(REPLACE(v.registration_no, " ", ""), "-", ""), ".", ""), "/", "")) = :vehicle_key_exact) DESC
           LIMIT 1'
        : 'SELECT v.vehicle_id
           FROM vehicles v
           LEFT JOIN defaulters d ON d.vehicle_id = v.vehicle_id
           WHERE (
                LOWER(REPLACE(REPLACE(REPLACE(REPLACE(CONCAT(v.brand, v.model, v.registration_no), " ", ""), "-", ""), ".", ""), "/", "")) = :vehicle_key
                OR LOWER(REPLACE(REPLACE(REPLACE(REPLACE(v.registration_no, " ", ""), "-", ""), ".", ""), "/", "")) = :vehicle_key
                OR :vehicle_key LIKE CONCAT("%", LOWER(REPLACE(REPLACE(REPLACE(REPLACE(v.registration_no, " ", ""), "-", ""), ".", ""), "/", "")), "%")
            )
             AND d.vehicle_id IS NULL
           ORDER BY
             (LOWER(REPLACE(REPLACE(REPLACE(REPLACE(CONCAT(v.brand, v.model, v.registration_no), " ", ""), "-", ""), ".", ""), "/", "")) = :vehicle_key_exact) DESC,
             (LOWER(REPLACE(REPLACE(REPLACE(REPLACE(v.registration_no, " ", ""), "-", ""), ".", ""), "/", "")) = :vehicle_key_exact) DESC
           LIMIT 1';
    $vehicleCheckStmt = db()->prepare($vehicleCheckSql);
    $vehicleCheckParams = [
        'vehicle_key' => $normalizedVehicleKey,
        'vehicle_key_exact' => $normalizedVehicleKey,
    ];
    if ($isEdit) {
        $vehicleCheckParams['defaulter_id'] = $form['defaulter_id'];
    }
    $vehicleCheckStmt->execute($vehicleCheckParams);
    $selectedVehicle = $vehicleCheckStmt->fetch();
    $selectedVehicleId = $selectedVehicle ? (int)$selectedVehicle['vehicle_id'] : 0;

    if (
        $selectedVehicle === false ||
        $form['vehicle_text'] === '' ||
        $form['defaulter_name'] === '' ||
        $form['loan_account_number'] === '' ||
        $form['bank_name'] === '' ||
        $loanAmount <= 0 ||
        $paidAmount < 0 ||
        $pendingAmount < 0 ||
        !$isDateValid ||
        $form['reason_for_seizure'] === ''
    ) {
        $bottomError = 'Vehicle not found. Add vehicle first in Add Vehicles, then add defaulter.';
    } elseif ($paidAmount > $loanAmount) {
        $bottomError = 'Paid amount cannot be greater than loan amount.';
    } elseif ($pendingAmount > $loanAmount) {
        $bottomError = 'Pending amount cannot be greater than loan amount.';
    } elseif (($paidAmount + $pendingAmount) > $loanAmount) {
        $bottomError = 'Paid amount plus pending amount cannot be greater than loan amount.';
    } else {
        try {
            db()->beginTransaction();

            if ($isEdit) {
                $updateDefaulterStmt = db()->prepare(
                    'UPDATE defaulters
                     SET vehicle_id = :vehicle_id,
                         defaulter_name = :defaulter_name,
                         loan_account_number = :loan_account_number,
                         bank_name = :bank_name,
                         loan_amount = :loan_amount,
                         paid_amount = :paid_amount,
                         pending_amount = :pending_amount,
                         seizure_date = :seizure_date,
                         reason_for_seizure = :reason_for_seizure
                     WHERE defaulter_id = :defaulter_id'
                );
                $updateDefaulterStmt->execute([
                    'vehicle_id' => $selectedVehicleId,
                    'defaulter_name' => $form['defaulter_name'],
                    'loan_account_number' => $form['loan_account_number'],
                    'bank_name' => $form['bank_name'],
                    'loan_amount' => $loanAmount,
                    'paid_amount' => $paidAmount,
                    'pending_amount' => $pendingAmount,
                    'seizure_date' => $form['seizure_date'],
                    'reason_for_seizure' => $form['reason_for_seizure'],
                    'defaulter_id' => $form['defaulter_id'],
                ]);

                flash('success', 'Defaulter updated successfully.');
            } else {
                $insertDefaulterStmt = db()->prepare(
                    'INSERT INTO defaulters
                        (vehicle_id, defaulter_name, loan_account_number, bank_name, loan_amount, paid_amount, pending_amount, seizure_date, reason_for_seizure)
                     VALUES
                        (:vehicle_id, :defaulter_name, :loan_account_number, :bank_name, :loan_amount, :paid_amount, :pending_amount, :seizure_date, :reason_for_seizure)'
                );
                $insertDefaulterStmt->execute([
                    'vehicle_id' => $selectedVehicleId,
                    'defaulter_name' => $form['defaulter_name'],
                    'loan_account_number' => $form['loan_account_number'],
                    'bank_name' => $form['bank_name'],
                    'loan_amount' => $loanAmount,
                    'paid_amount' => $paidAmount,
                    'pending_amount' => $pendingAmount,
                    'seizure_date' => $form['seizure_date'],
                    'reason_for_seizure' => $form['reason_for_seizure'],
                ]);

                flash('success', 'Defaulter added successfully.');
            }

            db()->commit();
            redirect('admin/defaulters.php');
        } catch (PDOException $e) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }

            if ($e->getCode() === '23000') {
                $bottomError = 'This vehicle already has a defaulter record.';
            } else {
                throw $e;
            }
        }
    }
}

$availableVehiclesSql = 'SELECT v.vehicle_id, v.brand, v.model, v.registration_no
                         FROM vehicles v
                         LEFT JOIN defaulters d
                           ON d.vehicle_id = v.vehicle_id';
$availableVehiclesParams = [];
if ($form['defaulter_id'] > 0) {
    $availableVehiclesSql .= ' AND d.defaulter_id <> :defaulter_id';
    $availableVehiclesParams['defaulter_id'] = $form['defaulter_id'];
}
$availableVehiclesSql .= ' WHERE d.vehicle_id IS NULL
                           ORDER BY v.created_at DESC, v.vehicle_id DESC';
$availableVehiclesStmt = db()->prepare($availableVehiclesSql);
$availableVehiclesStmt->execute($availableVehiclesParams);
$availableVehicles = $availableVehiclesStmt->fetchAll();

$defaulters = db()->query(
    'SELECT d.*, v.brand, v.model, v.registration_no
     FROM defaulters d
     JOIN vehicles v ON v.vehicle_id = d.vehicle_id
     ORDER BY d.defaulter_id DESC'
)->fetchAll();

if ($bottomError !== '') {
    flash('error', $bottomError);
}

$pageTitle = 'Defaulter Records';
require ROOT_PATH . '/includes/header.php';
?>
<section class="form-shell">
    <div class="card form-card">
        <div class="defaulter-records-head">
            <h2><?php echo $form['defaulter_id'] > 0 ? 'Edit Loan Defaulter' : 'Add Loan Defaulter'; ?></h2>
            <a class="btn btn-secondary" href="<?php echo BASE_URL; ?>/admin/defaulter_records.php">View Defaulter Records</a>
        </div>
        <form method="post" class="form-grid">
            <input type="hidden" name="defaulter_id" value="<?php echo (int)$form['defaulter_id']; ?>">

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
                <label for="paid_amount">Paid Amount</label>
                <input id="paid_amount" name="paid_amount" type="number" step="0.01" min="0" value="<?php echo esc((string)$form['paid_amount']); ?>" required>
            </div>

            <div>
                <label for="pending_amount">Pending Amount</label>
                <input id="pending_amount" name="pending_amount" type="number" step="0.01" min="0" value="<?php echo esc((string)$form['pending_amount']); ?>" required>
            </div>

            <div class="form-span">
                <label for="vehicle_text">Vehicle</label>
                <input
                    id="vehicle_text"
                    name="vehicle_text"
                    type="text"
                    list="vehicle_text_suggestions"
                    value="<?php echo esc((string)$form['vehicle_text']); ?>"
                    required
                >
                <datalist id="vehicle_text_suggestions">
                    <?php foreach ($availableVehicles as $vehicle): ?>
                        <option value="<?php echo esc($vehicle['brand'] . ' ' . $vehicle['model'] . ' ' . $vehicle['registration_no']); ?>">
                        </option>
                    <?php endforeach; ?>
                </datalist>
            </div>

            <div class="form-span">
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
</section>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
