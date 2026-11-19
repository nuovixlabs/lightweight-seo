# AI Crawlers

The crawler tokens AI Discovery writes into the virtual `robots.txt`, where each one is documented, and when that page was last read. `get_crawler_registry()` in `includes/class-lightweight-seo-ai-discovery-module.php` holds the same list; `tests/LightweightSEOAIDiscoveryModuleTest.php` fails while the two disagree.

Each token follows one of the two switches on the AI Discovery card:

- **search** and **user**: "Allow bundled AI search and user-directed crawler tokens", on by default.
- **training**: "Allow bundled model-training crawler tokens", off by default.

"Follows robots.txt" is what the vendor's page says, not something the plugin can check.

## Tokens

All checked on 2026-11-19.

| Token | Vendor | Purpose | Follows robots.txt | Source |
|---|---|---|---|---|
| `OAI-SearchBot` | OpenAI | search | Yes | https://developers.openai.com/api/docs/bots |
| `ChatGPT-User` | OpenAI | user | Not always: user-initiated, so rules "may not apply" | https://developers.openai.com/api/docs/bots |
| `GPTBot` | OpenAI | training | Yes | https://developers.openai.com/api/docs/bots |
| `Claude-SearchBot` | Anthropic | search | Yes | https://support.claude.com/en/articles/8896518-does-anthropic-crawl-data-from-the-web-and-how-can-site-owners-block-the-crawler |
| `Claude-User` | Anthropic | user | Yes | https://support.claude.com/en/articles/8896518-does-anthropic-crawl-data-from-the-web-and-how-can-site-owners-block-the-crawler |
| `ClaudeBot` | Anthropic | training | Yes | https://support.claude.com/en/articles/8896518-does-anthropic-crawl-data-from-the-web-and-how-can-site-owners-block-the-crawler |
| `PerplexityBot` | Perplexity | search | Yes; the page asks sites to allow it in robots.txt | https://docs.perplexity.ai/guides/bots |
| `Perplexity-User` | Perplexity | user | Generally not: user-requested | https://docs.perplexity.ai/guides/bots |
| `Google-Extended` | Google | training | A control token only: Google crawls with its usual crawlers and reads this group to decide use for Gemini training and grounding. No effect on Google Search. | https://developers.google.com/search/docs/crawling-indexing/google-common-crawlers |
| `Applebot-Extended` | Apple | training | A control token only: it does not crawl; it decides whether pages Applebot fetched train Apple's foundation models. No effect on Apple search results. | https://support.apple.com/en-us/119829 |
| `Meta-ExternalAgent` | Meta | training | Yes; can be disallowed. Meta also uses it to improve products by indexing content. | https://developers.facebook.com/docs/sharing/webmasters/web-crawlers/ |
| `Amazonbot` | Amazon | training | Yes. Improves Amazon products and services and may train Amazon AI models. | https://developer.amazon.com/amazonbot |
| `DuckAssistBot` | DuckDuckGo | search | Yes; a disallow takes effect after 72 hours. Not used to train AI models. | https://duckduckgo.com/duckduckgo-help-pages/results/duckassistbot |
| `MistralAI-User` | Mistral AI | user | Not stated; the token "governs which sites these user requests can be made to" | https://docs.mistral.ai/robots |

## Documented, not added

Found on the same pages, or proposed, and left out of the list for now.

| Token | Vendor | Why it is not in the list |
|---|---|---|
| `CCBot` | Common Crawl | Follows robots.txt, but https://commoncrawl.org/ccbot and https://commoncrawl.org/faq describe an open web archive for research and name no AI search, user, or training purpose, so neither switch describes it. |
| `Amzn-SearchBot`, `Amzn-User` | Amazon | Not on the list of candidates for 1.2. Search and user-requested; documented on https://developer.amazon.com/amazonbot. |
| `MistralAI-Index`, `MistralAI-Training` | Mistral AI | Not on the list of candidates for 1.2. Search indexing and training; documented on https://docs.mistral.ai/robots. |
| `Meta-WebIndexer`, `Meta-ExternalFetcher` | Meta | Not on the list of candidates for 1.2. Meta AI search and user-requested fetches; documented on https://developers.facebook.com/docs/sharing/webmasters/web-crawlers/. |
| `Claude-Agent` | Anthropic | Browses on a user's behalf; its robots.txt behavior is on a separate page that was not read. |
| `OAI-AdsBot` | OpenAI | Reviews ad landing pages, not an AI search, user, or training crawler. |

## Refreshing the list

1. Open every source address above. Check the spelling of each token, its purpose, and what the page says about robots.txt.
2. Change `get_crawler_registry()` to match. A token is added only when its vendor documents it on a page of its own and its purpose is search, user, or training.
3. Update the tables and the date checked, the token names in the two help texts of `includes/class-lightweight-seo-admin-module-fields.php`, and run `composer make-pot`.
4. A new token changes the `robots.txt` of every site that already has AI Discovery on. Say so in `CHANGELOG.md` and the release notes, with the switch each new token follows.
