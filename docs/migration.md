# Migration and RC Testing

## Before upgrading from 1.0.3

1. Use a staging copy first and back up the database.
2. Record any submitted image, video, or news sitemap URLs; those endpoints are retired.
3. If Search Console credentials were stored, plan to copy or delete them explicitly. They are not reused by core or a future Insights connection.
4. Record current Tracking, Redirects, Hreflang, and Local SEO behavior.
5. Install the release-candidate ZIP over 1.0.3 and open **SEO → Tools**.

## Automatic upgrade behavior

The idempotent schema migration preserves supported settings and post, term, and user SEO metadata. Existing valid tracking IDs enable Tracking; explicit Redirects, Hreflang, and Local SEO choices are preserved.

Redirects and Tracking each gain an "Enable module" switch. The migration sets it to what the module was doing before the upgrade: on where redirect rules, automatic slug redirects, or a valid tracking ID were in use, off otherwise. From then on only the switch decides, and switching a module off keeps its rules and IDs.

The migration stops meta-keywords output, removes retired specialized sitemap settings, report caches, Search Console tokens, and its scheduled synchronization event. Stored global and post-level keyword values remain available for CSV export. Search Console property/private-key values remain visible only to administrators until explicitly copied or deleted. Retained legacy 404 log entries are deleted from the same screen with a confirmation box and their own button.

A one-time administrator notice summarizes these changes. Dismissing it does not delete retained migration data.

## Importing another SEO plugin

The Tools screen supports Yoast SEO, Rank Math, All in One SEO, SEOPress, The SEO Framework, and Slim SEO metadata.

| Source | Read from | Notes |
|---|---|---|
| SEOPress | `_seopress_titles_title`, `_seopress_titles_desc`, `_seopress_robots_canonical`, `_seopress_robots_index`, `_seopress_robots_follow`, `_seopress_robots_snippet`, `_seopress_social_fb_title`, `_seopress_social_fb_desc`, `_seopress_social_fb_img`, `_seopress_analysis_target_kw` | Variables for the title, site title, tagline, separator, current year, author, first category, and "Page 2 of 5" are converted. Any other variable leaves that field for review. |
| The SEO Framework | `_genesis_title`, `_genesis_description`, `_genesis_canonical_uri`, `_genesis_noindex`, `_genesis_nofollow`, `_genesis_noarchive`, `_open_graph_title`, `_open_graph_description`, `_social_image_url`, `_social_image_id` | A robots choice of 1 is applied, and −1 becomes the opposite choice: "Show in search results" for `noindex`. Text is plain, with no variables. |
| Slim SEO | The `slim_seo` array: `title`, `description`, `canonical`, `noindex`, `facebook_image` | `{{ post.title }}`, `{{ site.title }}`, `{{ site.description }}`, `{{ sep }}`, `{{ page }}`, `{{ current.year }}`, and `{{ author.display_name }}` are converted. An image stored as an attachment ID is imported as its address. |

A page that an import hides from search results is stored with "follow links", so it prints `noindex` only. A page that was already hidden before the import keeps the behaviour it had.

- Choose the source, then use **Preview next batch** or **Import next batch**. The source is remembered together with the import position; **Save Changes** does not store it.
- Preview is read-only.
- Each batch scans at most 50 posts.
- Only empty Lightweight SEO fields are filled.
- The latest imported batch can be rolled back until another import replaces its rollback snapshot.
- Title and description variables are translated to Lightweight SEO tokens (`%title%`, `%sitename%`, `%tagline%`, `%sep%`, `%year%`, `%author%`, `%page%`, and Rank Math's `%category%`), which are resolved when the page renders. Variables for a bare page number or a page total are dropped.
- A field that uses any other source variable (for example Yoast `%%primary_category%%`) is not imported, because it would otherwise print literally in the page head. The import report counts these fields so they can be rewritten by hand.
- Run the preview again after changing sources; changing source resets the cursor.

## RC acceptance

Follow the complete [QA Checklist](qa-checklist.md). At minimum, confirm frontend title/canonical/robots/schema output, safe mode with any other active SEO plugin, every enabled module used by the site, migration exports/deletions, and deactivation/uninstall behavior.

If the candidate must be rolled back, restore the database backup together with the 1.0.3 plugin files. Do not run a delete-all uninstall first.
