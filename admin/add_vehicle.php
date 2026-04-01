<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/functions.php';

requireAdminLogin();
$bottomError = '';
$vehicleId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEditMode = $vehicleId > 0;
$vehicle = [
    'category' => '',
    'brand' => '',
    'model' => '',
    'registration_no' => '',
    'year' => '',
    'vehicle_condition' => '',
    'market_value' => '',
    'base_price' => '',
    'image' => '',
];

$marketValueColumnExists = db()->query("SHOW COLUMNS FROM vehicles LIKE 'market_value'")->fetch();
if (!$marketValueColumnExists) {
    db()->exec('ALTER TABLE vehicles ADD COLUMN market_value DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER base_price');
}

if ($isEditMode) {
    $existingVehicle = getVehicleById($vehicleId);
    if (!$existingVehicle) {
        flash('error', 'Auction record not found.');
        redirect('admin/vehicles.php');
    }

    $vehicle = $existingVehicle;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($isEditMode) {
        $existingVehicle = getVehicleById($vehicleId);
        if (!$existingVehicle) {
            flash('error', 'Auction record not found.');
            redirect('admin/vehicles.php');
        }

        $bidCheckStmt = db()->prepare('SELECT COUNT(*) FROM bids WHERE vehicle_id = :vehicle_id');
        $bidCheckStmt->execute(['vehicle_id' => $vehicleId]);
        if ((int)$bidCheckStmt->fetchColumn() > 0) {
            flash('error', 'Auctions with bids cannot be edited.');
            redirect('admin/vehicles.php');
        }

        $vehicle = $existingVehicle;
    }

    $category = $_POST['category'] ?? '';
    $brand = trim($_POST['brand'] ?? '');
    $model = trim($_POST['model'] ?? '');
    $registrationNo = trim($_POST['registration_no'] ?? '');
    $year = (int)($_POST['year'] ?? 0);
    $condition = trim($_POST['vehicle_condition'] ?? '');
    $marketValue = (float)($_POST['market_value'] ?? 0);
    $conditionRates = [
        'Good' => 0.85,
        'Average' => 0.70,
        'Damaged' => 0.55,
    ];

    if (!in_array($category, ['Bike', 'Car'], true) || $brand === '' || $model === '' || $registrationNo === '' || $year <= 0 || !isset($conditionRates[$condition]) || $marketValue <= 0) {
        flash('error', 'Please fill all vehicle details correctly.');
    } else {
        $basePrice = round($marketValue * $conditionRates[$condition], 2);
        $imagePath = handleImageUpload('image', 'vehicles', 10240, 20971520);
        if (!$isEditMode && $imagePath === null) {
            flash('error', 'Vehicle image must be JPG/JPEG/PNG/WEBP and between 10KB to 20MB.');
        } else {
            try {
                if ($isEditMode) {
                    $finalImagePath = $imagePath ?? (string)$vehicle['image'];
                    $sql = 'UPDATE vehicles
                            SET category = :category,
                                brand = :brand,
                                model = :model,
                                registration_no = :registration_no,
                                year = :year,
                                vehicle_condition = :vehicle_condition,
                                base_price = :base_price,
                                market_value = :market_value,
                                image = :image
                            WHERE vehicle_id = :vehicle_id';
                    $stmt = db()->prepare($sql);
                    $stmt->execute([
                        'category' => $category,
                        'brand' => $brand,
                        'model' => $model,
                        'registration_no' => $registrationNo,
                        'year' => $year,
                        'vehicle_condition' => $condition,
                        'base_price' => $basePrice,
                        'market_value' => $marketValue,
                        'image' => $finalImagePath,
                        'vehicle_id' => $vehicleId,
                    ]);

                    if ($imagePath !== null && !empty($vehicle['image'])) {
                        $oldImagePath = ROOT_PATH . '/' . ltrim((string)$vehicle['image'], '/');
                        if (is_file($oldImagePath)) {
                            @unlink($oldImagePath);
                        }
                    }

                    flash('success', 'Vehicle updated successfully.');
                } else {
                    $sql = 'INSERT INTO vehicles (category, brand, model, registration_no, year, vehicle_condition, base_price, market_value, image)
                            VALUES (:category, :brand, :model, :registration_no, :year, :vehicle_condition, :base_price, :market_value, :image)';
                    $stmt = db()->prepare($sql);
                    $stmt->execute([
                        'category' => $category,
                        'brand' => $brand,
                        'model' => $model,
                        'registration_no' => $registrationNo,
                        'year' => $year,
                        'vehicle_condition' => $condition,
                        'base_price' => $basePrice,
                        'market_value' => $marketValue,
                        'image' => $imagePath,
                    ]);

                    flash('success', 'Vehicle added successfully.');
                }

                redirect('admin/vehicles.php');
            } catch (PDOException $e) {
                if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'registration_no')) {
                    $bottomError = 'Registration number already exists.';
                } else {
                    throw $e;
                }
            }
        }
    }

    $vehicle = [
        'category' => $category,
        'brand' => $brand,
        'model' => $model,
        'registration_no' => $registrationNo,
        'year' => (string)$year,
        'vehicle_condition' => $condition,
        'market_value' => (string)$marketValue,
        'base_price' => (string)$basePrice,
        'image' => $vehicle['image'] ?? '',
    ];
}

