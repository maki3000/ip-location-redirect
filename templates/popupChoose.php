<?php
/**
 * User chooses location popup template.
 *
 * This template file renders the HTML structure for the popup displayed when
 * the user is given options to choose their location.
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
                    <?= wp_kses_post($redirectChooseTitleMarkup); ?>
                </h2>
                <div class="closer">
                    <?php include plugin_dir_path(__FILE__) . 'svg/closer-icon.svg'; ?>
                </div>
            </div>
            <div class="popup-text-content">
                <?php if (!empty($redirectChooseInfo)): ?>
                    <?= wp_kses_post($redirectChooseInfo); ?>
                <?php endif; ?>
            </div>
            <div class="popup-redirect-list-container">
                <ul>
                    <?= $redirectChooseListMarkup; ?>
                </ul>
            </div>
        </div>
    </div>

</div>
