<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

if (!defined('WPINC')) {
    define('WPINC', 'wp-includes');
}

if (!defined('LIGHTWEIGHT_SEO_OPTION_NAME')) {
    define('LIGHTWEIGHT_SEO_OPTION_NAME', 'lightweight_seo_settings');
}

if (!defined('LIGHTWEIGHT_SEO_DEFAULT_TITLE_FORMAT')) {
    define('LIGHTWEIGHT_SEO_DEFAULT_TITLE_FORMAT', '%title% – %sitename%');
}

if (!defined('LIGHTWEIGHT_SEO_DEFAULT_SEPARATOR')) {
    define('LIGHTWEIGHT_SEO_DEFAULT_SEPARATOR', '–');
}

if (!defined('LIGHTWEIGHT_SEO_PLUGIN_FILE')) {
    define('LIGHTWEIGHT_SEO_PLUGIN_FILE', dirname(__DIR__) . '/lightweight-seo.php');
}

if (!defined('LIGHTWEIGHT_SEO_PLUGIN_URL')) {
    define('LIGHTWEIGHT_SEO_PLUGIN_URL', 'https://example.com/wp-content/plugins/lightweight-seo/');
}

$lightweight_seo_test_nonce_is_valid = true;
$lightweight_seo_test_user_can = true;
$lightweight_seo_test_settings_errors = array();
$lightweight_seo_test_options = array();
$lightweight_seo_test_post_meta = array();
$lightweight_seo_test_attachment_urls = array();
$lightweight_seo_test_rendered_settings_errors = array();
$lightweight_seo_test_screen = null;

if (!function_exists('add_action')) {
    function add_action(...$args) {
        return true;
    }
}

