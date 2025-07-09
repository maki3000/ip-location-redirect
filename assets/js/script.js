function showPopup($popup, $popupOverlay) {
    $popup.addClass('--show');
    $popupOverlay.addClass('--show');
}

function hidePopup($popup, $popupOverlay) {
    $popup.removeClass('--show');
    $popupOverlay.removeClass('--show');
}

jQuery(() => {
    jQuery('.popup-wrapper').each(function () {
        const $popup = jQuery(this).find('.popup-window')
        const $popupOverlay = jQuery(this).closest('.popup-wrapper').find('.popup-overlay');

        if ($popup.length) {
            setTimeout(() => {
                showPopup($popup, $popupOverlay);
            }, 1000);
        }
    });

    jQuery(document).on('click', '.popup-close, .popup-close-popup, .popup-window .closer, .popup-overlay', function(event) {
        event.preventDefault();

        const $popup = jQuery(this).closest('.popup-window');
        const $popupOverlay = jQuery(this).closest('.popup-wrapper').find('.popup-overlay');

        hidePopup($popup, $popupOverlay);
    });
});
