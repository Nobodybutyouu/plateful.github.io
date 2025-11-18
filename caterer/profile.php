<?php
require_once __DIR__ . '/../init.php';
require_auth(['caterer']);
require_once __DIR__ . '/../lib/caterer_service.php';

$user = current_user();

$pageTitle = 'Business Profile';
$pageStyles = [APP_URL . '/public/assets/css/dashboard.css'];

$profile = get_caterer_profile($user['id']);

if (!$profile) {
    redirect('/caterer/pending.php');
}

$profileId = (int) $profile['id'];

$profile['business_name'] = $profile['business_name'] ?? '';
$profile['description'] = $profile['description'] ?? '';
$profile['location'] = $profile['location'] ?? '';
$profile['service_area'] = $profile['service_area'] ?? '';

$specializations = [];
if (!empty($profile['cuisine_specialties'])) {
    $decoded = json_decode($profile['cuisine_specialties'], true);
    if (is_array($decoded)) {
        $specializations = array_filter(array_map('trim', $decoded));
    } else {
        $specializations = array_filter(array_map('trim', explode(',', $profile['cuisine_specialties'])));
    }
}

$eventTypes = [];
if (!empty($profile['event_types'])) {
    $decoded = json_decode($profile['event_types'], true);
    if (is_array($decoded)) {
        $eventTypes = array_filter(array_map('trim', $decoded));
    } else {
        $eventTypes = array_filter(array_map('trim', explode(',', $profile['event_types'])));
    }
}

$specializationsValue = !empty($specializations) ? implode(', ', $specializations) : '';
$eventTypesValue = !empty($eventTypes) ? implode(', ', $eventTypes) : '';

$galleryErrors = [];
$gallerySuccess = null;
$gallery = get_caterer_gallery_photos($profileId);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_gallery'])) {
    $uploads = $_FILES['gallery_photos'] ?? null;

    $files = [];
    if ($uploads && is_array($uploads['name'])) {
        foreach ($uploads['name'] as $index => $name) {
            $files[] = [
                'name' => $name,
                'type' => $uploads['type'][$index] ?? '',
                'tmp_name' => $uploads['tmp_name'][$index] ?? '',
                'error' => $uploads['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                'size' => $uploads['size'][$index] ?? 0,
            ];
        }
    } elseif ($uploads) {
        $files[] = $uploads;
    }

    $processed = 0;
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $maxFileSize = 5 * 1024 * 1024;
    $targetDir = rtrim(UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR . 'gallery';

    foreach ($files as $file) {
        $originalName = trim((string) ($file['name'] ?? ''));

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            $galleryErrors[] = $originalName !== ''
                ? sprintf('Could not upload %s due to an unexpected error.', $originalName)
                : 'One of the selected files could not be uploaded due to an unexpected error.';
            continue;
        }

        if (($file['size'] ?? 0) > $maxFileSize) {
            $galleryErrors[] = $originalName !== ''
                ? sprintf('%s is larger than 5MB.', $originalName)
                : 'One of the selected files is larger than 5MB.';
            continue;
        }

        if (!is_uploaded_file($file['tmp_name'] ?? '')) {
            $galleryErrors[] = $originalName !== ''
                ? sprintf('%s failed validation. Please try again.', $originalName)
                : 'One of the selected files failed validation. Please try again.';
            continue;
        }

        $mimeType = $finfo->file($file['tmp_name']);
        if (!in_array($mimeType, ALLOWED_IMAGE_TYPES, true)) {
            $galleryErrors[] = $originalName !== ''
                ? sprintf('%s has an unsupported image format.', $originalName)
                : 'One of the selected files has an unsupported image format.';
            continue;
        }

        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            $galleryErrors[] = 'Unable to prepare storage for gallery uploads. Please try again later.';
            break;
        }

        $extensionMap = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
        $extension = $extensionMap[$mimeType] ?? pathinfo($originalName, PATHINFO_EXTENSION) ?: 'dat';

        try {
            $filename = sprintf('caterer-%d-%s.%s', $profileId, bin2hex(random_bytes(6)), $extension);
        } catch (Throwable $e) {
            $galleryErrors[] = 'Unable to generate a secure file name for an upload. Please try again.';
            break;
        }

        $absolutePath = $targetDir . DIRECTORY_SEPARATOR . $filename;

        if (!move_uploaded_file($file['tmp_name'], $absolutePath)) {
            $galleryErrors[] = $originalName !== ''
                ? sprintf('Failed to save %s. Please try again.', $originalName)
                : 'Failed to save one of the selected files. Please try again.';
            continue;
        }

        $relativePath = 'storage/uploads/gallery/' . $filename;

        try {
            add_caterer_gallery_photo($profileId, $relativePath);
            $processed++;
        } catch (Throwable $e) {
            @unlink($absolutePath);
            $galleryErrors[] = $originalName !== ''
                ? sprintf('Failed to record %s in the gallery.', $originalName)
                : 'Failed to record one of the uploaded files in the gallery.';
        }
    }

    if ($processed > 0) {
        $gallerySuccess = $processed === 1
            ? 'Photo uploaded successfully.'
            : sprintf('%d photos uploaded successfully.', $processed);
    } elseif (empty($galleryErrors)) {
        $galleryErrors[] = 'Select at least one image to upload.';
    }

    $gallery = get_caterer_gallery_photos($profileId);
}

