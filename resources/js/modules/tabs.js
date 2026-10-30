// Accessible in-page tabs (WAI-ARIA tabs pattern). Markup: [data-tabs] container with [role=tab][data-tab] buttons and
// [role=tabpanel][data-tab-panel] panels. The server picks the default tab; the last tab chosen is remembered for the
// browser session so returning after adding a to-do keeps the same tab.
export function initTabs() {
    document.querySelectorAll('[data-tabs]').forEach((root) => {
        const key = `tabs:${root.dataset.tabs}`;
        const tabs = [...root.querySelectorAll('[role="tab"][data-tab]')];
        const select = (name, focus = false) => {
            tabs.forEach((tab) => {
                const active = tab.dataset.tab === name;
                tab.setAttribute('aria-selected', active ? 'true' : 'false');
                tab.tabIndex = active ? 0 : -1;
                if (active && focus) tab.focus();
            });
            root.querySelectorAll('[role="tabpanel"][data-tab-panel]').forEach((panel) => { panel.hidden = panel.dataset.tabPanel !== name; });
        };
        let saved = null;
        try { saved = sessionStorage.getItem(key); } catch { /* storage unavailable */ }
        if (saved && tabs.some((t) => t.dataset.tab === saved)) select(saved);

        tabs.forEach((tab, i) => {
            tab.addEventListener('click', () => {
                select(tab.dataset.tab);
                try { sessionStorage.setItem(key, tab.dataset.tab); } catch { /* storage unavailable */ }
            });
            tab.addEventListener('keydown', (e) => {
                const moves = { ArrowRight: i + 1, ArrowLeft: i - 1, Home: 0, End: tabs.length - 1 };
                if (!(e.key in moves)) return;
                e.preventDefault();
                const next = tabs[(moves[e.key] + tabs.length) % tabs.length];
                select(next.dataset.tab, true);
                try { sessionStorage.setItem(key, next.dataset.tab); } catch { /* storage unavailable */ }
            });
        });
    });
}
