// Repeating form rows (e.g. quotation line items).
//   <div data-repeater> … <template data-repeater-template> row with __INDEX__ … </template>
//   <div data-repeater-rows> rows </div> <button data-repeater-add> ; rows have [data-repeater-remove]
export function initRepeaters() {
    document.querySelectorAll('[data-repeater]').forEach((wrap) => {
        const template = wrap.querySelector('template[data-repeater-template]');
        const rows = wrap.querySelector('[data-repeater-rows]');
        let index = rows.children.length;
        wrap.addEventListener('click', (e) => {
            if (e.target.closest('[data-repeater-add]')) {
                rows.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(index++)));
            }
            const remove = e.target.closest('[data-repeater-remove]');
            if (remove && rows.children.length > 1) remove.closest('[data-repeater-row]').remove();
        });
    });
}
