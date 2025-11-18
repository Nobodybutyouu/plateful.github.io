<?php

require_once __DIR__ . '/db.php';

function find_user_by_email(string $email): ?array
{
    $stmt = db()->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);

    $row = $stmt->fetch();

    return $row ?: null;
}

function create_user(array $data): int
{
    $stmt = db()->prepare(
        'INSERT INTO users (name, email, password_hash, role, status) VALUES (:name, :email, :password_hash, :role, :status)'
    );

    $stmt->execute([
        'name' => $data['name'],
        'email' => $data['email'],
        'password_hash' => $data['password_hash'],
        'role' => $data['role'],
        'status' => $data['status'] ?? 'active',
    ]);

    return (int) db()->lastInsertId();
}

function register_customer(array $input): int
{
    $userId = create_user([
        'name' => $input['name'],
        'email' => $input['email'],
        'password_hash' => password_hash($input['password'], PASSWORD_DEFAULT),
        'role' => 'customer',
        'status' => 'active',
    ]);

    $stmt = db()->prepare('INSERT INTO customer_profiles (user_id, phone, preferred_location) VALUES (:user_id, :phone, :preferred_location)');
    $stmt->execute([
        'user_id' => $userId,
        'phone' => $input['phone'] ?? null,
        'preferred_location' => $input['preferred_location'] ?? null,
    ]);

    return $userId;
}

function register_caterer(array $input): int
{
    $userId = create_user([
        'name' => $input['name'],
        'email' => $input['email'],
        'password_hash' => password_hash($input['password'], PASSWORD_DEFAULT),
        'role' => 'caterer',
        'status' => 'pending',
    ]);

    $stmt = db()->prepare(
        'INSERT INTO caterer_profiles (
            user_id, business_name, description, location, service_area,
            cuisine_specialties, event_types, average_price, approval_status
        ) VALUES (
            :user_id, :business_name, :description, :location, :service_area,
            :cuisine_specialties, :event_types, :average_price, :approval_status
        )'
    );

    $stmt->execute([
        'user_id' => $userId,
        'business_name' => $input['business_name'],
        'description' => $input['description'] ?? null,
        'location' => $input['location'] ?? null,
        'service_area' => $input['service_area'] ?? null,
        'cuisine_specialties' => $input['cuisine_specialties'] ?? null,
        'event_types' => $input['event_types'] ?? null,
        'average_price' => $input['average_price'] ?? null,
        'approval_status' => 'pending',
    ]);

    return $userId;
}

function attempt_login(string $email, string $password): bool
{
    $user = find_user_by_email($email);

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }

    if ($user['status'] === 'disabled') {
        return false;
    }

    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role'],
        'status' => $user['status'],
    ];

    return true;
}

function logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_authenticated(): bool
{
    return current_user() !== null;
}

function user_has_role(string ...$roles): bool
{
    $user = current_user();

    if (!$user) {
        return false;
    }

    return in_array($user['role'], $roles, true);
}

function require_auth(array $roles = []): void
{
    if (!is_authenticated()) {
        redirect('/login.php');
    }

    if ($roles && !user_has_role(...$roles)) {
        redirect('/login.php');
    }
}

function redirect_after_login(): void
{
    $user = current_user();

    if (!$user) {
        redirect('/login.php');
    }

    switch ($user['role']) {
        case 'admin':
            redirect('/admin/dashboard.php');
            break;
        case 'caterer':
            if ($user['status'] === 'pending') {
                redirect('/caterer/pending.php');
            }
            redirect('/caterer/dashboard.php');
            break;
        default:
            redirect('/customer/dashboard.php');
    }
}
