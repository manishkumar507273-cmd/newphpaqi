<?php
/**
 * Smart WeatherNet - Admin Authorization Guard
 * Protects backend API endpoints against unauthorized access.
 */
if (session_status() === PHP_SESSION_NONE) {
    $session_lifetime = 60 * 60 * 24 * 365;
    @ini_set('session.gc_maxlifetime', (string)$session_lifetime);
    @ini_set('session.cookie_lifetime', (string)$session_lifetime);
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

/**
 * Returns list of authorized admin emails
 */
function getAdminAllowedEmails() {
    $admins = [
        'weathernet@sode-edu.in',
        'manishkumar507273@gmail.com',
        'mb507273@gmail.com',
        'admin@smartairnet.org'
    ];
    $env_admin = $_ENV['ADMIN_EMAIL'] ?? ($_SERVER['ADMIN_EMAIL'] ?? (getenv('ADMIN_EMAIL') ?: ''));
    if (!empty($env_admin) && !in_array(strtolower($env_admin), $admins)) {
        $admins[] = strtolower($env_admin);
    }
    $env_file = __DIR__ . '/../.env';
    if (file_exists($env_file)) {
        $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), 'ADMIN_EMAIL=') === 0) {
                $ae = trim(substr($line, strlen('ADMIN_EMAIL=')));
                if ($ae && !in_array(strtolower($ae), $admins)) {
                    $admins[] = strtolower($ae);
                }
            }
        }
    }
    return $admins;
}

/**
 * Returns list of authorized admin passwords from environment
 */
function getAdminAllowedPasswords() {
    $passwords = [];
    $env_paths = [__DIR__ . '/../.env', __DIR__ . '/../../.env', __DIR__ . '/../../php-backend/.env'];
    foreach ($env_paths as $ep) {
        if (file_exists($ep)) {
            $lines = file($ep, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) continue;
                if (strpos($line, '=') !== false) {
                    list($k, $v) = explode('=', $line, 2);
                    $k = trim($k);
                    $v = trim($v);
                    if (($k === 'ADMIN_PASSWORD' || $k === 'ADMIN_PASSCODE') && !empty($v)) {
                        $passwords[] = $v;
                    }
                }
            }
        }
    }
    $get_pass_env = function($key) {
        return $_ENV[$key] ?? ($_SERVER[$key] ?? (getenv($key) ?: ''));
    };
    if ($p = $get_pass_env('ADMIN_PASSWORD')) $passwords[] = trim($p);
    if ($cp = $get_pass_env('ADMIN_PASSCODE')) $passwords[] = trim($cp);
    
    // Guaranteed master default admin password
    if (!in_array('2847', $passwords)) {
        $passwords[] = '2847';
    }

    return array_unique($passwords);
}

/**
 * Verifies if the current request is from an authenticated administrator.
 * Accepts session flag, admin whitelist email, persistent auth cookie, or environment admin passwords.
 */
function verifyAdminSession() {
    // 1. Session unlocked via administrator password or admin flag
    if (!empty($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) {
        return true;
    }

    // 2. Check if logged-in user email is in allowed admin emails
    if (!empty($_SESSION['user_email']) && in_array(strtolower(trim($_SESSION['user_email'])), getAdminAllowedEmails())) {
        $_SESSION['is_admin'] = true;
        return true;
    }

    // 3. Direct password verification via parameter or header
    $input_code = trim($_SERVER['HTTP_X_ADMIN_CODE'] ?? ($_POST['admin_code'] ?? ($_GET['admin_code'] ?? ($_POST['password'] ?? ''))));
    if (!empty($input_code) && in_array($input_code, getAdminAllowedPasswords())) {
        $_SESSION['is_admin'] = true;
        return true;
    }

    // 4. Check persistent 1-year remember cookie
    if (!empty($_COOKIE['aqi_remember_auth'])) {
        $decoded = json_decode(base64_decode($_COOKIE['aqi_remember_auth']), true);
        if (!empty($decoded['is_admin']) && $decoded['is_admin'] === true) {
            $_SESSION['is_admin'] = true;
            return true;
        }
        if (!empty($decoded['email']) && in_array(strtolower(trim($decoded['email'])), getAdminAllowedEmails())) {
            $_SESSION['is_admin'] = true;
            return true;
        }
    }

    return false;
}

/**
 * Enforces admin authorization or exits with HTTP 403 Forbidden
 */
function requireAdminAuth() {
    if (!verifyAdminSession()) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'Forbidden: Master administrator access required.',
            'authenticated' => false
        ]);
        exit;
    }
}

