<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

function get_caterer_notification_preferences(int $userId): array
{
    $defaults = [
        'email_new_request' => true,
        'client_updates' => true,
        'weekly_review_digest' => false,
    ];

    $stmt = db()->prepare(
        'SELECT email_new_request, client_updates, weekly_review_digest
         FROM caterer_notification_preferences
         WHERE user_id = :user_id
         LIMIT 1'
    );
    $stmt->execute(['user_id' => $userId]);

    $row = $stmt->fetch();

    if (!$row) {
        return $defaults;
    }

    return [
        'email_new_request' => (bool) $row['email_new_request'],
        'client_updates' => (bool) $row['client_updates'],
        'weekly_review_digest' => (bool) $row['weekly_review_digest'],
    ];
}

function update_caterer_notification_preferences(int $userId, bool $emailNewRequest, bool $clientUpdates, bool $weeklyReviewDigest): void
{
    $stmt = db()->prepare(
        'INSERT INTO caterer_notification_preferences (user_id, email_new_request, client_updates, weekly_review_digest)
         VALUES (:user_id, :email_new_request, :client_updates, :weekly_review_digest)
         ON DUPLICATE KEY UPDATE
            email_new_request = VALUES(email_new_request),
            client_updates = VALUES(client_updates),
            weekly_review_digest = VALUES(weekly_review_digest),
            updated_at = CURRENT_TIMESTAMP'
    );

    $stmt->execute([
        'user_id' => $userId,
        'email_new_request' => $emailNewRequest ? 1 : 0,
        'client_updates' => $clientUpdates ? 1 : 0,
        'weekly_review_digest' => $weeklyReviewDigest ? 1 : 0,
    ]);
}

function get_caterer_subscription(int $userId): ?array
{
    $stmt = db()->prepare(
        'SELECT plan, price, started_at, renews_at
         FROM caterer_subscriptions
         WHERE user_id = :user_id
         LIMIT 1'
    );
    $stmt->execute(['user_id' => $userId]);

    $row = $stmt->fetch();

    if (!$row) {
        return null;
    }

    return [
        'plan' => $row['plan'],
        'price' => (float) $row['price'],
        'started_at' => $row['started_at'],
        'renews_at' => $row['renews_at'],
    ];
}

function upsert_caterer_subscription(int $userId, string $plan): void
{
    $plan = strtolower(trim($plan));
    $allowedPlans = ['monthly' => 329.00, 'yearly' => 3500.00];

    if (!array_key_exists($plan, $allowedPlans)) {
        throw new InvalidArgumentException('Invalid subscription plan.');
    }

    $price = $allowedPlans[$plan];
    $now = new DateTimeImmutable('now');
    $renewsAt = $plan === 'monthly'
        ? $now->modify('+1 month')->format('Y-m-d')
        : $now->modify('+1 year')->format('Y-m-d');

    $stmt = db()->prepare(
        'INSERT INTO caterer_subscriptions (user_id, plan, price, renews_at)
         VALUES (:user_id, :plan, :price, :renews_at)
         ON DUPLICATE KEY UPDATE
            plan = VALUES(plan),
            price = VALUES(price),
            renews_at = VALUES(renews_at),
            started_at = IF(plan <> VALUES(plan), CURRENT_TIMESTAMP, started_at),
            updated_at = CURRENT_TIMESTAMP'
    );

    $stmt->execute([
        'user_id' => $userId,
        'plan' => $plan,
        'price' => $price,
        'renews_at' => $renewsAt,
    ]);
}

function get_caterer_profile(int $userId): ?array
{
    $db = db();

    $stmt = $db->prepare(
        'SELECT cp.id, cp.business_name, cp.description, cp.location,
                cp.service_area, cp.cuisine_specialties, cp.event_types,
                cp.average_price, cp.approval_status, cp.availability_status
         FROM caterer_profiles cp
         WHERE cp.user_id = :user_id
         LIMIT 1'
    );
    $stmt->execute(['user_id' => $userId]);

    return $stmt->fetch() ?: null;
}

