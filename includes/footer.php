</main>
<footer class="py-4">
    <div class="container text-center small text-muted">
        &copy; <?= date('Y') ?> Plateful. All rights reserved.
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script>
(function () {
    const dropdown = document.getElementById('userDropdown');
    if (!dropdown) {
        return;
    }

    const pollUrl = '<?= APP_URL ?>/notifications_unread_count.php';

    function getBadge() {
        let badge = dropdown.querySelector('.badge');
        if (!badge) {
            badge = document.createElement('span');
            badge.className = 'badge bg-danger ms-2 d-none';
            dropdown.appendChild(badge);
        }
        return badge;
    }

    async function refreshNotifications() {
        try {
            const response = await fetch(pollUrl, { credentials: 'same-origin' });
            if (!response.ok) {
                return;
            }

            const data = await response.json();
            const count = typeof data.unread === 'number' ? data.unread : 0;
            const badge = getBadge();

            if (count > 0) {
                badge.textContent = String(count);
                badge.classList.remove('d-none');
            } else {
                badge.textContent = '';
                badge.classList.add('d-none');
            }
        } catch (e) {
            // Silently ignore polling errors
        }
    }

    refreshNotifications();
    setInterval(refreshNotifications, 30000);
})();
</script>
</body>
</html>
