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
$lightweight_seo_test_posts = array();
$lightweight_seo_test_term_meta = array();
$lightweight_seo_test_user_meta = array();
$lightweight_seo_test_remote_post_responses = array();
$lightweight_seo_test_remote_get_responses = array();
$lightweight_seo_test_scheduled_events = array();
$lightweight_seo_test_registered_sitemap_providers = array();
$lightweight_seo_test_cache = array();
$lightweight_seo_test_blog_id = 1;
$lightweight_seo_test_network_admin = false;
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

if (!function_exists('selected')) {
    function selected($selected, $current = true, $display = true) {
        return $selected == $current ? 'selected="selected"' : '';
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
        return trim(strip_tags((string) $value));
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key($key) {
        return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $key));
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

if (!function_exists('wp_parse_args')) {
    function wp_parse_args($args, $defaults = array()) {
        return array_merge($defaults, (array) $args);
    }
}

if (!function_exists('update_option')) {
    function update_option($option, $value, $autoload = null) {
        global $lightweight_seo_test_options;

        $lightweight_seo_test_options[$option] = $value;

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

        return $lightweight_seo_test_post_meta[$post_id][$meta_key] ?? '';
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
        global $lightweight_seo_test_posts;

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

        usort($posts, function ($left, $right) use ($args) {
            if ('date' === ($args['orderby'] ?? '')) {
                return strcmp((string) ($right->post_date_gmt ?? ''), (string) ($left->post_date_gmt ?? ''));
            }

            return ((int) ($left->ID ?? 0)) <=> ((int) ($right->ID ?? 0));
        });

        $posts_per_page = (int) ($args['posts_per_page'] ?? count($posts));
        $paged = max(1, (int) ($args['paged'] ?? 1));

        if ($posts_per_page > -1) {
            $offset = ($paged - 1) * $posts_per_page;
            $posts = array_slice($posts, $offset, $posts_per_page);
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

if (!function_exists('is_singular')) {
    function is_singular() {
        global $lightweight_seo_test_query_state;

        return (bool) $lightweight_seo_test_query_state['is_singular'];
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

if (!function_exists('is_search')) {
    function is_search() {
        global $lightweight_seo_test_query_state;

        return (bool) $lightweight_seo_test_query_state['is_search'];
    }
}

if (!function_exists('is_404')) {
    function is_404() {
        global $lightweight_seo_test_query_state;

        return (bool) $lightweight_seo_test_query_state['is_404'];
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
        return 'https://example.com' . $path;
    }
}

if (!function_exists('get_locale')) {
    function get_locale() {
        return 'en_US';
    }
}

if (!function_exists('add_query_arg')) {
    function add_query_arg($args = array(), $url = '') {
        return (string) $url;
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
        return 'https://example.com/term/' . $term->term_id;
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

        return $lightweight_seo_test_query_state['published_date'];
    }
}

if (!function_exists('get_the_modified_date')) {
    function get_the_modified_date($format = '', $post_id = 0) {
        global $lightweight_seo_test_query_state;

        return $lightweight_seo_test_query_state['modified_date'];
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
        $field = '<input type="hidden" name="' . esc_attr($name) . '" value="nonce">';

        if ($display) {
            echo $field;
        }

        return $field;
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

if (!function_exists('wp_safe_redirect')) {
    function wp_safe_redirect($location, $status = 302, $x_redirect_by = 'WordPress') {
        return true;
    }
}
