<?php
require_once __DIR__ . '/../init.php';
require_auth(['caterer']);
require_once __DIR__ . '/../lib/caterer_service.php';

$user = current_user();

$pageTitle = 'Caterer Settings';
$pageStyles = [APP_URL . '/public/assets/css/dashboard.css'];

$subscriptionSuccess = null;
$subscriptionError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_subscription') {
        $plan = $_POST['plan'] ?? '';

        try {
            upsert_caterer_subscription((int) $user['id'], $plan);
            $subscriptionSuccess = ucfirst($plan) . ' subscription activated successfully.';
        } catch (Throwable $e) {
            $subscriptionError = $e instanceof InvalidArgumentException
                ? $e->getMessage()
                : 'Unable to update subscription right now. Please try again.';
        }
    }
}

$subscription = get_caterer_subscription((int) $user['id']);
$subscriptionPlans = [
    'monthly' => [
        'label' => 'Monthly Subscription',
        'price' => 329,
        'caption' => 'Billed monthly. Cancel anytime.',
        'frequency' => 'per month',
    ],
    'yearly' => [
        'label' => 'Yearly Subscription',
        'price' => 3500,
        'caption' => 'Best value—save over ₱400 vs monthly.',
        'frequency' => 'per year',
    ],
];

$subscriptionStartDate = null;
$subscriptionRenewDate = null;

if ($subscription) {
    try {
        $subscriptionStartDate = (new DateTime($subscription['started_at']))->format('F j, Y');
        $subscriptionRenewDate = (new DateTime($subscription['renews_at']))->format('F j, Y');
    } catch (Exception $e) {
        $subscriptionStartDate = $subscription['started_at'];
        $subscriptionRenewDate = $subscription['renews_at'];
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php include __DIR__ . '/../includes/caterer_sidebar.php'; ?>

    <div class="dashboard-content">
        <div class="page-header">
            <div>
                <h1 class="fw-bold">Account settings</h1>
                <p class="text-muted">Manage team access and billing information.</p>
            </div>
            <a href="<?= APP_URL ?>/logout.php" class="btn btn-outline-danger"><i class="bi bi-box-arrow-right me-2"></i>Log out</a>
        </div>

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <h5 class="fw-semibold mb-3">Business contacts</h5>
                        <form>
                            <div class="mb-3">
                                <label class="form-label">Primary contact name</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($user['name']) ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Primary email</label>
                                <input type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Contact number</label>
                                <input type="text" class="form-control" value="+63 917 888 4567">
                            </div>
                            <button type="submit" class="btn btn-primary">Save changes</button>
                        </form>
                    </div>
                </div>

                
            </div>
            <div class="col-lg-6">
                

                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <h5 class="fw-semibold mb-3">Billing</h5>
                        <p class="text-muted small">Choose the subscription that fits your business best.</p>
                        <?php if ($subscriptionSuccess): ?><div class="alert alert-success small mb-3"><?= htmlspecialchars($subscriptionSuccess) ?></div><?php endif; ?>
                        <?php if ($subscriptionError): ?><div class="alert alert-danger small mb-3"><?= htmlspecialchars($subscriptionError) ?></div><?php endif; ?>
                        <?php if ($subscription): ?>
                            <div class="border rounded-3 bg-light p-3 mb-4">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="fw-semibold mb-1">Current plan: <?= htmlspecialchars($subscriptionPlans[$subscription['plan']]['label'] ?? ucfirst($subscription['plan']) . ' plan') ?></h6>
                                        <small class="text-muted">Started <?= htmlspecialchars($subscriptionStartDate ?? $subscription['started_at']) ?> · Renews <?= htmlspecialchars($subscriptionRenewDate ?? $subscription['renews_at']) ?></small>
                                    </div>
                                    <span class="badge bg-primary">Active</span>
                                </div>
                            </div>
                        <?php else: ?>
                            <p class="text-muted small mb-4">No active subscription yet. Activate one below to unlock premium caterer tools.</p>
                        <?php endif; ?>
                        <div class="row g-3">
                            <?php foreach ($subscriptionPlans as $planKey => $planMeta): ?>
                                <?php $isActivePlan = $subscription && $subscription['plan'] === $planKey; ?>
                                <div class="col-md-6">
                                    <form method="post" class="h-100">
                                        <input type="hidden" name="action" value="update_subscription">
                                        <input type="hidden" name="plan" value="<?= htmlspecialchars($planKey) ?>">
                                        <div class="border rounded-3 h-100 p-3 <?= $isActivePlan ? 'border-primary' : 'border-light' ?>">
                                            <div class="d-flex justify-content-between align-items-start mb-3">
                                                <div>
                                                    <h6 class="fw-semibold mb-1"><?= htmlspecialchars($planMeta['label']) ?></h6>
                                                    <p class="text-muted small mb-0"><?= htmlspecialchars($planMeta['caption']) ?></p>
                                                </div>
                                                <?php if ($isActivePlan): ?><span class="badge bg-primary-subtle text-primary">Current</span><?php endif; ?>
                                            </div>
                                            <div class="mb-3">
                                                <div class="display-6 fw-bold mb-0">₱<?= number_format($planMeta['price'], 0) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($planMeta['frequency']) ?></small>
                                            </div>
                                            <button type="submit" class="btn <?= $isActivePlan ? 'btn-secondary' : 'btn-outline-primary' ?> w-100" <?= $isActivePlan ? 'disabled' : '' ?>>
                                                <?= $isActivePlan ? 'Active plan' : 'Choose plan' ?>
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="d-flex align-items-center gap-2 mt-4">
                            <i class="bi bi-receipt text-muted"></i>
                            <small class="text-muted">Invoices will be emailed to <?= htmlspecialchars($user['email']) ?> after each renewal.</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
