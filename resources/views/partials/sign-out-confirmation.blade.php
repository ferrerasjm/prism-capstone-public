<div class="modal fade prism-modal" id="signOutConfirmationModal" tabindex="-1" style="display:none;"
     aria-labelledby="signOutConfirmationTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="signOutConfirmationTitle">Sign out?</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">Are you sure you want to sign out of your PRISM account?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="prism-btn prism-btn-outline" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="prism-btn prism-btn-danger" id="confirmSignOutButton">
                    Sign Out
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(() => {
    const modalElement = document.getElementById('signOutConfirmationModal');
    const confirmButton = document.getElementById('confirmSignOutButton');
    let submitting = false;

    const submitLogout = () => {
        if (submitting) return;
        submitting = true;
        confirmButton.disabled = true;
        document.getElementById('logoutForm')?.requestSubmit();
    };

    window.PrismSignOut = {
        confirm() {
            document.querySelectorAll('.sdo-profile-dropdown.open, .gso-profile-dropdown.open')
                .forEach((dropdown) => dropdown.classList.remove('open'));
            window.bootstrap?.Modal.getOrCreateInstance(modalElement).show();
        },
    };

    confirmButton.addEventListener('click', submitLogout);
})();
</script>
