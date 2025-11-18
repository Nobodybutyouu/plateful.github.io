<?php
require_once __DIR__ . '/../init.php';
require_auth(['caterer']);
require_once __DIR__ . '/../lib/caterer_service.php';

$user = current_user();

if ($user['status'] === 'pending') {
    redirect('/caterer/pending.php');
}

$pageTitle = 'Menu & Packages';
$pageStyles = [APP_URL . '/public/assets/css/dashboard.css'];

$profile = get_caterer_profile($user['id']);

if (!$profile) {
    redirect('/caterer/profile.php');
}

$successMessage = null;
$errorMessage = null;
$activeTab = $_GET['tab'] ?? 'list';
$name = '';
$description = '';
$inclusions = '';
$price = null;
$depositPercentage = null;
$isActive = true;

$packageType = 'full';
$maincoursesInput = [];
$servicesInput = [];
$addonsInput = [];
$menuItemsInput = [];

$channelName = '';
$channelDetails = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    $name = trim($_POST['name'] ?? '');
    $price = isset($_POST['price']) && $_POST['price'] !== '' ? (float) $_POST['price'] : null;
    $depositPercentage = isset($_POST['deposit_percentage']) && $_POST['deposit_percentage'] !== ''
        ? (float) $_POST['deposit_percentage']
        : null;
    $description = trim($_POST['description'] ?? '');
    $inclusions = trim($_POST['inclusions'] ?? '');
    $packageType = isset($_POST['package_type']) && $_POST['package_type'] !== ''
        ? strtolower(trim((string) $_POST['package_type']))
        : 'full';
    $isActive = isset($_POST['is_active'])
        ? in_array($_POST['is_active'], ['1', 'on', 'true'], true)
        : false;

    $channelName = trim($_POST['channel_name'] ?? '');
    $channelDetails = trim($_POST['channel_details'] ?? '');

    try {
        $items = [];

        if (in_array($action, ['create', 'update'], true)) {
            $maincoursesInput = isset($_POST['maincourses']) && is_array($_POST['maincourses'])
                ? array_values(array_filter(array_map('trim', $_POST['maincourses']), 'strlen'))
                : [];

            $servicesInput = isset($_POST['services']) && is_array($_POST['services'])
                ? array_values(array_filter(array_map('trim', $_POST['services']), 'strlen'))
                : [];

            $addonsInput = [];
            $addonNames = isset($_POST['addon_names']) && is_array($_POST['addon_names']) ? $_POST['addon_names'] : [];
            $addonPrices = isset($_POST['addon_prices']) && is_array($_POST['addon_prices']) ? $_POST['addon_prices'] : [];

            foreach ($addonNames as $idx => $addonNameRaw) {
                $addonName = trim((string) $addonNameRaw);

                if ($addonName === '') {
                    continue;
                }

                $priceRaw = $addonPrices[$idx] ?? '';
                $addonPrice = $priceRaw !== '' ? (float) $priceRaw : null;

                if ($addonPrice !== null && $addonPrice < 0) {
                    throw new InvalidArgumentException('Add-on price cannot be negative.');
                }

                $addonsInput[] = [
                    'name' => $addonName,
                    'price' => $addonPrice,
                ];
            }

            if ($packageType === 'food' || $packageType === 'full') {
                foreach ($maincoursesInput as $courseName) {
                    $items[] = [
                        'item_type' => 'maincourse',
                        'name' => $courseName,
                        'price' => null,
                    ];
                }
            }

            if ($packageType === 'services' || $packageType === 'full') {
                foreach ($servicesInput as $serviceName) {
                    $items[] = [
                        'item_type' => 'service',
                        'name' => $serviceName,
                        'price' => null,
                    ];
                }
            }

            foreach ($addonsInput as $addon) {
                $items[] = [
                    'item_type' => 'addon',
                    'name' => $addon['name'],
                    'price' => $addon['price'],
                ];
            }
        }

        switch ($action) {
            case 'create':
                if ($name === '' || $price === null) {
                    throw new InvalidArgumentException('Name and price are required.');
                }

                $newPackageId = create_caterer_package(
                    (int) $profile['id'],
                    $name,
                    $price,
                    $depositPercentage,
                    $packageType,
                    $description !== '' ? $description : null,
                    $inclusions !== '' ? $inclusions : null,
                    $isActive
                );

                replace_package_items($newPackageId, $items);

                $successMessage = 'Package created successfully.';
                $name = '';
                $price = null;
                $depositPercentage = null;
                $description = '';
                $inclusions = '';
                $isActive = true;
                $packageType = 'full';
                $maincoursesInput = [];
                $servicesInput = [];
                $addonsInput = [];
                $activeTab = 'list';
                break;

            case 'update':
                $packageId = (int) ($_POST['package_id'] ?? 0);

                if ($packageId <= 0) {
                    throw new InvalidArgumentException('Invalid package selection.');
                }

                if ($name === '' || $price === null) {
                    throw new InvalidArgumentException('Name and price are required.');
                }

                update_caterer_package(
                    $packageId,
                    (int) $profile['id'],
                    $name,
                    $price,
                    $depositPercentage,
                    $packageType,
                    $description !== '' ? $description : null,
                    $inclusions !== '' ? $inclusions : null,
                    $isActive
                );

                replace_package_items($packageId, $items);

                $successMessage = 'Package updated successfully.';
                $activeTab = 'list';
                break;

            case 'delete':
                $packageId = (int) ($_POST['package_id'] ?? 0);

                if ($packageId <= 0) {
                    throw new InvalidArgumentException('Invalid package selection.');
                }

                delete_caterer_package($packageId, (int) $profile['id']);

                $successMessage = 'Package deleted successfully.';
                $activeTab = 'list';
                break;

            case 'toggle-status':
                $packageId = (int) ($_POST['package_id'] ?? 0);

                if ($packageId <= 0) {
                    throw new InvalidArgumentException('Invalid package selection.');
                }

                set_caterer_package_status($packageId, (int) $profile['id'], !$isActive);

                $successMessage = $isActive ? 'Package hidden from customers.' : 'Package marked active.';
                $activeTab = 'list';
                break;

            case 'create_payment_channel':
                create_caterer_payment_channel(
                    (int) $profile['id'],
                    $channelName,
                    $channelDetails
                );

                $successMessage = 'Payment channel added.';
                $channelName = '';
                $channelDetails = '';
                $activeTab = 'channels';
                break;

            case 'delete_payment_channel':
                $channelId = (int) ($_POST['channel_id'] ?? 0);

                if ($channelId <= 0) {
                    throw new InvalidArgumentException('Invalid payment channel selection.');
                }

                delete_caterer_payment_channel($channelId, (int) $profile['id']);

                $successMessage = 'Payment channel removed.';
                $activeTab = 'channels';
                break;

            case 'save_menu':
                $menuItemsInput = [];

                $menuMaincourses = isset($_POST['menu_maincourse']) && is_array($_POST['menu_maincourse'])
                    ? $_POST['menu_maincourse']
                    : [];
                $menuDishes = isset($_POST['menu_dish']) && is_array($_POST['menu_dish'])
                    ? $_POST['menu_dish']
                    : [];

                foreach ($menuMaincourses as $idx => $categoryRaw) {
                    $category = trim((string) $categoryRaw);
                    $dish = isset($menuDishes[$idx]) ? trim((string) $menuDishes[$idx]) : '';

                    if ($category === '' && $dish === '') {
                        continue;
                    }

                    $menuItemsInput[] = [
                        'category' => $category,
                        'dish' => $dish,
                    ];
                }

                if (empty($menuItemsInput)) {
                    throw new InvalidArgumentException('Please add at least one menu item.');
                }

                foreach ($menuItemsInput as $row) {
                    if ($row['category'] === '' || $row['dish'] === '') {
                        throw new InvalidArgumentException('Each menu row must have both a maincourse name and a dish name.');
                    }
                }

                $menuItemsToSave = array_map(static function (array $row): array {
                    return [
                        'category' => $row['category'],
                        'name' => $row['dish'],
                        'description' => null,
                    ];
                }, $menuItemsInput);

                replace_caterer_menu_items((int) $profile['id'], $menuItemsToSave);

                $successMessage = 'Menu updated successfully.';
                $activeTab = 'menu';
                break;

            default:
                throw new InvalidArgumentException('Unsupported action.');
        }
    } catch (Throwable $e) {
        $errorMessage = $e instanceof InvalidArgumentException
            ? $e->getMessage()
            : 'Unable to process request right now. Please try again.';

        if ($action === 'save_menu') {
            $activeTab = 'menu';
        } elseif (str_starts_with($action, 'create_payment_channel') || str_starts_with($action, 'delete_payment_channel')) {
            $activeTab = 'channels';
        } else {
            $activeTab = 'form';
        }
    }
}

