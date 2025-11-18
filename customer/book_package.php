<?php
require_once __DIR__ . '/../init.php';
require_auth(['customer']);
require_once __DIR__ . '/../lib/customer_service.php';
require_once __DIR__ . '/../lib/caterer_service.php';

$user = current_user();
$packageId = isset($_GET['package_id']) ? (int) $_GET['package_id'] : 0;

if ($packageId <= 0) {
    redirect('/customer/browse.php');
}

$errors = [];

$db = db();
$stmt = $db->prepare(
    'SELECT p.id, p.caterer_profile_id, p.name, p.price, p.deposit_percentage, p.package_type, p.description, p.inclusions,
            cp.business_name
     FROM packages p
     INNER JOIN caterer_profiles cp ON cp.id = p.caterer_profile_id
     WHERE p.id = :id AND p.is_active = 1
     LIMIT 1'
);
$stmt->execute(['id' => $packageId]);
$package = $stmt->fetch();

if (!$package) {
    redirect('/customer/browse.php');
}

$profileId = (int) $package['caterer_profile_id'];
$businessName = $package['business_name'];
$pricePerHead = (float) $package['price'];
$depositPercentage = $package['deposit_percentage'] !== null ? (float) $package['deposit_percentage'] : 0.0;
$packageType = isset($package['package_type']) && $package['package_type'] !== '' ? $package['package_type'] : 'full';

$packageItems = get_package_items($packageId);
$maincourseItems = [];
$serviceItems = [];
$addonItems = [];
$addonItemsById = [];

$menuItems = get_caterer_menu_items($profileId);
$menuByCategory = [];

foreach ($packageItems as $item) {
    if ($item['item_type'] === 'maincourse') {
        $maincourseItems[] = $item;
    } elseif ($item['item_type'] === 'service') {
        $serviceItems[] = $item;
    } elseif ($item['item_type'] === 'addon') {
        $addonItems[] = $item;
        $addonItemsById[$item['id']] = $item;
    }
}

foreach ($menuItems as $menuItem) {
    $category = $menuItem['category'] ?? '';
    $name = $menuItem['name'] ?? '';

    if ($category === '' || $name === '') {
        continue;
    }

    if (!isset($menuByCategory[$category])) {
        $menuByCategory[$category] = [];
    }

    $menuByCategory[$category][] = $menuItem;
}

$guestCountInput = '';
$eventDateInput = '';
$eventTimeInput = '';
$eventTypeIdInput = '';
$eventLocationInput = '';
$specialInstructionsInput = '';

$dishInputs = [];
$courseNameInputs = [];
$selectedServiceInputs = [];
$selectedAddonIds = [];

