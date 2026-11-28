/**
 * Smart WeatherNet - Silky Smooth Client-Side Page Router
 * Delivers zero-flicker SPA transitions, top progress bar,
 * prefetching on hover, and smooth slide/fade animations.
 */

(function () {
    let currentPage = new URLSearchParams(window.location.search).get('page') || 'overview';
    let activeNavController = null;
    const pageCache = new Map();
    const inFlightPrefetches = new Map();
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
        try {
            if (sessionStorage.getItem('deliberate_logout') === 'true' || localStorage.getItem('deliberate_logout') === 'true') {
                return false;
            }
            if (window.__currentUser && window.__currentUser.is_logged_in) return true;
            return localStorage.getItem('auth_logged_in') === 'true' && Boolean(localStorage.getItem('auth_email'));
        } catch (e) {
            return Boolean(window.__currentUser && window.__currentUser.is_logged_in);
        }
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

    // Prefetch page data on hover/touch
    function prefetchPage(url, force = false) {
        try {
            const urlObj = new URL(url, window.location.origin);
            const targetPage = urlObj.searchParams.get('page') || 'overview';
            const activeNode = urlObj.searchParams.get('node') || (window.getActiveNode ? window.getActiveNode() : (window.__selectedNode || '1'));
            const currentNode = String(window.__selectedNode || (window.getActiveNode ? window.getActiveNode() : '1'));
            // Do not prefetch across different stations
            if (String(activeNode) !== currentNode) return null;
            const targetTab = urlObj.searchParams.get('tab') || '';
            const cacheKey = `${targetPage}_n${activeNode}${targetTab ? '_t' + targetTab : ''}`;
            const isUserLoggedIn = isUserAuthenticated();

            // Skip prefetching protected pages for unauthenticated visitors
            if (PROTECTED_PAGES.includes(targetPage) && !isUserLoggedIn) return null;
            if (!force && targetPage === currentPage && pageCache.has(cacheKey)) return null;
            const existing = pageCache.get(cacheKey);
            if (!force && existing && (Date.now() - existing.time < 30000)) return null;
            if (inFlightPrefetches.has(cacheKey)) return inFlightPrefetches.get(cacheKey);

            urlObj.searchParams.set('page', targetPage);
            urlObj.searchParams.set('node', activeNode);
            const fetchQuery = '/?' + urlObj.searchParams.toString();
            const pfController = new AbortController();
            const pfTimeout = setTimeout(() => { try { pfController.abort(); } catch (e) {} }, 8000);
            const p = fetch(fetchQuery, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                signal: pfController.signal
            })
                .then(res => res.ok ? res.json() : null)
                .then(data => {
                    if (data && data.success) {
                        pageCache.set(cacheKey, { data, time: Date.now() });
                    }
                    return data;
                })
                .catch(() => null)
                .finally(() => {
                    clearTimeout(pfTimeout);
                    inFlightPrefetches.delete(cacheKey);
                });
            inFlightPrefetches.set(cacheKey, p);
            return p;
        } catch (e) { return null; }
    }

    // Avoid firing eager heavy SSR requests on load (prevents saturating Vercel serverless lambdas)
    const CORE_PAGES = ['overview', 'live', 'historical', 'predictive'];
    async function initPrecache() {
        return;
    }

    // Update Header Navigation elements (and optionally background) to match target page
    function updateHeaderUI(targetPage, applyBackground = true) {
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

        if (applyBackground) {
            // Update Page Background dynamically: Live AQI matches the current AQI bar theme
            const curAqi = (window.currentAqiValue != null && !isNaN(window.currentAqiValue)) ? window.currentAqiValue : 38;
            const aqiTheme = window.currentAQITheme || (typeof window.getAQITheme === 'function' ? window.getAQITheme(curAqi) : null);
            const livePageBg = aqiTheme ? `background-color: ${aqiTheme.pageColor}; background-image: ${aqiTheme.pageBg};` : 'background-color: #dcfce7; background-image: radial-gradient(ellipse 120% 70% at 50% -10%, #86efac 0%, #bbf7d0 35%, #dcfce7 65%, #bbf7d0 100%);';

            const pageBgStyles = {
                'live': livePageBg,
                'weather': 'background-color: #f0fdf4; background-image: radial-gradient(ellipse 120% 70% at 50% -10%, #dcfce7 0%, #e0f2fe 35%, #f0fdfa 65%, #f8fafc 100%);',
                'historical': 'background-color: #f0f4fa; background-image: radial-gradient(ellipse 120% 70% at 50% -10%, #e0e7ff 0%, #e0f2fe 35%, #f0f4f8 65%, #f8fafc 100%);',
                'predictive': 'background-color: #f0f9ff; background-image: radial-gradient(ellipse 120% 70% at 50% -10%, #bae6fd 0%, #e0f2fe 30%, #e0f7f6 60%, #f8fafc 100%);',
                'overview': 'background-color: #f1f5f9; background-image: radial-gradient(ellipse 120% 80% at 50% -20%, #e2e8f0 0%, #f1f5f9 65%, #e2e8f0 100%);'
            };
            const appRoot = document.getElementById('app-layout-root');
            if (appRoot && pageBgStyles[targetPage]) {
                if (targetPage === 'live' && (document.body.classList.contains('weather-backdrop-active') || document.body.classList.contains('soil-backdrop-active'))) {
                    appRoot.style.setProperty('background', 'transparent', 'important');
                    appRoot.style.setProperty('background-image', 'none', 'important');
                    document.body.style.backgroundColor = '';
                } else {
                    appRoot.style.cssText = `min-height: 100vh; ${pageBgStyles[targetPage]} position: relative; width: 100%; display: flex; flex-direction: column; transition: background 0.15s ease, background-color 0.15s ease;`;
                    if (targetPage === 'live' && aqiTheme && aqiTheme.pageColor) {
                        document.body.style.backgroundColor = aqiTheme.pageColor;
                    } else {
                        document.body.style.backgroundColor = '';
                    }
                }
            }
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
                el.classList.add('active');
                el.style.opacity = '1';
                el.style.color = activeColor;
                el.style.backgroundColor = activeBg;
                if (icon) {
                    icon.style.color = activeColor;
                    icon.style.stroke = activeColor;
                    icon.style.opacity = '1';
                    icon.style.transform = 'scale(1.08)';
                    icon.style.strokeWidth = '2.3';
                }
                if (label) {
                    label.style.color = activeColor;
                    label.style.fontWeight = '700';
                    label.style.opacity = '1';
                }
            } else if (isLocked) {
                el.classList.remove('active');
                el.style.opacity = '0.88';
                el.style.color = '#475569';
                el.style.backgroundColor = 'transparent';
                if (icon) {
                    icon.style.color = '#475569';
                    icon.style.stroke = '#475569';
                    icon.style.opacity = '1';
                    icon.style.transform = 'scale(1)';
                    icon.style.strokeWidth = '2';
                }
                if (label) {
                    label.style.color = '#475569';
                    label.style.fontWeight = '700';
                    label.style.opacity = '1';
                }
            } else {
                // Unlocked inactive tab — bold crisp dark slate #0f172a
                el.classList.remove('active');
                el.style.opacity = '1';
                el.style.color = '#0f172a';
                el.style.backgroundColor = 'transparent';
                if (icon) {
                    icon.style.color = '#0f172a';
                    icon.style.stroke = '#0f172a';
                    icon.style.opacity = '1';
                    icon.style.transform = 'scale(1)';
                    icon.style.strokeWidth = '2.2';
                }
                if (label) {
                    label.style.color = '#0f172a';
                    label.style.fontWeight = '700';
                    label.style.opacity = '1';
                }
            }
        });
    }

    // Main Navigation Function
    async function navigateToPage(url, pushState = true) {
        let targetPage = 'overview';
        let targetNode = String(window.__selectedNode || (window.getActiveNode ? window.getActiveNode() : '1'));
        let fetchQuery = url;
        let targetTab = '';
        try {
            const urlObj = new URL(url, window.location.origin);
            targetPage = urlObj.searchParams.get('page') || 'overview';
            if (targetPage === 'weather') targetPage = 'live';
            if (urlObj.searchParams.has('node')) {
                const rawNode = urlObj.searchParams.get('node');
                if (rawNode === '1' || rawNode === '2') targetNode = rawNode;
            }
            urlObj.searchParams.set('page', targetPage);
            urlObj.searchParams.set('node', targetNode);
            targetTab = urlObj.searchParams.get('tab') || '';
            fetchQuery = '/?' + urlObj.searchParams.toString();
        } catch (e) {
            targetPage = 'overview';
        }

        const currentNode = String(window.__selectedNode || '1');
        // If switching stations via link, perform a full station reload so header, SUPABASE_CONFIG, and footer sync completely
        if (String(targetNode) !== currentNode) {
            if (typeof window.switchActiveNode === 'function') {
                window.switchActiveNode(targetNode);
            } else {
                window.location.assign(fetchQuery);
            }
            return;
        }

        const cacheKey = `${targetPage}_n${targetNode}${targetTab ? '_t' + targetTab : ''}`;
        const nodeSuffix = (targetNode === '2') ? '&node=2' : '&node=1';

        if (targetPage === currentPage && !pushState && !targetTab) {
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

        // Cancel any previous in-flight navigation instead of dropping the user's tap
        if (activeNavController) {
            try { activeNavController.abort(); } catch (e) {}
            activeNavController = null;
        }
        const controller = new AbortController();
        activeNavController = controller;

        // Instant 0ms tactile feedback on nav pills only (keep current page background until new DOM swaps in)
        updateHeaderUI(targetPage, false);

        // Close mobile dropdown if open
        if (typeof toggleMobileMenu === 'function') {
            toggleMobileMenu(false);
        }

        const wrapper = document.getElementById('page-content-wrapper');
        const cached = pageCache.get(cacheKey);
        const hasFreshCache = Boolean(cached && (Date.now() - cached.time < 300000));

        if (!hasFreshCache) {
            setProgressBar(35, 1);
            if (wrapper) {
                wrapper.style.transition = 'opacity 0.12s ease';
                wrapper.style.opacity = '0.72';
            }
        } else if (Date.now() - cached.time > 20000) {
            // Stale-while-revalidate in background
            prefetchPage(fetchQuery, true);
        }

        let timedOut = false;
        const timeoutId = setTimeout(() => {
            timedOut = true;
            try { controller.abort(); } catch (e) {}
        }, 15000);

        try {
            let data = hasFreshCache ? cached.data : null;
            if (!data) {
                setProgressBar(65, 1);
                if (inFlightPrefetches.has(cacheKey)) {
                    data = await inFlightPrefetches.get(cacheKey);
                }
                if (!data) {
                    const res = await fetch(fetchQuery, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        signal: controller.signal
                    });
                    data = await res.json();
                    if (data && data.success) {
                        pageCache.set(cacheKey, { data, time: Date.now() });
                    }
                }
            }

            clearTimeout(timeoutId);

            if (controller.signal.aborted || activeNavController !== controller) {
                return;
            }

            if (!data) {
                window.location.href = fetchQuery;
                return;
            }

            // Handle auth requirement from server response
            if (data.require_login) {
                resetProgressBar();
                updateHeaderUI(currentPage, false);
                if (wrapper) {
                    wrapper.style.opacity = '1';
                }
                window.__pendingTargetPage = data.target_page || targetPage;
                if (typeof openAuthModal === 'function') {
                    openAuthModal(true);
                    showAuthPrompt(data.target_page || targetPage);
                }
                return;
            }

            if (!data.success) {
                window.location.href = fetchQuery;
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
            document.body.classList.remove('soil-backdrop-active');

            // Instant content swap (no artificial delays)
            // Push to history
            if (pushState) {
                history.pushState({ page: targetPage, node: targetNode }, '', fetchQuery);
            }
            currentPage = targetPage;
            window.__currentPage = targetPage;
            window.__selectedNode = String(targetNode);
            if (typeof window.syncNavLinksNode === 'function') {
                window.syncNavLinksNode(targetNode);
            }
            const footerStation = document.getElementById('footer-station-name');
            const footerBullet = document.getElementById('footer-station-bullet');
            if (footerStation) {
                if (String(targetNode) === '2') {
                    footerStation.style.display = 'none';
                    if (footerBullet) footerBullet.style.display = 'none';
                } else {
                    footerStation.innerText = 'SMVITM Campus';
                    footerStation.style.display = 'inline';
                    if (footerBullet) footerBullet.style.display = 'inline';
                }
            }
            try {
                localStorage.setItem('smartairnet_selected_node', String(targetNode));
                document.cookie = "smartairnet_selected_node=" + targetNode + "; path=/; max-age=" + (365 * 86400) + "; SameSite=Lax";
            } catch(e) {}
            if (data.title) {
                document.title = data.title;
            }

            // Re-verify nav pills (delay background swap until new DOM + scripts are inserted)
            updateHeaderUI(targetPage, false);

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
                    const nodeSuffix = (String(targetNode) === '2') ? '_n2' : '';
                    if (window.__apiCache && window.__apiCache.liveBulk && window.__apiCache.liveBulk.data && String(window.__apiCache.liveBulk.node) === String(targetNode)) {
                        preData = window.__apiCache.liveBulk.data;
                    } else if (window.__apiCache && window.__apiCache.overviewBulk && window.__apiCache.overviewBulk.data && String(window.__apiCache.overviewBulk.node) === String(targetNode)) {
                        preData = window.__apiCache.overviewBulk.data;
                    } else if (typeof window.getLocalCache === 'function') {
                        preData = window.getLocalCache((window.STORAGE_KEYS ? window.STORAGE_KEYS.BULK : 'smartairnet_bulk_telemetry') + nodeSuffix);
                    }
                    if (preData) {
                        if (!preData.history && preData.aqi_history) preData.history = preData.aqi_history;
                        if (!preData.aqi_history && preData.history) preData.aqi_history = preData.history;
                        window.INITIAL_DATA = preData;
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

                // Apply page background cleanly in the same frame after DOM & scripts are ready
                updateHeaderUI(targetPage, true);

                // Scroll to top instantly
                window.scrollTo({ top: 0, behavior: 'instant' });

                // Render Lucide icons
                if (typeof lucide !== 'undefined' && lucide.createIcons) {
                    lucide.createIcons();
                }

                wrapper.style.opacity = '1';
                wrapper.style.transform = 'translateY(0)';
                wrapper.classList.remove('page-fade-leave');
                wrapper.classList.add('page-fade-active');
            }

            setProgressBar(100, 1);
            resetProgressBar();

        } catch (err) {
            clearTimeout(timeoutId);
            if (err && err.name === 'AbortError' && !timedOut) {
                return;
            }
            console.error('Smooth navigation error, falling back to standard load:', err);
            window.location.href = url;
        } finally {
            clearTimeout(timeoutId);
            if (activeNavController === controller) {
                activeNavController = null;
            }
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
