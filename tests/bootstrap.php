<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

if (!defined('WPINC')) {
    define('WPINC', 'wp-includes');
}

if (!defined('LIGHTWEIGHT_SEO_OPTION_NAME')) {
    define('LIGHTWEIGHT_SEO_OPTION_NAME', 'lightweight_seo_settings');
}

if (!defined('LIGHTWEIGHT_SEO_MODULES_OPTION_NAME')) {
    define('LIGHTWEIGHT_SEO_MODULES_OPTION_NAME', 'lightweight_seo_modules');
}

// The plugin file is the one place the version and the schema version are written, so both are read from it.
$lightweight_seo_test_plugin_source = (string) file_get_contents(dirname(__DIR__) . '/lightweight-seo.php');

if (!defined('LIGHTWEIGHT_SEO_SCHEMA_VERSION')) {
    if (1 !== preg_match("/define\(\s*'LIGHTWEIGHT_SEO_SCHEMA_VERSION',\s*(\d+)\s*\)/", $lightweight_seo_test_plugin_source, $lightweight_seo_test_match)) {
        throw new RuntimeException('LIGHTWEIGHT_SEO_SCHEMA_VERSION was not found in lightweight-seo.php.');
    }

    define('LIGHTWEIGHT_SEO_SCHEMA_VERSION', (int) $lightweight_seo_test_match[1]);
}

if (!defined('LIGHTWEIGHT_SEO_VERSION')) {
    if (1 !== preg_match('/^ \* Version:\s*(\S+)/m', $lightweight_seo_test_plugin_source, $lightweight_seo_test_match)) {
        throw new RuntimeException('The Version header was not found in lightweight-seo.php.');
    }

    define('LIGHTWEIGHT_SEO_VERSION', $lightweight_seo_test_match[1]);
}

unset($lightweight_seo_test_plugin_source, $lightweight_seo_test_match);

if (!defined('LIGHTWEIGHT_SEO_API_VERSION')) {
    define('LIGHTWEIGHT_SEO_API_VERSION', '1.0');
}

if (!defined('LIGHTWEIGHT_SEO_SCHEMA_VERSION_OPTION')) {
    define('LIGHTWEIGHT_SEO_SCHEMA_VERSION_OPTION', 'lightweight_seo_schema_version');
}

if (!defined('LIGHTWEIGHT_SEO_PLUGIN_DIR')) {
    define('LIGHTWEIGHT_SEO_PLUGIN_DIR', dirname(__DIR__) . '/');
}

if (!defined('LIGHTWEIGHT_SEO_DEFAULT_TITLE_FORMAT')) {
    define('LIGHTWEIGHT_SEO_DEFAULT_TITLE_FORMAT', '%title% – %sitename%');
}

if (!defined('LIGHTWEIGHT_SEO_DEFAULT_SEPARATOR')) {
    define('LIGHTWEIGHT_SEO_DEFAULT_SEPARATOR', '–');
}

if (!defined('LIGHTWEIGHT_SEO_INSPECT_ARG')) {
    define('LIGHTWEIGHT_SEO_INSPECT_ARG', 'lightweight_seo_inspect');
}

if (!defined('LIGHTWEIGHT_SEO_PLUGIN_URL')) {
    define('LIGHTWEIGHT_SEO_PLUGIN_URL', 'https://example.com/wp-content/plugins/lightweight-seo/');
}

if (!defined('LIGHTWEIGHT_SEO_PLUGIN_FILE')) {
    define('LIGHTWEIGHT_SEO_PLUGIN_FILE', dirname(__DIR__) . '/lightweight-seo.php');
}

if (!defined('LIGHTWEIGHT_SEO_PLUGIN_URL')) {
    define('LIGHTWEIGHT_SEO_PLUGIN_URL', 'https://example.com/wp-content/plugins/lightweight-seo/');
}

$lightweight_seo_test_nonce_is_valid = true;
$lightweight_seo_test_user_can = true;
$lightweight_seo_test_user_roles = array();
$lightweight_seo_test_environment_type = 'production';
$lightweight_seo_test_filters = array();
$lightweight_seo_test_settings_errors = array();
$lightweight_seo_test_options = array();
$lightweight_seo_test_post_meta = array();
$lightweight_seo_test_attachment_urls = array();
$lightweight_seo_test_rendered_settings_errors = array();
$lightweight_seo_test_posts = array();
$lightweight_seo_test_term_meta = array();
$lightweight_seo_test_user_meta = array();
$lightweight_seo_test_remote_post_responses = array();
$lightweight_seo_test_remote_get_responses = array();
$lightweight_seo_test_scheduled_events = array();
$lightweight_seo_test_cleared_scheduled_events = array();
$lightweight_seo_test_registered_sitemap_providers = array();
$lightweight_seo_test_cache = array();
$lightweight_seo_test_get_posts_calls = 0;
$lightweight_seo_test_blog_id = 1;
$lightweight_seo_test_network_admin = false;
$lightweight_seo_test_wp_redirect_calls = array();
$lightweight_seo_test_wp_safe_redirect_calls = array();
$lightweight_seo_test_option_autoload = array();
$lightweight_seo_test_transients = array();
$lightweight_seo_test_registered_settings = array();
$lightweight_seo_test_menu_pages = array();
$lightweight_seo_test_authors = array(
    17 => array(
        'display_name' => 'Author Name',
        'description' => '',
    ),
);
$lightweight_seo_test_screen = null;
$lightweight_seo_test_query_state = array(
    'is_singular' => false,
    'is_single' => false,
    'is_author' => false,
    'is_home' => false,
    'is_front_page' => false,
    'is_category' => false,
    'is_tag' => false,
    'is_tax' => false,
    'is_archive' => false,
    'is_search' => false,
    'is_404' => false,
    'queried_object_id' => 0,
    'search_query' => '',
    'permalink' => 'https://example.com/current-page',
    'title' => 'Test Title',
    'thumbnail_url' => '',
    'current_request' => '',
    'queried_object' => null,
    'post_author' => 0,
    'published_date' => '2024-01-01T12:00:00+00:00',
    'modified_date' => '2024-01-02T12:00:00+00:00',
);

$GLOBALS['wp'] = (object) array(
    'request' => '',
);

require_once dirname(__DIR__) . '/includes/class-lightweight-seo-url.php';
require_once dirname(__DIR__) . '/includes/class-lightweight-seo-text.php';

if (!function_exists('esc_attr_e')) {
    function esc_attr_e($text, $domain = null) {
        echo esc_attr($text);
    }
}

