<?php
/*
Plugin Name: IP location redirect
Plugin URI: https://maki3000.net
Description: Redirects traffic according to IP location
Version: 3.0.1
Author: maki3000
Author URI: https://maki3000.net
Requires at least: 6.0
Tested up to: 6.0
Requires PHP: 8.1
Text Domain: ip-location-redirect
*/

require_once plugin_dir_path(__FILE__) . 'admin.php';
require_once plugin_dir_path(__FILE__) . 'templates.php';
require_once plugin_dir_path(__FILE__) . 'classes/RemoteAddress.php';

/**
 * IpLocationRedirect class.
 *
 * Handles the core IP location detection and redirection logic.
 */
class IpLocationRedirect {

    public $admin;
    public $templates;

    private static $ipApis;
    private static $activeIpApi;

    private static $redirectTo;
    private static $cookieUrl;

    private static $scriptsLoaded;
    private static $ipLocationApiCalled;
    private static $ipLocationApiCalledLimit;

    private static $redirectToCookie;
    private static $hasShownLocationCookie;
    private static $hasShownLocationChooseCookie;

    const COOKIE_REDIRECTED_TO                  = 'ip_location_redirected_to';
    const COOKIE_HAS_SHOWN_LOCATION             = 'ip_location_has_shown_location';
    const COOKIE_HAS_SHOWN_LOCATION_CHOOSE      = 'ip_location_has_shown_location_choose';

	/**
	 * Hook us in :)
	 *
	 * @access public
	 * @return void
	 */
	public function __construct() {
        // Initialize admin features
        $this->admin = new IpLocationRedirectAdmin($this);

        // Get and validate settings
        $settings = $this->admin->get_settings();
        if (empty($settings) || (int) $settings['redirection_active'] === 0) {
            return;
        }

        // prepare templates
        $this->templates = new IpLocationRedirectTemplates($this);

        // Setup
        $this->ipApis = [
            'ip_api' => 'http://ip-api.com/json/{{ ip }}?fields=status,country,countryCode', // TODO: implement other IP APIs
        ];
        $this->activeIpApi = $this->ipApis[$settings['ip_api']] ?? $this->ipApis['ip_api'];

        // Defaults
        $this->redirectTo = '';
        $this->cookieUrl = '';

        $this->scriptsLoaded = false;
        $this->ipLocationApiCalled = 0;
        $this->ipLocationApiCalledLimit = 5;

        // Restore from cookies
        $this->redirectToCookie             = $_COOKIE[self::COOKIE_REDIRECTED_TO]              ?? null;
        $this->hasShownLocationCookie       = $_COOKIE[self::COOKIE_HAS_SHOWN_LOCATION]         ?? null;
        $this->hasShownLocationChooseCookie = $_COOKIE[self::COOKIE_HAS_SHOWN_LOCATION_CHOOSE]  ?? null;

        if ($settings['default_redirect_option'] === 'show_redirect_options') {
            // show redirect options (do not automatically redirect)
            add_action('wp', [$this, 'include_choose_location_popup_content'], 100);
        } else if ($settings['default_redirect_option'] === 'use_given_redirect_options') {
            // check if redirected
            add_action('template_redirect', [$this, 'check_for_parameters'], 1);
            // call redirection API if needed
            $param = sanitize_text_field($_GET['redirect_chosen'] ?? null);
            if ($param === '1') {
                add_action('template_redirect', [$this, 'handle_redirect_chosen'], 1);
            }
            if (!isset($this->redirectToCookie) && $param === '') {
                add_action('template_redirect', [$this, 'call_ip_location_redirect'], 10);
            }
        }

        // show switch UI in footer if set
        if ((int) $settings['show_footer_message'] === 1) {
            $this->load_scripts_if_needed();
            add_action('wp_footer', [$this->templates, 'include_footer_selector'], 100);
            return;
        }
    }

    /**
     * Sets a cookie.
     *
     * @param string $name The cookie name.
     * @param mixed $value The cookie value.
     * @param int $expire The expiration time in seconds.
     * @param string $path The cookie path.
     */
    private function set_cookie($name, $value, $expire = 86400, $path = '/') {
        setcookie($name, $value, time() + $expire, $path);
    }

