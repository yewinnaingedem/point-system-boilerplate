/**
 * Small form helpers used across admin pages.
 */
export function initForms() {
    // Show the chosen file name in Bootstrap's custom file input.
    $(document).on('change', '.custom-file-input', function () {
        const name = this.files.length ? this.files[0].name : $(this).data('placeholder');
        $(this).next('.custom-file-label').text(name);
    });

    // Show / hide a password field: <button data-toggle-password="#password">.
    $(document).on('click', '[data-toggle-password]', function () {
        const $input = $($(this).data('toggle-password'));
        const show = $input.attr('type') === 'password';
        $input.attr('type', show ? 'text' : 'password');
        $(this).find('i').toggleClass('fa-eye', !show).toggleClass('fa-eye-slash', show);
    });

    // Tick / untick every checkbox inside a card: <button data-toggle-all>.
    $(document).on('click', '[data-toggle-all]', function () {
        const $boxes = $(this).closest('.card').find('input[type=checkbox]');
        const allChecked = $boxes.length === $boxes.filter(':checked').length;
        $boxes.prop('checked', !allChecked);
    });

    // Keep a colour picker and its hex text box in sync: <input type=color data-sync="#field">.
    $(document).on('input', '[data-sync]', function () {
        $($(this).data('sync')).val(this.value);
    });
    $(document).on('input', '[data-synced-by]', function () {
        if (/^#[0-9a-f]{6}$/i.test(this.value)) {
            $($(this).data('synced-by')).val(this.value);
        }
    });

    // Picking a date in a period filter switches its period select to "custom":
    // <input type="date" data-period-custom="#period-select">.
    $(document).on('change', '[data-period-custom]', function () {
        $($(this).data('period-custom')).val('custom').trigger('change');
    });

    // Ask before submitting: <form data-confirm="Are you sure?">.
    $(document).on('submit', 'form[data-confirm]', function (event) {
        if (!window.confirm(this.dataset.confirm)) {
            event.preventDefault();
        }
    });

    // Submit filter forms when a select changes: <select data-autosubmit>.
    $(document).on('change', '[data-autosubmit]', function () {
        this.form.submit();
    });
}
