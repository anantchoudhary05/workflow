// Ctrl/Cmd+K focuses the global search box.
export function initShortcuts() {
    document.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
            const input = document.getElementById('global-search');
            if (!input) return;
            e.preventDefault();
            input.focus();
            input.select();
        }
    });
}
