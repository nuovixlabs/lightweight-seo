'use strict';

// Run with: node --test tests/js/*.test.js
// The settings screen fetches /robots.txt in the administrator's browser and reads it with
// these functions. PHP decides what is expected; these tests pin how a served file is read.

const test = require('node:test');
const assert = require('node:assert/strict');
const robots = require('../../admin/js/lightweight-seo-robots.js');

const {parse, allows, rulesNaming, blocksWholeSite, check} = robots;

const CRAWLERS = ['Googlebot', 'Bingbot', '*'];

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
		'# Lightweight SEO AI Discovery (experimental)',
		'User-agent: OAI-SearchBot',
		'Allow: /',
		'',
		'User-agent: GPTBot',
		'Disallow: /',
	].join('\n');
	const results = byId(check(served(body), {
		rules: {'OAI-SearchBot': true, GPTBot: false},
		crawlers: CRAWLERS,
		sitemapLine: 'Sitemap: https://example.com/wp-sitemap.xml',
	}));

	assert.deepEqual(results.rules, {id: 'rules', state: 'success', key: 'present', values: ['2']});
	assert.deepEqual(results.blocked, {id: 'blocked', state: 'success', key: 'open', values: CRAWLERS});
	assert.deepEqual(results.sitemap, {id: 'sitemap', state: 'success', key: 'present', values: ['https://example.com/wp-sitemap.xml']});
});

test('some plugin rules missing, or served with the opposite answer, are named', () => {
	const expected = {rules: {'OAI-SearchBot': true, GPTBot: false, ClaudeBot: false}, crawlers: CRAWLERS, sitemapLine: ''};

	assert.deepEqual(
		byId(check(served('User-agent: OAI-SearchBot\nAllow: /\n'), expected)).rules,
		{id: 'rules', state: 'warning', key: 'missing', values: ['GPTBot', 'ClaudeBot']}
	);
	assert.deepEqual(
		byId(check(served('User-agent: OAI-SearchBot\nAllow: /\nUser-agent: GPTBot\nAllow: /\nUser-agent: ClaudeBot\nDisallow: /\n'), expected)).rules,
		{id: 'rules', state: 'warning', key: 'different', values: ['GPTBot']}
	);
});

test('no plugin rules are expected, so no row is reported for them', () => {
	const ids = check(served('User-agent: *\nDisallow: /\n'), {rules: {}, crawlers: CRAWLERS, sitemapLine: ''}).map((result) => result.id);

	assert.deepEqual(ids, ['fetch', 'blocked', 'sitemap']);
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
	assert.equal(html.length, 3);
	assert.deepEqual(check(served('', {contentType: ''}), {})[0].values, ['-']);
});
