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

// Restore session from persistent 1-year cookie if session file was flushed or device reopened
if (empty($_SESSION['user_id']) && !empty($_COOKIE['aqi_remember_auth']) && !isset($_GET['logout'])) {
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
            if (!empty($decoded['is_admin']) || strpos(strtolower($_SESSION['user_name']), 'admin') !== false) {
                $_SESSION['is_admin'] = true;
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
        $_SESSION['user_id'] = 'guest';
        $_SESSION['user_name'] = $raw_name ?: ($raw_email ? explode('@', $raw_email)[0] : 'User');
        $_SESSION['user_email'] = $raw_email;
        if (strpos(strtolower($_SESSION['user_name']), 'admin') !== false) {
            $_SESSION['is_admin'] = true;
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
    session_destroy();
    setcookie('aqi_remember_auth', '', time() - 86400, '/');
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

$is_logged_in = isset($_SESSION['user_id']);
$is_ajax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || isset($_GET['ajax']);

if ($page === 'admin' && !$is_logged_in) {
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode([
            'require_login' => true,
            'redirect' => '?page=overview&require_login=true'
        ]);
        exit;
    }
    header("Location: ?page=overview&require_login=true");
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
    <meta name="viewport" id="viewport-meta" content="width=1200, initial-scale=0.35, minimum-scale=0.2, maximum-scale=3.0, user-scalable=yes">
    <script>
        // Ensure mobile devices render the identical desktop layout (scaled edge-to-edge like laptop)
        (function() {
            function updateDesktopViewport() {
                var meta = document.getElementById('viewport-meta');
                if (!meta) return;
                var sw = window.screen.width;
                if (sw < 1200) {
                    var scale = (sw / 1200).toFixed(4);
                    meta.setAttribute('content', 'width=1200, initial-scale=' + scale + ', minimum-scale=0.1, maximum-scale=3.0, user-scalable=yes');
                } else {
                    meta.setAttribute('content', 'width=1200, initial-scale=1.0, maximum-scale=3.0, user-scalable=yes');
                }
            }
            updateDesktopViewport();
            window.addEventListener('resize', updateDesktopViewport);
            window.addEventListener('orientationchange', updateDesktopViewport);
        })();

        // Fast code update detector: checks on window/tab focus without lagging the server
        (function() {
            var lastMtime = null;
            var isChecking = false;

            async function checkCodeUpdate() {
                if (isChecking) return;
                isChecking = true;
                try {
                    var res = await fetch('api/check_update.php?_t=' + Date.now(), { cache: 'no-store' });
                    var data = await res.json();
                    if (data && data.latest_mtime) {
                        if (lastMtime !== null && data.latest_mtime > lastMtime) {
                            console.log('🔄 Code changes detected. Auto-refreshing view...');
                            window.location.reload();
                            return;
                        }
                        lastMtime = data.latest_mtime;
                    }
                } catch (e) {
                } finally {
                    isChecking = false;
                }
            }

            // Immediately check when user switches focus to the browser window/tab
            window.addEventListener('focus', checkCodeUpdate);
            document.addEventListener('visibilitychange', function() {
                if (!document.hidden) {
                    checkCodeUpdate();
                }
            });
            // Initial check once and interval every 4 seconds
            checkCodeUpdate();
            setInterval(checkCodeUpdate, 4000);
        })();
    </script>
    <meta name="theme-color" content="#f1f5f9">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="mobile-web-app-capable" content="yes">
    <title>Smart WeatherNet | Real-Time Air Quality & Weather Monitor</title>
    
    <!-- Google Fonts -->
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

    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/index.css">

    <?php
    $initial_overview_json = 'null';
    if ($page === 'overview') {
        $cache_file = sys_get_temp_dir() . '/smartairnet_cache/' . md5('overview_bulk') . '.json';
        if (file_exists($cache_file)) {
            $raw_cache = @file_get_contents($cache_file);
            if ($raw_cache && strlen($raw_cache) > 50) {
                $initial_overview_json = $raw_cache;
            }
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

    <!-- Load custom JavaScript -->
    <script src="js/api.js"></script>
    <script src="js/router.js"></script>
    <script>
        // Initialize Lucide icons
        lucide.createIcons();
    </script>
</body>
</html>
