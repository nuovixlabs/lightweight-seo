# Lightweight SEO 1.2 Release Notes

These notes describe the 1.2 line by feature, as it is built. [Changelog](../CHANGELOG.md) lists each change. The 1.1 line is described in [1.1 Release Notes](release-notes-1.1.0.md).

## Highlights

1.2 takes a position on AI discoverability alongside conventional SEO. The plugin checks whether AI crawlers and agents can reach and read the site, and publishes agent-discovery output only for things the site really offers. Each check states a fact; there is no readiness score or level, and nothing here promises crawling, citation, training, inclusion, or ranking.

## AI Discovery

### More AI crawlers

AI Discovery writes `robots.txt` rules for eleven more crawlers. Each was checked on its vendor's own documentation page on 2026-11-19.

| Crawler | Vendor | What the vendor uses it for | Follows the switch |
|---|---|---|---|
| `Applebot-Extended` | Apple | Training Apple's foundation models | Model training (off by default) |
| `Meta-ExternalAgent` | Meta | Training AI models, and improving products by indexing content | Model training (off by default) |
| `Amazonbot` | Amazon | Improving Amazon products and services, and possibly training Amazon AI models | Model training (off by default) |
| `MistralAI-Training` | Mistral AI | Training Mistral's models | Model training (off by default) |
| `Meta-WebIndexer` | Meta | Meta AI search | AI search and user-directed (on by default) |
| `Amzn-SearchBot` | Amazon | Search and answers, such as Alexa | AI search and user-directed (on by default) |
| `DuckAssistBot` | DuckDuckGo | AI-assisted answers in DuckDuckGo Search | AI search and user-directed (on by default) |
| `MistralAI-Index` | Mistral AI | Mistral search | AI search and user-directed (on by default) |
| `Meta-ExternalFetcher` | Meta | Fetching a link a user asked for | AI search and user-directed (on by default) |
| `Amzn-User` | Amazon | Fetching live information a user asked for | AI search and user-directed (on by default) |
| `MistralAI-User` | Mistral AI | Fetching a page a user asked for | AI search and user-directed (on by default) |

**This changes the `robots.txt` of sites that already use AI Discovery.** After the update, the virtual file holds a group for each new crawler, allowed or disallowed as the switches are set. A physical `robots.txt` file, and a crawler another plugin already names, are left alone as before.

With the defaults, the four training crawlers get `Disallow: /`:

- `Applebot-Extended` and `Google-Extended` do not crawl. They only tell Apple and Google whether pages their search crawlers fetched may be used for their models, so search results are not affected.
- `MistralAI-Training` crawls for training only.
- `Meta-ExternalAgent` and `Amazonbot` are crawlers that their vendors use for more than training. A `Disallow: /` stops them altogether, including Meta's product indexing and Amazon's product and service improvements. Meta's and Amazon's search crawlers are separate tokens, `Meta-WebIndexer` and `Amzn-SearchBot`, and follow the AI search switch, which is on by default.

`CCBot` was left out because Common Crawl documents no AI purpose for it, and `Claude-Agent` because Anthropic describes no `robots.txt` control for it. Some user-requested fetchers, such as `ChatGPT-User`, `Perplexity-User`, `Meta-ExternalFetcher`, and `Amzn-User`, say they may not follow `robots.txt`. [AI Crawlers](ai-crawlers.md) lists every token with its source.

### AI readiness report

The six readiness rows of 1.1 are replaced by a report grouped by the areas agent-readiness scanners look at. It adopts every check of the reference at isitagentready.com, and it does not copy that scanner's levels or score: each row states one fact, as **passed**, **needs attention**, **off by choice**, or **not applicable**.

| Area | Rows |
|---|---|
| Discoverability | Search visibility; the served `robots.txt`; Sitemap (`/sitemap.xml` after redirects, or a Sitemap line); Sitemap line in `robots.txt`; Link headers; DNS records for agents |
| Content | Markdown on request; the served `llms.txt`; pages listed in `llms.txt`; who answers `llms.txt`, when something else does |
| Bot governance | Who writes `robots.txt`; model-training crawlers; AI crawler rules; whole-site blocks; content signals; Web Bot Auth key directory |
| Structured data | The Organization or Person node: name, logo or photo, description, profile links, naming what is missing |
| Capabilities | API catalog; agent skills index; agent resource discovery manifest; MCP server card; OpenAPI; WebMCP |
| Authentication | OAuth metadata and `auth.md` |
| Agent protocols | A2A agent card; commerce protocols |

- **Served addresses are checked in your browser.** Once the card is open, the browser fetches `/robots.txt`, `/sitemap.xml`, the homepage, the homepage again asking for `text/markdown`, `/llms.txt`, and the well-known addresses below, without your sign-in, the way a crawler or an agent would. The server makes no request.
- **Files this version does not publish are not problems.** Markdown versions of pages, an API catalog (`/.well-known/api-catalog`), an agent skills index (`/.well-known/agent-skills/index.json`), an agent resource manifest (`/.well-known/ard.json` or `/.well-known/ai-catalog.json`), and an MCP server card (`/.well-known/mcp/server-card.json` and two older addresses) show "Found" when another plugin or a CDN serves one, and a neutral "Not offered" otherwise.
- **Checks that do not apply say why.** Web Bot Auth, OAuth metadata and `auth.md`, A2A agent cards, OpenAPI, WebMCP, DNS records for agents, and commerce protocols are provided by whatever runs the service they describe: a bot operator, a sign-in server, an agent, an API, a shop, or the DNS provider. Each row says in one sentence what the standard is for and what Lightweight SEO does about it, without claiming what else the site runs.
- **A failed request is not a finding.** When this browser cannot fetch an address, for example because the administration runs on `www` and the site does not, or on `http` and the site on `https`, the row says "Not checked" instead of reporting the file as missing. A row with several addresses is not checked only when none of them could be fetched.
- **Choices are not failures.** Disallowing model-training crawlers, leaving `llms.txt` or content signals off, and switching core schema output off are shown as off by choice.
- Without JavaScript, the rows about served addresses say they are checked in the browser and link to each address.