$pageTitle = $isEditMode ? 'Edit Vehicle' : 'Add Vehicle';
require ROOT_PATH . '/includes/header.php';
?>
<section class="form-shell">
    <div class="card form-card">
        <h2><?php echo $isEditMode ? 'Edit Bank Seized Vehicle' : 'Add Bank Seized Vehicle'; ?></h2>
        <p class="auth-subtitle"><?php echo $isEditMode ? 'Update the auction listing details before bidding starts.' : 'Create a new bike or car auction listing.'; ?></p>
        <form method="post" enctype="multipart/form-data" class="form-grid">
            <div>
                <label for="category">Vehicle Type</label>
                <select id="category" name="category" required>
                    <option value="">Select</option>
                    <option value="Bike" <?php echo ($vehicle['category'] ?? '') === 'Bike' ? 'selected' : ''; ?>>Bike</option>
                    <option value="Car" <?php echo ($vehicle['category'] ?? '') === 'Car' ? 'selected' : ''; ?>>Car</option>
                </select>
            </div>

            <div>
                <label for="brand">Brand</label>
                <input id="brand" name="brand" type="text" value="<?php echo esc((string)$vehicle['brand']); ?>" required>
            </div>

            <div>
                <label for="model">Model</label>
                <input id="model" name="model" type="text" value="<?php echo esc((string)$vehicle['model']); ?>" required>
            </div>

            <div>
                <label for="registration_no">Registration Number</label>
                <input id="registration_no" name="registration_no" type="text" value="<?php echo esc((string)$vehicle['registration_no']); ?>" required>
            </div>

            <div>
                <label for="year">Year of Manufacture</label>
                <input id="year" name="year" type="number" min="1980" max="2099" value="<?php echo esc((string)$vehicle['year']); ?>" required>
            </div>

            <div>
                <label for="market_value">Market Value</label>
                <input id="market_value" name="market_value" type="number" step="0.01" value="<?php echo esc((string)$vehicle['market_value']); ?>" required>
            </div>

            <div>
                <label for="vehicle_condition">Condition</label>
                <select id="vehicle_condition" name="vehicle_condition" required>
                    <option value="">Select</option>
                    <option value="Good" <?php echo ($vehicle['vehicle_condition'] ?? '') === 'Good' ? 'selected' : ''; ?>>Good</option>
                    <option value="Average" <?php echo ($vehicle['vehicle_condition'] ?? '') === 'Average' ? 'selected' : ''; ?>>Average</option>
                    <option value="Damaged" <?php echo ($vehicle['vehicle_condition'] ?? '') === 'Damaged' ? 'selected' : ''; ?>>Damaged</option>
                </select>
            </div>

            <div>
                <label for="base_price">Base Price</label>
                <input id="base_price" name="base_price" type="number" step="0.01" value="<?php echo esc((string)$vehicle['base_price']); ?>" readonly required>
            </div>

            <div>
                <label for="image"><?php echo $isEditMode ? 'Vehicle Image (Optional)' : 'Vehicle Image'; ?></label>
                <input id="image" name="image" type="file" accept=".jpg,.jpeg,.png,.webp" <?php echo $isEditMode ? '' : 'required'; ?>>
                <?php if ($isEditMode && !empty($vehicle['image'])): ?>
                    <p>Current image: <?php echo esc((string)$vehicle['image']); ?></p>
                <?php endif; ?>
            </div>

            <div class="add-vehicle-submit-under-image">
                <button class="btn add-vehicle-submit-btn" type="submit"><?php echo $isEditMode ? 'Update Vehicle' : 'Add Vehicle'; ?></button>
            </div>
        </form>
    </div>
    <?php if ($bottomError !== ''): ?>
        <p class="form-bottom-error"><?php echo esc($bottomError); ?></p>
    <?php endif; ?>
</section>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
