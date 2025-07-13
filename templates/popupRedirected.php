<?php
/**
 * Redirected popup template.
 *
 * This template file renders the HTML structure for the popup displayed after
 * an automatic IP-based redirection has occurred.
 *
 * @package IP_Location_Redirect
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="popup-wrapper">

    <div class="popup-overlay"></div>

    <div class="popup-window" role="dialog" aria-hidden="true">
        <div class="popup-content">
            <div class="popup-header">
                <h2>
                    <?= $redirectTitleMarkup; ?>
                </h2>
                <div class="closer">
                    <?php include plugin_dir_path(__FILE__) . 'svg/closer-icon.svg'; ?>
                </div>
            </div>
            <div class="popup-text-content">
                <div class="popup-back">
                    <?= $redirectBackMarkup; ?>
                </div>
                <div class="popup-redirect-list-container">
                    <ul>
                        <?= $redirectListMarkup; ?>
                    </ul>
                </div>
                <?php if (!empty($redirectInfo)): ?>
                    <div class="pop-info">
                        <?= $redirectInfo; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="pop-text-content">
                <button class="popup-close">
                    Ok
                </button>
            </div>
        </div>
    </div>

</div>
