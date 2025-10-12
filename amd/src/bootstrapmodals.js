/**
 * Display assignment feedback in a scrollable Moodle modal (Moodle 4.5+).
 *
 * @module mod_menteesummary/bootstrapmodals
 */
define(['jquery', 'core/modal', 'core/modal_events', 'core/notification'],
function($, Modal, ModalEvents, Notification) {

    const init = () => {
        $(document).on('click', '[data-feedback-target]', function(e) {
            e.preventDefault();

            const target = $(this).attr('data-feedback-target');
            const title  = $(this).data('title') || 'Feedback';
            const $content = $(target);

            if (!$content.length) {
                return;
            }

            // Wrap feedback in Moodle's scrollable container.
            const bodyHtml = `
                <div class="modal-scrollable p-3" style="max-height:60vh; overflow-y:auto;">
                    ${$content.html() || ''}
                </div>
            `;

            Modal.create({
                title: title,
                body: bodyHtml,
                large: true
            }).then(modal => {
                modal.show();
                modal.getRoot().on(ModalEvents.hidden, () => modal.destroy());
            }).catch(err => {
                Notification.exception(err);
            });
        });
    };

    return { init: init };
});







