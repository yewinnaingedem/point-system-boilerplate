import './jquery-global';
import './bootstrap';
import 'bootstrap';
import 'admin-lte';

import { initConfirmDelete } from './ui/confirm-delete';
import { initDataTables } from './ui/datatables';
import { initDarkMode } from './ui/dark-mode';
import { initForms } from './ui/forms';
import { initSelects } from './ui/select';
import { initToasts } from './ui/toasts';
import { initUiState } from './ui/ui-state';

$(() => {
    initDarkMode();
    initConfirmDelete();
    initForms();
    initSelects();
    initDataTables();
    initUiState();
    initToasts();
});
