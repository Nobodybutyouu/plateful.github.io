<?php
require_once __DIR__ . '/../init.php';
require_auth(['customer']);
require_once __DIR__ . '/../lib/customer_service.php';

$pageTitle = 'My Bookings';
$pageStyles = [APP_URL . '/public/assets/css/dashboard.css'];

$user = current_user();

$errors = [];
$successMessage = null;

$selectedBookingId = isset($_GET['view']) ? (int) $_GET['view'] : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_booking') {
        $bookingId = (int) ($_POST['booking_id'] ?? 0);

        if ($bookingId <= 0) {
            $errors[] = 'Invalid booking selected.';
        } else {
            $payload = [];

            if (array_key_exists('event_date', $_POST)) {
                $payload['event_date'] = $_POST['event_date'] !== '' ? trim((string) $_POST['event_date']) : null;
            }

            if (array_key_exists('event_time', $_POST)) {
                $payload['event_time'] = $_POST['event_time'] !== '' ? trim((string) $_POST['event_time']) : null;
            }

            if (array_key_exists('guest_count', $_POST)) {
                $payload['guest_count'] = $_POST['guest_count'] !== '' ? (int) $_POST['guest_count'] : null;
            }

            if (array_key_exists('custom_request', $_POST)) {
                $payload['custom_request'] = $_POST['custom_request'];
            }

            try {
                update_customer_booking($bookingId, $user['id'], $payload);
                $successMessage = 'Booking details updated successfully.';
                $selectedBookingId = $bookingId;
            } catch (InvalidArgumentException $e) {
                $errors[] = $e->getMessage();
            } catch (RuntimeException $e) {
                $errors[] = $e->getMessage();
            } catch (Throwable $e) {
                $errors[] = 'Unable to update booking right now. Please try again later.';
            }
        }
    }
}

if ($successMessage === null && isset($_GET['new_booking']) && $_GET['new_booking'] !== '') {
    $newBookingRef = (int) $_GET['new_booking'];

    if ($newBookingRef > 0) {
        $successMessage = sprintf(
            'Booking request #%d submitted! The caterer will review your request and keep you updated here.',
            $newBookingRef
        );
    } else {
        $successMessage = 'Booking request submitted! The caterer will review your request and keep you updated here.';
    }
}

$bookings = get_customer_bookings($user['id']);

$selectedBookingDetail = null;

