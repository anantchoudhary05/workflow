// Show/hide toggles: <button data-disclosure aria-controls="id" aria-expanded="false"> reveals or hides #id.
// Optional data-label-open / data-label-closed swap the text of a [data-disclosure-label] child.
export function initDisclosure() {
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-disclosure]');
        if (!btn) return;
        const target = document.getElementById(btn.getAttribute('aria-controls'));
        if (!target) return;
        const open = btn.getAttribute('aria-expanded') !== 'true';
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        target.hidden = !open;
        const label = btn.querySelector('[data-disclosure-label]');
        const text = open ? btn.dataset.labelOpen : btn.dataset.labelClosed;
        if (label && text) label.textContent = text;
    });
}
