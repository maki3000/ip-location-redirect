jQuery(($) => {

    // Counter for repeater items
    let repeaterIndex = 0;

    const showHideRepeaterRemoveButton = function() {
        if ($('.repeater').length <= 1) {
            $('.remove-repeater').hide();
        } else {
            $('.remove-repeater').show();
        }
    }

    // Function to add a new repeater item
    const addRepeaterItem = function() {
        repeaterIndex++;

        const $repeaterItem = $('#redirection-repeater .repeater:first-child').clone();
        $repeaterItem.attr('data-index', repeaterIndex);

        // Update attributes and clear values
        $repeaterItem.find('[name^="country"]').attr('name', `country[${repeaterIndex}]`).val('');
        $repeaterItem.find('[name^="ip_action"]').attr('name', `ip_action[${repeaterIndex}]`).prop('checked', false);
        $repeaterItem.find('[name^="redirect_url"]').attr('name', `redirect_url[${repeaterIndex}]`).val('');

        $repeaterItem.find('.ip_action_from_country').attr('id', `ip_action_from_country__${repeaterIndex}`);
        $repeaterItem.find('.ip_action_from_country-label').attr('for', `ip_action_from_country__${repeaterIndex}`);
        $repeaterItem.find('.ip_action_not_from_country').attr('id', `ip_action_from_country__${repeaterIndex}`);
        $repeaterItem.find('.ip_action_not_from_country-label').attr('for', `ip_action_from_country__${repeaterIndex}`);

        // Append the new repeater item
        $('#redirection-repeater .repeaters').append($repeaterItem);

        // Update GUI
        updateSelectOptions();
        showHideRepeaterRemoveButton();
    };

    // Function to remove a repeater item
    const removeRepeaterItem = function($repeaterItem) {
        $repeaterItem.remove();

        // Update GUI
        updateSelectOptions();
        showHideRepeaterRemoveButton();
    };

    // Function to update options in the default_redirect_option select
    const updateSelectOptions = function() {
        const $select = $('#default_redirect_option');
        const selectedValue = $select.val();

        // Remove all existing options
        $select.empty();

        // Add default options
        $select.append('<option value="do_nothing">Do nothing (standard)</option>');

        // Add options based on repeater items
        $('#redirection-repeater .repeater').each(function() {
            const countryValue = $(this).find('[name^="country"]').val();
            const ipActionValue = $(this).find('[name^="ip_action"]:checked').val();
            const redirectUrlValue = $(this).find('[name^="redirect_url"]').val();

            // Add option based on repeater item values
            $select.append(`<option value="${redirectUrlValue}">Go to URL: ${redirectUrlValue}</option>`);
        });

        // Set the previously selected value
        $select.val(selectedValue);
    };

    // Event listener for adding a new repeater item
    $('#redirection-repeater').on('click', '.add-repeater', function() {
        addRepeaterItem();
    });

    // Event listener for removing a repeater item
    $('#redirection-repeater').on('click', '.remove-repeater', function() {
        const $repeaterItem = $(this).closest('.repeater');
        removeRepeaterItem($repeaterItem);
    });

    showHideRepeaterRemoveButton();

    $('.media-upload-button').on('click', function(e) {
        e.preventDefault();

        // Create a media frame
        var mediaFrame = wp.media({
            title: 'Choose or Upload Media',
            button: {
                text: 'Use this media'
            },
            multiple: false
        });

        // When an image is selected in the media frame...
        mediaFrame.on('select', function() {
            var attachment = mediaFrame.state().get('selection').first().toJSON();

            // Update the input field value with the selected image URL
            $('.media-chooser').val(attachment.url);
        });

        // Open the media frame
        mediaFrame.open();
    });

});
