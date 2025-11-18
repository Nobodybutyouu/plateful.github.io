<aside class="sidebar">
    <a href="<?= APP_URL ?>/admin/dashboard.php" class="logo">Plateful</a>
    <nav>
        <ul>
            <li><a href="<?= APP_URL ?>/admin/dashboard.php" class="<?= is_active_page('dashboard.php') ? 'active' : '' ?>"><i class="bi bi-grid"></i> Dashboard</a></li>
            <li><a href="<?= APP_URL ?>/admin/users.php" class="<?= is_active_page('users.php') ? 'active' : '' ?>"><i class="bi bi-people"></i> User Management</a></li>
            <li><a href="<?= APP_URL ?>/admin/caterer-approvals.php" class="<?= is_active_page('caterer-approvals.php') ? 'active' : '' ?>"><i class="bi bi-check2-square"></i> Caterer Approvals</a></li>
            <li><a href="<?= APP_URL ?>/admin/bookings.php" class="<?= is_active_page('bookings.php') ? 'active' : '' ?>"><i class="bi bi-calendar2-event"></i> Bookings</a></li>
            <li><a href="<?= APP_URL ?>/admin/categories.php" class="<?= is_active_page('categories.php') ? 'active' : '' ?>"><i class="bi bi-tags"></i> Categories</a></li>
            <li><a href="<?= APP_URL ?>/admin/notifications.php" class="<?= is_active_page('notifications.php') ? 'active' : '' ?>"><i class="bi bi-bell"></i> Notifications</a></li>
            <li><a href="<?= APP_URL ?>/admin/settings.php" class="<?= is_active_page('settings.php') ? 'active' : '' ?>"><i class="bi bi-gear"></i> Settings</a></li>
        </ul>
    </nav>
</aside>
