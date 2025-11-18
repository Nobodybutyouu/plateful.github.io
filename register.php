<?php
require_once __DIR__ . '/init.php';

$pageTitle = 'Sign Up';
$errors = [];
$successMessage = null;
$selectedRole = $_POST['role'] ?? 'customer';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? 'customer';

    if ($name === '') {
        $errors['name'] = 'Name is required.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'A valid email address is required.';
    } elseif (find_user_by_email($email)) {
        $errors['email'] = 'This email is already registered.';
    }

    if (strlen($password) < 6) {
        $errors['password'] = 'Password must be at least 6 characters.';
    }

    if ($password !== $confirmPassword) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }

    if ($role === 'caterer') {
        $businessName = trim($_POST['business_name'] ?? '');
        if ($businessName === '') {
            $errors['business_name'] = 'Business name is required for caterers.';
        }
    }

    if (empty($errors)) {
        try {
            if ($role === 'customer') {
                register_customer([
                    'name' => $name,
                    'email' => $email,
                    'password' => $password,
                    'phone' => trim($_POST['phone'] ?? ''),
                    'preferred_location' => trim($_POST['preferred_location'] ?? ''),
                ]);
                $successMessage = 'Account created! You can now log in.';
            } else {
                register_caterer([
                    'name' => $name,
                    'email' => $email,
                    'password' => $password,
                    'business_name' => trim($_POST['business_name'] ?? ''),
                    'description' => trim($_POST['description'] ?? ''),
                    'location' => trim($_POST['location'] ?? ''),
                    'service_area' => trim($_POST['service_area'] ?? ''),
                    'cuisine_specialties' => trim($_POST['cuisine_specialties'] ?? ''),
                    'event_types' => trim($_POST['event_types'] ?? ''),
                    'average_price' => $_POST['average_price'] !== '' ? (float) $_POST['average_price'] : null,
                ]);
                $successMessage = 'Caterer account submitted! Wait for admin approval before logging in.';
            }
        } catch (Throwable $e) {
            $errors['global'] = 'Something went wrong while creating your account. Please try again.';
        }
    }

    $selectedRole = $role;
}

include __DIR__ . '/includes/header.php';
?>

<section class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4 p-md-5">
                        <h2 class="fw-bold mb-4 text-center">Create your Plateful account</h2>
                        <?php if ($successMessage): ?>
                            <div class="alert alert-success"><?= htmlspecialchars($successMessage) ?></div>
                        <?php endif; ?>
                        <?php if (isset($errors['global'])): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($errors['global']) ?></div>
                        <?php endif; ?>

                        <form method="post" novalidate>
                            <div class="row g-3 mb-3">
                                <div class="col-12">
                                    <label class="form-label fw-semibold">I am signing up as</label>
                                    <div class="btn-group w-100" role="group">
                                        <input type="radio" class="btn-check" name="role" id="role-customer" value="customer" <?= $selectedRole === 'customer' ? 'checked' : '' ?>>
                                        <label class="btn btn-outline-primary" for="role-customer">Customer</label>
                                        <input type="radio" class="btn-check" name="role" id="role-caterer" value="caterer" <?= $selectedRole === 'caterer' ? 'checked' : '' ?>>
                                        <label class="btn btn-outline-primary" for="role-caterer">Caterer</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label for="name" class="form-label">Full Name</label>
                                    <input type="text" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" id="name" name="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
                                    <?php if (isset($errors['name'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['name']) ?></div><?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <label for="email" class="form-label">Email Address</label>
                                    <input type="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                                    <?php if (isset($errors['email'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['email']) ?></div><?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <label for="password" class="form-label">Password</label>
                                    <input type="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>" id="password" name="password" minlength="6" required>
                                    <?php if (isset($errors['password'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['password']) ?></div><?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <label for="confirm_password" class="form-label">Confirm Password</label>
                                    <input type="password" class="form-control <?= isset($errors['confirm_password']) ? 'is-invalid' : '' ?>" id="confirm_password" name="confirm_password" required>
                                    <?php if (isset($errors['confirm_password'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['confirm_password']) ?></div><?php endif; ?>
                                </div>

                                <div class="col-12 role-field role-customer" <?= $selectedRole === 'customer' ? '' : 'style="display:none;"' ?>>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label for="phone" class="form-label">Phone Number (optional)</label>
                                            <input type="text" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="preferred_location" class="form-label">Preferred Location (optional)</label>
                                            <input type="text" class="form-control" id="preferred_location" name="preferred_location" value="<?= htmlspecialchars($_POST['preferred_location'] ?? '') ?>">
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 role-field role-caterer" <?= $selectedRole === 'caterer' ? '' : 'style="display:none;"' ?>>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label for="business_name" class="form-label">Business Name</label>
                                            <input type="text" class="form-control <?= isset($errors['business_name']) ? 'is-invalid' : '' ?>" id="business_name" name="business_name" value="<?= htmlspecialchars($_POST['business_name'] ?? '') ?>">
                                            <?php if (isset($errors['business_name'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['business_name']) ?></div><?php endif; ?>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="average_price" class="form-label">Average Package Price (optional)</label>
                                            <input type="number" step="0.01" min="0" class="form-control" id="average_price" name="average_price" value="<?= htmlspecialchars($_POST['average_price'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="location" class="form-label">Primary Location</label>
                                            <input type="text" class="form-control" id="location" name="location" value="<?= htmlspecialchars($_POST['location'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="service_area" class="form-label">Service Area</label>
                                            <input type="text" class="form-control" id="service_area" name="service_area" value="<?= htmlspecialchars($_POST['service_area'] ?? '') ?>">
                                        </div>
                                        <div class="col-12">
                                            <label for="cuisine_specialties" class="form-label">Cuisine Specialties (comma-separated)</label>
                                            <input type="text" class="form-control" id="cuisine_specialties" name="cuisine_specialties" value="<?= htmlspecialchars($_POST['cuisine_specialties'] ?? '') ?>">
                                        </div>
                                        <div class="col-12">
                                            <label for="event_types" class="form-label">Event Types Served (comma-separated)</label>
                                            <input type="text" class="form-control" id="event_types" name="event_types" value="<?= htmlspecialchars($_POST['event_types'] ?? '') ?>">
                                        </div>
                                        <div class="col-12">
                                            <label for="description" class="form-label">Business Description</label>
                                            <textarea class="form-control" id="description" name="description" rows="3"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 py-2">Create Account</button>
                        </form>

                        <p class="text-center small mt-4 mb-0">Already have an account? <a href="<?= APP_URL ?>/login.php">Login here</a>.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
    const roleRadios = document.querySelectorAll('input[name="role"]');
    const roleFields = {
        customer: document.querySelector('.role-customer'),
        caterer: document.querySelector('.role-caterer')
    };

    function toggleRoleFields(role) {
        Object.entries(roleFields).forEach(([key, element]) => {
            if (!element) return;
            element.style.display = key === role ? '' : 'none';
        });
    }

    roleRadios.forEach(radio => {
        radio.addEventListener('change', (event) => {
            toggleRoleFields(event.target.value);
        });
    });

    toggleRoleFields('<?= $selectedRole ?>');
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
