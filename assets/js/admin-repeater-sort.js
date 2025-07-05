// jQuery UI sortable for redirect repeater drag & drop
jQuery(function($) {
    if ($.fn.sortable) {
        $('#redirection-repeater .redirect-repeaters').sortable({
            handle: '.repeater-drag-handle',
            items: '> .redirect-repeater',
            axis: 'y',
            update: function(event, ui) {
                // Update all repeater_index hidden fields to reflect new order
                $('#redirection-repeater .redirect-repeater').each(function(i) {
                    $(this).find('.repeater-index-input').val(i);
                });
            }
        });
    }
});
