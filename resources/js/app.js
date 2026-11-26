import Alpine from 'alpinejs';
import TomSelect from 'tom-select/dist/js/tom-select.complete.js';

const PT_BR = {
    no_results: () => '<div class="no-results">Nenhum resultado</div>',
    option_create: (data, escape) => `<div class="create">Adicionar <strong>${escape(data.input)}</strong>&hellip;</div>`,
};

/**
 * Generic multi-select enhancer: any <select multiple data-multiselect> becomes a
 * searchable chip picker. Without JS the native multiple select keeps working.
 */
function initMultiSelects(root = document) {
    root.querySelectorAll('select[data-multiselect]').forEach((el) => {
        if (el.tomselect) {
            return;
        }

        const inheritedClasses = [...el.classList];
        const ts = new TomSelect(el, {
            plugins: { remove_button: { title: 'Remover' } },
            placeholder: el.dataset.placeholder || '',
            maxOptions: null,
            hidePlaceholder: true,
            closeAfterSelect: false,
            // Render the menu on <body> so cards with overflow-hidden don't clip it.
            dropdownParent: 'body',
            render: { no_results: PT_BR.no_results },
        });

        // TomSelect copies the original's classes onto its wrapper; swap for our skin.
        ts.wrapper.classList.remove(...inheritedClasses);
        ts.wrapper.classList.add('mx-ts');
        ts.dropdown.classList.add('mx-ts-dropdown');

        // Keep the field one line tall: with 2+ items, show a summary instead of the chips
        // until the field is focused (then the chips open as an overlay).
        const summary = document.createElement('div');
        summary.className = 'mx-ts-summary';
        summary.setAttribute('aria-hidden', 'true');
        ts.wrapper.appendChild(summary);
        // The summary covers the chips (and their remove buttons): a click on it only opens the field.
        summary.addEventListener('mousedown', (event) => {
            event.preventDefault();
            ts.focus();
            ts.open();
        });

        const refreshSummary = () => {
            const count = ts.items.length;
            const label = count === 1
                ? el.dataset.summarySingular || 'selecionado'
                : el.dataset.summaryPlural || 'selecionados';
            summary.textContent = `${count} ${label}`;
            ts.wrapper.classList.toggle('mx-ts--many', count > 1);
        };

        refreshSummary();
        ts.on('change', refreshSummary);
        // The control grows into an overlay on focus/add/remove; keep the menu below it.
        const reposition = () => requestAnimationFrame(() => ts.positionDropdown());
        ['focus', 'dropdown_open', 'item_add', 'item_remove'].forEach((event) => ts.on(event, reposition));
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => initMultiSelects());
} else {
    initMultiSelects();
}

window.initMultiSelects = initMultiSelects;

// Alpine powers the Breeze components (user menu, mobile nav, modals, action messages).
window.Alpine = Alpine;
Alpine.start();
