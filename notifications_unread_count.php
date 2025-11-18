<?php
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';

$user = $_SESSION['user'] ?? null;

if (!$user) {
    json_response(['unread' => 0], 200);
}

try {
    $userId = (int) ($user['id'] ?? 0);
    $role = $user['role'] ?? null;

    $unread = 0;

    if ($userId > 0) {
        if ($role === 'customer') {
            require_once __DIR__ . '/lib/customer_service.php';
            $data = get_customer_dashboard_data($userId);
            $unread = (int) ($data['counts']['notifications'] ?? 0);
        } else {
            $stmt = db()->prepare(
                'SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0'
            );
            $stmt->execute(['user_id' => $userId]);
            $unread = (int) $stmt->fetchColumn();
        }
    }

    json_response(['unread' => $unread], 200);
} catch (Throwable $e) {
    json_response(['unread' => 0], 500);
}
