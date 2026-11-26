<?php
require_once __DIR__ . '/brand-assets.php';
require_once __DIR__ . '/../config/nodes.php';

$selected_node = smartairnet_get_active_node_id();
$active_node_id = $selected_node;
$node_config = smartairnet_get_node_config($selected_node);
$active_node_cfg = $node_config;
$is_node_2 = ($selected_node === '2');

$allowed_pages = ['overview', 'live', 'historical', 'predictive', 'weather', 'live-aqi', 'live-weather', 'soil', 'live-soil', 'soil-historical'];
$current_page = (isset($_GET['page']) && in_array($_GET['page'], $allowed_pages)) ? $_GET['page'] : 'overview';

// Alias split pages or weather/soil to dedicated feeds
if ($current_page === 'live-aqi') {
    $_GET['tab'] = 'aqi';
    $current_page = 'live';
} elseif ($current_page === 'weather' || $current_page === 'live-weather') {
    $_GET['tab'] = 'weather';
    $current_page = 'live';
} elseif ($current_page === 'soil' || $current_page === 'live-soil') {
    $_GET['tab'] = 'soil';
    $current_page = 'live';
} elseif ($current_page === 'soil-historical') {
    $_GET['tab'] = 'soil';
    $current_page = 'historical';
}

$is_live_page_active = in_array($current_page, ['live', 'live-aqi', 'live-weather', 'soil', 'live-soil']);

$is_logged_in = !empty($_SESSION['user_id']) || !empty($_SESSION['user_email']);
$user_display_name = !empty($_SESSION['user_name']) ? $_SESSION['user_name'] : (!empty($_SESSION['user_email']) ? explode('@', $_SESSION['user_email'])[0] : 'User');
$user_email = $_SESSION['user_email'] ?? '';
$user_initial = strtoupper(substr($user_display_name, 0, 1));

// Check server-side telemetry cache to determine if cloud data is currently live (received <= 6 mins)
$is_telemetry_live = true;
$telemetry_last_updated_time = '';
$telemetry_cache_file = sys_get_temp_dir() . '/smartairnet_cache/' . md5($is_node_2 ? 'overview_bulk_node_2' : 'overview_bulk') . '.json';
if (!file_exists($telemetry_cache_file)) {
    $telemetry_cache_file = sys_get_temp_dir() . '/smartairnet_cache/' . md5($is_node_2 ? 'live_bulk_node_2_60' : 'live_bulk_60') . '.json';
}
if (file_exists($telemetry_cache_file) && (time() - filemtime($telemetry_cache_file)) < 25) {
    $raw_cache = @file_get_contents($telemetry_cache_file);
    if ($raw_cache && strlen($raw_cache) > 50) {
        $cached_data = json_decode($raw_cache, true);
        $aqi_ts = $cached_data['aqi']['timestamp'] ?? ($cached_data['aqi']['created_at'] ?? null);
        $weather_ts = $cached_data['weather']['timestamp'] ?? ($cached_data['weather']['created_at'] ?? null);
        $aqi_epoch = $aqi_ts ? strtotime($aqi_ts) : 0;
        $weather_epoch = $weather_ts ? strtotime($weather_ts) : 0;
        $latest_epoch = max($aqi_epoch, $weather_epoch);
        if ($latest_epoch > 0) {
            $telemetry_last_updated_time = date('h:i A', $latest_epoch);
            $diff_seconds = time() - $latest_epoch;
            $is_telemetry_live = ($diff_seconds >= -600 && $diff_seconds <= 5400);
        }
    }
}
$node_status_label = $is_telemetry_live ? 'LIVE' : 'OFFLINE';
$node_status_bg = $is_telemetry_live ? '#dcfce7' : '#fff7ed';
$node_status_color = $is_telemetry_live ? '#15803d' : '#c2410c';
$node_status_border = $is_telemetry_live ? '#bbf7d0' : '#fed7aa';
$node_status_dot = $is_telemetry_live ? '#16a34a' : '#f97316';

$page_titles = [
    'overview'        => 'Atmosphere Snapshot',
    'live'            => 'Live Data Stream',
    'live-aqi'        => 'Live AQI Telemetry',
    'live-weather'    => 'Live Weather Telemetry',
    'soil'            => 'Live Soil Telemetry',
    'live-soil'       => 'Live Soil Telemetry',
    'soil-historical' => 'Soil Telemetry Archive',
    'historical'      => 'Analytics Archive',
    'predictive'      => 'Weather Forecast'
];
$current_title = $page_titles[$current_page] ?? 'Smart WeatherNet';

// Parse credentials from frontend or shared .env
$env_paths = [
    __DIR__ . '/../.env',
    __DIR__ . '/../../.env',
    __DIR__ . '/../../php-backend/.env'
];

$env_config = [
    'firebaseApiKey' => '',
    'firebaseAuthDomain' => '',
    'firebaseProjectId' => '',
    'firebaseDatabaseUrl' => '',
    'supabaseUrl' => '',
    'supabaseKey' => ''
];

foreach ($env_paths as $env_path) {
    if (file_exists($env_path)) {
        $lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value);
                if (($name === 'VITE_FIREBASE_API_KEY' || $name === 'FIREBASE_API_KEY') && empty($env_config['firebaseApiKey'])) $env_config['firebaseApiKey'] = $value;
                if (($name === 'VITE_FIREBASE_AUTH_DOMAIN' || $name === 'FIREBASE_AUTH_DOMAIN') && empty($env_config['firebaseAuthDomain'])) $env_config['firebaseAuthDomain'] = $value;
                if (($name === 'VITE_FIREBASE_PROJECT_ID' || $name === 'FIREBASE_PROJECT_ID') && empty($env_config['firebaseProjectId'])) $env_config['firebaseProjectId'] = $value;
                if (($name === 'FIREBASE_DATABASE_URL' || $name === 'VITE_FIREBASE_DATABASE_URL') && empty($env_config['firebaseDatabaseUrl'])) $env_config['firebaseDatabaseUrl'] = $value;
                if (($name === 'VITE_SUPABASE_URL' || $name === 'SUPABASE_URL') && empty($env_config['supabaseUrl'])) $env_config['supabaseUrl'] = $value;
                if (($name === 'VITE_SUPABASE_ANON_KEY' || $name === 'SUPABASE_KEY') && empty($env_config['supabaseKey'])) $env_config['supabaseKey'] = $value;
            }
        }
        if (!empty($env_config['firebaseApiKey']) &&
            !empty($env_config['firebaseAuthDomain']) &&
            !empty($env_config['firebaseProjectId']) &&
            !empty($env_config['firebaseDatabaseUrl']) &&
            !empty($env_config['supabaseUrl']) &&
            !empty($env_config['supabaseKey'])) {
            break;
        }
    }
}

// Fallback to Vercel/System environment variables if not found in .env file
$get_layout_env = function($key) {
    return $_ENV[$key] ?? ($_SERVER[$key] ?? (getenv($key) ?: ''));
};
if (empty($env_config['supabaseUrl'])) $env_config['supabaseUrl'] = $get_layout_env('VITE_SUPABASE_URL') ?: $get_layout_env('SUPABASE_URL');
if (empty($env_config['supabaseKey'])) $env_config['supabaseKey'] = $get_layout_env('VITE_SUPABASE_ANON_KEY') ?: $get_layout_env('SUPABASE_KEY');
if (empty($env_config['firebaseApiKey'])) $env_config['firebaseApiKey'] = $get_layout_env('VITE_FIREBASE_API_KEY') ?: $get_layout_env('FIREBASE_API_KEY');
if (empty($env_config['firebaseAuthDomain'])) $env_config['firebaseAuthDomain'] = $get_layout_env('VITE_FIREBASE_AUTH_DOMAIN') ?: $get_layout_env('FIREBASE_AUTH_DOMAIN');
if (empty($env_config['firebaseProjectId'])) $env_config['firebaseProjectId'] = $get_layout_env('VITE_FIREBASE_PROJECT_ID') ?: $get_layout_env('FIREBASE_PROJECT_ID');
if (empty($env_config['firebaseDatabaseUrl'])) $env_config['firebaseDatabaseUrl'] = $get_layout_env('FIREBASE_DATABASE_URL') ?: $get_layout_env('VITE_FIREBASE_DATABASE_URL');

?>

<script>
window.ENV_CONFIG = <?= json_encode($env_config) ?>;
window.FIREBASE_ENV_CONFIG = {
    apiKey: window.ENV_CONFIG.firebaseApiKey || "",
    authDomain: window.ENV_CONFIG.firebaseAuthDomain || "",
    projectId: window.ENV_CONFIG.firebaseProjectId || "",
    databaseURL: window.ENV_CONFIG.firebaseDatabaseUrl || "",
    storageBucket: window.ENV_CONFIG.firebaseProjectId ? `${window.ENV_CONFIG.firebaseProjectId}.firebasestorage.app` : "",
    messagingSenderId: "647061464752",
    appId: "1:647061464752:web:349c77286659f3a9d4fd75",
    measurementId: "G-09JTD9RQQJ"
};
window.__currentPage = <?= json_encode($current_page) ?>;
window.__selectedNode = <?= json_encode($active_node_id) ?>;
window.__nodeConfig = <?= json_encode($active_node_cfg) ?>;

window.getActiveNode = function() {
    try {
        var params = new URLSearchParams(window.location.search);
        var urlNode = params.get('node');
        if (urlNode && (urlNode === '1' || urlNode === '2')) return urlNode;
        var ls = localStorage.getItem('smartairnet_selected_node');
        if (ls && (ls === '1' || ls === '2')) return ls;
    } catch(e) {}
    return window.__selectedNode || '1';
};

window.switchActiveNode = function(nodeId) {
    if (nodeId !== '1' && nodeId !== '2') nodeId = '1';
    try {
        localStorage.setItem('smartairnet_selected_node', nodeId);
        document.cookie = "smartairnet_selected_node=" + nodeId + "; path=/; max-age=" + (365 * 86400) + "; SameSite=Lax";
    } catch(e) {}
    
    var url = new URL(window.location.href);
    url.searchParams.set('node', nodeId);
    window.location.href = url.toString();
};

