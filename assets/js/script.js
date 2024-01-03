function showPopup($popup, $popupOverlay) {
    $popup.addClass('--show');
    $popupOverlay.addClass('--show');
}

function hidePopup($popup, $popupOverlay) {
    $popup.removeClass('--show');
    $popupOverlay.removeClass('--show');
}

jQuery(() => {
    const ajaxUrl = ip_location?.ajaxurl;

    jQuery('.popup-wrapper').each(function () {
        const $popup = jQuery(this).find('.popup-window')
        const $popupOverlay = jQuery(this).closest('.popup-wrapper').find('.popup-overlay');

        if ($popup.length) {
            setTimeout(() => {
                showPopup($popup, $popupOverlay);
            }, 1000);
        }
    });

    jQuery(document).on('click', '.popup-close, .popup-window .closer', function(event) {
        event.preventDefault();

        const $popup = jQuery(this).closest('.popup-window');
        const $popupOverlay = jQuery(this).closest('.popup-wrapper').find('.popup-overlay');

        hidePopup($popup, $popupOverlay);
    });
    jQuery(document).on('click', '.popup-overlay', function(event) {
        event.preventDefault();

        const $popup = jQuery(this).closest('.popup-wrapper').find('.popup-window');
        const $popupOverlay = jQuery(this).closest('.popup-wrapper').find('.popup-overlay');

        hidePopup($popup, $popupOverlay);
    });

    jQuery(document).on('click', '.popup-text-goto-link', function(event) {
        event.preventDefault();
        const href = jQuery(this).attr('href');
        const country = jQuery(this).attr('data-country');

        const isFooter = jQuery(this).closest('.footer-selector-wrapper').length;
        if (isFooter) {
            const $overlay = jQuery(this).closest('.footer-selector-wrapper').find('.footer-overlay');
            $overlay.addClass('--show');
            const $loader = jQuery(this).closest('.footer-selector-wrapper').find('.loader-container');
            $loader.addClass('--show');
        } else {
            const $popup = jQuery(this).closest('.popup-wrapper').find('.popup-window');
            $popup.removeClass('--show');
            const $loader = jQuery(this).closest('.popup-wrapper').find('.loader-container');
            $loader.addClass('--show');
        }

        if (ajaxUrl) {
            jQuery.ajax({
                type: 'POST',
                url: ajaxUrl,
                data: {
                    action: 'process_location_url_change',
                    redirectTo: href,
                    country: country,
                },
                success: function(response) {
                    const encodedResponse = JSON.parse(response);
                    if (encodedResponse && encodedResponse.url) {
                        window.location.href = encodedResponse.url;
                    }
                },
                error: function(xhr, status, error) {
                    console.warn('Error: ', error);
                },
            });
        }
    });

});
