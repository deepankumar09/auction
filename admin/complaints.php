<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/functions.php';

requireAdminLogin();
ensureComplaintTable();

$statuses = ['open', 'in_progress', 'resolved'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['action'] ?? ''));
    $complaintId = (int)($_POST['complaint_id'] ?? 0);

    $complaintStmt = db()->prepare(
        'SELECT complaint_id, bid_id, issue_type
         FROM complaint
         WHERE complaint_id = :complaint_id
         LIMIT 1'
    );
    $complaintStmt->execute(['complaint_id' => $complaintId]);
    $complaint = $complaintStmt->fetch();

    if (!$complaint) {
        flash('error', 'Complaint record not found.');
        redirect('admin/complaints.php');
    }

    if ($action === 'delete_wrong_bid') {
        $bidId = (int)($complaint['bid_id'] ?? 0);
        if ($bidId <= 0) {
            flash('error', 'There is no linked bid to remove for this complaint.');
            redirect('admin/complaints.php');
        }

        $deleteStmt = db()->prepare('DELETE FROM bids WHERE bid_id = :bid_id LIMIT 1');
        $deleteStmt->execute(['bid_id' => $bidId]);

        if ($deleteStmt->rowCount() <= 0) {
            flash('error', 'Unable to remove the linked bid.');
            redirect('admin/complaints.php');
        }

        $replyText = trim((string)($_POST['admin_reply'] ?? ''));
        if ($replyText === '') {
            $replyText = 'The reported wrong bid has been removed by admin.';
        }

        $updateComplaintStmt = db()->prepare(
            "UPDATE complaint
             SET admin_reply = :admin_reply,
                 status = 'resolved'
             WHERE complaint_id = :complaint_id"
        );
        $updateComplaintStmt->execute([
            'admin_reply' => $replyText,
            'complaint_id' => $complaintId,
        ]);

        flash('success', 'Wrong bid removed and complaint marked as resolved.');
        redirect('admin/complaints.php');
    }

    if ($action === 'reply') {
        $status = trim((string)($_POST['status'] ?? 'open'));
        $adminReply = trim((string)($_POST['admin_reply'] ?? ''));

        if (!in_array($status, $statuses, true)) {
            flash('error', 'Invalid complaint status selected.');
            redirect('admin/complaints.php');
        }

        $updateStmt = db()->prepare(
            'UPDATE complaint
             SET admin_reply = :admin_reply,
                 status = :status
             WHERE complaint_id = :complaint_id'
        );
        $updateStmt->execute([
            'admin_reply' => $adminReply !== '' ? $adminReply : null,
            'status' => $status,
            'complaint_id' => $complaintId,
        ]);

        flash('success', 'Complaint response updated successfully.');
        redirect('admin/complaints.php');
    }
}

$complaints = db()->query(
    "SELECT c.*,
            u.name AS user_name,
            u.email AS user_email,
            u.phone AS user_phone,
            v.brand,
            v.model,
            v.registration_no,
            b.bid_amount,
            b.bid_time
     FROM complaint c
     JOIN users u ON u.user_id = c.user_id
     LEFT JOIN vehicles v ON v.vehicle_id = c.vehicle_id
     LEFT JOIN bids b ON b.bid_id = c.bid_id
     WHERE c.status <> 'resolved'
     ORDER BY c.created_at DESC, c.complaint_id DESC"
)->fetchAll();
$pendingCount = count($complaints);

$pageTitle = 'Complaints';
require ROOT_PATH . '/includes/header.php';
?>
<section class="card admin-dash-hero">
    <div>
        <h2>Complaint</h2>
        <p>Review user issues, check bid details, remove wrong bids, and reply from one place.</p>
    </div>
    <div class="admin-dash-quick-actions">
        <a class="btn" href="<?php echo BASE_URL; ?>/admin/manage_bids.php">Review Bids</a>
        <a class="btn btn-secondary" href="<?php echo BASE_URL; ?>/admin/dashboard.php">Back to Dashboard</a>
    </div>
</section>

