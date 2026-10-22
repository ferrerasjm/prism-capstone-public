/**
 * PRISM shared toast helper: window.PrismToast.show(message, variant).
 *
 * Loaded unconditionally by every layout, including ones (login) that skip the heavier
 * shared JS bundle for a faster first paint - toasts still need to work there, and a
 * dependency on Bootstrap's JS is exactly what silently broke them. Several pages had also
 * each grown their own copy of this, one of them at a 4000ms delay while everywhere else
 * had settled on 6500 for errors and 4500 for everything else, and none of them remembered
 * what was already on screen: clicking Sign in twice with a blank password stacked two
 * identical toasts. One implementation, loaded everywhere, fixes both at once.
 *
 * Entrance, autohide, dismissal, and stack reflow are all controlled here rather than by
 * Bootstrap. This keeps the exact same motion and timing on every layout, including login
 * pages that deliberately do not load bootstrap.bundle.min.js.
 *
 * No dependencies. Safe to load before or after Bootstrap's JS.
 */
(function () {
    'use strict';

    var ICONS = {
        success: 'bi-check-circle-fill',
        danger: 'bi-exclamation-circle-fill',
        warning: 'bi-exclamation-triangle-fill',
        info: 'bi-info-circle-fill',
    };
    var LABELS = { success: 'Success', danger: 'Error', warning: 'Warning', info: 'Notice' };
    var DELAYS = { danger: 6500 };
    var DEFAULT_DELAY = 4500;
    var FADE_DURATION = 400;
    var STACK_DURATION = 320;
    var MAX_VISIBLE = 4;

    function reducedMotion() {
        return window.matchMedia
            && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }

    function region() {
        var el = document.querySelector('.prism-toast-region');
        if (el) return el;

        el = document.createElement('div');
        el.className = 'prism-toast-region';
        el.setAttribute('aria-live', 'polite');
        el.setAttribute('aria-atomic', 'true');
        document.body.appendChild(el);
        return el;
    }

    function captureStack(toastEl) {
        var reg = toastEl.parentElement;
        if (!reg) return [];

        return Array.prototype.slice.call(reg.querySelectorAll('.prism-toast'))
            .filter(function (el) { return el !== toastEl; })
            .map(function (el) {
                if (el._prismStackAnimation) el._prismStackAnimation.finish();
                return { el: el, top: el.getBoundingClientRect().top };
            });
    }

    function removeAndAnimateStack(toastEl) {
        if (!toastEl.isConnected) return;

        var positions = toastEl._prismStackPositions || captureStack(toastEl);
        toastEl.remove();

        if (reducedMotion()) return;

        positions.forEach(function (position) {
            if (!position.el.isConnected || !position.el.animate) return;

            var offset = position.top - position.el.getBoundingClientRect().top;
            if (Math.abs(offset) < 1) return;

            position.el._prismStackAnimation = position.el.animate([
                { transform: 'translateY(' + offset + 'px) scale(1)' },
                { transform: 'translateY(0) scale(1)' },
            ], {
                duration: STACK_DURATION,
                easing: 'cubic-bezier(.22, 1, .36, 1)',
            });
        });
    }

    function dismiss(toastEl) {
        if (toastEl.dataset.toastDismissed) return;
        toastEl.dataset.toastDismissed = 'true';
        clearTimeout(toastEl._prismToastTimer);
        toastEl._prismStackPositions = captureStack(toastEl);

        toastEl.classList.add('prism-toast-hiding');
        setTimeout(
            function () { removeAndAnimateStack(toastEl); },
            reducedMotion() ? 0 : FADE_DURATION
        );
    }

    function mount(toastEl) {
        if (!toastEl || toastEl.dataset.toastMounted) return toastEl;

        toastEl.dataset.toastMounted = 'true';
        toastEl.dataset.bsAutohide = 'false';
        toastEl.classList.add('fade', 'show', 'prism-toast-entering');

        var close = toastEl.querySelector('.prism-toast-close');
        if (close) {
            // Prevent Bootstrap's delegated dismiss handler from racing this helper.
            close.addEventListener('click', function () { dismiss(toastEl); });
        }

        var delay = toastEl.classList.contains('prism-toast-danger')
            ? DELAYS.danger
            : DEFAULT_DELAY;
        toastEl._prismToastTimer = setTimeout(function () { dismiss(toastEl); }, delay);

        if (reducedMotion()) {
            toastEl.classList.remove('prism-toast-entering');
        } else {
            requestAnimationFrame(function () {
                requestAnimationFrame(function () {
                    toastEl.classList.remove('prism-toast-entering');
                });
            });
        }

        return toastEl;
    }

    function show(message, variant) {
        variant = ICONS[variant] ? variant : 'info';
        var reg = region();
        var key = variant + '::' + message;
        var existing = Array.prototype.slice.call(reg.querySelectorAll('.toast'));

        // The same message already showing starts over instead of stacking a duplicate.
        existing.filter(function (t) { return t.dataset.toastKey === key; }).forEach(dismiss);

        // Different messages are still capped, so a burst of distinct errors cannot grow
        // the region without bound either.
        var remaining = existing.filter(function (t) { return t.dataset.toastKey !== key; });
        while (remaining.length >= MAX_VISIBLE) {
            dismiss(remaining.shift());
        }

        var toastEl = document.createElement('div');
        toastEl.className = 'toast fade prism-toast prism-toast-' + variant;
        toastEl.dataset.toastKey = key;
        toastEl.setAttribute('role', 'status');
        toastEl.setAttribute('aria-live', 'polite');
        toastEl.setAttribute('aria-atomic', 'true');
        // Bootstrap's own autohide timer is deliberately left off - ours is the only one,
        // so a page without Bootstrap's JS loaded still reliably dismisses this.
        toastEl.dataset.bsAutohide = 'false';

        var icon = document.createElement('span');
        icon.className = 'prism-toast-icon';
        icon.setAttribute('aria-hidden', 'true');
        icon.innerHTML = '<i class="bi ' + ICONS[variant] + '"></i>';

        var body = document.createElement('div');
        body.className = 'toast-body';
        var srLabel = document.createElement('span');
        srLabel.className = 'visually-hidden';
        srLabel.textContent = LABELS[variant] + ': ';
        body.appendChild(srLabel);
        body.appendChild(document.createTextNode(message));

        var close = document.createElement('button');
        close.type = 'button';
        close.className = 'btn-close prism-toast-close';
        close.setAttribute('aria-label', 'Close');

        toastEl.appendChild(icon);
        toastEl.appendChild(body);
        toastEl.appendChild(close);
        reg.appendChild(toastEl);
        return mount(toastEl);
    }

    function mountServerRenderedToasts() {
        var toasts = Array.prototype.slice.call(
            document.querySelectorAll('.prism-toast-region .prism-toast')
        );
        if (!toasts.length) return;

        // A layout flash and a page-level validation error can both render a region.
        // Move their toasts into one stack so fixed-position regions never overlap.
        var primaryRegion = region();
        toasts.forEach(function (toastEl) {
            if (toastEl.parentElement !== primaryRegion) primaryRegion.appendChild(toastEl);
            mount(toastEl);
        });

        document.querySelectorAll('.prism-toast-region').forEach(function (toastRegion) {
            if (toastRegion !== primaryRegion && !toastRegion.querySelector('.prism-toast')) {
                toastRegion.remove();
            }
        });

        while (toasts.length > MAX_VISIBLE) dismiss(toasts.shift());
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', mountServerRenderedToasts);
    } else {
        mountServerRenderedToasts();
    }

    window.PrismToast = { show: show, mount: mount, dismiss: dismiss };
})();
