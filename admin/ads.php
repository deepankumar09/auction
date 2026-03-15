<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/functions.php';

requireAdminLogin();

if (isset($_GET['delete'])) {
    $adId = (int)$_GET['delete'];
    $findStmt = db()->prepare('SELECT image FROM advertisements WHERE ad_id = :ad_id LIMIT 1');
    $findStmt->execute(['ad_id' => $adId]);
    $ad = $findStmt->fetch();

    if ($ad) {
        $deleteStmt = db()->prepare('DELETE FROM advertisements WHERE ad_id = :ad_id');
        $deleteStmt->execute(['ad_id' => $adId]);
        if (!empty($ad['image'])) {
            $imagePath = ROOT_PATH . '/' . ltrim((string)$ad['image'], '/');
            if (is_file($imagePath)) {
                @unlink($imagePath);
            }
        }
        flash('success', 'Advertisement deleted successfully.');
    } else {
        flash('error', 'Advertisement not found.');
    }
    redirect('admin/ads.php');
}

if (isset($_GET['toggle'])) {
    $adId = (int)$_GET['toggle'];
    $stmt = db()->prepare("UPDATE advertisements
                           SET status = CASE WHEN status = 'active' THEN 'inactive' ELSE 'active' END
                           WHERE ad_id = :ad_id");
    $stmt->execute(['ad_id' => $adId]);
    flash('success', 'Advertisement status updated.');
    redirect('admin/ads.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = ($_POST['status'] ?? 'inactive') === 'active' ? 'active' : 'inactive';
    $image = handleImageUpload('image', 'ads');

    if ($title === '') {
        flash('error', 'Advertisement title is required.');
    } elseif (!empty($_FILES['image']['name']) && $image === null) {
        flash('error', 'Banner image must be JPG/JPEG/PNG/WEBP and between 10KB to 20MB.');
    } else {
        $stmt = db()->prepare('INSERT INTO advertisements (title, description, image, status) VALUES (:title, :description, :image, :status)');
        $stmt->execute([
            'title' => $title,
            'description' => $description,
            'image' => $image,
            'status' => $status,
        ]);
        flash('success', 'Advertisement added successfully.');
        redirect('admin/ads.php');
    }
}

$ads = db()->query('SELECT * FROM advertisements ORDER BY ad_id DESC')->fetchAll();

$pageTitle = 'Manage Advertisements';
require ROOT_PATH . '/includes/header.php';
?>
<section class="ads-page">
    <section class="form-shell">
        <div class="card form-card ads-form-card">
            <h2>Add Advertisement Banner</h2>
            <p class="auth-subtitle">Create and manage homepage promotional banners.</p>
            <form method="post" enctype="multipart/form-data" class="form-grid ads-form-grid">
                <div class="form-span">
                    <label for="title">Title</label>
                    <input id="title" name="title" type="text" required>
                </div>

                <div class="form-span">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="4"></textarea>
                </div>

                <div>
                    <label for="image">Banner Image</label>
                    <input id="image" name="image" type="file" accept=".jpg,.jpeg,.png,.webp">
                </div>

                <div>
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>

                <button class="btn auth-btn form-span" type="submit">Save Advertisement</button>
            </form>
        </div>
    </section>

    <section class="card ads-table-card">
        <h2>Advertisement List</h2>
        <div class="ads-table-wrap">
            <table class="ads-table">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Action</th>
                    <th>Delete</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$ads): ?>
                    <tr><td colspan="6">No advertisements yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($ads as $ad): ?>
                        <tr>
                            <td><?php echo (int)$ad['ad_id']; ?></td>
                            <td><?php echo esc($ad['title']); ?></td>
                            <td>
                                <span class="ads-status-badge <?php echo $ad['status'] === 'active' ? 'is-active' : 'is-inactive'; ?>">
                                    <?php echo strtoupper(esc($ad['status'])); ?>
                                </span>
                            </td>
                            <td><?php echo esc($ad['created_at']); ?></td>
                            <td>
                                <div class="inline">
                                    <a class="btn btn-secondary ads-action-btn" href="<?php echo BASE_URL; ?>/admin/ads.php?toggle=<?php echo (int)$ad['ad_id']; ?>">
                                        <?php echo $ad['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>
                                    </a>
                                </div>
                            </td>
                            <td>
                                <a class="btn btn-danger ads-action-btn" data-confirm="Delete this advertisement?" href="<?php echo BASE_URL; ?>/admin/ads.php?delete=<?php echo (int)$ad['ad_id']; ?>">
                                    Delete
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</section>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
