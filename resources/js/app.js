import { initSidebar } from './modules/sidebar';
import { initDropdowns } from './modules/dropdowns';
import { initDialogs } from './modules/dialogs';
import { initToasts } from './modules/toasts';
import { initShortcuts } from './modules/shortcuts';
import { initKanban } from './modules/kanban';
import { initRepeaters } from './modules/repeaters';
import { initAutosubmit } from './modules/autosubmit';
import { initPrint } from './modules/print';
import { initDocSource } from './modules/doc-source';
import { initTabs } from './modules/tabs';

document.addEventListener('DOMContentLoaded', () => {
    initSidebar();
    initDropdowns();
    initDialogs();
    initToasts();
    initShortcuts();
    initKanban();
    initRepeaters();
    initAutosubmit();
    initPrint();
    initDocSource();
    initTabs();
});
