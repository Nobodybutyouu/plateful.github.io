<?php
require_once __DIR__ . '/../init.php';
require_auth(['customer']);
require_once __DIR__ . '/../lib/customer_service.php';

$pageTitle = 'Customer Dashboard';
$pageStyles = [APP_URL . '/public/assets/css/dashboard.css'];

$user = current_user();
$dashboardData = get_customer_dashboard_data($user['id']);
$counts = $dashboardData['counts'];
$upcomingBookings = $dashboardData['upcoming_bookings'];

include __DIR__ . '/../includes/header.php';
?>
<div class="dashboard-wrapper">
    <?php include __DIR__ . '/../includes/customer_sidebar.php'; ?>

    <div class="dashboard-content">
        <div class="page-header">
            <div>
                <h1 class="fw-bold">Welcome back, <?= htmlspecialchars($user['name']) ?>!</h1>
                <p class="text-muted">Here&#39;s what&#39;s happening with your upcoming events.</p>
            </div>
            <a href="<?= APP_URL ?>/customer/browse.php" class="btn btn-primary"><i class="bi bi-plus-lg me-2"></i>Start a Booking</a>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="stat-card">
                    <p class="label">Upcoming Events</p>
                    <div class="value"><?= $counts['upcoming'] ?? 0 ?></div>
                    <small class="text-muted">Confirm details and keep your timeline updated</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <p class="label">Past Bookings</p>
                    <div class="value"><?= $counts['completed'] ?? 0 ?></div>
                    <small class="text-muted">Leave reviews to help the community</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <p class="label">Unread Notifications</p>
                    <div class="value"><?= $counts['notifications'] ?? 0 ?></div>
                    <small class="text-muted">Stay updated with booking changes</small>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <h5 class="fw-semibold mb-3">Upcoming bookings</h5>
                <?php if (empty($upcomingBookings)): ?>
                    <p class="text-muted mb-0">No upcoming bookings yet. Start exploring caterers to plan your next event.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-modern mb-0">
                            <thead>
                                <tr>
                                    <th>Caterer</th>
                                    <th>Event</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($upcomingBookings as $booking): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($booking['business_name']) ?></td>
                                        <td><?= htmlspecialchars($booking['event_type']) ?></td>
                                        <td><?= htmlspecialchars(date('M j, Y', strtotime($booking['event_date']))) ?></td>
                                        <td>
                                            <span class="badge-status <?= htmlspecialchars($booking['status']) ?>">
                                                <?php if ($booking['status'] === 'approved'): ?>
                                                    <i class="bi bi-check-circle"></i> Approved
                                                <?php elseif ($booking['status'] === 'pending'): ?>
                                                    <i class="bi bi-hourglass-split"></i> Pending
                                                <?php else: ?>
                                                    <i class="bi bi-info-circle"></i> <?= htmlspecialchars(ucfirst($booking['status'])) ?>
                                                <?php endif; ?>
                                            </span>
                                        </td>
                                        <td class="text-end"><a href="<?= APP_URL ?>/customer/bookings.php" class="btn btn-outline-primary btn-sm">View</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h5 class="fw-semibold mb-3">Quick actions</h5>
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="p-3 border rounded-3 h-100">
                            <h6 class="fw-semibold">Track booking</h6>
                            <p class="text-muted small mb-3">Check request status and contact your caterer.</p>
                            <a href="<?= APP_URL ?>/customer/bookings.php" class="btn btn-sm btn-outline-primary">View bookings</a>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 border rounded-3 h-100">
                            <h6 class="fw-semibold">Find new caterers</h6>
                            <p class="text-muted small mb-3">Filter by cuisine, price, or event type.</p>
                            <a href="<?= APP_URL ?>/customer/browse.php" class="btn btn-sm btn-outline-primary">Browse now</a>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 border rounded-3 h-100">
                            <h6 class="fw-semibold">Leave feedback</h6>
                            <p class="text-muted small mb-3">Share insights from your latest booking.</p>
                            <a href="<?= APP_URL ?>/customer/reviews.php" class="btn btn-sm btn-outline-primary">Write a review</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
