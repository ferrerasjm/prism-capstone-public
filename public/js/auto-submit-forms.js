document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form[data-auto-submit]').forEach((form) => {
        form.querySelectorAll('select, input[type="date"], input[type="checkbox"], input[type="radio"]').forEach((control) => {
            control.addEventListener('change', () => form.requestSubmit());
        });
    });
});
