<?php

function redirect(string $path): void
{
    header('Location: ' . APP_URL . $path);
    exit;
}

function send_app_mail(string $to, string $subject, string $body): bool
{
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    if (defined('APP_ENV') && APP_ENV === 'local') {
        error_log(sprintf('[mail:disabled] To: %s | Subject: %s', $to, $subject));
        return true;
    }

    $headers = [
        'From: ' . (MAIL_SENDER ?? 'no-reply@localhost'),
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
    ];

    return mail($to, $subject, $body, implode("\r\n", $headers));
}

function asset(string $path): string
{
    return APP_URL . '/' . ltrim($path, '/');
}

function format_currency(float $amount): string
{
    return '₱' . number_format($amount, 2);
}

function json_response(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function is_active_page(array|string $names): bool
{
    $current = basename($_SERVER['PHP_SELF'] ?? '');

    if (is_array($names)) {
        return in_array($current, $names, true);
    }

    return $current === $names;
}

function create_notification(int $userId, string $type, string $title, string $message): void
{
    $stmt = db()->prepare(
        'INSERT INTO notifications (user_id, type, title, message) VALUES (:user_id, :type, :title, :message)'
    );

    $stmt->execute([
        'user_id' => $userId,
        'type' => $type,
        'title' => $title,
        'message' => $message,
    ]);
}
