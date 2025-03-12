<?php
/**
 * The meta box functionality of the plugin.
 *
 * @since      1.0.0
 * @package    Lightweight_SEO
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * The meta box functionality of the plugin.
 */
class Lightweight_SEO_Meta_Boxes {

    /**
     * Initialize the class.
     *
     * @since    1.0.0
     */
    public function __construct() {
        // Add meta boxes to posts and pages
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        
        // Save meta box data
        add_action('save_post', array($this, 'save_meta_box_data'));
    }

    /**
     * Add meta boxes to posts and pages.
     *
     * @since    1.0.0
     */
    public function add_meta_boxes() {
        // Get all public post types
        $post_types = get_post_types(array('public' => true), 'names');
        
        // Add meta box to all public post types
        foreach ($post_types as $post_type) {
            add_meta_box(
                'lightweight_seo_meta_box',
                __('SEO Settings', 'lightweight-seo'),
                array($this, 'render_meta_box'),
                $post_type,
                'normal',
                'high'
            );
        }
    }

    /**
     * Render the meta box.
     *
     * @since    1.0.0
     * @param    WP_Post    $post    The post object.
     */
    public function render_meta_box($post) {
        // Add nonce for security
        wp_nonce_field('lightweight_seo_meta_box', 'lightweight_seo_meta_box_nonce');
        
        // Get saved values
        $seo_title = get_post_meta($post->ID, '_lightweight_seo_title', true);
        $seo_description = get_post_meta($post->ID, '_lightweight_seo_description', true);
        $seo_keywords = get_post_meta($post->ID, '_lightweight_seo_keywords', true);
        $seo_noindex = get_post_meta($post->ID, '_lightweight_seo_noindex', true);
        $social_title = get_post_meta($post->ID, '_lightweight_seo_social_title', true);
        $social_description = get_post_meta($post->ID, '_lightweight_seo_social_description', true);
        $social_image = get_post_meta($post->ID, '_lightweight_seo_social_image', true);
        
        // Get global settings for reference
        $global_settings = get_option('lightweight_seo_settings');
        $global_title_format = $global_settings['title_format'] ?? '%title% | %sitename%';
        $global_description = $global_settings['meta_description'] ?? '';
        
        // Calculate current title
        $current_title = str_replace(
            array('%title%', '%sitename%', '%tagline%', '%sep%'),
            array(
                $post->post_title,
                get_bloginfo('name'),
                get_bloginfo('description'),
                '|'
            ),
            $global_title_format
        );
        
        // Start output
        ?>
        <div class="lightweight-seo-meta-box">
            <div class="lightweight-seo-tabs">
                <div class="lightweight-seo-tab-nav">
                    <span class="nav-tab nav-tab-active" data-tab="general"><?php _e('General SEO', 'lightweight-seo'); ?></span>
                    <span class="nav-tab" data-tab="social"><?php _e('Social Media', 'lightweight-seo'); ?></span>
                </div>
                
                <div class="lightweight-seo-tab-content">
                    <!-- General SEO Tab -->
                    <div class="tab-content active" id="general">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="lightweight_seo_title"><?php _e('SEO Title', 'lightweight-seo'); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="lightweight_seo_title" name="lightweight_seo_title" value="<?php echo esc_attr($seo_title); ?>" class="large-text">
                                    <p class="description">
                                        <?php _e('Current title (if not customized):', 'lightweight-seo'); ?> 
                                        <strong><?php echo esc_html($current_title); ?></strong>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="lightweight_seo_description"><?php _e('Meta Description', 'lightweight-seo'); ?></label>
                                </th>
                                <td>
                                    <textarea id="lightweight_seo_description" name="lightweight_seo_description" rows="3" class="large-text"><?php echo esc_textarea($seo_description); ?></textarea>
                                    <p class="description">
                                        <?php _e('Optimal length: 150-160 characters', 'lightweight-seo'); ?>
                                        <?php if (!empty($global_description)) : ?>
                                            <br>
                                            <?php _e('Global default description:', 'lightweight-seo'); ?> 
                                            <em><?php echo esc_html($global_description); ?></em>
                                        <?php endif; ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="lightweight_seo_keywords"><?php _e('Meta Keywords', 'lightweight-seo'); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="lightweight_seo_keywords" name="lightweight_seo_keywords" value="<?php echo esc_attr($seo_keywords); ?>" class="large-text">
                                    <p class="description">
                                        <?php _e('Comma-separated list of keywords', 'lightweight-seo'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <?php _e('Search Engine Indexing', 'lightweight-seo'); ?>
                                </th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="lightweight_seo_noindex" value="1" <?php checked($seo_noindex, '1'); ?>>
                                        <?php _e('Prevent search engines from indexing this page', 'lightweight-seo'); ?>
                                    </label>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Social Media Tab -->
                    <div class="tab-content" id="social">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="lightweight_seo_social_title"><?php _e('Social Title', 'lightweight-seo'); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="lightweight_seo_social_title" name="lightweight_seo_social_title" value="<?php echo esc_attr($social_title); ?>" class="large-text">
                                    <p class="description">
                                        <?php _e('Title used when shared on social media. If empty, the SEO title or post title will be used.', 'lightweight-seo'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="lightweight_seo_social_description"><?php _e('Social Description', 'lightweight-seo'); ?></label>
                                </th>
                                <td>
                                    <textarea id="lightweight_seo_social_description" name="lightweight_seo_social_description" rows="3" class="large-text"><?php echo esc_textarea($social_description); ?></textarea>
                                    <p class="description">
                                        <?php _e('Description used when shared on social media. If empty, the Meta Description will be used.', 'lightweight-seo'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="lightweight_seo_social_image"><?php _e('Social Image', 'lightweight-seo'); ?></label>
                                </th>
                                <td>
                                    <div class="lightweight-seo-image-field">
                                        <input type="text" id="lightweight_seo_social_image" name="lightweight_seo_social_image" value="<?php echo esc_url($social_image); ?>" class="large-text">
                                        <button type="button" class="button button-secondary lightweight-seo-upload-image"><?php _e('Upload Image', 'lightweight-seo'); ?></button>
                                        <?php if (!empty($social_image)) : ?>
                                            <div class="lightweight-seo-image-preview">
                                                <img src="<?php echo esc_url($social_image); ?>" alt="<?php _e('Preview', 'lightweight-seo'); ?>" style="max-width: 300px; margin-top: 10px;">
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <p class="description">
                                        <?php _e('Image used when shared on social media. Recommended size: 1200x630px.', 'lightweight-seo'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Save meta box data.
     *
     * @since    1.0.0
     * @param    int    $post_id    The post ID.
     */
    public function save_meta_box_data($post_id) {
        // Check if our nonce is set
        if (!isset($_POST['lightweight_seo_meta_box_nonce'])) {
            return;
        }
        
        // Verify the nonce
        if (!wp_verify_nonce($_POST['lightweight_seo_meta_box_nonce'], 'lightweight_seo_meta_box')) {
            return;
        }
        
        // If this is an autosave, we don't want to do anything
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check the user's permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Sanitize and save the data
        if (isset($_POST['lightweight_seo_title'])) {
            update_post_meta($post_id, '_lightweight_seo_title', sanitize_text_field($_POST['lightweight_seo_title']));
        }
        
        if (isset($_POST['lightweight_seo_description'])) {
            update_post_meta($post_id, '_lightweight_seo_description', sanitize_textarea_field($_POST['lightweight_seo_description']));
        }
        
        if (isset($_POST['lightweight_seo_keywords'])) {
            update_post_meta($post_id, '_lightweight_seo_keywords', sanitize_text_field($_POST['lightweight_seo_keywords']));
        }
        
        // Checkbox fields need to be handled differently
        $noindex = isset($_POST['lightweight_seo_noindex']) ? '1' : '0';
        update_post_meta($post_id, '_lightweight_seo_noindex', $noindex);
        
        if (isset($_POST['lightweight_seo_social_title'])) {
            update_post_meta($post_id, '_lightweight_seo_social_title', sanitize_text_field($_POST['lightweight_seo_social_title']));
        }
        
        if (isset($_POST['lightweight_seo_social_description'])) {
            update_post_meta($post_id, '_lightweight_seo_social_description', sanitize_textarea_field($_POST['lightweight_seo_social_description']));
        }
        
        if (isset($_POST['lightweight_seo_social_image'])) {
            update_post_meta($post_id, '_lightweight_seo_social_image', esc_url_raw($_POST['lightweight_seo_social_image']));
        }
    }
}
