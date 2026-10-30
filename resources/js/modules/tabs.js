// Accessible in-page tabs (WAI-ARIA tabs pattern). Markup: [data-tabs] container with [role=tab][data-tab] buttons and
// [role=tabpanel][data-tab-panel] panels (nested tab sets are left alone). The server picks the default tab; the last tab
// chosen is remembered for the browser session. A tab is opened automatically when the URL #hash points inside one of
// its panels, or when a panel holds a dialog the server re-opens after a validation error.
export function initTabs() {
    document.querySelectorAll('[data-tabs]').forEach((root) => {
        const key = `tabs:${root.dataset.tabs}`;
        const own = (el) => el.closest('[data-tabs]') === root;
        const tabs = [...root.querySelectorAll('[role="tab"][data-tab]')].filter(own);
        const panels = [...root.querySelectorAll('[role="tabpanel"][data-tab-panel]')].filter(own);
        const select = (name, focus = false) => {
            tabs.forEach((tab) => {
                const active = tab.dataset.tab === name;
                tab.setAttribute('aria-selected', active ? 'true' : 'false');
                tab.tabIndex = active ? 0 : -1;
                if (active && focus) tab.focus();
                if (active) reveal(tab);
            });
            edges();
            panels.forEach((panel) => { panel.hidden = panel.dataset.tabPanel !== name; });
        };
        const remember = (name) => { try { sessionStorage.setItem(key, name); } catch { /* storage unavailable */ } };
        const panelOf = (el) => panels.find((p) => p.contains(el));

        // A tab row wider than its card scrolls sideways; fade the edge that hides more tabs and keep the chosen tab in view.
        const list = tabs[0]?.closest('[role="tablist"]');
        const edges = () => {
            if (!list) return;
            const max = list.scrollWidth - list.clientWidth;
            list.toggleAttribute('data-more-start', max > 1 && list.scrollLeft > 1);
            list.toggleAttribute('data-more-end', max > 1 && list.scrollLeft < max - 1);
        };
        const reveal = (tab) => {
            if (!list || list.scrollWidth <= list.clientWidth) return;
            const left = tab.offsetLeft - list.offsetLeft;
            if (left < list.scrollLeft + 24) list.scrollLeft = Math.max(0, left - 24);
            else if (left + tab.offsetWidth > list.scrollLeft + list.clientWidth - 24) list.scrollLeft = left + tab.offsetWidth - list.clientWidth + 24;
        };
        if (list) {
            list.addEventListener('scroll', edges, { passive: true });
            if ('ResizeObserver' in window) new ResizeObserver(edges).observe(list);
            else window.addEventListener('resize', edges);
        }

        let saved = null;
        try { saved = sessionStorage.getItem(key); } catch { /* storage unavailable */ }
        if (saved && tabs.some((t) => t.dataset.tab === saved)) select(saved);

        // Deep links and failed dialog forms win over the remembered tab.
        const target = location.hash.length > 1 ? document.getElementById(decodeURIComponent(location.hash.slice(1))) : null;
        const forced = (target && panelOf(target)) || panelOf(root.querySelector('dialog[data-open]'));
        if (forced) select(forced.dataset.tabPanel);

        tabs.forEach((tab, i) => {
            tab.addEventListener('click', () => {
                select(tab.dataset.tab);
                remember(tab.dataset.tab);
            });
            tab.addEventListener('keydown', (e) => {
                const moves = { ArrowRight: i + 1, ArrowLeft: i - 1, Home: 0, End: tabs.length - 1 };
                if (!(e.key in moves)) return;
                e.preventDefault();
                const next = tabs[(moves[e.key] + tabs.length) % tabs.length];
                select(next.dataset.tab, true);
                remember(next.dataset.tab);
            });
        });
    });
}
