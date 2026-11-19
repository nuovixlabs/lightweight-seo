'use strict';

// Run with: node --test tests/js/*.test.js
// The settings screen fetches /robots.txt in the administrator's browser and reads it with
// these functions. PHP decides what is expected; these tests pin how a served file is read.

const test = require('node:test');
const assert = require('node:assert/strict');
const robots = require('../../admin/js/lightweight-seo-robots.js');
const robotsReader = robots;

const {parse, allows, rulesNaming, blocksWholeSite, check} = robots;

const CRAWLERS = ['Googlebot', 'Bingbot', '*'];
const MARKER = '# Lightweight SEO AI Discovery (experimental)';

function served(body, extra) {
	return Object.assign({fetched: true, status: 200, contentType: 'text/plain; charset=utf-8', body: body, url: 'https://example.com/robots.txt'}, extra || {});
}

function byId(results) {
	return Object.fromEntries(results.map((result) => [result.id, result]));
}

test('consecutive user-agent lines share a group, and sitemaps stand apart', () => {
	const parsed = parse([
		'# comment',
		'Disallow: /ignored-before-any-group',
		'User-agent: Googlebot/2.1',
		'user-agent: BINGBOT',
		'Disallow: /private # trailing comment',
		'Sitemap: https://example.com/wp-sitemap.xml',
		'Allow: /private/open',
		'User-agent: *',
		'Disallow:',
	].join('\r\n'));

	assert.deepEqual(parsed.groups, [
		{agents: ['googlebot', 'bingbot'], rules: [{allow: false, path: '/private'}, {allow: true, path: '/private/open'}]},
		{agents: ['*'], rules: []},
	]);
	assert.deepEqual(parsed.sitemaps, ['https://example.com/wp-sitemap.xml']);
});

test('the longest matching rule wins and an allow rule wins a tie', () => {
	assert.equal(allows([{allow: false, path: '/'}], '/'), false);
	assert.equal(allows([{allow: false, path: '/'}, {allow: true, path: '/'}], '/'), true);
	assert.equal(allows([{allow: false, path: '/'}, {allow: true, path: '/blog'}], '/blog/post'), true);
	assert.equal(allows([{allow: true, path: '/'}, {allow: false, path: '/*.pdf$'}], '/files/a.pdf'), false);
	assert.equal(allows([{allow: true, path: '/'}, {allow: false, path: '/*.pdf$'}], '/files/a.pdf?x'), true);
	assert.equal(allows([], '/'), true);
});

test('only a rule set that shuts the whole site counts as a whole-site block', () => {
	assert.equal(blocksWholeSite([{allow: false, path: '/'}]), true);
	assert.equal(blocksWholeSite([{allow: false, path: '/*'}]), true);
	assert.equal(blocksWholeSite([{allow: false, path: '/$'}]), false);
	assert.equal(blocksWholeSite([{allow: false, path: '/wp-admin/'}]), false);
	assert.equal(blocksWholeSite([{allow: false, path: '/'}, {allow: true, path: '/'}]), false);
});

test('groups are found by product token, and several groups for one token are combined', () => {
	const parsed = parse('User-agent: GPTBot\nDisallow: /a\n\nUser-agent: gptbot\nDisallow: /b\n');

	assert.deepEqual(rulesNaming(parsed, 'GPTBot'), [{allow: false, path: '/a'}, {allow: false, path: '/b'}]);
	assert.equal(rulesNaming(parsed, 'ClaudeBot'), null);
});

test('the file found on a real site is reported: no plugin rules, Googlebot shut out, no sitemap line', () => {
	// 58 bytes, served instead of the virtual file WordPress writes.
	const body = 'User-agent: Googlebot\nDisallow: /\n\nUser-agent: *\nAllow: /\n';
	const results = byId(check(served(body), {
		rules: {'OAI-SearchBot': true, GPTBot: false},
		marker: MARKER,
		crawlers: CRAWLERS,
		sitemapLine: 'Sitemap: https://example.com/wp-sitemap.xml',
	}));

	assert.equal(body.length, 58);
	assert.deepEqual(results.fetch, {id: 'fetch', state: 'success', key: 'ok', values: ['https://example.com/robots.txt']});
	assert.deepEqual(results.rules, {id: 'rules', state: 'warning', key: 'absent', values: []});
	assert.deepEqual(results.blocked, {id: 'blocked', state: 'warning', key: 'blocked', values: ['Googlebot']});
	assert.deepEqual(results.sitemap, {id: 'sitemap', state: 'warning', key: 'missing', values: ['Sitemap: https://example.com/wp-sitemap.xml']});
});

