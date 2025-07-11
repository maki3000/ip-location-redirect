<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class IpLocationRedirectAdmin {

    private $plugin;

    public function __construct($plugin_instance) {
        $this->plugin = $plugin_instance;

        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_init', [$this, 'save_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
    }

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

    public function register_settings() {
        // Register option group
        register_setting('ip_location_group', 'ip_location_options', array($this, 'validate_options'));
        // Add fields to the section
        add_settings_field('redirection_active', 'Redirection Active', array($this, 'redirection_active_field'), 'ip-location-settings', 'ip-location-section');
    }

    public function enqueue_admin_scripts($hook) {
        if (is_admin()) {
            if ('toplevel_page_ip-location-redirect' === $hook) {
                wp_enqueue_media();
                wp_enqueue_script('jquery-ui-sortable');
                wp_enqueue_script('admin-script', plugin_dir_url(__FILE__) . 'assets/js/admin-script.js', array('jquery'), '1.0', true);
                wp_enqueue_script('admin-repeater-sort', plugin_dir_url(__FILE__) . 'assets/js/admin-repeater-sort.js', array('jquery', 'jquery-ui-sortable'), '1.0', true);
                // Enqueue your CSS file
                wp_enqueue_style('admin-styles', plugin_dir_url(__FILE__) . 'assets/css/admin-styles.css', array(), '1.0');
            }
        }
    }

    public function save_settings() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {

            $errors = array();
        
            // Initialize the main option array
            $main_site_id = get_main_site_id();
            $saved_values = get_blog_option($main_site_id, 'ip_redirection_options', array());
        
            // Validate and save redirection_active
            $saved_values['redirection_active'] = isset($_POST['redirection_active']) ? 1 : 0;
            
            // Validate and save ip_api
            $saved_values['ip_api'] = sanitize_text_field($_POST['ip_api']);
            if (empty($saved_values['ip_api'])) {
                $errors[] = 'IP API is required.';
            }
        
            // Validate and save redirection_title
            $saved_values['redirection_title'] = sanitize_text_field($_POST['redirection_title']);
            if (empty($saved_values['redirection_title'])) {
                $errors[] = 'Redirection Title is required.';
            }
        
            // Validate and save redirection_text
            $saved_values['redirection_text'] = wp_kses_post($_POST['redirection_text']);
            if (empty($saved_values['redirection_text'])) {
                $errors[] = 'Redirection Text is required.';
            }
        
            // Validate and save redirection_info
            $saved_values['redirection_info'] = wp_kses_post($_POST['redirection_info']);
        
            // Validate and save repeater fields
            $redirect_actions = isset($_POST['country']) ? $_POST['country'] : array();
            $ip_actions = isset($_POST['ip_action']) ? $_POST['ip_action'] : array();
            $redirect_urls = isset($_POST['redirect_url']) ? $_POST['redirect_url'] : array();
            $redirect_message_befores = isset($_POST['redirect_message_before']) ? $_POST['redirect_message_before'] : array();
            $redirect_message_afters = isset($_POST['redirect_message_after']) ? $_POST['redirect_message_after'] : array();

            // Save current shop label
            $saved_values['current_shop_label'] = isset($_POST['current_shop_label']) ? sanitize_text_field($_POST['current_shop_label']) : '';
            if (empty($saved_values['current_shop_label'])) {
                $errors[] = 'Current shop label is required.';
            }

            $redirect_data = array();
            foreach ($redirect_actions as $index => $country) {
                $country = sanitize_text_field($country);
                $ip_action = sanitize_text_field($ip_actions[$index]);
                $redirect_url = sanitize_text_field($redirect_urls[$index]);
                $redirect_message_before = isset($redirect_message_befores[$index]) ? sanitize_text_field($redirect_message_befores[$index]) : '';
                $redirect_message_after = isset($redirect_message_afters[$index]) ? sanitize_text_field($redirect_message_afters[$index]) : '';

                // Validate country
                if (empty($country)) {
                    $errors[] = 'Country in repeater is required.';
                }

                // Validate ip_action
                if (empty($ip_action)) {
                    $errors[] = 'IP Action in repeater is required.';
                } elseif (!in_array($ip_action, array('from_country', 'not_from_country'))) {
                    $errors[] = 'Invalid IP Action in repeater.';
                }

                // Validate redirect_url
                if (empty($redirect_url)) {
                    $errors[] = 'Redirect URL in repeater is required.';
                } elseif (!filter_var($redirect_url, FILTER_VALIDATE_URL)) {
                    $errors[] = 'Invalid redirect URL in repeater.';
                }

                // Validate redirect_message_before
                if (empty($redirect_message_before)) {
                    $errors[] = 'Redirect message (before link) in repeater is required.';
                }

                // Validate redirect_message_after
                if (empty($redirect_message_after)) {
                    $errors[] = 'Redirect message (after link) in repeater is required.';
                }

                $redirect_data[] = array(
                    'country' => $country,
                    'ip_action' => $ip_action,
                    'redirect_url' => $redirect_url,
                    'redirect_message_before' => $redirect_message_before,
                    'redirect_message_after' => $redirect_message_after,
                );
            }
        
            if (empty($redirect_data)) {
                $errors[] = 'At least one redirect item is required in the repeater.';
            }

            $saved_values['redirects'] = $redirect_data;
        
            // Validate and save default_redirect_option
            $saved_values['default_redirect_option'] = sanitize_text_field($_POST['default_redirect_option']);
            if ($saved_values['default_redirect_option'] !== 'use_given_redirect_options' && $saved_values['default_redirect_option'] !== 'show_redirect_options') {
                $errors[] = 'Invalid Default Redirect Option.';
            }
           
            // Validate and save redirection_choose_title
            $saved_values['redirection_choose_title'] = sanitize_text_field($_POST['redirection_choose_title']);
            if (empty($saved_values['redirection_choose_title'])) {
                $errors[] = 'Choose location title is required.';
            }
        
            // Validate and save redirection_choose_info
            $saved_values['redirection_choose_info'] = wp_kses_post($_POST['redirection_choose_info']);
            if (empty($saved_values['redirection_choose_info'])) {
                $errors[] = 'Choose location info is required.';
            }

             // Validate and save show_footer_message
            $saved_values['show_footer_message'] = isset($_POST['show_footer_message']) ? 1 : 0;
        
            // Validate and save redirection_footer_message
            $saved_values['redirection_footer_message'] = wp_kses_post($_POST['redirection_footer_message']);
            if (empty($saved_values['redirection_footer_message'])) {
                $errors[] = 'Choose location info is required.';
            }
        
            // Update the main option array
            update_blog_option(get_main_site_id(), 'ip_redirection_options', $saved_values);
        
            // Check for errors and redirect accordingly
            if (!empty($errors)) {
                // Display error messages or handle them as needed
                set_transient('ip_location_settings_errors', $errors, 30);
            } else {
                // Redirect back to the settings page
                set_transient('ip_location_settings_success', 'Settings saved successfully.', 30);
                wp_redirect(admin_url('admin.php?page=ip-location-redirect'));
                exit;
            }
        }    
    }

    public function get_settings() {
        $values = array();
    
        $main_site_id = get_main_site_id();
        $saved_values = get_blog_option($main_site_id, 'ip_redirection_options', array());

        $keys = array(
            'redirection_active',
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
            if (isset($saved_values[$key])) {
                $values[$key] = $key === 'redirection_text' || $key === 'redirection_info' || $key === 'redirection_choose_info' ? wp_kses_post($saved_values[$key]) : wp_kses_post($saved_values[$key]);
            } else {
                $values[$key] = '';
            }
        }
    
        foreach ($saved_values['redirects'] as $key => $redirect) {
            $values['redirects'][$key] = $redirect;
        }
    
        $values['default_redirect_option'] = isset($saved_values['default_redirect_option']) ? sanitize_text_field($saved_values['default_redirect_option']) : '';

        return $values;
    }

    public function field_callback() {
        $values = $this->get_settings();
        include plugin_dir_path(__FILE__) . 'templates/adminForm.php';
    }

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
                        
                        // Output sections and their fields
                        do_settings_sections('ip-location-settings');

                        // Call your field callback here
                        $this->field_callback();
                    ?>
                    <button type="submit" name="submit" class="button button-primary">Save Changes</button>
                </form>
            </div>
        <?php
    }

}
