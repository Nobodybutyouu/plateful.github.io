<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

function get_customer_dashboard_data(int $userId): array
{
    $db = db();

    $counts = [];

    $upcomingStmt = $db->prepare(
        "SELECT COUNT(*)
         FROM bookings
         WHERE customer_id = :customer_id
           AND status IN ('pending', 'awaiting_payment', 'confirmed')
           AND event_date >= CURDATE()"
    );
    $upcomingStmt->execute(['customer_id' => $userId]);
    $counts['upcoming'] = (int) $upcomingStmt->fetchColumn();

    $completedStmt = $db->prepare(
        "SELECT COUNT(*)
         FROM bookings
         WHERE customer_id = :customer_id
           AND status = 'completed'"
    );
    $completedStmt->execute(['customer_id' => $userId]);
    $counts['completed'] = (int) $completedStmt->fetchColumn();

    $notifStmt = $db->prepare(
        'SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0'
    );
    $notifStmt->execute(['user_id' => $userId]);
    $counts['notifications'] = (int) $notifStmt->fetchColumn();

    $upcomingStmt = $db->prepare(
        "SELECT b.id, b.event_date, b.status, b.event_time, cp.business_name, COALESCE(et.name, 'Custom Event') AS event_type,
                COALESCE(SUM(CASE WHEN pay.status = 'pending' THEN 1 ELSE 0 END), 0) AS pending_payment_count
         FROM bookings b
         INNER JOIN caterer_profiles cp ON cp.id = b.caterer_profile_id
         LEFT JOIN event_types et ON et.id = b.event_type_id
         LEFT JOIN booking_payments pay ON pay.booking_id = b.id
         WHERE b.customer_id = :customer_id AND b.status IN ('pending', 'awaiting_payment', 'confirmed')
         GROUP BY b.id, b.event_date, b.status, b.event_time, cp.business_name, event_type
         ORDER BY b.event_date ASC, b.created_at ASC
         LIMIT 3"
    );
    $upcomingStmt->execute(['customer_id' => $userId]);
    $upcoming = [];

    foreach ($upcomingStmt->fetchAll() as $row) {
        $upcoming[] = [
            'id' => (int) $row['id'],
            'business_name' => $row['business_name'],
            'event_type' => $row['event_type'],
            'event_date' => $row['event_date'],
            'event_time' => $row['event_time'],
            'status' => $row['status'],
            'has_pending_payment' => ((int) ($row['pending_payment_count'] ?? 0)) > 0,
        ];
    }

    return [
        'counts' => $counts,
        'upcoming_bookings' => $upcoming,
    ];
}

function get_customer_booking_detail(int $bookingId, int $customerId): ?array
{
    $db = db();

    $stmt = $db->prepare(
        "SELECT b.id,
                b.status,
                b.event_date,
                b.event_time,
                b.guest_count,
                b.custom_request,
                b.package_id,
                b.event_type_id,
                cp.business_name,
                cp.id AS caterer_profile_id,
                cp.service_area,
                cp.location,
                p.name AS package_name,
                p.price AS package_price,
                p.deposit_percentage,
                et.name AS event_type_name,
                (
                    SELECT COUNT(*)
                    FROM booking_payments pay
                    WHERE pay.booking_id = b.id AND pay.status = 'pending'
                ) AS pending_payment_count
         FROM bookings b
         INNER JOIN caterer_profiles cp ON cp.id = b.caterer_profile_id
         LEFT JOIN packages p ON p.id = b.package_id
         LEFT JOIN event_types et ON et.id = b.event_type_id
         WHERE b.id = :id AND b.customer_id = :customer_id
         LIMIT 1"
    );
    $stmt->execute([
        'id' => $bookingId,
        'customer_id' => $customerId,
    ]);

    $booking = $stmt->fetch();

    if (!$booking) {
        return null;
    }

    $timelineStmt = $db->prepare(
        'SELECT new_status, old_status, message, created_at
         FROM booking_status_logs
         WHERE booking_id = :id
         ORDER BY created_at ASC'
    );
    $timelineStmt->execute(['id' => $bookingId]);

    return [
        'booking' => $booking,
        'timeline' => $timelineStmt->fetchAll(),
    ];
}

