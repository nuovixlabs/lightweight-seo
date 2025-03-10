<?php
/**
 * The core plugin class.
 *
 * @since      1.0.0
 * @package    Lightweight_SEO
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * The core plugin class.
 */
class Lightweight_SEO {

    /**
     * The unique identifier of this plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $plugin_name    The string used to uniquely identify this plugin.
     */
    protected $plugin_name;

    /**
     * The current version of the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $version    The current version of the plugin.
     */
    protected $version;

    /**
     * Define the core functionality of the plugin.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->plugin_name = 'lightweight-seo';
        $this->version = LIGHTWEIGHT_SEO_VERSION;
        $this->load_dependencies();
    }

    /**
     * Load the required dependencies for this plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function load_dependencies() {
        // Admin class for backend functionality
        require_once LIGHTWEIGHT_SEO_PLUGIN_DIR . 'includes/class-lightweight-seo-admin.php';
        
        // Meta boxes class for per-page SEO controls
        require_once LIGHTWEIGHT_SEO_PLUGIN_DIR . 'includes/class-lightweight-seo-meta-boxes.php';
        
        // Frontend class for displaying SEO data
        require_once LIGHTWEIGHT_SEO_PLUGIN_DIR . 'includes/class-lightweight-seo-frontend.php';
    }

    /**
     * Run the plugin.
     *
     * @since    1.0.0
     */
    public function run() {
        // Initialize admin functionality
        $plugin_admin = new Lightweight_SEO_Admin($this->get_plugin_name(), $this->get_version());
        
        // Initialize meta boxes
        $plugin_meta_boxes = new Lightweight_SEO_Meta_Boxes();
        
        // Initialize frontend functionality
        $plugin_frontend = new Lightweight_SEO_Frontend();
        
        // Register activation hook
        register_activation_hook(LIGHTWEIGHT_SEO_PLUGIN_DIR . 'lightweight-seo.php', array($this, 'activate'));
    }

    /**
     * The name of the plugin used to uniquely identify it within the context of
     * WordPress and to define internationalization functionality.
     *
     * @since     1.0.0
     * @return    string    The name of the plugin.
     */
    public function get_plugin_name() {
        return $this->plugin_name;
    }

    /**
     * Retrieve the version number of the plugin.
     *
     * @since     1.0.0
     * @return    string    The version number of the plugin.
     */
    public function get_version() {
        return $this->version;
    }

    /**
     * Plugin activation
     * 
     * @since     1.0.0
     */
    public function activate() {
        // Set default options if they don't exist
        if (!get_option('lightweight_seo_settings')) {
            $default_settings = array(
                'title_format' => '%title% - %sitename%',
                'meta_description' => get_bloginfo('description'),
                'meta_keywords' => '',
                'social_image' => '',
            );
            
            update_option('lightweight_seo_settings', $default_settings);
        }
    }
}
