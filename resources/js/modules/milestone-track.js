// Milestone journey on the project overview.
//  - The progress line fills from 0 once the track scrolls into view (static when the user prefers reduced motion).
//  - [data-ms-toggle] stops open their detail panel (one at a time); Escape or [data-ms-close] closes it again.
//  - Arrow keys move between stops; the open stop is kept in the URL (?milestone=…) so the view can be shared.
const reduceMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

export function initMilestoneTrack() {
    document.querySelectorAll('[data-ms-journey]').forEach((root) => {
        const toggles = [...root.querySelectorAll('[data-ms-toggle]')];
        const panels = [...root.querySelectorAll('[data-ms-panel]')];

        if (!reduceMotion() && 'IntersectionObserver' in window) {
            root.classList.add('is-armed');
            const io = new IntersectionObserver((entries) => {
                if (!entries.some((e) => e.isIntersecting)) return;
                // Two frames so the empty state is painted before the fill transition starts.
                requestAnimationFrame(() => requestAnimationFrame(() => root.classList.add('is-live')));
                io.disconnect();
            }, { threshold: 0.2 });
            io.observe(root);
        }

        const setUrl = (key) => {
            const url = new URL(location.href);
            if (key) url.searchParams.set('milestone', key);
            else url.searchParams.delete('milestone');
            history.replaceState(history.state, '', url);
        };

        const show = (key, { focusPanel = false } = {}) => {
            toggles.forEach((t) => t.setAttribute('aria-expanded', t.dataset.msToggle === key ? 'true' : 'false'));
            root.querySelectorAll('.ms-step, .ms-chip').forEach((el) => el.classList.toggle('is-selected', !!key && (el.dataset.msStop === key || el.dataset.msToggle === key)));
            let opened = null;
            panels.forEach((p) => {
                const open = p.dataset.msPanel === key;
                p.hidden = !open;
                if (open) opened = p;
            });
            setUrl(key);
            if (opened) {
                const r = opened.getBoundingClientRect();
                if (r.bottom > window.innerHeight || r.top < 0) {
                    opened.scrollIntoView({ behavior: reduceMotion() ? 'auto' : 'smooth', block: 'nearest' });
                }
                if (focusPanel) opened.focus({ preventScroll: true });
            }
        };

        toggles.forEach((btn, i) => {
            btn.addEventListener('click', () => {
                const open = btn.getAttribute('aria-expanded') === 'true';
                show(open ? null : btn.dataset.msToggle);
            });
            btn.addEventListener('keydown', (e) => {
                const moves = { ArrowRight: i + 1, ArrowDown: i + 1, ArrowLeft: i - 1, ArrowUp: i - 1, Home: 0, End: toggles.length - 1 };
                if (!(e.key in moves)) return;
                e.preventDefault();
                toggles[(moves[e.key] + toggles.length) % toggles.length].focus();
            });
        });

        const closeTo = (panel) => {
            const key = panel?.dataset.msPanel;
            show(null);
            if (key) document.getElementById(`ms-node-${key}`)?.focus();
        };
        root.addEventListener('click', (e) => {
            const close = e.target.closest('[data-ms-close]');
            if (close) closeTo(close.closest('[data-ms-panel]'));
        });
        root.addEventListener('keydown', (e) => {
            if (e.key !== 'Escape') return;
            const panel = panels.find((p) => !p.hidden);
            if (panel && (panel.contains(e.target) || e.target.closest('[data-ms-toggle]'))) closeTo(panel);
        });

        const initial = toggles.find((t) => t.getAttribute('aria-expanded') === 'true');
        if (initial) root.querySelectorAll(`[data-ms-stop="${initial.dataset.msToggle}"]`).forEach((el) => el.classList.add('is-selected'));
    });
}
