// Sidebar.
//   Mobile: [data-sidebar-toggle] slides #sidebar in/out over an overlay.
//   Desktop: [data-sidebar-collapse] switches to an icon rail; the choice is remembered in localStorage.
const KEY = 'workflow.sidebar';

export function initSidebar() {
    const sidebar = document.getElementById('sidebar');
    if (!sidebar) return;
    const set = (open) => {
        sidebar.dataset.open = open ? 'true' : 'false';
        document.querySelectorAll('[data-sidebar-overlay]').forEach((o) => (o.hidden = !open));
        document.querySelectorAll('[data-sidebar-toggle]').forEach((b) => b.setAttribute('aria-expanded', String(open)));
    };
    document.querySelectorAll('[data-sidebar-toggle]').forEach((b) =>
        b.addEventListener('click', () => set(sidebar.dataset.open !== 'true')),
    );
    document.addEventListener('keydown', (e) => e.key === 'Escape' && set(false));

    const root = document.documentElement;
    const collapse = (collapsed) => {
        root.dataset.sidebar = collapsed ? 'collapsed' : 'expanded';
        document.querySelectorAll('[data-sidebar-collapse]').forEach((b) => {
            b.setAttribute('aria-pressed', String(collapsed));
            b.setAttribute('aria-label', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
        });
        try {
            localStorage.setItem(KEY, root.dataset.sidebar);
        } catch {
            /* storage unavailable (private mode) */
        }
    };
    collapse(root.dataset.sidebar === 'collapsed');
    document.querySelectorAll('[data-sidebar-collapse]').forEach((b) =>
        b.addEventListener('click', () => collapse(root.dataset.sidebar !== 'collapsed')),
    );
}