function update_customer_booking(int $bookingId, int $customerId, array $payload): void
{
    $db = db();

    $allowedStatuses = ['pending', 'awaiting_payment'];

    $bookingStmt = $db->prepare(
        'SELECT status
         FROM bookings
         WHERE id = :id AND customer_id = :customer_id
         LIMIT 1'
    );
    $bookingStmt->execute([
        'id' => $bookingId,
        'customer_id' => $customerId,
    ]);

    $booking = $bookingStmt->fetch();

    if (!$booking) {
        throw new RuntimeException('Booking not found.');
    }

    if (!in_array($booking['status'], $allowedStatuses, true)) {
        throw new RuntimeException('This booking can no longer be edited.');
    }

    $guestCount = isset($payload['guest_count']) ? (int) $payload['guest_count'] : null;
    $eventDate = $payload['event_date'] ?? null;
    $eventTime = $payload['event_time'] ?? null;
    $customRequest = $payload['custom_request'] ?? null;

    if ($guestCount !== null && $guestCount <= 0) {
        throw new InvalidArgumentException('Guest count must be greater than zero.');
    }

    $eventDateForUpdate = null;
    if ($eventDate !== null) {
        $eventDateObj = DateTime::createFromFormat('Y-m-d', $eventDate);

        if (!$eventDateObj) {
            throw new InvalidArgumentException('Please provide a valid event date.');
        }

        $eventDateObj->setTime(0, 0, 0);
        $today = new DateTime('today');

        if ($eventDateObj < $today) {
            throw new InvalidArgumentException('Event date cannot be in the past.');
        }

        $eventDateForUpdate = $eventDateObj->format('Y-m-d');
    }

    $eventTimeForUpdate = null;
    if ($eventTime !== null) {
        $eventTime = trim($eventTime);

        if ($eventTime !== '') {
            $timeObj = DateTime::createFromFormat('H:i', $eventTime);

            if (!$timeObj) {
                throw new InvalidArgumentException('Please provide a valid event time.');
            }

            $eventTimeForUpdate = $timeObj->format('H:i:s');
        }
    }

    $customRequestForUpdate = null;
    if ($customRequest !== null) {
        $customRequest = trim((string) $customRequest);
        $customRequestForUpdate = $customRequest !== '' ? $customRequest : null;
    }

    $fields = [];
    $bindings = [
        'id' => $bookingId,
        'customer_id' => $customerId,
    ];

    if ($guestCount !== null) {
        $fields[] = 'guest_count = :guest_count';
        $bindings['guest_count'] = $guestCount;
    }

    if ($eventDateForUpdate !== null) {
        $fields[] = 'event_date = :event_date';
        $bindings['event_date'] = $eventDateForUpdate;
    }

    if ($eventTime !== null) {
        $fields[] = 'event_time = :event_time';
        $bindings['event_time'] = $eventTimeForUpdate;
    }

    if ($customRequest !== null) {
        $fields[] = 'custom_request = :custom_request';
        $bindings['custom_request'] = $customRequestForUpdate;
    }

    if (empty($fields)) {
        throw new InvalidArgumentException('No changes provided.');
    }

    $fields[] = 'updated_at = NOW()';

    $sql = sprintf(
        'UPDATE bookings SET %s WHERE id = :id AND customer_id = :customer_id',
        implode(', ', $fields)
    );

    $updateStmt = $db->prepare($sql);
    $updateStmt->execute($bindings);

    $logStmt = $db->prepare(
        'INSERT INTO booking_status_logs (booking_id, old_status, new_status, changed_by, message)
         VALUES (:booking_id, :old_status, :new_status, :changed_by, :message)'
    );

    $logStmt->execute([
        'booking_id' => $bookingId,
        'old_status' => $booking['status'],
        'new_status' => $booking['status'],
        'changed_by' => $customerId,
        'message' => 'Booking details updated by customer.',
    ]);
}

