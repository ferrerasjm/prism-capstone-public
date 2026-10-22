<script>
(() => {
    // Use the shared dependency-free animation and timeout here too; this partial is also
    // included on the login page, which deliberately does not load bootstrap.bundle.min.js.
    const showToast = (type, message) => window.PrismToast?.show(message, type);

    const checkbox = document.getElementById('privacyAccepted');
    const acceptBtn = document.getElementById('privacyAcceptBtn');
    const form = document.getElementById('privacyForm');

    if (!checkbox || !acceptBtn || !form) return;

    checkbox.addEventListener('change', () => {
        acceptBtn.disabled = !checkbox.checked;
    });

    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (!checkbox.checked) return;

        acceptBtn.disabled = true;
        acceptBtn.textContent = 'Please wait...';

        try {
            const response = await fetch('{{ route('privacy.accept') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ accepted: '1' }),
            });
            const data = await response.json();

            if (!response.ok) {
                showToast('danger', data.message || 'Something went wrong. Please try again.');
                acceptBtn.disabled = false;
                acceptBtn.textContent = 'I Accept';
                if (data.redirect) window.location.href = data.redirect;
                return;
            }

            window.location.href = data.redirect;
        } catch (error) {
            showToast('danger', 'Network error. Please try again.');
            acceptBtn.disabled = false;
            acceptBtn.textContent = 'I Accept';
        }
    });
})();
</script>
