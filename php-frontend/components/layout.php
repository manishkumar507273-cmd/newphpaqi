<?php
require_once __DIR__ . '/brand-assets.php';
$current_page = isset($_GET['page']) ? $_GET['page'] : 'overview';
$is_admin = isset($_SESSION['is_admin']) ? $_SESSION['is_admin'] : false;
$is_logged_in = isset($_SESSION['user_id']);

$page_titles = [
    'overview'   => 'Atmosphere Snapshot',
    'live'       => 'Live Data Stream',
    'historical' => 'Analytics Archive',
    'predictive' => 'Forecast & Advisory',
    'admin'      => 'Admin Center',
    'weather'    => 'Weather Conditions'
];
$current_title = $page_titles[$current_page] ?? 'Smart WeatherNet';

// Parse credentials from frontend or shared .env
$env_paths = [
    __DIR__ . '/../.env',
    __DIR__ . '/../../.env',
    __DIR__ . '/../../../.env',
    __DIR__ . '/../../php-backend/.env',
    __DIR__ . '/../../backend/.env'
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
    }
}

// Fallback to Vercel/System environment variables if not found in .env file
if (empty($env_config['supabaseUrl'])) $env_config['supabaseUrl'] = getenv('VITE_SUPABASE_URL') ?: getenv('SUPABASE_URL') ?: '';
if (empty($env_config['supabaseKey'])) $env_config['supabaseKey'] = getenv('VITE_SUPABASE_ANON_KEY') ?: getenv('SUPABASE_KEY') ?: '';
if (empty($env_config['firebaseApiKey'])) $env_config['firebaseApiKey'] = getenv('VITE_FIREBASE_API_KEY') ?: getenv('FIREBASE_API_KEY') ?: '';
if (empty($env_config['firebaseAuthDomain'])) $env_config['firebaseAuthDomain'] = getenv('VITE_FIREBASE_AUTH_DOMAIN') ?: getenv('FIREBASE_AUTH_DOMAIN') ?: '';
if (empty($env_config['firebaseProjectId'])) $env_config['firebaseProjectId'] = getenv('VITE_FIREBASE_PROJECT_ID') ?: getenv('FIREBASE_PROJECT_ID') ?: '';
if (empty($env_config['firebaseDatabaseUrl'])) $env_config['firebaseDatabaseUrl'] = getenv('FIREBASE_DATABASE_URL') ?: getenv('VITE_FIREBASE_DATABASE_URL') ?: '';

?>

<script>
window.ENV_CONFIG = <?= json_encode($env_config) ?>;
window.FIREBASE_ENV_CONFIG = {
    apiKey: window.ENV_CONFIG.firebaseApiKey || "AIzaSyC8UYQMhOaS5SFAdkCcdISTkEWw57WsmFs",
    authDomain: window.ENV_CONFIG.firebaseAuthDomain || "smart-air-net.firebaseapp.com",
    projectId: window.ENV_CONFIG.firebaseProjectId || "smart-air-net",
    databaseURL: window.ENV_CONFIG.firebaseDatabaseUrl || "https://smart-air-net-default-rtdb.firebaseio.com",
    storageBucket: "smart-air-net.firebasestorage.app",
    messagingSenderId: "647061464752",
    appId: "1:647061464752:web:349c77286659f3a9d4fd75",
    measurementId: "G-09JTD9RQQJ"
};
window.__currentPage = <?= json_encode($current_page) ?>;
window.__currentUser = <?= json_encode([
    'is_logged_in' => $is_logged_in,
    'name' => $is_logged_in ? ($_SESSION['user_name'] ?? '') : '',
    'email' => $is_logged_in ? ($_SESSION['user_email'] ?? '') : ''
]) ?>;
window.__currentUserEmail = <?= json_encode($is_logged_in ? ($_SESSION['user_email'] ?? '') : '') ?>;

