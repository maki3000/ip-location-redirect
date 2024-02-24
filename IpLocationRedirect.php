<?php
/*
Plugin Name: IP location redirect
Plugin URI: https://maki3000.net
Description: Redirects traffic according to IP location
Version: 2.0.0
Author: maki3000
Author URI: https://maki3000.net
Requires at least: 6.0
Tested up to: 6.0
Requires PHP: 8.1
Text Domain: ip-location-redirect
*/

require_once plugin_dir_path(__FILE__) . 'classes/RemoteAddress.php';

// TODO: try to put that inside the class
function processLocationUrlChange() {
    $redirectTo = sanitize_text_field($_POST['redirectTo']);
    $country = sanitize_text_field($_POST['country']);
    $cookieUrl = str_replace(array('http://', 'https://'), '', $redirectTo);

    $redirectTo = $redirectTo . '?redirected=' . urlencode(1);
    $redirectTo = $redirectTo . '&ip_location_redirect_chosen=' . urlencode($cookieUrl);
    $redirectTo = $redirectTo . '&ip_location_country=' . urlencode($country);

    $protocol = isset($_SERVER['HTTPS']) ? 'https' : 'http';
    if (isset($_SERVER['HTTPS']) && !str_contains($redirectTo, $protocol)) {
        $redirectTo = $protocol . $redirectTo;
    }

    $return = json_encode([
        'url' => $redirectTo,
    ]);

    echo $return;
    die();
}

add_action( 'wp_ajax_process_location_url_change', 'processLocationUrlChange', 10 );
add_action( 'wp_ajax_nopriv_process_location_url_change', 'processLocationUrlChange', 10 );


/**
 * IpLocationRedirect class.
 */
class IpLocationRedirect {

    private static $ipApis;
    private static $activeIpApi;
    private static $redirectTo;
    private static $redirectToChosen;  
    private static $country;  
    private static $cookieUrl;  
    private static $ip_location_country_has_redirected_not;
    private static $redirectFailed;
    private static $scriptsLoaded;

