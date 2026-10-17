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

    // Prefetch page data on hover/touch or eager pre-cache
    function prefetchPage(url) {
        try {
            const urlObj = new URL(url, window.location.origin);
            const targetPage = urlObj.searchParams.get('page') || 'overview';
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
        CORE_PAGES.forEach((pg, idx) => {
            if (pg !== currentPage && !pageCache.has(pg)) {
                setTimeout(() => prefetchPage(`?page=${pg}`), idx * 80);
            }
        });
    }

    // Update Header Navigation elements to match target page
    function updateHeaderUI(targetPage) {
        // Back Button
        const backBtn = document.getElementById('header-back-btn');
        if (backBtn) {
            backBtn.style.display = targetPage !== 'overview' ? 'flex' : 'none';
        }

        // View Dashboard CTA Button
        const dashBtn = document.getElementById('header-dashboard-btn');
        if (dashBtn) {
            dashBtn.style.display = targetPage === 'overview' ? 'inline-flex' : 'none';
        }

        // Desktop Nav container
        const desktopNav = document.getElementById('desktop-nav');
        if (desktopNav) {
            desktopNav.style.removeProperty('display');
            if (targetPage === 'overview') {
                desktopNav.className = 'hidden';
            } else {
                desktopNav.className = 'inline-flex';
            }
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
            if (icon) icon.style.opacity = isActive ? '1' : '0.75';
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
            const icon = el.querySelector('i') || el.querySelector('svg');
            const label = el.querySelector('span');
            if (isActive) {
                el.style.color = '#00bfa5';
                el.style.backgroundColor = 'rgba(0, 191, 165, 0.08)';
                if (icon) {
                    icon.style.color = '#00bfa5';
                    icon.style.transform = 'scale(1.1)';
                }
                if (label) {
                    label.style.color = '#00bfa5';
                    label.style.fontWeight = '700';
                }
            } else {
                el.style.color = '#64748b';
                el.style.backgroundColor = 'transparent';
                if (icon) {
                    icon.style.color = '#64748b';
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

            // Handle auth requirement
            if (data.require_login) {
                resetProgressBar();
                if (wrapper) {
                    wrapper.classList.remove('page-fade-leave');
                    wrapper.classList.add('page-fade-active');
                }
                isTransitioning = false;
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

                // Re-execute scripts inside swapped content
                const scripts = wrapper.querySelectorAll('script');
                scripts.forEach(oldScript => {
                    const newScript = document.createElement('script');
                    Array.from(oldScript.attributes).forEach(attr => {
                        newScript.setAttribute(attr.name, attr.value);
                    });
                    newScript.textContent = oldScript.textContent;
                    oldScript.parentNode.replaceChild(newScript, oldScript);
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