function get_customer_bookings(int $userId): array
{
    $db = db();

    $upcomingStmt = $db->prepare(
        "SELECT b.id,
                b.event_date,
                b.event_time,
                b.status,
                b.guest_count,
                b.custom_request,
                cp.business_name,
                COALESCE(et.name, 'Custom Event') AS event_type,
                p.name AS package_name,
                (
                    SELECT COUNT(*)
                    FROM booking_payments pay
                    WHERE pay.booking_id = b.id AND pay.status = 'pending'
                ) AS pending_payment_count
         FROM bookings b
         INNER JOIN caterer_profiles cp ON cp.id = b.caterer_profile_id
         LEFT JOIN event_types et ON et.id = b.event_type_id
         LEFT JOIN packages p ON p.id = b.package_id
         WHERE b.customer_id = :customer_id
           AND b.status IN ('pending', 'awaiting_payment', 'confirmed')
         ORDER BY b.event_date ASC, b.created_at ASC"
    );
    $upcomingStmt->execute(['customer_id' => $userId]);

    $completedStmt = $db->prepare(
        "SELECT b.id, b.event_date, b.event_time, b.status, cp.business_name, COALESCE(et.name, 'Custom Event') AS event_type
         FROM bookings b
         INNER JOIN caterer_profiles cp ON cp.id = b.caterer_profile_id
         LEFT JOIN event_types et ON et.id = b.event_type_id
         WHERE b.customer_id = :customer_id AND b.status = 'completed'
         ORDER BY b.event_date DESC"
    );
    $completedStmt->execute(['customer_id' => $userId]);

    $upcomingBookings = array_map(static function (array $row): array {
        $row['id'] = (int) $row['id'];
        $row['guest_count'] = (int) $row['guest_count'];
        $row['pending_payment_count'] = (int) ($row['pending_payment_count'] ?? 0);
        $row['has_pending_payment'] = $row['pending_payment_count'] > 0;
        $row['is_editable'] = in_array($row['status'], ['pending', 'awaiting_payment'], true);

        return $row;
    }, $upcomingStmt->fetchAll());

    return [
        'upcoming' => $upcomingBookings,
        'completed' => $completedStmt->fetchAll(),
    ];
}

function get_customer_reviews(int $userId): array
{
    $db = db();

    $pendingStmt = $db->prepare(
        "SELECT b.id, cp.id AS caterer_profile_id, cp.business_name, b.event_date
         FROM bookings b
         INNER JOIN caterer_profiles cp ON cp.id = b.caterer_profile_id
         LEFT JOIN reviews r ON r.booking_id = b.id
         WHERE b.customer_id = :customer_id AND b.status = 'completed' AND r.id IS NULL
         ORDER BY b.event_date DESC"
    );
    $pendingStmt->execute(['customer_id' => $userId]);

    $submittedStmt = $db->prepare(
        "SELECT r.id, r.rating, r.comment, r.created_at, cp.business_name, b.event_date
         FROM reviews r
         INNER JOIN bookings b ON b.id = r.booking_id
         INNER JOIN caterer_profiles cp ON cp.id = r.caterer_profile_id
         WHERE r.customer_id = :customer_id
         ORDER BY r.created_at DESC"
    );
    $submittedStmt->execute(['customer_id' => $userId]);

    return [
        'pending' => $pendingStmt->fetchAll(),
        'submitted' => $submittedStmt->fetchAll(),
    ];
}

function get_customer_profile_data(int $userId): array
{
    $db = db();

    $stmt = $db->prepare(
        'SELECT u.name, u.email, cp.phone, cp.preferred_location
         FROM users u
         LEFT JOIN customer_profiles cp ON cp.user_id = u.id
         WHERE u.id = :user_id
         LIMIT 1'
    );
    $stmt->execute(['user_id' => $userId]);

    $row = $stmt->fetch();

    if (!$row) {
        return [
            'name' => '',
            'email' => '',
            'phone' => null,
            'preferred_location' => null,
        ];
    }

    return $row;
}

function update_customer_profile(int $userId, ?string $phone, ?string $preferredLocation): void
{
    $db = db();

    $stmt = $db->prepare(
        'INSERT INTO customer_profiles (user_id, phone, preferred_location)
         VALUES (:user_id, :phone, :preferred_location)
         ON DUPLICATE KEY UPDATE phone = VALUES(phone), preferred_location = VALUES(preferred_location)'
    );

    $stmt->execute([
        'user_id' => $userId,
        'phone' => $phone,
        'preferred_location' => $preferredLocation,
    ]);
}

function get_event_types(): array
{
    $stmt = db()->query('SELECT id, name FROM event_types ORDER BY name');

    return array_map(static function (array $row): array {
        return [
            'id' => (int) $row['id'],
            'name' => $row['name'],
        ];
    }, $stmt->fetchAll());
}

