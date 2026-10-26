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

    const PROTECTED_PAGES = ['live', 'historical', 'predictive', 'weather', 'admin'];

    // Prefetch page data on hover/touch or eager pre-cache
    function prefetchPage(url) {
        try {
            const urlObj = new URL(url, window.location.origin);
            const targetPage = urlObj.searchParams.get('page') || 'overview';
            const isUserLoggedIn = Boolean(window.__currentUser && window.__currentUser.is_logged_in);

            // Skip prefetching protected pages for unauthenticated visitors
            if (PROTECTED_PAGES.includes(targetPage) && !isUserLoggedIn) return;
            if (targetPage === currentPage || pageCache.has(targetPage)) return;

            fetch(`?page=${encodeURIComponent(targetPage)}&_t=${Date.now()}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data && data.success) {
                    pageCache.set(targetPage, { data, time: Date.now() });
                }
            })
            .catch(() => {});
        } catch (e) {}
    }

    // Eagerly pre-cache all main pages in background so page switching is 0ms instant
    const CORE_PAGES = ['overview', 'live', 'historical', 'predictive', 'admin'];
    if (document.readyState === 'complete') {
        setTimeout(initPrecache, 100);
    } else {
        window.addEventListener('load', () => setTimeout(initPrecache, 150));
    }
    function initPrecache() {
        const isUserLoggedIn = Boolean(window.__currentUser && window.__currentUser.is_logged_in);
        CORE_PAGES.forEach((pg, idx) => {
            if (PROTECTED_PAGES.includes(pg) && !isUserLoggedIn) return;
            if (pg !== currentPage && !pageCache.has(pg)) {
                setTimeout(() => prefetchPage(`?page=${pg}`), idx * 80);
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
            admin: 'Admin Center',
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
            if (pageKey === 'admin') {
                el.style.backgroundColor = isActive ? '#6366f1' : 'rgba(99, 102, 241, 0.08)';
                el.style.color = isActive ? '#ffffff' : '#6366f1';
                el.style.boxShadow = isActive ? '0 4px 14px rgba(99, 102, 241, 0.35)' : 'none';
            } else {
                el.style.backgroundColor = isActive ? '#00bfa5' : 'transparent';
                el.style.color = isActive ? '#ffffff' : '#64748b';
                el.style.boxShadow = isActive ? '0 4px 14px rgba(0, 191, 165, 0.35)' : 'none';
            }
            const icon = el.querySelector('i');
            if (icon) {
                icon.style.opacity = isActive ? '1' : '0.85';
                if (pageKey === 'admin') icon.style.color = isActive ? '#ffffff' : '#6366f1';
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
        document.querySelectorAll('[data-bottom-nav]').forEach(el => {
            const pageKey = el.getAttribute('data-bottom-nav');
            const isActive = pageKey === targetPage;
            const activeColor = (pageKey === 'admin') ? '#6366f1' : '#00bfa5';
            const activeBg = (pageKey === 'admin') ? 'rgba(99, 102, 241, 0.12)' : 'rgba(0, 191, 165, 0.08)';
            const icon = el.querySelector('i') || el.querySelector('svg');
            const label = el.querySelector('span');
            if (isActive) {
                el.style.color = activeColor;
                el.style.backgroundColor = activeBg;
                if (icon) {
                    icon.style.color = activeColor;
                    icon.style.transform = 'scale(1.1)';
                }
                if (label) {
                    label.style.color = activeColor;
                    label.style.fontWeight = '700';
                }
            } else {
                el.style.color = '#64748b';
                el.style.backgroundColor = 'transparent';
                if (icon) {
                    icon.style.color = (pageKey === 'admin') ? '#6366f1' : '#64748b';
                    icon.style.transform = 'scale(1)';
                }
                if (label) {
                    label.style.color = '#64748b';
                    label.style.fontWeight = '600';
                }
            }
        });
    }

    // Main Navigation Function
    async function navigateToPage(url, pushState = true) {
        let targetPage = 'overview';
        try {
            const urlObj = new URL(url, window.location.origin);
            targetPage = urlObj.searchParams.get('page') || 'overview';
        } catch(e) {
            targetPage = 'overview';
        }

        if (targetPage === currentPage && !pushState) {
            return;
        }

        // Instant client-side auth check for protected pages (admin handles its own dedicated gate)
        const PROTECTED_PAGES = ['live', 'historical', 'predictive', 'weather'];
        const hasSavedAuth = (() => {
            try {
                return (localStorage.getItem('auth_logged_in') === 'true' && Boolean(localStorage.getItem('auth_email'))) &&
                       !(sessionStorage.getItem('deliberate_logout') === 'true' || localStorage.getItem('deliberate_logout') === 'true');
            } catch(e) { return false; }
        })();
        const isUserLoggedIn = Boolean(window.__currentUser && (window.__currentUser.is_logged_in || window.__currentUser.is_admin)) || hasSavedAuth;

        if (PROTECTED_PAGES.includes(targetPage) && !isUserLoggedIn) {
            window.__pendingTargetPage = targetPage;
            if (typeof openAuthModal === 'function') {
                openAuthModal();
                const errBlock = document.getElementById('auth-error-msg');
                const errText = document.getElementById('auth-error-text');
                if (errBlock && errText) {
                    const pageLabels = {
                        live: 'Live Data Stream',
                        historical: 'Analytics Archive',
                        predictive: 'Weather Forecast',
                        weather: 'Weather Telemetry',
                        admin: 'Admin Center'
                    };
                    const label = pageLabels[targetPage] || 'this page';
                    errText.innerText = `Please sign in to view ${label}.`;
                    errBlock.style.backgroundColor = "rgba(0, 191, 165, 0.1)";
                    errBlock.style.color = "#00bfa5";
                    errBlock.style.border = "1px solid rgba(0, 191, 165, 0.2)";
                    errBlock.style.display = 'flex';
                }
            }
            return;
        }

        if (isTransitioning) return;
        isTransitioning = true;

        // Progress bar start
        setProgressBar(35, 1);

        // Close mobile dropdown if open
        if (typeof toggleMobileMenu === 'function') {
            toggleMobileMenu(false);
        }

        // Smoothly animate out current content
        const wrapper = document.getElementById('page-content-wrapper');
        if (wrapper) {
            wrapper.classList.remove('page-fade-active', 'page-fade-enter');
            wrapper.classList.add('page-fade-leave');
        }

        // Cleanup current page handlers (intervals, charts, animation frames)
        if (typeof window.__cleanupCurrentPage === 'function') {
            try {
                window.__cleanupCurrentPage();
            } catch (err) {
                console.warn('Page cleanup error:', err);
            }
            window.__cleanupCurrentPage = null;
        }

        try {
            // Check prefetch cache or fetch from server (10s TTL)
            let cached = pageCache.get(targetPage);
            let data = (cached && (Date.now() - cached.time < 10000)) ? cached.data : null;
            if (!data) {
                setProgressBar(65, 1);
                const res = await fetch(`?page=${encodeURIComponent(targetPage)}&_t=${Date.now()}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                data = await res.json();
                pageCache.set(targetPage, { data, time: Date.now() });
            }

            // Handle auth requirement from server response
            if (data.require_login) {
                if (hasSavedAuth) {
                    const savedEmail = localStorage.getItem('auth_email');
                    const savedName = localStorage.getItem('auth_name') || (savedEmail ? savedEmail.split('@')[0] : '');
                    if (typeof window.setAuthCookie === 'function') {
                        window.setAuthCookie('guest', savedName, savedEmail, false);
                    }
                    window.location.href = `?page=${encodeURIComponent(targetPage)}&auth=true&name=${encodeURIComponent(savedName)}&email=${encodeURIComponent(savedEmail)}`;
                    return;
                }
                resetProgressBar();
                if (wrapper) {
                    wrapper.classList.remove('page-fade-leave');
                    wrapper.classList.add('page-fade-active');
                }
                isTransitioning = false;
                window.__pendingTargetPage = data.target_page || targetPage;
                if (typeof openAuthModal === 'function') {
                    openAuthModal();
                    const errBlock = document.getElementById('auth-error-msg');
                    const errText = document.getElementById('auth-error-text');
                    if (errBlock && errText) {
                        const pageLabels = {
                            live: 'Live Data Stream',
                            historical: 'Analytics Archive',
                            predictive: 'Weather Forecast',
                            weather: 'Weather Telemetry',
                            admin: 'Admin Center'
                        };
                        const targetPg = data.target_page || targetPage;
                        const label = pageLabels[targetPg] || 'this page';
                        errText.innerText = `Please sign in to view ${label}.`;
                        errBlock.style.backgroundColor = "rgba(0, 191, 165, 0.1)";
                        errBlock.style.color = "#00bfa5";
                        errBlock.style.border = "1px solid rgba(0, 191, 165, 0.2)";
                        errBlock.style.display = 'flex';
                    }
                }
                return;
            }

            if (!data.success) {
                window.location.href = url;
                return;
            }

            // Instant content swap (no artificial delays)
            // Push to history
            if (pushState) {
                history.pushState({ page: targetPage }, '', `?page=${targetPage}`);
            }
            currentPage = targetPage;
            if (data.title) {
                document.title = data.title;
            }

            // Update UI headers & nav pills
            updateHeaderUI(targetPage);

            // Swap content
            if (wrapper) {
                wrapper.classList.remove('page-fade-leave');
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
                    } catch(err) {
                        console.warn("Script execution warning:", err);
                    }
                });

                // Scroll to top instantly
                window.scrollTo({ top: 0, behavior: 'instant' });

                // Render Lucide icons
                if (typeof lucide !== 'undefined' && lucide.createIcons) {
                    lucide.createIcons();
                }

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

    document.addEventListener('touchstart', (e) => {
        const link = e.target.closest('a[href*="?page="]');
        if (link) {
            prefetchPage(link.getAttribute('href'));
        }
    }, { passive: true });

    // Handle browser Back / Forward buttons
    window.addEventListener('popstate', (e) => {
        const page = (e.state && e.state.page) || new URLSearchParams(window.location.search).get('page') || 'overview';
        navigateToPage(`?page=${page}`, false);
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
})();
