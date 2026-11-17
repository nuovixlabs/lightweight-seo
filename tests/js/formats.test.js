'use strict';

// Run with: node --test tests/js/*.test.js
// The stored formats are defined by Lightweight_SEO_Settings. These tests pin the
// script's reading and writing of them, so the row editors never change what is saved.

const test = require('node:test');
const assert = require('node:assert/strict');
const formats = require('../../admin/js/lightweight-seo-formats.js');

const {read, write, redirects, hreflang, hours, ids, resolveTokens, truncate} = formats;

test('redirect rules round-trip in the shape the server stores', () => {
	const stored = '/old-page /new-page 301\n/moved https://example.org/landing 302\n/gone /here 308';
	const parsed = read(stored, redirects);

	assert.deepEqual(parsed.leftover, []);
	assert.deepEqual(parsed.rows, [
		{source: '/old-page', target: '/new-page', status: 301},
		{source: '/moved', target: 'https://example.org/landing', status: 302},
		{source: '/gone', target: '/here', status: 308},
	]);
	assert.equal(write(parsed.rows, parsed.leftover, redirects), stored);
});

test('a redirect without a status, or with an unknown one, is a 301', () => {
	assert.deepEqual(redirects.parse('/a /b'), {source: '/a', target: '/b', status: 301});
	assert.deepEqual(redirects.parse('/a /b 999'), {source: '/a', target: '/b', status: 301});
	assert.deepEqual(redirects.parse('/a /b permanent'), {source: '/a', target: '/b', status: 301});
	assert.equal(redirects.format({source: '/a', target: '/b', status: '307'}), '/a /b 307');
	assert.equal(redirects.format({source: '/a', target: '/b', status: 'nonsense'}), '/a /b 301');
});

test('an incomplete redirect row is shown but not written', () => {
	assert.deepEqual(redirects.parse('/only-a-source'), {source: '/only-a-source', target: '', status: 301});
	assert.equal(redirects.format({source: '/only-a-source', target: '', status: 301}), '');
	assert.equal(redirects.format({source: '', target: '/b', status: 301}), '');
	assert.equal(write([{source: '/a', target: '/b', status: 301}, {source: '', target: '', status: 301}], [], redirects), '/a /b 301');
});

test('a space typed into a redirect cell cannot split the stored line', () => {
	assert.equal(redirects.format({source: '/old page', target: ' /new  page ', status: 301}), '/old%20page /new%20page 301');
});

test('blank lines are dropped and unreadable lines are kept verbatim', () => {
	const parsed = read('\n/a /b 301\n\n/too many words in this line\r\n/c /d 302\n', redirects);

	assert.equal(parsed.rows.length, 2);
	assert.deepEqual(parsed.leftover, ['/too many words in this line']);
	assert.equal(write(parsed.rows, parsed.leftover, redirects), '/a /b 301\n/c /d 302\n/too many words in this line');
});

test('redirect hints flag incomplete, self-pointing, and overridden rows', () => {
	assert.deepEqual(
		redirects.check([
			{source: '/a', target: '/b', status: 301},
			{source: '/c', target: '', status: 301},
			{source: '/d', target: '/d', status: 301},
			{source: '/a', target: '/z', status: 302},
			{source: '', target: '', status: 301},
		]),
		['duplicate', 'incomplete', 'same', '', '']
	);
});

test('a gone rule needs no destination and round-trips as "source 410"', () => {
	assert.deepEqual(redirects.parse('/retired 410'), {source: '/retired', target: '', status: 410});
	assert.deepEqual(redirects.parse('/retired /ignored 410'), {source: '/retired', target: '', status: 410});
	assert.equal(redirects.format({source: '/retired', target: '', status: '410'}), '/retired 410');
	// What was typed as a destination before the type changed is left out of the stored line.
	assert.equal(redirects.format({source: '/retired', target: '/typed-earlier', status: 410}), '/retired 410');
	assert.equal(redirects.format({source: '', target: '', status: 410}), '');
	assert.deepEqual(redirects.inactive({source: '/retired', target: '', status: '410'}), ['target']);
	assert.deepEqual(redirects.inactive({source: '/a', target: '/b', status: 301}), []);

	const stored = '/old/* /new/* 301\n/retired 410\n/shop/* 410';
	const parsed = read(stored, redirects);

	assert.deepEqual(parsed.leftover, []);
	assert.equal(write(parsed.rows, parsed.leftover, redirects), stored);
	assert.deepEqual(
		redirects.check([
			{source: '/retired', target: '', status: 410},
			{source: '', target: '', status: 410},
			{source: '/retired', target: '/typed-earlier', status: 410},
		]),
		['duplicate', '', '']
	);
});