include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php include __DIR__ . '/../includes/caterer_sidebar.php'; ?>

    <div class="dashboard-content">
        <div class="page-header">
            <div>
                <h1 class="fw-bold">Business profile</h1>
                <p class="text-muted">Keep your details updated so customers know what makes you stand out.</p>
            </div>
            <button class="btn btn-outline-primary"><i class="bi bi-eye me-2"></i>Preview public profile</button>
        </div>

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <h5 class="fw-semibold mb-3">About your business</h5>
                        <form>
                            <div class="mb-3">
                                <label class="form-label">Business name</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($profile['business_name']) ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" rows="4"><?= htmlspecialchars($profile['description']) ?></textarea>
                                <small class="text-muted">Highlight your signature dishes, awards, and service style.</small>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Primary location</label>
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($profile['location']) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Service areas</label>
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($profile['service_area']) ?>">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary mt-3">Save profile</button>
                        </form>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <h5 class="fw-semibold mb-3">Specializations</h5>
                        <form>
                            <div class="mb-3">
                                <label class="form-label">Cuisine specialties</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($specializationsValue) ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Event types served</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($eventTypesValue) ?>">
                            </div>
                            <button type="submit" class="btn btn-outline-primary">Update specializations</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <h5 class="fw-semibold mb-3">Profile completeness</h5>
                        <div class="mb-3">
                            <div class="progress" style="height: 12px;">
                                <div class="progress-bar bg-primary" role="progressbar" style="width: 76%;" aria-valuenow="76" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <small class="text-muted">Great progress! Add recent event photos to reach 100%.</small>
                        </div>
                        <ul class="list-group list-group-flush small">
                            <li class="list-group-item d-flex align-items-center">
                                <i class="bi bi-check2-circle text-success me-2"></i>
                                Business description
                            </li>
                            <li class="list-group-item d-flex align-items-center">
                                <i class="bi bi-check2-circle text-success me-2"></i>
                                Service area
                            </li>
                            <li class="list-group-item d-flex align-items-center">
                                <i class="bi bi-circle text-secondary me-2"></i>
                                Updated gallery
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <h5 class="fw-semibold mb-3">Gallery</h5>

                        <?php if ($gallerySuccess): ?>
                            <div class="alert alert-success small" role="alert">
                                <?= htmlspecialchars($gallerySuccess) ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($galleryErrors)): ?>
                            <div class="alert alert-danger small" role="alert">
                                <ul class="mb-0 ps-3">
                                    <?php foreach ($galleryErrors as $error): ?>
                                        <li><?= htmlspecialchars($error) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <div class="row g-2 mb-3">
                            <?php if (!empty($gallery)): ?>
                                <?php foreach ($gallery as $photo): ?>
                                    <?php $photoUrl = APP_URL . '/' . ltrim($photo['file_path'], '/'); ?>
                                    <div class="col-4">
                                        <div class="ratio ratio-1x1 rounded overflow-hidden border">
                                            <img src="<?= htmlspecialchars($photoUrl) ?>" alt="Gallery photo" class="img-fluid object-fit-cover w-100 h-100">
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="col-12">
                                    <div class="border border-dashed rounded py-4 text-center text-muted small">
                                        No gallery photos uploaded yet.
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <form method="post" enctype="multipart/form-data" class="border rounded p-3 bg-light-subtle">
                            <input type="hidden" name="upload_gallery" value="1">
                            <div class="mb-2">
                                <label for="gallery_photos" class="form-label small fw-semibold mb-1">Add new photos</label>
                                <input type="file" class="form-control form-control-sm" id="gallery_photos" name="gallery_photos[]" accept="image/jpeg,image/png,image/webp" multiple>
                                <div class="form-text">JPEG, PNG, or WebP up to 5MB each. You can select multiple files.</div>
                            </div>
                            <button type="submit" class="btn btn-outline-secondary btn-sm w-100"><i class="bi bi-upload me-2"></i>Upload photos</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
