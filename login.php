<?php
require_once __DIR__ . '/init.php';

if (is_authenticated()) {
    redirect_after_login();
}

$pageTitle = 'Login';
$error = null;
$emailValue = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emailValue = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($emailValue === '' || $password === '') {
        $error = 'Email and password are required.';
    } elseif (!attempt_login($emailValue, $password)) {
        $error = 'Invalid credentials or account not yet approved.';
    } else {
        redirect_after_login();
    }
}

include __DIR__ . '/includes/header.php';
?>

<section class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4 p-md-5">
                        <h2 class="fw-bold mb-4 text-center">Welcome back</h2>
                        <?php if ($error): ?>
                            <div class="alert alert-danger small"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>
                        <form method="post" novalidate>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email address</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($emailValue) ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Log in</button>
                        </form>
                        <p class="text-center small mt-4 mb-0">Don&#39;t have an account yet? <a href="<?= APP_URL ?>/register.php">Create one</a>.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
