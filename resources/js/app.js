import './jquery-global';
import './bootstrap';
import 'bootstrap';
import 'admin-lte';

import { initConfirmDelete } from './ui/confirm-delete';
import { initDarkMode } from './ui/dark-mode';
import { initForms } from './ui/forms';

$(() => {
    initDarkMode();
    initConfirmDelete();
    initForms();
});