	/**
	 * Hook us in :)
	 *
	 * @access public
	 * @return void
	 */
	public function __construct() {
        // add admin menu
        add_action('admin_menu', array( $this, 'add_admin_menu' ));
        add_action('admin_init', array( $this, 'register_settings' ));
        add_action('admin_init', array( $this, 'save_settings' ));
        add_action('admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ));

        // get settings
        $settings = $this->getSettings();

        // return if not activated
        if ($settings['redirection_active'] === 0) {
            return false;
        }

        $this->ipApis = [
            'ip_api'      => 'http://ip-api.com/json/{{ ip }}?fields=status,country,countryCode',
        ];
        if (isset($this->ipApis[$settings['ip_api']])) {
            $this->activeIpApi = $this->ipApis[$settings['ip_api']];
        } else {
            $this->activeIpApi = 'http://ip-api.com/json/{{ ip }}?fields=status,country,countryCode';
        }
       
        $this->redirectTo = '';
        $this->redirectToChosen = '';
        $this->country = '';
        $this->cookieUrl = '';
        $this->scriptsLoaded = false;
        $this->redirectFailed = false;

        // TODO: ev. make static 5 and make it editable in BE
        $this->ip_location_api_called_limit = 5;

        // check if we are already redirected
        $isRedirected = $this->check_for_parameters();
        $isRedirectedChosen = $this->check_for_parameters_redirect_chosen();

        $ip_location_country = isset( $_COOKIE['ip_location_country'] ) ? $_COOKIE['ip_location_country'] : null;
        $ip_location_country_has_redirected = isset( $_COOKIE['ip_location_country_has_redirected'] ) ? $_COOKIE['ip_location_country_has_redirected'] : null;
        $ip_location_country_has_shown_redirected = isset( $_COOKIE['ip_location_country_has_shown_redirected'] ) ? $_COOKIE['ip_location_country_has_shown_redirected'] : null;
        $ip_location_redirect = isset( $_COOKIE['ip_location_redirect'] ) ? $_COOKIE['ip_location_redirect'] : null;
        $ip_location_redirect_chosen = isset( $_COOKIE['ip_location_redirect_chosen'] ) ? $_COOKIE['ip_location_redirect_chosen'] : null;
        $ip_location_redirect_failed = isset( $_COOKIE['ip_location_redirect_failed'] ) ? $_COOKIE['ip_location_redirect_failed'] : null;

        // show location switch in footer
        if (null === $ip_location_redirect_failed && isset($ip_location_country_has_shown_redirected)) {
            $this->loadScripts();
            add_action( 'wp_footer', array( $this, 'include_footer_content' ), 100 );
        }

        // redirect actively if location is chosen and url different
        if (null === $ip_location_redirect_failed && isset($ip_location_redirect_chosen)) {
            $currentUrl = $this->getCurrentUrl();
            $this->loadScripts();
            add_action( 'wp_footer', array( $this, 'include_footer_content' ), 100 );

            if ($currentUrl !== $ip_location_redirect_chosen) {
                $this->redirectTo = $ip_location_redirect_chosen;
                add_action( 'template_redirect',  array($this, 'redirect_to'), 100 );
            }
            return;
        }

        // redirect actively if location is different from url
        if (null === $ip_location_redirect_failed && isset($ip_location_redirect)) {
            $currentUrl = $this->getCurrentUrl();

            if ($currentUrl !== $ip_location_redirect) {
                $this->redirectTo = $ip_location_redirect;
                add_action( 'template_redirect',  array($this, 'redirect_to'), 100 );
                return;
            }
        }

        // show info popup if redirected
        if (null === $ip_location_redirect_failed && isset($ip_location_country_has_redirected) && null === $ip_location_country_has_shown_redirected && isset($ip_location_country)) {
            $this->country = $ip_location_country;
            $this->loadScripts();

            $this->showRedirectionPopup($ip_location_country);
            return;
        }

        // make API call
        $ip_location_api_called = isset( $_COOKIE['ip_location_api_called'] ) ? $_COOKIE['ip_location_api_called'] : null;
        if (null === $ip_location_redirect_failed && !isset($ip_location_country) || (isset($ip_location_api_called) && intval($ip_location_api_called) < $this->ip_location_api_called_limit )) {
            add_action('wp', array( $this, 'call_ip_location_redirect' ), 1 );
            return;
        }
	}

    public function add_admin_menu() {
        add_menu_page(
            'IP Redirects',
            'IP Redirect',
            'ip-location-redirect',
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
                wp_enqueue_script('admin-script', plugin_dir_url(__FILE__) . 'assets/js/admin-script.js', array('jquery'), '1.0', true);
                // Enqueue your CSS file
                wp_enqueue_style('admin-styles', plugin_dir_url(__FILE__) . 'assets/css/admin-styles.css', array(), '1.0');
            }
        }
    }

    function generate_option_group_nonce_fields($option_group) {
        settings_fields($option_group);
    }

