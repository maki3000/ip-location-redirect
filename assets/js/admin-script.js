jQuery(($) => {

    // --- Dynamic show/hide and required logic for redirect modes ---
    function updateRedirectModeFields() {
        const mode = $('#default_redirect_option').val();
        // Use fieldsets for show/hide
        const $auto = $('.redirect-automatic-location');
        const $choose = $('.redirect-choose-location');
        const $footer = $('.redirect-footer-message');
        const $footerCheckbox = $('#show_footer_message');
        // All fields to toggle required
        const useGivenRequired = [
            '#redirection_title', '#redirection_text', '#current_shop_label',
            // All repeater required fields:
            '#redirection-repeater .redirect-message-before-input',
            '#redirection-repeater .redirect-message-after-input',
            '#redirection-repeater .redirect-url-input',
            '#redirection-repeater select[name^="country"]',
            '#redirection-repeater input[name^="ip_action"]'
        ];
        const showOptionsRequired = [
            '#redirection_choose_title', '#redirection_choose_info'
        ];

        if (mode === 'use_given_redirect_options') {
            $auto.fadeIn();
            $choose.fadeOut();
            useGivenRequired.forEach(sel => $(sel).attr('required', true));
            showOptionsRequired.forEach(sel => $(sel).removeAttr('required'));
        } else if (mode === 'show_redirect_options') {
            $auto.fadeOut();
            $choose.fadeIn();
            useGivenRequired.forEach(sel => $(sel).removeAttr('required'));
            showOptionsRequired.forEach(sel => $(sel).attr('required', true));
        }

        // Footer message fieldset show/hide
        if ($footerCheckbox.is(':checked')) {
            $footer.fadeIn();
        } else {
            $footer.fadeOut();
        }
    }

    // Run on page load
    updateRedirectModeFields();
    // Run on change
    $('#default_redirect_option').on('change', updateRedirectModeFields);
    $('#show_footer_message').on('change', updateRedirectModeFields);

    // Counter for repeater items
    let repeaterIndex = 0;

    // Function to update the preview field for a repeater item
    const updatePreview = function($repeaterItem) {
        const before = $repeaterItem.find('.redirect-message-before-input').val() || '';
        let url = $repeaterItem.find('.redirect-url-input').val() || '';
        const after = $repeaterItem.find('.redirect-message-after-input').val() || '';
        // Remove http:// or https://
        url = url.replace(/^https?:\/\//, '');
        $repeaterItem.find('.redirect-preview-input').val(`${before} ${url} ${after}`.trim());
    };

    // Function to add a new repeater item
    const addRepeaterItem = function() {
        repeaterIndex++;        

        const $repeaterItem = $('#redirection-repeater .redirect-repeater:first-child').clone();
        $repeaterItem.attr('data-index', repeaterIndex);

        // Update attributes and clear values
        $repeaterItem.find('[name^="country"]').attr('name', `country[${repeaterIndex}]`).val('');
        $repeaterItem.find('[name^="ip_action"]').attr('name', `ip_action[${repeaterIndex}]`).prop('checked', false);
        $repeaterItem.find('[name^="redirect_url"]').attr('name', `redirect_url[${repeaterIndex}]`).val('');
        $repeaterItem.find('[name^="redirect_message_before"]').attr('name', `redirect_message_before[${repeaterIndex}]`).val('');
        $repeaterItem.find('[name^="redirect_message_after"]').attr('name', `redirect_message_after[${repeaterIndex}]`).val('');
        $repeaterItem.find('.redirect-preview-input').val('');

        $repeaterItem.find('.ip_action_from_country').attr('id', `ip_action_from_country__${repeaterIndex}`);
        $repeaterItem.find('.ip_action_from_country-label').attr('for', `ip_action_from_country__${repeaterIndex}`);
        $repeaterItem.find('.ip_action_not_from_country').attr('id', `ip_action_not_from_country__${repeaterIndex}`);
        $repeaterItem.find('.ip_action_not_from_country-label').attr('for', `ip_action_not_from_country__${repeaterIndex}`);        

        // Append the new repeater item
        $('#redirection-repeater .redirect-repeaters').append($repeaterItem);

        // Attach preview update listeners
        attachPreviewListeners($repeaterItem);
    }

    // Attach preview update listeners to a repeater item
    function attachPreviewListeners($repeaterItem) {
        $repeaterItem.find('.redirect-message-before-input, .redirect-message-after-input, .redirect-url-input').on('input', function() {
            updatePreview($repeaterItem);
        });
    }

    // Function to remove a repeater item
    const removeRepeaterItem = function($repeaterItem) {
        $repeaterItem.remove();
        updateRepeaterActions();
    };

    // Function to update options in the default_redirect_option select
    const updateRepeaterActions = function() {
        // Update all .redirect-repeater items' indexes and name attributes
        $('#redirection-repeater .redirect-repeater').each(function(i) {
            // Update data-index attribute
            $(this).attr('data-index', i);

            // Update all fields with .repeater-index class
            $(this).find('.repeater-index').each(function() {
                // Update name attribute: replace [oldIndex] with [i]
                let name = $(this).attr('name');
                if (name) {
                    name = name.replace(/\[\d+\]/, '[' + i + ']');
                    $(this).attr('name', name);
                }
            });

            // Update id and for attributes for checkboxes/labels if present
            $(this).find('.ip_action_from_country').attr('id', `ip_action_from_country__${i}`);
            $(this).find('.ip_action_from_country-label').attr('for', `ip_action_from_country__${i}`);
            $(this).find('.ip_action_not_from_country').attr('id', `ip_action_not_from_country__${i}`);
            $(this).find('.ip_action_not_from_country-label').attr('for', `ip_action_not_from_country__${i}`);
        });
    };

    // Event listener for adding a new repeater item
    $('#redirection-repeater').on('click', '.add-repeater', function() {
        addRepeaterItem();
    });

    // Event listener for removing a repeater item
    $('#redirection-repeater').on('click', '.remove-repeater', function() {
        const $repeaterItem = $(this).closest('.redirect-repeater');
        removeRepeaterItem($repeaterItem);
    });

    // Attach preview listeners to all existing repeater items on page load
    $('#redirection-repeater .redirect-repeater').each(function() {
        attachPreviewListeners($(this));
        updatePreview($(this));
    });

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
