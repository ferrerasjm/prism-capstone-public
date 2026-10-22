/**
 * PRISM shared front-end helpers: page-navigation loader, a small
 * interval-based polling utility, and wiring for elements that opt into
 * live (no full refresh) updates via data attributes.
 *
 * Loaded once per layout, after auto-submit-forms.js. No dependencies.
 */
(function () {
    'use strict';

    /* Page-navigation loader: cosmetic progress bar, never blocks navigation. */
    function initPageLoader() {
        var bar = document.createElement('div');
        bar.className = 'prism-page-loader';
        bar.setAttribute('aria-hidden', 'true');
        document.body.appendChild(bar);

        var hideTimer = null;

        function start() {
            clearTimeout(hideTimer);
            bar.classList.remove('is-done');
            bar.classList.remove('is-active');
            // Force reflow so the entrance transition re-triggers.
            void bar.offsetWidth;
            bar.classList.add('is-active');
        }

        function stop() {
            clearTimeout(hideTimer);
            bar.classList.remove('is-active');
            bar.classList.add('is-done');
            hideTimer = setTimeout(function () {
                bar.classList.remove('is-done');
            }, 400);
        }

        window.addEventListener('pagehide', function () {
            bar.classList.remove('is-active');
            bar.classList.add('is-done');
        });

        function shouldSkipLink(link) {
            if (!link || !link.href) return true;
            if (link.target && link.target !== '' && link.target !== '_self') return true;
            if (link.hasAttribute('download')) return true;
            if (link.dataset.noLoader !== undefined) return true;
            var url;
            try { url = new URL(link.href, window.location.href); } catch (e) { return true; }
            if (url.origin !== window.location.origin) return true;
            if (url.pathname === window.location.pathname && url.hash) return true; // in-page anchor
            if (link.href.indexOf('javascript:') === 0) return true;
            return false;
        }

        document.addEventListener('click', function (event) {
            if (event.defaultPrevented || event.button !== 0) return;
            if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
            var link = event.target.closest('a');
            if (shouldSkipLink(link)) return;
            start();
        }, true);

        document.addEventListener('submit', function (event) {
            var form = event.target;
            if (!(form instanceof HTMLFormElement)) return;
            if (form.dataset.noLoader !== undefined) return;
            // A form targeting a new tab (e.g. print/preview) leaves the current
            // page in place, so there's no navigation for the loader to track.
            if (form.target && form.target !== '_self') return;

            // Other listeners (e.g. login/reCAPTCHA validation) may still cancel
            // the submit after this runs, so wait until dispatch completes.
            setTimeout(function () {
                if (!event.defaultPrevented) start();
            }, 0);
        }, true);

        window.PrismPageLoader = { start: start, stop: stop };
    }

    /* Lightweight polling helper: PrismPoll.watch(url, { interval, onData, onError }). */
    function watch(url, options) {
        options = options || {};
        var interval = options.interval || 20000;
        var onData = typeof options.onData === 'function' ? options.onData : function () {};
        var onError = typeof options.onError === 'function' ? options.onError : function () {};
        var fetchOptions = options.fetchOptions || {};

        var stopped = false;
        var timer = null;
        var failures = 0;

        function schedule(delay) {
            if (stopped) return;
            clearTimeout(timer);
            timer = setTimeout(tick, delay);
        }

        function tick() {
            if (stopped) return;
            if (document.hidden) {
                schedule(interval);
                return;
            }
            fetch(url, Object.assign({
                headers: Object.assign({ 'Accept': 'application/json' }, fetchOptions.headers || {}),
                cache: 'no-store',
                credentials: 'same-origin',
            }, fetchOptions))
                .then(function (response) {
                    if (!response.ok) throw new Error('HTTP ' + response.status);
                    return response.json();
                })
                .then(function (data) {
                    failures = 0;
                    onData(data);
                    schedule(interval);
                })
                .catch(function (error) {
                    failures++;
                    onError(error);
                    // Back off up to ~5x the base interval on repeated failures.
                    schedule(Math.min(interval * Math.pow(1.6, Math.min(failures, 5)), interval * 5));
                });
        }

        document.addEventListener('visibilitychange', function () {
            if (!document.hidden && !stopped) tick();
        });

        schedule(0);

        return {
            stop: function () { stopped = true; clearTimeout(timer); },
            refreshNow: function () { schedule(0); },
        };
    }

    window.PrismPoll = { watch: watch };

    /* Polls started for page content outlive it once navigation swaps <main> without a
     * reload, so keep each one next to the element it feeds and stop the orphans. */
    var pollRegistry = [];

    function registerPoll(el, handle) {
        pollRegistry.push({ el: el, handle: handle });
    }

    function stopDetachedPolls() {
        pollRegistry = pollRegistry.filter(function (entry) {
            if (document.contains(entry.el)) return true;
            entry.handle.stop();
            return false;
        });
    }

    /* Declarative wiring: [data-live-badge][data-count-url] elements get polled and their text/visibility kept in sync. */
    function initLiveBadges(root) {
        (root || document).querySelectorAll('[data-live-badge][data-count-url]').forEach(function (el) {
            var url = el.getAttribute('data-count-url');
            var interval = parseInt(el.getAttribute('data-interval'), 10) || 20000;
            var dismissKey = 'prism:live-badge-dismissed:' + url;

            function storedDismissedCount() {
                try {
                    return Number(sessionStorage.getItem(dismissKey)) || 0;
                } catch (error) {
                    return 0;
                }
            }

            function rememberDismissedCount(count) {
                try {
                    sessionStorage.setItem(dismissKey, String(Math.max(0, count)));
                } catch (error) {
                }
            }

            function currentCount() {
                var fromData = Number(el.getAttribute('data-current-count'));
                if (!isNaN(fromData)) return fromData;
                return parseInt(el.textContent, 10) || 0;
            }

            function renderCount(count) {
                var cap = parseInt(el.getAttribute('data-cap'), 10) || 9;
                el.setAttribute('data-current-count', String(count));
                el.textContent = count > cap ? (cap + '+') : String(count);
                // Inline style (not [hidden]) so it wins over badges' `display:flex` CSS class.
                el.style.display = count === 0 || count <= storedDismissedCount() ? 'none' : '';
            }

            renderCount(currentCount());

            registerPoll(el, watch(url, {
                interval: interval,
                onData: function (data) {
                    var count = Number(data && data.count) || 0;
                    var prev = Number(el.getAttribute('data-prev-count'));
                    el.setAttribute('data-prev-count', String(count));
                    renderCount(count);
                    if (!isNaN(prev) && count > prev) {
                        el.dispatchEvent(new CustomEvent('prism:live-badge-increase', { bubbles: true, detail: { count: count, previous: prev } }));
                    }
                },
            }));

            el.addEventListener('prism:live-badge-dismiss', function () {
                var count = currentCount();
                rememberDismissedCount(count);
                renderCount(count);
            });
        });
    }

    /* Declarative wiring: [data-live-watch][data-status-url] reveals a sibling [data-live-banner] when the fingerprint changes, instead of auto-reloading. */
    function initLiveWatchers(root) {
        (root || document).querySelectorAll('[data-live-watch][data-status-url]').forEach(function (container) {
            var url = container.getAttribute('data-status-url');
            var interval = parseInt(container.getAttribute('data-interval'), 10) || 15000;
            var fingerprintField = container.getAttribute('data-fingerprint-field') || 'updated_at';
            var currentFingerprint = container.getAttribute('data-fingerprint');
            var banner = container.querySelector('[data-live-banner]');

            var pollHandle = watch(url, {
                interval: interval,
                onData: function (data) {
                    var next = data ? data[fingerprintField] : null;
                    if (next && currentFingerprint && next !== currentFingerprint && banner) {
                        banner.hidden = false;
                        pollHandle.stop();
                    }
                },
            });

            registerPoll(container, pollHandle);

            if (banner) {
                var refreshBtn = banner.querySelector('[data-live-refresh]');
                if (refreshBtn) {
                    refreshBtn.addEventListener('click', function () {
                        window.location.reload();
                    });
                }
            }
        });
    }

    /* Declarative wiring: [data-ajax-table] intercepts pagination/filter links and fetches
     * just the [data-ajax-table-content] fragment (server side checks $request->ajax(), e.g.
     * Officer\SanctionController::index). URL kept in sync via pushState/popstate. */
    function initAjaxTables(root) {
        var scope = root || document;
        scope.querySelectorAll('[data-ajax-table]').forEach(function (container) {
            var contentWrap = container.querySelector('[data-ajax-table-content]') || container;

            function loadUrl(url, pushState) {
                container.classList.add('is-loading');
                fetch(url, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
                    credentials: 'same-origin',
                })
                    .then(function (response) {
                        if (!response.ok) throw new Error('HTTP ' + response.status);
                        return response.text();
                    })
                    .then(function (html) {
                        contentWrap.innerHTML = html;
                        stopDetachedPolls();
                        if (pushState !== false) {
                            window.history.pushState({ prismAjaxTable: container.id, url: url }, '', url);
                        }
                    })
                    .catch(function () {
                        // Fall back to a full-page navigation rather than leaving the table stuck on a spinner.
                        window.location.href = url;
                    })
                    .finally(function () {
                        container.classList.remove('is-loading');
                        if (window.PrismPageLoader) window.PrismPageLoader.stop();
                    });
            }

            container.addEventListener('click', function (event) {
                if (event.defaultPrevented || event.button !== 0) return;
                if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
                var link = event.target.closest('a');
                if (!link || link.dataset.noAjax !== undefined) return;
                var href = link.getAttribute('href');
                if (!href || href.charAt(0) === '#') return;
                event.preventDefault();
                loadUrl(href);
            });

            window.addEventListener('popstate', function (event) {
                // The container is gone once in-app navigation has swapped this page out.
                if (!document.contains(container)) return;
                if (event.state && event.state.prismAjaxTable === container.id) {
                    loadUrl(event.state.url, false);
                }
            });

            scope.querySelectorAll('[data-ajax-target="' + container.id + '"]').forEach(function (link) {
                link.addEventListener('click', function (event) {
                    if (event.defaultPrevented) return;
                    event.preventDefault();
                    loadUrl(link.getAttribute('href'));
                });
            });

            scope.querySelectorAll('[data-ajax-table-filter="' + container.id + '"]').forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    event.preventDefault();
                    var params = new URLSearchParams(new FormData(form)).toString();
                    var url = form.getAttribute('action') + (params ? '?' + params : '');
                    loadUrl(url);
                });
            });
        });
    }

    /* Declarative wiring: instant notification-badge feedback. Opening a notification posts
     * to a route that redirects away, so the real form submit would tear the page down
     * before the badge update is ever seen — update the UI first, then submit after a brief
     * delay so the change is visible. Mark-all-read never navigates anywhere, so it's sent
     * as a plain background fetch instead — no page reload at all. */
    function initNotificationBadges() {
        document.addEventListener('submit', function (event) {
            var form = event.target;
            if (!(form instanceof HTMLFormElement)) return;
            var isOpenForm = form.matches('[data-notification-open-form]');
            var isMarkAllForm = form.matches('[data-notification-mark-all-form]');
            if (!isOpenForm && !isMarkAllForm) return;
            if (form.dataset.notificationSubmitting) return; // already handled below, let it through
            event.preventDefault();

            // The urgent-notification popup's "Open" button isn't nested inside the header
            // dropdown, so fall back to the page's one notification badge if there's no menu.
            var menu = form.closest('[data-notification-menu]');
            var badge = menu
                ? menu.querySelector('[data-live-badge]')
                : document.querySelector('[data-live-badge]');

            if (isMarkAllForm && menu) {
                menu.querySelectorAll('.unread').forEach(function (item) {
                    item.classList.remove('unread');
                });
                menu.querySelectorAll('.notif-dot').forEach(function (dot) {
                    dot.remove();
                });
            } else {
                // Clicking a single notification marks it read (the dot clears)
                // but its unread background stays until the page actually navigates.
                var dot = form.querySelector('.notif-dot');
                if (dot) dot.remove();
            }

            if (badge) {
                var badgeCount = Number(badge.getAttribute('data-current-count'));
                if (isNaN(badgeCount)) badgeCount = parseInt(badge.textContent, 10) || 0;
                var next = isMarkAllForm ? 0 : Math.max(0, badgeCount - 1);
                badge.textContent = String(next);
                badge.setAttribute('data-current-count', String(next));
                if (next === 0) badge.style.display = 'none';
            }

            if (isMarkAllForm) {
                fetch(form.action, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    body: new FormData(form),
                    credentials: 'same-origin',
                }).catch(function () {
                    // The optimistic UI update above already reflects "read" locally; a failed
                    // request just means the server won't agree until the next real page load.
                });
                return;
            }

            form.dataset.notificationSubmitting = 'true';
            setTimeout(function () { form.requestSubmit(); }, 250);
        });

        // Opening the dropdown dismisses the count badge; items keep their unread highlight until opened.
        document.querySelectorAll('[data-notification-menu]').forEach(function (menu) {
            menu.addEventListener('toggle', function () {
                if (!menu.open) return;
                var badge = menu.querySelector('[data-live-badge]');
                if (badge) {
                    badge.dispatchEvent(new CustomEvent('prism:live-badge-dismiss', { bubbles: true }));
                }
            });
        });

        // Native <details> only toggles on its own <summary> — closing on an outside
        // click is not built in, so every [data-notification-menu] (one per layout,
        // every role) gets that wired here.
        document.addEventListener('click', function (event) {
            document.querySelectorAll('[data-notification-menu][open]').forEach(function (menu) {
                if (!menu.contains(event.target)) {
                    menu.open = false;
                }
            });
        });
    }

    /* Declarative wiring: any textarea/input with a `minlength` attribute gets a live,
     * Twitter-style circular progress counter overlaid on its bottom-right corner —
     * its `maxlength` (when present) is factored in too, once actually close to it.
     * Deliberately keyed on minlength alone: a bare maxlength shows up on all sorts of
     * unrelated short fields (OTP digit boxes, phone numbers, names) that should never
     * grow this UI. Kept in sync via delegated input/reset listeners so it also covers
     * fields inside Bootstrap modals and anything reset programmatically. */
    var LENGTH_CIRCLE_RADIUS = 11;
    var LENGTH_CIRCLE_CIRCUMFERENCE = 2 * Math.PI * LENGTH_CIRCLE_RADIUS;

    function buildLengthCircle(el) {
        var wrap = document.createElement('div');
        wrap.className = 'prism-length-wrap';
        el.parentNode.insertBefore(wrap, el);
        wrap.appendChild(el);

        var circle = document.createElement('div');
        circle.className = 'prism-length-circle';
        circle.innerHTML =
            '<svg viewBox="0 0 28 28">' +
                '<circle class="track" cx="14" cy="14" r="' + LENGTH_CIRCLE_RADIUS + '"></circle>' +
                '<circle class="progress" cx="14" cy="14" r="' + LENGTH_CIRCLE_RADIUS + '"></circle>' +
            '</svg>' +
            '<span class="prism-length-circle-label"></span>';
        wrap.appendChild(circle);

        el._prismLengthCircle = circle;
        return circle;
    }

    /* Below the minimum, the ring fills red -> orange as it approaches min. Once the
     * minimum is met, the ring just holds a plain checkmark — a maxlength (when present)
     * only becomes relevant, and starts counting down, once actually close to that cap. */
    var LENGTH_MAX_WARN_RATIO = 0.9;

    function updateLengthCounter(el) {
        var min = parseInt(el.getAttribute('minlength'), 10) || 0;
        var max = parseInt(el.getAttribute('maxlength'), 10) || 0;
        // minlength is the signal this is one of PRISM's reason/description fields —
        // a bare maxlength alone (OTP digit boxes, phone numbers, name fields, etc.)
        // should never grow this UI.
        if (!min) return;

        var circle = el._prismLengthCircle
            || (el.parentElement && el.parentElement.classList.contains('prism-length-wrap')
                ? el.parentElement.querySelector('.prism-length-circle')
                : null)
            || buildLengthCircle(el);
        el._prismLengthCircle = circle;

        var length = el.value.length;
        var underMin = min > 0 && length < min;
        var nearMax = max > 0 && (length / max) >= LENGTH_MAX_WARN_RATIO;
        var progress, label, title, near, met;

        if (underMin) {
            progress = min ? Math.min(length / min, 1) : 0;
            var stillNeeded = min - length;
            label = String(stillNeeded);
            title = stillNeeded + ' more character' + (stillNeeded === 1 ? '' : 's') + ' needed';
            near = progress >= 0.5;
            met = false;
        } else if (nearMax) {
            progress = Math.min(length / max, 1);
            var remaining = Math.max(max - length, 0);
            label = remaining === 0 ? '✓' : String(remaining);
            title = remaining === 0
                ? 'Maximum length reached'
                : remaining + ' character' + (remaining === 1 ? '' : 's') + ' left';
            near = true;
            met = remaining === 0;
        } else {
            progress = 1;
            label = '✓';
            title = 'Minimum length met';
            near = false;
            met = true;
        }

        el.classList.toggle('is-length-maxed', max > 0 && length >= max);

        var progressCircle = circle.querySelector('.progress');
        progressCircle.style.strokeDasharray = LENGTH_CIRCLE_CIRCUMFERENCE;
        progressCircle.style.strokeDashoffset = LENGTH_CIRCLE_CIRCUMFERENCE * (1 - progress);

        circle.querySelector('.prism-length-circle-label').textContent = label;
        circle.classList.toggle('is-met', met);
        circle.classList.toggle('is-near', near);
        // Feed the fast custom tooltip (prism-tooltip.js) directly rather than the
        // native title="" — otherwise the slow native bubble reappears every time
        // this runs on input and races the fast one.
        circle.setAttribute('data-prism-tooltip', title);
        circle.removeAttribute('title');
    }

    function initMinLengthCounters(root) {
        (root || document).querySelectorAll('textarea[minlength], input[minlength]').forEach(updateLengthCounter);
    }

    document.addEventListener('input', function (event) {
        var el = event.target;
        if (!(el instanceof HTMLElement) || !el.hasAttribute('minlength')) return;
        if (el.tagName !== 'TEXTAREA' && el.tagName !== 'INPUT') return;
        updateLengthCounter(el);
    });

    document.addEventListener('reset', function (event) {
        var form = event.target;
        if (!(form instanceof HTMLFormElement)) return;
        setTimeout(function () {
            form.querySelectorAll('textarea[minlength], input[minlength]').forEach(updateLengthCounter);
        }, 0);
    });

    /* Make the whole native date field open its picker, not only the calendar icon.
     * Delegation keeps the behavior consistent for every role and for fields added
     * after initial page load.
     *
     * The click toggles rather than always opening: opening on every click means a
     * click on the mm/dd/yyyy segments to type just reopens the picker over the field,
     * so a second click is passed through to the browser, which closes the picker and
     * drops the caret in the segment that was clicked. Picker state is tracked here
     * rather than inferred from focus, since a native picker can close while the input
     * stays focused. Every input type with a native picker is covered - a datetime-local
     * field (e.g. the Discipline Education program date) needs this as much as a date
     * one, and was previously left out. */
    var PICKER_FIELDS = 'input[type="date"], input[type="datetime-local"], '
        + 'input[type="month"], input[type="week"], input[type="time"]';

    function initDatePickers() {
        var openField = null;

        function forgetOpenField() {
            openField = null;
        }

        document.addEventListener('pointerdown', function (event) {
            if (event.button !== 0 || !(event.target instanceof Element)) return;

            var field = event.target.closest(PICKER_FIELDS);

            // A click anywhere else dismisses the picker on its own; forgetting the field
            // keeps the next click on it an opening click rather than a closing one.
            if (!field) {
                forgetOpenField();

                return;
            }

            if (field.disabled || field.readOnly) return;
            if (typeof field.showPicker !== 'function') return;

            if (field === openField) {
                forgetOpenField();

                return;
            }

            try {
                field.focus({ preventScroll: true });
                field.showPicker();
                openField = field;
            } catch (error) {
                // Preserve the browser's normal behavior when its native picker is
                // unavailable or cannot be opened programmatically.
                forgetOpenField();
            }
        });

        // Escape dismisses the picker, and choosing a value closes it - either way the
        // next click on the field should open it again.
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' || event.key === 'Tab') forgetOpenField();
        });

        document.addEventListener('change', function (event) {
            if (event.target === openField) forgetOpenField();
        });

        window.addEventListener('blur', forgetOpenField);
    }

    /* Declarative wiring: an in-page anchor link with [data-scroll-flash] (e.g. the
     * case stepper's steps) smooth-scrolls to its target instead of jumping, then
     * briefly flashes the target's outline so it's obvious what just came into
     * view — opt-in via the attribute so ordinary anchors are unaffected. */
    function initScrollFlashLinks() {
        document.addEventListener('click', function (event) {
            var link = event.target.closest('a[data-scroll-flash]');
            if (!link) return;

            var href = link.getAttribute('href') || '';
            var hash = href;

            // The stepper mixes bare "#anchor" links with full route URLs that carry
            // a hash (e.g. ".../cases/5?review_sanction=1#case-step-sanction"). Treat
            // the latter as an in-page scroll only when it resolves to the page we're
            // already on AND the query string matches — otherwise let the browser
            // navigate so the server can render the different sub-view.
            if (hash.charAt(0) !== '#') {
                var resolved;
                try {
                    resolved = new URL(href, window.location.href);
                } catch (e) {
                    return;
                }
                if (!resolved.hash
                    || resolved.pathname !== window.location.pathname
                    || resolved.search !== window.location.search) {
                    return;
                }
                hash = resolved.hash;
            }

            if (hash.length < 2) return;

            var target = document.getElementById(hash.slice(1));
            if (!target) return;

            event.preventDefault();
            // A collapsed <details> — the target itself (e.g. "Case Details"), or one
            // it's nested inside (e.g. a card inside the "Completed steps" history
            // disclosure) — hides its content until open; scrolling to it closed would
            // highlight nothing.
            var details = target.tagName === 'DETAILS' ? target : target.closest('details');
            if (details) details.open = true;
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });

            target.classList.remove('prism-flash-highlight');
            void target.offsetWidth; // restart the animation if the same step is clicked twice
            target.classList.add('prism-flash-highlight');
            target.addEventListener('animationend', function handler() {
                target.classList.remove('prism-flash-highlight');
                target.removeEventListener('animationend', handler);
            });
        });
    }

    /* The case stepper scrolls horizontally on narrow screens. Two things follow
     * from that: it must open on the step the officer is actually on rather than
     * at the far left, and the fact that it continues past the edge has to be
     * visible — a cut-off label is an accident, not an affordance. */
    function initCaseStepper() {
        document.querySelectorAll('.case-stepper').forEach(function (stepper) {
            var scroller = stepper.closest('.case-stepper-scroller');

            function syncEdges() {
                if (!scroller) return;
                var max = stepper.scrollWidth - stepper.clientWidth;
                scroller.classList.toggle('has-overflow-start', stepper.scrollLeft > 1);
                scroller.classList.toggle('has-overflow-end', stepper.scrollLeft < max - 1);
            }

            var focus = stepper.querySelector('.is-reviewing')
                || stepper.querySelector('.is-current')
                || stepper.querySelector('.is-closed');

            if (focus && stepper.scrollWidth > stepper.clientWidth) {
                // Centre it without scrolling the page itself, which scrollIntoView would do.
                stepper.scrollLeft = Math.max(
                    0,
                    focus.offsetLeft - (stepper.clientWidth - focus.offsetWidth) / 2
                );
            }

            syncEdges();
            stepper.addEventListener('scroll', syncEdges, { passive: true });
            window.addEventListener('resize', syncEdges);
        });
    }

    /* Landing with a #fragment after a redirect: the page's scroll container is an
     * inner element (.sdo-content), not the document, so the browser's own jump can
     * land short. Re-scroll once on boot and flash the target so the officer sees
     * where the page went. */
    function initHashLanding() {
        var hash = window.location.hash;
        if (!hash || hash.length < 2) return;

        var target = document.getElementById(hash.slice(1));
        if (!target) return;

        // The target may sit inside a collapsed disclosure (a closed-action archive,
        // or its own status editor) — scrolling to it shut would show nothing.
        var details = target.tagName === 'DETAILS' ? target : target.closest('details');
        while (details) {
            details.open = true;
            details = details.parentElement ? details.parentElement.closest('details') : null;
        }

        requestAnimationFrame(function () {
            target.scrollIntoView({ block: 'start' });
            target.classList.remove('prism-flash-highlight');
            void target.offsetWidth;
            target.classList.add('prism-flash-highlight');
            target.addEventListener('animationend', function handler() {
                target.classList.remove('prism-flash-highlight');
                target.removeEventListener('animationend', handler);
            });
        });
    }

    /* A support-action status form: the chosen status decides what the submit button
     * actually does and whether an outcome note is mandatory. Marking the textarea
     * required up front lets the browser stop an incomplete close before the round
     * trip, instead of bouncing the officer back through a server error. */
    /* Double-submit guard: a slow POST leaves the form on screen, and a second
     * impatient click files a second record. The first dispatch marks the form
     * and locks its submit buttons; anything after that is dropped. Buttons are
     * locked in a timeout so the click that triggered the submit is still part
     * of the serialized form data, and released again if the page is restored
     * from the back/forward cache. Opt out with data-allow-resubmit. */
    function lockSubmitters(form) {
        var selector = 'button[type="submit"], input[type="submit"], button:not([type])';
        var buttons = Array.prototype.slice.call(form.querySelectorAll(selector));

        // Buttons attached to the form from outside it via the form="" attribute.
        if (form.id) {
            var escaped = form.id.replace(/"/g, '\\"');
            document.querySelectorAll('[form="' + escaped + '"]').forEach(function (external) {
                if (external.type === 'submit' && buttons.indexOf(external) === -1) buttons.push(external);
            });
        }

        buttons.forEach(function (button) {
            if (button.disabled) return;
            button.dataset.submitLocked = '1';
            button.classList.add('is-submitting');
            button.disabled = true;
        });
    }

    function releaseSubmitters(form) {
        delete form.dataset.submitting;
        document.querySelectorAll('[data-submit-locked]').forEach(function (button) {
            delete button.dataset.submitLocked;
            button.classList.remove('is-submitting');
            button.disabled = false;
        });
    }

    function initSubmitOnce() {
        document.addEventListener('submit', function (event) {
            var form = event.target;
            if (!(form instanceof HTMLFormElement)) return;
            if (form.dataset.allowResubmit !== undefined) return;
            // Only writes need guarding; GET forms are filters and searches.
            if ((form.getAttribute('method') || 'get').toLowerCase() !== 'post') return;
            if (form.target && form.target !== '' && form.target !== '_self') return;

            if (form.dataset.submitting !== undefined) {
                event.preventDefault();
                event.stopImmediatePropagation();
                return;
            }

            // Later listeners may still cancel this submit, so only claim the
            // form once the dispatch has run its course uncancelled.
            setTimeout(function () {
                if (event.defaultPrevented) return;
                form.dataset.submitting = '1';
                lockSubmitters(form);
            }, 0);
        }, true);

        // Returning via the back button restores the DOM as it was mid-submit,
        // which would otherwise leave a permanently dead submit button.
        window.addEventListener('pageshow', function (event) {
            if (!event.persisted) return;
            document.querySelectorAll('form[data-submitting]').forEach(releaseSubmitters);
        });
    }

    /* Declarative wiring: [data-ajax-toast-form] submits via fetch instead of a normal
     * full-page POST. A validation failure (422 JSON) is reported as a toast and the
     * page is left exactly as the officer had it -- no navigation just to say what
     * needs fixing. Success still reloads: saving can change more of the page than the
     * form itself (status badges, a case list, a newly-raised referral card), and the
     * reload's own session flash becomes the success toast via the existing
     * partials.flash mechanism, so there is nothing extra to render here for that case.
     * A fetch failure (offline, blocked) falls back to a real submit rather than leaving
     * the officer stuck on a dead button -- that path is reported by the server the
     * classic way (session('error'), picked up by the same flash-to-toast mechanism). */
    function initAjaxToastForms() {
        document.addEventListener('submit', function (event) {
            var form = event.target;
            if (!(form instanceof HTMLFormElement)) return;
            if (!form.matches('[data-ajax-toast-form]')) return;
            event.preventDefault();

            lockSubmitters(form);

            fetch(form.getAttribute('action'), {
                method: 'POST', // Laravel method-spoofing via the form's own _method field.
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: new FormData(form),
                credentials: 'same-origin',
            })
                .then(function (response) {
                    if (response.status === 422) {
                        return response.json().then(function (data) {
                            releaseSubmitters(form);
                            if (window.PrismToast) {
                                window.PrismToast.show(
                                    data.message || 'Please check the form and try again.',
                                    'danger'
                                );
                            }
                        });
                    }
                    if (!response.ok) throw new Error('HTTP ' + response.status);
                    window.location.reload();
                })
                .catch(function () {
                    releaseSubmitters(form);
                    form.submit();
                });
        });
    }

    var ACTION_SUBMIT_LABELS = {
        planned: 'Save Changes',
        in_progress: 'Save Changes',
        completed: 'Complete Action',
        cancelled: 'Cancel Action'
    };

    function syncActionUpdater(scope) {
        var status = scope.querySelector('[data-action-status]');
        var outcome = scope.querySelector('[data-action-outcome]');
        var hint = scope.querySelector('[data-action-outcome-hint]');
        var submit = scope.querySelector('[data-action-submit]');
        if (!status) return;

        var value = status.value;
        var closing = value === 'completed' || value === 'cancelled';

        if (outcome) {
            outcome.required = closing;
            outcome.setAttribute('aria-required', closing ? 'true' : 'false');
        }
        if (hint) {
            hint.textContent = closing
                ? '(required to ' + (value === 'completed' ? 'complete' : 'cancel') + ' this action)'
                : '(optional while the action is open)';
        }
        if (submit) submit.textContent = ACTION_SUBMIT_LABELS[value] || 'Update Action';
    }

    /* "More Actions" menus (case, account, ...) are plain <details> elements -
     * clicking the summary again closes them, but a click anywhere else on the
     * page does not, since that's not something <details> does on its own. */
    function initMoreActionsMenus() {
        document.addEventListener('click', function (event) {
            document.querySelectorAll('details[class*="more-actions"][open]').forEach(function (details) {
                if (!details.contains(event.target)) details.open = false;
            });
        });

        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') return;
            document.querySelectorAll('details[class*="more-actions"][open]').forEach(function (details) {
                details.open = false;
            });
        });
    }

    function initActionUpdaters(root) {
        (root || document).querySelectorAll('[data-action-updater]').forEach(syncActionUpdater);
    }

    document.addEventListener('change', function (event) {
        var status = event.target.closest ? event.target.closest('[data-action-status]') : null;
        if (!status) return;
        var scope = status.closest('[data-action-updater]');
        if (scope) syncActionUpdater(scope);
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    function boot() {
        initPageLoader();
        initLiveBadges();
        initLiveWatchers();
        initAjaxTables();
        initNotificationBadges();
        initSubmitOnce();
        initAjaxToastForms();
        initDatePickers();
        initMinLengthCounters();
        initScrollFlashLinks();
        initCaseStepper();
        initActionUpdaters();
        initHashLanding();
        initMoreActionsMenus();
    }
})();
