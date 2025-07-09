<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class IpLocationRedirectTemplates {

    private $plugin;
    private $settings;

    public function __construct($plugin_instance) {
        $this->plugin = $plugin_instance;
        
        if (!empty($this->plugin->admin)) {
            $this->settings = $this->plugin->admin->get_settings();
        }
    }

    public function get_domain_and_tld() {
        return $_SERVER['HTTP_HOST'];
    }

    private function get_footer_template_data($settings) {
        $redirectListMarkup = '';

        if (isset($settings['redirects']) && count($settings['redirects'])) {
            $currentUrl = $this->get_domain_and_tld();
            $currentShopLabel = $settings['current_shop_label'];

            foreach ($settings['redirects'] as $key => $redirect) {
                $redirectUrl = str_replace(array('http://', 'https://'), '', $redirect['redirect_url']);
                $before = $redirect['redirect_message_before'];
                $after = $redirect['redirect_message_after'];
                if ($redirectUrl === $currentUrl) {
                    $redirectListMarkup .= '<li>' . esc_html($before) . ' <a href="' . $redirect['redirect_url'] . '" class="popup-text-goto-link">' . $redirectUrl . '</a> ' . esc_html($after) . ' ' . $currentShopLabel .'</li>';
                } else {
                    $redirectListMarkup .= '<li>' . esc_html($before) . ' <a href="' . $redirect['redirect_url'] . '?redirect_chosen=1&redirect_to=' . $redirectUrl . '" class="popup-text-goto-link">' . $redirectUrl . '</a> ' . esc_html($after) . '</li>';
                }
            }
        }

        return $redirectListMarkup;
    }
    
    public function include_footer_selector() {
        if (empty($this->settings)) {
            return false;
        }

        $settings = $this->settings;
        $redirectListMarkup = $this->get_footer_template_data($settings);
        $redirectBackMarkup = $settings['redirection_footer_message'] ?? '';

        include plugin_dir_path(__FILE__) . 'templates/footerSelector.php';
    }

    private function get_template_data($settings) {
        $redirectListMarkup = '';

        if (isset($settings['redirects']) && count($settings['redirects'])) {
            $currentUrl = $this->get_domain_and_tld();
            $currentShopLabel = $settings['current_shop_label'];
            
            foreach ($settings['redirects'] as $key => $redirect) {
                $redirectUrl = str_replace(array('http://', 'https://'), '', $redirect['redirect_url']);
                $before = $redirect['redirect_message_before'];
                $after = $redirect['redirect_message_after'];
                if ($redirectUrl === $currentUrl) {
                    $redirectListMarkup .= '<li>' . esc_html($before) . ' <a href="' . $redirect['redirect_url'] . '" class="popup-text-goto-link popup-close-popup">' . $redirectUrl . '</a> ' . esc_html($after) . ' ' . $currentShopLabel .'</li>';
                } else {
                    $redirectListMarkup .= '<li>' . esc_html($before) . ' <a href="' . $redirect['redirect_url'] . '?redirect_chosen=1&redirect_to=' . $redirectUrl . '" class="popup-text-goto-link">' . $redirectUrl . '</a> ' . esc_html($after) . '</li>';
                }
            }
        }

        return $redirectListMarkup;
    }

    public function include_redirect_popup() {
        if (empty($this->settings)) {
            return false;
        }

        $settings = $this->settings;

        if (isset($_GET['ip_location_redirected_to'])) {
            $redirectFrom = sanitize_text_field($_GET['ip_location_redirected_to']);
        } else {
            error_log( 'Location redirect plugin: could not get $redirectFrom in template.php' );
        }

        // Extract values
        $loaderImage = $settings['loader'];
        $redirectListMarkup = $this->get_template_data($settings);
        $redirectInfo = $settings['redirection_info'] ?? '';

        // Title markup
        $redirectionTitleArray = explode('{{ shopUrl }}', $settings['redirection_title'], 2);
        if (count($redirectionTitleArray) < 2) {
            $redirectionTitleArray = ['', $settings['redirection_title']];
        }
        $redirectTitleMarkup = "{$redirectionTitleArray[0]} <span class=\"popup-title-url\">$redirectFrom</span> {$redirectionTitleArray[1]}";

        // Back text markup
        $redirectionTextArray = explode('{{ shopUrl }}', $settings['redirection_text'], 2);
        if (count($redirectionTextArray) < 2) {
            $redirectionTextArray = ['', $settings['redirection_text']];
        }
        $redirectBackMarkup = "{$redirectionTextArray[0]} <span class=\"popup-text-goto-url\">$redirectFrom</span> {$redirectionTextArray[1]}";

        include plugin_dir_path(__FILE__) . 'templates/popupRedirected.php';
    }

    private function get_choose_template_data($settings) {
        $redirectListMarkup = '';

        if (isset($settings['redirects']) && count($settings['redirects'])) {
            $currentUrl = $this->get_domain_and_tld();
            $currentShopLabel = $settings['current_shop_label'];

            foreach ($settings['redirects'] as $key => $redirect) {
                $redirectUrl = str_replace(array('http://', 'https://'), '', $redirect['redirect_url']);
                $before = $redirect['redirect_message_before'];
                $after = $redirect['redirect_message_after'];
                if ($redirectUrl === $currentUrl) {
                    $redirectListMarkup .= '<li>' . esc_html($before) . ' <a href="' . $redirect['redirect_url'] . '" class="popup-text-goto-link-choose popup-close-popup">' . $redirectUrl . '</a> ' . esc_html($after) . ' ' . $currentShopLabel . '</li>';
                } else {
                    $redirectListMarkup .= '<li>' . esc_html($before) . ' <a href="' . $redirect['redirect_url'] . '?redirect_chosen=1&redirect_to=' . $redirectUrl . '" class="popup-text-goto-link-choose">' . $redirectUrl . '</a> ' . esc_html($after) . '</li>';
                }
            }
        }

        return $redirectListMarkup;
    }

    public function include_choose_popup() {
        if (empty($this->settings)) {
            return false;
        }

        $settings = $this->settings;

        // Extract values
        $loaderImage = $settings['loader'] ?? '';
        $redirectChooseTitleMarkup = $settings['redirection_choose_title'] ?? '';
        $redirectChooseListMarkup = $this->get_choose_template_data($settings);
        $redirectChooseInfo = $settings['redirection_choose_info'] ?? '';

        include plugin_dir_path(__FILE__) . 'templates/popupChoose.php';
    }

}