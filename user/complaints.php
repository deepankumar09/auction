<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/functions.php';

requireUserLogin();
ensureComplaintTable();

$userId = (int)$_SESSION['user_id'];
$issueTypes = ['Wrong Bid', 'Payment Issue', 'Other'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['action'] ?? 'submit'));

    if ($action === 'delete') {
        $complaintId = (int)($_POST['complaint_id'] ?? 0);
        $deleteCheckStmt = db()->prepare(
            'SELECT complaint_id
             FROM complaint
             WHERE complaint_id = :complaint_id
               AND user_id = :user_id
               AND status = :status
             LIMIT 1'
        );
        $deleteCheckStmt->execute([
            'complaint_id' => $complaintId,
            'user_id' => $userId,
            'status' => 'resolved',
        ]);

        if (!$deleteCheckStmt->fetch()) {
            flash('error', 'Only resolved complaints can be deleted.');
            redirect('user/complaints.php');
        }

        $deleteStmt = db()->prepare(
            'DELETE FROM complaint
             WHERE complaint_id = :complaint_id
               AND user_id = :user_id
             LIMIT 1'
        );
        $deleteStmt->execute([
            'complaint_id' => $complaintId,
            'user_id' => $userId,
        ]);

        flash('success', 'Complaint deleted successfully.');
        redirect('user/complaints.php');
    }

    $issueType = trim((string)($_POST['issue_type'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $bidId = (int)($_POST['bid_id'] ?? 0);
    $vehicleId = null;

    if (!in_array($issueType, $issueTypes, true)) {
        flash('error', 'Please select a valid issue type.');
        redirect('user/complaints.php');
    }

    if ($description === '' || mb_strlen($description) < 10) {
        flash('error', 'Please enter a clear description with at least 10 characters.');
        redirect('user/complaints.php');
    }

    if ($bidId > 0) {
        $bidStmt = db()->prepare(
            'SELECT b.bid_id, b.vehicle_id
             FROM bids b
             WHERE b.bid_id = :bid_id AND b.user_id = :user_id
             LIMIT 1'
        );
        $bidStmt->execute([
            'bid_id' => $bidId,
            'user_id' => $userId,
        ]);
        $bidRow = $bidStmt->fetch();

        if (!$bidRow) {
            flash('error', 'Selected bid record was not found.');
            redirect('user/complaints.php');
        }

        $vehicleId = (int)$bidRow['vehicle_id'];
    }

    $insertStmt = db()->prepare(
        'INSERT INTO complaint (user_id, bid_id, vehicle_id, issue_type, description)
         VALUES (:user_id, :bid_id, :vehicle_id, :issue_type, :description)'
    );
    $insertStmt->execute([
        'user_id' => $userId,
        'bid_id' => $bidId > 0 ? $bidId : null,
        'vehicle_id' => $vehicleId,
        'issue_type' => $issueType,
        'description' => $description,
    ]);

    flash('success', 'Your complaint has been sent to the admin team.');
    redirect('user/complaints.php');
}

$userBidsStmt = db()->prepare(
    "SELECT b.bid_id, b.bid_amount, b.bid_time, v.brand, v.model, v.registration_no
     FROM bids b
     JOIN vehicles v ON v.vehicle_id = b.vehicle_id
     WHERE b.user_id = :user_id
     ORDER BY b.bid_time DESC
     LIMIT 50"
);
$userBidsStmt->execute(['user_id' => $userId]);
$userBids = $userBidsStmt->fetchAll();

$complaintsStmt = db()->prepare(
    "SELECT c.*,
            v.brand,
            v.model,
            v.registration_no,
            b.bid_amount,
            b.bid_time
     FROM complaint c
     LEFT JOIN vehicles v ON v.vehicle_id = c.vehicle_id
     LEFT JOIN bids b ON b.bid_id = c.bid_id
     WHERE c.user_id = :user_id
     ORDER BY c.created_at DESC, c.complaint_id DESC"
);
$complaintsStmt->execute(['user_id' => $userId]);
$complaints = $complaintsStmt->fetchAll();

$pageTitle = 'Complaints';
require ROOT_PATH . '/includes/header.php';
?>
<section class="card complaint-hero">
    <div>
        <h2>Complaint</h2>
        <p>Report wrong bids, payment issues, or any other problem to the admin team.</p>
    </div>
    <a class="btn" href="<?php echo BASE_URL; ?>/place_bids.php">Back to Place Bids</a>
</section>

<section class="complaint-grid">
    <article class="card complaint-form-card">
        <h3>Send Complaint to Admin</h3>
        <form method="post">
            <input type="hidden" name="action" value="submit">
            <label for="issue_type">Issue Type</label>
            <select id="issue_type" name="issue_type" required>
                <option value="">Select issue</option>
                <?php foreach ($issueTypes as $issueType): ?>
                    <option value="<?php echo esc($issueType); ?>"><?php echo esc($issueType); ?></option>
                <?php endforeach; ?>
            </select>

            <label for="bid_id">Related Bid</label>
            <select id="bid_id" name="bid_id">
                <option value="">Select related bid (optional)</option>
                <?php foreach ($userBids as $bid): ?>
                    <option value="<?php echo (int)$bid['bid_id']; ?>">
                        <?php echo esc($bid['brand'] . ' ' . $bid['model']); ?>
                        | Bid #<?php echo (int)$bid['bid_id']; ?>
                        | Rs <?php echo number_format((float)$bid['bid_amount'], 2); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="description">Description</label>
            <textarea id="description" name="description" rows="6" placeholder="Explain the issue clearly. Example: I entered an extra zero while bidding." required></textarea>

            <button class="btn" type="submit">Submit Complaint</button>
        </form>
    </article>
</section>

<section class="card complaint-history-card">
    <div class="user-dash-panel-head">
        <h3>My Complaint History</h3>
    </div>
    <?php if (!$complaints): ?>
        <p class="user-dash-empty">No complaints submitted yet.</p>
    <?php else: ?>
        <div class="complaint-list">
            <?php foreach ($complaints as $complaint): ?>
                <article class="complaint-item">
                    <div class="complaint-item-head">
                        <div>
                            <strong><?php echo esc((string)$complaint['issue_type']); ?></strong>
                            <small><?php echo esc((string)$complaint['created_at']); ?></small>
                        </div>
                        <span class="status complaint-status complaint-status-<?php echo esc((string)$complaint['status']); ?>">
                            <?php echo strtoupper(str_replace('_', ' ', esc((string)$complaint['status']))); ?>
                        </span>
                    </div>

                    <?php if (!empty($complaint['brand']) || !empty($complaint['bid_amount'])): ?>
                        <p class="complaint-meta">
                            Related item:
                            <?php if (!empty($complaint['brand'])): ?>
                                <?php echo esc(trim((string)$complaint['brand'] . ' ' . (string)$complaint['model'])); ?>
                            <?php endif; ?>
                            <?php if (!empty($complaint['registration_no'])): ?>
                                (<?php echo esc((string)$complaint['registration_no']); ?>)
                            <?php endif; ?>
                            <?php if (!empty($complaint['bid_amount'])): ?>
                                | Bid Amount: Rs <?php echo number_format((float)$complaint['bid_amount'], 2); ?>
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>

                    <p class="complaint-description"><?php echo nl2br(esc((string)$complaint['description'])); ?></p>

                    <div class="complaint-reply-box">
                        <strong>Admin Reply</strong>
                        <p>
                            <?php echo !empty($complaint['admin_reply'])
                                ? nl2br(esc((string)$complaint['admin_reply']))
                                : 'No reply yet. The admin will respond here.'; ?>
                        </p>
                    </div>

                    <?php if (($complaint['status'] ?? '') === 'resolved'): ?>
                        <form method="post" class="inline complaint-item-actions">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="complaint_id" value="<?php echo (int)$complaint['complaint_id']; ?>">
                            <button class="btn btn-danger" type="submit" data-confirm="Delete this resolved complaint from your history?">
                                Delete
                            </button>
                        </form>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