$packages = get_caterer_packages((int) $profile['id']);
$menuItemsFromDb = get_caterer_menu_items((int) $profile['id']);

if (empty($menuItemsInput) && !empty($menuItemsFromDb)) {
    $menuItemsInput = array_map(static function (array $row): array {
        return [
            'category' => $row['category'],
            'dish' => $row['name'],
        ];
    }, $menuItemsFromDb);
}

usort($packages, static function (array $a, array $b): int {
    if ($a['is_active'] === $b['is_active']) {
        return strcasecmp($a['name'], $b['name']);
    }

    return $a['is_active'] ? -1 : 1;
});

$paymentChannels = get_caterer_payment_channels((int) $profile['id']);

$editPackage = null;

if (isset($_GET['edit'])) {
    $packageId = (int) $_GET['edit'];
    foreach ($packages as $pkg) {
        if ($pkg['id'] === $packageId) {
            $editPackage = $pkg;
            $activeTab = 'form';
            break;
        }
    }
}

if ($editPackage && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $existingItems = get_package_items((int) $editPackage['id']);

    $maincoursesInput = [];
    $servicesInput = [];
    $addonsInput = [];

    foreach ($existingItems as $item) {
        if ($item['item_type'] === 'maincourse') {
            $maincoursesInput[] = $item['name'];
        } elseif ($item['item_type'] === 'service') {
            $servicesInput[] = $item['name'];
        } elseif ($item['item_type'] === 'addon') {
            $addonsInput[] = [
                'name' => $item['name'],
                'price' => $item['price'],
            ];
        }
    }

    if (!empty($editPackage['package_type'])) {
        $packageType = $editPackage['package_type'];
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php include __DIR__ . '/../includes/caterer_sidebar.php'; ?>

    <div class="dashboard-content">
        <div class="page-header">
            <div>
                <h1 class="fw-bold">Menu & packages</h1>
                <p class="text-muted">Showcase your offerings with clear pricing, inclusions, and availability.</p>
            </div>
            <a class="btn btn-primary" href="?tab=form"><i class="bi bi-plus-lg me-2"></i>Add package</a>
        </div>

        <?php if ($successMessage): ?><div class="alert alert-success small"><?= htmlspecialchars($successMessage) ?></div><?php endif; ?>
        <?php if ($errorMessage): ?><div class="alert alert-danger small"><?= htmlspecialchars($errorMessage) ?></div><?php endif; ?>

        <ul class="nav nav-pills mb-3">
            <li class="nav-item"><a class="nav-link <?= $activeTab === 'list' ? 'active' : '' ?>" href="?tab=list">My packages</a></li>
            <li class="nav-item"><a class="nav-link <?= $activeTab === 'form' ? 'active' : '' ?>" href="?tab=form"><?= $editPackage ? 'Edit package' : 'Add package' ?></a></li>
            <li class="nav-item"><a class="nav-link <?= $activeTab === 'menu' ? 'active' : '' ?>" href="?tab=menu">Menu</a></li>
            <li class="nav-item"><a class="nav-link <?= $activeTab === 'channels' ? 'active' : '' ?>" href="?tab=channels">Payment channels</a></li>
        </ul>

        <?php if ($activeTab === 'form'): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h5 class="fw-semibold mb-3"><?= $editPackage ? 'Update package' : 'Create new package' ?></h5>
                    <form method="post" class="row g-3">
                        <input type="hidden" name="action" value="<?= $editPackage ? 'update' : 'create' ?>">
                        <?php if ($editPackage): ?><input type="hidden" name="package_id" value="<?= (int) $editPackage['id'] ?>"><?php endif; ?>
                        <div class="col-md-6">
                            <label class="form-label">Package name<span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($editPackage['name'] ?? $name) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Price per head<span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">₱</span>
                                <input type="number" name="price" class="form-control" min="0" step="0.01" value="<?= htmlspecialchars($editPackage['price'] ?? ($price ?? '')) ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Deposit percentage</label>
                            <div class="input-group">
                                <input
                                    type="number"
                                    name="deposit_percentage"
                                    class="form-control"
                                    min="0"
                                    max="100"
                                    step="0.01"
                                    value="<?= htmlspecialchars($editPackage['deposit_percentage'] ?? ($depositPercentage ?? '')) ?>"
                                    placeholder="e.g. 30"
                                >
                                <span class="input-group-text">%</span>
                            </div>
                            <small class="text-muted">Leave blank if no upfront deposit is required.</small>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Package type</label>
                            <?php
                                $currentType = $editPackage['package_type'] ?? $packageType ?? 'full';
                            ?>
                            <select name="package_type" class="form-select">
                                <option value="full" <?= $currentType === 'full' ? 'selected' : '' ?>>Full package</option>
                                <option value="food" <?= $currentType === 'food' ? 'selected' : '' ?>>Food only</option>
                                <option value="services" <?= $currentType === 'services' ? 'selected' : '' ?>>Services only</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="isActiveSwitch" <?= ($editPackage['is_active'] ?? $isActive) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="isActiveSwitch">Visible to customers</label>
                            </div>
                        </div>
                        <div class="col-12" id="maincoursesSection">
                            <label class="form-label">Main courses</label>
                            <div id="maincoursesRepeater" class="vstack gap-2">
                                <?php
                                    $maincourses = $maincoursesInput;
                                    if (empty($maincourses)) {
                                        $maincourses = [''];
                                    }
                                ?>
                                <?php foreach ($maincourses as $courseName): ?>
                                    <div class="input-group package-item-row">
                                        <input type="text" name="maincourses[]" class="form-control" value="<?= htmlspecialchars($courseName) ?>" placeholder="e.g. Beef, Chicken, Sea Foods">
                                        <button type="button" class="btn btn-outline-danger btn-sm package-item-remove">Remove</button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="addMaincourseBtn">Add main course</button>
                        </div>
                        <div class="col-12" id="servicesSection">
                            <label class="form-label">Services</label>
                            <div id="servicesRepeater" class="vstack gap-2">
                                <?php
                                    $services = $servicesInput;
                                    if (empty($services)) {
                                        $services = [''];
                                    }
                                ?>
                                <?php foreach ($services as $serviceName): ?>
                                    <div class="input-group package-item-row">
                                        <input type="text" name="services[]" class="form-control" value="<?= htmlspecialchars($serviceName) ?>" placeholder="e.g. Wait staff">
                                        <button type="button" class="btn btn-outline-danger btn-sm package-item-remove">Remove</button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="addServiceBtn">Add service</button>
                        </div>
                        <div class="col-12" id="addonsSection">
                            <label class="form-label">Add-ons</label>
                            <div id="addonsRepeater" class="vstack gap-2">
                                <?php
                                    $addons = $addonsInput;
                                    if (empty($addons)) {
                                        $addons = [['name' => '', 'price' => null]];
                                    }
                                ?>
                                <?php foreach ($addons as $addon): ?>
                                    <div class="input-group package-item-row">
                                        <input type="text" name="addon_names[]" class="form-control" value="<?= htmlspecialchars($addon['name'] ?? '') ?>" placeholder="Add-on name (e.g. Dessert station)">
                                        <input type="number" name="addon_prices[]" class="form-control" min="0" step="0.01" value="<?= isset($addon['price']) && $addon['price'] !== null ? htmlspecialchars((string) $addon['price']) : '' ?>" placeholder="Optional price">
                                        <button type="button" class="btn btn-outline-danger btn-sm package-item-remove">Remove</button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <small class="text-muted d-block mb-1">Add-ons are optional and can have an optional price.</small>
                            <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="addAddonBtn">Add add-on</button>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Short description</label>
                            <textarea name="description" class="form-control" rows="2" placeholder="Highlight what makes this package special."><?= htmlspecialchars($editPackage['description'] ?? ($description ?? '')) ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Inclusions</label>
                            <textarea name="inclusions" class="form-control" rows="3" placeholder="List key inclusions or menu details."><?= htmlspecialchars($editPackage['inclusions'] ?? ($inclusions ?? '')) ?></textarea>
                            <small class="text-muted">Separate bullet points with commas for quick scanning.</small>
                        </div>
                        <div class="col-12 d-flex gap-2">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i><?= $editPackage ? 'Save changes' : 'Create package' ?></button>
                            <?php if ($editPackage): ?>
                                <a href="?tab=list" class="btn btn-outline-secondary">Cancel</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($activeTab === 'menu'): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h5 class="fw-semibold mb-3">Menu</h5>
                    <p class="text-muted small mb-3">Define your main course categories (e.g. Beef, Chicken) and the dishes available under each. Customers will see this when viewing your packages.</p>
                    <form method="post" class="vstack gap-3">
                        <input type="hidden" name="action" value="save_menu">
                        <div id="menuItemsRepeater" class="vstack gap-3">
                            <?php
                                $menuItems = $menuItemsInput;
                                if (empty($menuItems)) {
                                    $menuItems = [['category' => '', 'dish' => '']];
                                }
                            ?>
                            <?php foreach ($menuItems as $row): ?>
                                <div class="row g-2 align-items-center package-item-row">
                                    <div class="col-md-4">
                                        <input type="text" name="menu_maincourse[]" class="form-control" value="<?= htmlspecialchars($row['category']) ?>" placeholder="Maincourse, e.g. Beef">
                                    </div>
                                    <div class="col-md-6">
                                        <input type="text" name="menu_dish[]" class="form-control" value="<?= htmlspecialchars($row['dish']) ?>" placeholder="Dish name, e.g. Beef Steak">
                                    </div>
                                    <div class="col-md-2 d-flex">
                                        <button type="button" class="btn btn-outline-danger btn-sm package-item-remove w-100">Remove</button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="addMenuItemBtn">Add menu item</button>
                        <div>
                            <button type="submit" class="btn btn-primary mt-3"><i class="bi bi-save me-1"></i>Save menu</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($activeTab === 'channels'): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h5 class="fw-semibold mb-3">Payment channels</h5>
                    <p class="text-muted small">Add the payment methods you accept (e.g. GCash, bank transfer). Customers will see these when completing their payments.</p>
                    <form method="post" class="row g-3 mb-4">
                        <input type="hidden" name="action" value="create_payment_channel">
                        <div class="col-md-4">
                            <label class="form-label">Channel name<span class="text-danger">*</span></label>
                            <input type="text" name="channel_name" class="form-control" value="<?= htmlspecialchars($channelName) ?>" placeholder="e.g. GCash" required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Channel details<span class="text-danger">*</span></label>
                            <textarea name="channel_details" class="form-control" rows="2" placeholder="Account name, number, and any instructions." required><?= htmlspecialchars($channelDetails) ?></textarea>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Add channel</button>
                        </div>
                    </form>

                    <?php if (empty($paymentChannels)): ?>
                        <p class="text-muted mb-0">You haven’t added any payment channels yet.</p>
                    <?php else: ?>
                        <div class="row g-3">
                            <?php foreach ($paymentChannels as $channel): ?>
                                <div class="col-md-6">
                                    <div class="border rounded-4 p-3 h-100">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h6 class="fw-semibold mb-0"><?= htmlspecialchars($channel['name']) ?></h6>
                                            <form method="post" onsubmit="return confirm('Remove this payment channel?');">
                                                <input type="hidden" name="action" value="delete_payment_channel">
                                                <input type="hidden" name="channel_id" value="<?= (int) $channel['id'] ?>">
                                                <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-x-lg"></i></button>
                                            </form>
                                        </div>
                                        <p class="mb-0 small" style="white-space: pre-line;">
                                            <?= htmlspecialchars($channel['details']) ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h5 class="fw-semibold mb-3">Current packages (<?= count($packages) ?>)</h5>
                <?php if (empty($packages)): ?>
                    <p class="text-muted mb-0">You haven’t added any packages yet. Create one to showcase what you offer.</p>
                <?php else: ?>
                    <div class="row g-4">
                        <?php foreach ($packages as $package): ?>
                            <?php
                                $packageId = (int) $package['id'];
                                $collapseId = 'packageDetails' . $packageId;
                                $inclusionsList = [];
                                if (!empty($package['inclusions'])) {
                                    $inclusionsList = array_filter(array_map('trim', explode("\n", (string) $package['inclusions'])));
                                }
                                $typeLabel = 'Full package';
                                if (!empty($package['package_type'])) {
                                    if ($package['package_type'] === 'food') {
                                        $typeLabel = 'Food only';
                                    } elseif ($package['package_type'] === 'services') {
                                        $typeLabel = 'Services only';
                                    }
                                }
                            ?>
                            <div class="col-md-6 col-xl-4">
                                <div class="border rounded-4 p-4 h-100">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <h4 class="fw-bold mb-1"><?= htmlspecialchars($package['name']) ?></h4>
                                            <p class="text-primary fw-semibold fs-5 mb-1"><?= format_currency($package['price']) ?></p>
                                            <?php if ($package['deposit_percentage'] !== null): ?>
                                                <?php $depositLabel = rtrim(rtrim(number_format((float) $package['deposit_percentage'], 2), '0'), '.'); ?>
                                                <span class="badge bg-primary-subtle text-primary me-1"><?= $depositLabel ?>% deposit</span>
                                            <?php endif; ?>
                                            <span class="badge bg-secondary-subtle text-secondary small"><?= htmlspecialchars($typeLabel) ?></span>
                                        </div>
                                        <span class="badge <?= $package['is_active'] ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' ?>">
                                            <?= $package['is_active'] ? 'Active' : 'Hidden' ?>
                                        </span>
                                    </div>

                                    <?php if (!empty($package['description']) || !empty($inclusionsList)): ?>
                                        <button
                                            class="btn btn-sm btn-outline-success mt-3"
                                            type="button"
                                            data-bs-toggle="collapse"
                                            data-bs-target="#<?= htmlspecialchars($collapseId) ?>"
                                            aria-expanded="false"
                                            aria-controls="<?= htmlspecialchars($collapseId) ?>"
                                        >
                                            View details
                                        </button>
                                        <div class="collapse mt-3" id="<?= htmlspecialchars($collapseId) ?>">
                                            <?php if (!empty($package['description'])): ?>
                                                <p class="text-muted small mb-3"><?= nl2br(htmlspecialchars($package['description'])) ?></p>
                                            <?php endif; ?>
                                            <?php if (!empty($inclusionsList)): ?>
                                                <p class="fw-semibold small mb-2">Inclusions:</p>
                                                <ul class="small text-muted ps-3 mb-0">
                                                    <?php foreach ($inclusionsList as $item): ?>
                                                        <li><?= htmlspecialchars($item) ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>

                                    <div class="d-flex flex-wrap gap-2 mt-3">
                                        <a class="btn btn-outline-secondary btn-sm" href="?edit=<?= $packageId ?>&tab=form"><i class="bi bi-pencil me-1"></i>Edit</a>
                                        <form method="post">
                                            <input type="hidden" name="action" value="toggle-status">
                                            <input type="hidden" name="package_id" value="<?= $packageId ?>">
                                            <input type="hidden" name="is_active" value="<?= $package['is_active'] ? '1' : '0' ?>">
                                            <button type="submit" class="btn btn-outline-<?= $package['is_active'] ? 'warning' : 'success' ?> btn-sm">
                                                <i class="bi <?= $package['is_active'] ? 'bi-eye-slash' : 'bi-eye' ?> me-1"></i><?= $package['is_active'] ? 'Hide package' : 'Make active' ?>
                                            </button>
                                        </form>
                                        <form method="post" onsubmit="return confirm('Delete this package? This action cannot be undone.');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="package_id" value="<?= $packageId ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash me-1"></i>Delete</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var packageTypeSelect = document.querySelector('select[name="package_type"]');
    var maincoursesSection = document.getElementById('maincoursesSection');
    var servicesSection = document.getElementById('servicesSection');
    var addonsSection = document.getElementById('addonsSection');

    function refreshPackageTypeSections() {
        if (!packageTypeSelect) {
            return;
        }
        var type = packageTypeSelect.value;
        if (maincoursesSection) {
            maincoursesSection.style.display = (type === 'food' || type === 'full') ? '' : 'none';
        }
        if (servicesSection) {
            servicesSection.style.display = (type === 'services' || type === 'full') ? '' : 'none';
        }
        if (addonsSection) {
            addonsSection.style.display = '';
        }
    }

    if (packageTypeSelect) {
        packageTypeSelect.addEventListener('change', refreshPackageTypeSections);
    }
    refreshPackageTypeSections();

    function setupRepeater(buttonId, containerId, createRow) {
        var button = document.getElementById(buttonId);
        var container = document.getElementById(containerId);
        if (!button || !container) {
            return;
        }
        button.addEventListener('click', function () {
            var row = createRow();
            container.appendChild(row);
        });
    }

    setupRepeater('addMaincourseBtn', 'maincoursesRepeater', function () {
        var wrapper = document.createElement('div');
        wrapper.className = 'input-group package-item-row';
        var input = document.createElement('input');
        input.type = 'text';
        input.name = 'maincourses[]';
        input.className = 'form-control';
        input.placeholder = 'e.g. Beef, Chicken, Sea Foods';
        var remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'btn btn-outline-danger btn-sm package-item-remove';
        remove.textContent = 'Remove';
        wrapper.appendChild(input);
        wrapper.appendChild(remove);
        return wrapper;
    });

    setupRepeater('addServiceBtn', 'servicesRepeater', function () {
        var wrapper = document.createElement('div');
        wrapper.className = 'input-group package-item-row';
        var input = document.createElement('input');
        input.type = 'text';
        input.name = 'services[]';
        input.className = 'form-control';
        input.placeholder = 'e.g. Wait staff';
        var remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'btn btn-outline-danger btn-sm package-item-remove';
        remove.textContent = 'Remove';
        wrapper.appendChild(input);
        wrapper.appendChild(remove);
        return wrapper;
    });

    setupRepeater('addAddonBtn', 'addonsRepeater', function () {
        var wrapper = document.createElement('div');
        wrapper.className = 'input-group package-item-row';
        var nameInput = document.createElement('input');
        nameInput.type = 'text';
        nameInput.name = 'addon_names[]';
        nameInput.className = 'form-control';
        nameInput.placeholder = 'Add-on name (e.g. Dessert station)';
        var priceInput = document.createElement('input');
        priceInput.type = 'number';
        priceInput.name = 'addon_prices[]';
        priceInput.className = 'form-control';
        priceInput.min = '0';
        priceInput.step = '0.01';
        priceInput.placeholder = 'Optional price';
        var remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'btn btn-outline-danger btn-sm package-item-remove';
        remove.textContent = 'Remove';
        wrapper.appendChild(nameInput);
        wrapper.appendChild(priceInput);
        wrapper.appendChild(remove);
        return wrapper;
    });

    setupRepeater('addMenuItemBtn', 'menuItemsRepeater', function () {
        var wrapper = document.createElement('div');
        wrapper.className = 'row g-2 align-items-center package-item-row';

        var colCategory = document.createElement('div');
        colCategory.className = 'col-md-4';
        var categoryInput = document.createElement('input');
        categoryInput.type = 'text';
        categoryInput.name = 'menu_maincourse[]';
        categoryInput.className = 'form-control';
        categoryInput.placeholder = 'Maincourse, e.g. Beef';
        colCategory.appendChild(categoryInput);

        var colDish = document.createElement('div');
        colDish.className = 'col-md-6';
        var dishInput = document.createElement('input');
        dishInput.type = 'text';
        dishInput.name = 'menu_dish[]';
        dishInput.className = 'form-control';
        dishInput.placeholder = 'Dish name, e.g. Beef Steak';
        colDish.appendChild(dishInput);

        var colActions = document.createElement('div');
        colActions.className = 'col-md-2 d-flex';
        var remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'btn btn-outline-danger btn-sm package-item-remove w-100';
        remove.textContent = 'Remove';
        colActions.appendChild(remove);

        wrapper.appendChild(colCategory);
        wrapper.appendChild(colDish);
        wrapper.appendChild(colActions);

        return wrapper;
    });

    document.addEventListener('click', function (event) {
        var target = event.target;
        if (target.classList.contains('package-item-remove')) {
            var row = target.closest('.package-item-row');
            if (!row) {
                return;
            }
            var container = row.parentElement;
            if (container && container.querySelectorAll('.package-item-row').length > 1) {
                row.remove();
            } else {
                var textInput = row.querySelector('input[type="text"]');
                var numberInput = row.querySelector('input[type="number"]');
                if (textInput) {
                    textInput.value = '';
                }
                if (numberInput) {
                    numberInput.value = '';
                }
            }
        }
    });
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
