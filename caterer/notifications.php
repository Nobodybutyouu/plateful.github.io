<?php
require_once __DIR__ . '/../init.php';
require_auth(['caterer']);
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/customer_service.php';

$user = current_user();

$pageTitle = 'Notifications';
$pageStyles = [APP_URL . '/public/assets/css/dashboard.css'];

$successMessage = null;
$errorMessage = null;

$filter = $_GET['filter'] ?? 'all';
$allowedFilters = ['all', 'unread', 'booking', 'reviews', 'system'];
if (!in_array($filter, $allowedFilters, true)) {
    $filter = 'all';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $notificationId = (int) ($_POST['notification_id'] ?? 0);

    if (isset($_POST['current_filter'])) {
        $filter = in_array($_POST['current_filter'], $allowedFilters, true)
            ? $_POST['current_filter']
            : 'all';
    }

    if ($notificationId <= 0) {
        $errorMessage = 'Invalid notification selected.';
    } else {
        try {
            mark_notification_read($notificationId, (int) $user['id']);
            $successMessage = 'Notification marked as read.';
        } catch (Throwable $e) {
            $errorMessage = 'Unable to update notification. Please try again.';
        }
    }
}

$conditions = ['user_id = :user_id'];
$params = ['user_id' => (int) $user['id']];

switch ($filter) {
    case 'unread':
        $conditions[] = 'is_read = 0';
        break;
    case 'booking':
        $conditions[] = "type IN ('booking_request', 'booking_status')";
        break;
    case 'reviews':
        $conditions[] = "type IN ('review_received')";
        break;
    case 'system':
        $conditions[] = "type NOT IN ('booking_request', 'booking_status', 'review_received')";
        break;
}

$query = sprintf(
    'SELECT id, title, message, type, is_read, created_at FROM notifications WHERE %s ORDER BY created_at DESC',
    implode(' AND ', $conditions)
);

$stmt = db()->prepare($query);
$stmt->execute($params);
$notifications = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php include __DIR__ . '/../includes/caterer_sidebar.php'; ?>

    <div class="dashboard-content">
        <div class="page-header">
            <div>
                <h1 class="fw-bold">Notifications</h1>
                <p class="text-muted">Booking requests, reviews, and platform updates will appear here.</p>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <?php if ($successMessage): ?><div class="alert alert-success small mb-3"><?= htmlspecialchars($successMessage) ?></div><?php endif; ?>
                <?php if ($errorMessage): ?><div class="alert alert-danger small mb-3"><?= htmlspecialchars($errorMessage) ?></div><?php endif; ?>
                <?php
                    $filterOptions = [
                        'all' => 'All',
                        'unread' => 'Unread',
                        'booking' => 'Booking updates',
                        'reviews' => 'Reviews',
                        'system' => 'Other'
                    ];
                ?>
                <ul class="nav nav-pills mb-3">
                    <?php foreach ($filterOptions as $key => $label): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= $filter === $key ? 'active' : '' ?>" href="?filter=<?= urlencode($key) ?>"><?= htmlspecialchars($label) ?></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php if (empty($notifications)): ?>
                    <p class="text-muted mb-0">No notifications yet.</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($notifications as $notification): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="fw-semibold mb-1"><?= htmlspecialchars($notification['title']) ?></h6>
                                    <small class="text-muted d-block mb-1"><?= htmlspecialchars(date('M j, Y g:i A', strtotime($notification['created_at']))) ?></small>
                                    <p class="text-muted small mb-0"><?= nl2br(htmlspecialchars($notification['message'])) ?></p>
                                </div>
                                <?php if ((int) $notification['is_read'] === 0): ?>
                                    <form method="post" class="ms-3">
                                        <input type="hidden" name="notification_id" value="<?= (int) $notification['id'] ?>">
                                        <input type="hidden" name="current_filter" value="<?= htmlspecialchars($filter) ?>">
                                        <button class="btn btn-sm btn-outline-secondary" type="submit">Mark as read</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