window.toggleNodeDropdown = function(e, target) {
    if (e) {
        e.preventDefault();
        e.stopPropagation();
    }
    var isMobile = (target === 'mobile');
    if (!target && e && e.currentTarget) {
        if (e.currentTarget.id === 'mobile-node-dropdown-trigger' || (e.currentTarget.closest && e.currentTarget.closest('#mobile-node-dropdown-container'))) {
            isMobile = true;
        }
    }
    var menu = document.getElementById(isMobile ? 'mobile-node-dropdown-menu' : 'node-dropdown-menu');
    var chevron = document.getElementById(isMobile ? 'mobile-node-dropdown-chevron' : 'node-dropdown-chevron');
    
    // Close the other one if open
    var otherMenu = document.getElementById(isMobile ? 'node-dropdown-menu' : 'mobile-node-dropdown-menu');
    var otherChevron = document.getElementById(isMobile ? 'node-dropdown-chevron' : 'mobile-node-dropdown-chevron');
    if (otherMenu) otherMenu.style.display = 'none';
    if (otherChevron) otherChevron.style.transform = 'rotate(0deg)';

    if (!menu) return;
    var isOpen = menu.style.display === 'block';
    if (isOpen) {
        menu.style.display = 'none';
        if (chevron) chevron.style.transform = 'rotate(0deg)';
    } else {
        menu.style.display = 'block';
        if (chevron) chevron.style.transform = 'rotate(180deg)';
        if (window.lucide && lucide.createIcons) lucide.createIcons();
        
        // Auto boundary alignment for mobile
        if (isMobile) {
            menu.style.left = '0px';
            menu.style.right = 'auto';
            var rect = menu.getBoundingClientRect();
            if (rect.right > window.innerWidth - 8) {
                menu.style.left = 'auto';
                menu.style.right = '0px';
            }
        }
    }
};

document.addEventListener('click', function(e) {
    var desktopContainer = document.getElementById('desktop-node-dropdown-container');
    var desktopMenu = document.getElementById('node-dropdown-menu');
    var desktopChevron = document.getElementById('node-dropdown-chevron');
    if (desktopMenu && desktopContainer && !desktopContainer.contains(e.target)) {
        desktopMenu.style.display = 'none';
        if (desktopChevron) desktopChevron.style.transform = 'rotate(0deg)';
    }

    var mobileContainer = document.getElementById('mobile-node-dropdown-container');
    var mobileMenu = document.getElementById('mobile-node-dropdown-menu');
    var mobileChevron = document.getElementById('mobile-node-dropdown-chevron');
    if (mobileMenu && mobileContainer && !mobileContainer.contains(e.target)) {
        mobileMenu.style.display = 'none';
        if (mobileChevron) mobileChevron.style.transform = 'rotate(0deg)';
    }
});

window.__currentUser = <?= json_encode([
    'is_logged_in' => $is_logged_in,
    'name' => $is_logged_in ? ($_SESSION['user_name'] ?? '') : '',
    'email' => $is_logged_in ? ($_SESSION['user_email'] ?? '') : ''
]) ?>;
window.__currentUserEmail = <?= json_encode($is_logged_in ? ($_SESSION['user_email'] ?? '') : '') ?>;
window.__isTelemetryLive = <?= $is_telemetry_live ? 'true' : 'false' ?>;

// Global API URL resolver so API calls work from any URL path
window.getApiUrl = function(endpoint) {
    var ep = String(endpoint || '').replace(/^\/?api\//, '').replace(/^\//, '');
    var origin = window.location.origin;
    var path = window.location.pathname;
    var base = '';
    var idx = path.indexOf('/index.php');
    if (idx !== -1) {
        base = path.substring(0, idx);
    }
    return origin + (base ? base : '') + '/api/' + ep;
};

// Global helper to write persistent 1-year auth cookie across browser restarts
window.setAuthCookie = function(id, name, email) {
    if (!email) return;
    try {
        var payload = btoa(unescape(encodeURIComponent(JSON.stringify({
            id: id || 'guest',
            name: name || '',
            email: email || '',
            time: Math.floor(Date.now() / 1000)
        }))));
        var isSecure = window.location.protocol === 'https:';
        document.cookie = 'aqi_remember_auth=' + payload + '; path=/; max-age=' + (365 * 86400) + '; SameSite=Lax' + (isSecure ? '; Secure' : '');
    } catch(e) {
        console.warn("Could not write auth cookie:", e);
    }
};

// Automatic Persistent Google / User Account Re-entry:
// When someone signed in and comes again on mobile or laptop,
// they are let in directly without asking for login again.
(function() {
    var isLoggedOutUrl = window.location.search.includes('logout=true') || window.location.search.includes('logged_out=true');
    var isDeliberateLogout = false;
    try {
        isDeliberateLogout = (sessionStorage.getItem('deliberate_logout') === 'true') || (localStorage.getItem('deliberate_logout') === 'true');
    } catch (e) {}

    if (isLoggedOutUrl || isDeliberateLogout) {
        try {
            localStorage.removeItem('auth_email');
            localStorage.removeItem('auth_name');
            localStorage.removeItem('auth_logged_in');
            document.cookie = 'aqi_remember_auth=; path=/; max-age=0; expires=Thu, 01 Jan 1970 00:00:00 GMT; SameSite=Lax';
        } catch (e) {}
        return;
    }
    
    if (localStorage.getItem('auth_email') === 'manish@sode-edu.in' || localStorage.getItem('auth_email') === 'manishb@sode-edu.in') {
        localStorage.removeItem('auth_email');
        localStorage.removeItem('auth_name');
    }
    
    var savedEmail = localStorage.getItem('auth_email');
    var savedName = localStorage.getItem('auth_name') || (savedEmail ? savedEmail.split('@')[0] : '');
    var isLoggedInFlag = localStorage.getItem('auth_logged_in') === 'true';

    var isPhpLoggedIn = <?= $is_logged_in ? 'true' : 'false' ?>;
    if (!isPhpLoggedIn) {
        try {
            localStorage.removeItem('auth_email');
            localStorage.removeItem('auth_name');
            localStorage.removeItem('auth_logged_in');
        } catch (e) {}
    } else {
        // Keep persistent 1-year cookie refreshed in document.cookie
        if (savedEmail && (!document.cookie.includes('aqi_remember_auth='))) {
            window.setAuthCookie('guest', savedName, savedEmail, false);
        }
    }
})();

window.signOutUser = async function(e) {
    if (e) {
        if (typeof e.preventDefault === 'function') e.preventDefault();
        if (typeof e.stopPropagation === 'function') e.stopPropagation();
    }
    try {
        sessionStorage.setItem('deliberate_logout', 'true');
        localStorage.setItem('deliberate_logout', 'true');
        localStorage.removeItem('auth_email');
        localStorage.removeItem('auth_name');
        localStorage.removeItem('auth_logged_in');
        document.cookie = 'aqi_remember_auth=; path=/; max-age=0; expires=Thu, 01 Jan 1970 00:00:00 GMT; SameSite=Lax';
    } catch (err) {}

    try {
        if (e && e.currentTarget) {
            e.currentTarget.style.opacity = '0.5';
            e.currentTarget.style.pointerEvents = 'none';
        }
    } catch (err) {}

    try {
        if (typeof window.firebaseSignOut === 'function') {
            await Promise.race([
                window.firebaseSignOut(),
                new Promise(resolve => setTimeout(resolve, 800))
            ]);
        }
    } catch (err) {
        console.warn("SignOut Firebase error:", err);
    }

    try {
        if (typeof window.updateBottomNavLockedState === 'function') {
            window.updateBottomNavLockedState(false);
        }
    } catch (e) {}

    window.location.href = '?logout=true';
};

// Global Native Mobile App Helpers: Haptic & Toasts
window.triggerHaptic = function(type = 'light') {
    try {
        if (navigator.vibrate) {
            if (type === 'light') navigator.vibrate(12);
            else if (type === 'medium') navigator.vibrate(25);
            else if (type === 'success') navigator.vibrate([15, 45, 18]);
        }
    } catch(e){}
};

// Floating Mobile Toast Notification
window.showMobileToast = function(msg, type = 'info') {
    let toast = document.getElementById('global-mobile-toast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'global-mobile-toast';
        toast.style.cssText = 'position: fixed; top: 72px; left: 50%; transform: translateX(-50%) translateY(-20px); z-index: 9999; background: #0f172a; color: #ffffff; padding: 9px 16px; border-radius: 999px; font-size: 12px; font-weight: 700; box-shadow: 0 10px 30px rgba(15, 23, 42, 0.25); display: flex; align-items: center; gap: 8px; opacity: 0; pointer-events: none; transition: all 0.28s cubic-bezier(0.16, 1, 0.3, 1); max-width: 90vw; text-align: center; border: 1px solid rgba(255, 255, 255, 0.12); backdrop-filter: blur(8px);';
        document.body.appendChild(toast);
    }
    const icon = type === 'offline' ? '🟠' : (type === 'live' ? '🟢' : 'ℹ️');
    toast.innerHTML = `<span>${icon}</span> <span>${msg}</span>`;
    toast.style.opacity = '1';
    toast.style.transform = 'translateX(-50%) translateY(0)';
    if (window.__toastTimeout) clearTimeout(window.__toastTimeout);
    window.__toastTimeout = setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(-50%) translateY(-20px)';
    }, 3200);
};

window.showLiveStatusInfo = function() {
    window.triggerHaptic('light');
    const isLive = window.__isTelemetryLive;
    if (isLive) {
        window.showMobileToast('Cloud Online: Real-time telemetry streaming from sensor node', 'live');
    } else {
        window.showMobileToast('Sensor Node Offline: No new data received for >5 mins (showing cached values)', 'offline');
    }
};

window.openAccountModal = function() {
    window.triggerHaptic('light');
    const modal = document.getElementById('account-modal');
    if (modal) modal.style.display = 'flex';
};

window.closeAccountModal = function() {
    const modal = document.getElementById('account-modal');
    if (modal) modal.style.display = 'none';
};