    /**
     * Gets the current domain and TLD from the server variables.
     *
     * @return string The current domain and TLD.
     */
    public function get_current_url() {
        $currentUrl = (empty($_SERVER['HTTPS']) ? 'http' : 'https') . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        return parse_url($currentUrl, PHP_URL_HOST);
    }

    /**
     * Adds a protocol (http or https) to a URL if it's missing.
     *
     * @param string|array $url The URL.
     * @return string The URL with protocol.
     */
    public function add_protocol($url) {
        if (empty($url)) {
            return '';
        }

        if (is_array($url) && isset($url['url'])) {
            $url = $url['url'];
        }

        $hasProtocol = preg_match('#^https?://#i', $url);
        if ($hasProtocol) {
            return $url;
        }

        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        return $protocol . $url;
    }

    /**
     * Checks if the current request should skip redirection.
     *
     * Skips for static assets, admin pages, and AJAX requests.
     *
     * @return bool True if redirection should be skipped, false otherwise.
     */
    public function should_skip_redirect(): bool {
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';

        return (
            preg_match('/\.(css|js|jpe?g|png|gif|svg|woff2?|ttf|eot|ico|webp|avif)(\\?.*)?$/i', $request_uri)
            || strpos($request_uri, '/wp-admin/') === 0
            || strpos($request_uri, '/wp-login.php') === 0
            || (defined('DOING_AJAX') && DOING_AJAX)
        );
    }

    /**
     * Performs the actual HTTP redirect.
     */
    public function redirect_to() {
        $redirectTo = $this->redirectTo;
        $redirectTo = $this->add_protocol($redirectTo);

        if (empty($redirectTo)) {
            die;
        }

        // Build query args array
        $query_args = array(
            'redirected'                => 1,
            'ip_location_redirected_to' => $this->cookieUrl,
        );

        // Append query args cleanly
        $redirectTo = add_query_arg($query_args, $redirectTo);

        wp_redirect($redirectTo, 307, 'IP location redirect');
        exit;
    }

    /**
     * Loads necessary frontend scripts and styles if not already loaded.
     */
    private function load_scripts_if_needed() {
        if (!$this->scriptsLoaded) {
            add_action('wp_enqueue_scripts', [$this, 'ajax_scripts'], 10 );
            add_action('wp_enqueue_scripts', [$this, 'enqueue_stylesheet'], 10 );
            $this->scriptsLoaded = true;
        }
    }

    /**
     * Enqueues the main frontend JavaScript file.
     */
    public function ajax_scripts() {
        wp_enqueue_script( 'ajax-script', plugins_url('/ip-location-redirect/assets/js/script.js'), array('jquery'), '1.0', true );
    }

    /**
     * Enqueues the URL cleanup JavaScript file.
     */
    public function url_cleanup_script() {
        wp_enqueue_script( 'remove-url-params-script', plugins_url('/ip-location-redirect/assets/js/remove-url-params-script.js'), array('jquery'), '1.0', true );
    }

    /**
     * Enqueues the main frontend stylesheet.
     */
    public function enqueue_stylesheet() {
        wp_enqueue_style('popup-stylesheet', plugins_url('/ip-location-redirect/assets/css/styles.css'));
    }

    /**
     * Sets a cookie and hooks the redirected popup template into the footer.
     */
    public function show_redirection_popup() {
        $this->set_cookie(self::COOKIE_HAS_SHOWN_LOCATION, 1);

        add_action( 'wp_footer', [$this->templates, 'include_redirect_popup'], 100 );
    }

    /**
     * Includes the user chooses location popup content if conditions are met.
     */
    public function include_choose_location_popup_content() {
        if ($this->should_skip_redirect()) { // Added skip check
            return;
        }

        if (isset($this->hasShownLocationChooseCookie)) {
            return;
        }

        $this->set_cookie(self::COOKIE_HAS_SHOWN_LOCATION_CHOOSE, 1);

        $param = sanitize_text_field($_GET['redirect_chosen'] ?? null);
        if (isset($param) && $param === '1') {
            return false;
        }

        $this->load_scripts_if_needed();
        add_action('wp_footer', [$this->templates, 'include_choose_popup'], 100);
    }

    /**
     * Handles actions when a redirect is chosen by the user.
     */
    public function handle_redirect_chosen() {
         if ($this->should_skip_redirect()) { // Added skip check
            return;
        }
        $this->set_cookie(self::COOKIE_REDIRECTED_TO, 1);

        // Add action to include the URL cleanup script in the footer conditionally
        $settings = $this->admin->get_settings(); // Get settings
        if (isset($settings['remove_url_params']) && (int) $settings['remove_url_params'] === 1) { // Check the setting
            add_action('wp_enqueue_scripts', [$this, 'url_cleanup_script'], 99);
        }
    }

