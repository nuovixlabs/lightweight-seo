# Migration and RC Testing

## Before upgrading from 1.0.3

1. Use a staging copy first and back up the database.
2. Record any submitted image, video, or news sitemap URLs; those endpoints are retired.
3. If Search Console credentials were stored, plan to copy or delete them explicitly. They are not reused by core or a future Insights connection.
4. Record current Tracking, Redirects, Hreflang, and Local SEO behavior.
5. Install the release-candidate ZIP over 1.0.3 and open **SEO → Tools and migration**.

## Automatic upgrade behavior

The idempotent schema migration preserves supported settings and post, term, and user SEO metadata. Existing valid tracking IDs enable Tracking; explicit Redirects, Hreflang, and Local SEO choices are preserved.

The migration stops meta-keywords output, removes retired specialized sitemap settings, report caches, Search Console tokens, and its scheduled synchronization event. Stored global and post-level keyword values remain available for CSV export. Search Console property/private-key values remain visible only to administrators until explicitly copied or deleted.

A one-time administrator notice summarizes these changes. Dismissing it does not delete retained migration data.

## Importing another SEO plugin

The Tools screen supports Yoast SEO, Rank Math, and All in One SEO metadata.

- Preview is read-only.
- Each batch scans at most 50 posts.
- Only empty Lightweight SEO fields are filled.
- The latest imported batch can be rolled back until another import replaces its rollback snapshot.
- Run the preview again after changing sources; changing source resets the cursor.

## RC acceptance

Follow the complete [QA Checklist](qa-checklist.md). At minimum, confirm frontend title/canonical/robots/schema output, safe mode with any other active SEO plugin, every enabled module used by the site, migration exports/deletions, and deactivation/uninstall behavior.

If the candidate must be rolled back, restore the database backup together with the 1.0.3 plugin files. Do not run a delete-all uninstall first.
