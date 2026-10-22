<div class="privacy-modal">
    <div class="privacy-modal-header">
        <span class="privacy-modal-header-icon"><i class="bi bi-shield-lock-fill" aria-hidden="true"></i></span>
        <div>
            <p class="privacy-modal-title">PRISM Privacy Notice</p>
            <p class="privacy-modal-subtitle">Please read and accept before continuing</p>
        </div>
    </div>

    <div class="privacy-modal-body">
        @include('auth.partials.privacy-notice-body')
    </div>

    <div class="privacy-modal-footer">
        <form id="privacyForm" class="privacy-modal-actions">
            <div class="privacy-check-row">
                <input type="checkbox" id="privacyAccepted" value="1">
                <label for="privacyAccepted">I have read and agree to the Data Privacy Policy</label>
            </div>
            <button type="submit" class="prism-btn prism-btn-primary" id="privacyAcceptBtn" disabled>I Accept</button>
        </form>
    </div>
</div>
