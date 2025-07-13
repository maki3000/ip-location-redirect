/**
 * Handles drag and drop sorting for the redirect repeater items
 * on the admin settings page using jQuery UI Sortable.
 *
 * @package IP_Location_Redirect
 */

// jQuery UI sortable for redirect repeater drag & drop
jQuery(function($) {
    if ($.fn.sortable) {
        $('#redirection-repeater .redirect-repeaters').sortable({
            handle: '.repeater-drag-handle',
            items: '> .redirect-repeater',
            axis: 'y',
            /**
             * Updates the hidden repeater index fields after a drag and drop sort event.
             *
             * @param {Event} event The event object.
             * @param {Object} ui The UI object from jQuery UI Sortable.
             */
            update: function(event, ui) {
                // Update all repeater_index hidden fields to reflect new order
                $('#redirection-repeater .redirect-repeater').each(function(i) {
                    $(this).find('.repeater-index-input').val(i);
                    // Also update the name attribute to ensure correct data submission order
                    $(this).find('.repeater-index-input').attr('name', `repeater_index[${i}]`);
                });
                // Call updateRepeaterActions from admin-script.js to update other fields' names/ids
                if (typeof updateRepeaterActions === 'function') {
                    updateRepeaterActions();
                }
            }
        });
    }
});
