<?php
require_once __DIR__ . '/../init.php';
require_auth(['customer']);
require_once __DIR__ . '/../lib/customer_service.php';

$catererId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($catererId <= 0) {
    redirect('/customer/browse.php');
}

$currentUser = current_user();
$successMessage = null;
$errorMessage = null;
$eventDateInput = '';
$eventTimeInput = '';
$guestCountInput = '';
$selectedPackageIdInput = '';
$selectedEventTypeIdInput = '';
$customRequestInput = '';

$detail = get_caterer_profile_detail($catererId);

if (!$detail) {
    $pageTitle = 'Caterer not found';
    $pageStyles = [APP_URL . '/public/assets/css/dashboard.css'];
    include __DIR__ . '/../includes/header.php';
    ?>
    <div class="dashboard-wrapper">
        <?php include __DIR__ . '/../includes/customer_sidebar.php'; ?>
        <div class="dashboard-content">
            <div class="alert alert-warning mt-4">
                <h5 class="fw-semibold mb-1">Caterer not found</h5>
                <p class="mb-0">The caterer you are looking for may no longer be available. Please browse other providers.</p>
            </div>
        </div>
    </div>
    <?php
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$profile = $detail['profile'];
$packages = $detail['packages'];
$reviews = $detail['reviews'];
$gallery = $detail['gallery'];

$eventTypes = get_event_types(); // For the dropdown in the booking form

$availabilityStatus = $profile['availability_status'];
$availabilityStatusNormalized = $availabilityStatus !== null ? strtolower(trim((string) $availabilityStatus)) : null;
$unavailableStates = ['unavailable', 'closed', 'snooze'];
$isAcceptingBookings = !in_array($availabilityStatusNormalized, $unavailableStates, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $eventDateInput = trim($_POST['event_date'] ?? '');
    $eventTimeInput = trim($_POST['event_time'] ?? '');
    $guestCountInput = trim($_POST['guest_count'] ?? '');
    $selectedPackageIdInput = $_POST['package_id'] ?? '';
    $selectedEventTypeIdInput = $_POST['event_type_id'] ?? '';
    $customRequestInput = trim($_POST['custom_request'] ?? '');

    $guestCountValue = (int) $guestCountInput;
    $packageIdValue = $selectedPackageIdInput !== '' ? (int) $selectedPackageIdInput : null;
    $eventTypeIdValue = $selectedEventTypeIdInput !== '' ? (int) $selectedEventTypeIdInput : null;
    $eventTimeValue = $eventTimeInput !== '' ? $eventTimeInput : null;
    $customRequestValue = $customRequestInput !== '' ? $customRequestInput : null;

    try {
        if ($guestCountValue <= 0) {
            throw new InvalidArgumentException('Please enter an estimated guest count.');
        }

        if ($eventDateInput === '') {
            throw new InvalidArgumentException('Please choose an event date.');
        }

        if (!empty($packages) && $packageIdValue === null) {
            throw new InvalidArgumentException('Please choose a package before sending a booking request.');
        }

        $bookingId = create_customer_booking(
            (int) $currentUser['id'],
            (int) $profile['id'],
            $guestCountValue,
            $eventDateInput,
            $eventTimeValue,
            $packageIdValue,
            $eventTypeIdValue,
            $customRequestValue
        );

        $successMessage = sprintf('Booking request #%d submitted! The caterer will get back to you soon.', $bookingId);
        $eventDateInput = '';
        $eventTimeInput = '';
        $guestCountInput = '';
        $selectedPackageIdInput = '';
        $selectedEventTypeIdInput = '';
        $customRequestInput = '';

        $detail = get_caterer_profile_detail($catererId);

        if ($detail) {
            $profile = $detail['profile'];
            $packages = $detail['packages'];
            $reviews = $detail['reviews'];
            $gallery = $detail['gallery'];

            $availabilityStatus = $profile['availability_status'];
            $availabilityStatusNormalized = $availabilityStatus !== null ? strtolower(trim((string) $availabilityStatus)) : null;
            $isAcceptingBookings = !in_array($availabilityStatusNormalized, $unavailableStates, true);
        }
    } catch (Throwable $e) {
        $errorMessage = $e instanceof InvalidArgumentException || $e instanceof RuntimeException
            ? $e->getMessage()
            : 'Unable to submit booking request right now. Please try again.';
    }
}

$pageTitle = $profile['business_name'] . ' · Caterer Profile';
$pageStyles = [APP_URL . '/public/assets/css/dashboard.css'];
$minEventDate = (new DateTime('today'))->format('Y-m-d');

include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php include __DIR__ . '/../includes/customer_sidebar.php'; ?>

    <div class="dashboard-content">
        <div class="page-header">
            <div>
                <h1 class="fw-bold mb-1"><?= htmlspecialchars($profile['business_name']) ?></h1>
                <p class="text-muted mb-0">
                    <i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($profile['location'] ?? 'Location not specified') ?>
                    <?php if (!empty($profile['service_area'])): ?> · Serves <?= htmlspecialchars($profile['service_area']) ?><?php endif; ?>
                </p>
            </div>
            <a href="<?= APP_URL ?>/customer/browse.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-2"></i>Back to browse</a>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <h5 class="fw-semibold mb-3">Gallery</h5>
                        <?php if (!empty($gallery)): ?>
                            <div class="row g-2">
                                <?php foreach ($gallery as $photo): ?>
                                    <?php $photoUrl = APP_URL . '/' . ltrim($photo['file_path'], '/'); ?>
                                    <div class="col-6 col-md-4">
                                        <div class="ratio ratio-4x3 rounded overflow-hidden border">
                                            <img src="<?= htmlspecialchars($photoUrl) ?>" alt="Gallery photo" class="img-fluid object-fit-cover w-100 h-100">
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted mb-0">This caterer hasn't uploaded any photos yet.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <h5 class="fw-semibold mb-3">About</h5>
                        <p class="text-muted mb-0">
                            <?= $profile['description'] ? nl2br(htmlspecialchars($profile['description'])) : '<span class="text-muted">This caterer hasn\'t added a description yet.</span>'; ?>
                        </p>
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <h5 class="fw-semibold mb-3">Packages</h5>
                        <?php if (empty($packages)): ?>
                            <p class="text-muted mb-0">No active packages listed. Contact the caterer for custom quotes.</p>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($packages as $package): ?>
                                    <?php
                                        $packageId = (int) $package['id'];
                                        $collapseId = 'packageDetails' . $packageId;
                                        $inclusionsList = array_filter(array_map('trim', explode("\n", (string) $package['inclusions'] ?? '')));
                                    ?>
                                    <div class="list-group-item border-0 px-0">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="fw-semibold mb-1 mb-0 d-flex align-items-center gap-2">
                                                    <?= htmlspecialchars($package['name']) ?>
                                                    <button class="btn btn-sm btn-link p-0 align-baseline" type="button" data-bs-toggle="collapse" data-bs-target="#<?= htmlspecialchars($collapseId) ?>" aria-expanded="false" aria-controls="<?= htmlspecialchars($collapseId) ?>">
                                                        View details
                                                    </button>
                                                </h6>
                                                <p class="text-muted small mb-1">
                                                    <?= $package['description'] ? nl2br(htmlspecialchars($package['description'])) : 'No description provided.'; ?>
                                                </p>
                                                <?php
                                                    $typeLabel = 'Full package';
                                                    if (!empty($package['package_type'])) {
                                                        if ($package['package_type'] === 'food') {
                                                            $typeLabel = 'Food only';
                                                        } elseif ($package['package_type'] === 'services') {
                                                            $typeLabel = 'Services only';
                                                        }
                                                    }
                                                ?>
                                                <span class="badge bg-secondary-subtle text-secondary small"><?= htmlspecialchars($typeLabel) ?></span>
                                            </div>
                                            <span class="fw-semibold text-primary ms-3"><?= format_currency((float) $package['price']) ?></span>
                                        </div>
                                        <div class="collapse mt-3" id="<?= htmlspecialchars($collapseId) ?>">
                                            <?php if (!empty($inclusionsList)): ?>
                                                <p class="fw-semibold small mb-2">Inclusions:</p>
                                                <ul class="small text-muted ps-3 mb-2">
                                                    <?php foreach ($inclusionsList as $item): ?>
                                                        <li><?= htmlspecialchars($item) ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php endif; ?>
                                            <div class="d-flex align-items-center gap-2">
                                                <a href="<?= APP_URL ?>/customer/book_package.php?package_id=<?= $packageId ?>" class="btn btn-sm btn-outline-primary">
                                                    View &amp; book this package
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-semibold mb-0">Recent reviews</h5>
                            <a href="<?= APP_URL ?>/customer/reviews.php" class="btn btn-sm btn-outline-primary">Manage my reviews</a>
                        </div>
                        <?php if (empty($reviews)): ?>
                            <p class="text-muted mb-0">No reviews yet. Be the first to book and share your experience.</p>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($reviews as $review): ?>
                                    <div class="list-group-item border-0 px-0">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="fw-semibold mb-1">Rating: <?= (int) $review['rating'] ?>/5</h6>
                                                <small class="text-muted d-block mb-2">
                                                    <?= htmlspecialchars($review['customer_name']) ?> · <?= htmlspecialchars(date('M j, Y', strtotime($review['created_at']))) ?>
                                                </small>
                                                <?php if (!empty($review['comment'])): ?>
                                                    <p class="text-muted small mb-0"><?= nl2br(htmlspecialchars($review['comment'])) ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <h5 class="fw-semibold mb-3">At a glance</h5>
                        <ul class="list-unstyled mb-0 small">
                            <li class="mb-2">
                                <strong>Status:</strong> 
                                <?php if ($isAcceptingBookings): ?>
                                    <span class="text-success">Accepting bookings</span>
                                <?php else: ?>
                                    <span class="text-muted">Not accepting bookings</span>
                                <?php endif; ?>
                            </li>
                            <li class="mb-2"><strong>Average rating:</strong> <?= number_format((float) $profile['average_rating'], 1) ?> (<?= (int) $profile['review_count'] ?> reviews)</li>
                            <li class="mb-2"><strong>Average price:</strong> <?= $profile['average_price'] !== null ? format_currency((float) $profile['average_price']) : 'Contact for pricing'; ?></li>
                            <li class="mb-2"><strong>Cuisine specialties:</strong>
                                <?php
                                    $cuisines = array_filter(array_map('trim', explode(',', (string) $profile['cuisine_specialties'])));
                                    echo empty($cuisines) ? 'Not listed' : htmlspecialchars(implode(', ', $cuisines));
                                ?>
                            </li>
                            <li class="mb-2"><strong>Event types:</strong>
                                <?php
                                    // Use a different variable name to avoid conflict with $eventTypes used in the form
                                    $catererEventTypes = array_filter(array_map('trim', explode(',', (string) $profile['event_types'])));
                                    echo empty($catererEventTypes) ? 'Not listed' : htmlspecialchars(implode(', ', $catererEventTypes));
                                ?>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <h5 class="fw-semibold mb-3">Contact</h5>
                        <p class="mb-2"><strong>Owner:</strong> <?= htmlspecialchars($profile['owner_name']) ?></p>
                        <p class="mb-0"><strong>Email:</strong> <a href="mailto:<?= htmlspecialchars($profile['owner_email']) ?>"><?= htmlspecialchars($profile['owner_email']) ?></a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>