test('the file WordPress and the plugin write together passes every row', () => {
	const body = [
		'User-agent: *',
		'Disallow: /wp-admin/',
		'Allow: /wp-admin/admin-ajax.php',
		'',
		'Sitemap: https://example.com/wp-sitemap.xml',
		'',
		MARKER,
		'# Not blocked here, so they follow the rules for all crawlers: OAI-SearchBot',
		'',
		'User-agent: GPTBot',
		'Disallow: /',
	].join('\n');
	const results = byId(check(served(body), {
		rules: {'OAI-SearchBot': true, GPTBot: false},
		marker: MARKER,
		crawlers: CRAWLERS,
		sitemapLine: 'Sitemap: https://example.com/wp-sitemap.xml',
	}));

	assert.deepEqual(results.rules, {id: 'rules', state: 'success', key: 'present', values: ['2']});
	assert.deepEqual(results.blocked, {id: 'blocked', state: 'success', key: 'open', values: CRAWLERS});
	assert.deepEqual(results.sitemap, {id: 'sitemap', state: 'success', key: 'present', values: ['https://example.com/wp-sitemap.xml']});
});

test('an allowed crawler follows its own group if another source wrote one, else the group for every crawler', () => {
	const expected = {rules: {'OAI-SearchBot': true}, marker: MARKER, crawlers: CRAWLERS};
	const rules = (body) => byId(check(served(MARKER + '\n' + body), expected)).rules;

	// The general rules shut out part of the site: the allowed crawler is not shut out of the whole site.
	assert.equal(rules('User-agent: *\nDisallow: /wp-admin/\nDisallow: /cart/\n').key, 'present');
	// The general rules shut out the whole site, and the allowed crawler follows them.
	assert.deepEqual(rules('User-agent: *\nDisallow: /\n'), {id: 'rules', state: 'warning', key: 'different', values: ['OAI-SearchBot']});
	// Another source gave it a group of its own, which it follows instead.
	assert.equal(rules('User-agent: *\nDisallow: /\n\nUser-agent: OAI-SearchBot\nDisallow: /private/\n').key, 'present');
	assert.equal(rules('User-agent: *\nAllow: /\n\nUser-agent: OAI-SearchBot\nDisallow: /\n').key, 'different');
	// With no group at all, nothing shuts it out.
	assert.equal(rules('').key, 'present');
});

test('a switched-off crawler needs a group of its own that disallows the whole site', () => {
	const expected = {rules: {'OAI-SearchBot': true, GPTBot: false, ClaudeBot: false}, marker: MARKER, crawlers: CRAWLERS};

	// The group for every crawler does not count: the plugin writes one for each crawler it blocks.
	assert.deepEqual(
		byId(check(served(MARKER + '\nUser-agent: *\nDisallow: /wp-admin/\n\nUser-agent: GPTBot\nDisallow: /\n'), expected)).rules,
		{id: 'rules', state: 'warning', key: 'missing', values: ['ClaudeBot']}
	);
	assert.deepEqual(
		byId(check(served(MARKER + '\nUser-agent: GPTBot\nAllow: /\n\nUser-agent: ClaudeBot\nDisallow: /\n'), expected)).rules,
		{id: 'rules', state: 'warning', key: 'different', values: ['GPTBot']}
	);
	// Without the plugin's part in the file, the rules are reported absent, whatever else it holds.
	assert.deepEqual(
		byId(check(served('User-agent: GPTBot\nDisallow: /\n\nUser-agent: ClaudeBot\nDisallow: /\n'), expected)).rules,
		{id: 'rules', state: 'warning', key: 'absent', values: []}
	);
});

test('no plugin rules are expected, so no row is reported for them', () => {
	const ids = check(served('User-agent: *\nDisallow: /\n'), {rules: {}, crawlers: CRAWLERS, sitemapLine: ''}).map((result) => result.id);

	assert.deepEqual(ids, ['fetch', 'blocked', 'sitemap', 'signals']);
});

test('a whole-site block is reported for each named group, not for crawlers that only fall under *', () => {
	const results = byId(check(served('User-agent: *\nDisallow: /\nUser-agent: Bingbot\nDisallow: /\n'), {rules: {}, crawlers: CRAWLERS}));

	assert.deepEqual(results.blocked.values, ['Bingbot', '*']);
	assert.deepEqual(byId(check(served('User-agent: *\nDisallow: /$\n'), {rules: {}, crawlers: CRAWLERS})).blocked.key, 'open');
});

test('without a sitemap line, the line to add is given only when WordPress sitemaps are on', () => {
	assert.deepEqual(byId(check(served('User-agent: *\nAllow: /\n'), {crawlers: CRAWLERS, sitemapLine: ''})).sitemap, {id: 'sitemap', state: 'info', key: 'none', values: []});
});

