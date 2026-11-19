# Lightweight SEO 1.3 Release Notes

These notes describe the 1.3 line by feature, as it is built. [Changelog](../CHANGELOG.md) lists each change. The 1.2 line is described in [1.2 Release Notes](release-notes-1.2.0.md).

## AI Discovery

### Markdown on request (experimental)

A new switch on the AI Discovery card, **Answer a request for Markdown with a Markdown version of the page (experimental)**, is off by default. While it is on, a client that asks for `text/markdown` in its `Accept` header gets a Markdown version of the same address instead of the theme's HTML. Browsers, search engines, and every request that does not ask for Markdown get exactly what they got before. There is no new address, and nothing is stored: the document is built for that request and discarded.

It only helps a client that asks for Markdown. No setting guarantees that an AI agent asks for it, reads it, or cites the page.

#### Which requests get Markdown

All of these must hold:

- The request is `GET` or `HEAD`.
- Its `Accept` header names `text/markdown` with a quality above 0, and no `text/html` or `application/xhtml+xml` entry has a higher one. `*/*` alone, or no header, never selects Markdown.

  | `Accept` | Answer |
  |---|---|
  | `text/markdown` | Markdown |
  | `text/markdown, text/html;q=0.9, */*;q=0.8` | Markdown |
  | `text/html, text/markdown;q=0.5` | HTML |
  | `text/markdown;q=0` | HTML |
  | `text/html,application/xhtml+xml,*/*;q=0.8` (a browser) | HTML |

- The page is a published post, page, or other public post type (not an attachment, not password protected, not a preview), a static front page, the latest-posts homepage, or the posts page. Archives, search results, 404 pages, feeds, embeds, the REST API, `robots.txt`, the favicon, sitemaps, `/llms.txt`, trackbacks, and the customizer are never answered in Markdown.
- The server has PHP's DOM extension, which the converter needs. Without it, HTML is sent as before.
- No plugin refuses it through the `lightweight_seo_markdown_supported` filter.

The answer is decided late, on `template_redirect` at priority 100000: after WordPress's canonical redirect, the plugin's own redirects, and any redirect or access check another plugin makes on `template_redirect` before that priority. A visitor that such a check redirects or refuses gets the same answer when asking for Markdown.

Some limits happen where the answer cannot see them, and the Markdown answer does not respect them:

- a plugin that hides a page by swapping its template through `template_include`, as many maintenance, coming-soon, and membership plugins do;
- a check made on a later hook, or later on `template_redirect`.

On such a site, a browser gets the maintenance page or the sign-in prompt, while a request for Markdown gets the page's real content. Before turning the switch on with such a plugin active, request a page while signed out, for example with `curl -H "Accept: text/markdown" https://example.com/a-post/`. If the content comes back, return `false` from the `lightweight_seo_markdown_supported` filter for the requests the plugin limits (see [For developers](#for-developers)). Limits applied through the `the_content` filter need nothing: the Markdown is rendered through that filter too.

For every page that can be answered in Markdown, the HTML answer gains `Vary: Accept`, added to any `Vary` header already there, so a cache that honors `Vary` keeps the two apart.

#### The document

