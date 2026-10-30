// Client document form: switches between "Upload file" and "Add link". The hidden panel's inputs are disabled,
// so only the chosen source is submitted. The server still requires exactly one of file or url.
export function initDocSource() {
    const sync = (form) => {
        const source = form.querySelector('[data-doc-source]:checked')?.value ?? 'file';
        form.querySelectorAll('[data-source-panel]').forEach((panel) => {
            const active = panel.dataset.sourcePanel === source;
            panel.hidden = !active;
            panel.querySelectorAll('input, select, textarea').forEach((el) => { el.disabled = !active; });
        });
    };
    document.querySelectorAll('[data-doc-source-form]').forEach(sync);
    document.addEventListener('change', (e) => {
        if (e.target.matches('[data-doc-source]')) sync(e.target.form);
    });
}
