<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('Asia/Kolkata');

// Load Supabase and Environment Configuration
$env_paths = [
    __DIR__ . '/.env',
    __DIR__ . '/../.env',
    __DIR__ . '/../../.env',
];
$supabaseUrl = 'https://sgkdpliqlhgiqsabxzxe.supabase.co';
$supabaseKey = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InNna2RwbGlxbGhnaXFzYWJ4enhlIiwicm9sZSI6ImFub24iLCJpYXQiOjE3ODQ4NzgzMTIsImV4cCI6MjEwMDQ1NDMxMn0.vMtbXomFdmOcBkhhSoiyYyp_vFxOhg4MYCFCw9-pL30';
$TABLE_AQI_LIVE = 'AQI_LIVE_NODE1';
$TABLE_AQI_HISTORICAL = 'AQI_NODE1';
$TABLE_WEATHER_LIVE = 'WEATHER_LIVE_NODE1';
$TABLE_WEATHER_HIST = 'WEATHER_NODE1';

// Node Configuration Architecture
require_once __DIR__ . '/config/nodes.php';
$active_node = smartairnet_get_active_node_id();
$active_node_id = $active_node;
$active_node_config = smartairnet_get_active_node_config();
$is_node_2 = ($active_node === '2');

$supabaseUrlNode1 = 'https://sgkdpliqlhgiqsabxzxe.supabase.co';
$supabaseKeyNode1 = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InNna2RwbGlxbGhnaXFzYWJ4enhlIiwicm9sZSI6ImFub24iLCJpYXQiOjE3ODQ4NzgzMTIsImV4cCI6MjEwMDQ1NDMxMn0.vMtbXomFdmOcBkhhSoiyYyp_vFxOhg4MYCFCw9-pL30';
$supabaseUrlNode2 = getenv('SUPABASE_URL_NODE2') ?: (getenv('VITE_SUPABASE_URL_NODE2') ?: 'https://nahabhzcaeppbteuxogg.supabase.co');
$supabaseKeyNode2 = getenv('SUPABASE_KEY_NODE2') ?: (getenv('SUPABASE_ANON_KEY_NODE2') ?: (getenv('VITE_SUPABASE_ANON_KEY_NODE2') ?: ''));

foreach ($env_paths as $ep) {
    if (file_exists($ep)) {
        $lines = file($ep, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            if (strpos($line, '=') !== false) {
                list($k, $v) = explode('=', $line, 2);
                $k = trim($k); $v = trim($v);
                if (($k === 'VITE_SUPABASE_URL' || $k === 'SUPABASE_URL') && $v) $supabaseUrlNode1 = $v;
                if (($k === 'VITE_SUPABASE_ANON_KEY' || $k === 'SUPABASE_KEY') && $v) $supabaseKeyNode1 = $v;
                if (($k === 'VITE_SUPABASE_URL_NODE2' || $k === 'SUPABASE_URL_NODE2') && $v) $supabaseUrlNode2 = $v;
                if (($k === 'SUPABASE_KEY_NODE2' || $k === 'SUPABASE_ANON_KEY_NODE2' || $k === 'VITE_SUPABASE_ANON_KEY_NODE2') && $v) $supabaseKeyNode2 = $v;
            }
        }
    }
}

if ($is_node_2) {
    $supabaseUrl = $supabaseUrlNode2;
    $supabaseKey = $supabaseKeyNode2;
    $TABLE_AQI_LIVE = 'AQI_LIVE_NODE2';
    $TABLE_AQI_HISTORICAL = 'AQI_NODE2';
    $TABLE_WEATHER_LIVE = 'SOIL_LIVE_NODE2';
    $TABLE_WEATHER_HIST = 'SOIL_NODE2';
} else {
    $supabaseUrl = $supabaseUrlNode1;
    $supabaseKey = $supabaseKeyNode1;
    $TABLE_AQI_LIVE = 'AQI_LIVE_NODE1';
    $TABLE_AQI_HISTORICAL = 'AQI_NODE1';
    $TABLE_WEATHER_LIVE = 'WEATHER_LIVE_NODE1';
    $TABLE_WEATHER_HIST = 'WEATHER_NODE1';
}

