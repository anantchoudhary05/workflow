// Toast notifications. Server flashes render as [data-toast]; JS code can call showToast().
// Tones: info | success | warning | danger (adds .toast-{tone} for the coloured accent).
const TONES = ['info', 'success', 'warning', 'danger'];

export function showToast(message, tone = 'info') {
    const region = document.getElementById('toasts');
    if (!region) return;
    const safeTone = TONES.includes(tone) ? tone : 'info';
    const el = document.createElement('div');
    el.className = `toast toast-${safeTone}`;
    el.setAttribute('role', safeTone === 'danger' ? 'alert' : 'status');
    el.textContent = message;
    region.append(el);
    setTimeout(() => el.remove(), safeTone === 'danger' ? 8000 : 5000);
}

export function initToasts() {
    document.querySelectorAll('[data-toast]').forEach((el) => {
        el.addEventListener('click', () => el.remove());
        setTimeout(() => el.remove(), 6000);
    });
}
