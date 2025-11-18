<?php
require_once __DIR__ . '/../init.php';
require_auth(['customer']);
require_once __DIR__ . '/../lib/customer_service.php';

$pageTitle = 'Browse Caterers';
$pageStyles = [APP_URL . '/public/assets/css/dashboard.css'];

$locationInput = trim($_GET['location'] ?? '');
$cuisineInput = trim($_GET['cuisine'] ?? '');
$eventTypeInput = trim($_GET['event_type'] ?? '');
$pricePerHeadInput = trim($_GET['price_per_head'] ?? '');

$pricePerHeadValue = null;
if ($pricePerHeadInput !== '') {
    $normalizedPrice = preg_replace('/[^0-9.]/', '', $pricePerHeadInput);
    if ($normalizedPrice !== '' && is_numeric($normalizedPrice)) {
        $pricePerHeadValue = (float) $normalizedPrice;
    }
}

$filters = array_filter(
    [
        'location' => $locationInput !== '' ? $locationInput : null,
        'cuisine' => $cuisineInput !== '' ? $cuisineInput : null,
        'event_type' => $eventTypeInput !== '' ? $eventTypeInput : null,
        'price_per_head' => $pricePerHeadValue,
    ],
    static fn($value) => $value !== null && $value !== ''
);

$directory = get_caterer_directory($filters);
$eventTypes = get_event_types();

include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php include __DIR__ . '/../includes/customer_sidebar.php'; ?>

    <div class="dashboard-content">
        <div class="page-header">
            <div>
                <h1 class="fw-bold">Discover caterers</h1>
                <p class="text-muted">Filter by location, cuisine, price per head, and event type.</p>
            </div>
            <button class="btn btn-outline-primary" type="button"><i class="bi bi-sliders me-2"></i>Save filters</button>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <form class="row g-3" method="get">
                    <div class="col-md-3">
                        <label class="form-label">Location</label>
                        <input type="text" name="location" class="form-control" placeholder="e.g. Quezon City" value="<?= htmlspecialchars($locationInput) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Cuisine</label>
                        <input type="text" name="cuisine" class="form-control" placeholder="e.g. Filipino" value="<?= htmlspecialchars($cuisineInput) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Event Type</label>
                        <select class="form-select" name="event_type">
                            <option value="" <?= $eventTypeInput === '' ? 'selected' : '' ?>>Any</option>
                            <?php foreach ($eventTypes as $type): ?>
                                <option value="<?= htmlspecialchars($type['name']) ?>" <?= $eventTypeInput === $type['name'] ? 'selected' : '' ?>><?= htmlspecialchars($type['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Price per head (₱)</label>
                        <input type="text" name="price_per_head" class="form-control" placeholder="e.g. 650" value="<?= htmlspecialchars($pricePerHeadInput) ?>">
                    </div>
                    <div class="col-12 text-end">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-search me-2"></i>Search</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="row g-4">
            <?php if (empty($directory)): ?>
                <div class="col-12">
                    <div class="alert alert-light border text-center mb-0">
                        <h5 class="fw-semibold mb-2">No caterers available yet</h5>
                        <p class="text-muted mb-0">Check back soon—approved caterers will appear here once admins verify their profiles.</p>
                    </div>
                </div>
            <?php endif; ?>
            <?php foreach ($directory as $caterer): ?>
                <div class="col-12 col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h4 class="fw-bold mb-1"><?= htmlspecialchars($caterer['business_name']) ?></h4>
                                    <p class="text-muted mb-2"><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($caterer['location'] ?? 'Location not specified') ?></p>
                                </div>
                                <div class="text-end">
                                    <?php if (($caterer['average_rating'] ?? 0) > 0): ?>
                                        <span class="badge bg-light text-dark"><i class="bi bi-star-fill text-warning me-1"></i><?= number_format($caterer['average_rating'], 1) ?> (<?= $caterer['review_count'] ?>)</span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-dark"><i class="bi bi-star text-warning me-1"></i>New</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <p class="mb-2 fw-semibold text-primary">
                                <?php if ($caterer['starting_price'] !== null): ?>
                                    Starting at <?= format_currency($caterer['starting_price']) ?>
                                <?php elseif ($caterer['average_price'] !== null): ?>
                                    Avg. <?= format_currency($caterer['average_price']) ?>
                                <?php else: ?>
                                    Pricing on request
                                <?php endif; ?>
                            </p>
                            <div class="mb-3">
                                <span class="text-muted small d-block mb-1">Cuisine specialties</span>
                                <?php
                                    $cuisines = array_filter(array_map('trim', explode(',', (string) $caterer['cuisine_specialties'])));
                                    if (empty($cuisines)) {
                                        echo '<span class="text-muted small">No specialties listed yet.</span>';
                                    }
                                ?>
                                <?php foreach ($cuisines as $cuisine): ?>
                                    <span class="badge bg-primary bg-opacity-10 text-primary me-1 mb-1"><?= htmlspecialchars($cuisine) ?></span>
                                <?php endforeach; ?>
                            </div>
                            <div class="mb-3">
                                <span class="text-muted small d-block mb-1">Event types</span>
                                <?php
                                    $events = array_filter(array_map('trim', explode(',', (string) $caterer['event_types'])));
                                    if (empty($events)) {
                                        echo '<span class="text-muted small">No events specified yet.</span>';
                                    }
                                ?>
                                <?php foreach ($events as $event): ?>
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary me-1 mb-1"><?= htmlspecialchars($event) ?></span>
                                <?php endforeach; ?>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <?php if ($caterer['availability_status'] === 'available'): ?>
                                    <small class="text-success"><i class="bi bi-check-circle me-1"></i>Accepting new bookings</small>
                                <?php else: ?>
                                    <small class="text-muted"><i class="bi bi-slash-circle me-1"></i>Temporarily unavailable</small>
                                <?php endif; ?>
                                <a href="<?= APP_URL ?>/customer/caterer.php?id=<?= (int) $caterer['id'] ?>" class="btn btn-outline-primary" aria-label="View profile for <?= htmlspecialchars($caterer['business_name']) ?>">View profile</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