test('hreflang mappings round-trip, including a retained legacy line', () => {
	const stored = 'post:123 en-GB https://example.co.uk/page/\nterm:7 de-DE https://example.de/thema/\nuser:2 x-default https://example.com/author/\nfr-FR https://example.fr';
	const parsed = read(stored, hreflang);

	assert.deepEqual(parsed.leftover, []);
	assert.deepEqual(parsed.rows, [
		{type: 'post', id: 123, language: 'en-GB', url: 'https://example.co.uk/page/'},
		{type: 'term', id: 7, language: 'de-DE', url: 'https://example.de/thema/'},
		{type: 'user', id: 2, language: 'x-default', url: 'https://example.com/author/'},
		{type: 'mirror', id: 0, language: 'fr-FR', url: 'https://example.fr'},
	]);
	assert.equal(write(parsed.rows, parsed.leftover, hreflang), stored);
});

test('an hreflang row without a page is never written as a legacy line', () => {
	// "en-GB https://…" on its own means "every page" to the server, so a half-filled row must not produce it.
	assert.equal(hreflang.format({type: 'post', id: 0, language: 'en-GB', url: 'https://example.co.uk/'}), '');
	assert.equal(hreflang.format({type: '', id: 12, language: 'en-GB', url: 'https://example.co.uk/'}), '');
	assert.equal(hreflang.format({type: 'post', id: 12, language: '', url: 'https://example.co.uk/'}), '');
	assert.equal(hreflang.format({type: 'post', id: 12, language: 'en-GB', url: ''}), '');
	assert.equal(hreflang.format({type: 'post', id: '12', language: 'en-GB', url: 'https://example.co.uk/'}), 'post:12 en-GB https://example.co.uk/');
});

test('hreflang lines in no known shape are kept as text', () => {
	const parsed = read('page:12 en https://example.com/\npost:12 en https://example.com/ extra words', hreflang);

	assert.deepEqual(parsed.rows, []);
	assert.equal(parsed.leftover.length, 2);
});

test('hreflang hints flag incomplete rows and a repeated page and language', () => {
	assert.deepEqual(
		hreflang.check([
			{type: 'post', id: 12, language: 'en-GB', url: 'https://example.co.uk/'},
			{type: 'post', id: 12, language: 'EN-gb', url: 'https://example.co.uk/other/'},
			{type: 'post', id: 0, language: 'fr', url: 'https://example.fr/'},
			{type: 'post', id: 0, language: '', url: ''},
			{type: 'mirror', id: 0, language: 'de', url: 'https://example.de'},
		]),
		['', 'duplicate', 'incomplete', '', '']
	);
});

test('opening hours expand day ranges and compress them again', () => {
	const stored = 'Mo-Fr 09:00-17:00\nSa 10:00-14:00';
	const parsed = hours.parse(stored);

	assert.deepEqual(parsed.leftover, []);
	assert.deepEqual(parsed.days.Mo, [['09:00', '17:00']]);
	assert.deepEqual(parsed.days.Fr, [['09:00', '17:00']]);
	assert.deepEqual(parsed.days.Sa, [['10:00', '14:00']]);
	assert.deepEqual(parsed.days.Su, []);
	assert.equal(hours.format(parsed.days, parsed.leftover), stored);
});

test('split shifts keep one line per time range', () => {
	const stored = 'Tu-Sa 12:00-14:30\nTu-Sa 18:00-22:00';
	const parsed = hours.parse(stored);

	assert.deepEqual(parsed.days.We, [['12:00', '14:30'], ['18:00', '22:00']]);
	assert.deepEqual(parsed.days.Mo, []);
	assert.equal(hours.format(parsed.days), stored);
});

test('a day range that runs over the weekend wraps around the week', () => {
	const parsed = hours.parse('Fr-Mo 10:00-16:00');

	assert.deepEqual(Object.keys(parsed.days).filter((day) => parsed.days[day].length), ['Mo', 'Fr', 'Sa', 'Su']);
	// Written back in week order: Monday on its own, then Friday to Sunday.
	assert.equal(hours.format(parsed.days), 'Mo 10:00-16:00\nFr-Su 10:00-16:00');
});