function create_customer_booking(
    int $customerId,
    int $catererProfileId,
    int $guestCount,
    string $eventDate,
    ?string $eventTime,
    ?int $packageId,
    ?int $eventTypeId,
    ?string $customRequest
): int {
    if ($guestCount <= 0) {
        throw new InvalidArgumentException('Guest count must be greater than zero.');
    }

    $eventDateObj = DateTime::createFromFormat('Y-m-d', $eventDate);

    if (!$eventDateObj) {
        throw new InvalidArgumentException('Invalid event date provided.');
    }

    $eventDateObj->setTime(0, 0, 0);
    $eventDateFormatted = $eventDateObj->format('Y-m-d');

    $today = new DateTime('today');

    if ($eventDateObj < $today) {
        throw new InvalidArgumentException('Event date cannot be in the past.');
    }

    $eventTimeFormatted = null;

    if ($eventTime !== null) {
        $eventTime = trim($eventTime);

        if ($eventTime !== '') {
            $timeObj = DateTime::createFromFormat('H:i', $eventTime);

            if (!$timeObj) {
                throw new InvalidArgumentException('Invalid event time provided.');
            }

            $eventTimeFormatted = $timeObj->format('H:i:s');
        }
    }

    $packageIdValue = $packageId !== null && $packageId > 0 ? $packageId : null;
    $eventTypeIdValue = $eventTypeId !== null && $eventTypeId > 0 ? $eventTypeId : null;
    $customRequestValue = $customRequest !== null && trim($customRequest) !== '' ? trim($customRequest) : null;

    $db = db();

    try {
        $db->beginTransaction();

        $profileStmt = $db->prepare(
            "SELECT cp.id, cp.user_id, cp.business_name, cp.availability_status,
                    owner.email AS owner_email
             FROM caterer_profiles cp
             INNER JOIN users owner ON owner.id = cp.user_id
             WHERE cp.id = :id AND cp.approval_status = 'approved'
             LIMIT 1"
        );
        $profileStmt->execute(['id' => $catererProfileId]);
        $profile = $profileStmt->fetch();

        if (!$profile) {
            throw new RuntimeException('Caterer not found.');
        }

        $availabilityStatus = $profile['availability_status'];
        $availabilityStatusNormalized = $availabilityStatus !== null ? strtolower(trim((string) $availabilityStatus)) : null;
        $unavailableStates = ['unavailable', 'closed', 'snooze', 'fully booked', 'fully_booked'];
        $isAcceptingBookings = !in_array($availabilityStatusNormalized, $unavailableStates, true);

        if (!$isAcceptingBookings) {
            throw new RuntimeException('This caterer is not currently accepting bookings.');
        }

        $packageName = null;
        if ($packageIdValue !== null) {
            $packageStmt = $db->prepare(
                'SELECT id, name FROM packages WHERE id = :id AND caterer_profile_id = :profile_id AND is_active = 1 LIMIT 1'
            );
            $packageStmt->execute([
                'id' => $packageIdValue,
                'profile_id' => $catererProfileId,
            ]);

            $packageRow = $packageStmt->fetch();

            if (!$packageRow) {
                throw new RuntimeException('Selected package is no longer available.');
            }

            $packageName = $packageRow['name'] ?? null;
        }

        // Verify event type if provided
        $eventTypeName = null;
        if ($eventTypeIdValue !== null) {
            $eventTypeStmt = $db->prepare('SELECT id, name FROM event_types WHERE id = :id LIMIT 1');
            $eventTypeStmt->execute(['id' => $eventTypeIdValue]);

            $eventTypeRow = $eventTypeStmt->fetch();

            if (!$eventTypeRow) {
                throw new RuntimeException('Selected event type is invalid.');
            }

            $eventTypeName = $eventTypeRow['name'] ?? null;
        }

        // Get customer name for notification
        $customerNameStmt = $db->prepare('SELECT name, email FROM users WHERE id = :id LIMIT 1');
        $customerNameStmt->execute(['id' => $customerId]);
        $customerRow = $customerNameStmt->fetch();
        $customerName = $customerRow['name'] ?? 'A customer';
        $customerEmail = $customerRow['email'] ?? null;

        // Prevent overlapping bookings
        $overlapSql = 'SELECT COUNT(*) FROM bookings WHERE caterer_profile_id = :profile_id AND event_date = :event_date AND status IN (\'pending\', \'approved\')';
        $overlapParams = [
            'profile_id' => $catererProfileId,
            'event_date' => $eventDateFormatted,
        ];

        if ($eventTimeFormatted !== null) {
            $overlapSql .= ' AND (event_time = :event_time OR event_time IS NULL)';
            $overlapParams['event_time'] = $eventTimeFormatted;
        } else {
            $overlapSql .= ' AND event_time IS NULL';
        }

        $overlapStmt = $db->prepare($overlapSql);
        $overlapStmt->execute($overlapParams);

        if ((int) $overlapStmt->fetchColumn() > 0) {
            throw new RuntimeException('The caterer already has a booking around this time. Try another slot.');
        }

        $insertStmt = $db->prepare(
            'INSERT INTO bookings (customer_id, caterer_profile_id, package_id, event_type_id, guest_count, event_date, event_time, custom_request, status)
             VALUES (:customer_id, :caterer_profile_id, :package_id, :event_type_id, :guest_count, :event_date, :event_time, :custom_request, :status)'
        );
        $insertStmt->execute([
            'customer_id' => $customerId,
            'caterer_profile_id' => $catererProfileId,
            'package_id' => $packageIdValue,
            'event_type_id' => $eventTypeIdValue,
            'guest_count' => $guestCount,
            'event_date' => $eventDateFormatted,
            'event_time' => $eventTimeFormatted,
            'custom_request' => $customRequestValue,
            'status' => 'pending',
        ]);

        $bookingId = (int) $db->lastInsertId();

        $logStmt = $db->prepare(
            'INSERT INTO booking_status_logs (booking_id, old_status, new_status, changed_by, message)
             VALUES (:booking_id, :old_status, :new_status, :changed_by, :message)'
        );
        $logStmt->execute([
            'booking_id' => $bookingId,
            'old_status' => null,
            'new_status' => 'pending',
            'changed_by' => $customerId,
            'message' => 'Booking request submitted by customer.',
        ]);

        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }

    $eventDateForMessage = (new DateTime($eventDateFormatted))->format('M j, Y');
    $eventTimeForMessage = $eventTimeFormatted
        ? DateTime::createFromFormat('H:i:s', $eventTimeFormatted)->format('g:i A')
        : 'To be confirmed';

    create_notification(
        (int) $profile['user_id'],
        'booking_request',
        'New booking request received',
        sprintf('%s requested a booking for %s.', $customerName, $eventDateForMessage)
    );

    $businessName = $profile['business_name'] ?? 'your business';
    $catererEmail = $profile['owner_email'] ?? null;

    $summaryLines = [
        "Event date: {$eventDateForMessage}",
        "Event time: {$eventTimeForMessage}",
        "Guests: {$guestCount}",
    ];

    if ($packageName) {
        $summaryLines[] = "Selected package: {$packageName}";
    }

    if ($eventTypeName) {
        $summaryLines[] = "Event type: {$eventTypeName}";
    }

    if ($customRequestValue) {
        $summaryLines[] = "Notes:\n" . $customRequestValue;
    }

    $summaryText = implode("\n", $summaryLines);

    if ($catererEmail) {
        $catererSubject = sprintf('[Plateful] New booking request from %s', $customerName);
        $catererBody = "Hi {$businessName},\n\n{$customerName} just submitted a booking request.\n\n{$summaryText}\n\nReply via your Plateful dashboard to approve or decline the request.";
        send_app_mail($catererEmail, $catererSubject, $catererBody);
    }

    if ($customerEmail) {
        $customerSubject = sprintf('[Plateful] Booking request sent to %s', $businessName);
        $customerBody = "Hi {$customerName},\n\nThanks for using Plateful! We sent your booking request to {$businessName}. They'll review it and respond soon.\n\n{$summaryText}\n\nYou can track the status from your dashboard.";
        send_app_mail($customerEmail, $customerSubject, $customerBody);
    }

    return $bookingId;
}

