'use strict';

// Run with: node --test tests/js/*.test.js
// The AI readiness report fetches each address in the administrator's browser and reads the
// answers with readiness(). Files this version does not publish are "found" or a neutral
// "not offered", never a warning.

const test = require('node:test');
const assert = require('node:assert/strict');
const {readiness, settle} = require('../../admin/js/lightweight-seo-robots.js');

const ROOT = 'https://example.com';

function answer(body, extra) {
	return Object.assign({fetched: true, status: 200, contentType: 'text/plain; charset=utf-8', body: body || '', url: ''}, extra || {});
}

function notFound(url) {
	return answer('<!doctype html><title>Not found</title>', {status: 404, contentType: 'text/html; charset=UTF-8', url: url});
}

// A WordPress site with AI Discovery on and nothing else installed.
function plainSite(overrides) {
	return Object.assign({
		robots: answer('User-agent: *\nDisallow: /wp-admin/\n\nSitemap: ' + ROOT + '/wp-sitemap.xml\n', {url: ROOT + '/robots.txt'}),
		sitemapXml: answer('<?xml version="1.0"?><sitemapindex/>', {contentType: 'application/xml', url: ROOT + '/wp-sitemap.xml', xmlRoot: 'sitemapindex'}),
		home: answer('<html></html>', {contentType: 'text/html', link: '<' + ROOT + '/wp-json/>; rel="https://api.w.org/"'}),
		markdown: answer('<html></html>', {contentType: 'text/html; charset=UTF-8'}),
		llms: notFound(ROOT + '/llms.txt'),
		apiCatalog: [notFound()],
		skills: [notFound(), notFound()],
		mcp: [notFound(), notFound(), notFound()],
		ard: [notFound(), notFound()],
	}, overrides || {});
}

function byId(results) {
	return Object.fromEntries(results.map((result) => [result.id, result]));
}

test('a plain WordPress site: valid sitemap, and every file it does not publish is a neutral "not offered"', () => {
	const results = byId(readiness(plainSite(), {crawlers: ['Googlebot', 'Bingbot', '*'], sitemapLine: 'Sitemap: ' + ROOT + '/wp-sitemap.xml', llmsEnabled: false, signalOff: true}));

	assert.deepEqual(results.sitemapXml, {id: 'sitemapXml', state: 'success', key: 'valid', values: [ROOT + '/wp-sitemap.xml']});
	// WordPress's own REST link is not one of the relations agents look for.
	assert.deepEqual(results.linkHeaders, {id: 'linkHeaders', state: 'info', key: 'none', values: []});
	assert.deepEqual(results.markdown, {id: 'markdown', state: 'info', key: 'none', values: []});
	assert.deepEqual(results.llms, {id: 'llms', state: 'off', key: 'off', values: []});
	assert.deepEqual(results.signals, {id: 'signals', state: 'off', key: 'off', values: []});

	for (const id of ['apiCatalog', 'skills', 'mcp', 'ard']) {
		assert.deepEqual(results[id], {id: id, state: 'info', key: 'none', values: []});
	}

	assert.equal(Object.values(results).filter((result) => result.state === 'warning').length, 0);
});

test('files another plugin or a CDN serves are reported as found, at the address that answered', () => {
	const results = byId(readiness(plainSite({
		home: answer('', {link: '<' + ROOT + '/.well-known/api-catalog>; rel="api-catalog", <' + ROOT + '/openapi.json>; rel="service-desc describedby"'}),
		markdown: answer('# Home\n', {contentType: 'text/markdown; charset=utf-8'}),
		apiCatalog: [answer('{"linkset":[{"anchor":"' + ROOT + '/api"}]}', {contentType: 'application/linkset+json', url: ROOT + '/.well-known/api-catalog'})],
		skills: [notFound(), answer('{"skills":[]}', {contentType: 'application/json', url: ROOT + '/.well-known/skills/index.json'})],
		mcp: [notFound(), notFound(), answer('{"serverInfo":{"name":"Example"}}', {contentType: 'application/json', url: ROOT + '/.well-known/mcp.json'})],
		ard: [notFound(), answer('{"specVersion":"1.0","entries":[{"id":"x"}]}', {contentType: 'application/json', url: ROOT + '/.well-known/ai-catalog.json'})],
	}), {}));

	assert.deepEqual(results.linkHeaders.values, ['api-catalog', 'service-desc', 'describedby']);
	assert.equal(results.markdown.key, 'found');
	assert.deepEqual(results.apiCatalog.values, [ROOT + '/.well-known/api-catalog']);
	assert.deepEqual(results.skills.values, [ROOT + '/.well-known/skills/index.json']);
	assert.deepEqual(results.mcp.values, [ROOT + '/.well-known/mcp.json']);
	assert.deepEqual(results.ard.values, [ROOT + '/.well-known/ai-catalog.json']);
});

