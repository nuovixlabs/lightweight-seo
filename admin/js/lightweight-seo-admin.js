/**
 * Lightweight SEO Admin JavaScript
 */
(function($) {
    'use strict';

    // Document ready
    $(document).ready(function() {
        var seoAdminStrings = window.lightweightSeoAdmin || {};
        var mediaTitle = seoAdminStrings.mediaTitle || 'Select or Upload Image';
        var mediaButton = seoAdminStrings.mediaButton || 'Use this image';
        var previewAlt = seoAdminStrings.previewAlt || 'Preview';

		// Storage can be unavailable (private browsing, blocked site data), so never let it break the screen.
		function readDismissedChecklist() {
			try {
				return window.localStorage.getItem('lightweightSeoChecklistDismissed') === '1';
			} catch (error) {
				return false;
			}
		}

		$('.lightweight-seo-dismiss-checklist').on('click', function() {
			$('.lightweight-seo-setup').attr('hidden', true);

			try {
				window.localStorage.setItem('lightweightSeoChecklistDismissed', '1');
			} catch (error) {
				// The checklist stays hidden for this page view only.
			}
		});

		if (readDismissedChecklist()) {
			$('.lightweight-seo-setup').attr('hidden', true);
		}

		// Build previews as DOM nodes so URLs and translated text are never parsed as markup.
		function buildImagePreview(url, maxWidth) {
			return $('<div>', {'class': 'lightweight-seo-image-preview'}).append(
				$('<img>', {src: url, alt: previewAlt}).css({maxWidth: maxWidth, marginTop: '10px'})
			);
		}

		$('#lightweight-seo-redirect-search').on('input', function() {
			var query = $.trim($(this).val()).toLowerCase();

			$('.lightweight-seo-redirect-table tbody tr').each(function() {
				$(this).toggle(!query || $(this).text().toLowerCase().indexOf(query) !== -1);
			});
		});

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
                title: mediaTitle,
                button: {
                    text: mediaButton
                },
                multiple: false
            });
            
            // When an image is selected, run a callback
            mediaUploader.on('select', function() {
                var attachment = mediaUploader.state().get('selection').first().toJSON();
                $('#lightweight_seo_social_image').val(attachment.url);
                $('#lightweight_seo_social_image_id').val(attachment.id);
                
                // Add or update preview
                var preview = $('.lightweight-seo-image-preview');
                if (preview.length === 0) {
                    $('.lightweight-seo-image-field').append(buildImagePreview(attachment.url, '200px'));
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
            var fieldContainer = button.parent();
            var imageField = fieldContainer.find('.lightweight-seo-image-url');
            var imageIdField = fieldContainer.find('.lightweight-seo-image-id');
            
            // Create a new media uploader instance
            var metaUploader = wp.media({
                title: mediaTitle,
                button: {
                    text: mediaButton
                },
                multiple: false
            });
            
            // When an image is selected, run a callback
            metaUploader.on('select', function() {
                var attachment = metaUploader.state().get('selection').first().toJSON();
                imageField.val(attachment.url);
                imageIdField.val(attachment.id);
                
                // Add or update preview
                var previewContainer = fieldContainer.find('.lightweight-seo-image-preview');
                if (previewContainer.length === 0) {
                    fieldContainer.append(buildImagePreview(attachment.url, '300px'));
                } else {
                    previewContainer.find('img').attr('src', attachment.url);
                }
                var socialPreviewImage = $('.lightweight-seo-social-preview img');

                if (!socialPreviewImage.length) {
                    socialPreviewImage = $('<img>', {alt: ''}).prependTo('.lightweight-seo-social-preview');
                }

                socialPreviewImage.attr('src', attachment.url);
            });
            
            // Open the uploader
            metaUploader.open();
        });

        // Clear attachment IDs when image URLs are manually edited
        $('.lightweight-seo-image-url').on('input', function() {
            $(this).siblings('.lightweight-seo-image-id').val('');
        });
        
        // Meta box tabs
		function activateTab(tab) {
            var tabId = tab.data('tab');
			var tabs = tab.closest('.lightweight-seo-tabs').find('[role="tab"]');
            var panels = tab.closest('.lightweight-seo-tabs').find('[role="tabpanel"]');

            tabs.removeClass('nav-tab-active').attr({'aria-selected': 'false', 'tabindex': '-1'});
            panels.removeClass('active').attr('hidden', true);
            tab.addClass('nav-tab-active').attr({'aria-selected': 'true', 'tabindex': '0'}).focus();
            panels.filter('[data-panel="' + tabId + '"]').addClass('active').removeAttr('hidden');
		}

        $('.lightweight-seo-tab-nav [role="tab"]').on('click', function() {
			activateTab($(this));
        });

		$('.lightweight-seo-tab-nav [role="tab"]').on('keydown', function(event) {
			if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') {
				return;
			}

			event.preventDefault();
			var tabs = $(this).closest('[role="tablist"]').find('[role="tab"]');
			var index = tabs.index(this) + (event.key === 'ArrowRight' ? 1 : -1);
			activateTab(tabs.eq((index + tabs.length) % tabs.length));
		});

		$('.lightweight-seo-checks a[href^="#"]').on('click', function(event) {
			var target = $(this.hash);

			if (!target.length) {
				return;
			}

			event.preventDefault();
			var panel = target.closest('[role="tabpanel"]');
			activateTab($('#' + panel.attr('aria-labelledby')));
			target.closest('details').attr('open', true);
			target.trigger('focus');
		});

		var previewTokens = $('.lightweight-seo-meta-box').data('tokens') || {};

		// Mirror the server: %title%, %sitename%, %tagline%, and %sep% are replaced in custom values.
		function resolveTokens(text) {
			$.each(previewTokens, function(token, value) {
				text = text.split(token).join(value);
			});

			return text;
		}

		function fieldValue(selector) {
			var field = $(selector);
			var value = $.trim(field.val());

			return value ? resolveTokens(value) : (field.data('fallback') || '');
		}

		function updateEditorPreviews() {
			var title = fieldValue('#lightweight_seo_title');
			var description = fieldValue('#lightweight_seo_description');
			var socialTitle = resolveTokens($.trim($('#lightweight_seo_social_title').val())) || title;
			var socialDescription = resolveTokens($.trim($('#lightweight_seo_social_description').val())) || description;

			$('.lightweight-seo-preview-title').text(title);
			$('.lightweight-seo-preview-description').text(description);
			$('.lightweight-seo-social-title-preview').text(socialTitle);
			$('.lightweight-seo-social-description-preview').text(socialDescription);
			// Count characters (code points), matching the server-side guidance for non-Latin text.
			$('[data-count-for="lightweight_seo_title"]').text(Array.from(String(title)).length);
			$('[data-count-for="lightweight_seo_description"]').text(Array.from(String(description)).length);
		}

		$('#lightweight_seo_title, #lightweight_seo_description, #lightweight_seo_social_title, #lightweight_seo_social_description').on('input', updateEditorPreviews);
    });

})(jQuery);