function get_customer_booking_payment_summary(int $bookingId, int $customerId): ?array
{
    $db = db();

    $stmt = $db->prepare(
        "SELECT b.id, b.status, b.event_date, b.event_time, b.guest_count, b.custom_request,
                b.caterer_profile_id,
                cp.business_name, cp.user_id AS caterer_user_id,
                p.name AS package_name, p.price AS package_price, p.deposit_percentage,
                COALESCE(SUM(CASE WHEN pay.status = 'completed' THEN pay.amount ELSE 0 END), 0) AS amount_paid,
                COALESCE(SUM(CASE WHEN pay.status = 'pending' THEN pay.amount ELSE 0 END), 0) AS pending_amount,
                SUM(CASE WHEN pay.status = 'pending' THEN 1 ELSE 0 END) AS pending_payments
         FROM bookings b
         INNER JOIN caterer_profiles cp ON cp.id = b.caterer_profile_id
         LEFT JOIN packages p ON p.id = b.package_id
         LEFT JOIN booking_payments pay ON pay.booking_id = b.id
         WHERE b.id = :id AND b.customer_id = :customer_id
         GROUP BY b.id, b.status, b.event_date, b.event_time, b.guest_count, b.custom_request,
                  b.caterer_profile_id,
                  cp.business_name, cp.user_id, p.name, p.price, p.deposit_percentage"
    );
    $stmt->execute([
        'id' => $bookingId,
        'customer_id' => $customerId,
    ]);

    $row = $stmt->fetch();

    if (!$row) {
        return null;
    }

    $guestCount = (int) $row['guest_count'];
    $pricePerHead = $row['package_price'] !== null ? (float) $row['package_price'] : null;
    $depositPercentage = $row['deposit_percentage'] !== null ? (float) $row['deposit_percentage'] : null;
    $amountPaid = (float) $row['amount_paid'];
    $pendingAmount = (float) ($row['pending_amount'] ?? 0.0);
    $pendingPayments = (int) ($row['pending_payments'] ?? 0);

    $totalAmount = $pricePerHead !== null ? round($pricePerHead * $guestCount, 2) : null;
    $depositAmount = ($totalAmount !== null && $depositPercentage !== null)
        ? round($totalAmount * ($depositPercentage / 100), 2)
        : null;
    $remainingBalance = $totalAmount !== null ? max(round($totalAmount - $amountPaid, 2), 0.0) : null;

    return [
        'id' => (int) $row['id'],
        'status' => $row['status'],
        'event_date' => $row['event_date'],
        'event_time' => $row['event_time'],
        'guest_count' => $guestCount,
        'custom_request' => $row['custom_request'],
        'business_name' => $row['business_name'],
        'caterer_profile_id' => (int) $row['caterer_profile_id'],
        'caterer_user_id' => (int) $row['caterer_user_id'],
        'package_name' => $row['package_name'],
        'price_per_head' => $pricePerHead,
        'total_amount' => $totalAmount,
        'deposit_percentage' => $depositPercentage,
        'deposit_amount' => $depositAmount,
        'amount_paid' => $amountPaid,
        'remaining_balance' => $remainingBalance,
        'requires_payment' => $row['status'] === 'awaiting_payment',
        'pending_payment_count' => $pendingPayments,
        'has_pending_payment' => $pendingPayments > 0,
        'pending_amount' => $pendingAmount,
    ];
}

