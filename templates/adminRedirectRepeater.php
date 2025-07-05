<?php
// adminRedirectRepeater.php
// Usage: include this file and provide $index and $redirect (optional)
// If $redirect is not set, default values will be used (for empty form)

if (!isset($index)) {
    $index = 0;
}

$country_value = isset($redirect['country']) ? $redirect['country'] : '';
$ip_action_value = isset($redirect['ip_action']) ? $redirect['ip_action'] : 'from_country';
$redirect_url_value = isset($redirect['redirect_url']) ? $redirect['redirect_url'] : '';

?>
<div class="form-group redirect-repeater" data-index="<?php echo esc_attr($index); ?>">
    <span class="repeater-drag-handle" title="Drag to reorder" style="cursor:move;display:inline-block;vertical-align:middle;margin-right:8px;">
        &udarr;
    </span>
    
    <input type="hidden" class="repeater-index repeater-index-input" name="repeater_index[<?php echo esc_attr($index); ?>]" value="<?php echo esc_attr($index); ?>">

    <div class="form-repeater-block">
        <label for="country">Country <span class="input-required">(required)</span></label>
        <select class="form-control repeater-index" id="country" name="country[<?php echo esc_attr($index); ?>]" required>
            <option value="" disabled <?php echo empty($country_value) ? 'selected' : ''; ?>>Please select a country</option>
            <?php
                // Include the countries list
                include plugin_dir_path(__FILE__) . 'adminCountriesList.php';
                // Output the options
                foreach ($countries as $code => $name) {
                    $selected = ($country_value === $code) ? 'selected' : '';
                    echo '<option value="' . esc_attr($code) . '" ' . $selected . '>' . esc_html($name) . '</option>';
                }
            ?>
        </select>
    </div>

    <div class="form-repeater-block">
        <div class="form-radio">
            <label>IP Action <span class="input-required">(required)</span></label>
            <div class="form-radio-option">
                <input type="radio" class="form-check-input repeater-index ip_action_from_country" id="ip_action_from_country__<?php echo esc_attr($index); ?>" name="ip_action[<?php echo esc_attr($index); ?>]" value="from_country" <?php checked($ip_action_value, 'from_country', true); ?><?php if (!isset($redirect)) echo ' checked'; ?>>
                <label class="form-radio-label ip_action_from_country-label" for="ip_action_from_country__<?php echo esc_attr($index); ?>">IP is from country</label>
            </div>
            <div class="form-radio-option">
                <input type="radio" class="form-check-input repeater-index ip_action_not_from_country" id="ip_action_not_from_country__<?php echo esc_attr($index); ?>" name="ip_action[<?php echo esc_attr($index); ?>]" value="not_from_country" <?php checked($ip_action_value, 'not_from_country', true); ?>>
                <label class="form-radio-label ip_action_not_from_country-label" for="ip_action_not_from_country__<?php echo esc_attr($index); ?>">IP is NOT from country</label>
            </div>
        </div>
    </div>
    <div class="form-repeater-block">
        <label for="redirect_url">Redirect to URL <span class="input-required">(required)</span></label>
        <input type="url" class="form-control repeater-index redirect-url-input" id="redirect_url_<?php echo esc_attr($index); ?>" name="redirect_url[<?php echo esc_attr($index); ?>]" value="<?php echo esc_attr($redirect_url_value); ?>">
    </div>
    <div class="form-repeater-block">
        <label for="redirect_message_before_<?php echo esc_attr($index); ?>">Redirect message (before link) <span class="input-required">(required)</span></label>
        <input type="text" class="form-control repeater-index redirect-message-before-input" id="redirect_message_before_<?php echo esc_attr($index); ?>" name="redirect_message_before[<?php echo esc_attr($index); ?>]" value="<?php echo isset($redirect['redirect_message_before']) ? esc_attr($redirect['redirect_message_before']) : ''; ?>" required>
        <small class="form-text text-muted">This text will be shown before the shop link in the information popup for this redirect action. Example: <b>Use shop</b></small>
    </div>
    <div class="form-repeater-block">
        <label for="redirect_message_after_<?php echo esc_attr($index); ?>">Redirect message (after link) <span class="input-required">(required)</span></label>
        <input type="text" class="form-control repeater-index redirect-message-after-input" id="redirect_message_after_<?php echo esc_attr($index); ?>" name="redirect_message_after[<?php echo esc_attr($index); ?>]" value="<?php echo isset($redirect['redirect_message_after']) ? esc_attr($redirect['redirect_message_after']) : ''; ?>" required>
        <small class="form-text text-muted">This text will be shown after the shop link in the information popup for this redirect action. Example: <b>to shop as a USA customer</b></small>
    </div>
    <div class="form-repeater-block">
        <label>Preview</label>
        <input type="text" class="form-control repeater-index redirect-preview-input" id="redirect_preview_<?php echo esc_attr($index); ?>" value="<?php
            $before = isset($redirect['redirect_message_before']) ? $redirect['redirect_message_before'] : '';
            $after = isset($redirect['redirect_message_after']) ? $redirect['redirect_message_after'] : '';
            $url = isset($redirect['redirect_url']) ? $redirect['redirect_url'] : '';
            $url_display = preg_replace('#^https?://#', '', $url);
            echo esc_attr(trim($before . ' ' . $url_display . ' ' . $after));
        ?>" disabled>
        <small class="form-text text-muted">This is a live preview of the final output for this redirect action. The domain will be a link, so the user is able to change the location.</small>
    </div>
    <button class="btn btn-danger remove-repeater" type="button">Remove</button>
</div>
