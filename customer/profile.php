<?php
require_once __DIR__ . '/../init.php';
require_auth(['customer']);
require_once __DIR__ . '/../lib/customer_service.php';

$pageTitle = 'My Profile';
$pageStyles = [APP_URL . '/public/assets/css/dashboard.css'];

$currentUser = current_user();
$profile = get_customer_profile_data((int) $currentUser['id']);
$successMessage = null;
$errorMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = trim($_POST['phone'] ?? '');
    $preferredLocation = trim($_POST['preferred_location'] ?? '');

    try {
        update_customer_profile(
            (int) $currentUser['id'],
            $phone !== '' ? $phone : null,
            $preferredLocation !== '' ? $preferredLocation : null
        );

        $successMessage = 'Profile information updated successfully.';
        $profile = get_customer_profile_data((int) $currentUser['id']);
    } catch (Throwable $e) {
        $errorMessage = 'Unable to update profile right now. Please try again.';
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php include __DIR__ . '/../includes/customer_sidebar.php'; ?>

    <div class="dashboard-content">
        <div class="page-header">
            <div>
                <h1 class="fw-bold">Account settings</h1>
                <p class="text-muted">Manage your personal information, contact details, and preferences.</p>
            </div>
            <a href="<?= APP_URL ?>/logout.php" class="btn btn-outline-danger"><i class="bi bi-box-arrow-right me-2"></i>Log out</a>
        </div>

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-4">
                        <h5 class="fw-semibold mb-3">Profile information</h5>
                        <?php if ($successMessage): ?><div class="alert alert-success small"><?= htmlspecialchars($successMessage) ?></div><?php endif; ?>
                        <?php if ($errorMessage): ?><div class="alert alert-danger small"><?= htmlspecialchars($errorMessage) ?></div><?php endif; ?>
                        <form method="post" novalidate>
                            <div class="mb-3">
                                <label class="form-label">Full name</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($currentUser['name']) ?>" placeholder="Enter your name">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email address</label>
                                <input type="email" class="form-control" value="<?= htmlspecialchars($currentUser['email']) ?>" placeholder="Enter your email">
                                <small class="text-muted">Email changes require verification.</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Phone number</label>
                                <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($profile['phone'] ?? '') ?>" placeholder="Optional">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Preferred location</label>
                                <input type="text" name="preferred_location" class="form-control" value="<?= htmlspecialchars($profile['preferred_location'] ?? '') ?>" placeholder="Optional">
                            </div>
                            <button type="submit" class="btn btn-primary">Save changes</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <h5 class="fw-semibold mb-3">Password & security</h5>
                        <form>
                            <div class="mb-3">
                                <label class="form-label">Current password</label>
                                <input type="password" class="form-control" placeholder="Enter current password">
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">New password</label>
                                    <input type="password" class="form-control" placeholder="Enter new password">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Confirm password</label>
                                    <input type="password" class="form-control" placeholder="Re-enter new password">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-outline-primary mt-3">Update password</button>
                        </form>
                    </div>
                </div>
                
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
