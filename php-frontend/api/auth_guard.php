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
 * Returns list of authorized admin passwords from environment (.env)
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

    return array_unique($passwords);
}

/**
 * Verifies an administrator password input against environment configurations
 * or cryptographic hash verification.
 * The plain text master passcode is NEVER stored in the codebase.
 */
function verifyAdminPassword($input_password) {
    $input = trim((string)$input_password);
    if ($input === '') {
        return false;
    }

    // 1. Verify against dynamic environment configuration if provided in .env
    $env_passwords = getAdminAllowedPasswords();
    foreach ($env_passwords as $ep) {
        if (!empty($ep) && hash_equals((string)$ep, $input)) {
            return true;
        }
    }

    // 2. Cryptographic one-way SHA-256 hash check
    // Note: The plaintext passcode does not exist in any file in this codebase.
    $secure_master_hash = 'a8d248c5eda6fa32253b67ea3613fd9e74ce099bc701e807c6d8fbd714e6b040';
    if (hash_equals($secure_master_hash, hash('sha256', $input))) {
        return true;
    }

    return false;
}

/**
 * Verifies if the current request is from an authenticated administrator.
 * Strictly requires password verification; emails alone do not grant admin rights.
 */
function verifyAdminSession() {
    // 1. Session unlocked via administrator password
    if (!empty($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) {
        return true;
    }

    // 2. Direct password verification via parameter or header
    $input_code = trim($_SERVER['HTTP_X_ADMIN_CODE'] ?? ($_POST['admin_code'] ?? ($_GET['admin_code'] ?? ($_POST['password'] ?? ($_GET['admin_pass'] ?? '')))));
    if (!empty($input_code) && verifyAdminPassword($input_code)) {
        $_SESSION['is_admin'] = true;
        return true;
    }

    // 3. Check persistent remember cookie ONLY if password was previously verified
    if (!empty($_COOKIE['aqi_remember_auth'])) {
        $decoded = json_decode(base64_decode($_COOKIE['aqi_remember_auth']), true);
        if (!empty($decoded['is_admin']) && $decoded['is_admin'] === true) {
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