function record_booking_payment(
    int $bookingId,
    int $customerId,
    float $amount,
    string $method,
    ?string $channel = null,
    ?string $reference = null,
    ?string $proofPath = null
): int {
    $method = strtolower(trim($method));
    $allowedMethods = ['full', 'deposit', 'cod'];

    if (!in_array($method, $allowedMethods, true)) {
        throw new InvalidArgumentException('Unsupported payment method.');
    }

    if ($amount < 0) {
        throw new InvalidArgumentException('Payment amount cannot be negative.');
    }

    $db = db();

    try {
        $db->beginTransaction();

        $bookingStmt = $db->prepare(
            'SELECT b.customer_id, b.status, b.caterer_profile_id, cp.user_id AS caterer_user_id
             FROM bookings b
             INNER JOIN caterer_profiles cp ON cp.id = b.caterer_profile_id
             WHERE b.id = :id
             FOR UPDATE'
        );
        $bookingStmt->execute(['id' => $bookingId]);
        $booking = $bookingStmt->fetch();

        if (!$booking || (int) $booking['customer_id'] !== $customerId) {
            throw new RuntimeException('Booking not found.');
        }

        if ($booking['status'] !== 'awaiting_payment') {
            if ($booking['status'] === 'confirmed') {
                throw new RuntimeException('This booking is already confirmed.');
            }

            throw new RuntimeException('Payment is not required at this stage.');
        }

        $pendingPaymentCheck = $db->prepare(
            "SELECT COUNT(*) FROM booking_payments WHERE booking_id = :booking_id AND status = 'pending'"
        );
        $pendingPaymentCheck->execute(['booking_id' => $bookingId]);

        if ((int) $pendingPaymentCheck->fetchColumn() > 0) {
            throw new RuntimeException('A payment has already been submitted and is awaiting verification. Please wait for the caterer to confirm it.');
        }

        $paymentStmt = $db->prepare(
            'INSERT INTO booking_payments (booking_id, customer_id, amount, payment_method, payment_channel, status, reference, proof_path)
             VALUES (:booking_id, :customer_id, :amount, :method, :channel, :status, :reference, :proof_path)'
        );
        $paymentStmt->execute([
            'booking_id' => $bookingId,
            'customer_id' => $customerId,
            'amount' => $amount,
            'method' => $method,
            'channel' => $channel,
            'status' => 'pending',
            'reference' => $reference,
            'proof_path' => $proofPath,
        ]);

        $touchBookingStmt = $db->prepare('UPDATE bookings SET updated_at = NOW() WHERE id = :id');
        $touchBookingStmt->execute(['id' => $bookingId]);

        $logStmt = $db->prepare(
            'INSERT INTO booking_status_logs (booking_id, old_status, new_status, changed_by, message)
             VALUES (:booking_id, :old_status, :new_status, :changed_by, :message)'
        );
        $logStmt->execute([
            'booking_id' => $bookingId,
            'old_status' => $booking['status'],
            'new_status' => $booking['status'],
            'changed_by' => $customerId,
            'message' => sprintf('Payment submitted for verification (%s)', strtoupper($method)),
        ]);

        $paymentId = (int) $db->lastInsertId();

        $catererUserId = (int) $booking['caterer_user_id'];

        create_notification(
            $customerId,
            'booking_status',
            'Payment submitted',
            "Thanks! We received your payment for booking #{$bookingId}. We'll let you know once the caterer confirms it."
        );

        if ($catererUserId > 0) {
            create_notification(
                $catererUserId,
                'booking_status',
                'Payment submitted for review',
                "Booking #{$bookingId} has a new payment to review and confirm."
            );
        }

        $db->commit();

        return $paymentId;
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $e;
    }
}

