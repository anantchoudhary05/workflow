// Native <dialog> helpers and a confirm step for destructive forms.
//   <button data-dialog-open="id">        opens dialog#id
//   <button data-dialog-close>            closes the enclosing dialog
//   <form data-confirm="Message">         asks before submitting (uses dialog#confirm-dialog)
export function initDialogs() {
    document.addEventListener('click', (e) => {
        const opener = e.target.closest('[data-dialog-open]');
        if (opener) {
            document.getElementById(opener.dataset.dialogOpen)?.showModal();
            return;
        }
        if (e.target.closest('[data-dialog-close]')) e.target.closest('dialog')?.close();
    });

    // Clicking the backdrop (outside the dialog box) closes it, like Escape.
    document.addEventListener('mousedown', (e) => {
        if (e.target instanceof HTMLDialogElement && e.target.classList.contains('modal') && e.target.open) {
            const r = e.target.getBoundingClientRect();
            const inside = e.clientX >= r.left && e.clientX <= r.right && e.clientY >= r.top && e.clientY <= r.bottom;
            if (!inside) e.target.close();
        }
    });

    // Re-open the dialog whose form failed validation (the server marks only that one with data-open).
    const failed = document.querySelector('dialog[data-open]');
    if (failed && !failed.open) {
        failed.showModal();
        failed.querySelector('[aria-invalid="true"]')?.focus();
    }

    // Show a busy state on submit so forms are not submitted twice.
    document.addEventListener('submit', (e) => {
        const btn = e.submitter ?? e.target.querySelector('button[type="submit"], button:not([type])');
        if (!btn || e.target.method === 'dialog' || e.target.method === 'get') return;
        // Runs after every other submit listener, so a cancelled submit (e.g. the confirm step) is left alone.
        setTimeout(() => {
            if (e.defaultPrevented) return;
            btn.disabled = true;
            btn.setAttribute('aria-busy', 'true');
        }, 0);
    });
    window.addEventListener('pageshow', () =>
        document.querySelectorAll('button[aria-busy="true"]').forEach((b) => {
            b.disabled = false;
            b.removeAttribute('aria-busy');
        }),
    );

    const confirmDialog = document.getElementById('confirm-dialog');
    document.addEventListener('submit', (e) => {
        const form = e.target;
        if (!form.dataset.confirm || form.dataset.confirmed || !confirmDialog) return;
        e.preventDefault();
        confirmDialog.querySelector('[data-confirm-message]').textContent = form.dataset.confirm;
        confirmDialog.returnValue = '';
        confirmDialog.showModal();
        confirmDialog.addEventListener(
            'close',
            () => {
                if (confirmDialog.returnValue !== 'ok') return;
                form.dataset.confirmed = '1';
                form.requestSubmit(e.submitter ?? undefined);
            },
            { once: true },
        );
    });
}
