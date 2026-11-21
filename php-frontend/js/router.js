/**
 * Smart WeatherNet - Silky Smooth Client-Side Page Router
 * Delivers zero-flicker SPA transitions, top progress bar,
 * prefetching on hover, and smooth slide/fade animations.
 */

(function () {
    let currentPage = new URLSearchParams(window.location.search).get('page') || 'overview';
    let isTransitioning = false;
    const pageCache = new Map();
    let prefetchTimeout = null;

    // Create & inject top glowing progress bar
    const progressBar = document.createElement('div');
    progressBar.id = 'page-progress-bar';
    document.body.appendChild(progressBar);

    function setProgressBar(pct, opacity = 1) {
        if (!progressBar) return;
        progressBar.style.opacity = String(opacity);
        progressBar.style.width = pct + '%';
    }

    function resetProgressBar() {
        if (!progressBar) return;
        progressBar.style.opacity = '0';
        setTimeout(() => {
            progressBar.style.width = '0%';
        }, 280);
    }

    const PROTECTED_PAGES = ['live', 'historical', 'predictive', 'weather'];

    function isUserAuthenticated() {
        return Boolean(window.__currentUser && window.__currentUser.is_logged_in);
    }


    function showAuthPrompt(targetPg) {
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
            errText.innerText = `Please sign in to view ${label}.`;
            errBlock.style.backgroundColor = "rgba(0, 191, 165, 0.1)";
            errBlock.style.color = "#00bfa5";
            errBlock.style.border = "1px solid rgba(0, 191, 165, 0.2)";
            errBlock.style.display = 'flex';
        }
    }

    // Prefetch page data on hover/touch or eager pre-cache
    function prefetchPage(url) {
        try {
            const urlObj = new URL(url, window.location.origin);
            const targetPage = urlObj.searchParams.get('page') || 'overview';
            const activeNode = urlObj.searchParams.get('node') || (window.getActiveNode ? window.getActiveNode() : (window.__selectedNode || '1'));
            const cacheKey = `${targetPage}_n${activeNode}`;
            const isUserLoggedIn = isUserAuthenticated();

            // Skip prefetching protected pages for unauthenticated visitors
            if (PROTECTED_PAGES.includes(targetPage) && !isUserLoggedIn) return;
            if (targetPage === currentPage || pageCache.has(cacheKey)) return;

            const nodeQuery = (activeNode === '2') ? '&node=2' : '';
            // No cache-buster — allow HTTP cache to work for page HTML
            fetch(`?page=${encodeURIComponent(targetPage)}${nodeQuery}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(res => res.json())
                .then(data => {
                    if (data && data.success) {
                        pageCache.set(cacheKey, { data, time: Date.now() });
                    }
                })
                .catch(() => { });
        } catch (e) { }
    }

    // Eagerly pre-cache all main pages in background so page switching is 0ms instant
    const CORE_PAGES = ['overview', 'live', 'weather', 'historical', 'predictive'];
    if (document.readyState === 'complete') {
        setTimeout(initPrecache, 100);
    } else {
        window.addEventListener('load', () => setTimeout(initPrecache, 150));
    }
    function initPrecache() {
        const isUserLoggedIn = isUserAuthenticated();
        const activeNode = (window.getActiveNode ? window.getActiveNode() : (window.__selectedNode || '1'));
        const nodeSuffix = (activeNode === '2') ? '&node=2' : '';
        CORE_PAGES.forEach((pg, idx) => {
            if (PROTECTED_PAGES.includes(pg) && !isUserLoggedIn) return;
            const cacheKey = `${pg}_n${activeNode}`;
            if (pg !== currentPage && !pageCache.has(cacheKey)) {
                // Reduced stagger to 30ms (was 80ms) for faster pre-population
                setTimeout(() => prefetchPage(`?page=${pg}${nodeSuffix}`), idx * 30);
            }
        });
    }

    // Update Header Navigation elements to match target page
    function updateHeaderUI(targetPage) {
        const PAGE_TITLES = {
            overview: 'Atmosphere Snapshot',
            live: 'Live Data Stream',
            historical: 'Analytics Archive',
            predictive: 'Forecast & Advisory',
            weather: 'Weather Conditions'
        };

        // Mobile Top App Bar (Always keep brand visible across all pages)
        const mobileBrand = document.getElementById('mobile-brand-overview');
        const mobileSecondary = document.getElementById('mobile-secondary-header');

        if (mobileBrand) {
            mobileBrand.style.display = 'flex';
        }
        if (mobileSecondary) {
            mobileSecondary.style.display = 'none';
        }

        // Update Cool & Light Page Background
        const pageBgStyles = {
            'live': 'background-color: #f0f7ff; background-image: radial-gradient(ellipse 120% 70% at 50% -10%, #dbeafe 0%, #e0f2fe 35%, #f0fdfa 65%, #f8fafc 100%);',
            'weather': 'background-color: #f0fdf4; background-image: radial-gradient(ellipse 120% 70% at 50% -10%, #dcfce7 0%, #e0f2fe 35%, #f0fdfa 65%, #f8fafc 100%);',
            'historical': 'background-color: #f0f4fa; background-image: radial-gradient(ellipse 120% 70% at 50% -10%, #e0e7ff 0%, #e0f2fe 35%, #f0f4f8 65%, #f8fafc 100%);',
            'predictive': 'background-color: #f0f9ff; background-image: radial-gradient(ellipse 120% 70% at 50% -10%, #bae6fd 0%, #e0f2fe 30%, #e0f7f6 60%, #f8fafc 100%);',
            'overview': 'background-color: #f1f5f9; background-image: radial-gradient(ellipse 120% 80% at 50% -20%, #e2e8f0 0%, #f1f5f9 65%, #e2e8f0 100%);'
        };
        const appRoot = document.getElementById('app-layout-root');
        if (appRoot && pageBgStyles[targetPage]) {
            appRoot.style.cssText = `min-height: 100vh; ${pageBgStyles[targetPage]} position: relative; width: 100%; display: flex; flex-direction: column; transition: background 0.4s ease;`;
        }

        // Back Button
        const backBtn = document.getElementById('header-back-btn');
        if (backBtn) {
            backBtn.style.display = 'none';
        }

        // View Dashboard CTA Button
        const dashBtn = document.getElementById('header-dashboard-btn');
        if (dashBtn) {
            dashBtn.className = 'hidden';
        }

        // Desktop Nav container (Always visible on desktop screens)
        const desktopNav = document.getElementById('desktop-nav');
        if (desktopNav) {
            desktopNav.style.removeProperty('display');
            desktopNav.className = 'hidden md:inline-flex';
        }

        // Mobile live dot (hidden in desktop view)
        const mobileLiveDot = document.getElementById('mobile-live-dot');
        if (mobileLiveDot) {
            mobileLiveDot.style.display = 'none';
        }

        // Update Desktop Nav items
        document.querySelectorAll('[data-nav-link]').forEach(el => {
            const pageKey = el.getAttribute('data-nav-link');
            const isActive = pageKey === targetPage;
            el.style.backgroundColor = isActive ? '#00bfa5' : 'transparent';
            el.style.color = isActive ? '#ffffff' : '#64748b';
            el.style.boxShadow = isActive ? '0 4px 14px rgba(0, 191, 165, 0.35)' : 'none';
            const icon = el.querySelector('i');
            if (icon) {
                icon.style.opacity = isActive ? '1' : '0.85';
            }
        });

        // Update Mobile Dropdown Nav items
        document.querySelectorAll('[data-mobile-nav-link]').forEach(el => {
            const pageKey = el.getAttribute('data-mobile-nav-link');
            const isActive = pageKey === targetPage;
            el.style.backgroundColor = isActive ? '#00bfa5' : '#ffffff';
            el.style.color = isActive ? '#ffffff' : '#475569';
        });

        // Update Mobile Bottom Nav Dock items
        const isUserLoggedIn = isUserAuthenticated();

        document.querySelectorAll('[data-bottom-nav]').forEach(el => {
            const pageKey = el.getAttribute('data-bottom-nav');
            const isActive = pageKey === targetPage;
            const activeColor = '#00bfa5';
            const activeBg = 'rgba(0, 191, 165, 0.1)';
            const icon = el.querySelector('i') || el.querySelector('svg');
            const label = el.querySelector('span');
            const liveDot = el.querySelector('.campus-status-dot');

            const isLocked = !isUserLoggedIn && pageKey !== 'overview';
            if (isLocked) {
                el.classList.add('bottom-nav-locked');
            } else {
                el.classList.remove('bottom-nav-locked');
            }
            el.removeAttribute('aria-disabled');
            el.style.cursor = 'pointer';

            if (liveDot) {
                liveDot.style.display = isLocked ? 'none' : '';
            }

            if (isActive) {
                el.style.opacity = '1';
                el.style.color = activeColor;
                el.style.backgroundColor = activeBg;
                if (icon) {
                    icon.style.color = activeColor;
                    icon.style.opacity = '1';
                    icon.style.transform = 'scale(1.1)';
                    icon.style.strokeWidth = '2.2';
                }
                if (label) {
                    label.style.color = activeColor;
                    label.style.fontWeight = '700';
                }
            } else {
                el.style.opacity = isLocked ? '0.38' : '0.78';
                el.style.color = '#94a3b8';
                el.style.backgroundColor = 'transparent';
                if (icon) {
                    icon.style.color = '#94a3b8';
                    icon.style.opacity = isLocked ? '0.75' : '0.85';
                    icon.style.transform = 'scale(1)';
                    icon.style.strokeWidth = '1.8';
                }
                if (label) {
                    label.style.color = '#94a3b8';
                    label.style.fontWeight = '500';
                }
            }
        });
    }

    // Main Navigation Function
    async function navigateToPage(url, pushState = true) {
        let targetPage = 'overview';
        let targetNode = (window.getActiveNode ? window.getActiveNode() : (window.__selectedNode || '1'));
        try {
            const urlObj = new URL(url, window.location.origin);
            targetPage = urlObj.searchParams.get('page') || 'overview';
            if (targetPage === 'weather') targetPage = 'live';
            if (urlObj.searchParams.has('node')) {
                targetNode = urlObj.searchParams.get('node');
            }
        } catch (e) {
            targetPage = 'overview';
        }

        const cacheKey = `${targetPage}_n${targetNode}`;
        const nodeSuffix = (targetNode === '2') ? '&node=2' : '';

        if (targetPage === currentPage && !pushState) {
            return;
        }

        const isUserLoggedIn = isUserAuthenticated();

        // Check authentication requirement before making any network calls or page transitions
        if (PROTECTED_PAGES.includes(targetPage)) {
            if (!isUserLoggedIn) {
                window.__pendingTargetPage = targetPage;
                if (typeof openAuthModal === 'function') {
                    openAuthModal(true);
                    showAuthPrompt(targetPage);
                }
                return;
            }
        }

        if (isTransitioning) return;
        isTransitioning = true;

        // Instant 0ms tactile feedback on tabs/dock before network begins
        updateHeaderUI(targetPage);

        // Progress bar start
        setProgressBar(35, 1);

        // Close mobile dropdown if open
        if (typeof toggleMobileMenu === 'function') {
            toggleMobileMenu(false);
        }

        // Non-destructive transition: keep existing content visible while loading to eliminate blank flash
        const wrapper = document.getElementById('page-content-wrapper');
        if (wrapper) {
            wrapper.style.transition = 'opacity 0.12s ease';
            wrapper.style.opacity = '0.72';
        }

        try {
            // Check prefetch cache or fetch from server (120s TTL — instant SPA switching for 2 min)
            let cached = pageCache.get(cacheKey);
            let data = (cached && (Date.now() - cached.time < 120000)) ? cached.data : null;
            if (!data) {
                setProgressBar(65, 1);
                // No cache-buster — allow HTTP cache to accelerate repeated loads
                const res = await fetch(`?page=${encodeURIComponent(targetPage)}${nodeSuffix}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                data = await res.json();
                pageCache.set(cacheKey, { data, time: Date.now() });
            }

            // Handle auth requirement from server response
            if (data.require_login) {
                resetProgressBar();
                if (wrapper) {
                    wrapper.style.opacity = '1';
                }
                isTransitioning = false;
                window.__pendingTargetPage = data.target_page || targetPage;
                if (typeof openAuthModal === 'function') {
                    openAuthModal(true);
                    showAuthPrompt(data.target_page || targetPage);
                }
                return;
            }

            if (!data.success) {
                window.location.href = url;
                return;
            }

            // Cleanup previous page handlers right before swapping content
            if (typeof window.__cleanupCurrentPage === 'function') {
                try {
                    window.__cleanupCurrentPage();
                } catch (err) {
                    console.warn('Page cleanup error:', err);
                }
                window.__cleanupCurrentPage = null;
            }
            document.body.classList.remove('weather-backdrop-active');

            // Instant content swap (no artificial delays)
            // Push to history
            if (pushState) {
                history.pushState({ page: targetPage, node: targetNode }, '', `?page=${targetPage}${nodeSuffix}`);
            }
            currentPage = targetPage;
            window.__selectedNode = String(targetNode);
            try {
                localStorage.setItem('smartairnet_selected_node', String(targetNode));
                document.cookie = "smartairnet_selected_node=" + targetNode + "; path=/; max-age=" + (365 * 86400) + "; SameSite=Lax";
            } catch(e) {}
            if (data.title) {
                document.title = data.title;
            }

            // Re-verify UI headers & nav pills
            updateHeaderUI(targetPage);

            // Swap content
            if (wrapper) {
                // Destroy any lingering Chart.js instances before swapping DOM
                if (window.__chartInstances) {
                    Object.keys(window.__chartInstances).forEach(k => {
                        try {
                            if (window.__chartInstances[k] && typeof window.__chartInstances[k].destroy === 'function') {
                                window.__chartInstances[k].destroy();
                            }
                        } catch(e) {}
                    });
                    window.__chartInstances = {};
                }

                // Pre-hydrate INITIAL_DATA from JS API cache so page scripts skip their first fetch
                // This eliminates the double-fetch (page HTML fetch + telemetry fetch) on SPA navigation
                try {
                    let preData = null;
                    if (targetNode === '2') {
                        preData = null;
                        window.INITIAL_DATA = null;
                    } else {
                        if (targetPage === 'overview' && window.__apiCache && window.__apiCache.overviewBulk && window.__apiCache.overviewBulk.data) {
                            preData = window.__apiCache.overviewBulk.data;
                        } else if (window.__apiCache && window.__apiCache.liveBulk && window.__apiCache.liveBulk.data) {
                            preData = window.__apiCache.liveBulk.data;
                        } else if (window.__apiCache && window.__apiCache.overviewBulk && window.__apiCache.overviewBulk.data) {
                            preData = window.__apiCache.overviewBulk.data;
                        } else if (window.__apiCache && window.__apiCache.aqiLive && window.__apiCache.aqiLive.data) {
                            preData = window.__apiCache.aqiLive.data;
                        }
                        if (!preData && typeof window.getLocalCache === 'function') {
                            preData = window.getLocalCache(window.STORAGE_KEYS ? window.STORAGE_KEYS.BULK : 'smartairnet_bulk_telemetry');
                        }
                        if (preData) {
                            // Ensure both history and aqi_history keys exist
                            if (!preData.history && preData.aqi_history) preData.history = preData.aqi_history;
                            if (!preData.aqi_history && preData.history) preData.aqi_history = preData.history;
                            window.INITIAL_DATA = preData;
                        }
                    }
                } catch (e) { }

                wrapper.innerHTML = data.content;

                // Re-execute scripts inside swapped content safely
                const scripts = wrapper.querySelectorAll('script');
                scripts.forEach(oldScript => {
                    try {
                        const newScript = document.createElement('script');
                        Array.from(oldScript.attributes).forEach(attr => {
                            newScript.setAttribute(attr.name, attr.value);
                        });
                        newScript.textContent = oldScript.textContent;
                        oldScript.parentNode.replaceChild(newScript, oldScript);
                    } catch (err) {
                        console.warn("Script execution warning:", err);
                    }
                });

                // Scroll to top instantly
                window.scrollTo({ top: 0, behavior: 'instant' });

                // Render Lucide icons
                if (typeof lucide !== 'undefined' && lucide.createIcons) {
                    lucide.createIcons();
                }

                wrapper.style.opacity = '1';
                wrapper.classList.remove('page-fade-leave');
                wrapper.classList.add('page-fade-active');
            }

            setProgressBar(100, 1);
            resetProgressBar();

        } catch (err) {
            console.error('Smooth navigation error, falling back to standard load:', err);
            window.location.href = url;
        } finally {
            isTransitioning = false;
        }
    }

    // Intercept clicks on links with ?page=
    document.addEventListener('click', (e) => {
        const link = e.target.closest('a');
        if (!link) return;
        const href = link.getAttribute('href');
        if (!href) return;

        // Skip non-page links, external links, auth or logout
        if (href.includes('logout=true') || href.includes('auth=true') || href.startsWith('mailto:') || href.startsWith('tel:') || link.target === '_blank') {
            return;
        }

        if (href.startsWith('?page=') || href.startsWith('./?page=') || (href.startsWith('/') && href.includes('?page='))) {
            if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
            e.preventDefault();
            navigateToPage(href);
        }
    });

    // Hover / touchstart prefetching for instantaneous transitions
    document.addEventListener('mouseover', (e) => {
        const link = e.target.closest('a[href*="?page="]');
        if (link) {
            clearTimeout(prefetchTimeout);
            prefetchTimeout = setTimeout(() => {
                prefetchPage(link.getAttribute('href'));
            }, 50);
        }
    });

    document.addEventListener('pointerdown', (e) => {
        const link = e.target.closest('a[href*="?page="]');
        if (link) {
            prefetchPage(link.getAttribute('href'));
        }
    }, { passive: true });

    document.addEventListener('touchstart', (e) => {
        const link = e.target.closest('a[href*="?page="]');
        if (link) {
            prefetchPage(link.getAttribute('href'));
        }
    }, { passive: true });

    // Handle browser Back / Forward buttons
    window.addEventListener('popstate', (e) => {
        const params = new URLSearchParams(window.location.search);
        const page = (e.state && e.state.page) || params.get('page') || 'overview';
        const node = (e.state && e.state.node) || params.get('node') || (window.getActiveNode ? window.getActiveNode() : '1');
        const nodeSuffix = (node === '2') ? '&node=2' : '';
        navigateToPage(window.location.search || `?page=${page}${nodeSuffix}`, false);
    });

    // Initial setup on page load
    window.addEventListener('DOMContentLoaded', () => {
        updateHeaderUI(currentPage);
        const wrapper = document.getElementById('page-content-wrapper');
        if (wrapper) {
            wrapper.classList.add('page-fade-active');
        }
    });

    // Expose router to window for programmatic navigation
    window.navigateToPage = navigateToPage;
    window.initRouterPrecache = initPrecache;
})();
