<?php
require_once __DIR__ . '/../init.php';
require_auth(['caterer']);

$user = current_user();

if ($user['status'] !== 'pending') {
    redirect('/caterer/dashboard.php');
}

$pageTitle = 'Account Under Review';
include __DIR__ . '/../includes/header.php';
?>

<section class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-5 text-center">
                        <span class="badge bg-warning-subtle text-warning mb-3">Verification in progress</span>
                        <h1 class="fw-bold mb-3">Thanks for joining Plateful!</h1>
                        <p class="text-muted mb-4">Our admin team is reviewing your caterer application to make sure all providers meet our quality standards. You&#39;ll receive an email once your account is approved.</p>
                        <div class="d-flex flex-column gap-3">
                            <div class="border rounded-3 p-3 text-start">
                                <h6 class="fw-semibold mb-1"><i class="bi bi-clock-history text-warning me-2"></i>Typical review time</h6>
                                <p class="mb-0 text-muted small">Approvals are usually completed within 1-2 business days. We&#39;ll reach out if additional information is needed.</p>
                            </div>
                            <div class="border rounded-3 p-3 text-start">
                                <h6 class="fw-semibold mb-1"><i class="bi bi-envelope-open text-primary me-2"></i>Need help updating your submission?</h6>
                                <p class="mb-0 text-muted small">Email <a href="mailto:support@plateful.local">support@plateful.local</a> if you have more documents or details to share.</p>
                            </div>
                        </div>
                        <a href="<?= APP_URL ?>/logout.php" class="btn btn-outline-secondary mt-4">Log out</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
