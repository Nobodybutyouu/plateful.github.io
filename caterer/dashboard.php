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

$pageTitle = 'Caterer Dashboard';
$pageStyles = [APP_URL . '/public/assets/css/dashboard.css'];

$dashboard = get_caterer_dashboard_stats((int) $profile['id']);
$stats = $dashboard['counts'];
$recentBookings = $dashboard['recent_bookings'];

$isAvailable = ($profile['availability_status'] ?? 'available') === 'available';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_availability'])) {
    $newStatus = $isAvailable ? 'unavailable' : 'available';

    try {
        set_caterer_availability_status((int) $profile['id'], (int) $user['id'], $newStatus);
        $isAvailable = $newStatus === 'available';
        $profile['availability_status'] = $newStatus;

        if (isset($_POST['accept']) && $_POST['accept'] === 'json') {
            json_response(['status' => 'ok', 'availability_status' => $newStatus]);
        }
    } catch (Throwable $e) {
        if (isset($_POST['accept']) && $_POST['accept'] === 'json') {
            json_response(['status' => 'error', 'message' => 'Unable to update availability right now.'], 422);
        }
        $availabilityError = 'Unable to update availability right now. Please try again.';
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php include __DIR__ . '/../includes/caterer_sidebar.php'; ?>

    <div class="dashboard-content">
        <div class="page-header">
            <div>
                <h1 class="fw-bold">Hello, <?= htmlspecialchars($user['name']) ?></h1>
                <p class="text-muted">Stay on top of new requests, manage menus, and update your availability.</p>
            </div>
            <a href="<?= APP_URL ?>/caterer/menu.php" class="btn btn-primary"><i class="bi bi-plus-lg me-2"></i>Add package</a>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="stat-card">
                    <p class="label">Pending requests</p>
                    <div class="value"><?= $stats['pending'] ?></div>
                    <small class="text-muted">Respond within 24 hours</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <p class="label">Upcoming events</p>
                    <div class="value"><?= $stats['approved'] ?></div>
                    <small class="text-muted">Approved and scheduled</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <p class="label">Completed events</p>
                    <div class="value"><?= $stats['completed'] ?></div>
                    <small class="text-muted">Keep reviews coming</small>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-semibold mb-0">Recent bookings</h5>
                            <a class="btn btn-sm btn-outline-primary" href="<?= APP_URL ?>/caterer/bookings.php">View all</a>
                        </div>
                        <?php if (empty($recentBookings)): ?>
                            <p class="text-muted mb-0">No bookings yet. Once clients send requests, they&#39;ll appear here.</p>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($recentBookings as $booking): ?>
                                    <div class="list-group-item d-flex flex-column flex-lg-row align-items-lg-center justify-content-lg-between">
                                        <div class="mb-2 mb-lg-0">
                                            <h6 class="fw-semibold mb-1"><?= htmlspecialchars($booking['customer_name']) ?></h6>
                                            <small class="text-muted">
                                                <i class="bi bi-calendar-event me-1"></i>
                                                <?= htmlspecialchars($booking['event_type']) ?> ·
                                                <?= htmlspecialchars(date('M j, Y', strtotime($booking['event_date']))) ?>
                                                <?php if (!empty($booking['event_time'])): ?>
                                                    · <?= htmlspecialchars(date('g:i A', strtotime($booking['event_time']))) ?>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                        <span class="badge-status <?= htmlspecialchars($booking['status']) ?>">
                                            <?php if ($booking['status'] === 'approved'): ?>
                                                <i class="bi bi-check-circle"></i> Approved
                                            <?php elseif ($booking['status'] === 'pending'): ?>
                                                <i class="bi bi-hourglass-split"></i> Pending
                                            <?php elseif ($booking['status'] === 'declined'): ?>
                                                <i class="bi bi-x-circle"></i> Declined
                                            <?php elseif ($booking['status'] === 'completed'): ?>
                                                <i class="bi bi-check2-circle"></i> Completed
                                            <?php else: ?>
                                                <i class="bi bi-info-circle"></i> <?= htmlspecialchars(ucfirst($booking['status'])) ?>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <h5 class="fw-semibold mb-3">Availability</h5>
                        <p class="small text-muted">Toggle when you are fully booked or receiving new inquiries.</p>
                        <?php if (!empty($availabilityError ?? null)): ?>
                            <div class="alert alert-danger small mb-3"><?= htmlspecialchars($availabilityError) ?></div>
                        <?php endif; ?>
                        <form method="post" class="d-flex align-items-center gap-3" id="availabilityForm">
                            <input type="hidden" name="toggle_availability" value="1">
                            <input type="hidden" name="accept" value="html">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="availabilityToggle" name="availability" <?= $isAvailable ? 'checked' : '' ?> data-availability-toggle>
                                <label class="form-check-label" for="availabilityToggle">
                                    <span data-availability-label><?= $isAvailable ? 'Currently accepting new events' : 'Not accepting new events' ?></span>
                                </label>
                            </div>
                            <button class="btn btn-sm btn-outline-primary" type="submit">Update</button>
                        </form>
                        <small class="d-block text-muted mt-2">Tip: switch off when you’re fully booked for the near future.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script>
    const availabilityForm = document.getElementById('availabilityForm');
    if (availabilityForm) {
        const toggle = availabilityForm.querySelector('[data-availability-toggle]');
        const label = availabilityForm.querySelector('[data-availability-label]');

        availabilityForm.addEventListener('submit', async (event) => {
            event.preventDefault();

            const formData = new FormData(availabilityForm);
            formData.set('accept', 'json');

            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });

                const data = await response.json();

                if (data.status === 'ok') {
                    const isAvailable = data.availability_status === 'available';
                    toggle.checked = isAvailable;
                    label.textContent = isAvailable ? 'Currently accepting new events' : 'Not accepting new events';
                } else {
                    throw new Error(data.message || 'Unable to update availability right now.');
                }
            } catch (error) {
                alert(error.message);
            }
        });
    }
</script>
