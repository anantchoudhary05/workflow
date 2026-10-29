// <button data-print> opens the browser print dialog (print stylesheet hides .no-print).
export function initPrint() {
    document.querySelectorAll('[data-print]').forEach((button) => {
        button.addEventListener('click', () => window.print());
    });
}
