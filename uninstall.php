<?php
/**
 * Uninstall cleanup for Lightweight SEO.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Delete plugin data for the current site.
 *
 * @return void
 */
function lightweight_seo_delete_plugin_data() {
    delete_option('lightweight_seo_settings');
    delete_site_option('lightweight_seo_settings');

    $meta_keys = array(
        '_lightweight_seo_title',
        '_lightweight_seo_description',
        '_lightweight_seo_keywords',
        '_lightweight_seo_noindex',
        '_lightweight_seo_social_title',
        '_lightweight_seo_social_description',
        '_lightweight_seo_social_image',
        '_lightweight_seo_social_image_id',
    );

    foreach ($meta_keys as $meta_key) {
        delete_post_meta_by_key($meta_key);
    }
}

if (is_multisite()) {
    $site_ids = get_sites(array(
        'fields' => 'ids',
        'number' => 0,
    ));

    foreach ($site_ids as $site_id) {
        switch_to_blog($site_id);
        lightweight_seo_delete_plugin_data();
        restore_current_blog();
    }
} else {
    lightweight_seo_delete_plugin_data();
}
