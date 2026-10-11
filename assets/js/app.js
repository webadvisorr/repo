/* evrako — site arayüzü etkileşimleri (bağımlılıksız, saf JS) */
(function () {
    'use strict';

    /* ---------- Tema (dark/light) ---------- */
    var root = document.documentElement;
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
    var stored = null;
    try { stored = localStorage.getItem(STORAGE_KEY); } catch (e) {}
    applyTheme(stored || root.getAttribute('data-theme-default') || 'system');

    if (window.matchMedia) {
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function () {
            var current = null;
            try { current = localStorage.getItem(STORAGE_KEY); } catch (e) {}
            if (!current || current === 'system') { applyTheme('system'); }
        });
    }
    document.addEventListener('click', function (event) {
        var btn = event.target.closest('[data-theme-toggle]');
        if (!btn) { return; }
        event.preventDefault();
        var current = root.getAttribute('data-theme');
        var next = current === 'dark' ? 'light' : 'dark';
        try { localStorage.setItem(STORAGE_KEY, next); } catch (e) {}
        applyTheme(next);
    });

    /* ---------- Yan panel (drawer) + sekmeler ---------- */
    var drawer = document.querySelector('[data-drawer]');
    var backdrop = document.querySelector('[data-drawer-backdrop]');

    function openDrawer(tab) {
        if (!drawer) { return; }
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
        document.body.classList.remove('no-scroll');
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
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') { closeDrawer(); }
    });

    /* ---------- Slider ---------- */
    document.querySelectorAll('[data-slider]').forEach(function (slider) {
        var track = slider.querySelector('.slider__track');
        var slides = slider.querySelectorAll('.slide');
        if (!track || slides.length === 0) { return; }
        var index = 0;
        var autoplay = parseInt(slider.getAttribute('data-autoplay') || '6000', 10);
        var timer = null;

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
        if (slides.length > 1) { slider.appendChild(dots); }

        function go(next) {
            index = (next + slides.length) % slides.length;
            track.style.transform = 'translateX(' + (-index * 100) + '%)';
            slider.querySelectorAll('.slider__dot').forEach(function (dot, i) {
                dot.classList.toggle('is-active', i === index);
            });
            restart();
        }
        function restart() {
            if (timer) { clearInterval(timer); }
            if (slides.length > 1 && autoplay > 0) { timer = setInterval(function () { go(index + 1); }, autoplay); }
        }
        if (slides.length > 1) {
            var prev = document.createElement('button');
            prev.type = 'button'; prev.className = 'slider__arrow slider__arrow--prev'; prev.innerHTML = '‹';
            prev.addEventListener('click', function () { go(index - 1); });
            var next = document.createElement('button');
            next.type = 'button'; next.className = 'slider__arrow slider__arrow--next'; next.innerHTML = '›';
            next.addEventListener('click', function () { go(index + 1); });
            slider.appendChild(prev); slider.appendChild(next);

            /* Dokunmatik kaydırma */
            var startX = null;
            track.addEventListener('touchstart', function (e) { startX = e.touches[0].clientX; }, { passive: true });
            track.addEventListener('touchend', function (e) {
                if (startX === null) { return; }
                var delta = e.changedTouches[0].clientX - startX;
                if (Math.abs(delta) > 45) { go(delta < 0 ? index + 1 : index - 1); }
                startX = null;
            }, { passive: true });
        }
        restart();
    });

    /* ---------- Preloader ---------- */
    var preloader = document.getElementById('preloader');
    if (preloader) {
        var minSeconds = parseFloat(preloader.getAttribute('data-min') || '0.6') * 1000;
        var maxSeconds = parseFloat(preloader.getAttribute('data-max') || '6') * 1000;
        var started = Date.now();
        var hidden = false;
        function hidePreloader() {
            if (hidden) { return; }
            hidden = true;
            var elapsed = Date.now() - started;
            setTimeout(function () {
                preloader.classList.add('is-hidden');
                setTimeout(function () { preloader.remove(); }, 400);
            }, Math.max(0, minSeconds - elapsed));
        }
        window.addEventListener('load', hidePreloader);
        setTimeout(hidePreloader, maxSeconds);
    }

    /* ---------- Dil seçici ---------- */
    document.querySelectorAll('[data-language-select]').forEach(function (select) {
        select.addEventListener('change', function () {
            var url = new URL(window.location.href);
            url.searchParams.set('dil', select.value);
            window.location.href = url.toString();
        });
    });

    /* ---------- Onay gerektiren işlemler ---------- */
    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (form.hasAttribute('data-confirm') && !window.confirm(form.getAttribute('data-confirm'))) {
            event.preventDefault();
            return;
        }
        var button = form.querySelector('button[type="submit"]');
        if (button && !button.hasAttribute('data-no-loading')) {
            button.disabled = true;
            button.dataset.originalText = button.textContent;
            button.textContent = '…';
            setTimeout(function () {
                button.disabled = false;
                if (button.dataset.originalText) { button.textContent = button.dataset.originalText; }
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
})();
