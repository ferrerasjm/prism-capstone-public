/**
 * PRISM shared tooltip helper.
 *
 * The browser's native title="" tooltip waits ~700-1500ms before it appears, which reads as
 * sluggish on hover-heavy UI (icon buttons, truncated labels, table actions). This replaces
 * that wait with a single delegated listener: the first time the pointer or keyboard focus
 * reaches an element carrying title="", its text is moved onto data-prism-tooltip and the
 * title attribute is stripped (so the slow native bubble can never show alongside this one),
 * then a small dark bubble appears after a much shorter delay.
 *
 * Delegated rather than scanned once at load, so it also picks up elements added to the page
 * after DOMContentLoaded without needing a rescan or a MutationObserver.
 *
 * No dependencies. Safe to load before or after Bootstrap's JS.
 */
(function () {
    'use strict';

    var SHOW_DELAY = 20;
    var FADE_DURATION = 100;

    var bubble = null;
    var showTimer = null;
    var activeTarget = null;
    // When constraint validation fails the browser auto-scrolls to the first
    // invalid field; that scroll would otherwise immediately trigger the global
    // scroll->hide and swallow the validation tooltip before it's readable.
    var suppressScrollHideUntil = 0;

    function reducedMotion() {
        return window.matchMedia
            && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }

    function ensureBubble() {
        if (bubble) return bubble;

        bubble = document.createElement('div');
        bubble.className = 'prism-tooltip';
        bubble.setAttribute('role', 'tooltip');
        bubble.hidden = true;
        document.body.appendChild(bubble);
        return bubble;
    }

    function claim(el) {
        var title = el.getAttribute('title');
        if (title) {
            el.dataset.prismTooltip = title;
            el.removeAttribute('title');
        }
        return el.dataset.prismTooltip || '';
    }

    function position(el) {
        var rect = el.getBoundingClientRect();
        var tipRect = bubble.getBoundingClientRect();
        var margin = 8;

        // Centre on the field, except a wide one (e.g. a full-width textarea),
        // where the bubble belongs near the top-left where reading starts rather
        // than lost in the middle. Always clamp to the viewport.
        var left = rect.width > 320
            ? rect.left + 12
            : rect.left + (rect.width / 2) - (tipRect.width / 2);
        left = Math.max(8, Math.min(left, window.innerWidth - tipRect.width - 8));

        // Above the field when it fits. Otherwise tuck it just inside the top of
        // a tall field (a big textarea), or below a short one.
        var top = rect.top - tipRect.height - margin;
        if (top < 8) {
            top = rect.height > 160 ? rect.top + margin : rect.bottom + margin;
        }

        bubble.style.left = left + 'px';
        bubble.style.top = top + 'px';
    }

    function render(el, text) {
        if (!text) return;

        ensureBubble();
        bubble.textContent = text;
        bubble.hidden = false;
        activeTarget = el;
        position(el);

        if (reducedMotion()) {
            bubble.classList.add('is-visible');
        } else {
            requestAnimationFrame(function () {
                requestAnimationFrame(function () {
                    if (activeTarget === el) bubble.classList.add('is-visible');
                });
            });
        }
    }

    function show(el) {
        render(el, claim(el));
    }

    function hide() {
        clearTimeout(showTimer);
        activeTarget = null;
        if (!bubble || bubble.hidden) return;

        bubble.classList.remove('is-visible');
        var delay = reducedMotion() ? 0 : FADE_DURATION;
        setTimeout(function () {
            if (!bubble.classList.contains('is-visible')) bubble.hidden = true;
        }, delay);
    }

    function scheduleShow(el) {
        if (activeTarget === el) return;
        clearTimeout(showTimer);
        showTimer = setTimeout(function () { show(el); }, SHOW_DELAY);
    }

    function targetFor(el) {
        return el && el.closest ? el.closest('[title]:not([title=""]), [data-prism-tooltip]') : null;
    }

    document.addEventListener('pointerover', function (event) {
        var target = targetFor(event.target);
        if (!target) return;
        // Strip title -> data-prism-tooltip synchronously on the very first hover,
        // not inside the delayed show(): while a title attribute is still present
        // the browser's own ~700ms native-tooltip clock is running in parallel and
        // will win whenever our short timer gets reset by pointer movement.
        claim(target);
        scheduleShow(target);
    });

    document.addEventListener('pointerout', function (event) {
        var target = targetFor(event.target);
        if (target && (!event.relatedTarget || !target.contains(event.relatedTarget))) hide();
    });

    document.addEventListener('focusin', function (event) {
        var target = targetFor(event.target);
        if (target) show(target);
    });

    document.addEventListener('focusout', function (event) {
        if (targetFor(event.target)) hide();
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') hide();
    });

    window.addEventListener('scroll', function () {
        // During the post-validation window, follow the field the browser is
        // scrolling into view instead of hiding the tooltip.
        if (Date.now() < suppressScrollHideUntil) {
            if (activeTarget && bubble && !bubble.hidden) position(activeTarget);
            return;
        }
        hide();
    }, true);
    window.addEventListener('resize', hide);

    // Same problem as title="", worse: the browser's native constraint-validation
    // bubble ("Please fill out this field") is slow to appear and can't be
    // restyled or timed at all. 'invalid' doesn't bubble, so this must listen
    // on the capture phase to catch it from any field.
    function hideOnEdit(event) {
        hide();
        event.target.removeEventListener('input', hideOnEdit);
    }

    var validationPassActive = false;

    document.addEventListener('invalid', function (event) {
        var el = event.target;
        if (!el || !('validationMessage' in el)) return;

        event.preventDefault();

        // 'invalid' fires once per invalid field, in DOM order. Only the first
        // one matters — that's the field the browser focuses — so ignore the
        // rest of this validation pass rather than letting a later, possibly
        // off-screen field steal the bubble.
        if (validationPassActive) return;
        validationPassActive = true;
        setTimeout(function () { validationPassActive = false; }, 0);

        // The browser may nudge-scroll to bring the field into view; keep the
        // bubble alive and tracking through that instead of self-hiding on it.
        suppressScrollHideUntil = Date.now() + 400;

        render(el, el.validationMessage);

        // Hide on the first edit. checkValidity() here fires another invalid
        // event for a still-short value and reopens the bubble while typing.
        el.addEventListener('input', hideOnEdit);
        el.addEventListener('blur', hide, { once: true });
    }, true);
})();
