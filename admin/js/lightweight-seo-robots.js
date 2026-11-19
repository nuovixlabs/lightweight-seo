/**
 * Lightweight SEO served-file checks.
 *
 * Reads a robots.txt file the way RFC 9309 describes it and compares it with
 * what the plugin expects to be served, and reads the other addresses the AI
 * readiness report fetches. Pure functions with no DOM or network access: the
 * settings screen fetches each address and passes the answers in, and the same
 * file runs under Node for tests/js/robots.test.js.
 *
 * check(response, expected) returns one result per row of the report:
 *   {id, state, key, values}
 * id      The row: fetch, rules, blocked, sitemap, or signals; readiness() adds sitemapXml,
 *         linkHeaders, markdown, llms, apiCatalog, skills, mcp, and ard.
 * state   success, warning, info, or off, as Lightweight_SEO_Admin_UI::status() uses them.
 * key     Which message of the row applies. The server prints each message. "unchecked"
 *         means this browser could not fetch the address, which says nothing about the site.
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

	// The rules a crawler follows: its own group, else the group for every crawler, else none.
	function rulesFollowedBy(parsed, token) {
		var own = rulesNaming(parsed, token);
		var every = own === null ? rulesNaming(parsed, '*') : null;

		return own !== null ? own : (every || []);
	}

	/**
	 * Compare the served file with the card. A crawler the card switches off must have a group of
	 * its own that disallows the whole site. A crawler the card allows gets no group from the plugin
	 * and follows the rules that apply to it, so it passes while those do not shut it out of the
	 * whole site. Whether the plugin's part is served at all is told by its marker comment.
	 */
	function rulesResult(parsed, body, expectedRules, marker) {
		var missing = [];
		var different = [];
		var tokens = Object.keys(expectedRules);

		if (!tokens.length) {
			return null;
		}

		if (marker && String(body).indexOf(marker) === -1) {
			return {id: 'rules', state: 'warning', key: 'absent', values: []};
		}

		tokens.forEach(function(token) {
			var own = rulesNaming(parsed, token);

			if (expectedRules[token]) {
				if (blocksWholeSite(rulesFollowedBy(parsed, token))) {
					different.push(token);
				}
			} else if (own === null) {
				missing.push(token);
			} else if (!blocksWholeSite(own)) {
				different.push(token);
			}
		});

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

	function signalsResult(parsed, contentSignal, signalOff) {
		if (parsed.signals.length) {
			return {id: 'signals', state: 'success', key: 'present', values: parsed.signals};
		}

		if (contentSignal) {
			return {id: 'signals', state: 'warning', key: 'missing', values: ['Content-Signal: ' + contentSignal]};
		}

		return signalOff
			? {id: 'signals', state: 'off', key: 'off', values: []}
			: {id: 'signals', state: 'info', key: 'none', values: []};
	}

	/* Other addresses the readiness report fetches ---------------------------- */

	function answered(response) {
		return Boolean(response && response.fetched && response.status === 200);
	}

	// A request that failed tells nothing about the site, only that this browser could not ask.
	function failed(response) {
		return !response || !response.fetched;
	}

	function unchecked(id) {
		return {id: id, state: 'info', key: 'unchecked', values: []};
	}

	function mediaType(response) {
		return String((response && response.contentType) || '').split(';')[0].trim().toLowerCase();
	}

	function json(response) {
		try {
			return JSON.parse(response.body);
		} catch (error) {
			return null;
		}
	}

	// /sitemap.xml, after the redirects the browser followed, or a Sitemap line in robots.txt.
	function sitemapXmlResult(response, robotsSitemaps) {
		if (failed(response) && !robotsSitemaps.length) {
			return unchecked('sitemapXml');
		}

		if (answered(response) && (response.xmlRoot === 'urlset' || response.xmlRoot === 'sitemapindex')) {
			return {id: 'sitemapXml', state: 'success', key: 'valid', values: [response.url || '']};
		}

		return robotsSitemaps.length
			? {id: 'sitemapXml', state: 'success', key: 'listed', values: robotsSitemaps}
			: {id: 'sitemapXml', state: 'warning', key: 'missing', values: []};
	}

	// Relations the reference names as useful to agents.
	var AGENT_RELATIONS = ['api-catalog', 'service-desc', 'describedby'];

	function linkHeadersResult(response) {
		if (failed(response)) {
			return unchecked('linkHeaders');
		}

		var header = String((response && response.link) || '');
		var found = [];
		var pattern = /rel\s*=\s*(?:"([^"]*)"|([^\s;,]+))/gi;
		var match;

		while ((match = pattern.exec(header))) {
			(match[1] !== undefined ? match[1] : match[2]).toLowerCase().split(/\s+/).forEach(function(relation) {
				if (AGENT_RELATIONS.indexOf(relation) !== -1 && found.indexOf(relation) === -1) {
					found.push(relation);
				}
			});
		}

		return found.length
			? {id: 'linkHeaders', state: 'success', key: 'found', values: found}
			: {id: 'linkHeaders', state: 'info', key: 'none', values: []};
	}

	function markdownResult(response) {
		if (failed(response)) {
			return unchecked('markdown');
		}

		return answered(response) && mediaType(response) === 'text/markdown'
			? {id: 'markdown', state: 'success', key: 'found', values: []}
			: {id: 'markdown', state: 'info', key: 'none', values: []};
	}

	// An llms.txt file is Markdown that opens with a level-one heading.
	function llmsResult(response, enabled) {
		if (failed(response)) {
			return unchecked('llms');
		}

		var first = answered(response) ? String(response.body).split(/\r\n|\n|\r/).filter(function(line) {
			return line.trim() !== '';
		})[0] || '' : '';

		if (/^# \S/.test(first) && mediaType(response).indexOf('text/') === 0) {
			return {id: 'llms', state: 'success', key: 'found', values: [response.url || '']};
		}

		return enabled
			? {id: 'llms', state: 'warning', key: 'missing', values: []}
			: {id: 'llms', state: 'off', key: 'off', values: []};
	}

	// What each well-known file must hold to count, following the reference.
	var WELL_KNOWN = {
		apiCatalog: function(data, response) {
			return mediaType(response) === 'application/linkset+json' && Array.isArray(data.linkset) && data.linkset.length > 0;
		},
		skills: function(data) {
			return Array.isArray(data.skills);
		},
		mcp: function(data) {
			var card = Array.isArray(data) ? data[0] || {} : data;
			var name = card.serverInfo && card.serverInfo.name ? card.serverInfo.name : card.name;

			return typeof name === 'string' && name.trim() !== '';
		},
		ard: function(data) {
			return Boolean(data.specVersion) && Array.isArray(data.entries) && data.entries.length > 0;
		}
	};

	// A file this version does not publish: found, or a neutral "not offered", never a warning.
	function wellKnownResult(id, responses) {
		var list = responses || [];

		// Several addresses: not checked only when none of them could be asked.
		if (list.length && list.every(failed)) {
			return unchecked(id);
		}

		var found = list.filter(function(response) {
			var data = answered(response) ? json(response) : null;

			return data !== null && typeof data === 'object' && WELL_KNOWN[id](data, response);
		})[0];

		return found
			? {id: id, state: 'success', key: 'found', values: [found.url || '']}
			: {id: id, state: 'info', key: 'none', values: []};
	}

	/**
	 * Settle a row the server could only partly decide, from the browser's result for another row.
	 *
	 * "Who writes robots.txt" knows only that no file is on disk and no other plugin it can see adds
	 * rules. The served file decides the rest: the plugin's part being served passes it, even when a
	 * rule in it differs, and its absence means something the server cannot see answers or rewrites
	 * the file. Any other result, such as a file that could not be fetched, leaves the statement as it is.
	 */
	function settle(result) {
		if (!result || result.id !== 'rules') {
			return null;
		}

		// Any answer but "absent" means the file served is WordPress's, with the plugin's part in it.
		if (['present', 'missing', 'different'].indexOf(result.key) !== -1) {
			return {state: 'success', key: 'present'};
		}

		return result.key === 'absent' ? {state: 'warning', key: 'absent'} : null;
	}

	/**
	 * Every result of the readiness report that the browser decides.
	 *
	 * answers   {robots, sitemapXml, home, markdown, llms, apiCatalog: [], skills: [], mcp: [], ard: []},
	 *           each an answer as check() takes it, plus xmlRoot (the root element name, or null when the
	 *           body is not XML) for sitemapXml and link (the Link header) for home.
	 * expected  What check() takes, plus llmsEnabled and signalOff.
	 */
	function readiness(answers, expected) {
		var settings = expected || {};
		var robots = answers.robots || {};
		var sitemaps = answered(robots) ? parse(robots.body).sitemaps : [];

		return check(robots, settings).concat([
			sitemapXmlResult(answers.sitemapXml, sitemaps),
			linkHeadersResult(answers.home),
			markdownResult(answers.markdown),
			llmsResult(answers.llms, Boolean(settings.llmsEnabled)),
			wellKnownResult('apiCatalog', answers.apiCatalog),
			wellKnownResult('skills', answers.skills),
			wellKnownResult('mcp', answers.mcp),
			wellKnownResult('ard', answers.ard)
		]);
	}

	/**
	 * Compare a served answer with what is expected.
	 *
	 * response  {fetched, status, contentType, body, url}; fetched is false when the request failed.
	 * expected  {rules: {token: allowed}, marker, crawlers: [tokens checked for a whole-site block], sitemapLine, contentSignal}
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
		results.push(rulesResult(parsed, response.body, settings.rules || {}, settings.marker || ''));
		results.push(blockedResult(parsed, settings.crawlers || []));
		results.push(sitemapResult(parsed, settings.sitemapLine || ''));
		results.push(signalsResult(parsed, settings.contentSignal || '', Boolean(settings.signalOff)));

		return results.filter(Boolean);
	}

	return {
		parse: parse,
		allows: allows,
		rulesNaming: rulesNaming,
		blocksWholeSite: blocksWholeSite,
		check: check,
		readiness: readiness,
		settle: settle
	};
});
