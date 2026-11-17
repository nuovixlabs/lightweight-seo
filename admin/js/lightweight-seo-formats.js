/**
 * Lightweight SEO setting formats.
 *
 * Reads and writes the line formats that Lightweight_SEO_Settings stores, so the
 * settings screen can show them as rows, and holds the text rules the previews
 * share with the server: filling variables and shortening a search result.
 * Pure functions with no DOM access: the same file runs in the browser and
 * under Node for tests/js/formats.test.js.
 *
 * Each row format has three functions:
 *   parse(line)  A row object, or null when the line is not in this format.
 *   format(row)  The stored line, or '' while the row is incomplete.
 *   check(rows)  One hint key per row: '' when the row is fine.
 */
(function(root, factory) {
	var formats = factory();

	if (typeof module === 'object' && module.exports) {
		module.exports = formats;
	} else {
		root.lightweightSeoFormats = formats;
	}
})(typeof self !== 'undefined' ? self : this, function() {
	'use strict';

	var DAYS = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'];
	var REDIRECT_STATUSES = [301, 302, 307, 308];
	var GONE = 410;

	function splitLines(text) {
		return String(text === undefined || text === null ? '' : text).split(/\r\n|\n|\r/);
	}

	function words(line) {
		return String(line).trim().split(/\s+/).filter(Boolean);
	}

	// A value typed into one cell must stay one word, because the stored line is split on spaces.
	function oneWord(value) {
		return String(value === undefined || value === null ? '' : value).trim().replace(/\s+/g, '%20');
	}

	/**
	 * Split stored text into rows and the lines that are not rows.
	 *
	 * @param {string} text   Stored value, one item per line.
	 * @param {Object} format A row format.
	 * @return {{rows: Object[], leftover: string[]}} Blank lines are dropped; unreadable lines are kept verbatim.
	 */
	function read(text, format) {
		var result = {rows: [], leftover: []};

		splitLines(text).forEach(function(line) {
			if (line.trim() === '') {
				return;
			}

			var row = format.parse(line);

			if (row) {
				result.rows.push(row);
			} else {
				result.leftover.push(line.trim());
			}
		});

		return result;
	}

	/**
	 * Join rows and kept lines back into the stored text.
	 *
	 * @param {Object[]} rows     Row objects.
	 * @param {string[]} leftover Lines kept verbatim.
	 * @param {Object}   format   A row format.
	 * @return {string} One item per line. Incomplete rows are left out.
	 */
	function write(rows, leftover, format) {
		return rows.map(format.format).filter(Boolean).concat(leftover || []).join('\n');
	}

	// Redirect rules: "/old-path /new-path 301". The status is optional and defaults to 301.
	// "/old-path 410" has no destination: the address answers that it is gone.
	var redirects = {
		parse: function(line) {
			var parts = words(line);

			if (parts.length < 1 || parts.length > 3) {
				return null;
			}

			if (parts[1] === '410' || parts[2] === '410') {
				return {source: parts[0], target: '', status: GONE};
			}

			var status = parseInt(parts[2], 10);

			return {
				source: parts[0],
				target: parts[1] || '',
				status: REDIRECT_STATUSES.indexOf(status) === -1 ? 301 : status
			};
		},

		format: function(row) {
			var source = oneWord(row.source);
			var target = oneWord(row.target);
			var status = parseInt(row.status, 10);

			if (source !== '' && status === GONE) {
				return source + ' ' + GONE;
			}

			if (source === '' || target === '') {
				return '';
			}

			return source + ' ' + target + ' ' + (REDIRECT_STATUSES.indexOf(status) === -1 ? 301 : status);
		},

		// Cells that take no part in the stored line for this row.
		inactive: function(row) {
			return parseInt(row.status, 10) === GONE ? ['target'] : [];
		},

		check: function(rows) {
			var lastIndex = {};

			rows.forEach(function(row, index) {
				lastIndex[oneWord(row.source)] = index;
			});

			return rows.map(function(row, index) {
				var source = oneWord(row.source);
				var gone = parseInt(row.status, 10) === GONE;
				var target = gone ? '' : oneWord(row.target);

				if (source === '' && target === '') {
					return '';
				}

				if (source === '' || (target === '' && !gone)) {
					return 'incomplete';
				}

				if (source === target) {
					return 'same';
				}

				// The server keeps the last rule for a path, so earlier ones never apply.
				return lastIndex[source] === index ? '' : 'duplicate';
			});
		}
	};

	// Hreflang mappings: "post:123 en-GB https://example.co.uk/page/". A retained legacy
	// line has no page of its own ("en-GB https://example.co.uk") and is kept as it is.
	var hreflang = {
		parse: function(line) {
			var parts = words(line);
			var reference = /^(post|term|user):([0-9]+)$/.exec(parts[0] || '');

			if (reference && parts.length >= 1 && parts.length <= 3) {
				return {
					type: reference[1],
					id: parseInt(reference[2], 10),
					language: parts[1] || '',
					url: parts[2] || ''
				};
			}

			if (parts.length === 2) {
				return {type: 'mirror', id: 0, language: parts[0], url: parts[1]};
			}

			return null;
		},

		format: function(row) {
			var language = oneWord(row.language);
			var url = oneWord(row.url);
			var id = parseInt(row.id, 10) || 0;

			if (language === '' || url === '') {
				return '';
			}

			if (row.type === 'mirror') {
				return language + ' ' + url;
			}

			// Without a page the line would read as a legacy mirror line, so it is not written at all.
			if (['post', 'term', 'user'].indexOf(row.type) === -1 || id < 1) {
				return '';
			}

			return row.type + ':' + id + ' ' + language + ' ' + url;
		},

		check: function(rows) {
			var seen = {};

			return rows.map(function(row) {
				var language = oneWord(row.language);
				var url = oneWord(row.url);
				var id = parseInt(row.id, 10) || 0;
				var hasPage = row.type === 'mirror' || id > 0;

				if (!hasPage && language === '' && url === '') {
					return '';
				}

				if (!hasPage || language === '' || url === '') {
					return 'incomplete';
				}

				// The server keeps the first line for a page and language.
				var key = row.type + ':' + id + '|' + language.toLowerCase();

				if (seen[key]) {
					return 'duplicate';
				}

				seen[key] = true;

				return '';
			});
		}
	};

	// Opening hours: "Mo-Fr 09:00-17:00". A day may have several lines, one per time range.
	var HOURS_LINE = /^(Mo|Tu|We|Th|Fr|Sa|Su)(?:-(Mo|Tu|We|Th|Fr|Sa|Su))? ((?:[01][0-9]|2[0-3]):[0-5][0-9])-((?:[01][0-9]|2[0-3]):[0-5][0-9])$/;
	var HOURS_TIME = /^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/;

	var hours = {
		days: DAYS,

		/**
		 * @param {string} text Stored opening hours.
		 * @return {{days: Object, leftover: string[]}} For each day, a list of [open, close] pairs.
		 */
		parse: function(text) {
			var result = {days: {}, leftover: []};

			DAYS.forEach(function(day) {
				result.days[day] = [];
			});

			splitLines(text).forEach(function(line) {
				var match = HOURS_LINE.exec(line.trim());

				if (line.trim() === '') {
					return;
				}

				if (!match) {
					result.leftover.push(line.trim());
					return;
				}

				var index = DAYS.indexOf(match[1]);
				var last = match[2] ? DAYS.indexOf(match[2]) : index;

				// A range such as Fr-Mo runs over the weekend, so it wraps around the week.
				for (;;) {
					result.days[DAYS[index]].push([match[3], match[4]]);

					if (index === last) {
						break;
					}

					index = (index + 1) % DAYS.length;
				}
			});

			return result;
		},

		/**
		 * @param {Object}   days     For each day, a list of [open, close] pairs.
		 * @param {string[]} leftover Lines kept verbatim.
		 * @return {string} Stored lines. Neighbouring days with the same hours share a line.
		 */
		format: function(days, leftover) {
			var lines = [];
			var start = 0;

			function ranges(day) {
				return (days[day] || []).filter(function(range) {
					return HOURS_TIME.test(range[0]) && HOURS_TIME.test(range[1]);
				});
			}

			function signature(day) {
				return ranges(day).map(function(range) {
					return range.join('-');
				}).join(',');
			}

			while (start < DAYS.length) {
				var end = start;

				while (end + 1 < DAYS.length && signature(DAYS[end + 1]) === signature(DAYS[start])) {
					end += 1;
				}

				var label = DAYS[start] + (end > start ? '-' + DAYS[end] : '');

				ranges(DAYS[start]).forEach(function(range) {
					lines.push(label + ' ' + range[0] + '-' + range[1]);
				});

				start = end + 1;
			}

			return lines.concat(leftover || []).join('\n');
		}
	};

	// A list of post IDs: "12,40,7".
	var ids = {
		parse: function(text) {
			var seen = {};

			return String(text === undefined || text === null ? '' : text).split(/[^0-9]+/).map(function(part) {
				return parseInt(part, 10);
			}).filter(function(id) {
				if (!id || id < 1 || seen[id]) {
					return false;
				}

				seen[id] = true;

				return true;
			});
		},

		format: function(list) {
			return ids.parse((list || []).join(',')).join(',');
		}
	};

	// Variables that may have no value on a page, and what counts as a separator beside one.
	// These mirror Lightweight_SEO_Text::OPTIONAL_TOKENS and SEPARATOR_PATTERN on the server.
	var OPTIONAL_TOKENS = ['%page%', '%category%', '%author%', '%year%', '%tagline%'];
	var SEPARATOR_PATTERN = '(?:%sep%|[–—\\-|·•»\\/])';

	/**
	 * Fill the variables of a title or description, as the server does when the page renders.
	 *
	 * A variable from OPTIONAL_TOKENS that has no value is left out together with one
	 * separator beside it, so "%title% %sep% %page% %sep% %sitename%" has no gap on page one.
	 *
	 * @param {string} text   Text with variables such as %title%.
	 * @param {Object} tokens Values keyed by variable.
	 * @return {string} The text with every known variable replaced.
	 */
	function resolveTokens(text, tokens) {
		text = String(text === undefined || text === null ? '' : text);
		tokens = tokens || {};

		OPTIONAL_TOKENS.forEach(function(token) {
			if (text.indexOf(token) !== -1 && String(tokens[token] === undefined || tokens[token] === null ? '' : tokens[token]).trim() === '') {
				text = text
					.replace(new RegExp('\\s+' + SEPARATOR_PATTERN + '\\s+' + token, 'g'), '')
					.replace(new RegExp(token + '\\s+' + SEPARATOR_PATTERN + '\\s+', 'g'), '')
					.split(token).join('');
			}
		});

		Object.keys(tokens).forEach(function(token) {
			text = text.split(token).join(tokens[token]);
		});

		return text;
	}

	/**
	 * Shorten text the way a search result does: at the end of a word, with an ellipsis.
	 *
	 * @param {string} text  Text to shorten.
	 * @param {number} limit Most characters to keep, counted as a reader would.
	 * @return {string} The text itself when it fits, otherwise its start followed by "…".
	 */
	function truncate(text, limit) {
		var characters = Array.from(String(text === undefined || text === null ? '' : text).trim());

		if (!(limit > 0) || characters.length <= limit) {
			return characters.join('');
		}

		var cut = characters.slice(0, limit).join('');
		var lastSpace = cut.search(/\s+\S*$/);

		// A word that is cut in the middle is dropped, unless it is the only word there is.
		if (lastSpace > 0 && !/\s/.test(characters[limit])) {
			cut = cut.slice(0, lastSpace);
		}

		return cut.replace(/[\s.,;:!?–—\-|·•»\/]+$/, '') + '…';
	}

	return {
		read: read,
		write: write,
		redirects: redirects,
		hreflang: hreflang,
		hours: hours,
		ids: ids,
		resolveTokens: resolveTokens,
		truncate: truncate
	};
});
