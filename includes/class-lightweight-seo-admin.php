<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @since      1.0.0
 * @package    Lightweight_SEO
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * The admin-specific functionality of the plugin.
 */
class Lightweight_SEO_Admin {

    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $plugin_name    The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $version    The current version of this plugin.
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param    string    $plugin_name       The name of this plugin.
     * @param    string    $version           The version of this plugin.
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;

        // Add menu item
        add_action('admin_menu', array($this, 'add_plugin_admin_menu'));
        
        // Add settings link on plugin page
        add_filter('plugin_action_links_lightweight-seo/lightweight-seo.php', array($this, 'add_action_links'));
        
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
        
        // Enqueue admin scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    /**
     * Register the administration menu for this plugin.
     *
     * @since    1.0.0
     */
    public function add_plugin_admin_menu() {
        add_menu_page(
            'Lightweight SEO Settings',
            'SEO',
            'manage_options',
            $this->plugin_name,
            array($this, 'display_plugin_admin_page'),
            'dashicons-search',
            100
        );
    }

    /**
     * Add settings action link to the plugins page.
     *
     * @since    1.0.0
     * @param    array    $links    Plugin Action links.
     * @return   array
     */
    public function add_action_links($links) {
        $settings_link = array(
            '<a href="' . admin_url('admin.php?page=' . $this->plugin_name) . '">' . __('Settings', 'lightweight-seo') . '</a>',
        );
        return array_merge($settings_link, $links);
    }

    /**
     * Register plugin settings
     *
     * @since    1.0.0
     */
    public function register_settings() {
        register_setting(
            'lightweight_seo_settings',
            'lightweight_seo_settings',
            array($this, 'validate_settings')
        );

        // General SEO Settings section
        add_settings_section(
            'lightweight_seo_general_section',
            __('Global SEO Settings', 'lightweight-seo'),
            array($this, 'general_section_callback'),
            $this->plugin_name
        );

        // Title Format
        add_settings_field(
            'title_format',
            __('Default Title Format', 'lightweight-seo'),
            array($this, 'title_format_render'),
            $this->plugin_name,
            'lightweight_seo_general_section'
        );

        // Meta Description
        add_settings_field(
            'meta_description',
            __('Default Meta Description', 'lightweight-seo'),
            array($this, 'meta_description_render'),
            $this->plugin_name,
            'lightweight_seo_general_section'
        );

        // Meta Keywords
        add_settings_field(
            'meta_keywords',
            __('Default Meta Keywords', 'lightweight-seo'),
            array($this, 'meta_keywords_render'),
            $this->plugin_name,
            'lightweight_seo_general_section'
        );

        // Social Image
        add_settings_field(
            'social_image',
            __('Default Social Image', 'lightweight-seo'),
            array($this, 'social_image_render'),
            $this->plugin_name,
            'lightweight_seo_general_section'
        );
    }

    /**
     * Render the general section information
     *
     * @since    1.0.0
     */
    public function general_section_callback() {
        echo '<p>' . __('Configure the global SEO settings for your site. These will be used as defaults for all pages unless overridden.', 'lightweight-seo') . '</p>';
        echo '<p>' . __('Available variables for title format: %title%, %sitename%, %tagline%, %sep%', 'lightweight-seo') . '</p>';
    }

    /**
     * Render the title format field
     *
     * @since    1.0.0
     */
    public function title_format_render() {
        $options = get_option('lightweight_seo_settings');
        ?>
        <input type="text" name="lightweight_seo_settings[title_format]" value="<?php echo esc_attr($options['title_format'] ?? '%title% | %sitename%'); ?>" class="regular-text">
        <p class="description"><?php _e('Format for page titles. Example: %title% | %sitename%', 'lightweight-seo'); ?></p>
        <?php
    }

