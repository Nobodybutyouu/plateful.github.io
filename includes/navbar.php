<?php
$currentUser = $_SESSION['user'] ?? null;
$currentRole = $currentUser['role'] ?? null;
$unreadCount = 0;

if ($currentUser && $currentRole === 'customer') {
    try {
        require_once __DIR__ . '/../lib/customer_service.php';
        $data = get_customer_dashboard_data((int) $currentUser['id']);
        $unreadCount = (int) ($data['counts']['notifications'] ?? 0);
    } catch (Throwable $e) {
        $unreadCount = 0;
    }
}
?>
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
    <div class="container">
        <a class="navbar-brand fw-bold text-primary" href="/plateful_web-app/index.php">Plateful</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto align-items-lg-center">
                <li class="nav-item">
                    <a class="nav-link" href="/plateful_web-app/index.php#features">Features</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/plateful_web-app/index.php#how-it-works">How it works</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/plateful_web-app/index.php#roles">Roles</a>
                </li>
                <?php if ($currentUser): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <?= htmlspecialchars($currentUser['name'] ?? 'Account') ?>
                            <?php if ($unreadCount > 0): ?>
                                <span class="badge bg-danger ms-2"><?= $unreadCount ?></span>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="/plateful_web-app/dashboard.php">Dashboard</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="/plateful_web-app/logout.php">Logout</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item ms-lg-3">
                        <a class="btn btn-outline-primary me-lg-2 mb-2 mb-lg-0" href="/plateful_web-app/login.php">Login</a>
                        <a class="btn btn-primary" href="/plateful_web-app/register.php">Sign Up</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
