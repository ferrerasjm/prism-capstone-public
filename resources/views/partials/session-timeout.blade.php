<div class="modal fade prism-modal session-timeout-modal" id="sessionTimeoutModal" tabindex="-1"
     aria-labelledby="sessionTimeoutModalTitle" aria-hidden="true"
     data-keep-alive-url="{{ route('session.keep-alive') }}"
     data-login-url="{{ route('login') }}"
     data-lifetime-seconds="{{ (int) config('session.lifetime', 480) * 60 }}"
     data-warning-seconds="{{ (int) config('session.warning', 300) }}">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="sessionTimeoutModalTitle">Session expiring soon</h2>
            </div>
            <div class="modal-body">
                <p class="mb-2">Your PRISM session will expire after extended inactivity.</p>
                <p class="prism-cell-sub mb-0">
                    Save any unfinished work. You will be signed out in
                    <strong id="sessionTimeoutCountdown">5:00</strong> unless you continue your session.
                </p>
                <p class="session-timeout-error mt-2 mb-0 d-none" id="sessionTimeoutError" role="alert"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="prism-btn prism-btn-outline" id="sessionTimeoutSignOut">Sign Out</button>
                <button type="button" class="prism-btn prism-btn-primary" id="sessionTimeoutContinue">Stay Signed In</button>
            </div>
        </div>
    </div>
</div>
<script src="{{ asset('js/session-timeout.js') }}"></script>
