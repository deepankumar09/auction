<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/functions.php';

requireAdminLogin();
$bottomError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category = $_POST['category'] ?? '';
    $brand = trim($_POST['brand'] ?? '');
    $model = trim($_POST['model'] ?? '');
    $registrationNo = trim($_POST['registration_no'] ?? '');
    $year = (int)($_POST['year'] ?? 0);
    $condition = trim($_POST['vehicle_condition'] ?? '');
    $basePrice = (float)($_POST['base_price'] ?? 0);

    if (!in_array($category, ['Bike', 'Car'], true) || $brand === '' || $model === '' || $registrationNo === '' || $year <= 0 || $condition === '' || $basePrice <= 0) {
        flash('error', 'Please fill all vehicle details correctly.');
    } else {
        $imagePath = handleImageUpload('image', 'vehicles');

        $sql = 'INSERT INTO vehicles (category, brand, model, registration_no, year, vehicle_condition, base_price, image)
                VALUES (:category, :brand, :model, :registration_no, :year, :vehicle_condition, :base_price, :image)';
        try {
            $stmt = db()->prepare($sql);
            $stmt->execute([
                'category' => $category,
                'brand' => $brand,
                'model' => $model,
                'registration_no' => $registrationNo,
                'year' => $year,
                'vehicle_condition' => $condition,
                'base_price' => $basePrice,
                'image' => $imagePath,
            ]);

            flash('success', 'Seized vehicle added successfully.');
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

$pageTitle = 'Add Vehicle';
require ROOT_PATH . '/includes/header.php';
?>
<section class="form-shell">
    <div class="card form-card">
        <h2>Add Seized Vehicle</h2>
        <p class="auth-subtitle">Create a new bike or car auction listing.</p>
        <form method="post" enctype="multipart/form-data" class="form-grid">
            <div>
                <label for="category">Vehicle Type</label>
                <select id="category" name="category" required>
                    <option value="">Select</option>
                    <option value="Bike">Bike</option>
                    <option value="Car">Car</option>
                </select>
            </div>

            <div>
                <label for="brand">Brand</label>
                <input id="brand" name="brand" type="text" required>
            </div>

            <div>
                <label for="model">Model</label>
                <input id="model" name="model" type="text" required>
            </div>

            <div>
                <label for="registration_no">Registration Number</label>
                <input id="registration_no" name="registration_no" type="text" required>
            </div>

            <div>
                <label for="year">Year</label>
                <input id="year" name="year" type="number" min="1980" max="2099" required>
            </div>

            <div>
                <label for="vehicle_condition">Condition</label>
                <input id="vehicle_condition" name="vehicle_condition" type="text" required>
            </div>

            <div>
                <label for="base_price">Base Price</label>
                <input id="base_price" name="base_price" type="number" step="0.01" required>
            </div>

            <div>
                <label for="image">Vehicle Image</label>
                <input id="image" name="image" type="file" accept=".jpg,.jpeg,.png,.webp" required>
            </div>

            <button class="btn auth-btn form-span" type="submit">Add Vehicle</button>
        </form>
    </div>
    <?php if ($bottomError !== ''): ?>
        <p class="form-bottom-error"><?php echo esc($bottomError); ?></p>
    <?php endif; ?>
</section>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
