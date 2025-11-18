<aside class="sidebar">
    <a href="<?= APP_URL ?>/caterer/dashboard.php" class="logo">Plateful</a>
    <nav>
        <ul>
            <li><a href="<?= APP_URL ?>/caterer/dashboard.php" class="<?= is_active_page('dashboard.php') ? 'active' : '' ?>"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
            <li><a href="<?= APP_URL ?>/caterer/bookings.php" class="<?= is_active_page(['bookings.php', 'booking-details.php']) ? 'active' : '' ?>"><i class="bi bi-calendar-event"></i> Bookings</a></li>
            <li><a href="<?= APP_URL ?>/caterer/menu.php" class="<?= is_active_page(['menu.php', 'package-edit.php']) ? 'active' : '' ?>"><i class="bi bi-list-ul"></i> Menu &amp; Packages</a></li>
            <li><a href="<?= APP_URL ?>/caterer/notifications.php" class="<?= is_active_page('notifications.php') ? 'active' : '' ?>"><i class="bi bi-bell"></i> Notifications</a></li>
            <li><a href="<?= APP_URL ?>/caterer/profile.php" class="<?= is_active_page('profile.php') ? 'active' : '' ?>"><i class="bi bi-building"></i> Business Profile</a></li>
            <li><a href="<?= APP_URL ?>/caterer/settings.php" class="<?= is_active_page('settings.php') ? 'active' : '' ?>"><i class="bi bi-gear"></i> Settings</a></li>
        </ul>
    </nav>
</aside>
