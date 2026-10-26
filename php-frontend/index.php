<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('Asia/Kolkata');
// Configure 1-year persistent session cookies (365 days) for mobile and laptop re-entry
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

header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Cleanse legacy mock data immediately from active session
if (!empty($_SESSION['user_email']) && ($_SESSION['user_email'] === 'manishb@sode-edu.in' || $_SESSION['user_email'] === 'manish@sode-edu.in')) {
    unset($_SESSION['user_email']);
}
if (!empty($_SESSION['user_name']) && $_SESSION['user_name'] === 'Manish B') {
    unset($_SESSION['user_name']);
    if (isset($_SESSION['user_id']) && $_SESSION['user_id'] === 'guest' && empty($_SESSION['user_email'])) {
        unset($_SESSION['user_id']);
    }
}

require_once __DIR__ . '/api/auth_guard.php';

// Direct admin bypass unlock if passed via query string with verified password
if (isset($_GET['admin_pass'])) {
    if (verifyAdminPassword($_GET['admin_pass'])) {
        $_SESSION['is_admin'] = true;
        if (empty($_SESSION['user_id']) || (!empty($_SESSION['user_email']) && $_SESSION['user_email'] === 'admin@smartairnet.org')) {
            $_SESSION['user_id'] = 'admin';
            $_SESSION['user_name'] = 'Admin';
            $_SESSION['user_email'] = 'admin@smartairnet.org';
        }
    }
    // Clean admin_pass from URL immediately so password is never exposed in browser address bar
    $clean_target = isset($_GET['page']) ? $_GET['page'] : 'admin';
    header("Location: ?page=" . urlencode($clean_target));
    exit;
}

// Clear persistent cookie if user just logged out
if (isset($_GET['logout']) || isset($_GET['logged_out'])) {
    $is_secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    setcookie('aqi_remember_auth', '', [
        'expires' => time() - 86400,
        'path' => '/',
        'secure' => $is_secure,
        'httponly' => false,
        'samesite' => 'Lax'
    ]);
    setcookie('aqi_remember_auth', '', time() - 86400, '/');
    unset($_COOKIE['aqi_remember_auth']);
    unset($_SESSION['is_admin']);
}

// Restore session from persistent 1-year cookie if session file was flushed or device reopened
if (empty($_SESSION['user_id']) && !empty($_COOKIE['aqi_remember_auth']) && !isset($_GET['logout']) && !isset($_GET['logged_out'])) {
    $decoded = json_decode(base64_decode($_COOKIE['aqi_remember_auth']), true);
    if (!empty($decoded) && !empty($decoded['email'])) {
        $em = trim($decoded['email']);
        if ($em === 'manishb@sode-edu.in' || $em === 'manish@sode-edu.in') {
            // Purge legacy mock cookie
            setcookie('aqi_remember_auth', '', time() - 86400, '/');
        } else {
            $_SESSION['user_id'] = !empty($decoded['id']) ? $decoded['id'] : 'guest';
            $_SESSION['user_name'] = !empty($decoded['name']) ? $decoded['name'] : explode('@', $em)[0];
            $_SESSION['user_email'] = $em;
            if (!empty($decoded['is_admin']) && $decoded['is_admin'] === true && strtolower($em) === 'admin@smartairnet.org') {
                $_SESSION['is_admin'] = true;
            } else {
                $_SESSION['is_admin'] = false;
                unset($_SESSION['is_admin']);
            }
        }
    }
}