### Content signals

A new switch on the AI Discovery card, **State how this site's content may be used for search, AI answers, and AI training**, is off by default. While it is on, and WordPress answers `robots.txt` (no physical file, and the site is not hidden from search engines), the virtual file gains one line inside the group for every crawler:

```text
User-agent: *
# Content signals, as defined at https://contentsignals.org/
Content-Signal: ai-train=no, search=yes, ai-input=yes
Disallow: /wp-admin/
```

- `search` is always `yes`.
- `ai-input`, using pages for AI answers, follows "Allow bundled AI search and user-directed crawler tokens".
- `ai-train`, training AI models, follows "Allow bundled model-training crawler tokens".

The placement follows contentsignals.org and Cloudflare's managed `robots.txt`: the line goes inside the `User-agent: *` group, under its User-agent lines and before its rules. When the file has no such group, one is added with `Allow: /`, which allows what was allowed anyway. A file that already holds a `Content-Signal` line, from another plugin or the theme, is left alone.

Cloudflare's own file adds a long comment above the line, with the policy's definitions and a reservation of rights under EU copyright law. Cloudflare's documentation does not require it, so the plugin prints only a one-line comment that names where the signals are defined. A site that wants the full text can add it with the `robots_txt` filter.

A content signal states a preference. Crawlers may ignore it, as they may ignore any `robots.txt` rule. The served-file check below reports whether a `Content-Signal` line is served.

### The served robots.txt

The checks of 1.1 looked for a `robots.txt` file on disk and for other plugins' rules. A file answered by the web server, a CDN, or a host's own setting went unnoticed, even when it replaced everything WordPress writes. The AI Discovery card now reads the file crawlers actually get:

- The administrator's browser fetches `/robots.txt` at the root of the host, without its sign-in, the way a crawler does. The server makes no request.
- **robots.txt** says whether the address answered with a plain text file, and reports a failed fetch, a status other than 200, or a type other than `text/plain`.
- **AI crawler rules** says whether the rules for the AI crawlers set on the card are in the served file, and names any that are missing or give a different answer. When none are there, another file or setting answers the address instead of WordPress.
- **Whole-site blocks** names any group for Googlebot, Bingbot, or every crawler (`*`) that disallows the whole site. A group that disallows `/` but also has `Allow` lines is reported as disallowing the homepage and every page those lines do not list, since the listed paths can still be fetched.
- **Sitemap line in robots.txt** lists the `Sitemap:` lines, or gives the exact line to add when WordPress sitemaps are on.
- **Content signals** lists the `Content-Signal` lines served, and says when the line this card adds is missing.
- WordPress answers `robots.txt` only when it is installed at the root of the host. On a subdirectory install the card says so, and that the rules set on the card are not served there.
- Without JavaScript, these rows say they are checked in the browser and link to the served file.

### llms.txt

- The file opens with a one-line summary of the site under its name: the homepage meta description from the "Description and sharing" card on **SEO → Search appearance**, or the tagline when there is none. With neither, the summary line is left out. 1.1 printed a disclaimer there; it is still shown on the AI Discovery card.
- When the static front page is one of the listed pages, it is listed the way the homepage prints it. Its link text is the page's own SEO title, or the homepage title format, such as "Site name – Tagline". Its note is the page's own description, or the homepage meta description. Other pages are listed as before.

## Content checks

### In the editor

The Checks tab of the editor panel has two new rows and a clearer description row. All three follow the page as it is edited, in the block editor and the classic editor.

- **Meta description** says which description the page prints: "The description written here is printed", "Nothing is written here, so the homepage meta description is printed" on a static front page, or "Nothing is written here, so a summary of the excerpt or content is printed". When none of these exists, as on a page whose text comes from the theme or from custom fields, it says "No description is printed for this page". A password-protected page prints no description, and the row says so.
- **Image alternative text** counts the images in the content that have no alternative text, such as "2 of 3". It is a warning to review, not a failure: an image that is only decorative may keep an empty one. It is hidden when the content has no images.
- **Main headings** warns when the content has more than one H1 heading, since the theme usually prints the page title as the H1.

### In the Inspect report

The report on the Tools tab ends with a **Content** section, measured on the page as it is served:

- the characters of text in the main content, read from the page's `main` element, or its first `article`, or its `body`
- the number of H1 headings on the page
- images with an empty or missing `alt` attribute, out of all images on the page

The counts come from the administrator's browser. After the report arrives, the Tools tab requests the page again, signed out, as any visitor would, and counts what it receives with the same functions the editor uses. So the site does no extra work for a visitor's request, and the page is measured with its theme templates and custom fields, not only its post content. The report opened in a new tab, without the script, says to open it from the Tools tab to see the counts.

Text a script adds after the page loads is not counted, and the counts state numbers, not grades.

## Site identity

- The "Site identity" card on **SEO → Schema and identity** has a "Description" field. A sentence or two about who the organization or person is is printed as `description` on the Organization or Person node of the structured data. Google lists it as a recommended property of an organization. Left empty, nothing is printed, as in 1.1.

## Upgrading from 1.1

Nothing changes until a new field is filled in or a new switch is turned on. Two settings are new in the existing settings option: `identity_description`, empty by default, and `enable_content_signals`, off by default. There is no migration.
