// Submits the parent form when a [data-autosubmit] control changes (used by filter bars).
export function initAutosubmit() {
    document.addEventListener('change', (e) => {
        if (e.target.matches('[data-autosubmit]')) e.target.form?.requestSubmit();
    });
}
