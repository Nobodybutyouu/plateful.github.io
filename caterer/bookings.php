<?php
require_once __DIR__ . '/../init.php';
require_auth(['caterer']);
require_once __DIR__ . '/../lib/caterer_service.php';

$user = current_user();

if ($user['status'] === 'pending') {
    redirect('/caterer/pending.php');
}

$profile = get_caterer_profile($user['id']);

if (!$profile) {
    redirect('/caterer/profile.php');
}

$pageTitle = 'Booking Requests';
$pageStyles = [APP_URL . '/public/assets/css/dashboard.css'];

$successMessage = null;
$errorMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bookingId = (int) ($_POST['booking_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $note = trim($_POST['note'] ?? '');
    $paymentId = (int) ($_POST['payment_id'] ?? 0);

    if ($action === 'confirm_payment') {
        if ($bookingId > 0 && $paymentId > 0) {
            try {
                confirm_booking_payment($bookingId, $paymentId, $user['id'], (int) $profile['id']);
                $successMessage = 'Payment confirmed and booking status updated to confirmed.';
            } catch (Throwable $e) {
                $errorMessage = 'Unable to confirm payment: ' . $e->getMessage();
            }
        } else {
            $errorMessage = 'Invalid payment confirmation request.';
        }
    } else {
        $actionMap = [
            'approve' => 'awaiting_payment',
            'decline' => 'declined',
            'complete' => 'completed',
            'cancel' => 'cancelled',
        ];

        if ($bookingId > 0 && isset($actionMap[$action])) {
            try {
                update_booking_status($bookingId, $user['id'], (int) $profile['id'], $actionMap[$action], $note ?: null);
                $successMessage = match ($action) {
                    'approve' => 'Booking approved. Payment is now pending from the customer.',
                    'decline' => 'Booking declined and logged.',
                    'complete' => 'Booking marked as completed.',
                    'cancel' => 'Booking cancelled.',
                    default => 'Booking updated.',
                };
            } catch (Throwable $e) {
                $errorMessage = 'Unable to update booking: ' . $e->getMessage();
            }
        } else {
            $errorMessage = 'Invalid booking action requested.';
        }
    }
}

$bookings = get_caterer_bookings((int) $profile['id']);
$pending = $bookings['pending'];
$approved = $bookings['approved'];
$completed = $bookings['completed'];

include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php include __DIR__ . '/../includes/caterer_sidebar.php'; ?>

    <div class="dashboard-content">
        <div class="page-header">
            <div>
                <h1 class="fw-bold">Booking management</h1>
                <p class="text-muted">Approve requests, coordinate details, and keep clients updated.</p>
            </div>
        </div>

        <?php if ($successMessage): ?><div class="alert alert-success small"><?= htmlspecialchars($successMessage) ?></div><?php endif; ?>
        <?php if ($errorMessage): ?><div class="alert alert-danger small"><?= htmlspecialchars($errorMessage) ?></div><?php endif; ?>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <h5 class="fw-semibold mb-3">Pending approval (<?= count($pending) ?>)</h5>
                <?php if (empty($pending)): ?>
                    <p class="text-muted mb-0">No pending booking requests. Great job staying responsive!</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($pending as $request): ?>
                            <div class="list-group-item">
                                <div class="d-flex flex-column flex-lg-row justify-content-lg-between align-items-lg-center">
                                    <div class="mb-3 mb-lg-0">
                                        <h6 class="fw-semibold mb-1">#<?= (int) $request['id'] ?> · <?= htmlspecialchars($request['customer_name']) ?></h6>
                                        <small class="text-muted"><i class="bi bi-calendar-event me-1"></i><?= htmlspecialchars($request['event_type']) ?> · <?= htmlspecialchars(date('M j, Y', strtotime($request['event_date']))) ?><?php if (!empty($request['event_time'])): ?> · <?= htmlspecialchars(date('g:i A', strtotime($request['event_time']))) ?><?php endif; ?></small>
                                        <p class="small text-muted mt-2 mb-0">
                                            <strong>Guests:</strong> <?= (int) $request['guest_count'] ?><?php if (!empty($request['package_name'])): ?> · <strong>Package:</strong> <?= htmlspecialchars($request['package_name']) ?><?php endif; ?>
                                            <br><?= $request['custom_request'] ? nl2br(htmlspecialchars($request['custom_request'])) : 'No additional notes.' ?>
                                        </p>
                                    </div>
                                    <form method="post" class="d-flex flex-column flex-sm-row gap-2">
                                        <input type="hidden" name="booking_id" value="<?= (int) $request['id'] ?>">
                                        <input type="hidden" name="note" value="">
                                        <button class="btn btn-outline-danger btn-sm" name="action" value="decline" type="submit"><i class="bi bi-x-circle me-1"></i>Decline</button>
                                        <button class="btn btn-primary btn-sm" name="action" value="approve" type="submit"><i class="bi bi-check-circle me-1"></i>Approve</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-4">
                        <h5 class="fw-semibold mb-3">Approved & upcoming (<?= count($approved) ?>)</h5>
                        <?php if (empty($approved)): ?>
                            <p class="text-muted mb-0">No approved bookings yet. Approve pending requests to see them here.</p>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($approved as $booking): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex justify-content-between align-items-start flex-column flex-md-row gap-3">
                                            <div class="flex-grow-1">
                                                <h6 class="fw-semibold mb-1">#<?= (int) $booking['id'] ?> · <?= htmlspecialchars($booking['customer_name']) ?></h6>
                                                <div class="small text-muted mb-2">
                                                    <span class="badge-status <?= htmlspecialchars($booking['status']) ?>">
                                                        <?php if ($booking['status'] === 'awaiting_payment'): ?>
                                                            <i class="bi bi-cash-coin"></i> Awaiting payment
                                                        <?php elseif ($booking['status'] === 'confirmed'): ?>
                                                            <i class="bi bi-check2-circle"></i> Confirmed
                                                        <?php else: ?>
                                                            <i class="bi bi-info-circle"></i> <?= htmlspecialchars(ucfirst($booking['status'])) ?>
                                                        <?php endif; ?>
                                                    </span>
                                                    · <?= htmlspecialchars($booking['event_type']) ?> · <?= htmlspecialchars(date('M j, Y', strtotime($booking['event_date']))) ?><?php if (!empty($booking['event_time'])): ?> · <?= htmlspecialchars(date('g:i A', strtotime($booking['event_time']))) ?><?php endif; ?>
                                                </div>
                                                <div class="small text-muted mb-2">
                                                    <strong>Guests:</strong> <?= (int) $booking['guest_count'] ?><?php if (!empty($booking['package_name'])): ?> · <strong>Package:</strong> <?= htmlspecialchars($booking['package_name']) ?><?php endif; ?>
                                                </div>
                                                <?php if ($booking['custom_request']): ?>
                                                    <p class="small text-muted mb-2"><?= nl2br(htmlspecialchars($booking['custom_request'])) ?></p>
                                                <?php endif; ?>

                                                <?php if (!empty($booking['pending_payment_id'])): ?>
                                                    <div class="border rounded bg-light p-3 small">
                                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                                            <span class="fw-semibold text-secondary">Pending payment review</span>
                                                            <span class="text-muted">Submitted <?= htmlspecialchars(date('M j, Y g:i A', strtotime($booking['pending_payment_created_at']))) ?></span>
                                                        </div>
                                                        <div class="row g-2 mb-2">
                                                            <div class="col-sm-6"><strong>Amount:</strong> ₱<?= number_format((float) $booking['pending_payment_amount'], 2) ?></div>
                                                            <div class="col-sm-6"><strong>Method:</strong> <?= htmlspecialchars(ucfirst($booking['pending_payment_method'] ?? '')) ?></div>
                                                            <?php if (!empty($booking['pending_payment_channel'])): ?><div class="col-sm-6"><strong>Channel:</strong> <?= htmlspecialchars($booking['pending_payment_channel']) ?></div><?php endif; ?>
                                                            <?php if (!empty($booking['pending_payment_reference'])): ?><div class="col-sm-6"><strong>Reference:</strong> <?= htmlspecialchars($booking['pending_payment_reference']) ?></div><?php endif; ?>
                                                        </div>
                                                        <?php if (!empty($booking['pending_payment_proof_path'])): ?>
                                                            <a class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener" href="<?= APP_URL . '/' . ltrim($booking['pending_payment_proof_path'], '/') ?>">
                                                                <i class="bi bi-receipt-cutoff me-1"></i>View proof image
                                                            </a>
                                                        <?php else: ?>
                                                            <span class="text-muted">No proof image attached.</span>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <form method="post" class="text-end">
                                                <input type="hidden" name="booking_id" value="<?= (int) $booking['id'] ?>">
                                                <?php if (!empty($booking['pending_payment_id']) && $booking['status'] === 'awaiting_payment'): ?>
                                                    <input type="hidden" name="payment_id" value="<?= (int) $booking['pending_payment_id'] ?>">
                                                    <button class="btn btn-primary btn-sm mb-2" name="action" value="confirm_payment" type="submit">
                                                        <i class="bi bi-shield-check me-1"></i>Confirm payment
                                                    </button>
                                                <?php endif; ?>
                                                <div class="btn-group btn-group-sm w-100">
                                                    <?php if ($booking['status'] === 'confirmed'): ?>
                                                        <button class="btn btn-outline-success" name="action" value="complete" type="submit"><i class="bi bi-check2-circle me-1"></i>Mark completed</button>
                                                    <?php endif; ?>
                                                    <button class="btn btn-outline-secondary" name="action" value="cancel" type="submit"><i class="bi bi-slash-circle me-1"></i>Cancel</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-4">
                        <h5 class="fw-semibold mb-3">Completed bookings (<?= count($completed) ?>)</h5>
                        <?php if (empty($completed)): ?>
                            <p class="text-muted mb-0">Completed bookings will appear here once events wrap up.</p>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($completed as $booking): ?>
                                    <div class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="fw-semibold mb-1">#<?= (int) $booking['id'] ?> · <?= htmlspecialchars($booking['customer_name']) ?></h6>
                                            <small class="text-muted"><?= htmlspecialchars($booking['event_type']) ?> · <?= htmlspecialchars(date('M j, Y', strtotime($booking['event_date']))) ?></small>
                                        </div>
                                        <div>
                                            <?php
                                                $rating = $booking['rating'] ?? 0;
                                                for ($i = 0; $i < 5; $i++):
                                            ?>
                                                <i class="bi <?= $i < $rating ? 'bi-star-fill text-warning' : 'bi-star text-muted' ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
