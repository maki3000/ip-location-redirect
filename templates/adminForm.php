<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>

<fieldset class="redirect-fieldset">

    <legend class="redirect-fieldset-legend redirect-fieldset-legend--first">Main redirect settings</legend>

    <div class="redirect-fieldset-inner">

        <div class="form-group">
            <label for="redirection_active">Redirection Active <span class="input-required">(required)</span></label>
            <div class="form-check">
                <input type="checkbox" class="form-check-input" id="redirection_active" name="redirection_active" <?php checked($values['redirection_active'], 1); ?>>
                <label class="form-check-label" for="redirection_active">Enable Redirection</label>
            </div>
        </div>

        <div id="redirection-default" class="form-group">
            <label for="default_redirect_option">Default URL redirect action <span class="input-required">(required)</span></label>
            <select class="form-control" id="default_redirect_option" name="default_redirect_option">
                <option value="use_given_redirect_options" <?php selected($values['default_redirect_option'], 'use_given_redirect_options'); ?>>Use given redirect options (automatic redirect)</option>
                <option value="show_redirect_options" <?php selected($values['default_redirect_option'], 'show_redirect_options'); ?>>Show redirect options (do not automatically redirect)</option>
            </select>
        </div>
    
        <div class="form-group">
            <label for="ip_api">IP API <span class="input-required">(required)</span></label>
            <select class="form-control" id="ip_api" name="ip_api" required>
                <option value="ip-api.com" <?php selected($values['ip_api'], 'ip-api.com'); ?>>IP Geolocation API (ip-api.com)</option>
            </select>
            <small class="form-text text-muted">At the moment only ip-api.com is implemented and the value of the first item in the list will be active.</small>
        </div>
    
        <div class="form-group">
            <label for="loader">Loader <span class="input-required">(required)</span></label>
            <div class="media-chooser-container">
                <input type="text" class="form-control media-chooser" id="loader" name="loader" required value="<?php echo esc_attr($values['loader']); ?>">
                <button class="btn btn-secondary media-upload-button" type="button">Choose Media</button>
            </div>
        </div>

        <div class="form-group">
            <label for="current_shop_label">Current shop label <span class="input-required">(required)</span></label>
            <input type="text" class="form-control" id="current_shop_label" name="current_shop_label" value="<?php echo isset($values['current_shop_label']) ? esc_attr($values['current_shop_label']) : ''; ?>" required>
            <small class="form-text text-muted">This text will be shown after the current shop link in the popup, e.g. <b>(current)</b>.</small>
        </div>

    </div>

</fieldset>

<fieldset class="redirect-fieldset redirect-automatic-location">

    <legend class="redirect-fieldset-legend">Redirect information (automatic redirect)</legend>
    <p class="form-text text-muted redirect-fieldset-description">These options will be used for the redirection popup, where the user is redirected automatically.</p>

    <div class="redirect-fieldset-inner">

        <div class="form-group">
            <label for="redirection_title">Redirection Title <span class="input-required">(required)</span></label>
            <input type="text" class="form-control" id="redirection_title" name="redirection_title" required value="<?php echo esc_attr($values['redirection_title']); ?>">
            <small class="form-text text-muted">The value {{ shopUrl }} will be replaced with the URL the user was redirected from.</small>
        </div>
    
        <div class="form-group">
            <label for="redirection_text" class="redirection-editor-label">Redirection Text <span class="input-required">(required)</span></label>
            <?php
                wp_editor(
                    $values['redirection_text'],
                    'redirection_text',
                    array(
                        'textarea_name' => 'redirection_text',
                        'textarea_rows' => 8,
                        'media_buttons' => false,
                        'tinymce'       => true,
                        'quicktags'     => true,
                    )
                );
            ?>
            <small class="form-text text-muted">The value {{ shopUrl }} will be replaced with the URL the user was redirected from.</small>
        </div>

        <div class="form-group">
            <label for="redirection_info" class="redirection-editor-label">Redirection Info</label>
            <?php
                wp_editor(
                    $values['redirection_info'],
                    'redirection_info',
                    array(
                        'textarea_name' => 'redirection_info',
                        'textarea_rows' => 8,
                        'media_buttons' => false,
                        'tinymce'       => true,
                        'quicktags'     => true,
                    )
                );
            ?>
            <small class="form-text text-muted">This text will be displayed under the popup window text.</small>
        </div>

    </div>