test('an answer that is not what the reference asks for does not count as found', () => {
	const results = byId(readiness(plainSite({
		// A theme that answers every address with a page.
		apiCatalog: [answer('<html></html>', {contentType: 'text/html', url: ROOT + '/.well-known/api-catalog'})],
		// JSON, but served with the wrong type, or without the fields that make it a catalog, card, or manifest.
		skills: [answer('{"name":"x"}', {contentType: 'application/json'})],
		mcp: [answer('{"serverInfo":{}}', {contentType: 'application/json'}), answer('[]', {contentType: 'application/json'}), notFound()],
		ard: [answer('{"specVersion":"1.0","entries":[]}', {contentType: 'application/json'}), notFound()],
	}), {}));

	for (const id of ['apiCatalog', 'skills', 'mcp', 'ard']) {
		assert.equal(results[id].key, 'none', id);
		assert.equal(results[id].state, 'info', id);
	}

	assert.equal(byId(readiness(plainSite({apiCatalog: [answer('{"linkset":[{}]}', {contentType: 'application/json'})]}), {})).apiCatalog.key, 'none');
});

test('llms.txt is found only as a text file that opens with a heading, and missing only when the switch is on', () => {
	const served = answer('\n# Example\n\n> Summary.\n', {url: ROOT + '/llms.txt'});

	assert.deepEqual(byId(readiness(plainSite({llms: served}), {llmsEnabled: true})).llms, {id: 'llms', state: 'success', key: 'found', values: [ROOT + '/llms.txt']});
	assert.equal(byId(readiness(plainSite({llms: served}), {llmsEnabled: false})).llms.key, 'found');
	assert.deepEqual(byId(readiness(plainSite(), {llmsEnabled: true})).llms, {id: 'llms', state: 'warning', key: 'missing', values: []});
	assert.equal(byId(readiness(plainSite({llms: answer('<!doctype html><h1>Home</h1>', {contentType: 'text/html'})}), {llmsEnabled: true})).llms.key, 'missing');
});

test('without a valid /sitemap.xml, a Sitemap line in robots.txt still leads to one', () => {
	const broken = answer('<html>', {contentType: 'text/html', xmlRoot: null});

	assert.deepEqual(byId(readiness(plainSite({sitemapXml: broken}), {})).sitemapXml, {id: 'sitemapXml', state: 'success', key: 'listed', values: [ROOT + '/wp-sitemap.xml']});
	assert.deepEqual(
		byId(readiness(plainSite({sitemapXml: broken, robots: answer('User-agent: *\nAllow: /\n')}), {})).sitemapXml,
		{id: 'sitemapXml', state: 'warning', key: 'missing', values: []}
	);
});

test('a robots.txt that cannot be fetched still lets every other address be checked', () => {
	const ids = readiness(plainSite({robots: {fetched: false}}), {}).map((result) => result.id);

	assert.deepEqual(ids, ['fetch', 'sitemapXml', 'linkHeaders', 'markdown', 'llms', 'apiCatalog', 'skills', 'mcp', 'ard']);
});