It follows the layout of [Cloudflare's Markdown for Agents](https://developers.cloudflare.com/fundamentals/reference/markdown-for-agents/), so an agent sees the same structure from either source:

````markdown
---
title: Oak furniture – Example
description: "How we choose oak: grain, drying, and finish."
image: https://example.com/wp-content/uploads/oak.jpg
---

# Oak furniture

The content of the page, converted to Markdown.

```json
{"@context":"https://schema.org","@graph":[…]}
```
````

1. **Front matter** with `title`, `description`, and `image`, each only when it has a value. They come from the same sources as the HTML head, so the two never disagree: the document title WordPress prints, and the meta description and social image Lightweight SEO prints. While another SEO plugin is active (safe mode), the title is what WordPress prints through that plugin, and the description and image are left out, because Lightweight SEO prints neither.
2. **The content.**
   - A post, page, or static front page: its title as a heading, then its content after the `the_content` filters, so blocks and shortcodes are rendered. A post split with `<!--nextpage-->` gives the page requested.
   - The latest-posts homepage and the posts page: the site name or the posts page's title as a heading, then the posts of that page of the list, each as a linked title followed by its excerpt.
3. **Structured data**: the schema graph the head prints for the page, in one fenced `json` block at the end. It is left out when the plugin prints none (schema output off, or safe mode).

Only the content is converted. What a theme or another plugin prints outside it, such as a price, a form, a sidebar, comments, or navigation, is not in the Markdown. Inside the content, scripts, styles, forms, and buttons are dropped.

The converter writes CommonMark with GitHub-flavored tables and strikethrough. It handles headings, paragraphs, line breaks, emphasis, inline code and code blocks (with their language), quotes, nested and numbered lists, links (made absolute; only `http`, `https`, `mailto`, and `tel` stay links), images with their alternative text, figures and captions, tables, description lists, details, and embedded video, audio, and frames as a link to their source. Text is escaped so it cannot turn into Markdown structure by accident.

#### Headers

| Header | Value |
|---|---|
| `Content-Type` | `text/markdown; charset=UTF-8` |
| `Vary` | `Accept`, added to any `Vary` already there |
| `X-Content-Type-Options` | `nosniff` |
| `Link` | `<canonical>; rel="canonical"`, when the plugin prints a canonical address for the page |
| `X-Robots-Tag` | The robots directives the HTML page prints, whenever they restrict anything, so a page hidden from search keeps `noindex` in its Markdown form |
| `Content-Signal` | The content signals, when the content signals switch is on |
| `Cache-Control` | `private, no-store` |
| `X-LiteSpeed-Cache-Control` | `no-cache` |

`DONOTCACHEPAGE` is defined, there is no `ETag` or `Last-Modified`, and a `HEAD` request gets the headers without a body. If output has already started when the answer is due, so that no header can be sent, the HTML page is sent instead.

#### Page caches and CDNs

Many WordPress page caches and some CDNs store a page by its address alone. If one of them stored the Markdown, it could hand it to a browser, so the Markdown answer tells every cache not to store it, in the ways the common ones understand.

The reverse can still happen: a cache in front of WordPress may answer a request for Markdown with the HTML it stored, without asking WordPress. Then the switch has no effect for those pages. A cache that honors `Vary: Accept`, or one that can be told to pass requests with `Accept: text/markdown` through, avoids it; see the cache's own settings.

#### For developers

Two filters, documented in [Developer API](developer-api.md#module-hooks):

- `lightweight_seo_markdown_supported`: return `false` to send the HTML page instead, as a membership or paywall plugin may for content a visitor has not unlocked, or a maintenance plugin that swaps the template while maintenance is on. It receives the post shown, the posts page, or `null` for the latest-posts homepage.
- `lightweight_seo_markdown_document`: filters the finished document, with the same post.

A membership plugin that limits content through the `the_content` filter limits the Markdown the same way, because the content is rendered through that filter.

#### What it costs

With the switch off, nothing is loaded and no hook is added. With it on, an ordinary page view runs one late check of the `Accept` header. The converter and the document builder, two new files, are loaded only when a Markdown answer is built.

### The readiness report

The "Markdown on request" row of the AI readiness report, which said "Not offered" in 1.2, now follows the switch. The administrator's browser still requests the homepage with `Accept: text/markdown`, signed out, and reads the `Content-Type` of the answer.

| Switch | The homepage answers | Row |
|---|---|---|
| On | `text/markdown` | Passed |
| On | anything else | Needs attention: it names the type that came back (and the status, when it is not 200), and says what may cause it |
| Off | `text/markdown` | Passed: something else, such as a CDN, serves Markdown |
| Off | anything else | Off by choice |
| Either | the request failed | Not checked |

The usual reason for HTML while the switch is on is a page cache or CDN in front of WordPress that answered with the HTML it stored, without asking WordPress; the row says so as a possibility, because the server cannot see a cache from inside. A plugin that refuses Markdown through `lightweight_seo_markdown_supported`, or a private or password-protected front page, also leaves the homepage in HTML.

With the switch on and no PHP DOM extension on the server, the row says that Markdown cannot be built there. Only the server knows that, so it is decided without the browser.

## Upgrading from 1.2

Nothing changes until the new switch is turned on. One setting is new in the existing settings option: `enable_markdown_on_request`, off by default. There is no migration.