function get_caterer_gallery_photos(int $profileId): array
{
    $stmt = db()->prepare(
        'SELECT id, file_path, created_at
         FROM caterer_gallery_photos
         WHERE caterer_profile_id = :profile_id
         ORDER BY created_at DESC'
    );
    $stmt->execute(['profile_id' => $profileId]);

    return array_map(static function (array $row): array {
        return [
            'id' => (int) $row['id'],
            'file_path' => $row['file_path'],
            'created_at' => $row['created_at'],
        ];
    }, $stmt->fetchAll());
}

function add_caterer_gallery_photo(int $profileId, string $filePath): int
{
    $stmt = db()->prepare(
        'INSERT INTO caterer_gallery_photos (caterer_profile_id, file_path)
         VALUES (:profile_id, :file_path)'
    );
    $stmt->execute([
        'profile_id' => $profileId,
        'file_path' => $filePath,
    ]);

    return (int) db()->lastInsertId();
}

function get_caterer_payment_channels(int $profileId): array
{
    $stmt = db()->prepare(
        'SELECT id, name, details, created_at, updated_at
         FROM caterer_payment_channels
         WHERE caterer_profile_id = :profile_id
         ORDER BY name ASC'
    );
    $stmt->execute(['profile_id' => $profileId]);

    return array_map(static function (array $row): array {
        return [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'details' => $row['details'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
        ];
    }, $stmt->fetchAll());
}

function create_caterer_payment_channel(int $profileId, string $name, string $details): int
{
    $name = trim($name);
    $details = trim($details);

    if ($name === '' || $details === '') {
        throw new InvalidArgumentException('Payment channel name and details are required.');
    }

    $stmt = db()->prepare(
        'INSERT INTO caterer_payment_channels (caterer_profile_id, name, details)
         VALUES (:profile_id, :name, :details)'
    );
    $stmt->execute([
        'profile_id' => $profileId,
        'name' => $name,
        'details' => $details,
    ]);

    return (int) db()->lastInsertId();
}

function update_caterer_payment_channel(int $channelId, int $profileId, string $name, string $details): void
{
    $name = trim($name);
    $details = trim($details);

    if ($name === '' || $details === '') {
        throw new InvalidArgumentException('Payment channel name and details are required.');
    }

    $stmt = db()->prepare(
        'UPDATE caterer_payment_channels
         SET name = :name,
             details = :details,
             updated_at = CURRENT_TIMESTAMP
         WHERE id = :id AND caterer_profile_id = :profile_id'
    );

    $stmt->execute([
        'name' => $name,
        'details' => $details,
        'id' => $channelId,
        'profile_id' => $profileId,
    ]);

    if ($stmt->rowCount() === 0) {
        throw new RuntimeException('Payment channel not found.');
    }
}

function get_caterer_menu_items(int $profileId): array
{
    $stmt = db()->prepare(
        'SELECT id, category, name, description
         FROM caterer_menu_items
         WHERE caterer_profile_id = :profile_id
         ORDER BY sort_order ASC, category ASC, name ASC'
    );
    $stmt->execute(['profile_id' => $profileId]);

    return array_map(static function (array $row): array {
        $row['id'] = (int) $row['id'];

        return $row;
    }, $stmt->fetchAll());
}

function replace_caterer_menu_items(int $profileId, array $items): void
{
    $db = db();

    try {
        $db->beginTransaction();

        $deleteStmt = $db->prepare('DELETE FROM caterer_menu_items WHERE caterer_profile_id = :profile_id');
        $deleteStmt->execute(['profile_id' => $profileId]);

        if (!empty($items)) {
            $insertStmt = $db->prepare(
                'INSERT INTO caterer_menu_items (caterer_profile_id, category, name, description, sort_order)
                 VALUES (:profile_id, :category, :name, :description, :sort_order)'
            );

            $sortOrder = 0;

            foreach ($items as $item) {
                $category = isset($item['category']) ? trim((string) $item['category']) : '';
                $name = isset($item['name']) ? trim((string) $item['name']) : '';
                $description = isset($item['description']) && $item['description'] !== ''
                    ? (string) $item['description']
                    : null;

                if ($category === '' || $name === '') {
                    continue;
                }

                $insertStmt->execute([
                    'profile_id' => $profileId,
                    'category' => $category,
                    'name' => $name,
                    'description' => $description,
                    'sort_order' => $sortOrder++,
                ]);
            }
        }

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $e;
    }
}

function delete_caterer_payment_channel(int $channelId, int $profileId): void
{
    $stmt = db()->prepare(
        'DELETE FROM caterer_payment_channels
         WHERE id = :id AND caterer_profile_id = :profile_id'
    );
    $stmt->execute([
        'id' => $channelId,
        'profile_id' => $profileId,
    ]);

    if ($stmt->rowCount() === 0) {
        throw new RuntimeException('Payment channel not found.');
    }
}

function get_caterer_packages(int $profileId): array
{
    $stmt = db()->prepare(
        'SELECT id, name, price, deposit_percentage, package_type, description, inclusions, is_active, created_at, updated_at
         FROM packages
         WHERE caterer_profile_id = :profile_id
         ORDER BY is_active DESC, name ASC'
    );
    $stmt->execute(['profile_id' => $profileId]);

    return array_map(static function (array $row): array {
        $row['id'] = (int) $row['id'];
        $row['price'] = (float) $row['price'];
        $row['deposit_percentage'] = $row['deposit_percentage'] !== null ? (float) $row['deposit_percentage'] : null;
        $row['is_active'] = (bool) $row['is_active'];

        return $row;
    }, $stmt->fetchAll());
}

function get_package_items(int $packageId): array
{
    $stmt = db()->prepare(
        'SELECT id, item_type, name, price
         FROM package_items
         WHERE package_id = :package_id
         ORDER BY id ASC'
    );
    $stmt->execute(['package_id' => $packageId]);

    return array_map(static function (array $row): array {
        $row['id'] = (int) $row['id'];
        $row['price'] = $row['price'] !== null ? (float) $row['price'] : null;

        return $row;
    }, $stmt->fetchAll());
}

function replace_package_items(int $packageId, array $items): void
{
    $db = db();

    try {
        $db->beginTransaction();

        $deleteStmt = $db->prepare('DELETE FROM package_items WHERE package_id = :package_id');
        $deleteStmt->execute(['package_id' => $packageId]);

        if (!empty($items)) {
            $insertStmt = $db->prepare(
                'INSERT INTO package_items (package_id, item_type, name, price)
                 VALUES (:package_id, :item_type, :name, :price)'
            );

            foreach ($items as $item) {
                $itemType = $item['item_type'] ?? null;
                $name = isset($item['name']) ? trim((string) $item['name']) : '';
                $price = $item['price'] ?? null;

                if ($name === '') {
                    continue;
                }

                if (!in_array($itemType, ['maincourse', 'service', 'addon'], true)) {
                    throw new InvalidArgumentException('Invalid package item type.');
                }

                $insertStmt->execute([
                    'package_id' => $packageId,
                    'item_type' => $itemType,
                    'name' => $name,
                    'price' => $price !== null ? (float) $price : null,
                ]);
            }
        }

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $e;
    }
}

function create_caterer_package(
    int $profileId,
    string $name,
    float $price,
    ?float $depositPercentage,
    string $packageType,
    ?string $description,
    ?string $inclusions,
    bool $isActive
): int {
    if ($depositPercentage !== null && ($depositPercentage < 0 || $depositPercentage > 100)) {
        throw new InvalidArgumentException('Deposit percentage must be between 0 and 100.');
    }

    $packageTypeNormalized = strtolower(trim($packageType));
    $allowedTypes = ['food', 'services', 'full'];

    if (!in_array($packageTypeNormalized, $allowedTypes, true)) {
        throw new InvalidArgumentException('Invalid package type.');
    }

    $stmt = db()->prepare(
        'INSERT INTO packages (caterer_profile_id, name, price, deposit_percentage, package_type, description, inclusions, is_active)
         VALUES (:profile_id, :name, :price, :deposit_percentage, :package_type, :description, :inclusions, :is_active)'
    );
    $stmt->execute([
        'profile_id' => $profileId,
        'name' => $name,
        'price' => $price,
        'deposit_percentage' => $depositPercentage,
        'package_type' => $packageTypeNormalized,
        'description' => $description,
        'inclusions' => $inclusions,
        'is_active' => $isActive ? 1 : 0,
    ]);

    return (int) db()->lastInsertId();
}

function update_caterer_package(
    int $packageId,
    int $profileId,
    string $name,
    float $price,
    ?float $depositPercentage,
    string $packageType,
    ?string $description,
    ?string $inclusions,
    bool $isActive
): void {
    if ($depositPercentage !== null && ($depositPercentage < 0 || $depositPercentage > 100)) {
        throw new InvalidArgumentException('Deposit percentage must be between 0 and 100.');
    }

    $packageTypeNormalized = strtolower(trim($packageType));
    $allowedTypes = ['food', 'services', 'full'];

    if (!in_array($packageTypeNormalized, $allowedTypes, true)) {
        throw new InvalidArgumentException('Invalid package type.');
    }

    $stmt = db()->prepare(
        'UPDATE packages
         SET name = :name,
             price = :price,
             deposit_percentage = :deposit_percentage,
             package_type = :package_type,
             description = :description,
             inclusions = :inclusions,
             is_active = :is_active
         WHERE id = :id AND caterer_profile_id = :profile_id'
    );

    $stmt->execute([
        'name' => $name,
        'price' => $price,
        'deposit_percentage' => $depositPercentage,
        'package_type' => $packageTypeNormalized,
        'description' => $description,
        'inclusions' => $inclusions,
        'is_active' => $isActive ? 1 : 0,
        'id' => $packageId,
        'profile_id' => $profileId,
    ]);

    if ($stmt->rowCount() === 0) {
        throw new RuntimeException('Package not found.');
    }
}

function set_caterer_availability_status(int $profileId, int $userId, string $status): void
{
    $status = strtolower(trim($status));
    $allowedStatuses = ['available', 'unavailable'];

    if (!in_array($status, $allowedStatuses, true)) {
        throw new InvalidArgumentException('Invalid availability status.');
    }

    $stmt = db()->prepare(
        'UPDATE caterer_profiles
         SET availability_status = :status, updated_at = NOW()
         WHERE id = :id AND user_id = :user_id'
    );

    $stmt->execute([
        'status' => $status,
        'id' => $profileId,
        'user_id' => $userId,
    ]);

    if ($stmt->rowCount() === 0) {
        throw new RuntimeException('Unable to update availability.');
    }
}

function delete_caterer_package(int $packageId, int $profileId): void
{
    $stmt = db()->prepare('DELETE FROM packages WHERE id = :id AND caterer_profile_id = :profile_id');
    $stmt->execute([
        'id' => $packageId,
        'profile_id' => $profileId,
    ]);

    if ($stmt->rowCount() === 0) {
        throw new RuntimeException('Package not found.');
    }
}

function set_caterer_package_status(int $packageId, int $profileId, bool $isActive): void
{
    $stmt = db()->prepare(
        'UPDATE packages SET is_active = :is_active WHERE id = :id AND caterer_profile_id = :profile_id'
    );
    $stmt->execute([
        'is_active' => $isActive ? 1 : 0,
        'id' => $packageId,
        'profile_id' => $profileId,
    ]);

    if ($stmt->rowCount() === 0) {
        throw new RuntimeException('Package not found.');
    }
}

function get_caterer_dashboard_stats(int $profileId): array
{
    $db = db();

    $countsStmt = $db->prepare(
        "SELECT
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending,
            SUM(CASE WHEN status IN ('awaiting_payment', 'confirmed') THEN 1 ELSE 0 END) AS approved,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed
         FROM bookings
         WHERE caterer_profile_id = :profile_id"
    );
    $countsStmt->execute(['profile_id' => $profileId]);
    $counts = $countsStmt->fetch() ?: ['pending' => 0, 'approved' => 0, 'completed' => 0];

    $recentStmt = $db->prepare(
        "SELECT b.id, b.event_date, b.event_time, b.status, b.custom_request,
                b.guest_count, COALESCE(et.name, 'Custom Event') AS event_type,
                u.name AS customer_name
         FROM bookings b
         INNER JOIN users u ON u.id = b.customer_id
         LEFT JOIN event_types et ON et.id = b.event_type_id
         WHERE b.caterer_profile_id = :profile_id
         ORDER BY b.event_date ASC, b.created_at DESC
         LIMIT 5"
    );
    $recentStmt->execute(['profile_id' => $profileId]);

    return [
        'counts' => [
            'pending' => (int) ($counts['pending'] ?? 0),
            'approved' => (int) ($counts['approved'] ?? 0),
            'completed' => (int) ($counts['completed'] ?? 0),
        ],
        'recent_bookings' => $recentStmt->fetchAll(),
    ];
}

function get_caterer_bookings(int $profileId): array
{
    $db = db();

    $pendingStmt = $db->prepare(
        "SELECT b.id, b.event_date, b.event_time, b.guest_count, b.custom_request,
                b.status,
                COALESCE(et.name, 'Custom Event') AS event_type,
                u.name AS customer_name, u.email AS customer_email,
                p.name AS package_name
         FROM bookings b
         INNER JOIN users u ON u.id = b.customer_id
         LEFT JOIN event_types et ON et.id = b.event_type_id
         LEFT JOIN packages p ON p.id = b.package_id
         WHERE b.caterer_profile_id = :profile_id AND b.status = 'pending'
         ORDER BY b.event_date ASC, b.created_at ASC"
    );
    $pendingStmt->execute(['profile_id' => $profileId]);

    $approvedStmt = $db->prepare(
        "SELECT b.id, b.event_date, b.event_time, b.guest_count, b.custom_request,
                b.status,
                COALESCE(et.name, 'Custom Event') AS event_type,
                u.name AS customer_name, u.email AS customer_email,
                p.name AS package_name,
                pending_payments.id AS pending_payment_id,
                pending_payments.amount AS pending_payment_amount,
                pending_payments.payment_method AS pending_payment_method,
                pending_payments.payment_channel AS pending_payment_channel,
                pending_payments.reference AS pending_payment_reference,
                pending_payments.proof_path AS pending_payment_proof_path,
                pending_payments.created_at AS pending_payment_created_at
         FROM bookings b
         INNER JOIN users u ON u.id = b.customer_id
         LEFT JOIN event_types et ON et.id = b.event_type_id
         LEFT JOIN packages p ON p.id = b.package_id
         LEFT JOIN (
            SELECT bp.*
            FROM booking_payments bp
            INNER JOIN (
                SELECT booking_id, MAX(id) AS latest_id
                FROM booking_payments
                WHERE status = 'pending'
                GROUP BY booking_id
            ) latest ON latest.booking_id = bp.booking_id AND latest.latest_id = bp.id
         ) pending_payments ON pending_payments.booking_id = b.id
         WHERE b.caterer_profile_id = :profile_id AND b.status IN ('awaiting_payment', 'confirmed')
         ORDER BY FIELD(b.status, 'awaiting_payment', 'confirmed'), b.event_date ASC, b.created_at ASC"
    );
    $approvedStmt->execute(['profile_id' => $profileId]);

    $completedStmt = $db->prepare(
        "SELECT b.id, b.event_date, b.event_time, b.guest_count,
                b.status,
                COALESCE(et.name, 'Custom Event') AS event_type,
                u.name AS customer_name, r.rating
         FROM bookings b
         INNER JOIN users u ON u.id = b.customer_id
         LEFT JOIN event_types et ON et.id = b.event_type_id
         LEFT JOIN reviews r ON r.booking_id = b.id
         WHERE b.caterer_profile_id = :profile_id AND b.status = 'completed'
         ORDER BY b.event_date DESC"
    );
    $completedStmt->execute(['profile_id' => $profileId]);

    $pendingRequests = $pendingStmt->fetchAll();

    $approvedBookings = array_map(static function (array $row): array {
        $row['pending_payment_id'] = isset($row['pending_payment_id']) ? (int) $row['pending_payment_id'] : null;
        $row['pending_payment_amount'] = isset($row['pending_payment_amount']) ? (float) $row['pending_payment_amount'] : null;
        $row['pending_payment_method'] = $row['pending_payment_method'] ?? null;
        $row['pending_payment_channel'] = $row['pending_payment_channel'] ?? null;
        $row['pending_payment_reference'] = $row['pending_payment_reference'] ?? null;
        $row['pending_payment_proof_path'] = $row['pending_payment_proof_path'] ?? null;
        $row['pending_payment_created_at'] = $row['pending_payment_created_at'] ?? null;
        $row['has_pending_payment'] = $row['pending_payment_id'] !== null;

        return $row;
    }, $approvedStmt->fetchAll());

    return [
        'pending' => $pendingRequests,
        'approved' => $approvedBookings,
        'completed' => array_map(static function ($row) {
            $row['rating'] = isset($row['rating']) ? (int) $row['rating'] : null;
            return $row;
        }, $completedStmt->fetchAll()),
    ];
}

function confirm_booking_payment(int $bookingId, int $paymentId, int $userId, int $profileId): void
{
    $db = db();

    try {
        $db->beginTransaction();

        $bookingStmt = $db->prepare(
            'SELECT id, customer_id, status, caterer_profile_id
             FROM bookings
             WHERE id = :id
             FOR UPDATE'
        );
        $bookingStmt->execute(['id' => $bookingId]);
        $booking = $bookingStmt->fetch();

        if (!$booking) {
            throw new RuntimeException('Booking not found.');
        }

        if ((int) $booking['caterer_profile_id'] !== $profileId) {
            throw new RuntimeException('You do not have permission to modify this booking.');
        }

        if ($booking['status'] !== 'awaiting_payment') {
            if ($booking['status'] === 'confirmed') {
                throw new RuntimeException('This booking is already confirmed.');
            }

            throw new RuntimeException('Booking is not waiting for payment confirmation.');
        }

        $paymentStmt = $db->prepare(
            'SELECT id, booking_id, amount, payment_method, payment_channel, reference, status
             FROM booking_payments
             WHERE id = :payment_id AND booking_id = :booking_id
             FOR UPDATE'
        );
        $paymentStmt->execute([
            'payment_id' => $paymentId,
            'booking_id' => $bookingId,
        ]);
        $payment = $paymentStmt->fetch();

        if (!$payment) {
            throw new RuntimeException('Payment record not found.');
        }

        if ($payment['status'] !== 'pending') {
            throw new RuntimeException('Payment is not pending confirmation.');
        }

        $updatePayment = $db->prepare("UPDATE booking_payments SET status = 'completed' WHERE id = :id");
        $updatePayment->execute(['id' => $paymentId]);

        $updateBooking = $db->prepare(
            "UPDATE bookings
             SET status = 'confirmed',
                 responded_at = COALESCE(responded_at, NOW()),
                 updated_at = NOW()
             WHERE id = :id"
        );
        $updateBooking->execute(['id' => $bookingId]);

        $logStmt = $db->prepare(
            'INSERT INTO booking_status_logs (booking_id, old_status, new_status, changed_by, message)
             VALUES (:booking_id, :old_status, :new_status, :changed_by, :message)'
        );
        $logStmt->execute([
            'booking_id' => $bookingId,
            'old_status' => $booking['status'],
            'new_status' => 'confirmed',
            'changed_by' => $userId,
            'message' => sprintf('Payment confirmed by caterer (%s)', strtoupper((string) $payment['payment_method'])),
        ]);

        $customerId = (int) $booking['customer_id'];

        if ($customerId > 0) {
            create_notification(
                $customerId,
                'booking_status',
                'Payment verified',
                "Great news! Your payment for booking #{$bookingId} has been verified and the booking is now confirmed."
            );
        }

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $e;
    }
}

function update_booking_status(int $bookingId, int $userId, int $profileId, string $status, ?string $note = null): void
{
    $status = strtolower($status);
    $allowed = ['awaiting_payment', 'confirmed', 'declined', 'completed', 'cancelled'];

    if (!in_array($status, $allowed, true)) {
        throw new InvalidArgumentException('Invalid booking status.');
    }

    $db = db();

    $bookingStmt = $db->prepare(
        'SELECT caterer_profile_id, customer_id, status FROM bookings WHERE id = :id FOR UPDATE'
    );

    try {
        $db->beginTransaction();

        $bookingStmt->execute(['id' => $bookingId]);
        $booking = $bookingStmt->fetch();

        if (!$booking) {
            throw new RuntimeException('Booking not found.');
        }

        if ((int) $booking['caterer_profile_id'] !== $profileId) {
            throw new RuntimeException('You do not have permission to modify this booking.');
        }

        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        $respondedAt = null;
        $completedAt = null;

        if (in_array($status, ['awaiting_payment', 'declined'], true) && !in_array($booking['status'], ['awaiting_payment', 'confirmed'], true)) {
            $respondedAt = $now;
        }

        if ($status === 'completed') {
            $completedAt = $now;
        }

        $updateBooking = $db->prepare(
            'UPDATE bookings
             SET status = :status,
                 updated_at = NOW(),
                 responded_at = COALESCE(:responded_at, responded_at),
                 completed_at = COALESCE(:completed_at, completed_at)
             WHERE id = :id'
        );
        $updateBooking->execute([
            'status' => $status,
            'responded_at' => $respondedAt,
            'completed_at' => $completedAt,
            'id' => $bookingId,
        ]);

        $logStmt = $db->prepare(
            'INSERT INTO booking_status_logs (booking_id, old_status, new_status, changed_by, message)
             VALUES (:booking_id, :old_status, :new_status, :changed_by, :message)'
        );
        $logStmt->execute([
            'booking_id' => $bookingId,
            'old_status' => $booking['status'],
            'new_status' => $status,
            'changed_by' => $userId,
            'message' => $note,
        ]);

        $customerId = (int) $booking['customer_id'];

        if ($customerId > 0) {
            switch ($status) {
                case 'awaiting_payment':
                    create_notification(
                        $customerId,
                        'booking_status',
                        'Booking approved – payment needed',
                        "Your booking #{$bookingId} was approved. Complete the payment to confirm."
                    );
                    break;
                case 'confirmed':
                    create_notification(
                        $customerId,
                        'booking_status',
                        'Payment received',
                        "Thanks! Your booking #{$bookingId} is now confirmed."
                    );
                    break;
                case 'declined':
                    create_notification(
                        $customerId,
                        'booking_status',
                        'Booking declined',
                        "Your booking #{$bookingId} was declined. Reach out to explore other options."
                    );
                    break;
                case 'completed':
                    create_notification(
                        $customerId,
                        'booking_status',
                        'Event completed',
                        "Thanks for working with Plateful! Booking #{$bookingId} is complete—share your review when you can."
                    );
                    break;
                case 'cancelled':
                    create_notification(
                        $customerId,
                        'booking_status',
                        'Booking cancelled',
                        "Booking #{$bookingId} has been cancelled."
                    );
                    break;
            }
        }

        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}