test('a failed fetch, another status, or another content type is reported first', () => {
	assert.deepEqual(check({fetched: false}, {}), [{id: 'fetch', state: 'warning', key: 'failed', values: []}]);
	assert.deepEqual(check(served('Not found', {status: 404}), {}), [{id: 'fetch', state: 'warning', key: 'status', values: ['404']}]);

	const html = check(served('User-agent: *\nAllow: /\n', {contentType: 'text/html; charset=UTF-8'}), {crawlers: CRAWLERS});

	assert.deepEqual(html[0], {id: 'fetch', state: 'warning', key: 'type', values: ['text/html']});
	// The file is still read, because crawlers read it whatever its type.
	assert.equal(html.length, 4);
	assert.deepEqual(check(served('', {contentType: ''}), {})[0].values, ['-']);
});

test('a served Content-Signal line is reported, and a missing one only when this card adds one', () => {
	const body = 'User-agent: *\n# Content signals, as defined at https://contentsignals.org/\nContent-Signal: ai-train=no, search=yes, ai-input=yes\nDisallow: /wp-admin/\n';
	const parsed = parse(body);

	// The line sits inside the group without splitting it or becoming a rule.
	assert.deepEqual(parsed.groups, [{agents: ['*'], rules: [{allow: false, path: '/wp-admin/'}]}]);
	assert.deepEqual(byId(check(served(body), {contentSignal: 'ai-train=no, search=yes, ai-input=yes'})).signals, {id: 'signals', state: 'success', key: 'present', values: ['ai-train=no, search=yes, ai-input=yes']});
	assert.deepEqual(byId(check(served('User-agent: *\nAllow: /\n'), {contentSignal: 'ai-train=no, search=yes, ai-input=yes'})).signals, {id: 'signals', state: 'warning', key: 'missing', values: ['Content-Signal: ai-train=no, search=yes, ai-input=yes']});
	assert.deepEqual(byId(check(served('User-agent: *\nAllow: /\n'), {})).signals, {id: 'signals', state: 'info', key: 'none', values: []});
});

test('a group that shuts the site except for its Allow lines is not reported as shutting every page', () => {
	const partly = byId(check(served('User-agent: *\nDisallow: /\nAllow: /blog/\n'), {rules: {}, crawlers: CRAWLERS}));

	assert.deepEqual(partly.blocked, {id: 'blocked', state: 'warning', key: 'partly', values: ['*']});
	assert.equal(allows(rulesNaming(parse('User-agent: *\nDisallow: /\nAllow: /blog/\n'), '*'), '/blog/post'), true);

	// When one group lists an allowed path, the wording that holds for every listed group is used.
	const mixed = byId(check(served('User-agent: Googlebot\nDisallow: /\n\nUser-agent: *\nDisallow: /\nAllow: /blog/\n'), {rules: {}, crawlers: CRAWLERS}));

	assert.deepEqual(mixed.blocked, {id: 'blocked', state: 'warning', key: 'partly', values: ['Googlebot', '*']});
});

test('a Content-Type header sent twice is read the way the browser reads it, not as one joined string', () => {
	const robots = (contentType) => byId(check(served('User-agent: *\nAllow: /\n', {contentType: contentType}), {crawlers: CRAWLERS})).fetch;

	// The header twice, as a real host sent it: the browser joins the two with ", ".
	assert.deepEqual(robots('text/plain, text/plain'), {id: 'fetch', state: 'success', key: 'ok', values: ['https://example.com/robots.txt']});
	// Parameters and case do not matter.
	assert.equal(robots('text/plain; charset=utf-8, Text/Plain').key, 'ok');
	assert.equal(robots('TEXT/PLAIN; charset=UTF-8').key, 'ok');
	// Two different types: the last that parses counts, and it is the one reported.
	assert.equal(robots('text/html; charset=UTF-8, text/plain').key, 'ok');
	assert.deepEqual(robots('text/plain, text/html; charset=UTF-8'), {id: 'fetch', state: 'warning', key: 'type', values: ['text/html']});
	// A value that does not parse, or */*, is skipped.
	assert.equal(robots('text/plain, not a type').key, 'ok');
	assert.equal(robots('text/plain, */*').key, 'ok');
	assert.deepEqual(robots('nonsense'), {id: 'fetch', state: 'warning', key: 'type', values: ['-']});
});

test('mediaTypeOf follows the extract-a-MIME-type steps of the Fetch standard', () => {
	assert.equal(robotsReader.mediaTypeOf('text/plain, text/plain'), 'text/plain');
	assert.equal(robotsReader.mediaTypeOf('text/plain; charset="a, b", text/html'), 'text/html');
	assert.equal(robotsReader.mediaTypeOf('text/html; charset="a, b"'), 'text/html');
	assert.equal(robotsReader.mediaTypeOf('text/ plain'), '');
	assert.equal(robotsReader.mediaTypeOf(''), '');
	assert.equal(robotsReader.mediaTypeOf(undefined), '');
});