// Automatic Persistent Google / User Account Re-entry:
// When someone signed in using a Google account and comes again on mobile or laptop,
// they are let in directly without asking for login again.
(function() {
    if (window.location.search.includes('logout=true') || window.location.search.includes('logged_out=true')) return;
    
    if (localStorage.getItem('auth_email') === 'manish@sode-edu.in' || localStorage.getItem('auth_email') === 'manishb@sode-edu.in') {
        localStorage.removeItem('auth_email');
        localStorage.removeItem('auth_name');
    }
    
    var isPhpLoggedIn = <?= $is_logged_in ? 'true' : 'false' ?>;
    if (!isPhpLoggedIn) {
        var savedEmail = localStorage.getItem('auth_email');
        var savedName = localStorage.getItem('auth_name') || (savedEmail ? savedEmail.split('@')[0] : '');
        var isLoggedInFlag = localStorage.getItem('auth_logged_in') === 'true';
        
        if (isLoggedInFlag && savedEmail && savedEmail !== 'manish@sode-edu.in' && savedEmail !== 'manishb@sode-edu.in') {
            console.log("Persistent Google/user account recognized, restoring session directly:", savedEmail);
            var page = window.__currentPage || 'overview';
            window.location.replace('?page=' + encodeURIComponent(page) + '&auth=true&name=' + encodeURIComponent(savedName) + '&email=' + encodeURIComponent(savedEmail));
        }
    }
})();

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

window.showMobileToast = function() {};
</script>

