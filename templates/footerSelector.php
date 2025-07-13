<?php
/**
 * Footer selector template.
 *
 * This template file renders the HTML structure for the footer message
 * displaying available locations.
 *
 * @package IP_Location_Redirect
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="footer-selector-wrapper">

    <div class="container">
        <div class="footer-selector-content">
            <?php if($redirectBackMarkup): ?>
                <div class="footer-selector-info">
                    <?= $redirectBackMarkup; ?>
                </div>
            <?php endif; ?>
            <div class="footer-redirect-list-container">
                <ul>
                    <?= $redirectListMarkup; ?>
                </ul>
            </div>
        </div>
    </div>

</div>