function get_caterer_profile_detail(int $catererProfileId): ?array
{
    $db = db();

    $profileStmt = $db->prepare(
        "SELECT cp.id, cp.business_name, cp.description, cp.location, cp.service_area,
                cp.cuisine_specialties, cp.event_types, cp.availability_status, cp.average_price,
                u.name AS owner_name, u.email AS owner_email,
                COALESCE(AVG(r.rating), 0) AS average_rating,
                COUNT(r.id) AS review_count
         FROM caterer_profiles cp
         INNER JOIN users u ON u.id = cp.user_id AND u.status = 'active'
         LEFT JOIN reviews r ON r.caterer_profile_id = cp.id
         WHERE cp.id = :id AND cp.approval_status = 'approved'
         GROUP BY cp.id, cp.business_name, cp.description, cp.location, cp.service_area,
                  cp.cuisine_specialties, cp.event_types, cp.availability_status, cp.average_price,
                  u.name, u.email"
    );
    $profileStmt->execute(['id' => $catererProfileId]);
    $profile = $profileStmt->fetch();

    if (!$profile) {
        return null;
    }

    $packagesStmt = $db->prepare(
        'SELECT id, name, description, inclusions, price, deposit_percentage, package_type, is_active
         FROM packages
         WHERE caterer_profile_id = :id AND is_active = 1
         ORDER BY price ASC'
    );
    $packagesStmt->execute(['id' => $catererProfileId]);
    $packages = array_map(static function (array $row): array {
        $row['id'] = (int) $row['id'];
        $row['price'] = (float) $row['price'];
        $row['deposit_percentage'] = $row['deposit_percentage'] !== null ? (float) $row['deposit_percentage'] : null;
        $row['is_active'] = (bool) $row['is_active'];

        return $row;
    }, $packagesStmt->fetchAll());

    $reviewsStmt = $db->prepare(
        "SELECT r.id, r.rating, r.comment, r.created_at,
                customer.name AS customer_name
         FROM reviews r
         INNER JOIN users customer ON customer.id = r.customer_id
         WHERE r.caterer_profile_id = :id
         ORDER BY r.created_at DESC
         LIMIT 10"
    );
    $reviewsStmt->execute(['id' => $catererProfileId]);

    $galleryStmt = $db->prepare(
        'SELECT id, file_path, created_at
         FROM caterer_gallery_photos
         WHERE caterer_profile_id = :id
         ORDER BY created_at DESC'
    );
    $galleryStmt->execute(['id' => $catererProfileId]);
    $gallery = array_map(static function (array $row): array {
        return [
            'id' => (int) $row['id'],
            'file_path' => $row['file_path'],
            'created_at' => $row['created_at'],
        ];
    }, $galleryStmt->fetchAll());

    return [
        'profile' => $profile,
        'packages' => $packages,
        'reviews' => $reviewsStmt->fetchAll(),
        'gallery' => $gallery,
    ];
}