    /**
     * Checks for redirect-related parameters in the URL and handles them.
     */
    public function check_for_parameters() {
         if ($this->should_skip_redirect()) { // Added skip check
            return false;
        }
        if (!isset($_GET['redirected']) || !isset($_GET['ip_location_redirected_to'])) {
            return false;
        }

        $redirectedTo = isset($_GET['ip_location_redirected_to']) ? sanitize_text_field($_GET['ip_location_redirected_to']) : null;
        $this->set_cookie(self::COOKIE_REDIRECTED_TO, $redirectedTo);

        // Add action to include the URL cleanup script in the footer conditionally
        $settings = $this->admin->get_settings(); // Get settings
        if (isset($settings['remove_url_params']) && (int) $settings['remove_url_params'] === 1) {
            add_action('wp_enqueue_scripts', [$this, 'url_cleanup_script'], 99);
        }

        // show popup if user was redirected but not informed yet
        $this->load_scripts_if_needed();
        $this->show_redirection_popup();
    }

    /**
     * Finds the appropriate redirect URL based on the country code.
     *
     * @param string $countryCode The country code.
     * @return string|false The redirect URL or false if no match is found.
     */
    public function get_redirect_to_url($countryCode) {
        $settings = $this->admin->get_settings();

        if (isset($settings['redirects']) && count($settings['redirects'])) {
            foreach ($settings['redirects'] as $key => $redirect) {
                // get country by IP succeded
                // if IP is from country, then redirect to first redirect_url in the list
                if (is_array($redirect) && isset($redirect['country']) && isset($redirect['ip_action']) && $redirect['ip_action'] === 'from_country' && $countryCode === $redirect['country']) {
                    return $redirect['redirect_url'];
                }
                // if IP is NOT from country, then redirect to first redirect_url in the list
                if (is_array($redirect) && isset($redirect['country']) && isset($redirect['ip_action']) && $redirect['ip_action'] === 'not_from_country' && $countryCode !== $redirect['country']) {
                    return $redirect['redirect_url'];
                }
            }
        }
        return false; // Added return false if no redirect is found
    }

	/**
	 * Calls the IP location API and handles redirection if necessary.
	 */
	public function call_ip_location_redirect() {
         if ($this->should_skip_redirect()) { // Added skip check
            return;
        }
		if ( !is_admin() ) {
			$remoteAddress = new RemoteAddress();
			$ip = $remoteAddress->getIpAddress();

            // show choose location popup fallback
            if ($ip === false || $ip === '') {
                error_log( 'Location redirect plugin: could not get IP' );
            }

            // limit api calls
            $this->ipLocationApiCalled += 1;
            if ($this->ipLocationApiCalled >= $this->ipLocationApiCalledLimit) {
                return;
            }

            // get api URL
            $apiUrl = str_replace('{{ ip }}', $ip, $this->activeIpApi);

			$curl = curl_init($apiUrl);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			$response = curl_exec($curl);

			if ($response === false) {
				$error = curl_error($curl);

                error_log( 'Location redirect plugin: could not get a proper response from the IP API' );
			} else {
				$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
				curl_close($curl);

				if ($httpCode === 200) {
                    try {
                        $decodedResponse = json_decode($response);
                    } catch (Exception $e) {
                        error_log($e);
                    }
                    if (isset($decodedResponse) && isset($decodedResponse->countryCode)) {
                        $redirectUrl = $this->get_redirect_to_url($decodedResponse->countryCode);
                        if ($redirectUrl === false) {
                            return false;
                        }

                        $this->redirectTo = $redirectUrl;
                        $this->cookieUrl = str_replace(array('http://', 'https://'), '', $this->redirectTo);
                        $currentUrl = $this->get_current_url();

                        if (isset($redirectUrl) && $currentUrl !== $this->cookieUrl) {
                            add_action( 'template_redirect',  [$this, 'redirect_to'], 100 );
                        }
                    }
				} else {
                    error_log( 'Location redirect plugin: could not get a proper response from the IP API' );
                }
			}
		}
	}
}

new IpLocationRedirect();
