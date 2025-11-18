<?php
require_once __DIR__ . '/../init.php';
require_auth(['customer']);
require_once __DIR__ . '/../lib/customer_service.php';

$pageTitle = 'My Reviews';
$pageStyles = [APP_URL . '/public/assets/css/dashboard.css'];

$user = current_user();
$successMessage = null;
$errorMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formType = $_POST['form_type'] ?? '';

    if ($formType === 'submit_review') {
        $bookingId = (int) ($_POST['booking_id'] ?? 0);
        $catererProfileId = (int) ($_POST['caterer_profile_id'] ?? 0);
        $rating = (int) ($_POST['rating'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');

        if ($bookingId <= 0 || $catererProfileId <= 0) {
            $errorMessage = 'Invalid booking selected.';
        } else {
            try {
                customer_submit_review($bookingId, $user['id'], $catererProfileId, $rating, $comment !== '' ? $comment : null);
                $successMessage = 'Thanks for your feedback! Your review has been submitted.';
            } catch (Throwable $e) {
                $errorMessage = 'Unable to submit review: ' . $e->getMessage();
            }
        }
    } elseif ($formType === 'mark_notification') {
        $notificationId = (int) ($_POST['notification_id'] ?? 0);
        if ($notificationId > 0) {
            mark_notification_read($notificationId, $user['id']);
        }
    }
}

$reviews = get_customer_reviews($user['id']);
$pendingReviews = $reviews['pending'];
$submittedReviews = $reviews['submitted'];
$notifications = get_customer_notifications($user['id']);

include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php include __DIR__ . '/../includes/customer_sidebar.php'; ?>

    <div class="dashboard-content">
        <div class="page-header">
            <div>
                <h1 class="fw-bold">Reviews & ratings</h1>
                <p class="text-muted">Share your experiences to help other customers choose the best caterer.</p>
            </div>
            <button class="btn btn-outline-primary"><i class="bi bi-bell me-2"></i>Review reminders</button>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <h5 class="fw-semibold mb-3">Pending reviews</h5>
                <?php if ($successMessage): ?><div class="alert alert-success small mb-3"><?= htmlspecialchars($successMessage) ?></div><?php endif; ?>
                <?php if ($errorMessage): ?><div class="alert alert-danger small mb-3"><?= htmlspecialchars($errorMessage) ?></div><?php endif; ?>
                <?php if (empty($pendingReviews)): ?>
                    <p class="text-muted mb-0">You&#39;re all caught up—no pending reviews.</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($pendingReviews as $review): ?>
                            <div class="list-group-item d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                                <div>
                                    <h6 class="fw-semibold mb-1">#<?= (int) $review['id'] ?> · <?= htmlspecialchars($review['business_name']) ?></h6>
                                    <small class="text-muted"><i class="bi bi-calendar-event me-1"></i><?= htmlspecialchars(date('M j, Y', strtotime($review['event_date']))) ?></small>
                                </div>
                                <button
                                    class="btn btn-primary mt-3 mt-md-0"
                                    data-bs-toggle="modal"
                                    data-bs-target="#reviewModal"
                                    data-booking-id="<?= (int) $review['id'] ?>"
                                    data-caterer-profile-id="<?= (int) $review['caterer_profile_id'] ?>"
                                    data-business-name="<?= htmlspecialchars($review['business_name']) ?>"
                                ><i class="bi bi-star me-2"></i>Leave review</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h5 class="fw-semibold mb-3">Submitted reviews</h5>
                <?php if (empty($submittedReviews)): ?>
                    <p class="text-muted mb-0">No reviews yet. Share feedback once your event is completed.</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($submittedReviews as $review): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="fw-semibold mb-1">#<?= (int) $review['id'] ?> · <?= htmlspecialchars($review['business_name']) ?></h6>
                                        <small class="text-muted"><i class="bi bi-calendar-check me-1"></i><?= htmlspecialchars(date('M j, Y', strtotime($review['event_date']))) ?></small>
                                    </div>
                                    <div>
                                        <?php $rating = (int) $review['rating']; ?>
                                        <?php for ($i = 0; $i < 5; $i++): ?>
                                            <i class="bi <?= $i < $rating ? 'bi-star-fill text-warning' : 'bi-star text-muted' ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <?php if (!empty($review['comment'])): ?>
                                    <p class="text-muted small mt-3 mb-0"><?= nl2br(htmlspecialchars($review['comment'])) ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h5 class="fw-semibold mb-3">Notifications</h5>
                <?php if (empty($notifications)): ?>
                    <p class="text-muted mb-0">No notifications yet. Booking updates and approvals will show up here.</p>
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
                                        <input type="hidden" name="form_type" value="mark_notification">
                                        <input type="hidden" name="notification_id" value="<?= (int) $notification['id'] ?>">
                                        <button class="btn btn-sm btn-outline-secondary" type="submit">Mark as read</button>
                                    </form>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted">Read</span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="reviewModal" tabindex="-1" aria-labelledby="reviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="form_type" value="submit_review">
                <input type="hidden" name="booking_id" id="review-booking-id" value="">
                <input type="hidden" name="caterer_profile_id" id="review-caterer-profile-id" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="reviewModalLabel">Leave a review</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="review-business-label" class="fw-semibold mb-3"></p>
                    <div class="mb-3">
                        <label class="form-label">Rating</label>
                        <select name="rating" class="form-select" required>
                            <option value="" selected disabled>Select rating</option>
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                <option value="<?= $i ?>"><?= $i ?> star<?= $i > 1 ? 's' : '' ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="review-comment" class="form-label">Comment</label>
                        <textarea id="review-comment" name="comment" class="form-control" rows="4" placeholder="Share details about the service, presentation, and overall experience."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit review</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script>
    const reviewModal = document.getElementById('reviewModal');
    if (reviewModal) {
        reviewModal.addEventListener('show.bs.modal', event => {
            const button = event.relatedTarget;
            if (!button) return;
            const bookingId = button.getAttribute('data-booking-id');
            const profileId = button.getAttribute('data-caterer-profile-id');
            const businessName = button.getAttribute('data-business-name');

            document.getElementById('review-booking-id').value = bookingId || '';
            document.getElementById('review-caterer-profile-id').value = profileId || '';
            const labelEl = document.getElementById('review-business-label');
            if (labelEl) {
                labelEl.textContent = businessName ? `Review for ${businessName}` : '';
            }
        });
    }
</script>