function get_caterer_directory(array $filters = []): array
{
    $db = db();

    $conditions = ["cp.approval_status = 'approved'"];
    $params = [];

    if (!empty($filters['location'])) {
        $conditions[] = 'cp.location LIKE :location';
        $params['location'] = '%' . $filters['location'] . '%';
    }

    if (!empty($filters['cuisine'])) {
        $conditions[] = 'cp.cuisine_specialties LIKE :cuisine';
        $params['cuisine'] = '%' . $filters['cuisine'] . '%';
    }

    if (!empty($filters['event_type'])) {
        $conditions[] = 'cp.event_types LIKE :event_type';
        $params['event_type'] = '%' . $filters['event_type'] . '%';
    }

    $whereSql = implode(' AND ', $conditions);

    $orderSql = 'cp.business_name ASC';
    $havingSql = '';

    if (isset($filters['price_per_head']) && $filters['price_per_head'] !== null) {
        $havingSql = 'HAVING COALESCE(MIN(CASE WHEN p.is_active = 1 THEN p.price END), cp.average_price) <= :price_per_head';
        $params['price_per_head'] = $filters['price_per_head'];
        $orderSql = 'COALESCE(MIN(CASE WHEN p.is_active = 1 THEN p.price END), cp.average_price) ASC, cp.business_name ASC';
    }

    $sql = "SELECT
                cp.id,
                cp.business_name,
                cp.location,
                cp.average_price,
                cp.cuisine_specialties,
                cp.event_types,
                cp.availability_status,
                COALESCE(AVG(r.rating), 0) AS average_rating,
                COUNT(r.id) AS review_count,
                MIN(CASE WHEN p.is_active = 1 THEN p.price END) AS starting_price
            FROM caterer_profiles cp
            INNER JOIN users u ON u.id = cp.user_id AND u.status = 'active'
            LEFT JOIN reviews r ON r.caterer_profile_id = cp.id
            LEFT JOIN packages p ON p.caterer_profile_id = cp.id
            WHERE {$whereSql}
            GROUP BY cp.id
            {$havingSql}
            ORDER BY {$orderSql}";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    $rows = $stmt->fetchAll();

    return array_map(static function ($row) {
        $startingPrice = $row['starting_price'] !== null ? (float) $row['starting_price'] : null;

        return [
            'id' => (int) $row['id'],
            'business_name' => $row['business_name'],
            'location' => $row['location'],
            'average_price' => $row['average_price'] !== null ? (float) $row['average_price'] : null,
            'starting_price' => $startingPrice,
            'cuisine_specialties' => $row['cuisine_specialties'],
            'event_types' => $row['event_types'],
            'availability_status' => $row['availability_status'],
            'average_rating' => (float) $row['average_rating'],
            'review_count' => (int) $row['review_count'],
        ];
    }, $rows);
}

function customer_submit_review(int $bookingId, int $customerId, int $catererProfileId, int $rating, ?string $comment = null): void
{
    if ($rating < 1 || $rating > 5) {
        throw new InvalidArgumentException('Rating must be between 1 and 5.');
    }

    $db = db();

    try {
        $db->beginTransaction();

        $bookingStmt = $db->prepare(
            "SELECT id FROM bookings
             WHERE id = :booking_id AND customer_id = :customer_id AND status = 'completed'
             LIMIT 1"
        );
        $bookingStmt->execute([
            'booking_id' => $bookingId,
            'customer_id' => $customerId,
        ]);

        if (!$bookingStmt->fetchColumn()) {
            throw new RuntimeException('Booking not eligible for review.');
        }

        $existingStmt = $db->prepare('SELECT id FROM reviews WHERE booking_id = :booking_id LIMIT 1');
        $existingStmt->execute(['booking_id' => $bookingId]);

        if ($existingStmt->fetchColumn()) {
            throw new RuntimeException('Review already submitted for this booking.');
        }

        $insertStmt = $db->prepare(
            'INSERT INTO reviews (booking_id, customer_id, caterer_profile_id, rating, comment)
             VALUES (:booking_id, :customer_id, :caterer_profile_id, :rating, :comment)'
        );
        $insertStmt->execute([
            'booking_id' => $bookingId,
            'customer_id' => $customerId,
            'caterer_profile_id' => $catererProfileId,
            'rating' => $rating,
            'comment' => $comment,
        ]);

        $catererOwnerStmt = $db->prepare(
            'SELECT user_id FROM caterer_profiles WHERE id = :id LIMIT 1'
        );
        $catererOwnerStmt->execute(['id' => $catererProfileId]);
        $catererUserId = (int) $catererOwnerStmt->fetchColumn();

        if ($catererUserId > 0) {
            create_notification(
                $catererUserId,
                'review_received',
                'New review received',
                "A customer left a new review (rating: {$rating}/5) for booking #{$bookingId}."
            );
        }

        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function get_customer_notifications(int $userId, int $limit = 10): array
{
    $db = db();

    $stmt = $db->prepare(
        'SELECT id, title, message, type, is_read, created_at
         FROM notifications
         WHERE user_id = :user_id
         ORDER BY created_at DESC
         LIMIT :limit'
    );
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function mark_notification_read(int $notificationId, int $userId): void
{
    $db = db();

    $stmt = $db->prepare(
        'UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :user_id'
    );
    $stmt->execute([
        'id' => $notificationId,
        'user_id' => $userId,
    ]);
}