window.updateUserAccountUI = function(user) {
    if (!user) return;
    const email = user.email || '';
    const name = user.displayName || (email ? email.split('@')[0] : 'User');
    const initial = (name || email || 'U')[0].toUpperCase();

    // Mobile top bar
    const mobBtn = document.getElementById('mobile-user-btn');
    const mobSignin = document.getElementById('mobile-signin-btn');
    const mobAvatar = document.getElementById('mobile-user-avatar');
    const mobTag = document.getElementById('mobile-user-initial-tag');
    if (mobAvatar) {
        mobAvatar.innerText = initial;
        mobAvatar.style.background = 'linear-gradient(135deg, #00bfa5 0%, #0d9488 100%)';
    }
    if (mobTag) mobTag.innerText = name;
    if (mobBtn) {
        mobBtn.style.display = 'inline-flex';
        mobBtn.style.borderColor = '#00bfa5';
    }
    if (mobSignin) mobSignin.style.display = 'none';

    // Desktop header
    const deskContainer = document.getElementById('desktop-user-badge');
    const deskSignin = document.getElementById('desktop-signin-btn');
    const deskAvatar = document.getElementById('desktop-user-avatar');
    const deskName = document.getElementById('desktop-user-name');
    const deskEmail = document.getElementById('desktop-user-email');

    if (deskAvatar) {
        deskAvatar.innerText = initial;
        deskAvatar.style.background = 'linear-gradient(135deg, #00bfa5 0%, #0d9488 100%)';
    }
    if (deskName) deskName.innerText = name;

    if (deskEmail) {
        deskEmail.innerText = email;
        deskEmail.style.display = 'block';
    }

    if (deskContainer) deskContainer.style.display = 'flex';
    if (deskSignin) deskSignin.style.display = 'none';

    // Account modal
    const accModalAvatar = document.getElementById('acc-modal-avatar');
    const accModalName = document.getElementById('acc-modal-name');
    const accModalEmail = document.getElementById('acc-modal-email');
    if (accModalAvatar) {
        accModalAvatar.innerText = initial;
        accModalAvatar.style.background = 'linear-gradient(135deg, #00bfa5 0%, #0d9488 100%)';
    }
    if (accModalName) accModalName.innerText = name;
    if (accModalEmail) {
        accModalEmail.style.display = 'block';
        accModalEmail.innerText = email || 'Signed in user';
    }

    if (typeof window.updateBottomNavLockedState === 'function') {
        window.updateBottomNavLockedState(true);
    }
};

window.updateBottomNavLockedState = function(isLoggedIn) {
    const isAuth = Boolean(isLoggedIn);
    const curPage = window.__currentPage || 'overview';
    document.querySelectorAll('[data-bottom-nav]').forEach(el => {
        const pageKey = el.getAttribute('data-bottom-nav');
        const icon = el.querySelector('i') || el.querySelector('svg');
        const label = el.querySelector('span:not(.campus-status-dot)');
        const liveDot = el.querySelector('.campus-status-dot');

        if (!isAuth && pageKey !== 'overview') {
            el.classList.add('bottom-nav-locked');
            el.classList.remove('active');
            el.removeAttribute('aria-disabled');
            el.style.opacity = '0.88';
            el.style.color = '#475569';
            el.style.cursor = 'pointer';
            el.style.backgroundColor = 'transparent';
            if (icon) {
                icon.style.color = '#475569';
                icon.style.stroke = '#475569';
                icon.style.opacity = '1';
                icon.style.transform = 'scale(1)';
                icon.style.strokeWidth = '1.8';
            }
            if (label) {
                label.style.color = '#475569';
                label.style.fontWeight = '600';
                label.style.opacity = '1';
            }
            if (liveDot) {
                liveDot.style.display = 'none';
            }
        } else {
            el.classList.remove('bottom-nav-locked');
            el.removeAttribute('aria-disabled');
            el.style.opacity = '1';
            el.style.cursor = 'pointer';
            if (liveDot) {
                liveDot.style.display = '';
            }
            const isCurrent = curPage === pageKey;
            if (isCurrent) {
                el.classList.add('active');
            } else {
                el.classList.remove('active');
            }
            el.style.color = isCurrent ? '#00bfa5' : '#0f172a';
            el.style.backgroundColor = isCurrent ? 'rgba(0, 191, 165, 0.12)' : 'transparent';
            if (icon) {
                icon.style.color = isCurrent ? '#00bfa5' : '#0f172a';
                icon.style.stroke = isCurrent ? '#00bfa5' : '#0f172a';
                icon.style.opacity = '1';
                icon.setAttribute('stroke-width', isCurrent ? '2.3' : '2.2');
                icon.style.strokeWidth = isCurrent ? '2.3' : '2.2';
            }
            if (label) {
                label.style.color = isCurrent ? '#00bfa5' : '#0f172a';
                label.style.fontWeight = '700';
                label.style.opacity = '1';
            }
        }
    });
};

// Immediately restore user account UI on mobile and desktop from localStorage if present
try {
    const savedEmail = localStorage.getItem('auth_email');
    const savedName = localStorage.getItem('auth_name');
    const isLoggedFlag = localStorage.getItem('auth_logged_in') === 'true';
    const isDelibOut = sessionStorage.getItem('deliberate_logout') === 'true' || localStorage.getItem('deliberate_logout') === 'true';
    if (!isDelibOut && isLoggedFlag && savedEmail) {
        window.updateUserAccountUI({ email: savedEmail, displayName: savedName });
    }
} catch (e) {}
document.addEventListener('DOMContentLoaded', () => {
    try {
        const savedEmail = localStorage.getItem('auth_email');
        const savedName = localStorage.getItem('auth_name');
        const isLoggedFlag = localStorage.getItem('auth_logged_in') === 'true';
        const isDelibOut = sessionStorage.getItem('deliberate_logout') === 'true' || localStorage.getItem('deliberate_logout') === 'true';
        if (!isDelibOut && isLoggedFlag && savedEmail) {
            window.updateUserAccountUI({ email: savedEmail, displayName: savedName });
        }
    } catch (e) {}
});

window.updateGlobalLiveBadge = function(isLive, tooltip = null, timeStr = null) {
    window.__isTelemetryLive = Boolean(isLive);
    const desktopBadge = document.getElementById('header-live-badge-desktop');
    const mobileBadge = document.getElementById('header-live-badge-mobile');
    
    const isStandby = (timeStr === 'Standby');
    
    [desktopBadge, mobileBadge].forEach(badge => {
        if (!badge) return;
        const isMobile = badge.id === 'header-live-badge-mobile';
        const dot = badge.querySelector('.status-dot');
        const text = badge.querySelector('.status-text');
        
        if (isStandby) {
            badge.style.backgroundColor = '#f1f5f9';
            badge.style.color = '#64748b';
            badge.style.borderColor = '#cbd5e1';
            badge.title = tooltip || 'Node 2 is currently in standby mode';
            if (dot) {
                dot.className = 'status-dot';
                dot.style.backgroundColor = '#94a3b8';
                dot.style.width = isMobile ? '6px' : '7px';
                dot.style.height = isMobile ? '6px' : '7px';
            }
            if (text) text.innerText = 'STANDBY';
        } else if (isLive) {
            badge.style.backgroundColor = '#dcfce7';
            badge.style.color = '#15803d';
            badge.style.borderColor = '#bbf7d0';
            badge.title = tooltip || 'Live telemetry streaming from cloud';
            if (dot) {
                dot.className = 'status-dot live-dot-pulse';
                dot.style.backgroundColor = '#16a34a';
                dot.style.width = isMobile ? '6px' : '7px';
                dot.style.height = isMobile ? '6px' : '7px';
            }
            if (text) text.innerText = 'LIVE';
        } else {
            badge.style.backgroundColor = '#fff7ed';
            badge.style.color = '#c2410c';
            badge.style.borderColor = '#fed7aa';
            badge.title = tooltip || 'Sensor node offline: No live telemetry received in cloud for >90 mins';
            if (dot) {
                dot.className = 'status-dot';
                dot.style.backgroundColor = '#f97316';
                dot.style.width = isMobile ? '6px' : '7px';
                dot.style.height = isMobile ? '6px' : '7px';
            }
            if (text) text.innerText = 'OFFLINE';
        }
    });

    document.querySelectorAll('.campus-status-dot').forEach(el => {
        if (isStandby) {
            el.className = 'campus-status-dot';
            el.style.backgroundColor = '#94a3b8';
        } else if (isLive) {
            el.className = 'campus-status-dot live-dot-pulse';
            el.style.backgroundColor = '#10b981';
        } else {
            el.className = 'campus-status-dot';
            el.style.backgroundColor = '#f97316';
        }
    });
};
</script>

<?php
// Dynamic AQI theme background for Live page matching CPCB AQI categories
$init_aqi_num = isset($server_init_aqi) && is_numeric($server_init_aqi) ? (int)$server_init_aqi : 38;
if ($init_aqi_num <= 50) {
    $live_bg_style = 'background-color: #dcfce7; background-image: radial-gradient(ellipse 120% 70% at 50% -10%, #86efac 0%, #bbf7d0 35%, #dcfce7 65%, #bbf7d0 100%);';
} elseif ($init_aqi_num <= 100) {
    $live_bg_style = 'background-color: #fde68a; background-image: radial-gradient(ellipse 120% 70% at 50% -10%, #fde047 0%, #fde68a 35%, #fef08a 65%, #fde68a 100%);';
} elseif ($init_aqi_num <= 200) {
    $live_bg_style = 'background-color: #fed7aa; background-image: radial-gradient(ellipse 120% 70% at 50% -10%, #fdba74 0%, #fed7aa 35%, #ffedd5 65%, #fed7aa 100%);';
} elseif ($init_aqi_num <= 300) {
    $live_bg_style = 'background-color: #fecdd3; background-image: radial-gradient(ellipse 120% 70% at 50% -10%, #fda4af 0%, #fecdd3 35%, #ffe4e6 65%, #fecdd3 100%);';
} elseif ($init_aqi_num <= 400) {
    $live_bg_style = 'background-color: #e9d5ff; background-image: radial-gradient(ellipse 120% 70% at 50% -10%, #d8b4fe 0%, #e9d5ff 35%, #f3e8ff 65%, #e9d5ff 100%);';
} else {
    $live_bg_style = 'background-color: #fecaca; background-image: radial-gradient(ellipse 120% 70% at 50% -10%, #fca5a5 0%, #fecaca 35%, #fee2e2 65%, #fecaca 100%);';
}