<section class="admin-dash-stats complaint-admin-stats">
    <article class="card admin-dash-stat-card">
        <p>Open Complaints</p>
        <strong><?php echo count($complaints); ?></strong>
    </article>
    <article class="card admin-dash-stat-card">
        <p>Pending</p>
        <strong><?php echo $pendingCount; ?></strong>
    </article>
</section>

<section class="complaint-list">
    <?php if (!$complaints): ?>
        <article class="card">
            <p class="admin-dash-empty">No user complaints available.</p>
        </article>
    <?php else: ?>
        <?php foreach ($complaints as $complaint): ?>
            <article class="card complaint-item complaint-item-admin">
                <div class="complaint-item-head">
                    <div>
                        <strong><?php echo esc((string)$complaint['issue_type']); ?></strong>
                        <small>
                            Complaint #<?php echo (int)$complaint['complaint_id']; ?>
                            | <?php echo esc((string)$complaint['created_at']); ?>
                        </small>
                    </div>
                    <span class="status complaint-status complaint-status-<?php echo esc((string)$complaint['status']); ?>">
                        <?php echo strtoupper(str_replace('_', ' ', esc((string)$complaint['status']))); ?>
                    </span>
                </div>

                <div class="complaint-admin-meta">
                    <p><strong>User:</strong> <?php echo esc((string)$complaint['user_name']); ?></p>
                    <p><strong>Email:</strong> <?php echo esc((string)$complaint['user_email']); ?></p>
                    <p><strong>Phone:</strong> <?php echo esc((string)$complaint['user_phone']); ?></p>
                    <p>
                        <strong>Vehicle:</strong>
                        <?php echo !empty($complaint['brand'])
                            ? esc(trim((string)$complaint['brand'] . ' ' . (string)$complaint['model'])) . ' (' . esc((string)$complaint['registration_no']) . ')'
                            : 'Not linked'; ?>
                    </p>
                    <p>
                        <strong>Bid Details:</strong>
                        <?php echo !empty($complaint['bid_amount'])
                            ? 'Bid #' . (int)($complaint['bid_id'] ?? 0) . ' | Rs ' . number_format((float)$complaint['bid_amount'], 2) . ' | ' . esc((string)$complaint['bid_time'])
                            : 'No linked bid'; ?>
                    </p>
                </div>

                <p class="complaint-description"><?php echo nl2br(esc((string)$complaint['description'])); ?></p>

                <form method="post" class="complaint-admin-form">
                    <input type="hidden" name="action" value="reply">
                    <input type="hidden" name="complaint_id" value="<?php echo (int)$complaint['complaint_id']; ?>">

                    <label for="status_<?php echo (int)$complaint['complaint_id']; ?>">Status</label>
                    <select id="status_<?php echo (int)$complaint['complaint_id']; ?>" name="status">
                        <?php foreach ($statuses as $status): ?>
                            <option value="<?php echo esc($status); ?>" <?php echo ($complaint['status'] ?? '') === $status ? 'selected' : ''; ?>>
                                <?php echo esc(strtoupper(str_replace('_', ' ', $status))); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label for="reply_<?php echo (int)$complaint['complaint_id']; ?>">Reply to User</label>
                    <textarea id="reply_<?php echo (int)$complaint['complaint_id']; ?>" name="admin_reply" rows="4" placeholder="Type your reply to the user"><?php echo esc((string)($complaint['admin_reply'] ?? '')); ?></textarea>

                    <div class="inline complaint-admin-actions">
                        <button class="btn" type="submit">Save Reply</button>
                    </div>
                </form>

                <?php if (($complaint['issue_type'] ?? '') === 'Wrong Bid' && !empty($complaint['bid_id'])): ?>
                    <form method="post" class="inline">
                        <input type="hidden" name="action" value="delete_wrong_bid">
                        <input type="hidden" name="complaint_id" value="<?php echo (int)$complaint['complaint_id']; ?>">
                        <input type="hidden" name="admin_reply" value="<?php echo esc((string)($complaint['admin_reply'] ?? '')); ?>">
                        <button class="btn btn-danger" type="submit" data-confirm="Remove the linked wrong bid and mark this complaint resolved?">
                            Delete Wrong Bid
                        </button>
                    </form>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
