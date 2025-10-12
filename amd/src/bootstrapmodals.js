/**
 * Display feedback modals using Moodle's default centered position.
 *
 * @module mod_menteesummary/bootstrapmodals
 */
define(['jquery', 'core/modal', 'core/modal_events', 'core/notification'],
function($, Modal, ModalEvents, Notification) {

    const init = () => {

        // Handle feedback button clicks
        $(document).on('click', '[data-feedback-target]', function(e) {
            e.preventDefault();

            const target = $(this).attr('data-feedback-target');
            const title  = $(this).data('title') || 'Feedback';
            const $content = $(target);

            if (!$content.length) {
                return;
            }

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
                const $root = modal.getRoot();
                const $dialog = $root.find('.modal-dialog');

                // ✅ Restore Moodle’s default centering and sizing
                $root.addClass('modal-dialog-centered');
                $dialog.css({
                    'max-width': '',   // remove fixed width
                    'margin': '',      // use Bootstrap defaults
                    'position': '',    // reset from fixed
                    'left': '',        // reset any manual position
                    'top': '',         // reset any manual position
                    'transform': '',   // reset any custom transform
                    'opacity': ''      // reset any fade tweak
                });

                modal.show();
                modal.getRoot().on(ModalEvents.hidden, () => modal.destroy());
            }).catch(err => {
                Notification.exception(err);
            });
        });
    };

    return { init: init };
});









