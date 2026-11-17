/**
 * Lightweight SEO admin behavior.
 *
 * Every block enhances markup that already works without it. Component names
 * match admin/css/lightweight-seo-admin.css and docs/design-system.md.
 */
(function($) {
	'use strict';

	$(function() {
		var strings = window.lightweightSeoAdmin || {};
		var mediaTitle = strings.mediaTitle || 'Select or Upload Image';
		var mediaButton = strings.mediaButton || 'Use this image';

		/* Setup checklist ---------------------------------------------------- */

		// Storage can be unavailable (private browsing, blocked site data), so never let it break the screen.
		function isChecklistDismissed() {
			try {
				return window.localStorage.getItem('lightweightSeoChecklistDismissed') === '1';
			} catch (error) {
				return false;
			}
		}

		$('.lwseo-setup__dismiss').on('click', function() {
			$('.lwseo-setup').attr('hidden', true);

			try {
				window.localStorage.setItem('lightweightSeoChecklistDismissed', '1');
			} catch (error) {
				// The checklist stays hidden for this page view only.
			}
		});

		if (isChecklistDismissed()) {
			$('.lwseo-setup').attr('hidden', true);
		}

		/* Module cards ------------------------------------------------------- */

		// A link to a collapsed module opens it, so the reader lands on its settings.
		function openLinkedCard() {
			var card = window.location.hash ? document.getElementById(window.location.hash.slice(1)) : null;

			if (card && card.tagName === 'DETAILS') {
				card.open = true;
				card.scrollIntoView({block: 'start'});
			}
		}

		openLinkedCard();
		$(window).on('hashchange', openLinkedCard);

		/* Redirect table search ---------------------------------------------- */

		$('#lightweight-seo-redirect-search').on('input', function() {
			var query = $.trim($(this).val()).toLowerCase();

			$('.lwseo-redirect-table tbody tr').each(function() {
				$(this).toggle(!query || $(this).text().toLowerCase().indexOf(query) !== -1);
			});
		});

		/* Title variables ---------------------------------------------------- */

		var lastTokenField = null;

		$(document).on('focusin', '[data-lwseo-tokens]', function() {
			lastTokenField = this;
		});

		$('.lwseo-token').on('mousedown', function(event) {
			// Keep the caret in the field being edited while the variable is clicked.
			event.preventDefault();
		}).on('click', function() {
			var scope = $(this).closest('.lwseo-card, .lwseo-metabox');
			var wasEdited = lastTokenField && $.contains(scope[0], lastTokenField);
			var field = wasEdited ? lastTokenField : scope.find('[data-lwseo-tokens]')[0];
			var token = $(this).data('token');

			if (!field) {
				return;
			}

			// Insert at the caret. A field that was never edited has no caret yet, so append instead.
			var start = wasEdited ? field.selectionStart : field.value.length;
			var end = wasEdited ? field.selectionEnd : field.value.length;

			field.value = field.value.slice(0, start) + token + field.value.slice(end);
			field.focus();
			field.setSelectionRange(start + token.length, start + token.length);
			$(field).trigger('input');
		});

		/* Image field -------------------------------------------------------- */

		// Build previews as DOM nodes so URLs and translated text are never parsed as markup.
		function showImage(field, url) {
			var preview = field.find('.lwseo-image__preview');
			var image = preview.find('img');

			if (!url) {
				preview.attr('hidden', true);
				return;
			}

			if (!image.length) {
				image = $('<img>', {alt: preview.data('alt') || ''}).appendTo(preview);
			}

			image.attr('src', url);
			preview.removeAttr('hidden');

			// The editor's share preview mirrors the chosen image.
			var media = field.closest('.lwseo-metabox').find('.lwseo-preview__media');
			var shared = media.find('img');

			if (media.length && !shared.length) {
				shared = $('<img>', {alt: ''}).appendTo(media);
			}

			shared.attr('src', url);
		}

		$('.lwseo-image__choose').on('click', function(event) {
			event.preventDefault();

			var field = $(this).closest('.lwseo-image');
			var frame = field.data('frame');

			if (!frame) {
				frame = wp.media({
					title: mediaTitle,
					button: {text: mediaButton},
					multiple: false
				});

				frame.on('select', function() {
					var attachment = frame.state().get('selection').first().toJSON();

					field.find('.lwseo-image__url').val(attachment.url);
					field.find('.lwseo-image__id').val(attachment.id);
					showImage(field, attachment.url);
				});

				field.data('frame', frame);
			}

			frame.open();
		});

		// A hand-edited URL no longer belongs to the previously chosen attachment.
		$('.lwseo-image__url').on('input', function() {
			$(this).closest('.lwseo-image').find('.lwseo-image__id').val('');
		}).on('change', function() {
			showImage($(this).closest('.lwseo-image'), $.trim($(this).val()));
		});

		/* Editor tabs -------------------------------------------------------- */

		function activateTab(tab) {
			var tabs = tab.closest('[role="tablist"]').find('[role="tab"]');

			tabs.each(function() {
				var isActive = this === tab[0];

				$(this).toggleClass('is-active', isActive).attr({'aria-selected': isActive ? 'true' : 'false', 'tabindex': isActive ? '0' : '-1'});
				$('#' + $(this).attr('aria-controls')).attr('hidden', !isActive);
			});

			tab.trigger('focus');
		}

		$('.lwseo-tabs [role="tab"]').on('click', function() {
			activateTab($(this));
		}).on('keydown', function(event) {
			if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') {
				return;
			}

			event.preventDefault();
			var tabs = $(this).closest('[role="tablist"]').find('[role="tab"]');
			var index = tabs.index(this) + (event.key === 'ArrowRight' ? 1 : -1);
			activateTab(tabs.eq((index + tabs.length) % tabs.length));
		});

		// A check's "Edit" link opens the panel that holds its field, then focuses the field.
		$('.lwseo-checks a[href^="#"]').on('click', function(event) {
			var target = $(this.hash);

			if (!target.length) {
				return;
			}

			event.preventDefault();
			activateTab($('#' + target.closest('[role="tabpanel"]').attr('aria-labelledby')));
			target.closest('details').attr('open', true);
			target.trigger('focus');
		});

		/* Editor previews ---------------------------------------------------- */

		var metabox = $('.lwseo-metabox');
		var previewTokens = metabox.data('tokens') || {};

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

		// Count characters (code points), matching the server-side guidance for non-Latin text.
		function updateLength(fieldId, text) {
			var length = Array.from(String(text)).length;
			var meter = $('[data-meter-for="' + fieldId + '"]');
			var max = Number(meter.data('max'));

			$('[data-count-for="' + fieldId + '"]').text(length);
			meter.toggleClass('is-in-range', length >= Number(meter.data('min')) && length <= max);
			// The guidance range ends at 80% of the track, leaving room to show an overrun.
			meter.find('.lwseo-meter__bar').css('width', Math.min(100, (length / max) * 80) + '%');
		}

		function updateEditorPreviews() {
			var title = fieldValue('#lightweight_seo_title');
			var description = fieldValue('#lightweight_seo_description');

			metabox.find('[data-preview="title"]').text(title);
			metabox.find('[data-preview="description"]').text(description);
			metabox.find('[data-preview="social-title"]').text(resolveTokens($.trim($('#lightweight_seo_social_title').val())) || title);
			metabox.find('[data-preview="social-description"]').text(resolveTokens($.trim($('#lightweight_seo_social_description').val())) || description);
			updateLength('lightweight_seo_title', title);
			updateLength('lightweight_seo_description', description);
		}

		if (metabox.length) {
			$('#lightweight_seo_title, #lightweight_seo_description, #lightweight_seo_social_title, #lightweight_seo_social_description').on('input', updateEditorPreviews);
			updateLength('lightweight_seo_title', metabox.find('[data-preview="title"]').text());
			updateLength('lightweight_seo_description', metabox.find('[data-preview="description"]').text());
		}
	});

})(jQuery);
