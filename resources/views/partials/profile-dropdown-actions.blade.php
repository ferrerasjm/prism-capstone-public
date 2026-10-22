<div class="{{ $containerClass }}">
    <button type="button" class="{{ $actionClass }}"
            onclick="window.location.href='{{ route('dashboard.profile') }}'">
        <i class="bi bi-person-circle" aria-hidden="true"></i>
        <span>View Profile</span>
    </button>
    <button type="button" class="{{ $actionClass }}"
            onclick="window.location.href='{{ route('account.password') }}'">
        <i class="bi bi-key" aria-hidden="true"></i>
        <span>Change Password</span>
    </button>
    <button type="button" class="{{ $actionClass }} profile-signout-action" id="{{ $signOutId }}">
        <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
        <span>Sign Out</span>
    </button>
</div>