// Auth handling: saves real authenticated session and writes persistent cookie
if (isset($_GET['auth']) && $_GET['auth'] === 'true') {
    $raw_email = !empty($_GET['email']) ? trim($_GET['email']) : '';
    $raw_name = !empty($_GET['name']) ? trim($_GET['name']) : '';

    // Ignore legacy mock email or mock name if passed
    if ($raw_email === 'manishb@sode-edu.in' || $raw_email === 'manish@sode-edu.in') {
        $raw_email = '';
    }
    if ($raw_name === 'Manish B' || $raw_name === 'Manish') {
        $raw_name = '';
    }

    if ($raw_email || $raw_name) {
        $is_personal_user = (!empty($raw_email) && strtolower($raw_email) !== 'admin@smartairnet.org');

        if ($is_personal_user) {
            // Personal email accounts are strictly NEVER admins
            $_SESSION['is_admin'] = false;
            unset($_SESSION['is_admin']);
            $_SESSION['user_id'] = 'guest';
            $_SESSION['user_name'] = $raw_name ?: explode('@', $raw_email)[0];
            $_SESSION['user_email'] = $raw_email;
        } else if (isset($_GET['admin_pass']) && verifyAdminPassword($_GET['admin_pass'])) {
            $_SESSION['is_admin'] = true;
            $_SESSION['user_id'] = 'admin';
            $_SESSION['user_name'] = 'Admin';
            $_SESSION['user_email'] = 'admin@smartairnet.org';
        } else {
            $_SESSION['is_admin'] = false;
            unset($_SESSION['is_admin']);
            $_SESSION['user_id'] = 'guest';
            $_SESSION['user_name'] = $raw_name ?: 'User';
            $_SESSION['user_email'] = $raw_email;
        }

        if ($raw_email) {
            // Set 1-year persistent cookie so returning users are automatically recognized
            $cookie_payload = base64_encode(json_encode([
                'id' => $_SESSION['user_id'],
                'name' => $_SESSION['user_name'],
                'email' => $_SESSION['user_email'],
                'is_admin' => !empty($_SESSION['is_admin']),
                'time' => time()
            ]));
            setcookie('aqi_remember_auth', $cookie_payload, [
                'expires' => time() + (86400 * 365),
                'path' => '/',
                'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
                'httponly' => false,
                'samesite' => 'Lax'
            ]);
        }
    }

    // Check if AJAX background call
    if ((isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || isset($_GET['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'name' => $_SESSION['user_name'], 'email' => $_SESSION['user_email']]);
        exit;
    }

    // Clean up the URL by stripping auth=true, but keeping the page parameter
    $target_page = isset($_GET['page']) ? $_GET['page'] : 'overview';
    if ($target_page === 'admin' && empty($_SESSION['is_admin'])) {
        $target_page = 'overview';
    }
    session_write_close();
    header("Location: ?page=" . $target_page);
    exit;
}

if (isset($_GET['logout']) && $_GET['logout'] === 'true') {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    $is_secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    setcookie('aqi_remember_auth', '', [
        'expires' => time() - 86400,
        'path' => '/',
        'secure' => $is_secure,
        'httponly' => false,
        'samesite' => 'Lax'
    ]);
    setcookie('aqi_remember_auth', '', time() - 86400, '/');
    unset($_COOKIE['aqi_remember_auth']);
    header("Location: ?page=overview&logged_out=true");
    exit;
}

// Simple query string router
$page = isset($_GET['page']) ? $_GET['page'] : 'overview';

// Allowed pages to prevent directory traversal
$allowed_pages = ['overview', 'live', 'historical', 'weather', 'admin', 'predictive'];

if (!in_array($page, $allowed_pages)) {
    $page = 'overview';
}

$is_logged_in = isset($_SESSION['user_id']) || (!empty($_SESSION['is_admin']) && $_SESSION['is_admin'] === true);
$is_ajax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || isset($_GET['ajax']);

// Pages requiring user login; admin page handles its own dedicated gate and session auth
$protected_pages = ['live', 'historical', 'predictive', 'weather'];

if (in_array($page, $protected_pages) && !$is_logged_in) {
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode([
            'require_login' => true,
            'target_page' => $page,
            'redirect' => '?page=overview&require_login=true&target=' . urlencode($page)
        ]);
        exit;
    }
    header("Location: ?page=overview&require_login=true&target=" . urlencode($page));
    exit;
}

if ($is_ajax) {
    ob_start();
    $page_file = __DIR__ . '/pages/' . $page . '.php';
    if (file_exists($page_file)) {
        include $page_file;
    } else {
        echo "<div style='padding: 40px; text-align: center; color: #64748b;'>Page not found. (" . htmlspecialchars($page) . ")</div>";
    }
    $page_content = ob_get_clean();

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'page' => $page,
        'title' => 'Smart WeatherNet | Real-Time Air Quality & Weather Monitor',
        'content' => $page_content,
        'is_logged_in' => $is_logged_in,
        'user_name' => isset($_SESSION['user_name']) ? $_SESSION['user_name'] : null
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, viewport-fit=cover">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#f1f5f9" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#0f172a" media="(prefers-color-scheme: dark)">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="WeatherNet">
    <meta name="mobile-web-app-capable" content="yes">
    <title>Smart WeatherNet | Real-Time Air Quality & Weather Monitor</title>
    
    <!-- Official Smart WeatherNet Favicon & App Icons -->
    <link rel="icon" type="image/png" sizes="64x64" href="assets/favicon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/favicon.png">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/apple-touch-icon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=JetBrains+Mono:wght@300;400;500;600;700;800&family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS (via CDN for development) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#00bfa5',
                        'primary-light': '#00d6b9',
                        'bg-main': '#f1f5f9',
                    }
                }
            }
        }
    </script>

    <!-- CDN Preconnects for faster asset resolution -->
    <link rel="preconnect" href="https://cdn.tailwindcss.com">
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="preconnect" href="https://unpkg.com">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <?php
    $css_v = file_exists(__DIR__ . '/css/index.css') ? substr(md5_file(__DIR__ . '/css/index.css'), 0, 8) : '1.1';
    $api_v = file_exists(__DIR__ . '/js/api.js') ? substr(md5_file(__DIR__ . '/js/api.js'), 0, 8) : '1.1';
    $router_v = file_exists(__DIR__ . '/js/router.js') ? substr(md5_file(__DIR__ . '/js/router.js'), 0, 8) : '1.1';
    ?>
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/index.css?v=<?= $css_v ?>">

    <!-- Core Telemetry & API Engine (Available before page scripts run) -->
    <script src="js/api.js?v=<?= $api_v ?>"></script>

    <?php
    $initial_overview_json = 'null';
    $cache_file = sys_get_temp_dir() . '/smartairnet_cache/' . md5('overview_bulk') . '.json';
    if (file_exists($cache_file)) {
        $raw_cache = @file_get_contents($cache_file);
        if ($raw_cache && strlen($raw_cache) > 50) {
            $initial_overview_json = $raw_cache;
        }
    }
    ?>
    <script>
        // Server pre-hydrated telemetry cache (0ms instant first-paint)
        window.INITIAL_DATA = <?= $initial_overview_json ?>;
    </script>
</head>
<body class="bg-[#f1f5f9] text-[#0f172a] font-sans antialiased">
    
    <?php include __DIR__ . '/components/layout.php'; ?>

    <!-- Load Client Router & Icon Initializer -->
    <script src="js/router.js?v=<?= $router_v ?>"></script>
    <script>
        // Initialize Lucide icons
        lucide.createIcons();
    </script>
</body>
</html>
