<aside class="sidebar">
    <a href="<?= APP_URL ?>/customer/dashboard.php" class="logo">Plateful</a>
    <nav>
        <ul>
            <li><a href="<?= APP_URL ?>/customer/dashboard.php" class="<?= is_active_page('dashboard.php') ? 'active' : '' ?>"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
            <li><a href="<?= APP_URL ?>/customer/bookings.php" class="<?= is_active_page('bookings.php') ? 'active' : '' ?>"><i class="bi bi-calendar-event"></i> Bookings</a></li>
            <li><a href="<?= APP_URL ?>/customer/browse.php" class="<?= is_active_page('browse.php') ? 'active' : '' ?>"><i class="bi bi-search"></i> Browse Caterers</a></li>
            <li><a href="<?= APP_URL ?>/customer/reviews.php" class="<?= is_active_page('reviews.php') ? 'active' : '' ?>"><i class="bi bi-star"></i> Reviews & Notifications</a></li>
            <li><a href="<?= APP_URL ?>/customer/profile.php" class="<?= is_active_page('profile.php') ? 'active' : '' ?>"><i class="bi bi-person"></i> Profile</a></li>
        </ul>
    </nav>
</aside>