$cool_bg_styles = [
    'live' => $live_bg_style,
    'historical' => 'background-color: #f0f4fa; background-image: radial-gradient(ellipse 120% 70% at 50% -10%, #e0e7ff 0%, #e0f2fe 35%, #f0f4f8 65%, #f8fafc 100%);',
    'predictive' => 'background-color: #f0f9ff; background-image: radial-gradient(ellipse 120% 70% at 50% -10%, #bae6fd 0%, #e0f2fe 30%, #e0f7f6 60%, #f8fafc 100%);',
    'overview' => 'background-color: #f1f5f9; background-image: radial-gradient(ellipse 120% 80% at 50% -20%, #e2e8f0 0%, #f1f5f9 65%, #e2e8f0 100%);',
    'live-weather' => 'background: transparent; background-image: none;'
];
$is_weather_tab_active = ((
    (isset($_GET['page']) && ($_GET['page'] === 'live-weather' || $_GET['page'] === 'weather')) ||
    (isset($_GET['tab']) && $_GET['tab'] === 'weather') ||
    ($current_page === 'live-weather')
));
$is_soil_tab_active = ((
    (isset($_GET['tab']) && $_GET['tab'] === 'soil') ||
    (isset($_GET['page']) && in_array($_GET['page'], ['live-soil', 'soil'])) ||
    $current_page === 'live-soil' ||
    $current_page === 'soil'
));
$active_layout_bg = ($is_weather_tab_active || $is_soil_tab_active)
    ? 'background: transparent; background-image: none;'
    : ($cool_bg_styles[$current_page] ?? $cool_bg_styles['live']);