$eventTypes = get_event_types();
$minEventDate = (new DateTime('today'))->format('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $guestCountInput = trim($_POST['guest_count'] ?? '');
    $eventDateInput = trim($_POST['event_date'] ?? '');
    $eventTimeInput = trim($_POST['event_time'] ?? '');
    $eventTypeIdInput = $_POST['event_type_id'] ?? '';
    $eventLocationInput = trim($_POST['event_location'] ?? '');
    $specialInstructionsInput = trim($_POST['special_instructions'] ?? '');

    $dishInputs = isset($_POST['dishes']) && is_array($_POST['dishes']) ? $_POST['dishes'] : [];
    $courseNameInputs = isset($_POST['course_names']) && is_array($_POST['course_names']) ? $_POST['course_names'] : [];
    $selectedServiceInputs = isset($_POST['selected_services']) && is_array($_POST['selected_services']) ? $_POST['selected_services'] : [];
    $selectedAddonIds = isset($_POST['addons']) && is_array($_POST['addons']) ? $_POST['addons'] : [];

    $guestCountValue = (int) $guestCountInput;
    $eventTypeIdValue = $eventTypeIdInput !== '' ? (int) $eventTypeIdInput : null;
    $eventTimeValue = $eventTimeInput !== '' ? $eventTimeInput : null;

    if ($guestCountValue <= 0) {
        $errors[] = 'Please enter the number of guests.';
    }

    if ($eventDateInput === '') {
        $errors[] = 'Please choose an event date.';
    }

    $customRequestParts = [];
    if ($eventLocationInput !== '') {
        $customRequestParts[] = 'Location: ' . $eventLocationInput;
    }
    if ($specialInstructionsInput !== '') {
        $customRequestParts[] = $specialInstructionsInput;
    }

    // Include selected dishes, services, and add-ons in the custom request for caterer visibility
    if (($packageType === 'food' || $packageType === 'full') && !empty($maincourseItems) && !empty($dishInputs)) {
        $lines = [];
        foreach ($dishInputs as $idx => $dishNameRaw) {
            $dishName = trim((string) $dishNameRaw);
            $courseName = isset($courseNameInputs[$idx]) ? trim((string) $courseNameInputs[$idx]) : '';

            if ($dishName === '' && $courseName === '') {
                continue;
            }

            $label = $courseName !== '' ? $courseName : 'Dish ' . ($idx + 1);
            $lines[] = sprintf('%s: %s', $label, $dishName !== '' ? $dishName : '(not specified)');
        }

        if (!empty($lines)) {
            $customRequestParts[] = "Main course dishes:\n- " . implode("\n- ", $lines);
        }
    }

    if (($packageType === 'services' || $packageType === 'full') && !empty($selectedServiceInputs)) {
        $lines = [];
        foreach ($selectedServiceInputs as $serviceNameRaw) {
            $serviceName = trim((string) $serviceNameRaw);
            if ($serviceName === '') {
                continue;
            }
            $lines[] = $serviceName;
        }

        if (!empty($lines)) {
            $customRequestParts[] = "Selected services:\n- " . implode("\n- ", $lines);
        }
    }

    if (!empty($selectedAddonIds)) {
        $lines = [];
        foreach ($selectedAddonIds as $addonIdRaw) {
            $addonId = (int) $addonIdRaw;
            if (isset($addonItemsById[$addonId])) {
                $addon = $addonItemsById[$addonId];
                $name = $addon['name'];
                $price = $addon['price'] ?? null;

                if ($price !== null && $price > 0) {
                    $lines[] = sprintf('%s (₱%s)', $name, number_format($price, 2));
                } else {
                    $lines[] = sprintf('%s (no extra charge)', $name);
                }
            }
        }

        if (!empty($lines)) {
            $customRequestParts[] = "Selected add-ons:\n- " . implode("\n- ", $lines);
        }
    }
    $customRequestValue = !empty($customRequestParts) ? implode("\n\n", $customRequestParts) : null;

    if (empty($errors)) {
        try {
            $bookingId = create_customer_booking(
                (int) $user['id'],
                $profileId,
                $guestCountValue,
                $eventDateInput,
                $eventTimeValue,
                $packageId,
                $eventTypeIdValue,
                $customRequestValue
            );

            redirect('/customer/bookings.php?new_booking=' . $bookingId);
        } catch (Throwable $e) {
            $errors[] = $e instanceof InvalidArgumentException || $e instanceof RuntimeException
                ? $e->getMessage()
                : 'Unable to submit booking request right now. Please try again.';
        }
    }
}

$pageTitle = 'Book package · ' . $businessName;
$pageStyles = [APP_URL . '/public/assets/css/dashboard.css'];

