import { showToast } from './toasts';

// Task board drag & drop. Cards: [data-card][data-status-url]; columns: [data-column="status"].
// Columns with data-needs-reason prompt for a reason. The server validates every move;
// rejected moves are reverted and the server message is shown.
export function initKanban() {
    const board = document.querySelector('[data-kanban]');
    if (!board) return;
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    let dragged = null;

    board.addEventListener('dragstart', (e) => {
        const card = e.target.closest('[data-card]');
        // Only cards the server marked as movable may be dragged (links/text inside a locked card are ignored).
        if (!card || card.getAttribute('draggable') !== 'true' || !card.dataset.statusUrl) {
            dragged = null;
            return;
        }
        dragged = card;
        dragged.dataset.dragging = '';
        e.dataTransfer.effectAllowed = 'move';
    });
    board.addEventListener('dragend', () => {
        dragged?.removeAttribute('data-dragging');
        board.querySelectorAll('[data-drop-active]').forEach((c) => c.removeAttribute('data-drop-active'));
        dragged = null;
    });
    board.addEventListener('dragover', (e) => {
        const col = e.target.closest('[data-column]');
        if (!col || !dragged) return;
        e.preventDefault();
        board.querySelectorAll('[data-drop-active]').forEach((c) => c !== col && c.removeAttribute('data-drop-active'));
        col.dataset.dropActive = '';
    });
    board.addEventListener('drop', async (e) => {
        const col = e.target.closest('[data-column]');
        if (!col || !dragged) return;
        e.preventDefault();
        const card = dragged;
        const from = card.closest('[data-column]');
        col.removeAttribute('data-drop-active');
        if (from === col) return;

        let reason = null;
        if ('needsReason' in col.dataset) {
            reason = window.prompt(col.dataset.needsReason);
            if (!reason?.trim()) return;
        }

        const list = col.querySelector('[data-cards]');
        list.prepend(card);
        updateCounts(board);
        try {
            const res = await fetch(card.dataset.statusUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token },
                body: JSON.stringify({ status: col.dataset.column, reason }),
            });
            const body = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(body.message || 'The task could not be moved.');
            showToast(`Moved to ${body.label}.`, 'success');
        } catch (err) {
            from.querySelector('[data-cards]').prepend(card);
            updateCounts(board);
            showToast(err.message, 'danger');
        }
    });
}

function updateCounts(board) {
    board.querySelectorAll('[data-column]').forEach((col) => {
        const count = col.querySelector('[data-count]');
        if (count) count.textContent = col.querySelectorAll('[data-card]').length;
    });
}
