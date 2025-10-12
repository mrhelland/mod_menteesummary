/**
 * Display feedback modals near click position and narrower on desktop.
 *
 * @module mod_menteesummary/bootstrapmodals
 */
define(['jquery', 'core/modal', 'core/modal_events', 'core/notification'],
function($, Modal, ModalEvents, Notification) {

    let lastClick = {x: window.innerWidth / 2, y: window.innerHeight / 2};

    const init = () => {

        // Track last click position globally (anywhere on document)
        $(document).on('click', function(e) {
            lastClick = {x: e.clientX, y: e.clientY};
        });

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
                  $root.removeClass('modal-dialog-centered');
                  // ✳️ Make narrower on desktop
                  $dialog.css({
                      'max-width': '450px',
                      'margin': '0',
                      'position': 'fixed'
                  });

                  // ✳️ Position near click (stay within viewport)
                  const modalWidth  = 450;
                  const modalHeight = 300;

                  const left = Math.min(
                      Math.max(lastClick.x - modalWidth / 2, 20),
                      window.innerWidth - modalWidth - 20
                  );
                  const top = Math.min(
                      Math.max(lastClick.y - modalHeight / 3, 20),
                      window.innerHeight - modalHeight - 20
                  );

                  $dialog.css({
                      left: `${left}px`,
                      top: `${top}px`
                  });

                  $dialog.css({ opacity: 0, transform: 'scale(0.9)' });
                      setTimeout(() => {
                          $dialog.css({
                              transition: 'opacity 0.2s ease, transform 0.2s ease',
                              opacity: 1,
                              transform: 'scale(1)'
                          });
                      }, 10);


                  // ✅ Keep Moodle backdrop fully functional
                  modal.show();
                  modal.getRoot().on(ModalEvents.hidden, () => modal.destroy());
              }).catch(err => {
                Notification.exception(err);
            });
        });
    };

    return { init: init };
});