<div style="min-height: 100vh; background-color: #f1f5f9; background-image: radial-gradient(ellipse 120% 80% at 50% -20%, #e2e8f0 0%, #f1f5f9 65%, #e2e8f0 100%); position: relative; width: 100%; display: flex; flex-direction: column;">

  <!-- Sticky Header with Safe-Area Inset -->
  <header style="min-height: calc(60px + env(safe-area-inset-top, 0px)); padding-top: env(safe-area-inset-top, 0px); display: flex; align-items: center; justify-content: space-between; gap: 12px; position: sticky; top: 0; z-index: 50; background-color: rgba(255, 255, 255, 0.94); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); border-bottom: 1px solid #e2e8f0; box-shadow: 0 4px 20px rgba(15, 23, 42, 0.03); width: 100%; box-sizing: border-box; padding-left: 16px; padding-right: 16px;">
    
    <!-- Left: Desktop Brand & Mobile Header Selector -->
    <div style="display: flex; align-items: center; gap: 12px; min-width: 0;">
      
      <!-- Desktop Brand (Always visible on md+ screens) -->
      <div class="hidden md:flex items-center gap-2.5">
        <a href="?page=overview" style="display: flex; align-items: center; gap: 11px; cursor: pointer; text-decoration: none;">
          <div style="width: 42px; height: 42px; border-radius: 12px; background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%); display: flex; align-items: center; justify-content: center; box-shadow: 0 3px 12px rgba(15, 23, 42, 0.08); border: 1px solid #e2e8f0; flex-shrink: 0; overflow: hidden; padding: 3px;">
            <img src="<?= defined('SMART_WEATHERNET_LOGO_MARK') ? SMART_WEATHERNET_LOGO_MARK : 'assets/logo-mark.png' ?>" alt="Smart WeatherNet Logo" style="width: 100%; height: 100%; object-fit: contain;" />
          </div>
          <div style="display: flex; flex-direction: column; gap: 1px; overflow: hidden; padding-top: 2px;">
            <div style="font-size: 18px; font-weight: 800; color: #0f172a; letter-spacing: -0.03em; white-space: nowrap; line-height: 1.1;">
              Smart <span style="font-weight: 600; color: #00bfa5;">WeatherNet</span>
            </div>
            <div style="display: flex; align-items: center; gap: 5px; margin-top: 2px;">
              <span class="live-dot-pulse"></span>
              <span style="font-size: 11px; font-weight: 700; color: #475569; white-space: nowrap;">SMVITM Campus</span>
              <span style="font-size: 10px; font-weight: 600; color: #94a3b8; border-left: 1px solid #e2e8f0; padding-left: 6px; margin-left: 2px; white-space: nowrap;">13°15'40" N, 74°47'13" E</span>
            </div>
          </div>
        </a>
      </div>

      <!-- Mobile Top App Bar (Brand always visible across all pages) -->
      <div class="flex md:hidden items-center gap-2 min-w-0" id="mobile-top-bar">
        <!-- Mobile Brand (Always visible on all pages) -->
        <a href="?page=overview" id="mobile-brand-overview" style="display: flex; align-items: center; gap: 10px; text-decoration: none; min-width: 0;">
          <div style="width: 38px; height: 38px; border-radius: 11px; background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%); display: flex; align-items: center; justify-content: center; box-shadow: 0 3px 10px rgba(15, 23, 42, 0.08); border: 1px solid #e2e8f0; flex-shrink: 0; overflow: hidden; padding: 2px;">
            <img src="<?= defined('SMART_WEATHERNET_LOGO_MARK') ? SMART_WEATHERNET_LOGO_MARK : 'assets/logo-mark.png' ?>" alt="Smart WeatherNet Logo" style="width: 100%; height: 100%; object-fit: contain;" />
          </div>
          <div style="display: flex; flex-direction: column; overflow: hidden;">
            <span style="font-size: 16px; font-weight: 800; color: #0f172a; letter-spacing: -0.02em; white-space: nowrap; line-height: 1.1;">
              Smart <span style="color: #00bfa5;">WeatherNet</span>
            </span>
            <div style="display: flex; align-items: center; gap: 4px; margin-top: 1px;">
              <span class="live-dot-pulse"></span>
              <span style="font-size: 10.5px; font-weight: 700; color: #64748b; white-space: nowrap;">SMVITM Campus</span>
              <span style="font-size: 9.5px; font-weight: 600; color: #94a3b8; border-left: 1px solid #e2e8f0; padding-left: 5px; margin-left: 2px; white-space: nowrap;">13°15'40" N, 74°47'13" E</span>
            </div>
          </div>
        </a>

        <!-- Secondary Page Mobile App Bar: hidden so brand is always shown -->
        <div id="mobile-secondary-header" style="display: none; align-items: center; gap: 8px; min-width: 0;">
          <a href="?page=overview" id="header-back-btn" title="Back to Overview" style="display: none; align-items: center; gap: 3px; padding: 6px 10px 6px 6px; border-radius: 10px; border: 1.5px solid #e2e8f0; background: #ffffff; color: #475569; text-decoration: none; font-size: 12px; font-weight: 700; -webkit-tap-highlight-color: transparent; flex-shrink: 0;">
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

    <!-- Center: Desktop Nav -->
    <nav id="desktop-nav" style="align-items: center; background-color: #f8fafc; padding: 4px; border-radius: 999px; border: 1px solid #e2e8f0; box-shadow: 0 2px 10px rgba(15,23,42,0.04); gap: 2px;" class="hidden md:inline-flex">
      <a href="?page=overview" data-nav-link="overview" style="display: flex; align-items: center; gap: 7px; padding: 8px 16px; border-radius: 999px; font-size: 13px; font-weight: 600; text-decoration: none; color: <?= $current_page === 'overview' ? '#ffffff' : '#64748b' ?>; background-color: <?= $current_page === 'overview' ? '#00bfa5' : 'transparent' ?>; box-shadow: <?= $current_page === 'overview' ? '0 4px 14px rgba(0, 191, 165, 0.35)' : 'none' ?>; transition: all 0.2s ease;">
        <i data-lucide="layout-dashboard" style="width: 15px; height: 15px; opacity: <?= $current_page === 'overview' ? '1' : '0.75' ?>;"></i> Overview
      </a>
      <a href="?page=live" data-nav-link="live" style="display: flex; align-items: center; gap: 7px; padding: 8px 16px; border-radius: 999px; font-size: 13px; font-weight: 600; text-decoration: none; color: <?= $current_page === 'live' ? '#ffffff' : '#64748b' ?>; background-color: <?= $current_page === 'live' ? '#00bfa5' : 'transparent' ?>; box-shadow: <?= $current_page === 'live' ? '0 4px 14px rgba(0, 191, 165, 0.35)' : 'none' ?>; transition: all 0.2s ease;">
        <i data-lucide="radio" style="width: 15px; height: 15px; opacity: <?= $current_page === 'live' ? '1' : '0.75' ?>;"></i> Live Data Stream
      </a>
      <a href="?page=historical" data-nav-link="historical" style="display: flex; align-items: center; gap: 7px; padding: 8px 16px; border-radius: 999px; font-size: 13px; font-weight: 600; text-decoration: none; color: <?= $current_page === 'historical' ? '#ffffff' : '#64748b' ?>; background-color: <?= $current_page === 'historical' ? '#00bfa5' : 'transparent' ?>; box-shadow: <?= $current_page === 'historical' ? '0 4px 14px rgba(0, 191, 165, 0.35)' : 'none' ?>; transition: all 0.2s ease;">
        <i data-lucide="database" style="width: 15px; height: 15px; opacity: <?= $current_page === 'historical' ? '1' : '0.75' ?>;"></i> Analytics Archive
      </a>
      <a href="?page=predictive" data-nav-link="predictive" style="display: flex; align-items: center; gap: 7px; padding: 8px 16px; border-radius: 999px; font-size: 13px; font-weight: 600; text-decoration: none; color: <?= $current_page === 'predictive' ? '#ffffff' : '#64748b' ?>; background-color: <?= $current_page === 'predictive' ? '#00bfa5' : 'transparent' ?>; box-shadow: <?= $current_page === 'predictive' ? '0 4px 14px rgba(0, 191, 165, 0.35)' : 'none' ?>; transition: all 0.2s ease;">
        <i data-lucide="line-chart" style="width: 15px; height: 15px; opacity: <?= $current_page === 'predictive' ? '1' : '0.75' ?>;"></i> Predictive Forecast
      </a>
    </nav>

    <!-- Right: Auth & Admin & Mobile Trigger -->
    <div style="display: flex; align-items: center; gap: 8px;">

      <!-- Desktop Auth & Admin -->
      <div class="hidden md:flex items-center gap-2.5">
        <?php if ($is_logged_in && strpos(strtolower($_SESSION['user_name']), 'admin') !== false): ?>
          <a href="?page=admin" style="display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; border-radius: 999px; border: 1.5px solid #e2e8f0; background-color: #ffffff; color: #475569; font-size: 13px; font-weight: 700; cursor: pointer; text-decoration: none; transition: all 0.15s ease;">
            <i data-lucide="shield-check" style="width: 14px; height: 14px; color: #00bfa5;"></i>
            <span>Admin Center</span>
          </a>
        <?php endif; ?>

        <?php if ($is_logged_in): ?>
          <div style="display: flex; align-items: center; gap: 8px; background-color: #ffffff; border: 1.5px solid #e2e8f0; border-radius: 999px; padding: 3px 10px 3px 4px; box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);">
            <div style="width: 28px; height: 28px; border-radius: 50%; background-color: #00bfa5; color: white; display: flex; align-items: center; justify-content: center; font-size: 12.5px; font-weight: 700; box-shadow: 0 1px 3px rgba(0, 191, 165, 0.3);">
              <?= strtoupper(substr($_SESSION['user_name'], 0, 1)) ?>
            </div>
            <span style="font-size: 12px; font-weight: 700; color: #0f172a;"><?= $_SESSION['user_name'] ?></span>
            <a href="?logout=true" title="Sign Out" style="padding: 4px 6px; border-radius: 999px; border: none; background: #f1f5f9; color: #64748b; cursor: pointer; text-decoration: none; transition: all 0.15s ease;">
              <i data-lucide="log-out" style="width: 13px; height: 13px;"></i>
            </a>
          </div>
        <?php else: ?>
          <button onclick="openAuthModal()" style="display: inline-flex; align-items: center; gap: 6px; padding: 7px 16px; border-radius: 999px; border: none; background-color: #0f172a; color: #ffffff; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1); box-shadow: 0 4px 12px rgba(15, 23, 42, 0.15);">
            <i data-lucide="user" style="width: 14px; height: 14px;"></i>
            <span>Sign In</span>
          </button>
        <?php endif; ?>
        
        <!-- Live Badge -->
        <div style="display: flex; padding: 6px 14px; border-radius: 999px; font-size: 11.5px; font-weight: 700; align-items: center; gap: 6px; background-color: #dcfce7; color: #15803d; border: 1.5px solid #bbf7d0; font-family: var(--font-mono); white-space: nowrap;">
          <span style="width: 7px; height: 7px; border-radius: 50%; background-color: #16a34a;"></span>
          <span>LIVE</span>
        </div>
      </div>

      <!-- Mobile Right Items (Visible on mobile screens) -->
      <div class="flex md:hidden items-center gap-2">
        <!-- Animated LIVE badge -->
        <div style="display: flex; padding: 5px 10px; border-radius: 999px; font-size: 10.5px; font-weight: 700; align-items: center; gap: 6px; background-color: #dcfce7; color: #15803d; border: 1.5px solid #bbf7d0; font-family: var(--font-mono); white-space: nowrap;">
          <span class="live-dot-pulse" style="background-color: #16a34a;"></span>
          <span>LIVE</span>
        </div>
      </div>
    </div>
  </header>

  <script>
    function toggleMobileMenu(forceState = null) {}
  </script>

  <!-- Main Content Area -->
  <main style="flex: 1; display: flex; flex-direction: column; position: relative;">
    <div id="page-content-wrapper" class="page-fade-active" style="flex-grow: 1;">
      <?php
        $page_file = __DIR__ . '/../pages/' . $current_page . '.php';
        if (file_exists($page_file)) {
            include $page_file;
        } else {
            echo "<div style='padding: 40px; text-align: center; color: #64748b;'>Page not found. (" . htmlspecialchars($current_page) . ")</div>";
        }
      ?>
    </div>
    
    <!-- Global Footer (Contact Us & Campus Credits) -->
    <footer id="global-footer" style="margin-top: auto; padding: 22px 16px; text-align: center; position: relative; z-index: 10; font-size: 13px; background: #ffffff; color: #64748b; border-top: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: center; gap: 14px; flex-wrap: wrap; width: 100%; box-sizing: border-box; box-shadow: 0 -2px 10px rgba(15, 23, 42, 0.02);" class="mb-[84px] md:mb-0">
      
      <div style="display: flex; align-items: center; gap: 8px;">
        <img src="<?= defined('SMART_WEATHERNET_LOGO_MARK') ? SMART_WEATHERNET_LOGO_MARK : 'assets/logo-mark.png' ?>" alt="Logo" style="width: 22px; height: 22px; object-fit: contain;">
        <span style="font-weight: 700; color: #0f172a;">Smart WeatherNet</span>
        <span style="font-size: 11px; color: #94a3b8; font-weight: 600;">&bull; SMVITM Campus</span>
      </div>

      <span style="color: #cbd5e1; font-weight: 300;" class="hidden sm:inline">|</span>

      <button type="button" onclick="document.getElementById('contact-modal').style.display='flex';" style="background: none; border: none; font-size: 13px; font-weight: 700; color: #00bfa5; cursor: pointer; text-decoration: underline; text-decoration-color: rgba(0,191,165,0.4); text-underline-offset: 4px; transition: all 0.2s; padding: 0;" onmouseover="this.style.color='#0d9488';" onmouseout="this.style.color='#00bfa5';">
        Contact Us
      </button>

      <span style="color: #cbd5e1; font-weight: 300;" class="hidden sm:inline">|</span>

      <a href="mailto:weathernet@sode-edu.in" style="font-weight: 600; color: #475569; text-decoration: none; display: flex; align-items: center; gap: 5px; transition: color 0.2s;" onmouseover="this.style.color='#00bfa5'" onmouseout="this.style.color='#475569'">
        <i data-lucide="mail" style="width: 14px; height: 14px; color: #00bfa5;"></i>
        <span>weathernet@sode-edu.in</span>
      </a>
      
    </footer>
  </main>

  <!-- Fixed Native Mobile Bottom Navigation Bar —— Safe area compliant & haptic response -->
  <nav id="mobile-bottom-nav" class="mobile-bottom-nav md:hidden" style="display: flex; flex-direction: row; align-items: stretch; justify-content: space-around; width: 100%; position: fixed; bottom: 0; left: 0; right: 0; z-index: 100; background: rgba(255, 255, 255, 0.97); backdrop-filter: blur(24px) saturate(1.4); -webkit-backdrop-filter: blur(24px) saturate(1.4); border-top: 1px solid rgba(226, 232, 240, 0.8); height: calc(64px + env(safe-area-inset-bottom, 14px)); padding: 6px 8px calc(6px + env(safe-area-inset-bottom, 14px)); box-shadow: 0 -6px 24px rgba(15, 23, 42, 0.07); overflow-x: auto; -webkit-overflow-scrolling: touch; scrollbar-width: none;">

    <!-- Overview -->
    <a href="?page=overview" data-bottom-nav="overview" onclick="window.triggerHaptic('light')" style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 3px; flex: 1; min-width: 52px; padding: 5px 8px; border-radius: 14px; text-decoration: none; transition: all 0.18s cubic-bezier(0.16,1,0.3,1); color: <?= $current_page === 'overview' ? '#00bfa5' : '#64748b' ?>; background-color: <?= $current_page === 'overview' ? 'rgba(0, 191, 165, 0.1)' : 'transparent' ?>;">
      <i data-lucide="home" style="width: 20px; height: 20px; stroke-width: 2.2; flex-shrink: 0; <?= $current_page === 'overview' ? 'color: #00bfa5;' : '' ?>"></i>
      <span style="font-size: 10px; font-weight: <?= $current_page === 'overview' ? '700' : '600' ?>; letter-spacing: -0.01em; white-space: nowrap;">Overview</span>
    </a>

    <!-- Live Feed -->
    <a href="?page=live" data-bottom-nav="live" onclick="window.triggerHaptic('light')" style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 3px; flex: 1; min-width: 52px; padding: 5px 8px; border-radius: 14px; text-decoration: none; transition: all 0.18s cubic-bezier(0.16,1,0.3,1); color: <?= $current_page === 'live' ? '#00bfa5' : '#64748b' ?>; background-color: <?= $current_page === 'live' ? 'rgba(0, 191, 165, 0.1)' : 'transparent' ?>;">
      <i data-lucide="radio" style="width: 20px; height: 20px; stroke-width: 2.2; flex-shrink: 0; <?= $current_page === 'live' ? 'color: #00bfa5;' : '' ?>"></i>
      <span style="font-size: 10px; font-weight: <?= $current_page === 'live' ? '700' : '600' ?>; letter-spacing: -0.01em; white-space: nowrap;">Live Feed</span>
    </a>

    <!-- Analytics -->
    <a href="?page=historical" data-bottom-nav="historical" onclick="window.triggerHaptic('light')" style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 3px; flex: 1; min-width: 52px; padding: 5px 8px; border-radius: 14px; text-decoration: none; transition: all 0.18s cubic-bezier(0.16,1,0.3,1); color: <?= $current_page === 'historical' ? '#00bfa5' : '#64748b' ?>; background-color: <?= $current_page === 'historical' ? 'rgba(0, 191, 165, 0.1)' : 'transparent' ?>;">
      <i data-lucide="database" style="width: 20px; height: 20px; stroke-width: 2.2; flex-shrink: 0; <?= $current_page === 'historical' ? 'color: #00bfa5;' : '' ?>"></i>
      <span style="font-size: 10px; font-weight: <?= $current_page === 'historical' ? '700' : '600' ?>; letter-spacing: -0.01em; white-space: nowrap;">Analytics</span>
    </a>

    <!-- Forecast -->
    <a href="?page=predictive" data-bottom-nav="predictive" onclick="window.triggerHaptic('light')" style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 3px; flex: 1; min-width: 52px; padding: 5px 8px; border-radius: 14px; text-decoration: none; transition: all 0.18s cubic-bezier(0.16,1,0.3,1); color: <?= $current_page === 'predictive' ? '#00bfa5' : '#64748b' ?>; background-color: <?= $current_page === 'predictive' ? 'rgba(0, 191, 165, 0.1)' : 'transparent' ?>;">
      <i data-lucide="line-chart" style="width: 20px; height: 20px; stroke-width: 2.2; flex-shrink: 0; <?= $current_page === 'predictive' ? 'color: #00bfa5;' : '' ?>"></i>
      <span style="font-size: 10px; font-weight: <?= $current_page === 'predictive' ? '700' : '600' ?>; letter-spacing: -0.01em; white-space: nowrap;">Forecast</span>
    </a>

    <!-- Admin / Auth -->
    <?php if ($is_logged_in && strpos(strtolower($_SESSION['user_name']), 'admin') !== false): ?>
      <a href="?page=admin" data-bottom-nav="admin" onclick="window.triggerHaptic('light')" style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 3px; flex: 1; min-width: 52px; padding: 5px 8px; border-radius: 14px; text-decoration: none; transition: all 0.18s cubic-bezier(0.16,1,0.3,1); color: <?= $current_page === 'admin' ? '#00bfa5' : '#64748b' ?>; background-color: <?= $current_page === 'admin' ? 'rgba(0, 191, 165, 0.1)' : 'transparent' ?>;">
        <i data-lucide="shield-check" style="width: 20px; height: 20px; stroke-width: 2.2; flex-shrink: 0; <?= $current_page === 'admin' ? 'color: #00bfa5;' : '' ?>"></i>
        <span style="font-size: 10px; font-weight: <?= $current_page === 'admin' ? '700' : '600' ?>; letter-spacing: -0.01em; white-space: nowrap;">Admin</span>
      </a>
    <?php elseif ($is_logged_in): ?>
      <a href="?logout=true" onclick="window.triggerHaptic('light')" style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 3px; flex: 1; min-width: 52px; padding: 5px 8px; border-radius: 14px; text-decoration: none; color: #64748b; transition: all 0.18s cubic-bezier(0.16,1,0.3,1);">
        <div style="width: 20px; height: 20px; border-radius: 50%; background-color: #00bfa5; color: white; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 800; flex-shrink: 0;">
          <?= strtoupper(substr($_SESSION['user_name'], 0, 1)) ?>
        </div>
        <span style="font-size: 10px; font-weight: 600; letter-spacing: -0.01em; white-space: nowrap;">Sign Out</span>
      </a>
    <?php else: ?>
      <button onclick="window.triggerHaptic('light'); openAuthModal();" style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 3px; flex: 1; min-width: 52px; padding: 5px 8px; border-radius: 14px; border: none; background: transparent; cursor: pointer; color: #64748b; transition: all 0.18s cubic-bezier(0.16,1,0.3,1);">
        <i data-lucide="user" style="width: 20px; height: 20px; stroke-width: 2.2; flex-shrink: 0;"></i>
        <span style="font-size: 10px; font-weight: 600; letter-spacing: -0.01em; white-space: nowrap;">Sign In</span>
      </button>
    <?php endif; ?>

  </nav>
  
  <?php include __DIR__ . '/auth-modal.php'; ?>
  
  <!-- Contact Us Developer Modal -->
  <div id="contact-modal" style="display: none; position: fixed; z-index: 100; inset: 0; align-items: center; justify-content: center; padding: 20px;">
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

      <p style="margin: 0; font-size: 14px; font-weight: 600; color: #475569; line-height: 1.8;">
        Developed by<br/>
        <span style="color: #00bfa5;">Manish Kumar</span><br/>
        <span style="color: #00bfa5;">Manikanta CH</span><br/>
        <span style="color: #00bfa5;">Madan</span><br/>
        <span style="color: #00bfa5;">Aditya Thunga K</span><br/>
        <span style="color: #00bfa5;">Prathvish Kumar</span>
      </p>
      
      <div style="margin: 20px auto; width: 40px; height: 2px; background-color: #e2e8f0; border-radius: 2px;"></div>

      <p style="margin: 0; font-size: 13px; font-weight: 500; color: #64748b;">
        Under the Guidance of<br/>
        <a href="https://sode-edu.in/smvitm/about-smvitm/principal/" target="_blank" style="color: #334155; font-size: 14px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; border-bottom: 1.5px solid #334155; padding-bottom: 1px; margin-top: 4px;">
          Dr. Nagaraj Bhat <i data-lucide="arrow-up-right" style="width: 14px; height: 14px;"></i>
        </a>
      </p>
    </div>
  </div>

</div>



<?php if (isset($_GET['require_login']) && $_GET['require_login'] === 'true'): ?>
<script>
  document.addEventListener("DOMContentLoaded", () => {
      setTimeout(() => {
          if (typeof openAuthModal === 'function') {
              openAuthModal();
              
              const errBlock = document.getElementById('auth-error-msg');
              const errText = document.getElementById('auth-error-text');
              if (errBlock && errText) {
                  errText.innerText = "Please sign in to access this page.";
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