    /**
     * Render the meta description field
     *
     * @since    1.0.0
     */
    public function meta_description_render() {
        $options = get_option('lightweight_seo_settings');
        ?>
        <textarea name="lightweight_seo_settings[meta_description]" rows="4" cols="50" class="large-text"><?php echo esc_textarea($options['meta_description'] ?? ''); ?></textarea>
        <p class="description"><?php _e('Default description for pages without custom descriptions.', 'lightweight-seo'); ?></p>
        <?php
    }

    /**
     * Render the meta keywords field
     *
     * @since    1.0.0
     */
    public function meta_keywords_render() {
        $options = get_option('lightweight_seo_settings');
        ?>
        <input type="text" name="lightweight_seo_settings[meta_keywords]" value="<?php echo esc_attr($options['meta_keywords'] ?? ''); ?>" class="large-text">
        <p class="description"><?php _e('Comma-separated list of keywords for your site.', 'lightweight-seo'); ?></p>
        <?php
    }

    /**
     * Render the social image field
     *
     * @since    1.0.0
     */
    public function social_image_render() {
        $options = get_option('lightweight_seo_settings');
        $image_url = $options['social_image'] ?? '';
        ?>
        <div class="lightweight-seo-image-field">
            <input type="text" name="lightweight_seo_settings[social_image]" id="lightweight_seo_social_image" value="<?php echo esc_url($image_url); ?>" class="regular-text">
            <button type="button" class="button button-secondary" id="lightweight_seo_upload_image"><?php _e('Upload Image', 'lightweight-seo'); ?></button>
            <?php if (!empty($image_url)) : ?>
                <div class="lightweight-seo-image-preview">
                    <img src="<?php echo esc_url($image_url); ?>" alt="<?php _e('Preview', 'lightweight-seo'); ?>" style="max-width: 200px; margin-top: 10px;">
                </div>
            <?php endif; ?>
        </div>
        <p class="description"><?php _e('Default image for social media sharing.', 'lightweight-seo'); ?></p>
        <?php
    }

    /**
     * Sanitize and validate settings
     *
     * @since    1.0.0
     * @param    array    $input    The settings array.
     * @return   array
     */
    public function validate_settings($input) {
        $sanitized_input = array();
        
        if (isset($input['title_format'])) {
            $sanitized_input['title_format'] = sanitize_text_field($input['title_format']);
        }
        
        if (isset($input['meta_description'])) {
            $sanitized_input['meta_description'] = sanitize_textarea_field($input['meta_description']);
        }
        
        if (isset($input['meta_keywords'])) {
            $sanitized_input['meta_keywords'] = sanitize_text_field($input['meta_keywords']);
        }
        
        if (isset($input['social_image'])) {
            $sanitized_input['social_image'] = esc_url_raw($input['social_image']);
        }
        
        return $sanitized_input;
    }

    /**
     * Render the settings page for this plugin.
     *
     * @since    1.0.0
     */
    public function display_plugin_admin_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('lightweight_seo_settings');
                do_settings_sections($this->plugin_name);
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Enqueue admin scripts and styles
     *
     * @since    1.0.0
     */
    public function enqueue_admin_scripts($hook) {
        // Only load scripts on our plugin page or post/page edit screens
        if ('toplevel_page_' . $this->plugin_name !== $hook && !in_array($hook, array('post.php', 'post-new.php'))) {
            return;
        }

        // Enqueue the WordPress media uploader
        wp_enqueue_media();
        
        // Enqueue our admin script
        wp_enqueue_script(
            $this->plugin_name . '-admin-script',
            LIGHTWEIGHT_SEO_PLUGIN_URL . 'admin/js/lightweight-seo-admin.js',
            array('jquery'),
            $this->version,
            true
        );

        // Enqueue our admin styles
        wp_enqueue_style(
            $this->plugin_name . '-admin-style',
            LIGHTWEIGHT_SEO_PLUGIN_URL . 'admin/css/lightweight-seo-admin.css',
            array(),
            $this->version
        );
    }
}