?>
<div id="app-layout-root" style="min-height: 100vh; <?= $active_layout_bg ?> position: relative; width: 100%; display: flex; flex-direction: column; transition: background 0.15s ease, background-color 0.15s ease;">

  <!-- Atmospheric Dynamic Weather Backdrop (Fixed full-bleed viewport layer, never cut off on scroll) -->
  <div id="node2-weather-backdrop" class="node2-weather-backdrop <?= $is_weather_tab_active ? 'active' : '' ?>" aria-hidden="true">
    <div class="weather-sky-photo-layer"></div>
    <div class="sky-ambient-vignette"></div>
  </div>

  <!-- Immediate Full-Page Light Soil Backdrop for Node 2 -->
  <div id="node2-soil-backdrop" class="node2-soil-backdrop <?= $is_soil_tab_active ? 'active' : '' ?>" aria-hidden="true">
    <div class="soil-photo-layer"></div>
    <div class="soil-ambient-overlay"></div>
  </div>

  <!-- Sticky Header with Safe-Area Inset -->
  <header class="px-3 sm:px-4 md:px-6" style="min-height: calc(60px + env(safe-area-inset-top, 0px)); padding-top: env(safe-area-inset-top, 0px); display: flex; align-items: center; justify-content: space-between; gap: 8px; position: sticky; top: 0; z-index: 50; background-color: rgba(255, 255, 255, 0.94); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); border-bottom: 1px solid #e2e8f0; box-shadow: 0 4px 20px rgba(15, 23, 42, 0.03); width: 100%; box-sizing: border-box; transition: background-color 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease;">
    
    <!-- Left: Desktop Brand & Mobile Header Selector -->
    <div style="display: flex; align-items: center; gap: 8px; min-width: 0;">
      
      <!-- Desktop Brand (Always visible on md+ screens) -->
      <div class="hidden md:flex items-center gap-2">
        <a href="?page=overview&node=<?= $selected_node ?>" style="display: flex; align-items: center; gap: 10px; cursor: pointer; text-decoration: none;">
          <div style="width: 40px; height: 40px; border-radius: 12px; background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%); display: flex; align-items: center; justify-content: center; box-shadow: 0 3px 12px rgba(15, 23, 42, 0.08); border: 1px solid #e2e8f0; flex-shrink: 0; overflow: hidden; padding: 3px;">
            <img src="<?= defined('SMART_WEATHERNET_LOGO_MARK') ? SMART_WEATHERNET_LOGO_MARK : 'assets/logo-mark.png' ?>" alt="Smart WeatherNet Logo" style="width: 100%; height: 100%; object-fit: contain;" />
          </div>
          <div style="display: flex; flex-direction: column; gap: 1px; overflow: hidden; padding-top: 1px;">
            <div style="font-size: 17px; font-weight: 800; color: #0f172a; letter-spacing: -0.03em; white-space: nowrap; line-height: 1.1;">
              Smart <span style="font-weight: 800; color: #00bfa5;">WeatherNet</span>
            </div>
            <?php if (!empty($node_config['station_tag'])): ?>
            <div style="display: flex; align-items: center; gap: 5px; margin-top: 2px;">
              <span class="campus-status-dot <?= (!$is_node_2 && $is_telemetry_live) ? 'live-dot-pulse' : '' ?>" style="width: 6.5px; height: 6.5px; border-radius: 50%; background-color: <?= $node_status_dot ?>; display: inline-block; flex-shrink: 0;"></span>
              <span style="font-size: 10.5px; font-weight: 700; color: #475569; white-space: nowrap;"><?= htmlspecialchars($node_config['station_tag']) ?></span>
              <span id="navbar-station-coords" style="font-size: 9.5px; font-weight: 600; color: #94a3b8; border-left: <?= (!empty($node_config['location']['coords_display']) && $node_config['location']['coords_display'] !== '--') ? '1px solid #e2e8f0' : 'none' ?>; padding-left: 5px; margin-left: 1px; white-space: nowrap; display: <?= (!empty($node_config['location']['coords_display']) && $node_config['location']['coords_display'] !== '--') ? 'inline-block' : 'none' ?>;"><?= (!empty($node_config['location']['coords_display']) && $node_config['location']['coords_display'] !== '--') ? htmlspecialchars($node_config['location']['coords_display']) : '' ?></span>
            </div>
            <?php endif; ?>
          </div>
        </a>

        <!-- Desktop Node Selector Dropdown -->
        <div class="relative ml-2" id="desktop-node-dropdown-container" style="position: relative;">
          <button type="button" id="node-dropdown-trigger" onclick="window.toggleNodeDropdown(event)" style="display: flex; align-items: center; gap: 7px; padding: 5px 12px; border-radius: 999px; border: 1.5px solid #e2e8f0; background: #ffffff; cursor: pointer; box-shadow: 0 1px 4px rgba(15, 23, 42, 0.05); transition: all 0.18s ease; outline: none;" onmouseover="this.style.borderColor='#00bfa5';" onmouseout="this.style.borderColor='#e2e8f0';">
            <span style="width: 7.5px; height: 7.5px; border-radius: 50%; background-color: <?= $is_telemetry_live ? '#10b981' : '#94a3b8' ?>; display: inline-block; flex-shrink: 0;" class="<?= $is_telemetry_live ? 'live-dot-pulse' : '' ?>"></span>
            <span style="font-size: 12px; font-weight: 700; color: #0f172a; white-space: nowrap;"><?= $selected_node === '1' ? 'Station 1 (SMVITM)' : 'Station 2 (Field Station)' ?></span>
            <i data-lucide="chevron-down" id="node-dropdown-chevron" style="width: 13px; height: 13px; color: #64748b; transition: transform 0.2s;"></i>
          </button>

          <!-- Dropdown Popup Menu -->
          <div id="node-dropdown-menu" style="display: none; position: absolute; top: calc(100% + 6px); left: 0; min-width: 250px; background: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 12px 30px -4px rgba(15, 23, 42, 0.15); z-index: 1000; padding: 6px; animation: modal-pop 0.15s ease-out;">
            <div style="padding: 6px 10px 4px; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: #94a3b8;">
              Select Station
            </div>
            
            <!-- Option: Node 1 -->
            <button type="button" onclick="switchActiveNode('1')" style="display: flex; align-items: center; justify-content: space-between; width: 100%; padding: 8px 10px; border-radius: 10px; border: none; background: <?= $selected_node === '1' ? '#f0fdf4' : 'transparent' ?>; cursor: pointer; text-align: left; transition: background 0.15s;" onmouseover="if('<?= $selected_node ?>' !== '1') this.style.background='#f8fafc';" onmouseout="if('<?= $selected_node ?>' !== '1') this.style.background='transparent';">
              <div style="display: flex; align-items: center; gap: 8px;">
                <span style="width: 8px; height: 8px; border-radius: 50%; background-color: #10b981; display: inline-block; flex-shrink: 0;" class="<?= ($selected_node === '1' && $is_telemetry_live) ? 'live-dot-pulse' : '' ?>"></span>
                <div style="display: flex; flex-direction: column;">
                  <span style="font-size: 12px; font-weight: 700; color: #0f172a;">Station 1 - SMVITM Campus</span>
                  <span style="font-size: 10px; color: #16a34a; font-weight: 600;">Active</span>
                </div>
              </div>
              <?php if ($selected_node === '1'): ?>
                <i data-lucide="check" style="width: 14px; height: 14px; color: #10b981; stroke-width: 2.5;"></i>
              <?php endif; ?>
            </button>

            <!-- Option: Node 2 -->
            <button type="button" onclick="switchActiveNode('2')" style="display: flex; align-items: center; justify-content: space-between; width: 100%; padding: 8px 10px; border-radius: 10px; border: none; background: <?= $selected_node === '2' ? '#f0fdf4' : 'transparent' ?>; cursor: pointer; text-align: left; transition: background 0.15s; margin-top: 2px;" onmouseover="if('<?= $selected_node ?>' !== '2') this.style.background='#f8fafc';" onmouseout="if('<?= $selected_node ?>' !== '2') this.style.background='transparent';">
              <div style="display: flex; align-items: center; gap: 8px;">
                <span style="width: 8px; height: 8px; border-radius: 50%; background-color: #10b981; display: inline-block; flex-shrink: 0;" class="<?= ($selected_node === '2' && $is_telemetry_live) ? 'live-dot-pulse' : '' ?>"></span>
                <div style="display: flex; flex-direction: column;">
                  <span style="font-size: 12px; font-weight: 700; color: #0f172a;">Station 2 - Field Station</span>
                  <span style="font-size: 10px; color: #16a34a; font-weight: 600;">Active</span>
                </div>
              </div>
              <?php if ($selected_node === '2'): ?>
                <i data-lucide="check" style="width: 14px; height: 14px; color: #10b981; stroke-width: 2.5;"></i>
              <?php endif; ?>
            </button>
          </div>
        </div>
      </div>

      <!-- Mobile Top App Bar (Brand always visible across all pages) -->
      <div class="flex md:hidden items-center gap-2 min-w-0" id="mobile-top-bar">
        <a href="?page=overview&node=<?= $selected_node ?>" id="mobile-brand-overview" style="display: flex; align-items: center; gap: 8px; text-decoration: none; min-width: 0; flex-shrink: 0;">
          <div style="width: 38px; height: 38px; border-radius: 11px; background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%); display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 8px rgba(15, 23, 42, 0.08); border: 1px solid #e2e8f0; flex-shrink: 0; overflow: hidden; padding: 2px;">
            <img src="<?= defined('SMART_WEATHERNET_LOGO_MARK') ? SMART_WEATHERNET_LOGO_MARK : 'assets/logo-mark.png' ?>" alt="Smart WeatherNet Logo" style="width: 100%; height: 100%; object-fit: contain;" />
          </div>
          <div style="display: flex; flex-direction: column; flex-shrink: 0;">
            <span class="mobile-brand-title" style="font-size: 15.5px; font-weight: 800; color: #0f172a; letter-spacing: -0.02em; white-space: nowrap; line-height: 1.15;">
              Smart <span class="mobile-brand-weathernet" style="color: #00bfa5; font-size: 15.5px; font-weight: 800;">WeatherNet</span>
            </span>
            <div id="mobile-station-subline" style="display: <?= (!empty($node_config['location']['coords_display']) && $node_config['location']['coords_display'] !== '--') ? 'flex' : 'none' ?>; align-items: center; gap: 4px; margin-top: 1px;">
              <span class="campus-status-dot <?= (!$is_node_2 && $is_telemetry_live) ? 'live-dot-pulse' : '' ?>" style="width: 5.5px; height: 5.5px; border-radius: 50%; background-color: <?= $node_status_dot ?>; display: inline-block; flex-shrink: 0;"></span>
              <span id="navbar-station-coords-mobile" style="font-size: 9.5px; font-weight: 600; color: #94a3b8; white-space: nowrap;"><?= (!empty($node_config['location']['coords_display']) && $node_config['location']['coords_display'] !== '--') ? htmlspecialchars($node_config['location']['coords_display']) : '' ?></span>
            </div>
          </div>
        </a>

        <!-- Mobile Node Selector Dropdown (Custom styled matching desktop) -->
        <div class="relative ml-1 shrink-0" id="mobile-node-dropdown-container" style="position: relative;">
          <button type="button" id="mobile-node-dropdown-trigger" onclick="window.toggleNodeDropdown(event, 'mobile')" style="display: flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 999px; border: 1.5px solid #e2e8f0; background: #ffffff; cursor: pointer; box-shadow: 0 1px 4px rgba(15, 23, 42, 0.05); transition: all 0.18s ease; outline: none; -webkit-tap-highlight-color: transparent;" onmouseover="this.style.borderColor='#00bfa5';" onmouseout="this.style.borderColor='#e2e8f0';">
            <span style="width: 7px; height: 7px; border-radius: 50%; background-color: <?= $is_telemetry_live ? '#10b981' : '#94a3b8' ?>; display: inline-block; flex-shrink: 0;" class="<?= $is_telemetry_live ? 'live-dot-pulse' : '' ?>"></span>
            <span style="font-size: 11px; font-weight: 700; color: #0f172a; white-space: nowrap;"><?= $selected_node === '1' ? 'Station 1<span class="hidden sm:inline"> (SMVITM)</span>' : 'Station 2<span class="hidden sm:inline"> (Field Station)</span>' ?></span>
            <i data-lucide="chevron-down" id="mobile-node-dropdown-chevron" style="width: 12px; height: 12px; color: #64748b; transition: transform 0.2s;"></i>
          </button>

          <!-- Mobile Dropdown Popup Menu -->
          <div id="mobile-node-dropdown-menu" style="display: none; position: absolute; top: calc(100% + 6px); left: 0; min-width: 245px; max-width: calc(100vw - 20px); background: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 12px 30px -4px rgba(15, 23, 42, 0.18); z-index: 1000; padding: 6px; animation: modal-pop 0.15s ease-out;">
            <div style="padding: 6px 10px 4px; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: #94a3b8;">
              Select Station
            </div>
            
            <!-- Option: Node 1 -->
            <button type="button" onclick="switchActiveNode('1')" style="display: flex; align-items: center; justify-content: space-between; width: 100%; padding: 8px 10px; border-radius: 10px; border: none; background: <?= $selected_node === '1' ? '#f0fdf4' : 'transparent' ?>; cursor: pointer; text-align: left; transition: background 0.15s;" onmouseover="if('<?= $selected_node ?>' !== '1') this.style.background='#f8fafc';" onmouseout="if('<?= $selected_node ?>' !== '1') this.style.background='transparent';">
              <div style="display: flex; align-items: center; gap: 8px;">
                <span style="width: 8px; height: 8px; border-radius: 50%; background-color: #10b981; display: inline-block; flex-shrink: 0;" class="<?= ($selected_node === '1' && $is_telemetry_live) ? 'live-dot-pulse' : '' ?>"></span>
                <div style="display: flex; flex-direction: column;">
                  <span style="font-size: 12px; font-weight: 700; color: #0f172a;">Station 1 - SMVITM Campus</span>
                  <span style="font-size: 10px; color: #16a34a; font-weight: 600;">Active</span>
                </div>
              </div>
              <?php if ($selected_node === '1'): ?>
                <i data-lucide="check" style="width: 14px; height: 14px; color: #10b981; stroke-width: 2.5;"></i>
              <?php endif; ?>
            </button>

            <!-- Option: Node 2 -->
            <button type="button" onclick="switchActiveNode('2')" style="display: flex; align-items: center; justify-content: space-between; width: 100%; padding: 8px 10px; border-radius: 10px; border: none; background: <?= $selected_node === '2' ? '#f0fdf4' : 'transparent' ?>; cursor: pointer; text-align: left; transition: background 0.15s; margin-top: 2px;" onmouseover="if('<?= $selected_node ?>' !== '2') this.style.background='#f8fafc';" onmouseout="if('<?= $selected_node ?>' !== '2') this.style.background='transparent';">
              <div style="display: flex; align-items: center; gap: 8px;">
                <span style="width: 8px; height: 8px; border-radius: 50%; background-color: #10b981; display: inline-block; flex-shrink: 0;" class="<?= ($selected_node === '2' && $is_telemetry_live) ? 'live-dot-pulse' : '' ?>"></span>
                <div style="display: flex; flex-direction: column;">
                  <span style="font-size: 12px; font-weight: 700; color: #0f172a;">Station 2 - Field Station</span>
                  <span style="font-size: 10px; color: #16a34a; font-weight: 600;">Active</span>
                </div>
              </div>
              <?php if ($selected_node === '2'): ?>
                <i data-lucide="check" style="width: 14px; height: 14px; color: #10b981; stroke-width: 2.5;"></i>
              <?php endif; ?>
            </button>
          </div>
        </div>

        <!-- Secondary Page Mobile App Bar: hidden so brand is always shown -->
        <div id="mobile-secondary-header" style="display: none; align-items: center; gap: 8px; min-width: 0;">
          <a href="?page=overview&node=<?= $selected_node ?>" id="header-back-btn" title="Back to Overview" style="display: none; align-items: center; gap: 3px; padding: 6px 10px 6px 6px; border-radius: 10px; border: 1.5px solid #e2e8f0; background: #ffffff; color: #475569; text-decoration: none; font-size: 12px; font-weight: 700; -webkit-tap-highlight-color: transparent; flex-shrink: 0;">
            <i data-lucide="chevron-left" style="width: 18px; height: 18px; color: #00bfa5;"></i>
            <span>Back</span>
          </a>
          <div style="display: flex; align-items: center; gap: 6px; min-width: 0; overflow: hidden;">
            <span id="mobile-page-title" style="font-size: 15px; font-weight: 800; color: #0f172a; letter-spacing: -0.02em; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
              <?= htmlspecialchars($current_title) ?>
            </span>
            <span class="live-dot-pulse" style="flex-shrink: 0;"></span>
          </div>
        </div>
      </div>

    </div>

    <!-- Center: Desktop Nav (4 Clean Pages) -->
    <nav id="desktop-nav" style="align-items: center; background-color: #f8fafc; padding: 4px; border-radius: 999px; border: 1px solid #e2e8f0; box-shadow: 0 2px 10px rgba(15,23,42,0.04); gap: 2px;" class="hidden md:inline-flex">
      <a href="?page=overview&node=<?= $selected_node ?>" data-nav-link="overview" style="display: flex; align-items: center; gap: 6px; padding: 7px 15px; border-radius: 999px; font-size: 12.5px; font-weight: 600; text-decoration: none; color: <?= $current_page === 'overview' ? '#ffffff' : '#64748b' ?>; background-color: <?= $current_page === 'overview' ? '#00bfa5' : 'transparent' ?>; box-shadow: <?= $current_page === 'overview' ? '0 4px 14px rgba(0, 191, 165, 0.35)' : 'none' ?>; transition: all 0.2s ease;">
        <i data-lucide="layout-dashboard" style="width: 14px; height: 14px; opacity: <?= $current_page === 'overview' ? '1' : '0.75' ?>;"></i> Overview
      </a>
      <a href="?page=live&node=<?= $selected_node ?>" data-nav-link="live" style="display: flex; align-items: center; gap: 6px; padding: 7px 15px; border-radius: 999px; font-size: 12.5px; font-weight: 600; text-decoration: none; color: <?= $is_live_page_active ? '#ffffff' : '#64748b' ?>; background-color: <?= $is_live_page_active ? '#00bfa5' : 'transparent' ?>; box-shadow: <?= $is_live_page_active ? '0 4px 14px rgba(0, 191, 165, 0.35)' : 'none' ?>; transition: all 0.2s ease;">
        <i data-lucide="activity" style="width: 14px; height: 14px; opacity: <?= $is_live_page_active ? '1' : '0.75' ?>;"></i> Live Feed
      </a>
      <a href="?page=historical&node=<?= $selected_node ?>" data-nav-link="historical" style="display: flex; align-items: center; gap: 6px; padding: 7px 15px; border-radius: 999px; font-size: 12.5px; font-weight: 600; text-decoration: none; color: <?= $current_page === 'historical' ? '#ffffff' : '#64748b' ?>; background-color: <?= $current_page === 'historical' ? '#00bfa5' : 'transparent' ?>; box-shadow: <?= $current_page === 'historical' ? '0 4px 14px rgba(0, 191, 165, 0.35)' : 'none' ?>; transition: all 0.2s ease;">
        <i data-lucide="database" style="width: 14px; height: 14px; opacity: <?= $current_page === 'historical' ? '1' : '0.75' ?>;"></i> Analytics
      </a>
      <a href="?page=predictive&node=<?= $selected_node ?>" data-nav-link="predictive" style="display: flex; align-items: center; gap: 6px; padding: 7px 15px; border-radius: 999px; font-size: 12.5px; font-weight: 600; text-decoration: none; color: <?= $current_page === 'predictive' ? '#ffffff' : '#64748b' ?>; background-color: <?= $current_page === 'predictive' ? '#00bfa5' : 'transparent' ?>; box-shadow: <?= $current_page === 'predictive' ? '0 4px 14px rgba(0, 191, 165, 0.35)' : 'none' ?>; transition: all 0.2s ease;">
        <i data-lucide="cloud-rain" style="width: 14px; height: 14px; opacity: <?= $current_page === 'predictive' ? '1' : '0.75' ?>;"></i> Forecast
      </a>
    </nav>

    <!-- Right: Auth & Mobile Trigger -->
    <div style="display: flex; align-items: center; gap: 8px;">

      <!-- Desktop Auth -->
      <div class="hidden md:flex items-center gap-2.5">
        <!-- Desktop Auth Block -->
        <div id="desktop-user-badge" style="display: <?= $is_logged_in ? 'flex' : 'none' ?>; align-items: center; gap: 8px; background-color: #ffffff; border: 1.5px solid #e2e8f0; border-radius: 999px; padding: 3px 10px 3px 4px; box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);">
          <div id="desktop-user-avatar" style="width: 28px; height: 28px; border-radius: 50%; background: linear-gradient(135deg, #00bfa5 0%, #0d9488 100%); color: white; display: flex; align-items: center; justify-content: center; font-size: 12.5px; font-weight: 800; box-shadow: 0 1px 3px rgba(0, 191, 165, 0.35); flex-shrink: 0;">
            <?= htmlspecialchars($user_initial) ?>
          </div>
          <div style="display: flex; flex-direction: column; justify-content: center; line-height: 1.2; max-width: 140px; overflow: hidden;">
            <div style="display: flex; align-items: center; gap: 4px;">
              <span id="desktop-user-name" style="font-size: 12.5px; font-weight: 700; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($user_display_name) ?></span>
              
            </div>
            <span id="desktop-user-email" style="display: block; font-size: 9.5px; font-weight: 500; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($user_email) ?></span>
          </div>
          <a href="?logout=true" onclick="window.signOutUser(event); return false;" title="Sign Out" style="padding: 4px 6px; border-radius: 999px; border: none; background: #f1f5f9; color: #64748b; cursor: pointer; text-decoration: none; transition: all 0.15s ease;">
            <i data-lucide="log-out" style="width: 13px; height: 13px;"></i>
          </a>
        </div>
        <button id="desktop-signin-btn" onclick="openAuthModal()" style="display: <?= $is_logged_in ? 'none' : 'inline-flex' ?>; align-items: center; gap: 6px; padding: 7px 16px; border-radius: 999px; border: none; background-color: #0f172a; color: #ffffff; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1); box-shadow: 0 4px 12px rgba(15, 23, 42, 0.15);">
          <i data-lucide="user" style="width: 14px; height: 14px;"></i>
          <span>Sign In</span>
        </button>
        
        <!-- Live / Standby / Offline Badge (Desktop) -->
        <div id="header-live-badge-desktop" class="header-status-badge" onclick="showLiveStatusInfo()" title="<?= $is_telemetry_live ? 'Live telemetry streaming from cloud' : 'Sensor node offline: No live telemetry received in cloud for >90 mins' ?>" style="display: flex; padding: 6px 14px; border-radius: 999px; font-size: 11.5px; font-weight: 700; align-items: center; gap: 6px; background-color: <?= $node_status_bg ?>; color: <?= $node_status_color ?>; border: 1.5px solid <?= $node_status_border ?>; font-family: var(--font-mono); white-space: nowrap; transition: all 0.25s ease; cursor: pointer;">
          <span class="status-dot <?= $is_telemetry_live ? 'live-dot-pulse' : '' ?>" style="width: 7px; height: 7px; border-radius: 50%; background-color: <?= $node_status_dot ?>;"></span>
          <span class="status-text"><?= $node_status_label ?></span>
        </div>
      </div>

      <!-- Mobile Right Items (Visible on mobile screens) -->
      <div id="mobile-right-actions" class="flex md:hidden items-center gap-2 flex-shrink-0">
        <!-- Live / Standby / Offline Badge (Mobile) -->
        <div id="header-live-badge-mobile" class="header-status-badge" onclick="showLiveStatusInfo()" title="<?= $is_telemetry_live ? 'Live telemetry streaming from cloud' : 'Sensor node offline' ?>" style="display: flex; padding: 4px 9px; border-radius: 999px; font-size: 10.5px; font-weight: 800; align-items: center; gap: 4px; background-color: <?= $node_status_bg ?>; color: <?= $node_status_color ?>; border: 1.5px solid <?= $node_status_border ?>; font-family: var(--font-mono); white-space: nowrap; transition: all 0.25s ease; cursor: pointer; flex-shrink: 0;">
          <span class="status-dot <?= $is_telemetry_live ? 'live-dot-pulse' : '' ?>" style="width: 6px; height: 6px; border-radius: 50%; background-color: <?= $node_status_dot ?>;"></span>
          <span class="status-text"><?= $node_status_label ?></span>
        </div>

        <!-- Mobile User Account Pill (Always displays which account is signed in) -->
        <div id="mobile-user-container" style="display: flex; align-items: center;">
          <button type="button" id="mobile-user-btn" onclick="openAccountModal()" title="Logged in as <?= htmlspecialchars($user_display_name) ?>" style="display: <?= $is_logged_in ? 'inline-flex' : 'none' ?>; align-items: center; justify-content: center; width: 30px; height: 30px; border-radius: 50%; background: #ffffff; border: 1.5px solid #00bfa5; box-shadow: 0 2px 6px rgba(0, 191, 165, 0.15); cursor: pointer; -webkit-tap-highlight-color: transparent; padding: 0; flex-shrink: 0;">
            <div id="mobile-user-avatar" style="width: 25px; height: 25px; border-radius: 50%; background: linear-gradient(135deg, #00bfa5 0%, #0d9488 100%); color: white; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 800; box-shadow: 0 1px 3px rgba(0, 191, 165, 0.3); flex-shrink: 0;">
              <?= htmlspecialchars($user_initial) ?>
            </div>
            <span id="mobile-user-initial-tag" style="display: none;"><?= htmlspecialchars($user_display_name) ?></span>
          </button>
          
          <button type="button" id="mobile-signin-btn" onclick="openAuthModal()" style="display: <?= $is_logged_in ? 'none' : 'inline-flex' ?>; align-items: center; gap: 4px; padding: 5px 10px; border-radius: 999px; background-color: #0f172a; border: none; color: #ffffff; font-size: 11px; font-weight: 700; cursor: pointer; box-shadow: 0 2px 6px rgba(15, 23, 42, 0.15);">
            <i data-lucide="user" style="width: 12px; height: 12px;"></i>
            <span>Sign In</span>
          </button>
        </div>
      </div>
    </div>
  </header>

  <script>
    function toggleMobileMenu(forceState = null) {}
  </script>

  <!-- Main Content Area -->
  <main style="display: flex; flex-direction: column; position: relative;">
    <div id="page-content-wrapper" class="page-fade-active" style="flex-grow: 0; min-height: auto; padding-top: 10px; padding-bottom: 0px;">
      <?php
        $node_folder = ($active_node_id === '2') ? 'node 2' : 'node 1';
        $page_file = __DIR__ . '/../' . $node_folder . '/' . $current_page . '.php';
        if (file_exists($page_file)) {
            include $page_file;
        } else {
            echo "<div style='padding: 40px; text-align: center; color: #64748b;'>Page not found. (" . htmlspecialchars($current_page) . ")</div>";
        }
      ?>
    </div>
    
    <!-- Global Footer (Differentiated styling with team LinkedIn links) -->
    <footer id="global-footer" style="margin-top: 4px; padding: 12px 16px; text-align: center; position: relative; z-index: 10; font-size: 12.5px; background: #0f172a; color: #94a3b8; border-top: 1px solid rgba(255, 255, 255, 0.08); display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 12px; width: 100%; box-sizing: border-box; box-shadow: 0 -4px 20px rgba(15, 23, 42, 0.15);" class="mb-[84px] md:mb-0">
      
      <!-- Top Brand Row -->
      <div style="display: flex; align-items: center; justify-content: center; gap: 8px; flex-wrap: wrap;">
        <div style="display: flex; align-items: center; gap: 6px;">
          <img src="<?= defined('SMART_WEATHERNET_LOGO_MARK') ? SMART_WEATHERNET_LOGO_MARK : 'assets/logo-mark.png' ?>" alt="Logo" style="width: 20px; height: 20px; object-fit: contain;">
          <span style="font-weight: 800; color: #f8fafc; font-size: 13px; letter-spacing: -0.01em;">Smart WeatherNet</span>
        </div>

        <?php $has_footer_station = ($active_node_id === '1' && !empty($node_config['station_tag'])); ?>
        <span id="footer-station-bullet" style="color: rgba(255, 255, 255, 0.25); font-weight: 300; display: <?= $has_footer_station ? 'inline' : 'none' ?>;">•</span>
        <span id="footer-station-name" style="font-size: 11.5px; color: #cbd5e1; font-weight: 500; display: <?= $has_footer_station ? 'inline' : 'none' ?>;"><?= $has_footer_station ? htmlspecialchars($node_config['station_tag']) : '' ?></span>
        <span style="color: rgba(255, 255, 255, 0.25); font-weight: 300;">•</span>

        <button type="button" onclick="document.getElementById('contact-modal').style.display='flex'; if (window.lucide) { lucide.createIcons(); }" style="background: none; border: none; font-size: 12px; font-weight: 700; color: #00e5c5; cursor: pointer; text-decoration: underline; text-decoration-color: rgba(0,229,197,0.4); text-underline-offset: 3px; transition: all 0.2s; padding: 0;" onmouseover="this.style.color='#38bdf8';" onmouseout="this.style.color='#00e5c5';">
          Contact Us
        </button>
      </div>

      
    </footer>
  </main>

  <!-- Fixed Native Mobile Bottom Navigation Bar —— Safe area compliant & haptic response -->
  <style>
    /* Strict laptop & desktop hiding: completely suppress bottom nav on screens >= 768px */
    @media (min-width: 768px) {
      #mobile-bottom-nav,
      .mobile-bottom-nav,
      nav#mobile-bottom-nav {
        display: none !important;
        visibility: hidden !important;
        pointer-events: none !important;
        opacity: 0 !important;
        height: 0 !important;
        min-height: 0 !important;
        max-height: 0 !important;
        overflow: hidden !important;
        position: absolute !important;
        z-index: -9999 !important;
        transform: translateY(200%) !important;
      }
    }
    @media (max-width: 767px) {
      #mobile-bottom-nav,
      .mobile-bottom-nav {
        display: flex !important;
      }
    }
  </style>
  <nav id="mobile-bottom-nav" class="mobile-bottom-nav md:hidden md:!hidden" style="flex-direction: row; align-items: stretch; justify-content: space-around; width: 100%; position: fixed; bottom: 0; left: 0; right: 0; z-index: 100; background: rgba(255, 255, 255, 0.97); backdrop-filter: blur(24px) saturate(1.4); -webkit-backdrop-filter: blur(24px) saturate(1.4); border-top: 1px solid rgba(226, 232, 240, 0.8); height: calc(64px + env(safe-area-inset-bottom, 14px)); padding: 6px 3px calc(6px + env(safe-area-inset-bottom, 14px)); box-shadow: 0 -6px 24px rgba(15, 23, 42, 0.07); overflow: hidden; touch-action: manipulation;">

    <?php 
    $is_nav_locked = !$is_logged_in; 
    $node_suffix = ($active_node_id === '2') ? '&node=2' : '';
    ?>
    <!-- Overview -->
    <a href="?page=overview<?= $node_suffix ?>" data-bottom-nav="overview" onclick="window.triggerHaptic('light')" class="<?= $current_page === 'overview' ? 'active' : '' ?>" style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 2px; flex: 1; min-width: 44px; padding: 4px 2px; border-radius: 12px; text-decoration: none; transition: all 0.18s cubic-bezier(0.16,1,0.3,1); color: <?= $current_page === 'overview' ? '#00bfa5' : '#0f172a' ?>; background-color: <?= $current_page === 'overview' ? 'rgba(0, 191, 165, 0.12)' : 'transparent' ?>; opacity: 1; cursor: pointer;">
      <i data-lucide="home" style="width: 19px; height: 19px; stroke-width: <?= $current_page === 'overview' ? '2.3' : '2.2' ?>; flex-shrink: 0; color: <?= $current_page === 'overview' ? '#00bfa5' : '#0f172a' ?>;"></i>
      <span style="font-size: 9.5px; font-weight: 700; letter-spacing: -0.01em; white-space: nowrap; color: <?= $current_page === 'overview' ? '#00bfa5' : '#0f172a' ?>;">Overview</span>
    </a>

    <!-- Live Feed -->
    <a href="?page=live<?= $node_suffix ?>" data-bottom-nav="live" onclick="window.triggerHaptic('light')" class="<?= ($is_nav_locked && !$is_live_page_active) ? 'bottom-nav-locked' : ($is_live_page_active ? 'active' : '') ?>" style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 2px; flex: 1; min-width: 48px; padding: 4px 2px; border-radius: 12px; text-decoration: none; transition: all 0.18s cubic-bezier(0.16,1,0.3,1); color: <?= $is_live_page_active ? '#00bfa5' : (($is_nav_locked ? '#475569' : '#0f172a')) ?>; background-color: <?= $is_live_page_active ? 'rgba(0, 191, 165, 0.12)' : 'transparent' ?>; opacity: <?= $is_live_page_active ? '1' : (($is_nav_locked ? '0.88' : '1')) ?>; cursor: pointer;">
      <div style="position: relative; display: inline-flex; align-items: center; justify-content: center;">
        <i data-lucide="activity" style="width: 19px; height: 19px; stroke-width: <?= $is_live_page_active ? '2.3' : '2.2' ?>; flex-shrink: 0; color: <?= $is_live_page_active ? '#00bfa5' : (($is_nav_locked ? '#475569' : '#0f172a')) ?>;"></i>
        <span class="campus-status-dot <?= ($is_telemetry_live && !$is_node_2) ? 'live-dot-pulse' : '' ?>" style="position: absolute; top: -1px; right: -4px; width: 6px; height: 6px; border-radius: 50%; background-color: <?= $is_node_2 ? '#94a3b8' : ($is_telemetry_live ? '#10b981' : '#f97316') ?>; border: 1px solid #ffffff; <?= $is_nav_locked ? 'display: none;' : '' ?>"></span>
      </div>
      <span style="font-size: 10px; font-weight: 700; letter-spacing: -0.01em; white-space: nowrap; color: <?= $is_live_page_active ? '#00bfa5' : (($is_nav_locked ? '#475569' : '#0f172a')) ?>;">Live Feed</span>
    </a>

    <!-- Analytics -->
    <a href="?page=historical<?= $node_suffix ?>" data-bottom-nav="historical" onclick="window.triggerHaptic('light')" class="<?= ($is_nav_locked && $current_page !== 'historical') ? 'bottom-nav-locked' : ($current_page === 'historical' ? 'active' : '') ?>" style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 2px; flex: 1; min-width: 44px; padding: 4px 2px; border-radius: 12px; text-decoration: none; transition: all 0.18s cubic-bezier(0.16,1,0.3,1); color: <?= $current_page === 'historical' ? '#00bfa5' : (($is_nav_locked ? '#475569' : '#0f172a')) ?>; background-color: <?= $current_page === 'historical' ? 'rgba(0, 191, 165, 0.12)' : 'transparent' ?>; opacity: <?= $current_page === 'historical' ? '1' : (($is_nav_locked ? '0.88' : '1')) ?>; cursor: pointer;">
      <i data-lucide="database" style="width: 19px; height: 19px; stroke-width: <?= $current_page === 'historical' ? '2.3' : '2.2' ?>; flex-shrink: 0; color: <?= $current_page === 'historical' ? '#00bfa5' : (($is_nav_locked ? '#475569' : '#0f172a')) ?>;"></i>
      <span style="font-size: 9.5px; font-weight: 700; letter-spacing: -0.01em; white-space: nowrap; color: <?= $current_page === 'historical' ? '#00bfa5' : (($is_nav_locked ? '#475569' : '#0f172a')) ?>;">Analytics</span>
    </a>

    <!-- Forecast -->
    <a href="?page=predictive<?= $node_suffix ?>" data-bottom-nav="predictive" onclick="window.triggerHaptic('light')" class="<?= ($is_nav_locked && $current_page !== 'predictive') ? 'bottom-nav-locked' : ($current_page === 'predictive' ? 'active' : '') ?>" style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 2px; flex: 1; min-width: 44px; padding: 4px 2px; border-radius: 12px; text-decoration: none; transition: all 0.18s cubic-bezier(0.16,1,0.3,1); color: <?= $current_page === 'predictive' ? '#00bfa5' : (($is_nav_locked ? '#475569' : '#0f172a')) ?>; background-color: <?= $current_page === 'predictive' ? 'rgba(0, 191, 165, 0.12)' : 'transparent' ?>; opacity: <?= $current_page === 'predictive' ? '1' : (($is_nav_locked ? '0.88' : '1')) ?>; cursor: pointer;">
      <i data-lucide="cloud-rain" style="width: 19px; height: 19px; stroke-width: <?= $current_page === 'predictive' ? '2.3' : '2.2' ?>; flex-shrink: 0; color: <?= $current_page === 'predictive' ? '#00bfa5' : (($is_nav_locked ? '#475569' : '#0f172a')) ?>;"></i>
      <span style="font-size: 9.5px; font-weight: 700; letter-spacing: -0.01em; white-space: nowrap; color: <?= $current_page === 'predictive' ? '#00bfa5' : (($is_nav_locked ? '#475569' : '#0f172a')) ?>;">Forecast</span>
    </a>

  </nav>

  <!-- User Account Profile Modal (Mobile & Desktop) -->
  <div id="account-modal" style="display: none; position: fixed; inset: 0; z-index: 9999; align-items: center; justify-content: center; padding: 16px; background-color: rgba(15, 23, 42, 0.65); backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px);">
    <div onclick="closeAccountModal()" style="position: absolute; inset: 0;"></div>
    <div style="position: relative; z-index: 10; width: 100%; max-width: 360px; background-color: #ffffff; border-radius: 24px; box-shadow: 0 20px 40px -10px rgba(15, 23, 42, 0.25); overflow: hidden; font-family: var(--font-sans); color: #0f172a; animation: modal-pop 0.18s ease-out;">
      <div style="height: 4px; width: 100%; background: linear-gradient(90deg, #00bfa5 0%, #0284c7 100%);"></div>
      <button onclick="closeAccountModal()" style="position: absolute; top: 14px; right: 14px; width: 30px; height: 30px; border-radius: 50%; border: 1px solid #e2e8f0; background: #f8fafc; color: #64748b; display: flex; align-items: center; justify-content: center; cursor: pointer;">
        <i data-lucide="x" style="width: 15px; height: 15px;"></i>
      </button>
      <div style="padding: 24px 20px 20px; text-align: center;">
        <div id="acc-modal-avatar" style="width: 60px; height: 60px; border-radius: 50%; background: linear-gradient(135deg, #00bfa5 0%, #0d9488 100%); color: white; display: flex; align-items: center; justify-content: center; font-size: 26px; font-weight: 800; margin: 0 auto 12px; box-shadow: 0 4px 14px rgba(0, 191, 165, 0.35);">
          <?= htmlspecialchars($user_initial) ?>
        </div>
        <h3 id="acc-modal-name" style="margin: 0; font-size: 17px; font-weight: 800; color: #0f172a; letter-spacing: -0.02em;">
          <?= htmlspecialchars($user_display_name) ?>
        </h3>
        <p id="acc-modal-email" style="margin: 3px 0 14px; font-size: 13px; color: #64748b; word-break: break-all; font-family: var(--font-mono); ">
          <?= htmlspecialchars($user_email ?: 'Signed in user') ?>
        </p>
        <div style="display: inline-flex; align-items: center; gap: 5px; padding: 4px 12px; border-radius: 999px; background: #ecfdf5; border: 1px solid #a7f3d0; color: #059669; font-size: 11px; font-weight: 700; margin-bottom: 20px;">
          <span style="width: 6px; height: 6px; border-radius: 50%; background-color: #10b981;"></span>
          <span>Active Authenticated Session</span>
        </div>
        <div style="display: flex; flex-direction: column; gap: 8px;">
          <a href="?logout=true" onclick="window.signOutUser(event); return false;" style="display: flex; align-items: center; justify-content: center; gap: 7px; width: 100%; padding: 11px 16px; border-radius: 12px; background: #fee2e2; border: 1.5px solid #fca5a5; color: #b91c1c; font-size: 13px; font-weight: 700; text-decoration: none; cursor: pointer; transition: all 0.15s ease;">
            <i data-lucide="log-out" style="width: 15px; height: 15px;"></i>
            <span>Sign Out from this Device</span>
          </a>
          <button type="button" onclick="closeAccountModal()" style="width: 100%; padding: 9px 16px; border-radius: 12px; background: #f1f5f9; border: 1px solid #e2e8f0; color: #475569; font-size: 12.5px; font-weight: 600; cursor: pointer;">
            Close
          </button>
        </div>
      </div>
    </div>
  </div>
  
  <?php include __DIR__ . '/auth-modal.php'; ?>
  
  <!-- Contact Us Developer Modal -->
  <div id="contact-modal" style="display: none; position: fixed; z-index: 10000; inset: 0; align-items: center; justify-content: center; padding: 20px;">
    <!-- Backdrop -->
    <div style="position: absolute; inset: 0; background-color: rgba(15, 23, 42, 0.4); backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px);" onclick="document.getElementById('contact-modal').style.display='none';"></div>
    
    <!-- Modal Content -->
    <div style="position: relative; background: #ffffff; border-radius: 24px; padding: 32px 24px; max-width: 400px; width: 100%; box-shadow: 0 20px 40px rgba(15, 23, 42, 0.1); border: 1px solid #e2e8f0; text-align: center; animation: fade-in 0.2s ease-out;">
      <button onclick="document.getElementById('contact-modal').style.display='none';" style="position: absolute; top: 16px; right: 16px; background: #f1f5f9; border: none; border-radius: 50%; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: #64748b;">
        <i data-lucide="x" style="width: 16px; height: 16px;"></i>
      </button>
      
      <div style="width: 64px; height: 64px; border-radius: 18px; background: #ffffff; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px auto; box-shadow: 0 4px 14px rgba(15, 23, 42, 0.08); border: 1px solid #e2e8f0; padding: 6px;">
        <img src="<?= defined('SMART_WEATHERNET_LOGO_MARK') ? SMART_WEATHERNET_LOGO_MARK : 'assets/logo-mark.png' ?>" alt="Smart WeatherNet" style="width: 100%; height: 100%; object-fit: contain;" />
      </div>

      <div style="margin: 0 auto; width: fit-content; max-width: 100%; font-size: 13.5px; font-weight: 600; color: #475569; display: flex; flex-direction: column; gap: 7px; align-items: flex-start; text-align: left;">
        <span style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #94a3b8; font-weight: 700; align-self: center; margin-bottom: 2px;">Developed by</span>
        <a href="https://www.linkedin.com/in/manish-kumar-129b53330?utm_source=share_via&amp;utm_content=profile&amp;utm_medium=member_android" target="_blank" rel="noopener noreferrer" style="color: #00bfa5; text-decoration: none; font-weight: 700; display: inline-flex; align-items: center; gap: 5px;">
          <span>Manish Kumar</span>
          <i data-lucide="external-link" style="width: 12px; height: 12px;"></i>
        </a>
        <a href="https://www.linkedin.com/in/prathvish-kumar-037b60294?utm_source=share_via&amp;utm_content=profile&amp;utm_medium=member_android" target="_blank" rel="noopener noreferrer" style="color: #00bfa5; text-decoration: none; font-weight: 700; display: inline-flex; align-items: center; gap: 5px;">
          <span>Prathvish Kumar</span>
          <i data-lucide="external-link" style="width: 12px; height: 12px;"></i>
        </a>
        <a href="https://www.linkedin.com/in/manikanta-ch-2027935815035727046?utm_source=share_via&amp;utm_content=profile&amp;utm_medium=member_android" target="_blank" rel="noopener noreferrer" style="color: #00bfa5; text-decoration: none; font-weight: 700; display: inline-flex; align-items: center; gap: 5px;">
          <span>Manikanta CH</span>
          <i data-lucide="external-link" style="width: 12px; height: 12px;"></i>
        </a>
        <a href="https://www.linkedin.com/in/madan-k-19412b33b?utm_source=share_via&amp;utm_content=profile&amp;utm_medium=member_android" target="_blank" rel="noopener noreferrer" style="color: #00bfa5; text-decoration: none; font-weight: 700; display: inline-flex; align-items: center; gap: 5px;">
          <span>Madan K</span>
          <i data-lucide="external-link" style="width: 12px; height: 12px;"></i>
        </a>
        <a href="https://www.linkedin.com/in/aditya-thunga-k-365a1033a?utm_source=share_via&amp;utm_content=profile&amp;utm_medium=member_android" target="_blank" rel="noopener noreferrer" style="color: #00bfa5; text-decoration: none; font-weight: 700; display: inline-flex; align-items: center; gap: 5px;">
          <span>Aditya Thunga K</span>
          <i data-lucide="external-link" style="width: 12px; height: 12px;"></i>
        </a>
      </div>
      
      <div style="margin: 20px auto; width: 40px; height: 2px; background-color: #e2e8f0; border-radius: 2px;"></div>

      <p style="margin: 0; font-size: 13px; font-weight: 500; color: #64748b;">
        Under the Guidance of<br/>
        <a href="https://sode-edu.in/smvitm/about-smvitm/principal/" target="_blank" style="color: #334155; font-size: 14px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; border-bottom: 1.5px solid #334155; padding-bottom: 1px; margin-top: 4px;">
          Dr. Nagaraj Bhat <i data-lucide="arrow-up-right" style="width: 14px; height: 14px;"></i>
        </a>
      </p>

      <div style="margin: 20px auto; width: 40px; height: 2px; background-color: #e2e8f0; border-radius: 2px;"></div>

      <div style="display: flex; flex-direction: column; align-items: center; gap: 6px;">
        <span style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #94a3b8;">Contact Email</span>
        <a href="mailto:weathernet@sode-edu.in" style="font-weight: 600; font-size: 13px; color: #475569; background: #f8fafc; border: 1px solid #e2e8f0; padding: 8px 16px; border-radius: 12px; text-decoration: none; display: inline-flex; align-items: center; gap: 7px; transition: all 0.2s;" onmouseover="this.style.borderColor='#00bfa5'; this.style.color='#00bfa5'; this.style.background='#f0fdfa';" onmouseout="this.style.borderColor='#e2e8f0'; this.style.color='#475569'; this.style.background='#f8fafc';">
          <i data-lucide="mail" style="width: 15px; height: 15px; color: #00bfa5;"></i>
          <span>weathernet@sode-edu.in</span>
        </a>
      </div>
    </div>
  </div>

</div>



<?php if (isset($_GET['require_login']) && $_GET['require_login'] === 'true'): ?>
<script>
  document.addEventListener("DOMContentLoaded", () => {
      const targetPg = (new URLSearchParams(window.location.search).get('target')) || 'overview';
      setTimeout(() => {
          if (typeof openAuthModal === 'function') {
              if (targetPg) window.__pendingTargetPage = targetPg;
              openAuthModal(true);
              
              const errBlock = document.getElementById('auth-error-msg');
              const errText = document.getElementById('auth-error-text');
              if (errBlock && errText) {
                  const pageLabels = {
                      live: 'Live Data Stream',
                      historical: 'Analytics Archive',
                      predictive: 'Forecast & Advisory',
                      weather: 'Weather Telemetry',
                  };
                  const label = pageLabels[targetPg] || 'this page';
                  errText.innerText = "Please sign in to view " + label + ".";
                  errBlock.style.backgroundColor = "rgba(0, 191, 165, 0.1)";
                  errBlock.style.color = "#00bfa5";
                  errBlock.style.border = "1px solid rgba(0, 191, 165, 0.2)";
                  errBlock.style.display = 'flex';
              }
          }
      }, 100);
  });
</script>
<?php endif; ?>