include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php include __DIR__ . '/../includes/customer_sidebar.php'; ?>
    <div class="dashboard-content">
        <div class="page-header">
            <div>
                <h1 class="fw-bold mb-1"><?= htmlspecialchars($package['name']) ?></h1>
                <p class="text-muted mb-0">Book this package with <?= htmlspecialchars($businessName) ?>.</p>
            </div>
            <a href="<?= APP_URL ?>/customer/caterer.php?id=<?= $profileId ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-2"></i>Back to caterer
            </a>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger" role="alert">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <div class="mb-3">
                            <h5 class="fw-semibold mb-1"><?= htmlspecialchars($package['name']) ?></h5>
                            <p class="mb-1 text-muted"><?= htmlspecialchars($businessName) ?></p>
                            <div class="d-flex flex-wrap gap-3 align-items-center mt-2">
                                <div>
                                    <div class="text-muted small">Price per head</div>
                                    <div class="fw-semibold fs-5 text-primary" id="pricePerHeadDisplay"><?= format_currency($pricePerHead) ?></div>
                                </div>
                                <div>
                                    <div class="text-muted small">Deposit required</div>
                                    <div class="fw-semibold">
                                        <?php if ($depositPercentage > 0): ?>
                                            <?php $depositLabel = rtrim(rtrim(number_format($depositPercentage, 2), '0'), '.'); ?>
                                            <?= $depositLabel ?>%
                                        <?php else: ?>
                                            No deposit
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div>
                                    <div class="text-muted small">Package type</div>
                                    <?php
                                        $typeLabel = 'Full package';
                                        if ($packageType === 'food') {
                                            $typeLabel = 'Food only';
                                        } elseif ($packageType === 'services') {
                                            $typeLabel = 'Services only';
                                        }
                                    ?>
                                    <div class="fw-semibold small">
                                        <span class="badge bg-secondary-subtle text-secondary"><?= htmlspecialchars($typeLabel) ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php if (!empty($package['description'])): ?>
                            <p class="text-muted mb-3"><?= nl2br(htmlspecialchars($package['description'])) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($package['inclusions'])): ?>
                            <?php $inclusionsList = array_filter(array_map('trim', explode("\n", (string) $package['inclusions']))); ?>
                            <?php if (!empty($inclusionsList)): ?>
                                <p class="fw-semibold small mb-1">Inclusions:</p>
                                <ul class="small text-muted ps-3 mb-0">
                                    <?php foreach ($inclusionsList as $item): ?>
                                        <li><?= htmlspecialchars($item) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="text-muted small mb-0"><?= nl2br(htmlspecialchars($package['inclusions'])) ?></p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($menuByCategory)): ?>
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body p-4">
                            <h5 class="fw-semibold mb-3">Menu</h5>
                            <p class="text-muted small mb-3">Here are some of the dishes this caterer offers, grouped by main course.</p>
                            <div class="row g-3">
                                <?php foreach ($menuByCategory as $category => $items): ?>
                                    <div class="col-md-6">
                                        <div class="border rounded-3 p-3 h-100">
                                            <h6 class="fw-semibold mb-2 mb-md-3"><?= htmlspecialchars($category) ?></h6>
                                            <ul class="small text-muted ps-3 mb-0">
                                                <?php foreach ($items as $menuItem): ?>
                                                    <li><?= htmlspecialchars($menuItem['name']) ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <h5 class="fw-semibold mb-3">Event details</h5>
                        <form method="post" class="vstack gap-4" id="bookPackageForm">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Number of guests <span class="text-danger">*</span></label>
                                    <input type="number" name="guest_count" id="guestCountInput" class="form-control" min="1" value="<?= htmlspecialchars($guestCountInput) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Event date <span class="text-danger">*</span></label>
                                    <input type="date" name="event_date" class="form-control" value="<?= htmlspecialchars($eventDateInput) ?>" min="<?= htmlspecialchars($minEventDate) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <?php $eventTimeLabel = $packageType === 'food' ? 'Delivery time' : 'Event time'; ?>
                                    <label class="form-label"><?= htmlspecialchars($eventTimeLabel) ?></label>
                                    <input type="time" name="event_time" class="form-control" value="<?= htmlspecialchars($eventTimeInput) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Event type</label>
                                    <select name="event_type_id" class="form-select">
                                        <option value="">Select event type (optional)</option>
                                        <?php foreach ($eventTypes as $eventType): ?>
                                            <option value="<?= (int) $eventType['id'] ?>" <?= $eventTypeIdInput !== '' && (int) $eventTypeIdInput === (int) $eventType['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($eventType['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Event location</label>
                                    <input type="text" name="event_location" class="form-control" value="<?= htmlspecialchars($eventLocationInput) ?>" placeholder="Enter full address">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Special instructions</label>
                                    <textarea name="special_instructions" class="form-control" rows="3" placeholder="Any special requests or details..."><?= htmlspecialchars($specialInstructionsInput) ?></textarea>
                                </div>
                            </div>

                            <?php if (($packageType === 'food' || $packageType === 'full') && !empty($maincourseItems)): ?>
                                <div class="mt-3">
                                    <h6 class="fw-semibold mb-2"><i class="bi bi-egg-fried me-2"></i>Specify dishes</h6>
                                    <p class="text-muted small mb-3">Please specify the dish for each main course.</p>
                                    <?php foreach ($maincourseItems as $idx => $course): ?>
                                        <?php
                                            $courseName = $course['name'];
                                            $existingDish = $dishInputs[$idx] ?? '';
                                        ?>
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold mb-1"><?= htmlspecialchars($courseName) ?> dish</label>
                                            <input type="text" name="dishes[]" class="form-control" value="<?= htmlspecialchars($existingDish) ?>" placeholder="E.g., Beef Caldereta, Pork Adobo" required>
                                            <input type="hidden" name="course_names[]" value="<?= htmlspecialchars($courseName) ?>">
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <?php if (($packageType === 'services' || $packageType === 'full') && !empty($serviceItems)): ?>
                                <div class="mt-3">
                                    <h6 class="fw-semibold mb-2"><i class="bi bi-ui-checks me-2"></i>Select services</h6>
                                    <p class="text-muted small mb-2">Choose the services you need.</p>
                                    <?php foreach ($serviceItems as $service): ?>
                                        <?php $serviceName = $service['name']; ?>
                                        <div class="form-check mb-2">
                                            <input
                                                class="form-check-input"
                                                type="checkbox"
                                                name="selected_services[]"
                                                value="<?= htmlspecialchars($serviceName) ?>"
                                                id="service<?= (int) $service['id'] ?>"
                                                <?= empty($selectedServiceInputs) || in_array($serviceName, $selectedServiceInputs, true) ? 'checked' : '' ?>
                                            >
                                            <label class="form-check-label" for="service<?= (int) $service['id'] ?>">
                                                <?= htmlspecialchars($serviceName) ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($addonItems)): ?>
                                <div class="mt-3">
                                    <h6 class="fw-semibold mb-2"><i class="bi bi-plus-circle me-2"></i>Add-ons (optional)</h6>
                                    <?php foreach ($addonItems as $addon): ?>
                                        <?php
                                            $addonId = (int) $addon['id'];
                                            $addonName = $addon['name'];
                                            $addonPrice = $addon['price'] !== null ? (float) $addon['price'] : 0.0;
                                            $checked = in_array((string) $addonId, $selectedAddonIds, true) || in_array($addonId, $selectedAddonIds, true);
                                        ?>
                                        <div class="border rounded-3 p-2 mb-2 d-flex justify-content-between align-items-center">
                                            <div class="form-check">
                                                <input
                                                    class="form-check-input addon-checkbox"
                                                    type="checkbox"
                                                    name="addons[]"
                                                    value="<?= $addonId ?>"
                                                    id="addon<?= $addonId ?>"
                                                    data-price="<?= htmlspecialchars((string) $addonPrice) ?>"
                                                    data-name="<?= htmlspecialchars($addonName) ?>"
                                                    <?= $checked ? 'checked' : '' ?>
                                                >
                                                <label class="form-check-label" for="addon<?= $addonId ?>">
                                                    <?= htmlspecialchars($addonName) ?>
                                                </label>
                                            </div>
                                            <?php if ($addonPrice > 0): ?>
                                                <span class="text-success small fw-semibold">+ <?= format_currency($addonPrice) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted small">No extra charge</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <h5 class="fw-semibold mb-3"><i class="bi bi-receipt me-2"></i>Payment summary</h5>
                        <div class="d-flex justify-content-between small mb-2">
                            <span>Price per head</span>
                            <span>₱<span id="summaryPricePerHead"><?= number_format($pricePerHead, 2) ?></span></span>
                        </div>
                        <div class="d-flex justify-content-between small mb-2">
                            <span>Number of guests</span>
                            <span><span id="summaryGuestCount"><?= $guestCountInput !== '' ? (int) $guestCountInput : 0 ?></span></span>
                        </div>
                        <div class="d-flex justify-content-between small mb-2">
                            <span>Base price</span>
                            <span>₱<span id="summaryBasePrice">0.00</span></span>
                        </div>
                        <div id="summaryAddons"></div>
                        <hr>
                        <div class="d-flex justify-content-between fw-semibold mb-2">
                            <span>Total estimate</span>
                            <span>₱<span id="summaryTotal">0.00</span></span>
                        </div>
                        <?php if ($depositPercentage > 0): ?>
                            <div class="d-flex justify-content-between small text-danger mb-2">
                                <span>Deposit (<?php $depositLabel = rtrim(rtrim(number_format($depositPercentage, 2), '0'), '.'); ?><?= $depositLabel ?>%)</span>
                                <span>₱<span id="summaryDeposit">0.00</span></span>
                            </div>
                        <?php endif; ?>
                        <button type="submit" form="bookPackageForm" class="btn btn-primary w-100 mt-3">
                            <i class="bi bi-send me-2"></i>Send booking request
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const guestCountInput = document.getElementById('guestCountInput');
    const summaryGuestCount = document.getElementById('summaryGuestCount');
    const summaryBasePrice = document.getElementById('summaryBasePrice');
    const summaryTotal = document.getElementById('summaryTotal');
    const summaryDeposit = document.getElementById('summaryDeposit');
    const summaryAddons = document.getElementById('summaryAddons');
    const addonCheckboxes = document.querySelectorAll('.addon-checkbox');

    const pricePerHead = <?= json_encode($pricePerHead) ?>;
    const depositPercentage = <?= json_encode($depositPercentage) ?>;

    const formatAmount = (value) => {
        return value.toLocaleString('en-PH', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    };

    const recalc = () => {
        const guestCount = parseInt(guestCountInput.value, 10) || 0;
        const basePrice = pricePerHead * guestCount;

        let addonsTotal = 0;
        let addonsHtml = '';

        addonCheckboxes.forEach((checkbox) => {
            if (!checkbox.checked) {
                return;
            }
            const raw = checkbox.dataset.price || '0';
            const price = parseFloat(raw) || 0;
            const name = checkbox.dataset.name || '';

            if (price > 0) {
                addonsTotal += price;
                addonsHtml += `
                    <div class="d-flex justify-content-between small mb-1">
                        <span class="text-muted">${name}</span>
                        <span>₱${formatAmount(price)}</span>
                    </div>`;
            }
        });

        if (summaryAddons) {
            summaryAddons.innerHTML = addonsHtml;
        }

        const total = basePrice + addonsTotal;
        const deposit = depositPercentage > 0 ? (total * depositPercentage / 100) : 0;

        if (summaryGuestCount) summaryGuestCount.textContent = guestCount.toString();
        if (summaryBasePrice) summaryBasePrice.textContent = formatAmount(basePrice);
        if (summaryTotal) summaryTotal.textContent = formatAmount(total);
        if (summaryDeposit) summaryDeposit.textContent = formatAmount(deposit);
    };

    if (guestCountInput) {
        guestCountInput.addEventListener('input', recalc);
    }

    addonCheckboxes.forEach((checkbox) => {
        checkbox.addEventListener('change', recalc);
    });

    recalc();
});
</script>