test('changing one day splits it from its neighbours', () => {
	const parsed = hours.parse('Mo-Fr 09:00-17:00');

	parsed.days.We = [['09:00', '13:00']];

	assert.equal(hours.format(parsed.days), 'Mo-Tu 09:00-17:00\nWe 09:00-13:00\nTh-Fr 09:00-17:00');
});

test('half-entered hours are not written and unreadable lines are kept', () => {
	const parsed = hours.parse('Mo 09:00-17:00\nweekdays nine to five\nTu 9:00-17:00');

	assert.deepEqual(parsed.leftover, ['weekdays nine to five', 'Tu 9:00-17:00']);

	parsed.days.Tu = [['09:00', '']];

	assert.equal(hours.format(parsed.days, parsed.leftover), 'Mo 09:00-17:00\nweekdays nine to five\nTu 9:00-17:00');
});

test('every line the validator accepts is read as hours', () => {
	// Mirrors the pattern in Lightweight_SEO_Settings::normalize_local_business_input().
	const accepted = ['Mo 00:00-23:59', 'Su-Sa 08:30-20:15', 'Th-Th 19:00-02:00'];

	accepted.forEach((line) => {
		assert.deepEqual(hours.parse(line).leftover, [], line);
	});
});

test('page IDs are read from any separator and written comma-separated', () => {
	assert.deepEqual(ids.parse('12, 40 7;7,0,abc 12'), [12, 40, 7]);
	assert.deepEqual(ids.parse(''), []);
	assert.equal(ids.format([12, 40, 7]), '12,40,7');
	assert.equal(ids.format(['5', 5, 0, 9]), '5,9');
});

test('variables are filled, and an empty optional one leaves with one separator', () => {
	const tokens = {'%title%': 'Hello world', '%sitename%': 'My Site', '%tagline%': '', '%sep%': '–', '%page%': '', '%category%': 'News', '%year%': '2026'};

	assert.equal(resolveTokens('%title% %sep% %sitename%', tokens), 'Hello world – My Site');
	assert.equal(resolveTokens('%title% %sep% %page% %sep% %sitename%', tokens), 'Hello world – My Site');
	assert.equal(resolveTokens('%page% | %title%', tokens), 'Hello world');
	assert.equal(resolveTokens('%title% - %category% (%year%)', tokens), 'Hello world - News (2026)');
	// A variable with no entry at all is optional only when it is one of the four.
	assert.equal(resolveTokens('%title% %sep% %author%', tokens), 'Hello world');
	assert.equal(resolveTokens('%title% %sep% %unknown%', tokens), 'Hello world – %unknown%');
	// An empty tagline leaves with its separator, as on the server.
	assert.equal(resolveTokens('%sitename% %sep% %tagline%', tokens), 'My Site');
	assert.equal(resolveTokens('%sitename% %sep% %tagline%', Object.assign({}, tokens, {'%tagline%': 'Photographs'})), 'My Site – Photographs');
	// A value of spaces only counts as empty.
	assert.equal(resolveTokens('%sitename% %sep% %tagline%', Object.assign({}, tokens, {'%tagline%': '   '})), 'My Site');
	assert.equal(resolveTokens(undefined, tokens), '');
	assert.equal(resolveTokens('%title%', undefined), '%title%');
	assert.equal(resolveTokens('%page% %sep% %title%', Object.assign({}, tokens, {'%page%': 'Page 2'})), 'Page 2 – Hello world');
});

test('preview text is shortened at the end of a word', () => {
	assert.equal(truncate('Short title', 60), 'Short title');
	assert.equal(truncate('  Exactly ten  ', 11), 'Exactly ten');
	assert.equal(truncate('The quick brown fox jumps over the lazy dog', 18), 'The quick brown…');
	// A cut that falls exactly between two words keeps the whole last word.
	assert.equal(truncate('The quick brown fox', 15), 'The quick brown…');
	// Punctuation and separators are not left hanging before the ellipsis.
	assert.equal(truncate('Hello world – My Site and more', 14), 'Hello world…');
	assert.equal(truncate('One, two, three', 9), 'One, two…');
	// A single long word cannot be cut at a space.
	assert.equal(truncate('Supercalifragilistic', 5), 'Super…');
	// Characters outside the basic plane count once.
	assert.equal(truncate('😀😀😀😀😀', 3), '😀😀😀…');
	assert.equal(truncate('Anything', 0), 'Anything');
	assert.equal(truncate(null, 10), '');
});
