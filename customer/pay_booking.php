<?php
require_once __DIR__ . '/../init.php';
require_auth(['customer']);
require_once __DIR__ . '/../lib/customer_service.php';
require_once __DIR__ . '/../lib/caterer_service.php';

$user = current_user();
$bookingId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($bookingId <= 0) {
    header('Location: ' . APP_URL . '/customer/bookings.php');
    exit;
}

$errors = [];
$successMessage = null;
$amountField = '';
$selectedMethod = $_POST['payment_method'] ?? 'full';
$channelField = isset($_POST['payment_channel']) ? trim((string) $_POST['payment_channel']) : '';
$referenceField = isset($_POST['reference']) ? trim((string) $_POST['reference']) : '';
$proofTypeField = $_POST['proof_type'] ?? 'reference';
$pendingProofUpload = null;
$storedProofPath = null;
$paymentChannels = [];

try {
    $summary = get_customer_booking_payment_summary($bookingId, $user['id']);
} catch (Throwable $e) {
    $summary = null;
    $errors[] = 'Unable to load booking details right now. Please try again later.';
}

if (!$summary) {
    $pageTitle = 'Complete Payment';
    include __DIR__ . '/../includes/header.php';
    echo '<div class="container py-5"><div class="alert alert-danger">Booking not found or unavailable.</div><a href="' . APP_URL . '/customer/bookings.php" class="btn btn-primary">Back to bookings</a></div>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$pricePerHead = $summary['price_per_head'];
$totalAmount = $summary['total_amount'];
$depositAmount = $summary['deposit_amount'];
$depositPercentage = $summary['deposit_percentage'];
$amountPaid = $summary['amount_paid'];
$remainingBalance = $summary['remaining_balance'];
$pendingAmount = $summary['pending_amount'];
$pendingPaymentCount = $summary['pending_payment_count'];
$hasPendingPayment = $summary['has_pending_payment'];

try {
    $paymentChannels = get_caterer_payment_channels((int) $summary['caterer_profile_id']);
} catch (Throwable $e) {
    $paymentChannels = [];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $summary['requires_payment']) {
    $suggestedAmount = null;

    if ($selectedMethod === 'full' && $totalAmount !== null) {
        $suggestedAmount = $totalAmount;
    } elseif ($selectedMethod === 'deposit' && $depositAmount !== null) {
        $suggestedAmount = $depositAmount;
    }

    if ($suggestedAmount !== null && $suggestedAmount > 0) {
        $amountField = number_format($suggestedAmount, 2);
    }
}

$amountPlaceholder = '5,000.00';
if ($amountField !== '') {
    $amountPlaceholder = $amountField;
} else {
    if ($selectedMethod === 'full' && $totalAmount !== null) {
        $amountPlaceholder = number_format($totalAmount, 2);
    } elseif ($selectedMethod === 'deposit' && $depositAmount !== null) {
        $amountPlaceholder = number_format($depositAmount, 2);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawAmountInput = trim((string) ($_POST['amount'] ?? ''));
    $amountInput = str_replace([',', ' '], '', $rawAmountInput);
    $selectedMethod = $_POST['payment_method'] ?? 'full';
    $channelField = trim((string) ($_POST['payment_channel'] ?? ''));
    $referenceField = trim((string) ($_POST['reference'] ?? ''));
    $proofTypeField = $_POST['proof_type'] ?? $proofTypeField;
    $uploadedProof = $_FILES['payment_proof'] ?? null;

    $allowedMethods = ['full', 'deposit'];

    if (!in_array($selectedMethod, $allowedMethods, true)) {
        $errors[] = 'Please choose a valid payment method.';
    }

    if (!in_array($proofTypeField, ['reference', 'image'], true)) {
        $errors[] = 'Please choose how you will provide payment proof.';
        $proofTypeField = 'reference';
    }

    $amountValue = 0.0;

    if ($amountInput === '' || !is_numeric($amountInput)) {
        $errors[] = 'Enter a valid payment amount.';
    } else {
        $amountValue = (float) $amountInput;

        if ($amountValue <= 0) {
            $errors[] = 'Payment amount must be greater than zero.';
        }
    }

    if ($channelField === '') {
        $errors[] = 'Please choose or enter a payment channel.';
    }

    if ($uploadedProof && ($uploadedProof['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        if (($uploadedProof['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $errors[] = 'There was a problem uploading your proof image. Please try again.';
        } elseif (!is_uploaded_file($uploadedProof['tmp_name'])) {
            $errors[] = 'Invalid upload detected.';
        } else {
            $maxProofSize = 5 * 1024 * 1024; // 5 MB
            if (($uploadedProof['size'] ?? 0) > $maxProofSize) {
                $errors[] = 'Payment proof images must be 5MB or smaller.';
            } else {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mimeType = $finfo->file($uploadedProof['tmp_name']) ?: $uploadedProof['type'];

                if (!in_array($mimeType, ALLOWED_IMAGE_TYPES, true)) {
                    $errors[] = 'Unsupported image format. Please upload a JPEG, PNG, or WebP file.';
                } else {
                    $extensionMap = [
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png',
                        'image/webp' => 'webp',
                    ];
                    $extension = $extensionMap[$mimeType] ?? pathinfo($uploadedProof['name'] ?? '', PATHINFO_EXTENSION);
                    $pendingProofUpload = [
                        'tmp_name' => $uploadedProof['tmp_name'],
                        'extension' => $extension ?: 'dat',
                    ];
                }
            }
        }
    }

    if ($proofTypeField === 'reference') {
        if ($referenceField === '') {
            $errors[] = 'Please enter your transaction reference for this payment.';
        }
    } elseif ($proofTypeField === 'image') {
        if (!$uploadedProof || ($uploadedProof['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            $errors[] = 'Please attach a payment proof image.';
        }
    }

    if ($summary['status'] !== 'awaiting_payment') {
        $errors[] = 'This booking no longer requires payment.';
    }

    if ($rawAmountInput !== '') {
        $amountField = is_numeric($amountInput)
            ? number_format((float) $amountInput, 2)
            : $rawAmountInput;
    }

    if (empty($errors)) {
        if ($pendingProofUpload !== null) {
            $proofBaseDir = rtrim(UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR . 'payments';

            if (!is_dir($proofBaseDir) && !mkdir($proofBaseDir, 0775, true) && !is_dir($proofBaseDir)) {
                $errors[] = 'Unable to prepare storage for payment proof. Please try again later.';
            } else {
                $filename = sprintf('booking-%d-%s.%s', $bookingId, bin2hex(random_bytes(4)), $pendingProofUpload['extension']);
                $targetPath = $proofBaseDir . DIRECTORY_SEPARATOR . $filename;

                if (!move_uploaded_file($pendingProofUpload['tmp_name'], $targetPath)) {
                    $errors[] = 'Failed to save the uploaded proof image. Please retry.';
                } else {
                    $storedProofPath = 'storage/uploads/payments/' . $filename;
                }
            }
        }
    }

    if (empty($errors)) {
        try {
            record_booking_payment(
                $bookingId,
                $user['id'],
                $amountValue,
                $selectedMethod,
                $channelField !== '' ? $channelField : null,
                $referenceField !== '' ? $referenceField : null,
                $storedProofPath
            );

            $successMessage = "Payment submitted for verification. We'll notify you once the caterer confirms it.";
            $summary = get_customer_booking_payment_summary($bookingId, $user['id']);
            $pricePerHead = $summary['price_per_head'];
            $totalAmount = $summary['total_amount'];
            $depositAmount = $summary['deposit_amount'];
            $depositPercentage = $summary['deposit_percentage'];
            $amountPaid = $summary['amount_paid'];
            $remainingBalance = $summary['remaining_balance'];
            $pendingAmount = $summary['pending_amount'];
            $pendingPaymentCount = $summary['pending_payment_count'];
            $hasPendingPayment = $summary['has_pending_payment'];

            try {
                $paymentChannels = get_caterer_payment_channels((int) $summary['caterer_profile_id']);
            } catch (Throwable $inner) {
                $paymentChannels = [];
            }
            $amountField = '';
            $selectedMethod = 'full';
            $channelField = '';
            $referenceField = '';
            $amountPlaceholder = '5,000.00';
        } catch (Throwable $e) {
            if ($storedProofPath !== null) {
                $absoluteStoredProof = dirname(__DIR__) . '/' . $storedProofPath;
                if (is_file($absoluteStoredProof)) {
                    @unlink($absoluteStoredProof);
                }
            }

            $errors[] = $e->getMessage();
        }
    }
}

$pageTitle = 'Complete Payment';
$pageStyles = [APP_URL . '/public/assets/css/dashboard.css'];

include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php include __DIR__ . '/../includes/customer_sidebar.php'; ?>
    <div class="dashboard-content">
        <div class="page-header">
            <div>
                <h1 class="fw-bold">Complete payment</h1>
                <p class="text-muted mb-0">Finish your booking with <?= htmlspecialchars($summary['business_name']) ?>.</p>
            </div>
            <a href="<?= APP_URL ?>/customer/bookings.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-2"></i>Back to bookings</a>
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

        <?php if ($successMessage): ?>
            <div class="alert alert-success" role="alert">
                <?= htmlspecialchars($successMessage) ?>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-4">
                        <h5 class="fw-semibold mb-3">Booking summary</h5>
                        <ul class="list-unstyled mb-0">
                            <li class="mb-2"><strong>Booking ID:</strong> #<?= (int) $summary['id'] ?></li>
                            <li class="mb-2"><strong>Status:</strong> <span class="badge-status <?= htmlspecialchars($summary['status']) ?>"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $summary['status']))) ?></span></li>
                            <li class="mb-2"><strong>Event date:</strong> <?= htmlspecialchars(date('M j, Y', strtotime($summary['event_date']))) ?></li>
                            <?php if (!empty($summary['event_time'])): ?>
                                <li class="mb-2"><strong>Event time:</strong> <?= htmlspecialchars(date('g:i A', strtotime($summary['event_time']))) ?></li>
                            <?php endif; ?>
                            <li class="mb-2"><strong>Guests:</strong> <?= (int) $summary['guest_count'] ?></li>
                            <?php if (!empty($summary['package_name'])): ?>
                                <li class="mb-2"><strong>Package:</strong> <?= htmlspecialchars($summary['package_name']) ?></li>
                            <?php endif; ?>
                            <?php if ($pricePerHead !== null): ?>
                                <li class="mb-2"><strong>Price per head:</strong> ₱<?= number_format($pricePerHead, 2) ?></li>
                            <?php endif; ?>
                            <?php if ($totalAmount !== null): ?>
                                <li class="mb-2"><strong>Total estimate:</strong> ₱<?= number_format($totalAmount, 2) ?></li>
                            <?php endif; ?>
                            <?php if ($depositAmount !== null): ?>
                                <?php $depositLabel = rtrim(rtrim(number_format((float) $depositPercentage, 2), '0'), '.'); ?>
                                <li class="mb-2"><strong>Deposit due:</strong> ₱<?= number_format($depositAmount, 2) ?> <span class="text-muted">(<?= $depositLabel ?>% of total)</span></li>
                            <?php endif; ?>
                            <?php if ($pendingAmount > 0): ?>
                                <li class="mb-2"><strong>Payments awaiting confirmation:</strong> ₱<?= number_format($pendingAmount, 2) ?><?php if ($pendingPaymentCount > 0): ?> <span class="text-muted">(<?= $pendingPaymentCount === 1 ? '1 submission' : $pendingPaymentCount . ' submissions' ?>)</span><?php endif; ?></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-4">
                        <h5 class="fw-semibold mb-3">Payment details</h5>

                        <?php if (!$summary['requires_payment']): ?>
                            <p class="text-muted mb-0">This booking is already confirmed. No further payment is required.</p>
                        <?php else: ?>
                            <?php if ($hasPendingPayment): ?>
                                <div class="alert alert-info" role="alert">
                                    We received your payment submission and sent it to the caterer for review. You'll get a notification as soon as it's confirmed.
                                </div>
                            <?php endif; ?>
                            <form method="post" enctype="multipart/form-data" novalidate>
                                <fieldset <?= $hasPendingPayment ? 'disabled' : '' ?>>
                                    <div class="mb-3">
                                        <label for="payment_method" class="form-label">Payment method</label>
                                        <select name="payment_method" id="payment_method" class="form-select" required>
                                            <option value="full" <?= $selectedMethod === 'full' ? 'selected' : '' ?>>Full payment</option>
                                            <option value="deposit" <?= $selectedMethod === 'deposit' ? 'selected' : '' ?>>Partial / deposit</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label for="amount" class="form-label">Amount (₱)</label>
                                        <input
                                            type="text"
                                            inputmode="decimal"
                                            step="0.01"
                                            min="0"
                                            name="amount"
                                            id="amount"
                                            class="form-control"
                                            value="<?= htmlspecialchars($amountField) ?>"
                                            placeholder="e.g. <?= htmlspecialchars($amountPlaceholder) ?>"
                                            data-total-amount="<?= $totalAmount !== null ? htmlspecialchars(number_format($totalAmount, 2, '.', '')) : '' ?>"
                                            data-deposit-amount="<?= $depositAmount !== null ? htmlspecialchars(number_format($depositAmount, 2, '.', '')) : '' ?>"
                                        >
                                        <div class="form-text">Use the suggested amount for full or partial payments, or adjust if needed.</div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="payment_channel" class="form-label">Payment channel</label>
                                        <?php if (!empty($paymentChannels)): ?>
                                            <select
                                                name="payment_channel"
                                                id="payment_channel"
                                                class="form-select"
                                                required
                                            >
                                                <option value="">Select a payment channel</option>
                                                <?php foreach ($paymentChannels as $channel): ?>
                                                    <?php $channelValue = $channel['name']; ?>
                                                    <option
                                                        value="<?= htmlspecialchars($channelValue) ?>"
                                                        data-details="<?= htmlspecialchars($channel['details']) ?>"
                                                        <?= $channelField === $channelValue ? 'selected' : '' ?>
                                                    >
                                                        <?= htmlspecialchars($channel['name']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div id="payment_channel_info" class="card border-0 shadow-sm mt-3 d-none">
                                                <div class="card-body py-3 px-3">
                                                    <h6 class="fw-semibold mb-1" id="payment_channel_info_name"></h6>
                                                    <p class="mb-0 small text-muted" id="payment_channel_info_details" style="white-space: pre-line;"></p>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <input
                                                type="text"
                                                name="payment_channel"
                                                id="payment_channel"
                                                class="form-control"
                                                value="<?= htmlspecialchars($channelField) ?>"
                                                placeholder="e.g. Bank transfer, GCash, PayMaya"
                                                required
                                            >
                                        <?php endif; ?>
                                    </div>

                                    <div class="mb-3<?= $proofTypeField === 'image' ? ' d-none' : '' ?>" id="reference_group">
                                        <label for="reference" class="form-label">Transaction reference (optional)</label>
                                        <input
                                            type="text"
                                            name="reference"
                                            id="reference"
                                            class="form-control"
                                            value="<?= htmlspecialchars($referenceField) ?>"
                                            placeholder="e.g. bank reference number, GCash transaction ID, etc."
                                        >
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Payment proof type</label>
                                        <div class="form-check">
                                            <input
                                                class="form-check-input"
                                                type="radio"
                                                name="proof_type"
                                                id="proof_type_reference"
                                                value="reference"
                                                <?= $proofTypeField === 'image' ? '' : 'checked' ?>
                                            >
                                            <label class="form-check-label" for="proof_type_reference">
                                                Transaction reference only
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input
                                                class="form-check-input"
                                                type="radio"
                                                name="proof_type"
                                                id="proof_type_image"
                                                value="image"
                                                <?= $proofTypeField === 'image' ? 'checked' : '' ?>
                                            >
                                            <label class="form-check-label" for="proof_type_image">
                                                Attach image screenshot
                                            </label>
                                        </div>
                                        <div class="form-text">You may either enter a transaction reference, attach an image, or use both.</div>
                                    </div>

                                    <div class="mb-3<?= $proofTypeField === 'reference' ? ' d-none' : '' ?>" id="proof_image_group">
                                        <label for="payment_proof" class="form-label">Payment proof image (optional)</label>
                                        <input
                                            type="file"
                                            name="payment_proof"
                                            id="payment_proof"
                                            class="form-control"
                                            accept="image/jpeg,image/png,image/webp"
                                        >
                                        <div class="form-text">Attach a screenshot or receipt image (JPEG, PNG, or WebP, up to 5MB).</div>
                                    </div>

                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-wallet2 me-2"></i>Record payment
                                        </button>
                                    </div>
                                </fieldset>
                            </form>
                            <?php if ($hasPendingPayment): ?>
                                <p class="small text-muted mt-3 mb-0">Need to make changes? Please wait for the caterer to respond or contact them directly to clarify the submission.</p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const amountInput = document.getElementById('amount');
    const methodSelect = document.getElementById('payment_method');
    const channelSelect = document.getElementById('payment_channel');
    const channelInfo = document.getElementById('payment_channel_info');
    const channelInfoName = document.getElementById('payment_channel_info_name');
    const channelInfoDetails = document.getElementById('payment_channel_info_details');
    const proofTypeReference = document.getElementById('proof_type_reference');
    const proofTypeImage = document.getElementById('proof_type_image');
    const referenceGroup = document.getElementById('reference_group');
    const proofImageGroup = document.getElementById('proof_image_group');

    if (!amountInput) {
        return;
    }

    const stripSeparators = (value) => value.replace(/,/g, '').trim();

    const formatAmount = (value) => {
        const normalized = stripSeparators(value || '');
        if (normalized === '') {
            return '';
        }

        const numberValue = Number(normalized);
        if (Number.isNaN(numberValue)) {
            return value;
        }

        return numberValue.toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    };

    const parseDatasetNumber = (value) => {
        if (!value) {
            return NaN;
        }

        const num = Number(value);
        return Number.isNaN(num) ? NaN : num;
    };

    const totalAmount = parseDatasetNumber(amountInput.dataset.totalAmount || '');
    const depositAmount = parseDatasetNumber(amountInput.dataset.depositAmount || '');

    const updateProofVisibility = () => {
        if (!referenceGroup || !proofImageGroup) {
            return;
        }

        const selectedType = proofTypeImage && proofTypeImage.checked ? 'image' : 'reference';

        if (selectedType === 'reference') {
            referenceGroup.classList.remove('d-none');
            proofImageGroup.classList.add('d-none');
        } else {
            referenceGroup.classList.add('d-none');
            proofImageGroup.classList.remove('d-none');
        }
    };

    if (proofTypeReference) {
        proofTypeReference.addEventListener('change', updateProofVisibility);
    }

    if (proofTypeImage) {
        proofTypeImage.addEventListener('change', updateProofVisibility);
    }

    updateProofVisibility();

    const applySuggestedAmount = () => {
        if (!methodSelect) {
            return;
        }

        const method = methodSelect.value;
        let numericValue = NaN;

        if (method === 'full') {
            numericValue = totalAmount;
        } else if (method === 'deposit') {
            numericValue = depositAmount;
        }

        if (!Number.isNaN(numericValue) && numericValue > 0) {
            amountInput.value = formatAmount(String(numericValue));
        }
    };

    amountInput.addEventListener('focus', () => {
        const rawValue = amountInput.value;
        if (rawValue) {
            amountInput.value = stripSeparators(rawValue);
        }
    });

    amountInput.addEventListener('blur', () => {
        amountInput.value = formatAmount(amountInput.value);
    });

    if (methodSelect) {
        methodSelect.addEventListener('change', () => {
            applySuggestedAmount();
        });
    }

    if (amountInput.value) {
        amountInput.value = formatAmount(amountInput.value);
    } else {
        applySuggestedAmount();
    }

    const updatePaymentChannelInfo = () => {
        if (!channelSelect || !channelInfo || !channelInfoName || !channelInfoDetails) {
            return;
        }

        const selectedOption = channelSelect.options[channelSelect.selectedIndex];

        if (!selectedOption || !selectedOption.value) {
            channelInfo.classList.add('d-none');
            channelInfoName.textContent = '';
            channelInfoDetails.textContent = '';
            return;
        }

        const name = selectedOption.textContent.trim();
        const details = selectedOption.getAttribute('data-details') || '';

        channelInfoName.textContent = name;
        channelInfoDetails.textContent = details;
        channelInfo.classList.remove('d-none');
    };

    if (channelSelect) {
        channelSelect.addEventListener('change', updatePaymentChannelInfo);
        updatePaymentChannelInfo();
    }
});
</script>
