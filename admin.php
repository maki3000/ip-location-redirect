<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class IpLocationRedirectAdmin {

    private $plugin;

    /**
     * Constructor.
     *
     * @param object $plugin_instance The main plugin instance.
     */
    public function __construct($plugin_instance) {
        $this->plugin = $plugin_instance;

        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_init', [$this, 'save_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
    }

    /**
     * Adds the plugin's admin menu page.
     */
    public function add_admin_menu() {
        add_menu_page(
            'IP Redirects',
            'IP Redirect',
            'manage_options',
            'ip-location-redirect',
            array($this, 'display_settings_page'),
            'dashicons-arrow-right-alt',
            40
        );
    }

    /**
     * Registers the plugin's settings.
     */
    public function register_settings() {
        // Register option group
        register_setting('ip_location_group', 'ip_location_options', array($this, 'validate_options'));
    }

    /**
     * Placeholder validation function for register_setting.
     * Actual validation is handled in save_settings.
     *
     * @param array $input The input array from the settings form.
     * @return array The sanitized input array.
     */
    public function validate_options($input) {
        // We handle validation and sanitization in save_settings,
        // so we\'ll just return the input here.
        return $input;
    }


    /**
     * Enqueues admin scripts and styles.
     *
     * @param string $hook The current admin page hook.
     */
    public function enqueue_admin_scripts($hook) {
        if (is_admin()) {
            if ('toplevel_page_ip-location-redirect' === $hook) {
                wp_enqueue_media();
                wp_enqueue_script('jquery-ui-sortable');
                wp_enqueue_script('admin-script', plugin_dir_url(__FILE__) . 'assets/js/admin-script.js', array('jquery'), '1.0', true);
                wp_enqueue_script('admin-repeater-sort', plugin_dir_url(__FILE__) . 'assets/js/admin-repeater-sort.js', array('jquery', 'jquery-ui-sortable'), '1.0', true);
                wp_enqueue_style('admin-styles', plugin_dir_url(__FILE__) . 'assets/css/admin-styles.css', array(), '1.0');
            }
        }
    }

    /**
     * Sanitizes a setting value based on its key.
     *
     * @param string $key The setting key.
     * @param mixed $value The setting value.
     * @return mixed The sanitized value.
     */
    private function _sanitize_setting($key, $value) {
        switch ($key) {
            case 'redirection_active':
            case 'show_footer_message':
            case 'remove_url_params':
                return isset($value) ? 1 : 0;
            case 'redirection_text':
            case 'redirection_info':
            case 'redirection_choose_info':
            case 'redirection_footer_message':
                return wp_kses_post($value);
            case 'ip_api':
            case 'redirection_title':
            case 'redirection_choose_title':
            case 'current_shop_label':
            case 'default_redirect_option':
                return sanitize_text_field($value);
            case 'redirects':
                // Handle repeater sanitization separately in save_settings
                return $value;
            default:
                return $value;
        }
    }

    /**
     * Validates a setting value based on its key and current settings.
     *
     * @param string $key The setting key.
     * @param mixed $value The setting value.
     * @param array $settings All current settings values.
     * @param array $errors Array to add validation errors to.
     */
    /*
    private function _validate_setting($key, $value, $settings, &$errors) {
        switch ($key) {
            case 'redirection_active':
                if (!in_array($value, array(0, 1))) {
                    $errors[] = 'Please choose if the plugin should be active or not.';
                }
                break;
            case 'remove_url_params':
                if (!in_array($value, array(0, 1))) {
                    $errors[] = 'Please choose if the URL parameters should be removed or not.';
                }
                break;
            case 'ip_api':
                if (empty($value)) {
                    $errors[] = 'IP API is required.';
                }
                break;
            case 'redirection_title':
                if ($settings['default_redirect_option'] === 'use_given_redirect_options' && empty($value)) {
                    $errors[] = 'Redirection Title is required for automatic redirect.';
                }
                break;
            case 'redirection_text':
                if ($settings['default_redirect_option'] === 'use_given_redirect_options' && empty($value)) {
                    $errors[] = 'Redirection Text is required for automatic redirect.';
                }
                break;
            case 'redirection_choose_title':
                if ($settings['default_redirect_option'] === 'show_redirect_options' && empty($value)) {
                    $errors[] = 'Choose location title is required for user chooses location.';
                }
                break;
            case 'redirection_choose_info':
                 if ($settings['default_redirect_option'] === 'show_redirect_options' && empty($value)) {
                    $errors[] = 'Choose location info is required for user chooses location.';
                }
                break;
            case 'current_shop_label':
                if (empty($value)) {
                    $errors[] = 'Current shop label is required.';
                }
                break;
            case 'default_redirect_option':
                if (!in_array($value, array('use_given_redirect_options', 'show_redirect_options'))) {
                     $errors[] = 'Invalid Default Redirect Option.';
                }
                break;
            case 'redirection_footer_message':
                 if ($settings['show_footer_message'] && empty($value)) {
                    $errors[] = 'Redirection footer message is required when enabled.';
                }
                break;
            // Repeater validation handled in save_settings
        }
    }

    /**
     * Saves the plugin settings.
     */
    public function save_settings() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {

            $errors = array();
            $main_site_id = get_main_site_id();
            $saved_values = get_blog_option($main_site_id, 'ip_redirection_options', array());

            // Sanitize and validate simple fields
            $simple_keys = array(
                'redirection_active',
                'remove_url_params',
                'ip_api',
                'redirection_title',
                'redirection_text',
                'redirection_info',
                'default_redirect_option',
                'redirection_choose_title',
                'redirection_choose_info',
                'show_footer_message',
                'redirection_footer_message',
                'current_shop_label',
            );

            foreach ($simple_keys as $key) {
                $value = $_POST[$key] ?? '';
                $saved_values[$key] = $this->_sanitize_setting($key, $value);
                // Pass the potentially updated saved_values for cross-field validation
                $this->_validate_setting($key, $saved_values[$key], $saved_values, $errors);
            }

            // Validate and save repeater fields
            $redirect_actions = $_POST['country'] ?? array();
            $ip_actions = $_POST['ip_action'] ?? array();
            $redirect_urls = $_POST['redirect_url'] ?? array();
            $redirect_message_befores = $_POST['redirect_message_before'] ?? array();
            $redirect_message_afters = $_POST['redirect_message_after'] ?? array();

            $redirect_data = array();
            if (!empty($redirect_actions)) {
                foreach ($redirect_actions as $index => $country) {
                    $country = sanitize_text_field($country ?? '');
                    $ip_action = sanitize_text_field($ip_actions[$index] ?? '');
                    $redirect_url = sanitize_text_field($redirect_urls[$index] ?? '');
                    // Correct sanitization for HTML editor fields
                    $redirect_message_before = isset($redirect_message_befores[$index]) ? wp_kses_post($redirect_message_befores[$index]) : '';
                    $redirect_message_after = isset($redirect_message_afters[$index]) ? wp_kses_post($redirect_message_afters[$index]) : '';

                    $item_errors = array(); // Collect errors for this item

                    // Validate country
                    if (empty($country)) {
                        $item_errors[] = 'Country is required.';
                    }

                    // Validate ip_action
                    if (empty($ip_action)) {
                        $item_errors[] = 'IP Action is required.';
                    } elseif (!in_array($ip_action, array('from_country', 'not_from_country'))) {
                        $item_errors[] = 'Invalid IP Action.';
                    }

                    // Validate redirect_url
                    if (empty($redirect_url)) {
                        $item_errors[] = 'Redirect URL is required.';
                    } elseif (!filter_var($redirect_url, FILTER_VALIDATE_URL)) {
                        $item_errors[] = 'Invalid redirect URL.';
                    }

                    // Validate redirect_message_before
                    if (empty($redirect_message_before)) {
                        $item_errors[] = 'Redirect message (before link) is required.';
                    }

                    // Validate redirect_message_after
                    if (empty($redirect_message_after)) {
                        $item_errors[] = 'Redirect message (after link) is required.';
                    }

                    if (!empty($item_errors)) {
                        // Prefix errors with item index for clarity
                        foreach ($item_errors as $item_error) {
                            $errors[] = "Repeater Item #{$index}: {$item_error}";
                        }
                    }

                    $redirect_data[] = array(
                        'country' => $country,
                        'ip_action' => $ip_action,
                        'redirect_url' => $redirect_url,
                        'redirect_message_before' => $redirect_message_before,
                        'redirect_message_after' => $redirect_message_after,
                    );
                }
            }

            $saved_values['redirects'] = $redirect_data;

            // Update the main option array
            if (empty($errors)) {
                 update_blog_option($main_site_id, 'ip_redirection_options', $saved_values);
                 set_transient('ip_location_settings_success', 'Settings saved successfully.', 30);
                 wp_redirect(admin_url('admin.php?page=ip-location-redirect'));
                 exit;
            } else {
                // If there are errors, we don\'t save the settings, but we can store the submitted values
                // to repopulate the form. However, for simplicity here, we\'ll just show errors.
                set_transient('ip_location_settings_errors', $errors, 30);
                // Redirect back to the settings page to display errors
                wp_redirect(admin_url('admin.php?page=ip-location-redirect'));
                exit;
            }
        }
    }

    /**
     * Retrieves and sanitizes the plugin settings.
     *
     * @return array The sanitized settings values.
     */
    public function get_settings() {
        $values = array();
        $main_site_id = get_main_site_id();
        // Retrieve raw saved values
        $saved_values = get_blog_option($main_site_id, 'ip_redirection_options', array());

        $keys = array(
            'redirection_active',
            'remove_url_params',
            'default_redirect_option',
            'ip_api',
            'redirection_title',
            'redirection_text',
            'redirection_info',
            'redirection_choose_title',
            'redirection_choose_info',
            'show_footer_message',
            'redirection_footer_message',
            'current_shop_label',
        );

        foreach ($keys as $key) {
            // Retrieve saved value directly. It was sanitized on save.
            $values[$key] = $saved_values[$key] ?? '';
        }

        // Retrieve repeater data directly (it was sanitized on save)
        $values['redirects'] = $saved_values['redirects'] ?? array();

        // Basic check to ensure redirects is an array and its items are structured correctly
        if (!is_array($values['redirects'])) {
            $values['redirects'] = array();
        } else {
             // Ensure repeater items are also retrieved without re-sanitization
             // The sanitization happened in save_settings
             $retrieved_redirects = array();
             foreach ($values['redirects'] as $redirect) {
                 // Ensure each item is an array before processing
                 if (is_array($redirect)) {
                     $retrieved_redirects[] = array(
                         'country' => $redirect['country'] ?? '',
                         'ip_action' => $redirect['ip_action'] ?? '',
                         'redirect_url' => $redirect['redirect_url'] ?? '',
                         'redirect_message_before' => $redirect['redirect_message_before'] ?? '',
                         'redirect_message_after' => $redirect['redirect_message_after'] ?? '',
                     );
                 }
             }
             $values['redirects'] = $retrieved_redirects;
        }


        return $values;
    }

    /**
     * Renders the settings form fields.
     */
    public function field_callback() {
        $values = $this->get_settings();
        include plugin_dir_path(__FILE__) . 'templates/adminForm.php';
    }

    /**
     * Displays the plugin settings page.
     */
    public function display_settings_page() {
        // Retrieve transient errors, if any
        $errors = get_transient('ip_location_settings_errors');
        delete_transient('ip_location_settings_errors');
        // Retrieve transient success, if any
        $success = get_transient('ip_location_settings_success');
        delete_transient('ip_location_settings_success');

        ?>
            <div class="wrap">
                <h1 class="mb-4">IP redirection</h1>

                <?php
                    // Display errors if there are any
                    if (!empty($errors)) {
                        echo '<div class="error"><p>';
                        echo esc_html(implode('<br>', $errors));
                        echo '</p></div>';
                    }
                    // Display success if there is any
                    if (!empty($success)) {
                        echo '<div class="updated notice is-dismissible"><p>';
                        echo esc_html($success);
                        echo '</p></div>';
                    }
                ?>

                <form method="post" action="options.php">
                    <?php
                        // Output nonce, action, and option_page fields for a settings page
                        settings_fields('ip_location_group');

                        // Call your field callback here
                        $this->field_callback();
                    ?>
                    <button type="submit" name="submit" class="button button-primary">Save Changes</button>
                </form>
            </div>
        <?php
    }

}
