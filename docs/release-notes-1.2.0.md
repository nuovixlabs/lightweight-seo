# Lightweight SEO 1.2 Release Notes

These notes describe the 1.2 line by feature, as it is built. [Changelog](../CHANGELOG.md) lists each change. The 1.1 line is described in [1.1 Release Notes](release-notes-1.1.0.md).

## Highlights

1.2 takes a position on AI discoverability alongside conventional SEO. The plugin checks whether AI crawlers and agents can reach and read the site, and publishes agent-discovery output only for things the site really offers. Each check states a fact; there is no readiness score or level, and nothing here promises crawling, citation, training, inclusion, or ranking.

## AI Discovery

### More AI crawlers

AI Discovery writes `robots.txt` rules for five more crawlers. Each was checked on its vendor's own documentation page on 2026-11-19.

| Crawler | Vendor | Follows the switch |
|---|---|---|
| `Applebot-Extended` | Apple | Allow bundled model-training crawler tokens (off by default) |
| `Meta-ExternalAgent` | Meta | Allow bundled model-training crawler tokens (off by default) |
| `Amazonbot` | Amazon | Allow bundled model-training crawler tokens (off by default) |
| `DuckAssistBot` | DuckDuckGo | Allow bundled AI search and user-directed crawler tokens (on by default) |
| `MistralAI-User` | Mistral AI | Allow bundled AI search and user-directed crawler tokens (on by default) |

**This changes the `robots.txt` of sites that already use AI Discovery.** After the update, the virtual file holds a group for each new crawler, allowed or disallowed as the switches are set. With the defaults, Apple, Meta, and Amazon are asked not to use the site for model training, and DuckDuckGo's and Mistral AI's assistants may fetch it. A physical `robots.txt` file, and a crawler another plugin already names, are left alone as before.

`Applebot-Extended`, like `Google-Extended`, does not crawl; it tells Apple whether pages its search crawler fetched may train its models, and does not affect Apple's search results. `CCBot` was left out because Common Crawl documents no AI purpose for it. Some user-requested fetchers, such as `ChatGPT-User` and `Perplexity-User`, say they may not follow `robots.txt`. [AI Crawlers](ai-crawlers.md) lists every token with its source.

### The served robots.txt

The checks of 1.1 looked for a `robots.txt` file on disk and for other plugins' rules. A file answered by the web server, a CDN, or a host's own setting went unnoticed, even when it replaced everything WordPress writes. The AI Discovery card now reads the file crawlers actually get:

- The administrator's browser fetches `/robots.txt` at the root of the host, without its sign-in, the way a crawler does. The server makes no request.
- **Served robots.txt** says whether the address answered with a plain text file, and reports a failed fetch, a status other than 200, or a type other than `text/plain`.
- **Crawler rules from this card** says whether the rules for the AI crawlers set on the card are in the served file, and names any that are missing or give a different answer. When none are there, another file or setting answers the address instead of WordPress.
- **Whole-site blocks** names any group for Googlebot, Bingbot, or every crawler (`*`) that disallows the whole site.
- **Sitemap line** lists the `Sitemap:` lines, or gives the exact line to add when WordPress sitemaps are on.
- WordPress answers `robots.txt` only when it is installed at the root of the host. On a subdirectory install the card says so, and that the rules set on the card are not served there.
- Without JavaScript, the card keeps the checks of 1.1 and links to the served file.

### llms.txt

- The file opens with a one-line summary of the site under its name: the homepage meta description from the "Description and sharing" card on **SEO → Search appearance**, or the tagline when there is none. With neither, the summary line is left out. 1.1 printed a disclaimer there; it is still shown on the AI Discovery card.
- When the static front page is one of the listed pages, it is listed the way the homepage prints it. Its link text is the page's own SEO title, or the homepage title format, such as "Site name – Tagline". Its note is the page's own description, or the homepage meta description. Other pages are listed as before.

## Site identity

- The "Site identity" card on **SEO → Schema and identity** has a "Description" field. A sentence or two about who the organization or person is is printed as `description` on the Organization or Person node of the structured data. Google lists it as a recommended property of an organization. Left empty, nothing is printed, as in 1.1.

## Upgrading from 1.1

Nothing changes until a new field is filled in or a new switch is turned on. One setting is new in the existing settings option: `identity_description`, empty by default. There is no migration.
