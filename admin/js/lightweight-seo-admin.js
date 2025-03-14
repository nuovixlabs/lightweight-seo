/**
 * Lightweight SEO Admin JavaScript
 */
(function($) {
    'use strict';

    // Document ready
    $(document).ready(function() {
        // Media uploader for social image
        var mediaUploader;

        // Handle upload image button click for global settings
        $('#lightweight_seo_upload_image').on('click', function(e) {
            e.preventDefault();
            
            // If the media uploader already exists, open it
            if (mediaUploader) {
                mediaUploader.open();
                return;
            }
            
            // Create the media uploader
            mediaUploader = wp.media({
                title: 'Select or Upload Image',
                button: {
                    text: 'Use this image'
                },
                multiple: false
            });
            
            // When an image is selected, run a callback
            mediaUploader.on('select', function() {
                var attachment = mediaUploader.state().get('selection').first().toJSON();
                $('#lightweight_seo_social_image').val(attachment.url);
                
                // Add or update preview
                var preview = $('.lightweight-seo-image-preview');
                if (preview.length === 0) {
                    $('.lightweight-seo-image-field').append('<div class="lightweight-seo-image-preview"><img src="' + attachment.url + '" alt="Preview" style="max-width: 200px; margin-top: 10px;"></div>');
                } else {
                    preview.find('img').attr('src', attachment.url);
                }
            });
            
            // Open the uploader
            mediaUploader.open();
        });
        
        // Handle upload image button clicks for meta boxes
        $('.lightweight-seo-upload-image').on('click', function(e) {
            e.preventDefault();
            
            var button = $(this);
            var imageField = button.prev('input');
            
            // Create a new media uploader instance
            var metaUploader = wp.media({
                title: 'Select or Upload Image',
                button: {
                    text: 'Use this image'
                },
                multiple: false
            });
            
            // When an image is selected, run a callback
            metaUploader.on('select', function() {
                var attachment = metaUploader.state().get('selection').first().toJSON();
                imageField.val(attachment.url);
                
                // Add or update preview
                var previewContainer = button.parent().find('.lightweight-seo-image-preview');
                if (previewContainer.length === 0) {
                    button.parent().append('<div class="lightweight-seo-image-preview"><img src="' + attachment.url + '" alt="Preview" style="max-width: 300px; margin-top: 10px;"></div>');
                } else {
                    previewContainer.find('img').attr('src', attachment.url);
                }
            });
            
            // Open the uploader
            metaUploader.open();
        });
        
        // Meta box tabs
        $('.lightweight-seo-tab-nav .nav-tab').on('click', function() {
            var tabId = $(this).data('tab');
            
            // Remove active class from all tabs and content
            $('.lightweight-seo-tab-nav .nav-tab').removeClass('nav-tab-active');
            $('.lightweight-seo-tab-content .tab-content').removeClass('active');
            
            // Add active class to clicked tab and corresponding content
            $(this).addClass('nav-tab-active');
            $('#' + tabId).addClass('active');
        });
    });

})(jQuery);
