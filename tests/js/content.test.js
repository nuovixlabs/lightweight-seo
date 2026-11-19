'use strict';

// Run with: node --test tests/js/*.test.js
// The editor checks and the Inspect report count images, alternative text, and H1 headings with
// these functions. tests/fixtures/content-facts.json holds cases the PHP twin must count the same way.

const test = require('node:test');
const assert = require('node:assert/strict');
const {contentFacts, pageFacts} = require('../../admin/js/lightweight-seo-formats.js');
const cases = require('../fixtures/content-facts.json');

for (const fixture of cases) {
	test('content facts: ' + fixture.name, () => {
		assert.deepEqual(contentFacts(fixture.html), fixture.expected);
	});
}

test('a page is measured as served: text in its main element, headings and images across the page', () => {
	const html = [
		'<!doctype html><html><head><title>Ignored</title><style>h1{}</style></head><body>',
		'<header><img src="logo.png" alt="Oak &amp; Co"><nav>Home About</nav></header>',
		'<main id="content"><h1>Chairs</h1><p>Made&nbsp;by hand &#8211; in Leeds.</p><img src="a.jpg" alt=""><script>var hidden = "text";</script></main>',
		'<footer><h1>Footer heading</h1></footer></body></html>',
	].join('');

	assert.deepEqual(pageFacts(html), {images: 2, imagesWithoutAlt: 1, h1: 2, region: 'main', textLength: 'Chairs Made by hand – in Leeds.'.length});
});

test('without a main element the first article is measured, then the body', () => {
	assert.equal(pageFacts('<body><nav>Menu</nav><article><p>Story</p></article><article><p>Other</p></article></body>').region, 'article');
	assert.equal(pageFacts('<body><nav>Menu</nav><article><p>Story</p></article></body>').textLength, 'Story'.length);
	assert.deepEqual(pageFacts('<body><p>Only text</p></body>'), {images: 0, imagesWithoutAlt: 0, h1: 0, region: 'body', textLength: 'Only text'.length});
});

test('a page whose text sits outside its main element reports no characters there', () => {
	const facts = pageFacts('<body><main></main><div class="hero">Text the theme prints elsewhere</div></body>');

	assert.equal(facts.region, 'main');
	assert.equal(facts.textLength, 0);
});
