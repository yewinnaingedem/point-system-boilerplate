/**
 * One shared Bootstrap modal (#confirm-delete-modal in the admin layout) for every delete button:
 * <button data-confirm-delete="{url}" data-title="Delete X?">.
 */
export function initConfirmDelete() {
    const $modal = $('#confirm-delete-modal');

    $(document).on('click', '[data-confirm-delete]', function () {
        const $button = $(this);
        $modal.find('form').attr('action', $button.data('confirm-delete'));
        $modal.find('[data-title]').text($button.data('title') || $modal.find('[data-title]').data('default'));
        $modal.modal('show');
    });
}