if ($selectedBookingId && $selectedBookingId > 0) {
    $selectedBookingDetail = get_customer_booking_detail($selectedBookingId, $user['id']);

    if (!$selectedBookingDetail) {
        $errors[] = 'Booking not found or unavailable.';
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php include __DIR__ . '/../includes/customer_sidebar.php'; ?>
    <div class="dashboard-content">
        <div class="page-header">
            <div>
                <h1 class="fw-bold">Booking timeline</h1>
                <p class="text-muted">Track status and manage your upcoming and past events.</p>
            </div>
            <a href="<?= APP_URL ?>/customer/browse.php" class="btn btn-primary"><i class="bi bi-plus"></i> New Booking</a>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger"><ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul></div>
        <?php endif; ?>

        <?php if ($successMessage): ?>
            <div class="alert alert-success" id="pageSuccessAlert"><?= htmlspecialchars($successMessage) ?></div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <h5 class="fw-semibold mb-3">Upcoming events</h5>
                <?php if (empty($bookings['upcoming'])): ?>
                    <p class="text-muted mb-0">You have no upcoming events at the moment.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-modern">
                            <thead>
                                <tr>
                                    <th>Event</th>
                                    <th>Caterer</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bookings['upcoming'] as $booking): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($booking['event_type']) ?></td>
                                        <td><?= htmlspecialchars($booking['business_name']) ?></td>
                                        <td><?= htmlspecialchars(date('M j, Y', strtotime($booking['event_date']))) ?></td>
                                        <td>
                                            <span class="badge-status <?= htmlspecialchars($booking['status']) ?>">
                                                <?php if ($booking['status'] === 'pending'): ?>
                                                    <i class="bi bi-hourglass-split"></i> Pending review
                                                <?php elseif ($booking['status'] === 'awaiting_payment'): ?>
                                                    <i class="bi bi-cash-coin"></i> Awaiting payment
                                                <?php elseif ($booking['status'] === 'confirmed'): ?>
                                                    <i class="bi bi-check-circle"></i> Confirmed
                                                <?php else: ?>
                                                    <i class="bi bi-info-circle"></i> <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $booking['status']))) ?>
                                                <?php endif; ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <div class="btn-group" role="group">
                                                <a href="<?= APP_URL ?>/customer/bookings.php?view=<?= (int) $booking['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-eye me-1"></i>View
                                                </a>
                                                <?php if (!empty($booking['is_editable'])): ?>
                                                    <button
                                                        type="button"
                                                        class="btn btn-sm btn-outline-secondary"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#editBookingModal"
                                                        data-booking
                                                        data-booking-id="<?= (int) $booking['id'] ?>"
                                                        data-booking-date="<?= htmlspecialchars($booking['event_date']) ?>"
                                                        data-booking-time="<?= htmlspecialchars($booking['event_time'] ?? '') ?>"
                                                        data-booking-guests="<?= (int) $booking['guest_count'] ?>"
                                                        data-booking-notes="<?= htmlspecialchars($booking['custom_request'] ?? '') ?>"
                                                        data-booking-package="<?= htmlspecialchars($booking['package_name'] ?? '') ?>"
                                                        data-booking-status="<?= htmlspecialchars($booking['status']) ?>"
                                                    >
                                                        <i class="bi bi-pencil me-1"></i>Edit
                                                    </button>
                                                <?php endif; ?>
                                                <?php if ($booking['status'] === 'awaiting_payment'): ?>
                                                    <a href="<?= APP_URL ?>/customer/pay_booking.php?id=<?= (int) $booking['id'] ?>" class="btn btn-sm btn-primary">
                                                        <i class="bi bi-wallet2 me-1"></i>Pay
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($selectedBookingDetail && isset($selectedBookingDetail['booking'])): ?>
            <?php
                $bookingDetail = $selectedBookingDetail['booking'];
                $bookingTimeline = $selectedBookingDetail['timeline'] ?? [];
                $isBookingEditable = in_array($bookingDetail['status'], ['pending', 'awaiting_payment'], true);
                $pendingPaymentCount = (int) ($bookingDetail['pending_payment_count'] ?? 0);
            ?>
            <div class="card border-0 shadow-sm mb-4" id="bookingDetailCard">
                <div class="card-body p-4">
                    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-3">
                        <div>
                            <h5 class="fw-semibold mb-1">Booking #<?= (int) $bookingDetail['id'] ?> · <?= htmlspecialchars($bookingDetail['business_name']) ?></h5>
                            <p class="text-muted mb-0">
                                <i class="bi bi-calendar-event me-1"></i><?= htmlspecialchars(date('M j, Y', strtotime($bookingDetail['event_date']))) ?>
                                <?php if (!empty($bookingDetail['event_time'])): ?>· <?= htmlspecialchars(date('g:i A', strtotime($bookingDetail['event_time']))) ?><?php endif; ?>
                                · <strong><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $bookingDetail['status']))) ?></strong>
                            </p>
                        </div>
                        <div class="mt-3 mt-lg-0 d-flex gap-2">
                            <?php if ($bookingDetail['status'] === 'awaiting_payment'): ?>
                                <a class="btn btn-sm btn-primary" href="<?= APP_URL ?>/customer/pay_booking.php?id=<?= (int) $bookingDetail['id'] ?>">
                                    <i class="bi bi-wallet2 me-1"></i>Settle payment
                                </a>
                            <?php endif; ?>
                            <?php if ($isBookingEditable): ?>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-secondary"
                                    data-bs-toggle="modal"
                                    data-bs-target="#editBookingModal"
                                    data-booking
                                    data-booking-id="<?= (int) $bookingDetail['id'] ?>"
                                    data-booking-date="<?= htmlspecialchars($bookingDetail['event_date']) ?>"
                                    data-booking-time="<?= htmlspecialchars($bookingDetail['event_time'] ? date('H:i', strtotime($bookingDetail['event_time'])) : '') ?>"
                                    data-booking-guests="<?= (int) $bookingDetail['guest_count'] ?>"
                                    data-booking-notes="<?= htmlspecialchars($bookingDetail['custom_request'] ?? '') ?>"
                                    data-booking-status="<?= htmlspecialchars($bookingDetail['status']) ?>"
                                >
                                    <i class="bi bi-pencil me-1"></i>Edit booking
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($pendingPaymentCount > 0): ?>
                        <div class="alert alert-info small">
                            <i class="bi bi-exclamation-circle me-2"></i>
                            Payment confirmation pending with the caterer. Editing event details is limited until it’s verified.
                        </div>
                    <?php endif; ?>

                    <div class="row g-4">
                        <div class="col-lg-6">
                            <h6 class="fw-semibold mb-3">Event details</h6>
                            <dl class="row mb-0 small">
                                <dt class="col-sm-4">Event type</dt>
                                <dd class="col-sm-8"><?= htmlspecialchars($bookingDetail['event_type_name'] ?? 'Custom event') ?></dd>
                                <dt class="col-sm-4">Guests</dt>
                                <dd class="col-sm-8"><?= (int) $bookingDetail['guest_count'] ?></dd>
                                <dt class="col-sm-4">Package</dt>
                                <dd class="col-sm-8">
                                    <?= $bookingDetail['package_name'] ? htmlspecialchars($bookingDetail['package_name']) : '<span class="text-muted">No package selected</span>' ?>
                                    <?php if ($bookingDetail['package_name'] && $bookingDetail['package_price'] !== null): ?>
                                        <span class="text-muted"> · <?= format_currency((float) $bookingDetail['package_price']) ?> / head</span>
                                    <?php endif; ?>
                                </dd>
                                <dt class="col-sm-4">Service area</dt>
                                <dd class="col-sm-8"><?= htmlspecialchars($bookingDetail['service_area'] ?? 'Not specified') ?></dd>
                                <dt class="col-sm-4">Venue</dt>
                                <dd class="col-sm-8"><?= htmlspecialchars($bookingDetail['location'] ?? 'Coordinate with caterer') ?></dd>
                            </dl>
                        </div>
                        <div class="col-lg-6">
                            <h6 class="fw-semibold mb-3">Customer notes</h6>
                            <p class="small mb-0">
                                <?= $bookingDetail['custom_request'] ? nl2br(htmlspecialchars($bookingDetail['custom_request'])) : '<span class="text-muted">No notes provided.</span>' ?>
                            </p>
                        </div>
                    </div>

                    <hr class="my-4">

                    <h6 class="fw-semibold mb-3">Status history</h6>
                    <?php if (empty($bookingTimeline)): ?>
                        <p class="text-muted small mb-0">No timeline entries recorded yet.</p>
                    <?php else: ?>
                        <ul class="list-unstyled timeline small mb-0">
                            <?php foreach ($bookingTimeline as $entry): ?>
                                <li class="mb-3">
                                    <div class="fw-semibold"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $entry['new_status'] ?? ''))) ?></div>
                                    <div class="text-muted"><?= htmlspecialchars(date('M j, Y g:i A', strtotime($entry['created_at']))) ?></div>
                                    <?php if (!empty($entry['message'])): ?>
                                        <div><?= nl2br(htmlspecialchars($entry['message'])) ?></div>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h5 class="fw-semibold mb-3">Past events</h5>
                <?php if (empty($bookings['completed'])): ?>
                    <p class="text-muted mb-0">No completed bookings yet. Once events finish, you can review them here.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-modern">
                            <thead>
                                <tr>
                                    <th>Event</th>
                                    <th>Caterer</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Review</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bookings['completed'] as $booking): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($booking['event_type']) ?></td>
                                        <td><?= htmlspecialchars($booking['business_name']) ?></td>
                                        <td><?= htmlspecialchars(date('M j, Y', strtotime($booking['event_date']))) ?></td>
                                        <td><span class="badge-status completed"><i class="bi bi-check-circle"></i> Completed</span></td>
                                        <td><a href="<?= APP_URL ?>/customer/reviews.php" class="btn btn-sm btn-outline-primary">Write review</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<div class="modal fade" id="editBookingModal" tabindex="-1" aria-labelledby="editBookingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post" id="editBookingForm">
                <input type="hidden" name="action" value="update_booking">
                <input type="hidden" name="booking_id" id="editBookingId">
                <div class="modal-header">
                    <h5 class="modal-title" id="editBookingModalLabel">Edit booking details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="editBookingDate" class="form-label">Event date</label>
                            <input type="date" class="form-control" name="event_date" id="editBookingDate" min="<?= htmlspecialchars((new DateTime('today'))->format('Y-m-d')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="editBookingTime" class="form-label">Event time</label>
                            <input type="time" class="form-control" name="event_time" id="editBookingTime">
                        </div>
                        <div class="col-md-6">
                            <label for="editGuestCount" class="form-label">Guest count</label>
                            <input type="number" class="form-control" name="guest_count" id="editGuestCount" min="1" placeholder="e.g. 120">
                        </div>
                        <div class="col-12">
                            <label for="editCustomRequest" class="form-label">Special notes</label>
                            <textarea class="form-control" name="custom_request" id="editCustomRequest" rows="4" placeholder="Mention venue details, cuisine notes, allergens, etc."></textarea>
                            <div class="form-text">Leave blank to clear optional notes.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const successAlert = document.getElementById('pageSuccessAlert');
    if (successAlert) {
        setTimeout(() => {
            successAlert.remove();
        }, 6000);
    }

    const editModalEl = document.getElementById('editBookingModal');

    if (!editModalEl) {
        return;
    }

    const bookingButtons = document.querySelectorAll('[data-booking]');
    const bookingIdInput = document.getElementById('editBookingId');
    const dateInput = document.getElementById('editBookingDate');
    const timeInput = document.getElementById('editBookingTime');
    const guestInput = document.getElementById('editGuestCount');
    const notesInput = document.getElementById('editCustomRequest');
    const modalTitle = document.getElementById('editBookingModalLabel');

    bookingButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const bookingId = button.getAttribute('data-booking-id') || '';
            const bookingDate = button.getAttribute('data-booking-date') || '';
            const bookingTime = button.getAttribute('data-booking-time') || '';
            const guestCount = button.getAttribute('data-booking-guests') || '';
            const notes = button.getAttribute('data-booking-notes') || '';
            const status = button.getAttribute('data-booking-status') || '';

            bookingIdInput.value = bookingId;
            dateInput.value = bookingDate;
            timeInput.value = bookingTime;
            guestInput.value = guestCount;
            notesInput.value = notes;

            modalTitle.textContent = `Edit booking #${bookingId} (${status.replace(/_/g, ' ')})`;
        });
    });

    editModalEl.addEventListener('hidden.bs.modal', () => {
        bookingIdInput.value = '';
        dateInput.value = '';
        timeInput.value = '';
        guestInput.value = '';
        notesInput.value = '';
    });
});
</script>