test('a request that failed is reported as not checked, never as a fact about the site', () => {
	const failed = {fetched: false};
	const results = byId(readiness({
		robots: failed,
		sitemapXml: failed,
		home: failed,
		markdown: failed,
		llms: failed,
		apiCatalog: [failed],
		skills: [failed, failed],
		mcp: [failed, failed, failed],
		ard: [failed, failed],
	}, {llmsEnabled: true, sitemapLine: 'Sitemap: ' + ROOT + '/wp-sitemap.xml'}));

	assert.deepEqual(results.fetch, {id: 'fetch', state: 'warning', key: 'failed', values: []});

	for (const id of ['sitemapXml', 'linkHeaders', 'markdown', 'llms', 'apiCatalog', 'skills', 'mcp', 'ard']) {
		assert.deepEqual(results[id], {id: id, state: 'info', key: 'unchecked', values: []}, id);
	}
});

test('a row is checked when any of its requests went through', () => {
	const failed = {fetched: false};
	const results = byId(readiness(plainSite({
		// The sitemap request failed, but robots.txt names a sitemap to fall back on.
		sitemapXml: failed,
		markdown: failed,
		llms: answer('# Example\n', {url: ROOT + '/llms.txt'}),
		// One address failed and another answered 404: the file is not offered.
		skills: [failed, notFound()],
		// One address failed and another serves a card: found.
		mcp: [failed, answer('{"name":"Example"}', {contentType: 'application/json', url: ROOT + '/.well-known/mcp.json'}), failed],
		ard: [failed, failed],
	}), {llmsEnabled: true}));

	assert.deepEqual(results.sitemapXml, {id: 'sitemapXml', state: 'success', key: 'listed', values: [ROOT + '/wp-sitemap.xml']});
	assert.equal(results.markdown.key, 'unchecked');
	assert.equal(results.llms.key, 'found');
	assert.deepEqual(results.skills, {id: 'skills', state: 'info', key: 'none', values: []});
	assert.deepEqual(results.mcp.values, [ROOT + '/.well-known/mcp.json']);
	assert.equal(results.ard.key, 'unchecked');
	assert.equal(results.linkHeaders.key, 'none');
	assert.equal(results.apiCatalog.key, 'none');
});

test('"Who writes robots.txt" is settled by whether the served file holds the card\'s rules', () => {
	const expected = {rules: {'OAI-SearchBot': true, GPTBot: false}, marker: '# Lightweight SEO AI Discovery (experimental)', crawlers: ['Googlebot', 'Bingbot', '*']};
	// What the real site's host served instead of the file WordPress writes.
	const host = answer('User-agent: Googlebot\nDisallow: /\n\nUser-agent: *\nAllow: /\n', {url: ROOT + '/robots.txt'});
	const wordpress = answer('User-agent: *\nDisallow: /wp-admin/\n\n# Lightweight SEO AI Discovery (experimental)\n# Not blocked here, so they follow the rules for every crawler (User-agent: *): OAI-SearchBot\n\nUser-agent: GPTBot\nDisallow: /\n', {url: ROOT + '/robots.txt'});

	assert.deepEqual(settle(byId(readiness(plainSite({robots: host}), expected)).rules), {state: 'warning', key: 'absent'});
	assert.deepEqual(settle(byId(readiness(plainSite({robots: wordpress}), expected)).rules), {state: 'success', key: 'present'});
	// The plugin's part is served even when one of its rules differs, so the file is WordPress's with it.
	assert.deepEqual(settle({id: 'rules', state: 'warning', key: 'missing', values: ['GPTBot']}), {state: 'success', key: 'present'});
	assert.deepEqual(settle({id: 'rules', state: 'warning', key: 'different', values: ['GPTBot']}), {state: 'success', key: 'present'});

	// Without a rules result, as when robots.txt could not be fetched, the server's statement stands.
	assert.equal(byId(readiness(plainSite({robots: {fetched: false}}), expected)).rules, undefined);
	assert.equal(settle(undefined), null);
	assert.equal(settle({id: 'blocked', state: 'warning', key: 'blocked', values: ['*']}), null);
});
