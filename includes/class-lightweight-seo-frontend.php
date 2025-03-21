<?php
/**
 * The frontend functionality of the plugin.
 *
 * @since      1.0.0
 * @package    Lightweight_SEO
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * The frontend functionality of the plugin.
 */
class Lightweight_SEO_Frontend {

    /**
     * Initialize the class.
     *
     * @since    1.0.0
     */
    public function __construct() {
        // Filter document title
        add_filter('pre_get_document_title', array($this, 'filter_document_title'), 15);
        
        // Add meta tags to head
        add_action('wp_head', array($this, 'add_meta_tags'), 1);

        // Add tracking codes
        add_action('wp_head', array($this, 'add_tracking_codes'), 1);
        add_action('wp_body_open', array($this, 'add_gtm_noscript'), 1);
    }

    /**
     * Filter the document title.
     *
     * @since    1.0.0
     * @param    string    $title    The document title.
     * @return   string    Modified title.
     */
    public function filter_document_title($title) {
        // Only proceed if we're on a singular page
        if (!is_singular()) {
            return $title;
        }
        
        $post_id = get_the_ID();
        $custom_title = get_post_meta($post_id, '_lightweight_seo_title', true);
        
        // If a custom title is set, use it
        if (!empty($custom_title)) {
            return $custom_title;
        }
        
        // Otherwise use the format from settings
        $settings = get_option('lightweight_seo_settings');
        $title_format = $settings['title_format'] ?? '%title% &#8211; %sitename%';
        
        // Replace variables
        $title = str_replace(
            array('%title%', '%sitename%', '%tagline%', '%sep%'),
            array(
                get_the_title($post_id),
                get_bloginfo('name'),
                get_bloginfo('description'),
                '&#8211;'
            ),
            $title_format
        );
        
        return $title;
    }

    /**
     * Add meta tags to head.
     *
     * @since    1.0.0
     */
    public function add_meta_tags() {
        // Global settings
        $settings = get_option('lightweight_seo_settings');
        $global_description = $settings['meta_description'] ?? '';
        $global_keywords = $settings['meta_keywords'] ?? '';
        $global_social_image = $settings['social_image'] ?? '';
        
        // Initialize variables
        $description = $global_description;
        $keywords = $global_keywords;
        $robots = '';
        $og_title = '';
        $og_description = '';
        $og_image = $global_social_image;
        $og_type = 'website';
        $twitter_card = 'summary_large_image';
        
        // If on a singular page, get post-specific values
        if (is_singular()) {
            $post_id = get_the_ID();
            
            // Get custom values for this post
            $custom_description = get_post_meta($post_id, '_lightweight_seo_description', true);
            $custom_keywords = get_post_meta($post_id, '_lightweight_seo_keywords', true);
            $custom_noindex = get_post_meta($post_id, '_lightweight_seo_noindex', true);
            $custom_social_title = get_post_meta($post_id, '_lightweight_seo_social_title', true);
            $custom_social_description = get_post_meta($post_id, '_lightweight_seo_social_description', true);
            $custom_social_image = get_post_meta($post_id, '_lightweight_seo_social_image', true);
            
            // Override with custom values if they exist
            if (!empty($custom_description)) {
                $description = $custom_description;
            }
            
            if (!empty($custom_keywords)) {
                $keywords = $custom_keywords;
            }
            
            if ($custom_noindex === '1') {
                $robots = 'noindex, nofollow';
            }
            
            // Set OpenGraph title (use custom social title, SEO title, or post title)
            $og_title = !empty($custom_social_title) ? $custom_social_title : 
                        (!empty(get_post_meta($post_id, '_lightweight_seo_title', true)) ? 
                          get_post_meta($post_id, '_lightweight_seo_title', true) : 
                          get_the_title($post_id));
            
            // Set OpenGraph description
            $og_description = !empty($custom_social_description) ? $custom_social_description : $description;
            
            // Set OpenGraph image
            if (!empty($custom_social_image)) {
                $og_image = $custom_social_image;
            } elseif (has_post_thumbnail($post_id)) {
                $og_image = get_the_post_thumbnail_url($post_id, 'large');
            }
            
            // Set OpenGraph type
            $og_type = is_single() ? 'article' : 'website';
        } else {
            // Non-singular pages
            if (is_home() || is_front_page()) {
                $og_title = get_bloginfo('name');
                $og_description = $description;
            } elseif (is_category() || is_tag() || is_tax()) {
                $term = get_queried_object();
                $og_title = $term->name;
                $og_description = $term->description ?: $description;
            } elseif (is_archive()) {
                $og_title = get_the_archive_title();
                $og_description = get_the_archive_description() ?: $description;
            } elseif (is_search()) {
                $og_title = sprintf(__('Search Results for "%s"', 'lightweight-seo'), get_search_query());
                $og_description = $description;
            } else {
                $og_title = get_bloginfo('name');
                $og_description = $description;
            }
        }
        
        // Output meta tags
        
        // Standard meta tags
        if (!empty($description)) {
            echo '<meta name="description" content="' . esc_attr($description) . '" />' . "\n";
        }
        
        if (!empty($keywords)) {
            echo '<meta name="keywords" content="' . esc_attr($keywords) . '" />' . "\n";
        }
        
        // Robots meta tag
        if (!empty($robots)) {
            echo '<meta name="robots" content="' . esc_attr($robots) . '" />' . "\n";
        }
        
        // Open Graph tags
        echo '<meta property="og:title" content="' . esc_attr($og_title) . '" />' . "\n";
        
        if (!empty($og_description)) {
            echo '<meta property="og:description" content="' . esc_attr($og_description) . '" />' . "\n";
        }
        
        echo '<meta property="og:type" content="' . esc_attr($og_type) . '" />' . "\n";
        echo '<meta property="og:url" content="' . esc_url((is_singular() ? get_permalink() : home_url(add_query_arg(array(), $GLOBALS['wp']->request)))) . '" />' . "\n";
        
        if (!empty($og_image)) {
            echo '<meta property="og:image" content="' . esc_url($og_image) . '" />' . "\n";
        }
        
        echo '<meta property="og:site_name" content="' . esc_attr(get_bloginfo('name')) . '" />' . "\n";
        
        // Twitter Card tags
        echo '<meta name="twitter:card" content="' . esc_attr($twitter_card) . '" />' . "\n";
        echo '<meta name="twitter:title" content="' . esc_attr($og_title) . '" />' . "\n";
        
        if (!empty($og_description)) {
            echo '<meta name="twitter:description" content="' . esc_attr($og_description) . '" />' . "\n";
        }
        
        if (!empty($og_image)) {
            echo '<meta name="twitter:image" content="' . esc_url($og_image) . '" />' . "\n";
        }
    }