if (!function_exists('add_filter')) {
    function add_filter(...$args) {
        return true;
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters($hook_name, $value, ...$args) {
        return $value;
    }
}

if (!function_exists('do_action')) {
    function do_action(...$args) {
        return null;
    }
}

if (!function_exists('__')) {
    function __($text, $domain = null) {
        return $text;
    }
}

if (!function_exists('_e')) {
    function _e($text, $domain = null) {
        echo $text;
    }
}

if (!function_exists('checked')) {
    function checked($checked, $current = true, $display = true) {
        return $checked == $current ? 'checked="checked"' : '';
    }
}

if (!function_exists('absint')) {
    function absint($maybeint) {
        return abs((int) $maybeint);
    }
}

if (!function_exists('wp_unslash')) {
    function wp_unslash($value) {
        if (is_array($value)) {
            return array_map('wp_unslash', $value);
        }

        return is_string($value) ? stripslashes($value) : $value;
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($value) {
        return trim(strip_tags((string) $value));
    }
}

if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($value) {
        return trim(strip_tags((string) $value));
    }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw($value) {
        return filter_var((string) $value, FILTER_SANITIZE_URL);
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_url')) {
    function esc_url($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_html')) {
    function esc_html($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_textarea')) {
    function esc_textarea($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('wp_specialchars_decode')) {
    function wp_specialchars_decode($string, $quote_style = ENT_NOQUOTES) {
        return html_entity_decode($string, $quote_style, 'UTF-8');
    }
}

if (!function_exists('wp_parse_args')) {
    function wp_parse_args($args, $defaults = array()) {
        if (is_object($args)) {
            $args = get_object_vars($args);
        }

        if (!is_array($args)) {
            $args = array();
        }

        return array_merge($defaults, $args);
    }
}

if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce($nonce, $action) {
        global $lightweight_seo_test_nonce_is_valid;

        return $lightweight_seo_test_nonce_is_valid;
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can($capability, $object_id = null) {
        global $lightweight_seo_test_user_can;

        return $lightweight_seo_test_user_can;
    }
}

if (!function_exists('get_bloginfo')) {
    function get_bloginfo($show = '', $filter = 'raw') {
        if ('name' === $show) {
            return 'Test Site';
        }

        if ('description' === $show) {
            return 'Test Tagline';
        }

        if ('charset' === $show) {
            return 'UTF-8';
        }

        return '';
    }
}

if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        global $lightweight_seo_test_options;

        return $lightweight_seo_test_options[$option] ?? $default;
    }
}

if (!function_exists('add_settings_error')) {
    function add_settings_error($setting, $code, $message, $type = 'error') {
        global $lightweight_seo_test_settings_errors;

        $lightweight_seo_test_settings_errors[] = compact('setting', 'code', 'message', 'type');

        return true;
    }
}

if (!function_exists('settings_errors')) {
    function settings_errors($setting = '', $sanitize = false, $hide_on_update = false) {
        global $lightweight_seo_test_rendered_settings_errors;

        $lightweight_seo_test_rendered_settings_errors[] = $setting;

        echo '<div class="settings-errors" data-setting="' . esc_attr($setting) . '"></div>';

        return true;
    }
}

if (!function_exists('get_current_screen')) {
    function get_current_screen() {
        global $lightweight_seo_test_screen;

        return $lightweight_seo_test_screen;
    }
}

if (!function_exists('get_post_types')) {
    function get_post_types($args = array(), $output = 'names') {
        return array('post', 'page');
    }
}

if (!function_exists('post_type_exists')) {
    function post_type_exists($post_type) {
        return in_array($post_type, array('post', 'page'), true);
    }
}

if (!function_exists('register_post_meta')) {
    function register_post_meta($post_type, $meta_key, $args) {
        return true;
    }
}

if (!function_exists('get_post_meta')) {
    function get_post_meta($post_id, $key = '', $single = false) {
        global $lightweight_seo_test_post_meta;

        $value = $lightweight_seo_test_post_meta[$post_id][$key] ?? ($single ? '' : array());

        if ($single) {
            return $value;
        }

        return array($value);
    }
}

if (!function_exists('update_post_meta')) {
    function update_post_meta($post_id, $key, $value, $prev_value = '') {
        global $lightweight_seo_test_post_meta;

        if (!isset($lightweight_seo_test_post_meta[$post_id])) {
            $lightweight_seo_test_post_meta[$post_id] = array();
        }

        $lightweight_seo_test_post_meta[$post_id][$key] = $value;

        return true;
    }
}

if (!function_exists('wp_get_attachment_image_url')) {
    function wp_get_attachment_image_url($attachment_id, $size = 'thumbnail') {
        global $lightweight_seo_test_attachment_urls;

        return $lightweight_seo_test_attachment_urls[$attachment_id] ?? '';
    }
}

if (!function_exists('plugin_basename')) {
    function plugin_basename($file) {
        return basename(dirname($file)) . '/' . basename($file);
    }
}

if (!function_exists('settings_fields')) {
    function settings_fields($group) {
        echo '<input type="hidden" name="option_page" value="' . esc_attr($group) . '">';
    }
}

if (!function_exists('do_settings_sections')) {
    function do_settings_sections($page) {
        return true;
    }
}

if (!function_exists('submit_button')) {
    function submit_button() {
        echo '<button type="submit">Save</button>';
    }
}

if (!function_exists('load_plugin_textdomain')) {
    function load_plugin_textdomain($domain, $deprecated = false, $plugin_rel_path = false) {
        return true;
    }
}

if (!function_exists('wp_enqueue_media')) {
    function wp_enqueue_media() {
        return true;
    }
}

if (!function_exists('wp_enqueue_script')) {
    function wp_enqueue_script(...$args) {
        return true;
    }
}

if (!function_exists('wp_enqueue_style')) {
    function wp_enqueue_style(...$args) {
        return true;
    }
}

if (!function_exists('wp_localize_script')) {
    function wp_localize_script(...$args) {
        return true;
    }
}

if (!function_exists('admin_url')) {
    function admin_url($path = '') {
        return 'https://example.com/wp-admin/' . ltrim($path, '/');
    }
}

if (!function_exists('get_admin_page_title')) {
    function get_admin_page_title() {
        return 'Lightweight SEO Settings';
    }
}
