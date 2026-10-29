// [data-dropdown] wraps a button[data-dropdown-trigger] and a [data-dropdown-menu].
export function initDropdowns() {
    const closeAll = (except) =>
        document.querySelectorAll('[data-dropdown-menu]').forEach((m) => {
            if (m !== except) {
                m.hidden = true;
                m.closest('[data-dropdown]')?.querySelector('[data-dropdown-trigger]')?.setAttribute('aria-expanded', 'false');
            }
        });

    document.addEventListener('click', (e) => {
        const trigger = e.target.closest('[data-dropdown-trigger]');
        if (trigger) {
            const menu = trigger.closest('[data-dropdown]').querySelector('[data-dropdown-menu]');
            closeAll(menu);
            menu.hidden = !menu.hidden;
            trigger.setAttribute('aria-expanded', String(!menu.hidden));
            return;
        }
        if (!e.target.closest('[data-dropdown-menu]')) closeAll();
    });
    document.addEventListener('keydown', (e) => e.key === 'Escape' && closeAll());
}