    /**
     * Add tracking codes to head
     *
     * @since    1.0.1
     */
    public function add_tracking_codes() {
        $settings = get_option('lightweight_seo_settings');
        
        // Google Analytics 4
        if (!empty($settings['ga4_measurement_id'])) {
            ?>
            <!-- Google Analytics 4 -->
            <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr($settings['ga4_measurement_id']); ?>"></script>
            <script>
                window.dataLayer = window.dataLayer || [];
                function gtag(){dataLayer.push(arguments);}
                gtag('js', new Date());
                gtag('config', '<?php echo esc_attr($settings['ga4_measurement_id']); ?>');
            </script>
            <?php
        }

        // Google Tag Manager
        if (!empty($settings['gtm_container_id'])) {
            ?>
            <!-- Google Tag Manager -->
            <script>
                (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
                new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
                j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
                'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
                })(window,document,'script','dataLayer','<?php echo esc_attr($settings['gtm_container_id']); ?>');
            </script>
            <?php
        }

        // Facebook Pixel
        if (!empty($settings['facebook_pixel_id'])) {
            ?>
            <!-- Facebook Pixel -->
            <script>
                !function(f,b,e,v,n,t,s)
                {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
                n.callMethod.apply(n,arguments):n.queue.push(arguments)};
                if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
                n.queue=[];t=b.createElement(e);t.async=!0;
                t.src=v;s=b.getElementsByTagName(e)[0];
                s.parentNode.insertBefore(t,s)}(window, document,'script',
                'https://connect.facebook.net/en_US/fbevents.js');
                fbq('init', '<?php echo esc_attr($settings['facebook_pixel_id']); ?>');
                fbq('track', 'PageView');
            </script>
            <noscript>
                <img height="1" width="1" style="display:none" 
                    src="https://www.facebook.com/tr?id=<?php echo esc_attr($settings['facebook_pixel_id']); ?>&ev=PageView&noscript=1"/>
            </noscript>
            <?php
        }
    }

    /**
     * Add Google Tag Manager noscript code after body tag
     *
     * @since    1.0.1
     */
    public function add_gtm_noscript() {
        $settings = get_option('lightweight_seo_settings');
        
        if (!empty($settings['gtm_container_id'])) {
            ?>
            <!-- Google Tag Manager (noscript) -->
            <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?php echo esc_attr($settings['gtm_container_id']); ?>"
            height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
            <?php
        }
    }
}
