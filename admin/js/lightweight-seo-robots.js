/**
 * Lightweight SEO served robots.txt checks.
 *
 * Reads a robots.txt file the way RFC 9309 describes it and compares it with
 * what the plugin expects to be served. Pure functions with no DOM or network
 * access: the settings screen fetches the file and passes the answer in, and
 * the same file runs under Node for tests/js/robots.test.js.
 *
 * check(response, expected) returns one result per row of the report:
 *   {id, state, key, values}
 * id      The row: fetch, rules, blocked, sitemap, or signals.
 * state   success, warning, or info, as Lightweight_SEO_Admin_UI::status() uses them.
 * key     Which message of the row applies. The server prints each message.
 * values  Text that fills the message's %s, joined by the caller.
 */
(function(root, factory) {
	var robots = factory();

	if (typeof module === 'object' && module.exports) {
		module.exports = robots;
	} else {
		root.lightweightSeoRobots = robots;
	}
})(typeof self !== 'undefined' ? self : this, function() {
	'use strict';

	// A path no site is likely to allow on its own, to tell "Disallow: /" from "Disallow: /$".
	var PROBE_PATH = '/lightweight-seo-robots-probe/';

	// A product token is matched without its version and without regard to case.
	function agentToken(value) {
		return String(value).trim().split(/[\s\/]/)[0].toLowerCase();
	}

	/**
	 * Split a file into groups, sitemaps, and content signals.
	 *
	 * Consecutive user-agent lines share one group. A rule before any user-agent
	 * line belongs to no group and is ignored. Sitemap and Content-Signal lines
	 * stand on their own and do not end a group.
	 */
	function parse(text) {
		var result = {groups: [], sitemaps: [], signals: []};
		var group = null;
		var lastWasAgent = false;

		String(text === undefined || text === null ? '' : text).split(/\r\n|\n|\r/).forEach(function(rawLine) {
			var line = rawLine.replace(/#.*$/, '').trim();
			var colon = line.indexOf(':');
			var key;
			var value;

			if (colon < 1) {
				return;
			}

			key = line.slice(0, colon).trim().toLowerCase();
			value = line.slice(colon + 1).trim();

			if (key === 'user-agent') {
				if (!lastWasAgent || !group) {
					group = {agents: [], rules: []};
					result.groups.push(group);
				}

				group.agents.push(agentToken(value));
				lastWasAgent = true;
				return;
			}

			if (key === 'sitemap') {
				if (value) {
					result.sitemaps.push(value);
				}
				return;
			}

			if (key === 'content-signal') {
				if (value) {
					result.signals.push(value);
				}
				return;
			}

			if ((key === 'allow' || key === 'disallow') && group) {
				lastWasAgent = false;

				// An empty path matches nothing, so "Disallow:" allows everything.
				if (value) {
					group.rules.push({allow: key === 'allow', path: value});
				}
			}
		});

		return result;
	}

	function patternMatches(pattern, path) {
		var anchored = pattern.charAt(pattern.length - 1) === '$';
		var body = anchored ? pattern.slice(0, -1) : pattern;
		var expression = body.split('*').map(function(part) {
			return part.replace(/[.+?^${}()|[\]\\]/g, '\\$&');
		}).join('.*');

		return new RegExp('^' + expression + (anchored ? '$' : '')).test(path);
	}

	// The longest matching rule wins, and an allow rule wins a tie.
	function allows(rules, path) {
		var best = null;

		rules.forEach(function(rule) {
			if (!patternMatches(rule.path, path)) {
				return;
			}

			if (!best || rule.path.length > best.path.length || (rule.path.length === best.path.length && rule.allow)) {
				best = rule;
			}
		});

		return !best || best.allow;
	}

	// Every rule of every group that names this product token, or null when no group names it.
	function rulesNaming(parsed, token) {
		var wanted = agentToken(token);
		var found = false;
		var rules = [];

		parsed.groups.forEach(function(group) {
			if (group.agents.indexOf(wanted) !== -1) {
				found = true;
				rules = rules.concat(group.rules);
			}
		});

		return found ? rules : null;
	}

	function blocksWholeSite(rules) {
		return !allows(rules, '/') && !allows(rules, PROBE_PATH);
	}

	function fetchResult(response) {
		var type = String(response.contentType || '').split(';')[0].trim().toLowerCase();

		if (!response.fetched) {
			return {id: 'fetch', state: 'warning', key: 'failed', values: []};
		}

		if (response.status !== 200) {
			return {id: 'fetch', state: 'warning', key: 'status', values: [String(response.status)]};
		}

		if (type !== 'text/plain') {
			return {id: 'fetch', state: 'warning', key: 'type', values: [type || '-']};
		}

		return {id: 'fetch', state: 'success', key: 'ok', values: [response.url || '']};
	}

	function rulesResult(parsed, expectedRules) {
		var missing = [];
		var different = [];
		var tokens = Object.keys(expectedRules);

		if (!tokens.length) {
			return null;
		}

		tokens.forEach(function(token) {
			var rules = rulesNaming(parsed, token);

			if (rules === null) {
				missing.push(token);
			} else if (allows(rules, '/') !== Boolean(expectedRules[token])) {
				different.push(token);
			}
		});

		if (missing.length === tokens.length) {
			return {id: 'rules', state: 'warning', key: 'absent', values: []};
		}

		if (missing.length) {
			return {id: 'rules', state: 'warning', key: 'missing', values: missing};
		}

		if (different.length) {
			return {id: 'rules', state: 'warning', key: 'different', values: different};
		}

		return {id: 'rules', state: 'success', key: 'present', values: [String(tokens.length)]};
	}

	function blockedResult(parsed, crawlers) {
		var blocked = crawlers.filter(function(token) {
			var rules = rulesNaming(parsed, token);

			return rules !== null && blocksWholeSite(rules);
		});

		// A group that also has an Allow line still lets those paths through, so it is not a block of every page.
		var withAllowLines = blocked.filter(function(token) {
			return rulesNaming(parsed, token).some(function(rule) {
				return rule.allow;
			});
		});

		if (!blocked.length) {
			return {id: 'blocked', state: 'success', key: 'open', values: crawlers};
		}

		return {id: 'blocked', state: 'warning', key: withAllowLines.length ? 'partly' : 'blocked', values: blocked};
	}

	function sitemapResult(parsed, sitemapLine) {
		if (parsed.sitemaps.length) {
			return {id: 'sitemap', state: 'success', key: 'present', values: parsed.sitemaps};
		}

		return sitemapLine
			? {id: 'sitemap', state: 'warning', key: 'missing', values: [sitemapLine]}
			: {id: 'sitemap', state: 'info', key: 'none', values: []};
	}

	function signalsResult(parsed, contentSignal) {
		if (parsed.signals.length) {
			return {id: 'signals', state: 'success', key: 'present', values: parsed.signals};
		}

		return contentSignal
			? {id: 'signals', state: 'warning', key: 'missing', values: ['Content-Signal: ' + contentSignal]}
			: {id: 'signals', state: 'info', key: 'none', values: []};
	}

	/**
	 * Compare a served answer with what is expected.
	 *
	 * response  {fetched, status, contentType, body, url}; fetched is false when the request failed.
	 * expected  {rules: {token: allowed}, crawlers: [tokens checked for a whole-site block], sitemapLine, contentSignal}
	 */
	function check(response, expected) {
		var settings = expected || {};
		var first = fetchResult(response || {});
		var parsed;
		var results = [first];

		// Without a file there is nothing to read. A file served with the wrong type is still read.
		if (!response || !response.fetched || response.status !== 200) {
			return results;
		}

		parsed = parse(response.body);
		results.push(rulesResult(parsed, settings.rules || {}));
		results.push(blockedResult(parsed, settings.crawlers || []));
		results.push(sitemapResult(parsed, settings.sitemapLine || ''));
		results.push(signalsResult(parsed, settings.contentSignal || ''));

		return results.filter(Boolean);
	}

	return {
		parse: parse,
		allows: allows,
		rulesNaming: rulesNaming,
		blocksWholeSite: blocksWholeSite,
		check: check
	};
});
