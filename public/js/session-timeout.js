(() => {
    const root = document.getElementById('sessionTimeoutModal');
    if (!root) return;

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    const lifetimeMs = Number(root.dataset.lifetimeSeconds) * 1000;
    const warningMs = Number(root.dataset.warningSeconds) * 1000;
    const heartbeatIntervalMs = 5 * 60 * 1000;
    const recentActivityMs = 2 * 60 * 1000;
    const countdown = document.getElementById('sessionTimeoutCountdown');
    const continueButton = document.getElementById('sessionTimeoutContinue');
    const signOutButton = document.getElementById('sessionTimeoutSignOut');
    const errorMessage = document.getElementById('sessionTimeoutError');
    let lastServerActivityAt = Date.now();
    let lastUserActivityAt = Date.now();
    let lastHeartbeatAt = Date.now();
    let modal = null;
    let requestInFlight = false;

    const getModal = () => {
        if (!modal && window.bootstrap?.Modal) {
            modal = bootstrap.Modal.getOrCreateInstance(root, {
                backdrop: 'static',
                keyboard: false,
            });
        }
        return modal;
    };

    const markActivity = () => {
        lastUserActivityAt = Date.now();
    };

    ['pointerdown', 'keydown', 'input', 'scroll', 'touchstart'].forEach(eventName => {
        document.addEventListener(eventName, markActivity, { passive: true });
    });

    const refreshSession = async () => {
        if (requestInFlight || !csrfToken) return false;
        requestInFlight = true;
        continueButton.disabled = true;
        errorMessage.classList.add('d-none');

        try {
            const response = await fetch(root.dataset.keepAliveUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: '{}',
            });

            if (response.status === 401 || response.status === 419) {
                window.location.assign(root.dataset.loginUrl);
                return false;
            }

            if (!response.ok) throw new Error('Session refresh failed.');

            lastServerActivityAt = Date.now();
            lastHeartbeatAt = lastServerActivityAt;
            getModal()?.hide();
            return true;
        } catch (error) {
            errorMessage.textContent = 'PRISM could not refresh your session. Save your work and try again.';
            errorMessage.classList.remove('d-none');
            return false;
        } finally {
            requestInFlight = false;
            continueButton.disabled = false;
        }
    };

    const updateCountdown = () => {
        const now = Date.now();
        const remainingMs = lifetimeMs - (now - lastServerActivityAt);

        if (remainingMs <= 0) {
            window.location.assign(root.dataset.loginUrl);
            return;
        }

        if (remainingMs <= warningMs) {
            const remainingSeconds = Math.max(0, Math.ceil(remainingMs / 1000));
            const minutes = Math.floor(remainingSeconds / 60);
            const seconds = String(remainingSeconds % 60).padStart(2, '0');
            countdown.textContent = `${minutes}:${seconds}`;
            getModal()?.show();
            return;
        }

        if (now - lastHeartbeatAt >= heartbeatIntervalMs
            && now - lastUserActivityAt <= recentActivityMs) {
            refreshSession();
        }
    };

    continueButton.addEventListener('click', refreshSession);
    signOutButton.addEventListener('click', () => {
        document.getElementById('logoutBtn')?.click();
    });
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) updateCountdown();
    });

    window.setInterval(updateCountdown, 1000);
})();