if (!function_exists('esc_js')) {
    function esc_js($text) {
        return addslashes(htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'));
    }
}

if (!function_exists('get_query_var')) {
    function get_query_var($query_var, $default_value = '') {
        global $lightweight_seo_test_query_state;

        return $lightweight_seo_test_query_state['query_vars'][$query_var] ?? $default_value;
    }
}

if (!function_exists('user_trailingslashit')) {
    function user_trailingslashit($url, $type_of_url = '') {
        return rtrim($url, '/') . '/';
    }
}

$lightweight_seo_test_hooks = array();
$lightweight_seo_test_hook_callbacks = array();
$lightweight_seo_test_home_url = 'https://example.com';

if (!function_exists('add_action')) {
    function add_action(...$args) {
        global $lightweight_seo_test_hooks;
        global $lightweight_seo_test_hook_callbacks;

        $lightweight_seo_test_hooks[] = (string) $args[0];
        $lightweight_seo_test_hook_callbacks[(string) $args[0]][] = $args[1] ?? null;

        return true;
    }
}

if (!function_exists('add_filter')) {
    function add_filter(...$args) {
        global $lightweight_seo_test_hooks;
        global $lightweight_seo_test_hook_callbacks;

        $lightweight_seo_test_hooks[] = (string) $args[0];
        $lightweight_seo_test_hook_callbacks[(string) $args[0]][] = $args[1] ?? null;

        return true;
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters($hook_name, $value, ...$args) {
		global $lightweight_seo_test_filters;

		if ( isset( $lightweight_seo_test_filters[ $hook_name ] ) && is_callable( $lightweight_seo_test_filters[ $hook_name ] ) ) {
			return $lightweight_seo_test_filters[ $hook_name ]( $value, ...$args );
		}

        return $value;
    }
}

if (!function_exists('selected')) {
    function selected($selected, $current = true, $display = true) {
        // Like WordPress, print the attribute unless the caller asks for it back.
        $result = (string) $selected === (string) $current ? " selected='selected'" : '';

        if ($display) {
            echo $result;
        }

        return $result;
    }
}

if (!function_exists('do_action')) {
    function do_action(...$args) {
        return null;
    }
}

if (!function_exists('__return_null')) {
    function __return_null() {
        return null;
    }
}

if (!function_exists('_x')) {
    function _x($text, $context, $domain = null) {
        return $text;
    }
}

if (!function_exists('__')) {
    function __($text, $domain = null) {
        return $text;
    }
}

if (!function_exists('esc_html__')) {
    function esc_html__($text, $domain = null) {
        return esc_html($text);
    }
}

if (!function_exists('esc_html_e')) {
    function esc_html_e($text, $domain = null) {
        echo esc_html($text);
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
        $value = trim(strip_tags((string) $value));

        // Like WordPress, remove anything shaped like a percent-encoded byte.
        while (preg_match('/%[a-f0-9]{2}/i', $value, $match)) {
            $value = str_replace($match[0], '', $value);
        }

        return trim($value);
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key($key) {
        return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $key));
    }
}

if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($value) {
        $value = trim(strip_tags((string) $value));

        // Like WordPress, remove anything shaped like a percent-encoded byte.
        while (preg_match('/%[a-f0-9]{2}/i', $value, $match)) {
            $value = str_replace($match[0], '', $value);
        }

        return trim($value);
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

if (!function_exists('trailingslashit')) {
    function trailingslashit($value) {
        return rtrim((string) $value, '/') . '/';
    }
}

if (!function_exists('untrailingslashit')) {
    function untrailingslashit($value) {
        return rtrim((string) $value, '/');
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

if (!function_exists('is_user_logged_in')) {
    function is_user_logged_in() {
		global $lightweight_seo_test_user_roles;

		return ! empty( $lightweight_seo_test_user_roles );
	}
}

if (!function_exists('wp_get_current_user')) {
    function wp_get_current_user() {
		global $lightweight_seo_test_user_roles;

		return (object) array( 'roles' => $lightweight_seo_test_user_roles );
	}
}

if (!function_exists('wp_get_environment_type')) {
    function wp_get_environment_type() {
		global $lightweight_seo_test_environment_type;

		return $lightweight_seo_test_environment_type;
	}
}

if (!function_exists('get_bloginfo')) {
    function get_bloginfo($show = '', $filter = 'raw') {
        if ('name' === $show) {
            return $GLOBALS['lightweight_seo_test_site_name'] ?? 'Test Site';
        }

        if ('description' === $show) {
            // A test can stand for a site without a tagline, which is how WordPress installs.
            return $GLOBALS['lightweight_seo_test_tagline'] ?? 'Test Tagline';
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

if (!function_exists('wp_parse_args')) {
    function wp_parse_args($args, $defaults = array()) {
        return array_merge($defaults, (array) $args);
    }
}

if (!function_exists('update_option')) {
    function update_option($option, $value, $autoload = null) {
        global $lightweight_seo_test_options;
        global $lightweight_seo_test_option_autoload;

        $lightweight_seo_test_options[$option] = $value;
        $lightweight_seo_test_option_autoload[$option] = $autoload;

        return true;
    }
}

if (!function_exists('delete_option')) {
    function delete_option($option) {
        global $lightweight_seo_test_options;

        unset($lightweight_seo_test_options[$option]);

        return true;
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

if (!function_exists('get_taxonomies')) {
    function get_taxonomies($args = array(), $output = 'names') {
        return array('category', 'post_tag');
    }
}

if (!function_exists('get_taxonomy')) {
    function get_taxonomy($taxonomy) {
        $labels = array(
            'category' => 'Categories',
            'post_tag' => 'Tags',
        );

        return isset($labels[$taxonomy]) ? (object) array('name' => $taxonomy, 'labels' => (object) array('name' => $labels[$taxonomy])) : false;
    }
}

if (!function_exists('current_theme_supports')) {
    function current_theme_supports($feature) {
        return false;
    }
}

if (!function_exists('post_type_exists')) {
    function post_type_exists($post_type) {
        return in_array($post_type, array('post', 'page'), true);
    }
}

if (!function_exists('taxonomy_exists')) {
    function taxonomy_exists($taxonomy) {
        return in_array($taxonomy, array('category', 'post_tag'), true);
    }
}

if (!function_exists('register_post_meta')) {
    function register_post_meta($post_type, $meta_key, $args) {
        global $lightweight_seo_test_registered_post_meta;

        $lightweight_seo_test_registered_post_meta[$meta_key] = $args;

        return true;
    }
}

if (!function_exists('register_term_meta')) {
    function register_term_meta($taxonomy, $meta_key, $args) {
        return true;
    }
}

if (!function_exists('register_meta')) {
    function register_meta($object_type, $meta_key, $args) {
        return true;
    }
}

if (!class_exists('WP_Sitemaps_Provider')) {
    abstract class WP_Sitemaps_Provider {
        protected $name = '';
        protected $object_type = '';

        abstract public function get_url_list($page_num, $object_subtype = '');
        abstract public function get_max_num_pages($object_subtype = '');
        public function get_object_subtypes() {
            return array();
        }
    }
}

if (!function_exists('get_post_meta')) {
    function get_post_meta($post_id, $meta_key = '', $single = false) {
        global $lightweight_seo_test_post_meta;

        $value = $lightweight_seo_test_post_meta[$post_id][$meta_key] ?? ($single ? '' : array());

        if ($single) {
            return $value;
        }

        return array($value);
    }
}

if (!function_exists('update_post_meta')) {
    function update_post_meta($post_id, $meta_key, $meta_value, $prev_value = '') {
        global $lightweight_seo_test_post_meta;

        if (!isset($lightweight_seo_test_post_meta[$post_id])) {
            $lightweight_seo_test_post_meta[$post_id] = array();
        }

        $lightweight_seo_test_post_meta[$post_id][$meta_key] = $meta_value;

        return true;
    }
}

if (!function_exists('delete_post_meta')) {
    function delete_post_meta($post_id, $meta_key, $meta_value = '') {
        global $lightweight_seo_test_post_meta;

        unset($lightweight_seo_test_post_meta[$post_id][$meta_key]);

        return true;
    }
}

if (!function_exists('get_post')) {
    function get_post($post = null, $output = 'OBJECT', $filter = 'raw') {
        global $lightweight_seo_test_posts;

        if (is_object($post)) {
            return $post;
        }

        return $lightweight_seo_test_posts[(int) $post] ?? null;
    }
}

if (!function_exists('get_posts')) {
    function get_posts($args = array()) {
        global $lightweight_seo_test_get_posts_calls;
        global $lightweight_seo_test_post_meta;
        global $lightweight_seo_test_posts;

        $lightweight_seo_test_get_posts_calls++;
        $posts = array_values($lightweight_seo_test_posts);

        if (isset($args['post_type'])) {
            $allowed_post_types = (array) $args['post_type'];
            $posts = array_values(array_filter($posts, function ($post) use ($allowed_post_types) {
                return in_array($post->post_type ?? '', $allowed_post_types, true);
            }));
        }

        if (isset($args['post_status'])) {
            $allowed_statuses = (array) $args['post_status'];
            $posts = array_values(array_filter($posts, function ($post) use ($allowed_statuses) {
                return in_array($post->post_status ?? '', $allowed_statuses, true);
            }));
        }

        if (isset($args['post_mime_type']) && 'image' === $args['post_mime_type']) {
            $posts = array_values(array_filter($posts, function ($post) {
                return 0 === strpos((string) ($post->post_mime_type ?? ''), 'image/');
            }));
        }

        if (isset($args['post_mime_type']) && 'video' === $args['post_mime_type']) {
            $posts = array_values(array_filter($posts, function ($post) {
                return 0 === strpos((string) ($post->post_mime_type ?? ''), 'video/');
            }));
        }

        if (!empty($args['post__not_in'])) {
            $excluded_ids = array_map('intval', (array) $args['post__not_in']);
            $posts = array_values(array_filter($posts, function ($post) use ($excluded_ids) {
                return !in_array((int) ($post->ID ?? 0), $excluded_ids, true);
            }));
        }

		if ( ! empty( $args['meta_key'] ) ) {
			$meta_key = (string) $args['meta_key'];
			$posts    = array_values( array_filter( $posts, function ( $post ) use ( $lightweight_seo_test_post_meta, $meta_key ) {
				return '' !== (string) ( $lightweight_seo_test_post_meta[ (int) ( $post->ID ?? 0 ) ][ $meta_key ] ?? '' );
			} ) );
		}

        usort($posts, function ($left, $right) use ($args) {
            if ('date' === ($args['orderby'] ?? '')) {
                return strcmp((string) ($right->post_date_gmt ?? ''), (string) ($left->post_date_gmt ?? ''));
            }

            return ((int) ($left->ID ?? 0)) <=> ((int) ($right->ID ?? 0));
        });

        $posts_per_page = (int) ($args['posts_per_page'] ?? count($posts));
        $paged = max(1, (int) ($args['paged'] ?? 1));

        if ($posts_per_page > -1) {
            $offset = isset($args['offset']) ? max(0, (int) $args['offset']) : ($paged - 1) * $posts_per_page;
            $posts = array_slice($posts, $offset, $posts_per_page);
        }

		if ( 'ids' === ( $args['fields'] ?? '' ) ) {
			return array_map( function ( $post ) {
				return (int) ( $post->ID ?? 0 );
			}, $posts );
		}

        if (($args['fields'] ?? '') === 'ids') {
            return array_map(function ($post) {
                return $post->ID;
            }, $posts);
        }

        return $posts;
    }
}

if (!function_exists('get_term_meta')) {
    function get_term_meta($term_id, $meta_key = '', $single = false) {
        global $lightweight_seo_test_term_meta;

        return $lightweight_seo_test_term_meta[$term_id][$meta_key] ?? '';
    }
}

if (!function_exists('update_term_meta')) {
    function update_term_meta($term_id, $meta_key, $meta_value, $prev_value = '') {
        global $lightweight_seo_test_term_meta;

        if (!isset($lightweight_seo_test_term_meta[$term_id])) {
            $lightweight_seo_test_term_meta[$term_id] = array();
        }

        $lightweight_seo_test_term_meta[$term_id][$meta_key] = $meta_value;

        return true;
    }
}

if (!function_exists('get_user_meta')) {
    function get_user_meta($user_id, $meta_key = '', $single = false) {
        global $lightweight_seo_test_user_meta;

        return $lightweight_seo_test_user_meta[$user_id][$meta_key] ?? '';
    }
}

if (!function_exists('update_user_meta')) {
    function update_user_meta($user_id, $meta_key, $meta_value, $prev_value = '') {
        global $lightweight_seo_test_user_meta;

        if (!isset($lightweight_seo_test_user_meta[$user_id])) {
            $lightweight_seo_test_user_meta[$user_id] = array();
        }

        $lightweight_seo_test_user_meta[$user_id][$meta_key] = $meta_value;

        return true;
    }
}

if (!function_exists('delete_user_meta')) {
    function delete_user_meta($user_id, $meta_key, $meta_value = '') {
        global $lightweight_seo_test_user_meta;

        unset($lightweight_seo_test_user_meta[$user_id][$meta_key]);

        return true;
    }
}

// Weekday names come from the WordPress locale; index 0 is Sunday.
$GLOBALS['wp_locale'] = new class() {
    public function get_weekday($weekday_number) {
        return array('Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday')[(int) $weekday_number];
    }
};

if (!function_exists('get_term')) {
    function get_term($term, $taxonomy = '', $output = 'OBJECT', $filter = 'raw') {
        global $lightweight_seo_test_terms;

        return $lightweight_seo_test_terms[(int) $term] ?? null;
    }
}

if (!function_exists('get_userdata')) {
    function get_userdata($user_id) {
        global $lightweight_seo_test_users;

        return $lightweight_seo_test_users[(int) $user_id] ?? false;
    }
}

if (!function_exists('rest_url')) {
    function rest_url($path = '', $scheme = 'rest') {
        return 'https://example.com/wp-json/' . ltrim($path, '/');
    }
}

if (!function_exists('wp_roles')) {
    function wp_roles() {
        return new class() {
            public function get_names() {
                return array(
                    'administrator' => 'Administrator',
                    'editor' => 'Editor',
                    'subscriber' => 'Subscriber',
                );
            }
        };
    }
}

if (!function_exists('translate_user_role')) {
    function translate_user_role($name, $domain = 'default') {
        return $name;
    }
}

if (!function_exists('get_site_icon_url')) {
    function get_site_icon_url($size = 512, $url = '', $blog_id = 0) {
        global $lightweight_seo_test_site_icon_url;

        return (string) ($lightweight_seo_test_site_icon_url ?? $url);
    }
}

if (!function_exists('get_edit_post_link')) {
    function get_edit_post_link($post = 0, $context = 'display') {
        return 'https://example.com/wp-admin/post.php?post=' . (int) (is_object($post) ? $post->ID : $post) . '&action=edit';
    }
}

if (!function_exists('get_current_user_id')) {
    function get_current_user_id() {
        return 1;
    }
}

if (!function_exists('is_singular')) {
    function is_singular() {
        global $lightweight_seo_test_query_state;

        return (bool) ( $lightweight_seo_test_query_state['is_singular'] ?? false );
    }
}

if (!function_exists('is_single')) {
    function is_single() {
        global $lightweight_seo_test_query_state;

        return (bool) $lightweight_seo_test_query_state['is_single'];
    }
}

if (!function_exists('is_home')) {
    function is_home() {
        global $lightweight_seo_test_query_state;

        return (bool) $lightweight_seo_test_query_state['is_home'];
    }
}

if (!function_exists('is_author')) {
    function is_author() {
        global $lightweight_seo_test_query_state;

        return (bool) $lightweight_seo_test_query_state['is_author'];
    }
}

if (!function_exists('is_front_page')) {
    function is_front_page() {
        global $lightweight_seo_test_query_state;

        return (bool) $lightweight_seo_test_query_state['is_front_page'];
    }
}

if (!function_exists('is_category')) {
    function is_category() {
        global $lightweight_seo_test_query_state;

        return (bool) $lightweight_seo_test_query_state['is_category'];
    }
}

if (!function_exists('is_tag')) {
    function is_tag() {
        global $lightweight_seo_test_query_state;

        return (bool) $lightweight_seo_test_query_state['is_tag'];
    }
}

if (!function_exists('is_tax')) {
    function is_tax() {
        global $lightweight_seo_test_query_state;

        return (bool) $lightweight_seo_test_query_state['is_tax'];
    }
}

if (!function_exists('is_archive')) {
    function is_archive() {
        global $lightweight_seo_test_query_state;

        return (bool) $lightweight_seo_test_query_state['is_archive'];
    }
}

if (!function_exists('is_date')) {
    function is_date() {
        global $lightweight_seo_test_query_state;

        return (bool) ( $lightweight_seo_test_query_state['is_date'] ?? false );
    }
}

if (!function_exists('is_post_type_archive')) {
    function is_post_type_archive() {
        global $lightweight_seo_test_query_state;

        return (bool) ( $lightweight_seo_test_query_state['is_post_type_archive'] ?? false );
    }
}

if (!function_exists('is_search')) {
    function is_search() {
        global $lightweight_seo_test_query_state;

        return (bool) $lightweight_seo_test_query_state['is_search'];
    }
}

if (!function_exists('is_404')) {
    function is_404() {
        global $lightweight_seo_test_query_state;

        return (bool) ( $lightweight_seo_test_query_state['is_404'] ?? false );
    }
}

if (!function_exists('get_queried_object_id')) {
    function get_queried_object_id() {
        global $lightweight_seo_test_query_state;

        return (int) $lightweight_seo_test_query_state['queried_object_id'];
    }
}

if (!function_exists('get_the_title')) {
    function get_the_title($post_id = 0) {
        global $lightweight_seo_test_query_state;

        // Inside a loop, as the Markdown answer of a list of posts runs one.
        if (0 === $post_id && isset($GLOBALS['lightweight_seo_test_loop_post'])) {
            return $GLOBALS['lightweight_seo_test_loop_post']->post_title ?? '';
        }

        return $lightweight_seo_test_query_state['title'];
    }
}

if (!function_exists('get_permalink')) {
    function get_permalink($post_id = 0) {
        global $lightweight_seo_test_posts;
        global $lightweight_seo_test_query_state;

        if (is_object($post_id) && isset($post_id->permalink)) {
            return $post_id->permalink;
        }

        if (0 === $post_id && isset($GLOBALS['lightweight_seo_test_loop_post'])) {
            return $GLOBALS['lightweight_seo_test_loop_post']->permalink ?? '';
        }

        if (isset($lightweight_seo_test_posts[(int) $post_id]) && isset($lightweight_seo_test_posts[(int) $post_id]->permalink)) {
            return $lightweight_seo_test_posts[(int) $post_id]->permalink;
        }

        return $lightweight_seo_test_query_state['permalink'];
    }
}

if (!function_exists('wp_get_attachment_url')) {
    function wp_get_attachment_url($attachment_id = 0) {
        global $lightweight_seo_test_posts;

        $attachment = $lightweight_seo_test_posts[(int) $attachment_id] ?? null;

        if (is_object($attachment) && isset($attachment->attachment_url)) {
            return $attachment->attachment_url;
        }

        return '';
    }
}

if (!function_exists('has_post_thumbnail')) {
    function has_post_thumbnail($post_id = 0) {
        global $lightweight_seo_test_posts;
        global $lightweight_seo_test_query_state;

        if (isset($lightweight_seo_test_posts[(int) $post_id]) && !empty($lightweight_seo_test_posts[(int) $post_id]->thumbnail_id)) {
            return true;
        }

        return '' !== $lightweight_seo_test_query_state['thumbnail_url'];
    }
}

if (!function_exists('get_the_post_thumbnail_url')) {
    function get_the_post_thumbnail_url($post_id = 0, $size = 'post-thumbnail') {
        global $lightweight_seo_test_posts;
        global $lightweight_seo_test_query_state;

        if (isset($lightweight_seo_test_posts[(int) $post_id]) && !empty($lightweight_seo_test_posts[(int) $post_id]->thumbnail_url)) {
            return $lightweight_seo_test_posts[(int) $post_id]->thumbnail_url;
        }

        return $lightweight_seo_test_query_state['thumbnail_url'];
    }
}

if (!function_exists('get_post_thumbnail_id')) {
    function get_post_thumbnail_id($post_id = 0) {
        global $lightweight_seo_test_posts;

        return (int) ($lightweight_seo_test_posts[(int) $post_id]->thumbnail_id ?? 0);
    }
}

if (!function_exists('wp_get_attachment_metadata')) {
    function wp_get_attachment_metadata($attachment_id = 0, $unfiltered = false) {
        global $lightweight_seo_test_posts;

        return $lightweight_seo_test_posts[(int) $attachment_id]->metadata ?? array();
    }
}

if (!function_exists('home_url')) {
    function home_url($path = '') {
        global $lightweight_seo_test_home_url;

        return $lightweight_seo_test_home_url . $path;
    }
}

if (!function_exists('get_locale')) {
    function get_locale() {
        return 'en_US';
    }
}

if (!function_exists('add_query_arg')) {
    function add_query_arg(...$args) {
        // Like WordPress: either an array of arguments and an address, or a key, a value, and an address.
        if (is_array($args[0] ?? null)) {
            $params = $args[0];
            $url    = (string) ($args[1] ?? '');
        } else {
            $params = array((string) ($args[0] ?? '') => $args[1] ?? '');
            $url    = (string) ($args[2] ?? '');
        }

        if (empty($params)) {
            return $url;
        }

        return $url . (false === strpos($url, '?') ? '?' : '&') . http_build_query($params);
    }
}

if (!function_exists('get_search_query')) {
    function get_search_query() {
        global $lightweight_seo_test_query_state;

        return $lightweight_seo_test_query_state['search_query'];
    }
}

if (!function_exists('get_search_link')) {
    function get_search_link($search_query = '') {
        return 'https://example.com/?s=' . rawurlencode((string) $search_query);
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($value, $flags = 0, $depth = 512) {
        return json_encode($value, $flags, $depth);
    }
}

if (!function_exists('wp_remote_post')) {
    function wp_remote_post($url, $args = array()) {
        global $lightweight_seo_test_remote_post_responses;

        $response = $lightweight_seo_test_remote_post_responses[$url] ?? new WP_Error('missing_stub', 'Missing wp_remote_post stub for ' . $url);

        if (is_callable($response)) {
            return $response($url, $args);
        }

        return $response;
    }
}

if (!function_exists('wp_remote_get')) {
    function wp_remote_get($url, $args = array()) {
        global $lightweight_seo_test_remote_get_responses;

        $response = $lightweight_seo_test_remote_get_responses[$url] ?? new WP_Error('missing_stub', 'Missing wp_remote_get stub for ' . $url);

        if (is_callable($response)) {
            return $response($url, $args);
        }

        return $response;
    }
}

if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response) {
        return (int) ($response['response']['code'] ?? 0);
    }
}

if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response) {
        return (string) ($response['body'] ?? '');
    }
}

if (!function_exists('wp_next_scheduled')) {
    function wp_next_scheduled($hook, $args = array()) {
        global $lightweight_seo_test_scheduled_events;

        return $lightweight_seo_test_scheduled_events[$hook] ?? false;
    }
}

if (!function_exists('wp_schedule_event')) {
    function wp_schedule_event($timestamp, $recurrence, $hook, $args = array(), $wp_error = false) {
        global $lightweight_seo_test_scheduled_events;

        $lightweight_seo_test_scheduled_events[$hook] = (int) $timestamp;

        return true;
    }
}

if (!function_exists('wp_clear_scheduled_hook')) {
    function wp_clear_scheduled_hook($hook, $args = array(), $wp_error = false) {
        global $lightweight_seo_test_cleared_scheduled_events;
        global $lightweight_seo_test_scheduled_events;

        $lightweight_seo_test_cleared_scheduled_events[] = $hook;
        unset($lightweight_seo_test_scheduled_events[$hook]);

        return 1;
    }
}

if (!function_exists('is_multisite')) {
    function is_multisite() {
        return false;
    }
}

if (!function_exists('wp_cache_get')) {
    function wp_cache_get($key, $group = '') {
        global $lightweight_seo_test_cache;

        return $lightweight_seo_test_cache[$group][$key] ?? false;
    }
}

if (!function_exists('wp_cache_set')) {
    function wp_cache_set($key, $data, $group = '', $expire = 0) {
        global $lightweight_seo_test_cache;

        if (!isset($lightweight_seo_test_cache[$group])) {
            $lightweight_seo_test_cache[$group] = array();
        }

        $lightweight_seo_test_cache[$group][$key] = $data;

        return true;
    }
}

if (!function_exists('wp_cache_delete')) {
    function wp_cache_delete($key, $group = '') {
        global $lightweight_seo_test_cache;

        unset($lightweight_seo_test_cache[$group][$key]);

        return true;
    }
}

if (!function_exists('get_current_blog_id')) {
    function get_current_blog_id() {
        global $lightweight_seo_test_blog_id;

        return (int) $lightweight_seo_test_blog_id;
    }
}

if (!function_exists('is_network_admin')) {
    function is_network_admin() {
        global $lightweight_seo_test_network_admin;

        return (bool) $lightweight_seo_test_network_admin;
    }
}

if (!function_exists('wp_sitemaps_get_max_urls')) {
    function wp_sitemaps_get_max_urls($object_type = '') {
        return 2000;
    }
}

if (!function_exists('wp_register_sitemap_provider')) {
    function wp_register_sitemap_provider($name, $provider) {
        global $lightweight_seo_test_registered_sitemap_providers;

        $lightweight_seo_test_registered_sitemap_providers[$name] = $provider;

        return true;
    }
}

if (!function_exists('wp_parse_url')) {
    function wp_parse_url($url, $component = -1) {
        return parse_url($url, $component);
    }
}

if (!function_exists('get_queried_object')) {
    function get_queried_object() {
        global $lightweight_seo_test_query_state;

        return $lightweight_seo_test_query_state['queried_object'];
    }
}

if (!function_exists('get_the_archive_title')) {
    function get_the_archive_title() {
        global $lightweight_seo_test_query_state;

        return $lightweight_seo_test_query_state['title'];
    }
}

if (!function_exists('get_the_archive_description')) {
    function get_the_archive_description() {
        return '';
    }
}

if (!function_exists('get_term_link')) {
    function get_term_link($term) {
        // WordPress accepts a term object or a term ID.
        return 'https://example.com/term/' . ( is_object( $term ) ? $term->term_id : (int) $term );
    }
}

if (!class_exists('WP_Error')) {
    class WP_Error {
        private $code;
        private $message;

        public function __construct($code = '', $message = '') {
            $this->code = $code;
            $this->message = $message;
        }

        public function get_error_message() {
            return $this->message;
        }
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return $thing instanceof WP_Error;
    }
}

if (!function_exists('get_the_date')) {
    function get_the_date($format = '', $post_id = 0) {
        global $lightweight_seo_test_query_state;

        return $lightweight_seo_test_query_state['published_date'] ?? '';
    }
}

if (!function_exists('get_the_modified_date')) {
    function get_the_modified_date($format = '', $post_id = 0) {
        global $lightweight_seo_test_query_state;

        return $lightweight_seo_test_query_state['modified_date'] ?? '';
    }
}

if (!function_exists('get_the_category')) {
    function get_the_category($post_id = false) {
        global $lightweight_seo_test_categories;

        return $lightweight_seo_test_categories[(int) $post_id] ?? array();
    }
}

if (!function_exists('wp_date')) {
    function wp_date($format, $timestamp = null, $timezone = null) {
        return gmdate($format, null === $timestamp ? time() : $timestamp);
    }
}

if (!function_exists('wp_get_document_title')) {
    function wp_get_document_title() {
        global $lightweight_seo_test_query_state;

        return $lightweight_seo_test_query_state['document_title'] ?? '';
    }
}

if (!function_exists('wp_get_canonical_url')) {
    function wp_get_canonical_url($post = null) {
        global $lightweight_seo_test_query_state;

        return $lightweight_seo_test_query_state['wp_canonical'] ?? '';
    }
}

if (!function_exists('remove_query_arg')) {
    function remove_query_arg($keys, $url = false) {
        $parts = explode('?', (string) $url, 2);

        if (empty($parts[1])) {
            return $parts[0];
        }

        parse_str($parts[1], $query);

        foreach ((array) $keys as $key) {
            unset($query[$key]);
        }

        return $parts[0] . (empty($query) ? '' : '?' . http_build_query($query));
    }
}

if (!function_exists('includes_url')) {
    function includes_url($path = '') {
        return 'https://example.com/wp-includes/' . ltrim($path, '/');
    }
}

if (!function_exists('get_language_attributes')) {
    function get_language_attributes() {
        return 'lang="en-US"';
    }
}

if (!function_exists('post_password_required')) {
    function post_password_required($post = null) {
        $post = $post ?? ($GLOBALS['lightweight_seo_test_loop_post'] ?? null);

        return '' !== (string) ($post->post_password ?? '');
    }
}

if (!function_exists('get_post_type')) {
    function get_post_type($post = null) {
        $post = get_post($post);

        return $post->post_type ?? false;
    }
}

if (!function_exists('wp_is_post_revision')) {
    function wp_is_post_revision($post) {
        $post = get_post($post);

        return 'revision' === ($post->post_type ?? '') ? (int) ($post->post_parent ?? 0) : false;
    }
}

if (!function_exists('get_post_field')) {
    function get_post_field($field, $post_id = 0, $context = 'display') {
        global $lightweight_seo_test_query_state;

        if ('post_author' === $field) {
            return $lightweight_seo_test_query_state['post_author'];
        }

        return '';
    }
}

if (!function_exists('get_the_author_meta')) {
    function get_the_author_meta($field = '', $user_id = false) {
        global $lightweight_seo_test_authors;

        $user_id = (int) $user_id;

        return $lightweight_seo_test_authors[$user_id][$field] ?? '';
    }
}

if (!function_exists('get_author_posts_url')) {
    function get_author_posts_url($author_id, $author_nicename = '') {
        return 'https://example.com/author/' . (int) $author_id;
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

if (!function_exists('wp_nonce_field')) {
    function wp_nonce_field($action = -1, $name = '_wpnonce', $referer = true, $display = true) {
        $field = '<input type="hidden" name="' . esc_attr($name) . '" value="' . esc_attr(wp_create_nonce($action)) . '">';

        if ($display) {
            echo $field;
        }

        return $field;
    }
}

if (!function_exists('_n')) {
    function _n($single, $plural, $number, $domain = null) {
        return 1 === (int) $number ? $single : $plural;
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
        global $lightweight_seo_test_enqueued_scripts;

        // Handle => dependencies, so tests can see what was loaded and in which order.
        $lightweight_seo_test_enqueued_scripts[$args[0]] = $args[2] ?? array();

        return true;
    }
}

if (!function_exists('wp_enqueue_style')) {
    function wp_enqueue_style(...$args) {
        global $lightweight_seo_test_enqueued_styles;

        $lightweight_seo_test_enqueued_styles[] = $args[0];

        return true;
    }
}

if (!function_exists('wp_localize_script')) {
    function wp_localize_script(...$args) {
        global $lightweight_seo_test_localized_scripts;

        $lightweight_seo_test_localized_scripts[$args[0]] = $args[2] ?? array();

        return true;
    }
}

if (!function_exists('admin_url')) {
    function admin_url($path = '') {
        return 'https://example.com/wp-admin/' . ltrim($path, '/');
    }
}

if (!function_exists('wp_safe_redirect')) {
    function wp_safe_redirect($location, $status = 302, $x_redirect_by = 'WordPress') {
        global $lightweight_seo_test_wp_safe_redirect_calls;

        $lightweight_seo_test_wp_safe_redirect_calls[] = array(
            'location' => $location,
            'status' => $status,
            'x_redirect_by' => $x_redirect_by,
        );

        return true;
    }
}

if (!function_exists('wp_redirect')) {
    function wp_redirect($location, $status = 302, $x_redirect_by = 'WordPress') {
        global $lightweight_seo_test_wp_redirect_calls;

        $lightweight_seo_test_wp_redirect_calls[] = array(
            'location' => $location,
            'status' => $status,
            'x_redirect_by' => $x_redirect_by,
        );

        return true;
    }
}

if (!function_exists('get_admin_page_title')) {
    function get_admin_page_title() {
        return 'Lightweight SEO Settings';
    }
}

if (!function_exists('url_to_postid')) {
    function url_to_postid($url) {
        global $lightweight_seo_test_posts;

        $normalized_url = untrailingslashit((string) $url);

        foreach ($lightweight_seo_test_posts as $post) {
            $permalink = (string) ($post->permalink ?? '');

            if ('' !== $permalink && untrailingslashit($permalink) === $normalized_url) {
                return (int) ($post->ID ?? 0);
            }
        }

        return 0;
    }
}

if (!function_exists('get_post_type_object')) {
    function get_post_type_object($post_type) {
		if ( ! in_array( $post_type, array( 'post', 'page' ), true ) ) {
			return null;
		}

		return (object) array(
			'public' => true,
			'labels' => (object) array( 'name' => 'post' === $post_type ? 'Posts' : 'Pages' ),
		);
	}
}

if (!function_exists('is_post_type_viewable')) {
    function is_post_type_viewable($post_type) {
		return is_object( $post_type ) && ! empty( $post_type->public );
	}
}

if (!function_exists('strip_shortcodes')) {
    function strip_shortcodes($content) {
		return $content;
	}
}

if (!function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags($text, $remove_breaks = false) {
		$text = strip_tags( $text );

		return $remove_breaks ? preg_replace( '/[\r\n\t ]+/', ' ', $text ) : $text;
	}
}

if (!function_exists('wp_trim_words')) {
    function wp_trim_words($text, $num_words = 55, $more = null) {
		$words = preg_split( '/\s+/', trim( $text ) );

		if ( count( $words ) <= $num_words ) {
			return $text;
		}

		return implode( ' ', array_slice( $words, 0, $num_words ) ) . ( null === $more ? '&hellip;' : $more );
	}
}

if (!function_exists('wp_list_pluck')) {
    function wp_list_pluck($input_list, $field) {
		return array_map(
			static function ( $item ) use ( $field ) {
				return is_array( $item ) ? ( $item[ $field ] ?? null ) : ( $item->$field ?? null );
			},
			$input_list
		);
	}
}

/**
 * Thrown by the wp_die() stub so a test can observe a request being stopped.
 */
class Lightweight_SEO_Test_WP_Die_Exception extends RuntimeException {
}

if (!function_exists('wp_die')) {
    function wp_die($message = '', $title = '', $args = array()) {
        throw new Lightweight_SEO_Test_WP_Die_Exception((string) $message);
    }
}

if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action = -1) {
        return 'nonce-' . $action;
    }
}

if (!function_exists('wp_nonce_url')) {
    function wp_nonce_url($actionurl, $action = -1, $name = '_wpnonce') {
        return $actionurl . (false === strpos($actionurl, '?') ? '?' : '&') . $name . '=' . wp_create_nonce($action);
    }
}

if (!function_exists('check_admin_referer')) {
    // Like WordPress, stop the request unless the named field carries the nonce created for this exact action.
    function check_admin_referer($action = -1, $query_arg = '_wpnonce') {
        global $lightweight_seo_test_nonce_is_valid;

        $submitted = $_POST[$query_arg] ?? ($_GET[$query_arg] ?? null);

        if (!$lightweight_seo_test_nonce_is_valid || wp_create_nonce($action) !== $submitted) {
            wp_die('The link you followed has expired.');
        }

        return 1;
    }
}

if (!function_exists('esc_attr__')) {
    function esc_attr__($text, $domain = null) {
        return esc_attr($text);
    }
}

if (!function_exists('get_settings_errors')) {
    function get_settings_errors($setting = '', $sanitize = false) {
        global $lightweight_seo_test_settings_errors;

        return $lightweight_seo_test_settings_errors;
    }
}

if (!function_exists('set_transient')) {
    function set_transient($transient, $value, $expiration = 0) {
        global $lightweight_seo_test_transients;

        $lightweight_seo_test_transients[$transient] = $value;

        return true;
    }
}

if (!function_exists('register_setting')) {
    function register_setting($option_group, $option_name, $args = array()) {
        global $lightweight_seo_test_registered_settings;

        $lightweight_seo_test_registered_settings[$option_name] = array(
            'group' => $option_group,
            'args' => $args,
        );
    }
}

if (!function_exists('add_settings_section')) {
    // Mirrors the globals WordPress fills, which the settings page reads to render a tab.
    function add_settings_section($id, $title, $callback, $page, $args = array()) {
        global $wp_settings_sections;

        $wp_settings_sections[$page][$id] = array(
            'id' => $id,
            'title' => $title,
            'callback' => $callback,
        );
    }
}

if (!function_exists('add_settings_field')) {
    function add_settings_field($id, $title, $callback, $page, $section = 'default', $args = array()) {
        global $wp_settings_fields;

        $wp_settings_fields[$page][$section][$id] = array(
            'id' => $id,
            'title' => $title,
            'callback' => $callback,
            'args' => $args,
        );
    }
}

if (!function_exists('add_menu_page')) {
    function add_menu_page($page_title, $menu_title, $capability, $menu_slug, $callback = '', $icon_url = '', $position = null) {
        global $lightweight_seo_test_menu_pages;

        $lightweight_seo_test_menu_pages[$menu_slug] = array(
            'capability' => $capability,
            'callback' => $callback,
        );

        return 'toplevel_page_' . $menu_slug;
    }
}

if (!function_exists('status_header')) {
    function status_header($code, $description = '') {
        global $lightweight_seo_test_status_headers;

        $lightweight_seo_test_status_headers[] = (int) $code;
    }
}

if (!function_exists('nocache_headers')) {
    function nocache_headers() {
        global $lightweight_seo_test_nocache_headers_calls;

        $lightweight_seo_test_nocache_headers_calls = (int) $lightweight_seo_test_nocache_headers_calls + 1;
    }
}

if (!function_exists('remove_action')) {
    function remove_action($hook_name, $callback, $priority = 10) {
        global $lightweight_seo_test_removed_actions;

        $lightweight_seo_test_removed_actions[] = array($hook_name, $callback);

        return true;
    }
}

if (!function_exists('get_post_type_archive_link')) {
    function get_post_type_archive_link($post_type) {
        global $lightweight_seo_test_post_type_archives;

        return $lightweight_seo_test_post_type_archives[$post_type] ?? false;
    }
}

if (!function_exists('is_post_type_hierarchical')) {
    function is_post_type_hierarchical($post_type) {
        return 'page' === $post_type;
    }
}

if (!function_exists('get_object_taxonomies')) {
    function get_object_taxonomies($object_type, $output = 'names') {
        global $lightweight_seo_test_object_taxonomies;

        return $lightweight_seo_test_object_taxonomies[$object_type] ?? array();
    }
}

if (!function_exists('wp_filter_object_list')) {
    function wp_filter_object_list($input_list, $args = array()) {
        return array_filter(
            (array) $input_list,
            static function ($item) use ($args) {
                foreach ($args as $key => $value) {
                    if (($item->$key ?? null) !== $value) {
                        return false;
                    }
                }

                return true;
            }
        );
    }
}

if (!function_exists('get_the_terms')) {
    function get_the_terms($post, $taxonomy) {
        global $lightweight_seo_test_post_terms;

        return $lightweight_seo_test_post_terms[(int) $post][$taxonomy] ?? false;
    }
}

if (!function_exists('is_taxonomy_hierarchical')) {
    function is_taxonomy_hierarchical($taxonomy) {
        return 'category' === $taxonomy;
    }
}

if (!function_exists('get_post_ancestors')) {
    function get_post_ancestors($post) {
        global $lightweight_seo_test_post_ancestors;

        return $lightweight_seo_test_post_ancestors[(int) $post] ?? array();
    }
}

if (!function_exists('get_ancestors')) {
    function get_ancestors($object_id = 0, $object_type = '', $resource_type = '') {
        global $lightweight_seo_test_term_ancestors;

        return $lightweight_seo_test_term_ancestors[(int) $object_id] ?? array();
    }
}

if (!function_exists('get_year_link')) {
    function get_year_link($year) {
        return 'https://example.com/' . (int) $year . '/';
    }
}

if (!function_exists('get_month_link')) {
    function get_month_link($year, $month) {
        return 'https://example.com/' . (int) $year . '/' . sprintf('%02d', (int) $month) . '/';
    }
}

if (!function_exists('date_i18n')) {
    function date_i18n($format, $timestamp_with_offset = false, $gmt = false) {
        return gmdate($format, (int) $timestamp_with_offset);
    }
}

if (!function_exists('wp_register_ability_category')) {
    function wp_register_ability_category($slug, $args) {
        global $lightweight_seo_test_ability_categories;

        $lightweight_seo_test_ability_categories[$slug] = $args;

        return null;
    }
}

if (!function_exists('wp_register_ability')) {
    function wp_register_ability($name, $args) {
        global $lightweight_seo_test_abilities;

        $lightweight_seo_test_abilities[$name] = $args;

        return null;
    }
}

if (!function_exists('wp_script_is')) {
    function wp_script_is($handle, $status = 'enqueued') {
        global $lightweight_seo_test_enqueued_scripts;

        return isset($lightweight_seo_test_enqueued_scripts[$handle]);
    }
}

if (!function_exists('wp_add_inline_script')) {
    function wp_add_inline_script($handle, $data, $position = 'after') {
        global $lightweight_seo_test_inline_scripts;

        $lightweight_seo_test_inline_scripts[$handle][] = $data;

        return true;
    }
}

if (!function_exists('is_admin')) {
    function is_admin() {
        global $lightweight_seo_test_is_admin;

        return !empty($lightweight_seo_test_is_admin);
    }
}

// Conditionals a Markdown answer checks, read from the query state like the others.

if (!function_exists('is_feed')) {
    function is_feed() {
        return !empty($GLOBALS['lightweight_seo_test_query_state']['is_feed']);
    }
}

if (!function_exists('is_embed')) {
    function is_embed() {
        return !empty($GLOBALS['lightweight_seo_test_query_state']['is_embed']);
    }
}

if (!function_exists('is_robots')) {
    function is_robots() {
        return !empty($GLOBALS['lightweight_seo_test_query_state']['is_robots']);
    }
}

if (!function_exists('is_trackback')) {
    function is_trackback() {
        return !empty($GLOBALS['lightweight_seo_test_query_state']['is_trackback']);
    }
}

if (!function_exists('is_favicon')) {
    function is_favicon() {
        return !empty($GLOBALS['lightweight_seo_test_query_state']['is_favicon']);
    }
}

if (!function_exists('is_preview')) {
    function is_preview() {
        return !empty($GLOBALS['lightweight_seo_test_query_state']['is_preview']);
    }
}

if (!function_exists('is_customize_preview')) {
    function is_customize_preview() {
        return !empty($GLOBALS['lightweight_seo_test_query_state']['is_customize_preview']);
    }
}

// The main loop: $lightweight_seo_test_loop holds the posts of the main query.
$lightweight_seo_test_loop = array();
$lightweight_seo_test_loop_index = 0;

if (!function_exists('have_posts')) {
    function have_posts() {
        return $GLOBALS['lightweight_seo_test_loop_index'] < count($GLOBALS['lightweight_seo_test_loop']);
    }
}

if (!function_exists('the_post')) {
    function the_post() {
        $GLOBALS['lightweight_seo_test_loop_post'] = $GLOBALS['lightweight_seo_test_loop'][$GLOBALS['lightweight_seo_test_loop_index']++];
    }
}

if (!function_exists('rewind_posts')) {
    function rewind_posts() {
        $GLOBALS['lightweight_seo_test_loop_index'] = 0;
    }
}

if (!function_exists('wp_reset_postdata')) {
    function wp_reset_postdata() {
        unset($GLOBALS['lightweight_seo_test_loop_post']);
    }
}

if (!function_exists('get_the_content')) {
    function get_the_content() {
        return $GLOBALS['lightweight_seo_test_loop_post']->post_content ?? '';
    }
}

if (!function_exists('get_the_excerpt')) {
    function get_the_excerpt() {
        return $GLOBALS['lightweight_seo_test_loop_post']->post_excerpt ?? '';
    }
}
