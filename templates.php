<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class IpLocationRedirectTemplates {

    private $plugin;
    private $settings;

    /**
     * Constructor.
     *
     * @param object $plugin_instance The main plugin instance.
     */
    public function __construct($plugin_instance) {
        $this->plugin = $plugin_instance;

        if (!empty($this->plugin->admin)) {
            $this->settings = $this->plugin->admin->get_settings();
        }
    }

    /**
     * Gets the current domain and TLD from the server variables.
     *
     * @return string The current domain and TLD.
     */
    public function get_domain_and_tld() {
        return $_SERVER['HTTP_HOST'];
    }

    /**
     * Helper to format text containing {{ shopUrl }} placeholder.
     *
     * @param string $text_setting The raw text from settings.
     * @param string $shop_url The shop URL to insert.
     * @param string $span_class The CSS class for the span wrapping the shop URL.
     * @return string The formatted HTML string with placeholder replaced.
     */
    private function _format_shop_url_text($text_setting, $shop_url, $span_class) {
        $text = $text_setting ?? '';
        // Replace the placeholder with the shop URL wrapped in a span
        $formatted_text = str_replace('{{ shopUrl }}', '<span class="' . esc_attr($span_class) . '">' . esc_html($shop_url) . '</span>', $text);
        return $formatted_text;
    }

    /**
     * Generates the HTML list markup for redirects based on settings.
     *
     * @param array $settings The plugin settings array.
     * @param string $link_class The CSS class to apply to the redirect links.
     * @param bool $add_close_class Whether to add the 'popup-close-popup' class to links matching the current URL.
     * @return string The generated HTML list markup.
     */
    private function _generate_redirect_list_markup($settings, $link_class, $add_close_class = false) {
        $redirectListMarkup = '';

        if (isset($settings['redirects']) && count($settings['redirects'])) {
            $currentUrl = $this->get_domain_and_tld();
            $currentShopLabel = $settings['current_shop_label'];

            foreach ($settings['redirects'] as $key => $redirect) {
                // Ensure redirect is an array and has required keys before accessing
                if (!is_array($redirect) || !isset($redirect['redirect_url'], $redirect['redirect_message_before'], $redirect['redirect_message_after'])) {
                    continue; // Skip if redirect data is incomplete
                }

                $redirectUrl = str_replace(array('http://', 'https://'), '', $redirect['redirect_url']);
                $before = $redirect['redirect_message_before'];
                $after = $redirect['redirect_message_after'];

                $linkClasses = [$link_class];
                if ($redirectUrl === $currentUrl && $add_close_class) {
                    $linkClasses[] = 'popup-close-popup';
                }
                $linkClassesString = implode(' ', $linkClasses);

                $linkHref = $redirect['redirect_url'];
                if ($redirectUrl !== $currentUrl) {
                    // Add query args for tracking chosen redirect
                    $linkHref = add_query_arg(array(
                        'redirect_chosen' => 1,
                        'redirect_to'     => urlencode($redirectUrl), // Use urlencode for safety
                    ), $linkHref);
                }

                // Replace {{ shopUrl }} in before and after messages
                $before_formatted = str_replace('{{ shopUrl }}', '<span class="popup-text-goto-url">' . esc_html($redirectUrl) . '</span>', $before);
                $after_formatted = str_replace('{{ shopUrl }}', '<span class="popup-text-goto-url">' . esc_html($redirectUrl) . '</span>', $after);


                $redirectListMarkup .= '<li>'
                                     . wp_kses_post($before_formatted) . ' '
                                     . '<a href="' . esc_url($linkHref) . '" class="' . esc_attr($linkClassesString) . '">' . esc_html($redirectUrl) . '</a> '
                                     . wp_kses_post($after_formatted); // Output formatted after with wp_kses_post

                // Add current shop label only if it's the current shop's link
                if ($redirectUrl === $currentUrl) {
                     $redirectListMarkup .= ' ' . esc_html($currentShopLabel);
                }

                $redirectListMarkup .= '</li>';
            }
        }

        return $redirectListMarkup;
    }

    /**
     * Includes the redirected popup template.
     */
    public function include_redirect_popup() {
        $redirectFrom = ''; // Initialize with empty string
        if (isset($_GET['ip_location_redirected_to'])) {
            $redirectFrom = sanitize_text_field($_GET['ip_location_redirected_to']);
        }

        // Extract values
        $redirectListMarkup = $this->_generate_redirect_list_markup($this->settings, 'popup-text-goto-link', true); // Use new helper
        $redirectInfo = $this->settings['redirection_info'] ?? '';

        // Title markup using helper and escaping output
        $redirectTitleMarkup = wp_kses_post($this->_format_shop_url_text($this->settings['redirection_title'], $redirectFrom, 'popup-title-url'));

        // Back text markup using helper and escaping output
        $redirectBackMarkup = wp_kses_post($this->_format_shop_url_text($this->settings['redirection_text'], $redirectFrom, 'popup-text-goto-url'));

        include plugin_dir_path(__FILE__) . 'templates/popupRedirected.php';
    }

    /**
     * Includes the user chooses location popup template.
     */
    public function include_choose_popup() {
        $redirectChooseTitleMarkup = $this->settings['redirection_choose_title'] ?? '';
        $redirectChooseListMarkup = $this->_generate_redirect_list_markup($this->settings, 'popup-text-goto-link-choose', true); // Use new helper
        $redirectChooseInfo = $this->settings['redirection_choose_info'] ?? '';

        // Escape output with wp_kses_post
        $redirectChooseTitleMarkup = wp_kses_post($redirectChooseTitleMarkup);
        $redirectChooseInfo = wp_kses_post($this->_format_shop_url_text($redirectChooseInfo, '', 'popup-text-goto-url')); // Apply placeholder replacement and escape


        include plugin_dir_path(__FILE__) . 'templates/popupChoose.php';
    }

    /**
     * Includes the footer selector template.
     */
    public function include_footer_selector() {
        $redirectListMarkup = $this->_generate_redirect_list_markup($this->settings, 'popup-text-goto-link', false); // Use new helper
        $redirectBackMarkup = $this->settings['redirection_footer_message'] ?? '';

        // Escape output with wp_kses_post
        $redirectBackMarkup = wp_kses_post($this->_format_shop_url_text($redirectBackMarkup, '', 'popup-text-goto-url')); // Apply placeholder replacement and escape


        include plugin_dir_path(__FILE__) . 'templates/footerSelector.php';
    }

}
