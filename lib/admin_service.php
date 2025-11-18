<?php

require_once __DIR__ . '/db.php';

function get_pending_caterers(): array
{
    $db = db();

    $stmt = $db->query(
        "SELECT cp.id, cp.business_name, cp.description, cp.location, cp.service_area,
                cp.cuisine_specialties, cp.event_types, cp.created_at,
                u.name AS owner_name, u.email AS owner_email, u.id AS user_id
         FROM caterer_profiles cp
         INNER JOIN users u ON u.id = cp.user_id
         WHERE cp.approval_status = 'pending'
         ORDER BY cp.created_at ASC"
    );

    return $stmt->fetchAll();
}

function get_admin_customers(): array
{
    $db = db();

    $stmt = $db->query(
        "SELECT u.id, u.name, u.email, u.status,
                COALESCE(COUNT(b.id), 0) AS booking_count,
                MAX(b.created_at) AS last_booking_at
         FROM users u
         LEFT JOIN bookings b ON b.customer_id = u.id
         WHERE u.role = 'customer'
         GROUP BY u.id
         ORDER BY u.created_at DESC"
    );

    return $stmt->fetchAll();
}

function get_admin_caterers(): array
{
    $db = db();

    $stmt = $db->query(
        "SELECT u.id, u.name, u.email, u.status,
                cp.business_name, cp.approval_status, cp.created_at AS profile_created_at,
                COALESCE(COUNT(b.id), 0) AS booking_count
         FROM users u
         LEFT JOIN caterer_profiles cp ON cp.user_id = u.id
         LEFT JOIN bookings b ON b.caterer_profile_id = cp.id
         WHERE u.role = 'caterer'
         GROUP BY u.id
         ORDER BY cp.created_at DESC"
    );

    return $stmt->fetchAll();
}

function get_admin_bookings(int $limit = 20): array
{
    $db = db();

    $stmt = $db->prepare(
        "SELECT b.id, b.status, b.event_date, b.event_time, b.created_at,
                b.guest_count, COALESCE(et.name, 'Custom Event') AS event_type,
                customer.name AS customer_name,
                cp.business_name AS caterer_name
         FROM bookings b
         INNER JOIN users customer ON customer.id = b.customer_id
         INNER JOIN caterer_profiles cp ON cp.id = b.caterer_profile_id
         LEFT JOIN event_types et ON et.id = b.event_type_id
         ORDER BY b.created_at DESC
         LIMIT :limit"
    );
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function get_admin_dashboard_stats(): array
{
    $db = db();

    $totalUsers = (int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $totalCustomers = (int) $db->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn();
    $activeCaterers = (int) $db->query("SELECT COUNT(*) FROM users WHERE role = 'caterer' AND status = 'active'")->fetchColumn();
    $pendingCaterers = (int) $db->query("SELECT COUNT(*) FROM caterer_profiles WHERE approval_status = 'pending'")->fetchColumn();
    $activeBookings = (int) $db->query("SELECT COUNT(*) FROM bookings WHERE status IN ('pending', 'approved')")->fetchColumn();
    $pendingBookings = (int) $db->query("SELECT COUNT(*) FROM bookings WHERE status = 'pending'")->fetchColumn();
    $bookingsLast7Days = (int) $db->query("SELECT COUNT(*) FROM bookings WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)")->fetchColumn();

    return [
        'total_users' => $totalUsers,
        'total_customers' => $totalCustomers,
        'active_caterers' => $activeCaterers,
        'pending_caterers' => $pendingCaterers,
        'active_bookings' => $activeBookings,
        'pending_bookings' => $pendingBookings,
        'bookings_last_7_days' => $bookingsLast7Days,
    ];
}

function get_recent_platform_bookings(int $limit = 5): array
{
    $db = db();

    $stmt = $db->prepare(
        "SELECT b.id, b.status, b.event_date, b.created_at,
                customer.name AS customer_name,
                cp.business_name AS caterer_name
         FROM bookings b
         INNER JOIN users customer ON customer.id = b.customer_id
         INNER JOIN caterer_profiles cp ON cp.id = b.caterer_profile_id
         ORDER BY b.created_at DESC
         LIMIT :limit"
    );
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function get_recent_caterer_decisions(int $limit = 5): array
{
    $db = db();

    $stmt = $db->prepare(
        "SELECT cp.business_name, u.name AS owner_name, cp.approval_status, cp.updated_at
         FROM caterer_profiles cp
         INNER JOIN users u ON u.id = cp.user_id
         WHERE cp.approval_status IN ('approved', 'rejected')
         ORDER BY cp.updated_at DESC
         LIMIT :limit"
    );
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function update_caterer_approval(int $profileId, string $status): void
{
    $status = strtolower($status);
    $allowed = ['approved', 'rejected'];

    if (!in_array($status, $allowed, true)) {
        throw new InvalidArgumentException('Invalid approval status provided.');
    }

    $db = db();

    try {
        $db->beginTransaction();

        $profileStmt = $db->prepare(
            'SELECT user_id, business_name FROM caterer_profiles WHERE id = :id FOR UPDATE'
        );
        $profileStmt->execute(['id' => $profileId]);
        $profile = $profileStmt->fetch();

        if (!$profile) {
            throw new RuntimeException('Caterer profile not found.');
        }

        $userId = (int) $profile['user_id'];
        $businessName = $profile['business_name'];

        $updateProfile = $db->prepare(
            'UPDATE caterer_profiles SET approval_status = :status, updated_at = NOW() WHERE id = :id'
        );
        $updateProfile->execute([
            'status' => $status,
            'id' => $profileId,
        ]);

        $userStatus = $status === 'approved' ? 'active' : 'disabled';
        $updateUser = $db->prepare('UPDATE users SET status = :status WHERE id = :id');
        $updateUser->execute([
            'status' => $userStatus,
            'id' => $userId,
        ]);

        $message = $status === 'approved'
            ? 'Your caterer profile has been approved! You can now accept bookings on Plateful.'
            : 'Unfortunately your caterer application was not approved at this time. Please contact support for more details.';

        $notification = $db->prepare(
            'INSERT INTO notifications (user_id, type, title, message) VALUES (:user_id, :type, :title, :message)'
        );
        $notification->execute([
            'user_id' => $userId,
            'type' => 'caterer_approval',
            'title' => $status === 'approved' ? 'Caterer profile approved' : 'Caterer profile decision',
            'message' => $message,
        ]);

        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}