    public function display_settings_page() {
        // Retrieve transient errors, if any
        $errors = get_transient('ip_location_settings_errors');
        // Clear the transient after retrieval
        delete_transient('ip_location_settings_errors');

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

    public function field_callback() {
        $values = $this->getSettings();

        ?>
        <div class="form-group">
            <label for="redirection_active">Redirection Active <span class="input-required">(required)</span></label>
            <div class="form-check">
                <input type="checkbox" class="form-check-input" id="redirection_active" name="redirection_active" <?php checked($values['redirection_active'], 1); ?>>
                <label class="form-check-label" for="redirection_active">Enable Redirection</label>
            </div>
        </div>
    
        <div class="form-group">
            <label for="ip_api">IP API <span class="input-required">(required)</span></label>
            <!-- TODO: add more IP APIs options -->
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
            <label for="redirection_title">Redirection Title <span class="input-required">(required)</span></label>
            <input type="text" class="form-control" id="redirection_title" name="redirection_title" required value="<?php echo esc_attr($values['redirection_title']); ?>">
            <small class="form-text text-muted">The value {{ shopUrl }} will be replaced with the redirection name.</small>
        </div>
    
        <div class="form-group">
            <label for="redirection_text">Redirection Text <span class="input-required">(required)</span></label>
            <textarea class="form-control" id="redirection_text" name="redirection_text" required rows="4"><?php echo esc_textarea($values['redirection_text']); ?></textarea>
            <small class="form-text text-muted">The value {{ shopUrl }} will be replaced with the redirection name.</small>
        </div>
    
        <div class="form-group">
            <label for="redirection_info">Redirection Info</label>
            <textarea class="form-control" id="redirection_info" name="redirection_info" rows="4"><?php echo esc_textarea($values['redirection_info']); ?></textarea>
            <small class="form-text text-muted">This text will be displayed under the popup window text.</small>
        </div>
    
        <div id="redirection-repeater" class="form-group">
            <label for="redirect_actions">Redirect Actions <span class="input-required">(required)</span></label>
    
            <!-- TODO: add sort -->
            <div class="repeaters">

                <?php if (empty($values['redirects'])) : ?>
                    <div class="form-group repeater" data-index="0">

                        <label for="country">Country <span class="input-required">(required)</span></label>

                        <div class="form-repeater-block">
                            <select class="form-control" id="country" name="country[0]" required>
                                <option value="" disabled selected>Please select a country</option>
                                <?php
                                    // Include the countries list
                                    include plugin_dir_path(__FILE__) . 'templates/countries-list.php';
                                    // Output the options
                                    foreach ($countries as $code => $name) {
                                        echo '<option value="' . esc_attr($code) . '">' . esc_html($name) . '</option>';
                                    }
                                ?>
                            </select>
                        </div>

                        <div class="form-repeater-block">
                            <div class="form-radio">
                                <label>IP Action <span class="input-required">(required)</span></label>

                                <div class="form-radio-option">
                                    <input type="radio" class="form-check-input ip_action_from_country" id="ip_action_from_country__0" name="ip_action[0]" value="from_country" checked>
                                    <label class="form-radio-label ip_action_from_country-label" for="ip_action_from_country__0">IP is from country</label>
                                </div>

                                <div class="form-radio-option">
                                    <input type="radio" class="form-check-input ip_action_not_from_country" id="ip_action_not_from_country__0" name="ip_action[0]" value="not_from_country">
                                    <label class="form-radio-label ip_action_not_from_country-label" for="ip_action_not_from_country__0">IP is NOT from country</label>
                                </div>

                            </div>
                        </div>

                        <div class="form-repeater-block">
                            <label for="redirect_url">Redirect to URL <span class="input-required">(required)</span></label>
                            <input type="text" class="form-control" id="redirect_url" name="redirect_url[0]">
                        </div>

                        <button class="btn btn-danger remove-repeater" type="button">Remove</button>
                    </div>
                <?php else : ?>
                    <!-- Show repeaters with values if $values['redirects'] is not empty -->
                    <!-- TODO: use template for repeater item -->
                    <?php foreach ($values['redirects'] as $index => $redirect) : ?>
                        <div class="form-group repeater" data-index="<?php echo esc_attr($index); ?>">

                            <div class="form-repeater-block">
                                <label for="country">Country <span class="input-required">(required)</span></label>
                                <select class="form-control" id="country" name="country[<?php echo esc_attr($index); ?>]" required>
                                    <option value="" disabled selected>Please select a country</option>
                                    <?php
                                        // Include the countries list
                                        include plugin_dir_path(__FILE__) . 'templates/countries-list.php';
                                        // Output the options
                                        foreach ($countries as $code => $name) {
                                            $selected = ($redirect['country'] === $code) ? 'selected' : '';
                                            echo '<option value="' . esc_attr($code) . '" ' . $selected . '>' . esc_html($name) . '</option>';
                                        }
                                    ?>
                                </select>
                            </div>

                            <div class="form-repeater-block">
                                <div class="form-radio">
                                    <label>IP Action <span class="input-required">(required)</span></label>

                                    <div class="form-radio-option">
                                        <input type="radio" class="form-check-input ip_action_from_country" id="ip_action_from_country__<?php echo esc_attr($index); ?>" name="ip_action[<?php echo esc_attr($index); ?>]" value="from_country" <?php checked($redirect['ip_action'], 'from_country'); ?>>
                                        <label class="form-radio-label ip_action_from_country-label" for="ip_action_from_country__<?php echo esc_attr($index); ?>">IP is from country</label>
                                    </div>

                                    <div class="form-radio-option">
                                        <input type="radio" class="form-check-input ip_action_not_from_country" id="ip_action_not_from_country__<?php echo esc_attr($index); ?>" name="ip_action[<?php echo esc_attr($index); ?>]" value="not_from_country" <?php checked($redirect['ip_action'], 'not_from_country'); ?>>
                                        <label class="form-radio-label ip_action_not_from_country-label" for="ip_action_not_from_country__<?php echo esc_attr($index); ?>">IP is NOT from country</label>
                                    </div>

                                </div>
                            </div>

                            <div class="form-repeater-block">
                                <label for="redirect_url">Redirect to URL <span class="input-required">(required)</span></label>
                                <input type="text" class="form-control" id="redirect_url" name="redirect_url[<?php echo esc_attr($index); ?>]" value="<?php echo esc_attr($redirect['redirect_url']); ?>">
                            </div>

                            <button class="btn btn-danger remove-repeater" type="button">Remove</button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

            </div>
    
            <small class="form-text text-muted">The first redirect that fulfills the setting will be executed.</small>
            <button class="btn btn-success add-repeater" type="button">Add new redirect</button>
    
        </div>
    
        <div id="redirection-default" class="form-group">
            <label for="default_redirect_option">Default URL redirect <span class="input-required">(required)</span></label>
            <select class="form-control" id="default_redirect_option" name="default_redirect_option">
                <option value="do_nothing" <?php selected($values['default_redirect_option'], 'do_nothing'); ?>>Do nothing (standard)</option>
            </select>
        </div>
        <?php
    }    

    public function save_settings() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {

            $errors = array();
        
            // Initialize the main option array
            $saved_values = get_option('ip_redirection_options', array());
        
            // Validate and save redirection_active
            $saved_values['redirection_active'] = isset($_POST['redirection_active']) ? 1 : 0;
        
            // Validate and save ip_api
            $saved_values['ip_api'] = sanitize_text_field($_POST['ip_api']);
            if (empty($saved_values['ip_api'])) {
                $errors[] = 'IP API is required.';
            }
        
            // Validate and save loader
            $saved_values['loader'] = sanitize_text_field($_POST['loader']);
            if (empty($saved_values['loader'])) {
                $errors[] = 'Loader URL is required.';
            }
        
            // Validate and save redirection_title
            $saved_values['redirection_title'] = sanitize_text_field($_POST['redirection_title']);
            if (empty($saved_values['redirection_title'])) {
                $errors[] = 'Redirection Title is required.';
            }
        
            // Validate and save redirection_text
            $saved_values['redirection_text'] = sanitize_text_field($_POST['redirection_text']);
            if (empty($saved_values['redirection_text'])) {
                $errors[] = 'Redirection Text is required.';
            }
        
            // Validate and save redirection_info
            $saved_values['redirection_info'] = sanitize_text_field($_POST['redirection_info']);
        
            // Validate and save repeater fields
            $redirect_actions = isset($_POST['country']) ? $_POST['country'] : array();
            $ip_actions = isset($_POST['ip_action']) ? $_POST['ip_action'] : array();
            $redirect_urls = isset($_POST['redirect_url']) ? $_POST['redirect_url'] : array();
        
            $redirect_data = array();
            foreach ($redirect_actions as $index => $country) {
                $country = sanitize_text_field($country);
                $ip_action = sanitize_text_field($ip_actions[$index]);
                $redirect_url = sanitize_text_field($redirect_urls[$index]);
        
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
                    $errors[] = 'Invalid Redirect URL in repeater.';
                }
        
                $redirect_data[] = array(
                    'country' => $country,
                    'ip_action' => $ip_action,
                    'redirect_url' => $redirect_url,
                );
            }
        
            if (empty($redirect_data)) {
                $errors[] = 'At least one redirect item is required in the repeater.';
            }

            $saved_values['redirects'] = $redirect_data;
        
            // Validate and save default_redirect_option
            $saved_values['default_redirect_option'] = sanitize_text_field($_POST['default_redirect_option']);
            if ($saved_values['default_redirect_option'] !== 'do_nothing' && $saved_values['default_redirect_option'] !== 'custom_redirect') {
                $errors[] = 'Invalid Default Redirect Option.';
            }
        
            // Update the main option array
            update_option('ip_redirection_options', $saved_values);
        
            // Check for errors and redirect accordingly
            if (!empty($errors)) {
                // Display error messages or handle them as needed
                // For example, you can store them in a transient and display them on the settings page
                set_transient('ip_location_settings_errors', $errors, 30);
            } else {
                // Redirect back to the settings page
                wp_redirect(admin_url('admin.php?page=ip-location-settings'));
            }
        }    
    }    

    public function getSettings() {
        $values = array();
    
        // Retrieve values from get_option
        $saved_values = get_option('ip_redirection_options', array());

        // Assign saved values to the corresponding keys
        $keys = array(
            'redirection_active',
            'ip_api',
            'loader',
            'redirection_title',
            'redirection_text',
            'redirection_info'
        );
    
        foreach ($keys as $key) {
            if (isset($saved_values[$key])) {
                // Use sanitize_text_field for single values, and sanitize_textarea_field for textarea
                $values[$key] = $key === 'redirection_text' || $key === 'redirection_info' ? sanitize_textarea_field($saved_values[$key]) : sanitize_text_field($saved_values[$key]);
            } else {
                $values[$key] = '';
            }
        }
    
        // Retrieve values for repeater fields
        // Iterate through the fields and apply the sanitization/escaping functions for redirects
        foreach ($saved_values['redirects'] as $key => $redirect) {
            //$values['redirects'][$key] = isset($saved_values['redirect_actions'][$key]) ? array_map($function, $saved_values['redirect_actions'][$key]) : array();
            $values['redirects'][$key] = $redirect;
        }
    
        // Retrieve values for default redirect option
        $values['default_redirect_option'] = isset($saved_values['default_redirect_option']) ? sanitize_text_field($saved_values['default_redirect_option']) : '';
    
        return $values;
    }

    public function loadScripts() {
        if ($this->scriptsLoaded === false) {
            add_action( 'wp_enqueue_scripts', array( $this, 'ajax_scripts' ), 10 );
            add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_stylesheet' ), 10 );
            $this->scriptsLoaded = true;
        }
    }
    
    public function getCurrentUrl() {
        $currentUrl = (empty($_SERVER['HTTPS']) ? 'http' : 'https') . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        return parse_url($currentUrl, PHP_URL_HOST);
    }

    public function addProtocol($url) {
        if (strlen($url) <= 0) {
            return '';
        }

        $protocol = isset($_SERVER['HTTPS']) ? 'https' : 'http';
        $protocolDelimiter = isset($_SERVER['HTTPS']) ? 'https://' : 'http://';

        if (is_array($url) && isset($url['url'])) {
            $url = $url['url'];
        }

        if (isset($_SERVER['HTTPS']) && !str_contains($url, $protocolDelimiter)) {
            return $protocolDelimiter . $url;
        } else if (isset($_SERVER['HTTPS']) && !str_contains($url, $protocol)) {
            return $protocol . $url;
        } else {
            return $url;
        }
    }

    public function check_for_parameters() {
        if (isset($_GET['redirected'])) {
            if (isset($_GET['ip_location_country'])) {
                $ip_location_country = sanitize_text_field($_GET['ip_location_country']);
                setcookie( 'ip_location_country', $ip_location_country, time() + 86400, '/' );
            }
            if (isset($_GET['ip_location_country_has_redirected'])) {
                $ip_location_country_has_redirected = sanitize_text_field($_GET['ip_location_country_has_redirected']);
                setcookie( 'ip_location_country_has_redirected', $ip_location_country_has_redirected, time() + 86400, '/' );
            }
            if (isset($_GET['ip_location_country_has_redirected_not'])) {
                $ip_location_country_has_redirected_not = sanitize_text_field($_GET['ip_location_country_has_redirected_not']);
                setcookie( 'ip_location_country_has_redirected_not', $ip_location_country_has_redirected_not, time() + 86400, '/' );
            }
            if (isset($_GET['ip_location_redirect_failed'])) {
                setcookie( 'ip_location_redirect_failed', 1, time() + 86400, '/' );
            }
            if (isset($_GET['ip_location_redirect'])) {
                $ip_location_redirect = sanitize_text_field($_GET['ip_location_redirect']);

                $this->redirectTo = $ip_location_redirect;
                setcookie( 'ip_location_redirect', $ip_location_redirect, time() + 86400, '/' );

                add_action( 'template_redirect',  array($this, 'redirected'), 100 );
            }
            return true;
        }
        return false;
    }

    public function check_for_parameters_redirect_chosen() {
        if (isset($_GET['redirected'])) {
            if (isset($_GET['ip_location_redirect_chosen'])) {
                $ip_location_redirect_chosen = sanitize_text_field($_GET['ip_location_redirect_chosen']);
                $ip_location_country = sanitize_text_field($_GET['ip_location_country']);
                $this->redirectToChosen = $ip_location_redirect_chosen;

                setcookie( 'ip_location_redirect_chosen', $ip_location_redirect_chosen, time() + 86400, '/' );
                setcookie( 'ip_location_country', $ip_location_country, time() + 86400, '/' );

                add_action( 'template_redirect',  array($this, 'redirected'), 100 );
            }
            return true;
        }
        return false;
    }

    public function redirected() {
        $redirectTo = $this->redirectTo;

        if ($redirectTo === '' || null === $redirectTo) {
            $redirectTo = sanitize_text_field($_GET['ip_location_redirect']);
        }
        if ($this->redirectToChosen !== '') {
            $redirectTo = $this->redirectToChosen;
        }

        $redirectTo = $this->addProtocol($redirectTo);

        if (strlen($redirectTo) > 0) {
            wp_redirect($redirectTo, 307, 'IP location redirect');
            die;
        }
    }

    public function redirect_to() {
        $redirectTo = $this->redirectTo;
        $redirectTo = $this->addProtocol($redirectTo);

        if (strlen($redirectTo) <= 0) {
            die;
        }
        
        $redirectTo = $redirectTo . '?redirected=' . urlencode(1);
        $redirectTo = $redirectTo . '&ip_location_country=' . urlencode($this->country);
        $redirectTo = $redirectTo . '&ip_location_redirect=' . urlencode($this->cookieUrl);
        $redirectTo = $redirectTo . '&ip_location_country_has_redirected=' . urlencode(1);
        $redirectTo = $redirectTo . '&ip_location_country_has_redirected_not=' . urlencode($this->ip_location_country_has_redirected_not);

        if ($this->redirectFailed) {
            $redirectTo = $redirectTo . '&ip_location_redirect_failed=' . urlencode(1);
        }

        wp_redirect($redirectTo, 307, 'IP location redirect');
        die;
    }

    public function getRedirectToUrl( $countryCode, $resolveSuccessful ) {
        $settings = $this->getSettings();

        if (isset($settings['redirects']) && count($settings['redirects'])) {
            foreach ($settings['redirects'] as $key => $redirect) {
                // get country by IP failed
                if ($resolveSuccessful === false) {
                    $defaultRedirect = $settings['default_redirect_option'];
                    if ($defaultRedirect === 'do_nothing') {
                        return false;
                    } else {
                        return $defaultRedirect;
                    }
                }
                // get country by IP succeded
                // if IP is from country, then redirect to first redirect_url in the list
                if (is_array($redirect) && isset($redirect['country']) && isset($redirect['ip_action']) && $redirect['ip_action'] === 'from_country' && $countryCode === $redirect['country']) {
                    // TODO: check this ip_location_country_has_redirected_not
                    $this->ip_location_country_has_redirected_not = 0;
                    return $redirect['redirect_url'];
                }
                // if IP is NOT from country, then redirect to first redirect_url in the list
                if (is_array($redirect) && isset($redirect['country']) && isset($redirect['ip_action']) && $redirect['ip_action'] === 'not_from_country' && $countryCode !== $redirect['country']) {
                    // TODO: check this ip_location_country_has_redirected_not
                    $this->ip_location_country_has_redirected_not = 1;
                    return $redirect['redirect_url'];
                }
            }
        }
    }
    
    public function ajax_scripts() {
        wp_enqueue_script( 'ajax-script', plugins_url('/ip-location-redirect/assets/js/script.js'), array('jquery'), '1.0', true );
        wp_localize_script( 'ajax-script',' ip_location', array(
            'ajaxurl'       => admin_url( 'admin-ajax.php' ),
            'country'       => $this->country,
        ) );
    }

    public function enqueue_stylesheet() {
        wp_enqueue_style('popup-stylesheet', plugins_url('/ip-location-redirect/assets/css/styles.css'));
    }

    public function getTemplateData($settings) {
        $ip_location_redirect = isset( $_COOKIE['ip_location_redirect'] ) ? $_COOKIE['ip_location_redirect'] : null;
        $ip_location_redirect_chosen = isset( $_COOKIE['ip_location_redirect_chosen'] ) ? $_COOKIE['ip_location_redirect_chosen'] : null;

        $ipLocationRedirect = null !== $ip_location_redirect_chosen ? $ip_location_redirect_chosen : $ip_location_redirect;

        $redirectUrlsArray = [];
        $redirectListMarkup = '';
        if (isset($settings['redirects']) && count($settings['redirects'])) {
            foreach ($settings['redirects'] as $key => $redirect) {
                // TODO: check for non http
                $redirectUrl = str_replace(array('http://', 'https://'), '', $redirect['redirect_url']);
                // TODO: make (aktuell) editable in BE
                $currentUrlInfo = $redirectUrl === $ipLocationRedirect ? ' (aktuell)' : '';
                $redirectListMarkup .= '<li>Im Shop der <a href="' . $redirect['redirect_url'] . '" data-country="' . $redirect['country'] . '" class="popup-text-goto-link">' . $redirectUrl . '</a> einkaufen' . $currentUrlInfo . '</li>';
            }
        }

        return [
            'redirectFrom' => $ip_location_redirect,
            'redirectListMarkup' => $redirectListMarkup,
        ];
    }

    public function include_footer_content() {
        $settings = $this->getSettings();
        $templateData = $this->getTemplateData($settings);

        $footer_selector_template = file_get_contents(plugin_dir_path(__FILE__) . 'templates/footerSelector.php');
        $footer_selector_template = str_replace(array("\r", "\n", "\r\n"), '', $footer_selector_template);

        // loader
        $redirectLoaderMarkup = '<div class="loader-container"><img src="{{ loaderImage }}" alt="Loading..."></div>';
        $redirectLoaderMarkup = str_replace('{{ loaderImage }}', $settings['loader'], $redirectLoaderMarkup);
        $footer_selector_template = str_replace('{{ loaderImage }}', $redirectLoaderMarkup, $footer_selector_template);

        // redirect back choices
        $redirectBackMarkup = '{{ redirect_back_start }} <span class="footer-selector-text-goto-country">{{ shopUrl }}</span> {{ redirect_back_end }}';
        $redirectBackMarkup = str_replace('{{ shopUrl }}', $templateData['redirectFrom'], $redirectBackMarkup);
        // TODO: make fail safe
        $redirectionTextArray = explode('{{ shopUrl }}', $settings['redirection_text']);
        $redirectBackMarkup = str_replace('{{ redirect_back_start }}', $redirectionTextArray[0], $redirectBackMarkup);
        $redirectBackMarkup = str_replace('{{ redirect_back_end }}', $redirectionTextArray[1], $redirectBackMarkup);
        $footer_selector_template = str_replace('{{ redirect_back_choices }}', $redirectBackMarkup, $footer_selector_template);

        // redirect list
        $footer_selector_template = str_replace('{{ redirectList }}', $templateData['redirectListMarkup'], $footer_selector_template);

        echo $footer_selector_template;
    }

    public function include_popup_content() {
        $settings = $this->getSettings();
        $templateData = $this->getTemplateData($settings);

        $popup_template = file_get_contents(plugin_dir_path(__FILE__) . 'templates/popup.php');
        $popup_template = str_replace(array("\r", "\n", "\r\n"), '', $popup_template);

        // loader markup
        $redirectLoaderMarkup = '<div class="loader-container"><img src="{{ loaderImage }}" alt="Loading..."></div>';
        $redirectLoaderMarkup = str_replace('{{ loaderImage }}', $settings['loader'], $redirectLoaderMarkup);
        $popup_template = str_replace('{{ loaderImage }}', $redirectLoaderMarkup, $popup_template);

        // title markup
        $redirectTitleMarkup = '{{ redirect_title_start }} <span class="popup-title-country">{{ shopUrl }}</span> {{ redirect_title_end }}';
        $redirectTitleMarkup = str_replace('{{ shopUrl }}', $templateData['redirectFrom'], $redirectTitleMarkup);
        // TODO: make fail safe
        $redirectionTitleArray = explode('{{ shopUrl }}', $settings['redirection_title']);
        $redirectTitleMarkup = str_replace('{{ redirect_title_start }}', $redirectionTitleArray[0], $redirectTitleMarkup);
        $redirectTitleMarkup = str_replace('{{ redirect_title_end }}', $redirectionTitleArray[1], $redirectTitleMarkup);
        $popup_template = str_replace('{{ redirect_title }}', $redirectTitleMarkup, $popup_template);

        // redirect back choices
        $redirectBackMarkup = '{{ redirect_back_start }} <span class="popup-text-goto-country">{{ shopUrl }}</span> {{ redirect_back_end }}';
        $redirectBackMarkup = str_replace('{{ shopUrl }}', $templateData['redirectFrom'], $redirectBackMarkup);
        $redirectionTextArray = explode('{{ shopUrl }}', $settings['redirection_text']);
        $redirectBackMarkup = str_replace('{{ redirect_back_start }}', $redirectionTextArray[0], $redirectBackMarkup);
        $redirectBackMarkup = str_replace('{{ redirect_back_end }}', $redirectionTextArray[1], $redirectBackMarkup);
        $popup_template = str_replace('{{ redirect_back_choices }}', $redirectBackMarkup, $popup_template);

        // redirect list
        $popup_template = str_replace('{{ redirectList }}', $templateData['redirectListMarkup'], $popup_template);

        // redirect info
        $popup_template = isset($settings['redirection_info']) ? str_replace('{{ redirect_info }}', $settings['redirection_info'], $popup_template) : $popup_template;

        echo $popup_template;
    }
    
    public function showRedirectionPopup($country) {
        setcookie( 'ip_location_country_has_shown_redirected', true, time() + 86400, '/' );

        add_action( 'wp_footer', array( $this, 'include_popup_content' ), 100 );
        add_action( 'wp_footer', array( $this, 'include_footer_content' ), 100 );
    }

	public function call_ip_location_redirect() {
		if ( !is_admin() ) {
			$remoteAddress = new RemoteAddress();
			$ip = $remoteAddress->getIpAddress();

            // redirect to default if IP is not set
            if ($ip === false || $ip === '') {
                $this->redirectFailed = true;
            }

            // get api URL
            $apiUrl = str_replace('{{ ip }}', $ip, $this->activeIpApi);

			$curl = curl_init($apiUrl);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			$response = curl_exec($curl);

            $ip_location_api_called = isset( $_COOKIE['ip_location_api_called'] ) ? $_COOKIE['ip_location_api_called'] : null;
            if (isset($ip_location_api_called)) {
                $ip_location_api_called = intval($ip_location_api_called) + 1;
                setcookie( 'ip_location_api_called', $ip_location_api_called, time() + 86400, '/' );
            } else {
                setcookie( 'ip_location_api_called', 1, time() + 86400, '/' );
            }

			if ($response === false) {
				$error = curl_error($curl);

                // redirect to default if API call did not work
                if ($ip === false || $ip === '') {
                    $this->redirectFailed = true;
                }
			} else if ($this->redirectFailed === false) {
				$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
				curl_close($curl);

				if ($httpCode === 200) {
                    try {
                        $decodedResponse = json_decode($response);
                    } catch (Exception $e) {
                        error_log($e);
                    }
                    if (isset($decodedResponse) && isset($decodedResponse->countryCode) && $this->redirectToChosen === '') {
                        $redirectUrl = $this->getRedirectToUrl($decodedResponse->countryCode, !$this->redirectFailed);
                        if ($redirectUrl === false) {
                            return false;
                        }

                        $this->redirectTo = $redirectUrl;
                        $this->country = $decodedResponse->countryCode;

                        $this->cookieUrl = str_replace(array('http://', 'https://'), '', $this->redirectTo);
                        $currentUrl = $this->getCurrentUrl();

                        if (isset($redirectUrl) && $currentUrl !== $this->cookieUrl) {
                            add_action( 'template_redirect',  array($this, 'redirect_to'), 100 );
                        }
                    }
				} else {
                    // redirect to default if API call did not work
                    if ($ip === false || $ip === '') {
                        $this->redirectFailed = true;
                    }
                }
			}
            if ($this->redirectFailed === true) {
                $redirectUrl = $this->getRedirectToUrl($decodedResponse->countryCode, !$this->redirectFailed);
                if ($redirectUrl === false) {
                    return false;
                }

                $this->redirectTo = $redirectUrl;
                $this->country = $decodedResponse->countryCode;

                $this->cookieUrl = str_replace(array('http://', 'https://'), '', $this->redirectTo);
                $currentUrl = $this->getCurrentUrl();

                if ($this->cookieUrl !== $this->redirectTo) {
                    add_action( 'template_redirect',  array($this, 'redirect_to'), 100 );
                }
            }
		}
	}
}

new IpLocationRedirect();