if (!empty($active_node_config['tables']['aqi_live'])) $TABLE_AQI_LIVE = $active_node_config['tables']['aqi_live'];
if (!empty($active_node_config['tables']['aqi_historical'])) $TABLE_AQI_HISTORICAL = $active_node_config['tables']['aqi_historical'];
if (!empty($active_node_config['tables']['weather_live'])) $TABLE_WEATHER_LIVE = $active_node_config['tables']['weather_live'];
if (!empty($active_node_config['tables']['weather_historical'])) $TABLE_WEATHER_HIST = $active_node_config['tables']['weather_historical'];
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
    }

// Restore session from persistent 1-year cookie if session file was flushed or device reopened
if ((empty($_SESSION['user_id']) || empty($_SESSION['user_email'])) && !empty($_COOKIE['aqi_remember_auth']) && !isset($_GET['logout']) && !isset($_GET['logged_out'])) {
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
                    }
    }
}

// Automatically refresh and keep the 1-year cookie active for all returning logged-in visitors
if (!empty($_SESSION['user_email']) && !isset($_GET['logout']) && !isset($_GET['logged_out'])) {
    $cookie_payload = base64_encode(json_encode([
        'id' => $_SESSION['user_id'] ?? 'guest',
        'name' => $_SESSION['user_name'] ?? explode('@', $_SESSION['user_email'])[0],
        'email' => $_SESSION['user_email'],
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
        $_SESSION['user_id'] = 'guest';
        $_SESSION['user_name'] = $raw_name ?: (explode('@', $raw_email)[0] ?? 'User');
        $_SESSION['user_email'] = $raw_email;

        if ($raw_email) {
            // Set 1-year persistent cookie so returning users are automatically recognized
            $cookie_payload = base64_encode(json_encode([
                'id' => $_SESSION['user_id'],
                'name' => $_SESSION['user_name'],
                'email' => $_SESSION['user_email'],
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
$allowed_pages = ['overview', 'live', 'historical', 'predictive', 'weather', 'live-aqi', 'live-weather'];

if (!in_array($page, $allowed_pages)) {
    $page = 'overview';
}

// Alias split pages or weather to single live feed
if ($page === 'live-aqi') {
    $_GET['tab'] = 'aqi';
    $page = 'live';
} elseif ($page === 'weather' || $page === 'live-weather') {
    $_GET['tab'] = 'weather';
    $page = 'live';
}

$is_logged_in = !empty($_SESSION['user_id']) || !empty($_SESSION['user_email']);
$is_ajax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || isset($_GET['ajax']);

// Pages requiring user login; overview is freely accessible to all guests
$protected_pages = ['live', 'historical', 'predictive', 'weather', 'live-aqi', 'live-weather'];

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
    $node_folder = ($active_node_id === '2') ? 'node 2' : 'node 1';
    $page_file = __DIR__ . '/' . $node_folder . '/' . $page . '.php';
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
    <meta name="theme-color" content="#f0f7ff" media="(prefers-color-scheme: light)">
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

    <!-- CDN Preconnects for faster asset resolution — placed early for maximum benefit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdn.tailwindcss.com">
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="preconnect" href="https://unpkg.com">
    <link rel="dns-prefetch" href="https://fonts.googleapis.com">
    <link rel="dns-prefetch" href="https://cdn.jsdelivr.net">
    <link rel="dns-prefetch" href="https://unpkg.com">

    <!-- Google Fonts: load non-blocking via media=print swap trick -->
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=JetBrains+Mono:wght@300;400;500;600;700;800&family=Inter:wght@400;500;600;700;800;900&display=swap"
          rel="stylesheet" media="print" onload="this.media='all'">
    <noscript>
        <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=JetBrains+Mono:wght@300;400;500;600;700;800&family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    </noscript>

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

    <!-- Chart.js — defer to avoid blocking HTML parse -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js" defer></script>

    <!-- Lucide Icons — defer; createIcons() called after DOMContentLoaded -->
    <script src="https://unpkg.com/lucide@latest" defer></script>

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
    $cache_dir = sys_get_temp_dir() . '/smartairnet_cache';
    $candidates = $is_node_2 ? [
        $cache_dir . '/' . md5('overview_bulk_node_2') . '.json',
        $cache_dir . '/' . md5('live_bulk_node_2_60') . '.json',
        $cache_dir . '/' . md5('live_bulk_node_2_50') . '.json',
        $cache_dir . '/last_known_hero_node_2.json',
    ] : [
        $cache_dir . '/' . md5('overview_bulk') . '.json',
        $cache_dir . '/' . md5('live_bulk_60') . '.json',
        $cache_dir . '/' . md5('live_bulk_50') . '.json',
        $cache_dir . '/last_known_hero.json',
    ];
    $best_epoch = 0;
    foreach ($candidates as $cf) {
        if (file_exists($cf)) {
            $raw_c = @file_get_contents($cf);
            if ($raw_c && strlen($raw_c) > 20) {
                $parsed = @json_decode($raw_c, true);
                if ($parsed && (!empty($parsed['aqi']) || !empty($parsed['weather']))) {
                    if (isset($parsed['cpcb_aqi']) && !isset($parsed['aqi'])) {
                        $parsed = ['aqi' => $parsed, 'history' => [$parsed], 'aqi_history' => [$parsed]];
                        $raw_c = json_encode($parsed);
                    }
                    $aqi_item = !empty($parsed['aqi']) ? (isset($parsed['aqi'][0]) ? $parsed['aqi'][0] : $parsed['aqi']) : null;
                    $ts_val = $aqi_item['timestamp'] ?? $aqi_item['created_at'] ?? null;
                    $e = $ts_val ? strtotime($ts_val) : (isset($parsed['time']) ? (int)$parsed['time'] : filemtime($cf));
                    if ($e > $best_epoch) {
                        $best_epoch = $e;
                        $initial_overview_json = $raw_c;
                    }
                }
            }
        }
    }
    ?>
    <script>
        window.__selectedNode = <?= json_encode($active_node) ?>;
        window.__nodeConfig = <?= json_encode($active_node_config) ?>;
        // Supabase configuration for instant direct browser telemetry streaming
        window.SUPABASE_CONFIG = {
            url: <?= json_encode($supabaseUrl) ?>,
            key: <?= json_encode($supabaseKey) ?>,
            tableAqiLive: <?= json_encode($TABLE_AQI_LIVE) ?>,
            tableWeatherLive: <?= json_encode($TABLE_WEATHER_LIVE) ?>,
            tableAqiHist: <?= json_encode($TABLE_AQI_HISTORICAL) ?>,
            tableWeatherHist: <?= json_encode($TABLE_WEATHER_HIST) ?>
        };
        // Server pre-hydrated telemetry cache (0ms instant first-paint)
        window.INITIAL_DATA = <?= $initial_overview_json ?>;
    </script>
</head>
<body class="bg-[#f0f7ff] text-[#0f172a] font-sans antialiased">
    
    <?php include __DIR__ . '/components/layout.php'; ?>

    <!-- Load Client Router & Icon Initializer -->
    <script src="js/router.js?v=<?= $router_v ?>"></script>
    <script>
        // Initialize Lucide icons — wait for deferred script if not yet available
        function initLucideIcons() {
            if (window.lucide && lucide.createIcons) {
                lucide.createIcons();
            }
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initLucideIcons);
        } else {
            initLucideIcons();
        }
    </script>
</body>
</html>
