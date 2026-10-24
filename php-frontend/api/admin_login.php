<?php
/**
 * Smart WeatherNet - Admin Authentication Endpoint
 * Verifies the administrator master password configured via ADMIN_PASSWORD in environment.
 */
$session_lifetime = 60 * 60 * 24 * 365;
ini_set('session.gc_maxlifetime', (string)$session_lifetime);
ini_set('session.cookie_lifetime', (string)$session_lifetime);

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => $session_lifetime,
        'path' => '/',
        'domain' => '',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}
header('Content-Type: application/json');
require_once __DIR__ . '/auth_guard.php';

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$password = trim($input['password'] ?? ($input['admin_code'] ?? ''));

if (!empty($password) && in_array($password, getAdminAllowedPasswords())) {
    $_SESSION['is_admin'] = true;
    $_SESSION['user_id'] = 'admin';
    $_SESSION['user_name'] = 'Admin';
    $_SESSION['user_email'] = 'admin@smartairnet.org';

    // Set 1-year persistent cookie so returning admins are recognized
    $cookie_payload = base64_encode(json_encode([
        'id' => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'],
        'email' => $_SESSION['user_email'],
        'is_admin' => true,
        'time' => time()
    ]));
    setcookie('aqi_remember_auth', $cookie_payload, [
        'expires' => time() + (86400 * 365),
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => false,
        'samesite' => 'Lax'
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Administrator session unlocked successfully.'
    ]);
    exit;
}

http_response_code(401);
echo json_encode([
    'success' => false,
    'error' => 'Incorrect administrator password.'
]);
exit;

