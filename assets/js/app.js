/* evrako — site arayüzü etkileşimleri (bağımlılıksız, saf JS) */
(function () {
    'use strict';

    var root = document.documentElement;
    var storage = {
        get: function (key) { try { return localStorage.getItem(key); } catch (e) { return null; } },
        set: function (key, value) { try { localStorage.setItem(key, value); } catch (e) {} },
        session: function (key) { try { return sessionStorage.getItem(key); } catch (e) { return null; } },
        setSession: function (key, value) { try { sessionStorage.setItem(key, value); } catch (e) {} }
    };
    var reduceMotion = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);

    /* ---------- Tema (dark/light) ---------- */
    var STORAGE_KEY = 'evrako-theme';

    function systemTheme() {
        return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }
    function applyTheme(mode) {
        var resolved = mode === 'system' ? systemTheme() : mode;
        root.setAttribute('data-theme', resolved);
        var btn = document.querySelector('[data-theme-toggle]');
        if (btn) {
            btn.setAttribute('aria-label', resolved === 'dark' ? 'Açık tema' : 'Koyu tema');
            var icon = btn.querySelector('[data-theme-icon]');
            if (icon) { icon.textContent = resolved === 'dark' ? '☀' : '☾'; }
        }
    }
    applyTheme(storage.get(STORAGE_KEY) || root.getAttribute('data-theme-default') || 'system');

    if (window.matchMedia) {
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function () {
            var current = storage.get(STORAGE_KEY);
            if (!current || current === 'system') { applyTheme('system'); }
        });
    }
    document.addEventListener('click', function (event) {
        var btn = event.target.closest('[data-theme-toggle]');
        if (!btn) { return; }
        event.preventDefault();
        var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        storage.set(STORAGE_KEY, next);
        applyTheme(next);
    });

    /* ---------- Yan panel (drawer) + sekmeler ---------- */
    var drawer = document.querySelector('[data-drawer]');
    var backdrop = document.querySelector('[data-drawer-backdrop]');

    function openDrawer(tab) {
        if (!drawer) { return; }
        closeMenu();
        drawer.classList.add('is-open');
        if (backdrop) { backdrop.classList.add('is-open'); }
        document.body.classList.add('no-scroll');
        drawer.setAttribute('aria-hidden', 'false');
        if (tab) { activateTab(tab); }
        var firstInput = drawer.querySelector('.tab-panel.is-active input:not([type=hidden])');
        if (firstInput) { setTimeout(function () { firstInput.focus(); }, 220); }
    }
    function closeDrawer() {
        if (!drawer) { return; }
        drawer.classList.remove('is-open');
        if (backdrop) { backdrop.classList.remove('is-open'); }
        if (!mobileMenu || !mobileMenu.classList.contains('is-open')) { document.body.classList.remove('no-scroll'); }
        drawer.setAttribute('aria-hidden', 'true');
    }
    function activateTab(name) {
        document.querySelectorAll('[data-tab]').forEach(function (btn) {
            btn.classList.toggle('is-active', btn.getAttribute('data-tab') === name);
            btn.setAttribute('aria-selected', btn.getAttribute('data-tab') === name ? 'true' : 'false');
        });
        document.querySelectorAll('[data-tab-panel]').forEach(function (panel) {
            panel.classList.toggle('is-active', panel.getAttribute('data-tab-panel') === name);
        });
    }
    document.addEventListener('click', function (event) {
        var opener = event.target.closest('[data-open-drawer]');
        if (opener) {
            event.preventDefault();
            openDrawer(opener.getAttribute('data-open-drawer') || undefined);
            return;
        }
        if (event.target.closest('[data-close-drawer]') || event.target === backdrop) { closeDrawer(); return; }
        var tabButton = event.target.closest('[data-tab]');
        if (tabButton) { event.preventDefault(); activateTab(tabButton.getAttribute('data-tab')); }
    });

    /* ---------- Mobil menü (hamburger) ---------- */
    var mobileMenu = document.querySelector('[data-mobile-menu]');
    var menuBackdrop = document.querySelector('[data-menu-backdrop]');
    var menuButton = document.querySelector('[data-open-menu]');

    function openMenu() {
        if (!mobileMenu) { return; }
        closeDrawer();
        mobileMenu.classList.add('is-open');
        if (menuBackdrop) { menuBackdrop.classList.add('is-open'); }
        document.body.classList.add('no-scroll');
        mobileMenu.setAttribute('aria-hidden', 'false');
        if (menuButton) {
            menuButton.classList.add('is-active');
            menuButton.setAttribute('aria-expanded', 'true');
        }
    }
    function closeMenu() {
        if (!mobileMenu) { return; }
        mobileMenu.classList.remove('is-open');
        if (menuBackdrop) { menuBackdrop.classList.remove('is-open'); }
        if (!drawer || !drawer.classList.contains('is-open')) { document.body.classList.remove('no-scroll'); }
        mobileMenu.setAttribute('aria-hidden', 'true');
        if (menuButton) {
            menuButton.classList.remove('is-active');
            menuButton.setAttribute('aria-expanded', 'false');
        }
    }
    document.addEventListener('click', function (event) {
        if (event.target.closest('[data-open-menu]')) { event.preventDefault(); openMenu(); return; }
        if (event.target.closest('[data-close-menu]') || event.target === menuBackdrop) { closeMenu(); return; }
        if (mobileMenu && mobileMenu.classList.contains('is-open') && event.target.closest('[data-mobile-menu] a, [data-mobile-menu] button')) {
            closeMenu();
        }
    });
    window.addEventListener('resize', function () {
        if (window.innerWidth > 900) { closeMenu(); }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') { closeDrawer(); closeMenu(); closePopup(); }
    });

    /* ---------- Görünür oldukça yumuşak giriş (reveal) ---------- */
    var revealTargets = document.querySelectorAll('.card, .step, .section-head, .plan, .stat, .feature-icon, .cta-band, .accordion, .trust-item');
    if (!reduceMotion && revealTargets.length) {
        revealTargets.forEach(function (el, i) {
            el.classList.add('reveal');
            el.style.setProperty('--reveal-delay', (Math.min(i % 6, 5) * 70) + 'ms');
        });
        if ('IntersectionObserver' in window) {
            var observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-visible');
                        observer.unobserve(entry.target);
                    }
                });
            }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
            revealTargets.forEach(function (el) { observer.observe(el); });
        } else {
            revealTargets.forEach(function (el) { el.classList.add('is-visible'); });
        }
    }

    /* ---------- Slider ---------- */
    document.querySelectorAll('[data-slider]').forEach(function (slider) {
        var track = slider.querySelector('.slider__track');
        var slides = slider.querySelectorAll('.slide');
        if (!track || slides.length === 0) { return; }
        var index = 0;
        var autoplay = parseInt(slider.getAttribute('data-autoplay') || '6000', 10);
        var timer = null;
        var progress = slider.querySelector('.slider__progress span');
        var kenburns = slider.hasAttribute('data-kenburns');

        var dots = document.createElement('div');
        dots.className = 'slider__nav';
        slides.forEach(function (_, i) {
            var dot = document.createElement('button');
            dot.type = 'button';
            dot.className = 'slider__dot' + (i === 0 ? ' is-active' : '');
            dot.setAttribute('aria-label', 'Slayt ' + (i + 1));
            dot.addEventListener('click', function () { go(i); });
            dots.appendChild(dot);
        });
        if (slides.length > 1) { slider.setAttribute('tabindex', '0'); }

        function paint() {
            slides.forEach(function (slide, i) {
                slide.classList.toggle('is-active', i === index);
                if (kenburns) { slide.classList.toggle('kb-run', i === index && !reduceMotion); }
            });
        }
        function go(next) {
            index = (next + slides.length) % slides.length;
            track.style.transform = 'translateX(' + (-index * 100) + '%)';
            slider.querySelectorAll('.slider__dot').forEach(function (dot, i) {
                dot.classList.toggle('is-active', i === index);
            });
            paint();
            restart();
        }
        function runProgress() {
            if (!progress || slides.length < 2 || autoplay <= 0 || reduceMotion) { return; }
            progress.style.transition = 'none';
            progress.style.width = '0%';
            void progress.offsetWidth;
            progress.style.transition = 'width ' + autoplay + 'ms linear';
            progress.style.width = '100%';
        }
        function restart() {
            if (timer) { clearInterval(timer); }
            runProgress();
            if (slides.length > 1 && autoplay > 0) {
                timer = setInterval(function () { go(index + 1); }, autoplay);
            }
        }
        if (slides.length > 1) {
            // Sadece nokta göstergeleri: sağ/sol ok düğmeleri kaldırıldı.
            var controls = document.createElement('div');
            controls.className = 'slider__controls';
            controls.appendChild(dots);
            slider.appendChild(controls);

            var startX = null;
            track.addEventListener('touchstart', function (e) { startX = e.touches[0].clientX; }, { passive: true });
            track.addEventListener('touchend', function (e) {
                if (startX === null) { return; }
                var delta = e.changedTouches[0].clientX - startX;
                if (Math.abs(delta) > 45) { go(delta < 0 ? index + 1 : index - 1); }
                startX = null;
            }, { passive: true });

            slider.addEventListener('keydown', function (e) {
                if (e.key === 'ArrowLeft') { go(index - 1); }
                else if (e.key === 'ArrowRight') { go(index + 1); }
            });
            slider.addEventListener('mouseenter', function () { if (timer) { clearInterval(timer); timer = null; } });
            slider.addEventListener('mouseleave', restart);
        }
        paint();
        restart();
    });

    /* ---------- Preloader ---------- */
    var preloader = document.getElementById('preloader');
    if (preloader) {
        var minSeconds = parseFloat(preloader.getAttribute('data-min') || '0.6') * 1000;
        var maxSeconds = parseFloat(preloader.getAttribute('data-max') || '6') * 1000;
        var started = Date.now();
        var hidden = false;
        var hidePreloader = function () {
            if (hidden) { return; }
            hidden = true;
            var elapsed = Date.now() - started;
            setTimeout(function () {
                preloader.classList.add('is-hidden');
                setTimeout(function () { preloader.remove(); }, 400);
            }, Math.max(0, minSeconds - elapsed));
        };
        window.addEventListener('load', hidePreloader);
        setTimeout(hidePreloader, maxSeconds);
    }

    /* ---------- Dil + para birimi seçici (footer) ---------- */
    document.querySelectorAll('[data-language-select]').forEach(function (select) {
        select.addEventListener('change', function () {
            var url = new URL(window.location.href);
            url.searchParams.set('dil', select.value);
            window.location.href = url.toString();
        });
    });
    document.querySelectorAll('[data-currency-select]').forEach(function (select) {
        select.addEventListener('change', function () {
            var url = new URL(window.location.href);
            url.searchParams.set('para', select.value);
            window.location.href = url.toString();
        });
    });

    /* ---------- Onay + gönderim yükleniyor durumu ---------- */
    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (form.hasAttribute('data-confirm') && !window.confirm(form.getAttribute('data-confirm'))) {
            event.preventDefault();
            return;
        }
        if (event.defaultPrevented) { return; }
        var button = form.querySelector('button[type="submit"]');
        if (button && !button.hasAttribute('data-no-loading')) {
            button.classList.add('is-loading');
            button.disabled = true;
            var label = button.querySelector('span:not(.btn__icon)') || button;
            button.dataset.originalText = label.textContent;
            label.textContent = button.getAttribute('data-loading-text') || '…';
            setTimeout(function () {
                button.disabled = false;
                button.classList.remove('is-loading');
                if (button.dataset.originalText) { label.textContent = button.dataset.originalText; }
            }, 12000);
        }
    });

    /* ---------- Şifre göster/gizle ---------- */
    document.addEventListener('click', function (event) {
        var toggle = event.target.closest('[data-toggle-password]');
        if (!toggle) { return; }
        var input = document.querySelector(toggle.getAttribute('data-toggle-password'));
        if (!input) { return; }
        event.preventDefault();
        input.type = input.type === 'password' ? 'text' : 'password';
    });

    /* ---------- Form doğrulama (istemci tarafı, anında geri bildirim) ---------- */
    function fieldError(input, message) {
        var field = input.closest('.field');
        if (!field) { return; }
        field.classList.add('has-error');
        input.setAttribute('aria-invalid', 'true');
        var note = field.querySelector('.form-error.js-error');
        if (!note) {
            note = document.createElement('p');
            note.className = 'form-error js-error';
            input.insertAdjacentElement('afterend', note);
        }
        note.textContent = message;
    }
    function clearFieldError(input) {
        var field = input.closest('.field');
        if (!field) { return; }
        field.classList.remove('has-error');
        input.removeAttribute('aria-invalid');
        var note = field.querySelector('.form-error.js-error');
        if (note) { note.remove(); }
    }
    function validateField(input) {
        if (input.type === 'hidden' || input.disabled || input.closest('.hp-field')) { return true; }
        var value = (input.value || '').trim();
        if (input.hasAttribute('required') && value === '') {
            fieldError(input, (input.dataset.label || 'Bu alan') + ' zorunludur.');
            return false;
        }
        if (value !== '' && input.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
            fieldError(input, 'Geçerli bir e-posta girin.');
            return false;
        }
        if (value !== '' && input.minLength > 0 && value.length < input.minLength) {
            fieldError(input, 'En az ' + input.minLength + ' karakter girin.');
            return false;
        }
        clearFieldError(input);
        return true;
    }
    document.querySelectorAll('form[data-form-validate]').forEach(function (form) {
        form.querySelectorAll('input, textarea, select').forEach(function (input) {
            input.addEventListener('blur', function () { validateField(input); });
            input.addEventListener('input', function () { if (input.getAttribute('aria-invalid')) { validateField(input); } });
        });
        var counterTarget = form.querySelector('[data-char-count-for]');
        if (counterTarget) {
            var counted = document.getElementById(counterTarget.getAttribute('data-char-count-for'));
            if (counted) {
                var update = function () { counterTarget.textContent = String(counted.value.length); };
                counted.addEventListener('input', update);
                update();
            }
        }
        form.addEventListener('submit', function (event) {
            var valid = true;
            var firstInvalid = null;
            form.querySelectorAll('input, textarea, select').forEach(function (input) {
                if (!validateField(input)) {
                    valid = false;
                    if (!firstInvalid) { firstInvalid = input; }
                }
            });
            if (!valid) {
                event.preventDefault();
                if (firstInvalid) { firstInvalid.focus(); }
            }
        });
    });

    /* ---------- Çerez onayı + analitik ---------- */
    var ANALYTICS = (function () {
        var el = document.getElementById('evrako-analytics');
        if (!el) { return null; }
        try { return JSON.parse(el.textContent); } catch (e) { return null; }
    })();
    var analyticsLoaded = false;

    function loadAnalytics() {
        if (!ANALYTICS || analyticsLoaded) { return; }
        analyticsLoaded = true;
        var nonce = ANALYTICS.nonce || '';
        var provider = ANALYTICS.provider || '';
        var head = document.head;

        if (provider === 'plausible') {
            var p = document.createElement('script');
            p.defer = true; p.async = true; p.nonce = nonce;
            p.src = 'https://plausible.io/js/script.js';
            if (ANALYTICS.domain) { p.setAttribute('data-domain', ANALYTICS.domain); }
            head.appendChild(p);
            return;
        }
        if (provider === 'custom') {
            var tmp = document.createElement('div');
            tmp.innerHTML = ANALYTICS.custom || '';
            Array.prototype.forEach.call(tmp.querySelectorAll('script'), function (old) {
                var s = document.createElement('script');
                for (var i = 0; i < old.attributes.length; i++) {
                    s.setAttribute(old.attributes[i].name, old.attributes[i].value);
                }
                if (!s.getAttribute('nonce')) { s.nonce = nonce; }
                if (old.src) { s.src = old.src; } else { s.text = old.text; }
                head.appendChild(s);
            });
            return;
        }
        // Varsayılan: GA4
        if (!ANALYTICS.gaId) { return; }
        window.dataLayer = window.dataLayer || [];
        window.gtag = function () { window.dataLayer.push(arguments); };
        window.gtag('js', new Date());
        window.gtag('config', ANALYTICS.gaId);
        var g = document.createElement('script');
        g.async = true; g.nonce = nonce;
        g.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(ANALYTICS.gaId);
        head.appendChild(g);
    }

    var cookieBanner = document.querySelector('[data-cookie-banner]');
    function showCookieBanner() {
        if (!cookieBanner) { return; }
        cookieBanner.hidden = false;
        document.body.classList.add('has-cookie-banner');
        requestAnimationFrame(function () { cookieBanner.classList.add('is-visible'); });
    }
    function hideCookieBanner() {
        if (!cookieBanner) { return; }
        cookieBanner.classList.remove('is-visible');
        document.body.classList.remove('has-cookie-banner');
        setTimeout(function () { cookieBanner.hidden = true; }, 260);
    }
    if (cookieBanner) {
        if (!storage.get('evrako-consent')) {
            setTimeout(showCookieBanner, 900);
        }
        cookieBanner.addEventListener('click', function (event) {
            if (event.target.closest('[data-cookie-accept]')) {
                storage.set('evrako-consent', 'accepted');
                hideCookieBanner();
                loadAnalytics();
            } else if (event.target.closest('[data-cookie-decline]')) {
                storage.set('evrako-consent', 'declined');
                hideCookieBanner();
            }
        });
    }
    if (ANALYTICS && (!ANALYTICS.consentRequired || storage.get('evrako-consent') === 'accepted')) {
        loadAnalytics();
    }

    /* ---------- Popup (duyuru) ---------- */
    var popup = document.querySelector('[data-popup]');
    var popupBackdrop = document.querySelector('[data-popup-backdrop]');
    function openPopup() {
        if (!popup) { return; }
        popup.hidden = false;
        if (popupBackdrop) { popupBackdrop.hidden = false; }
        document.body.classList.add('no-scroll');
        requestAnimationFrame(function () {
            popup.classList.add('is-open');
            if (popupBackdrop) { popupBackdrop.classList.add('is-open'); }
        });
    }
    function closePopup() {
        if (!popup || popup.hidden) { return; }
        popup.classList.remove('is-open');
        if (popupBackdrop) { popupBackdrop.classList.remove('is-open'); }
        document.body.classList.remove('no-scroll');
        setTimeout(function () {
            popup.hidden = true;
            if (popupBackdrop) { popupBackdrop.hidden = true; }
        }, 260);
    }
    if (popup) {
        popupBackdrop && popupBackdrop.addEventListener('click', closePopup);
        popup.addEventListener('click', function (event) { if (event.target.closest('[data-popup-close]')) { closePopup(); } });

        var frequency = popup.getAttribute('data-frequency') || 'session';
        var shouldShow = true;
        if (frequency === 'session') {
            shouldShow = storage.session('evrako-popup-seen') !== '1';
        } else if (frequency === 'week') {
            var seenAt = parseInt(storage.get('evrako-popup-seen') || '0', 10);
            shouldShow = !seenAt || (Date.now() - seenAt) > (7 * 24 * 60 * 60 * 1000);
        }
        if (shouldShow) {
            var delay = (parseFloat(popup.getAttribute('data-delay') || '3') * 1000);
            setTimeout(function () {
                openPopup();
                if (frequency === 'session') { storage.setSession('evrako-popup-seen', '1'); }
                else if (frequency === 'week') { storage.set('evrako-popup-seen', String(Date.now())); }
            }, Math.max(0, delay));
        }
    }

    /* ---------- Sabit CTA + geri dön + okuma çubuğu ---------- */
    var stickyCta = document.querySelector('[data-sticky-cta]');
    var backToTop = document.querySelector('[data-back-to-top]');
    var progressBar = document.querySelector('[data-scroll-progress] span');
    var stickyDismissed = storage.session('evrako-sticky-closed') === '1';

    function onScroll() {
        var y = window.scrollY || document.documentElement.scrollTop;
        var scrolled = y > 320;
        document.body.classList.toggle('is-scrolled', scrolled);
        if (stickyCta && !stickyDismissed) {
            var visible = y > 420;
            stickyCta.hidden = !visible;
            if (visible) { requestAnimationFrame(function () { stickyCta.classList.add('is-visible'); }); }
            else { stickyCta.classList.remove('is-visible'); }
        }
        document.body.classList.toggle('has-sticky-cta', !!(stickyCta && !stickyDismissed && y > 420));
        if (backToTop) {
            backToTop.classList.toggle('is-visible', scrolled);
        }
        if (progressBar) {
            var height = document.documentElement.scrollHeight - window.innerHeight;
            progressBar.style.width = (height > 0 ? Math.min(100, (y / height) * 100) : 0) + '%';
        }
    }
    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', onScroll);
    onScroll();

    if (stickyCta) {
        var closeSticky = stickyCta.querySelector('[data-sticky-cta-close]');
        closeSticky && closeSticky.addEventListener('click', function () {
            stickyDismissed = true;
            storage.setSession('evrako-sticky-closed', '1');
            stickyCta.classList.remove('is-visible');
            document.body.classList.remove('has-sticky-cta');
            setTimeout(function () { stickyCta.hidden = true; }, 220);
        });
    }
    if (backToTop) {
        backToTop.addEventListener('click', function () {
            window.scrollTo({ top: 0, behavior: reduceMotion ? 'auto' : 'smooth' });
        });
    }

})();
