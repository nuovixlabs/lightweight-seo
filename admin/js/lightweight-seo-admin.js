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

		/* Unsaved changes ---------------------------------------------------- */

		// Each tab saves on its own, so leaving one with edits would lose them without a word.
		$('.lwseo-form').each(function() {
			var form = $(this);
			var status = form.find('.lwseo-actions__status');
			var saved = form.serialize();
			var submitting = false;

			// Tool controls inside this form belong to their own forms and are not serialized here.
			function isDirty() {
				return !submitting && form.serialize() !== saved;
			}

			form.on('input change', function() {
				var dirty = isDirty();

				form.toggleClass('is-dirty', dirty);
				status.text(dirty ? status.attr('data-unsaved') || '' : '');
			}).on('submit', function() {
				submitting = true;
			});

			$(window).on('beforeunload', function() {
				// Returning a value asks the browser to confirm before the page is left.
				if (isDirty()) {
					return status.attr('data-unsaved') || '';
				}
			});
		});

		/* Row editors -------------------------------------------------------- */

		// A setting stored as one item per line is shown as rows. The field itself stays in
		// the form as the only submitted control; the rows read it and write the same lines
		// back. The line formats live in lightweight-seo-formats.js.

		var settingFormats = window.lightweightSeoFormats || {};

		// Fill a translated template such as "%1$d of %2$d" or "%d lines".
		function fillTemplate(template, values) {
			return String(template || '').replace(/%(\d)\$d/g, function(match, position) {
				return values[position - 1];
			}).replace('%d', values[0]);
		}

		// Shared by every editor over a raw field: switch between the editor and the field,
		// and say how many lines are kept as text because the editor cannot show them.
		function editorView(wrapper, editor, field, load) {
			var toggle = wrapper.find('.lwseo-rows__toggle');
			var note = wrapper.find('.lwseo-rows__leftover');
			// Whatever explains the raw format is shown with the raw field and hidden with it.
			var textView = field.add('[data-lwseo-text-for="' + field.attr('id') + '"]');

			function show(asEditor) {
				if (asEditor) {
					load();
				}

				editor.prop('hidden', !asEditor);
				textView.prop('hidden', asEditor);
				toggle.text(toggle.attr(asEditor ? 'data-as-text' : 'data-as-rows'));
			}

			toggle.on('click', function() {
				show(editor.prop('hidden'));
			});

			return {
				show: show,
				kept: function(count) {
					note.prop('hidden', count === 0)
						.text(count ? fillTemplate(note.attr(count === 1 ? 'data-one' : 'data-many'), [count]) : '');
				},
				announce: function(message) {
					wrapper.children('.lwseo-rows__live').text(message || '');
				}
			};
		}

		// Search this site's own REST API from the administrator's browser. PHP requests nothing.
		function searchSite(query, types) {
			var base = strings.restUrl || '';

			if (!base || !window.fetch) {
				return Promise.reject(new Error('Search is unavailable.'));
			}

			return Promise.all(types.map(function(type) {
				var address = base + 'wp/v2/search' + (base.indexOf('?') === -1 ? '?' : '&') + $.param({search: query, type: type, per_page: 8, _fields: 'id,title,url'});

				return window.fetch(address, {credentials: 'same-origin', headers: {'X-WP-Nonce': strings.restNonce || ''}}).then(function(response) {
					if (!response.ok) {
						throw new Error('Search failed.');
					}

					return response.json();
				}).then(function(found) {
					return found.map(function(item) {
						var path = '';

						try {
							path = new URL(item.url).pathname;
						} catch (error) {
							path = '';
						}

						return {type: type, id: Number(item.id), title: plainText(item.title), path: path};
					});
				});
			})).then(function(groups) {
				return [].concat.apply([], groups);
			});
		}

		// Connect a search box to a list of results. Choosing a result calls onChoose with
		// its type, ID, and title. Returns a function that clears the results.
		function bindSearch(input, results, status, types, onChoose, isChosen) {
			var source = input.closest('[data-search-none]');
			var timer = null;
			var sequence = 0;

			function clear() {
				sequence += 1;
				window.clearTimeout(timer);
				results.empty();
				status.text('');
			}

			input.on('input', function() {
				var query = $.trim(input.val());

				clear();

				if (query.length < 2) {
					return;
				}

				var current = sequence;

				status.text(source.attr('data-search-searching') || '');

				// Wait for a pause in typing, and ignore an answer that a newer search has overtaken.
				timer = window.setTimeout(function() {
					searchSite(query, types).then(function(found) {
						if (current !== sequence) {
							return;
						}

						found.forEach(function(item) {
							if (isChosen && isChosen(item)) {
								return;
							}

							// Built as nodes, so a title is always text and never markup.
							var button = $('<button>', {type: 'button', 'class': 'button-link lwseo-picker__result', text: item.title || '#' + item.id});

							if (item.path) {
								// The leading space keeps the title and the path apart when they are read out.
								button.append($('<span>', {'class': 'lwseo-picker__path', text: ' ' + item.path}));
							}

							button.on('click', function() {
								onChoose(item);
							});

							results.append($('<li>').append(button));
						});

						status.text(results.children().length ? '' : source.attr('data-search-none') || '');
					}, function() {
						if (current === sequence) {
							status.text(source.attr('data-search-error') || '');
						}
					});
				}, 250);
			}).on('keydown', function(event) {
				// Enter belongs to the search, not to the settings form around it.
				if (event.key === 'Enter') {
					event.preventDefault();
				}
			});

			return clear;
		}

		// A row cell that holds a page: a button naming the page, which opens a search to change it.
		function setupPageCell(row, values, wrapper) {
			var cell = row.find('.lwseo-pagecell');

			if (!cell.length) {
				return;
			}

			var labels = wrapper.data('labels') || {};
			var current = cell.children('.lwseo-pagecell__current');
			var box = cell.children('.lwseo-pagecell__search');
			var input = box.children('input');
			var id = Number(values.id) || 0;

			// A retained legacy line has no page of its own, and is shown but not changed.
			if (values.type === 'mirror') {
				current.text(cell.attr('data-mirror') || '').prop('disabled', true);
				return;
			}

			if (id) {
				current.text(labels[values.type + ':' + id] || values.type + ':' + id);
			}

			function open(state) {
				box.prop('hidden', !state);
				current.attr('aria-expanded', state ? 'true' : 'false');

				if (state) {
					input.trigger('focus');
				}
			}

			var clear = bindSearch(input, box.children('.lwseo-picker__results'), box.children('.lwseo-picker__status'), ['post', 'term'], function(item) {
				labels[item.type + ':' + item.id] = item.title;
				row.find('[data-field="type"]').val(item.type);
				current.text(item.title || item.type + ':' + item.id);
				clear();
				input.val('');
				open(false);
				// The change bubbles to the row list, which writes the rows back to the field.
				row.find('[data-field="id"]').val(item.id).trigger('change');
				current.trigger('focus');
			});

			current.on('click', function() {
				open(box.prop('hidden'));
			});

			input.on('keydown', function(event) {
				if (event.key === 'Escape') {
					clear();
					open(false);
					current.trigger('focus');
				}
			});
		}

		function initRowEditor(wrapper) {
			var format = settingFormats[wrapper.attr('data-format')];
			var field = $('#' + wrapper.attr('data-field'));
			var template = wrapper.children('template')[0];

			if (!format || !field.length || !template || !template.content) {
				return;
			}

			var editor = wrapper.children('.lwseo-rows__editor');
			var list = editor.children('.lwseo-rows__list');
			var addButton = editor.find('.lwseo-rows__add');
			var count = editor.find('.lwseo-rows__count');
			var filter = editor.children('.lwseo-rows__filter');
			var limit = Number(wrapper.attr('data-limit')) || 0;
			var hints = wrapper.data('hints') || {};
			// Lines that are not rows are kept as they are and written back after the rows.
			var leftover = [];
			var view = editorView(wrapper, editor, field, load);

			function rowValues(row) {
				var values = {};

				$(row).find('[data-field]').each(function() {
					values[$(this).attr('data-field')] = $(this).val();
				});

				return values;
			}

			function allValues() {
				return list.children().get().map(rowValues);
			}

			function addRow(values) {
				var row = $(document.importNode(template.content, true).firstElementChild);

				$.each(values || {}, function(key, value) {
					row.find('[data-field="' + key + '"]').val(String(value));
				});

				setupPageCell(row, values || {}, wrapper);
				list.append(row);

				return row;
			}

			function refresh() {
				var rows = list.children();
				var checks = format.check ? format.check(allValues()) : [];

				rows.each(function(index) {
					var hint = hints[checks[index]] || '';

					$(this).attr('aria-label', fillTemplate(wrapper.attr('data-item'), [index + 1]))
						.toggleClass('has-hint', hint !== '')
						.children('.lwseo-rows__hint').text(hint).prop('hidden', hint === '');
				});

				editor.children('.lwseo-rows__head').prop('hidden', rows.length === 0);
				editor.children('.lwseo-rows__empty').prop('hidden', rows.length > 0);
				filter.prop('hidden', rows.length <= 10);
				addButton.prop('disabled', limit > 0 && rows.length >= limit);
				count.text(limit > 0 && rows.length ? fillTemplate(count.attr('data-template'), [rows.length, limit]) : '');
				view.kept(leftover.length);
			}

			// Write the rows back to the field. Only ever called after the user changes something,
			// so opening the screen never alters the stored text.
			function save() {
				field.val(settingFormats.write(allValues(), leftover, format)).trigger('change');
				refresh();
			}

			function load() {
				var parsed = settingFormats.read(field.val(), format);

				leftover = parsed.leftover;
				list.empty();
				parsed.rows.forEach(addRow);
				refresh();
			}

			list.on('input change', '[data-field]', save);

			list.on('click', '.lwseo-rows__remove', function() {
				var row = $(this).closest('.lwseo-rows__row');
				var neighbour = row.next().length ? row.next() : row.prev();

				row.remove();
				save();
				view.announce(wrapper.attr('data-removed'));
				(neighbour.length ? neighbour.find('input:visible, select:visible, button:visible').first() : addButton).trigger('focus');
			});

			// Enter moves down a row instead of submitting the whole settings form.
			list.on('keydown', 'input:not([type="search"])', function(event) {
				if (event.key !== 'Enter') {
					return;
				}

				event.preventDefault();

				var next = $(this).closest('.lwseo-rows__row').next();

				if (!next.length && !addButton.prop('disabled')) {
					next = addRow();
					refresh();
				}

				next.find('input:visible, select:visible, button:visible').first().trigger('focus');
			});

			addButton.on('click', function() {
				var row = addRow();

				refresh();
				row.find('input:visible, select:visible, button:visible').first().trigger('focus');
			});

			filter.find('input').on('input', function() {
				var query = $.trim($(this).val()).toLowerCase();

				list.children().each(function() {
					var text = $.map(rowValues(this), function(value) {
						return String(value);
					}).join(' ').toLowerCase();

					$(this).prop('hidden', query !== '' && text.indexOf(query) === -1);
				});
			});

			wrapper.prop('hidden', false);
			view.show(true);
		}

		$('.lwseo-rows').each(function() {
			initRowEditor($(this));
		});

		/* Opening hours ------------------------------------------------------ */

		// One row per day, each with any number of time ranges. Reads and writes the
		// opening hours field in the same way the row editors do.
		function initHoursEditor(wrapper) {
			var hours = settingFormats.hours;
			var field = $('#' + wrapper.attr('data-field'));
			var template = wrapper.children('template')[0];

			if (!hours || !field.length || !template || !template.content) {
				return;
			}

			var editor = wrapper.children('.lwseo-hours__editor');
			var days = editor.children('.lwseo-hours__day');
			var leftover = [];
			var view = editorView(wrapper, editor, field, load);

			function addRange(day, range) {
				var node = $(document.importNode(template.content, true).firstElementChild);

				node.find('[data-time="open"]').val(range ? range[0] : '');
				node.find('[data-time="close"]').val(range ? range[1] : '');
				day.find('.lwseo-hours__ranges').append(node);

				return node;
			}

			function readDays() {
				var values = {};

				days.each(function() {
					values[$(this).attr('data-day')] = $(this).find('.lwseo-hours__range').get().map(function(range) {
						return [$(range).find('[data-time="open"]').val(), $(range).find('[data-time="close"]').val()];
					});
				});

				return values;
			}

			function refresh() {
				days.each(function() {
					$(this).find('.lwseo-hours__closed').prop('hidden', $(this).find('.lwseo-hours__range').length > 0);
				});

				view.kept(leftover.length);
			}

			function save() {
				field.val(hours.format(readDays(), leftover)).trigger('change');
				refresh();
			}

			function load() {
				var parsed = hours.parse(field.val());

				leftover = parsed.leftover;

				days.each(function() {
					var day = $(this);

					day.find('.lwseo-hours__ranges').empty();
					(parsed.days[day.attr('data-day')] || []).forEach(function(range) {
						addRange(day, range);
					});
				});

				refresh();
			}

			editor.on('input change', 'input[type="time"]', save);

			editor.on('click', '.lwseo-hours__add', function() {
				// A range with no times yet is not written, so the field is unchanged until one is entered.
				addRange($(this).closest('.lwseo-hours__day')).find('input').first().trigger('focus');
				refresh();
			});

			editor.on('click', '.lwseo-rows__remove', function() {
				var day = $(this).closest('.lwseo-hours__day');

				$(this).closest('.lwseo-hours__range').remove();
				save();
				view.announce(wrapper.attr('data-removed'));
				day.find('.lwseo-hours__add').trigger('focus');
			});

			editor.on('click', '.lwseo-hours__copy', function() {
				var monday = readDays().Mo || [];

				days.filter('[data-day="Tu"], [data-day="We"], [data-day="Th"], [data-day="Fr"]').each(function() {
					var day = $(this);

					day.find('.lwseo-hours__ranges').empty();
					monday.forEach(function(range) {
						addRange(day, range);
					});
				});

				save();
				view.announce(wrapper.attr('data-copied'));
			});

			wrapper.prop('hidden', false);
			view.show(true);
		}

		$('.lwseo-hours').each(function() {
			initHoursEditor($(this));
		});

		/* Page picker -------------------------------------------------------- */

		// A list of pages chosen by searching, stored as the IDs the field already holds.
		function initPagePicker(wrapper) {
			var ids = settingFormats.ids;
			var field = $('#' + wrapper.attr('data-field'));

			if (!ids || !field.length) {
				return;
			}

			var editor = wrapper.children('.lwseo-picker__editor');
			var chosen = editor.children('.lwseo-picker__chosen');
			var search = editor.find('input[type="search"]');
			var count = editor.find('.lwseo-rows__count');
			var limit = Number(wrapper.attr('data-limit')) || 0;
			// Titles and notes for stored pages come from the server; searching adds to them.
			var items = wrapper.data('items') || {};
			var selected = [];
			var view = editorView(wrapper, editor, field, load);

			function render() {
				chosen.empty();

				selected.forEach(function(id) {
					var item = items[id] || {};
					var title = item.title || fillTemplate(wrapper.attr('data-fallback'), [id]);
					var remove = $('<button>', {type: 'button', 'class': 'button-link lwseo-rows__remove'}).append(
						$('<span>', {'class': 'dashicons dashicons-no-alt', 'aria-hidden': 'true'}),
						$('<span>', {'class': 'screen-reader-text', text: (wrapper.attr('data-remove') || '') + ' ' + title})
					);

					remove.on('click', function() {
						selected = selected.filter(function(other) {
							return other !== id;
						});
						save();
						view.announce(wrapper.attr('data-removed'));
						search.trigger('focus');
					});

					chosen.append($('<li>', {'class': 'lwseo-picker__item'}).append(
						$('<span>', {'class': 'lwseo-picker__title', text: title}),
						item.note ? $('<span>', {'class': 'lwseo-picker__note', text: item.note}) : null,
						remove
					));
				});

				editor.children('.lwseo-picker__empty').prop('hidden', selected.length > 0);
				search.prop('disabled', limit > 0 && selected.length >= limit);
				count.text(limit > 0 && selected.length ? fillTemplate(count.attr('data-template'), [selected.length, limit]) : '');
			}

			function save() {
				field.val(ids.format(selected)).trigger('change');
				render();
			}

			function load() {
				selected = ids.parse(field.val());
				render();
			}

			var clear = bindSearch(search, editor.children('.lwseo-picker__results'), editor.children('.lwseo-picker__status'), ['post'], function(item) {
				items[item.id] = {title: item.title, note: ''};
				selected.push(item.id);
				clear();
				search.val('');
				save();
				search.trigger('focus');
			}, function(item) {
				return selected.indexOf(item.id) !== -1;
			});

			wrapper.prop('hidden', false);
			view.show(true);
		}

		$('.lwseo-picker').each(function() {
			initPagePicker($(this));
		});

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

					field.find('.lwseo-image__id').val(attachment.id);
					field.attr('data-width', attachment.width || '');
					// A scripted value fires no event, so announce it for the previews and the unsaved-changes guard.
					field.find('.lwseo-image__url').val(attachment.url).trigger('change');
				});

				field.data('frame', frame);
			}

			frame.open();
		});

		// A hand-edited URL no longer belongs to the previously chosen attachment, so its ID and width are unknown.
		$('.lwseo-image__url').on('input', function() {
			$(this).closest('.lwseo-image').removeAttr('data-width').find('.lwseo-image__id').val('');
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
		// Delegated, because the links come and go as checks pass and fail.
		$('.lwseo-checks').on('click', 'a[href^="#"]', function(event) {
			var target = $(this.hash);

			if (!target.length) {
				return;
			}

			event.preventDefault();
			activateTab($('#' + target.closest('[role="tabpanel"]').attr('aria-labelledby')));
			target.closest('details').attr('open', true);
			target.trigger('focus');
		});

		/* Editor previews and checks ----------------------------------------- */

		// The server prints the panel in its saved state. From here on, the previews, meters,
		// and checks follow the page as it is edited, using the same rules as
		// Lightweight_SEO_Meta_Boxes::get_editor_checks().

		var metabox = $('.lwseo-metabox');

		// Count characters (code points), matching the server-side guidance for non-Latin text.
		function textLength(text) {
			return Array.from(String(text)).length;
		}

		// Mirror the server's preview text: no markup, block comments, or shortcodes, and single spaces.
		function plainText(value) {
			var text = String(value)
				.replace(/<!--[\s\S]*?-->/g, ' ')
				.replace(/<(script|style)\b[^>]*>[\s\S]*?<\/\1>/gi, ' ')
				.replace(/\[\/?[a-z][\w-]*(?:\s[^\]]*)?\]/gi, ' ')
				.replace(/<\/?[a-z!][^>]*>/gi, ' ');

			// A detached textarea decodes entities without ever running markup.
			return $.trim($('<textarea>').html(text).val().replace(/\s+/g, ' '));
		}

		// Mirror Lightweight_SEO_Text::summarize(): 160 characters, broken on a word, with an ellipsis.
		function summarize(text) {
			var limit = 160;
			var characters = Array.from($.trim(text));

			if (characters.length <= limit) {
				return characters.join('');
			}

			var summary = characters.slice(0, limit);
			var boundary = summary.lastIndexOf(' ');

			if (boundary !== -1 && boundary >= limit / 2) {
				summary = summary.slice(0, boundary);
			}

			return summary.join('').replace(/[ \t\n\r.,;:-]+$/, '') + '…';
		}

		function urlHost(value) {
			try {
				return new URL(value).hostname.toLowerCase();
			} catch (error) {
				return '';
			}
		}

		// One rule per check ID. true passes, false fails, a string picks a named failure
		// message, and null means the check does not apply. "conflicts" is decided by the server.
		var checkRules = {
			title: function(state) {
				return state.title !== '';
			},
			description: function(state) {
				return state.description !== '';
			},
			title_length: function(state) {
				var length = textLength(state.title);

				return length >= 30 && length <= 60;
			},
			description_length: function(state) {
				var length = textLength(state.description);

				return length >= 120 && length <= 160;
			},
			social_image: function(state) {
				return state.image !== '';
			},
			social_image_width: function(state) {
				return state.imageWidth ? state.imageWidth >= 1200 : null;
			},
			indexing: function(state) {
				return !state.noindex;
			},
			canonical: function(state) {
				if (state.canonical === '') {
					return null;
				}

				var host = urlHost(state.canonical);

				return host === '' ? 'invalid' : host === state.homeHost;
			}
		};

		function initEditorPanel() {
			var tokens = metabox.data('tokens') || {};
			var titleFormat = metabox.attr('data-title-format') || '%title%';
			var homeHost = (metabox.attr('data-home-host') || '').toLowerCase();
			var defaultImage = metabox.attr('data-default-image') || '';
			var titleField = $('#lightweight_seo_title');
			var descriptionField = $('#lightweight_seo_description');
			var imageField = metabox.find('.lwseo-image');
			var checkList = metabox.find('.lwseo-checks');
			var badge = metabox.find('.lwseo-checks-badge');
			var lastCount = null;
			// What the editor currently holds. The server's values stand until the editor reports its own.
			var post = {
				descriptionFallback: descriptionField.attr('data-fallback') || '',
				featuredImage: metabox.attr('data-featured-image') || '',
				featuredWidth: Number(metabox.attr('data-featured-width')) || 0
			};

			// Mirror the server: %title%, %sitename%, %tagline%, and %sep% are replaced in custom values.
			function resolveTokens(text) {
				$.each(tokens, function(token, value) {
					text = text.split(token).join(value);
				});

				return text;
			}

			function readState() {
				var title = $.trim(titleField.val());
				var description = $.trim(descriptionField.val());
				var ownImage = $.trim(imageField.find('.lwseo-image__url').val());
				var state = {
					title: plainText(resolveTokens(title || titleFormat)),
					description: plainText(description ? resolveTokens(description) : post.descriptionFallback),
					image: ownImage || post.featuredImage || defaultImage,
					imageWidth: 0,
					noindex: $('#lightweight_seo_noindex').prop('checked'),
					canonical: $.trim($('#lightweight_seo_canonical_url').val()),
					homeHost: homeHost
				};

				if (ownImage) {
					state.imageWidth = Number(imageField.attr('data-width')) || 0;
				} else if (post.featuredImage) {
					state.imageWidth = post.featuredWidth;
				}

				state.socialTitle = plainText(resolveTokens($.trim($('#lightweight_seo_social_title').val()))) || state.title;
				state.socialDescription = plainText(resolveTokens($.trim($('#lightweight_seo_social_description').val()))) || state.description;

				return state;
			}

			function renderLength(fieldId, text) {
				var length = textLength(text);
				var meter = $('[data-meter-for="' + fieldId + '"]');
				var max = Number(meter.data('max'));

				$('[data-count-for="' + fieldId + '"]').text(length);
				meter.toggleClass('is-in-range', length >= Number(meter.data('min')) && length <= max);
				// The guidance range ends at 80% of the track, leaving room to show an overrun.
				meter.find('.lwseo-meter__bar').css('width', Math.min(100, (length / max) * 80) + '%');
			}

			function renderShareImage(url) {
				var media = metabox.find('.lwseo-preview__media');
				var image = media.find('img');

				if (!url) {
					image.remove();
					return;
				}

				if (!image.length) {
					image = $('<img>', {alt: ''}).appendTo(media);
				}

				if (image.attr('src') !== url) {
					image.attr('src', url);
				}
			}

			// Switch one row between its two outcomes. Text is set as text, never as markup.
			function renderCheck(row, result) {
				var passes = result === true;
				var failure = typeof result === 'string' ? row.attr('data-fail-' + result) : '';
				var target = row.attr('data-target') || '';
				var action = row.children('.lwseo-status__action');

				row.prop('hidden', result === null);

				if (result === null) {
					return;
				}

				row.toggleClass('is-success', passes).toggleClass('is-warning', !passes);
				row.children('.lwseo-status__icon').toggleClass('dashicons-yes-alt', passes).toggleClass('dashicons-warning', !passes);
				row.find('.lwseo-status__title .screen-reader-text').text((checkList.attr(passes ? 'data-prefix-success' : 'data-prefix-warning') || '') + ' ');
				row.find('.lwseo-status__text').text(passes ? row.attr('data-pass') : (failure || row.attr('data-fail')));

				if (passes || !target) {
					action.remove();
				} else if (!action.length) {
					$('<a>', {'class': 'lwseo-status__action', href: target, text: checkList.attr('data-edit-label') || ''}).appendTo(row);
				}
			}

			function renderBadge(count) {
				var label = count ? (badge.attr(count === 1 ? 'data-review-one' : 'data-review-many') || '').replace('%d', count) : (badge.attr('data-review-clear') || '');

				badge.toggleClass('is-warning', count > 0).toggleClass('is-success', count === 0);
				badge.find('.lwseo-checks-badge__count').toggleClass('dashicons dashicons-yes', count === 0).text(count || '');
				badge.find('.screen-reader-text').text(label);

				if (lastCount !== null && lastCount !== count) {
					// Only a change made while editing is announced and, when it clears the list, celebrated.
					metabox.find('.lwseo-checks-live').text(label);
					badge.toggleClass('is-cleared', count === 0);
				}

				lastCount = count;
			}

			function render() {
				var state = readState();

				metabox.find('[data-preview="title"]').text(state.title);
				metabox.find('[data-preview="description"]').text(state.description);
				metabox.find('[data-preview="social-title"]').text(state.socialTitle);
				metabox.find('[data-preview="social-description"]').text(state.socialDescription);
				renderLength('lightweight_seo_title', state.title);
				renderLength('lightweight_seo_description', state.description);
				renderShareImage(state.image);

				checkList.children('[data-check]').each(function() {
					var rule = checkRules[$(this).attr('data-check')];

					if (rule) {
						renderCheck($(this), rule(state));
					}
				});

				renderBadge(checkList.children('.is-warning').not('[hidden]').length);
			}

			function setPost(changes) {
				var changed = false;

				$.each(changes, function(key, value) {
					if (post[key] !== value) {
						post[key] = value;
						changed = true;
					}
				});

				if (changed) {
					render();
				}
			}

			function setTitle(title) {
				if (tokens['%title%'] !== title) {
					tokens['%title%'] = title;
					render();
				}
			}

			// Classic editor: the title and excerpt are plain fields, and the content is in the visual or text editor.
			function classicContent() {
				var visual = window.tinymce && window.tinymce.get ? window.tinymce.get('content') : null;

				return visual && !visual.isHidden() ? visual.getContent() : ($('#content').val() || '');
			}

			$('#title').on('input', function() {
				setTitle(this.value);
			});
			$('#excerpt').on('input', function() {
				setPost({descriptionFallback: summarize(plainText($.trim(this.value) ? this.value : classicContent()))});
			});

			// Block editor: read the edited post from its data store, when there is one.
			var data = window.wp && window.wp.data;
			var editor = null;

			try {
				editor = data && data.select ? data.select('core/editor') : null;
			} catch (error) {
				editor = null;
			}

			function readBlockEditor() {
				var title = editor.getEditedPostAttribute('title');
				var excerpt = editor.getEditedPostAttribute('excerpt');
				var featured = editor.getEditedPostAttribute('featured_media');

				if (typeof title === 'string') {
					setTitle(title);
				}

				// Content is only serialized while it is what the description falls back to.
				if (typeof excerpt === 'string' && !$.trim(descriptionField.val())) {
					var source = $.trim(excerpt) ? excerpt : editor.getEditedPostAttribute('content');

					if (typeof source === 'string') {
						setPost({descriptionFallback: summarize(plainText(source))});
					}
				}

				if (featured === 0) {
					setPost({featuredImage: '', featuredWidth: 0});
				} else if (featured) {
					var media = data.select('core').getMedia(featured, {context: 'view'});

					if (media && media.source_url) {
						setPost({featuredImage: media.source_url, featuredWidth: (media.media_details && Number(media.media_details.width)) || 0});
					}
				}
			}

			var hasBlockEditor = !!editor && typeof editor.getEditedPostAttribute === 'function';

			if (hasBlockEditor) {
				var pending = null;

				// The store changes on every keystroke and selection, so read it at most a few times a second.
				data.subscribe(function() {
					if (pending) {
						return;
					}

					pending = window.setTimeout(function() {
						pending = null;
						readBlockEditor();
					}, 300);
				});
			}

			metabox.on('input change', 'input, textarea, select', function() {
				// Clearing the description brings the content-derived fallback back, so refresh it first.
				if (hasBlockEditor) {
					readBlockEditor();
				}

				render();
			});
			render();
		}

		if (metabox.length) {
			initEditorPanel();
		}
	});

})(jQuery);