</fieldset>

<fieldset class="redirect-fieldset redirect-choose-location">

    <legend class="redirect-fieldset-legend">Redirect information (user chooses location)</legend>
    <p class="form-text text-muted redirect-fieldset-description">These options will be used for the redirection popup where the user can choose in which store to buy.</p>

    <div class="redirect-fieldset-inner">

        <div class="form-group">
            <label for="redirection_choose_title">Choose Redirection Title <span class="input-required">(required)</span></label>
            <input type="text" class="form-control" id="redirection_choose_title" name="redirection_choose_title" required value="<?php echo esc_attr($values['redirection_choose_title']); ?>">
        </div>
    
        <div class="form-group">
            <label for="redirection_choose_info" class="redirection-editor-label">Choose Redirection Text <span class="input-required">(required)</span></label>
            <?php
                wp_editor(
                    $values['redirection_choose_info'],
                    'redirection_choose_info',
                    array(
                        'textarea_name' => 'redirection_choose_info',
                        'textarea_rows' => 8,
                        'media_buttons' => false,
                        'tinymce'       => true,
                        'quicktags'     => true,
                    )
                );
            ?>
        </div>

    </div>

</fieldset>

<fieldset class="redirect-fieldset redirect-actions">

    <legend class="redirect-fieldset-legend">Redirect Actions <span class="input-required">(required)</span></legend>

    <div class="redirect-fieldset-inner">

        <div id="redirection-repeater" class="form-group">

            <div class="redirect-repeaters">
                <?php
                    if (empty($values['redirects'])) {
                        $index = 0;
                        include plugin_dir_path(__FILE__) . 'adminRedirectRepeater.php';
                    } else {
                        foreach ($values['redirects'] as $index => $redirect) {
                            include plugin_dir_path(__FILE__) . 'adminRedirectRepeater.php';
                        }
                    }
                ?>
            </div>

            <small class="form-text text-muted">The first redirect that fulfills the setting will be executed.</small>
            <button class="btn btn-success add-repeater" type="button">Add new redirect</button>

        </div>

    </div>

</fieldset>

<fieldset class="redirect-fieldset">

    <legend class="redirect-fieldset-legend">Show redirection message in footer</legend>

    <div class="redirect-fieldset-inner">

        <div class="form-group">
            <div class="form-check">
                <input type="checkbox" class="form-check-input" id="show_footer_message" name="show_footer_message" <?php checked($values['show_footer_message'], 1); ?>>
                <label class="form-check-label" for="show_footer_message">Enable redirection message in footer</label>
            </div>
        </div>

    </div>

</fieldset>

<fieldset class="redirect-fieldset redirect-footer-message">

    <legend class="redirect-fieldset-legend">Redirect footer information</legend>
    <p class="form-text text-muted redirect-fieldset-description">These options will be used for a message showed in the footer, which locations are available with a possiblity to change.</p>

    <div class="redirect-fieldset-inner">

        <div class="form-group">
            <label for="redirection_footer_message" class="redirection-editor-label">Redirection footer message</label>
            <?php
                wp_editor(
                    $values['redirection_footer_message'],
                    'redirection_footer_message',
                    array(
                        'textarea_name' => 'redirection_footer_message',
                        'textarea_rows' => 8,
                        'media_buttons' => false,
                        'tinymce'       => true,
                        'quicktags'     => true,
                    )
                );
            ?>
        </div>

    </div>

</fieldset>
