# Repository Agent Rules

WordPress on-page SEO plugin. PHP 7.4+, WordPress 6.0+, no build step. Ships: `lightweight-seo.php`, `uninstall.php`, `includes/`, `admin/`, `languages/`.

## Rules

- Core makes no remote request, recurring job, site-wide scan, custom table, or database write on a page view. IndexNow, off by default, is the only exception.
- A disabled module loads no class and registers no hook. Load a new file only in the requests that need it.
- Code runs on PHP 7.4 and WordPress 6.0. Guard anything newer with `function_exists()`.
- Every mutation checks a capability and a nonce. Escape at output.
- Never rewrite or delete existing user metadata. A new setting defaults to the current behavior.
- No SEO score, keyword density, or ranking promise, in code or copy.
- Ask before adding a feature that [docs/architecture.md](docs/architecture.md) excludes.
- Never uninstall or delete the plugin on a site where it is symlinked: WordPress deletes the repository through the link. Use `bin/dev-site up --copy`.

## Commands

```bash
composer install     # once per checkout
composer check       # every offline gate; run before each commit
composer test        # unit tests, about a second
composer make-pot    # after changing a translatable string
bin/dev-site up      # throwaway WordPress for manual or destructive tests
```

## Done means

| Change | Also required |
|---|---|
| Any | A test that fails without it, `composer check` passing, an entry under "Unreleased" in `CHANGELOG.md` |
| Behavior a user sees | `README.md`, `docs/release-notes-1.1.0.md`, a check in `docs/qa-checklist.md` |
| A string | `composer make-pot`. Sentence case; say what a setting does before naming the directive |
| Hook, API method, ability, WP-CLI command | `docs/developer-api.md` |
| Setting, module, import source, stored data, new file, release | Every step of its recipe in [docs/recipes.md](docs/recipes.md) |
| Administration interface | `docs/design-system.md`; reuse before adding |

Other docs are listed in [docs/README.md](docs/README.md).

## Git

- Branch in a worktree, never `main`. Other sessions and a linked local test site share the root checkout: do not switch its branch or leave files in it.
- Commit subject: `<EMOJI> <TYPE>: <imperative summary>`, following [Emoji-Log](https://github.com/ahmadawais/Emoji-Log). Exactly one prefix: `📦 NEW:`, `👌 IMPROVE:`, `🐛 FIX:`, `📖 DOC:`, `🚀 RELEASE:`, `🤖 TEST:`, or `‼️ BREAKING:`. One dominant action per commit and pull request, for example `📦 NEW: Add sitemap support`.
- Merge a pull request with a merge commit titled `<EMOJI> <TYPE>: Merge <what> into main (#<number>)`.
- A `v*` tag publishes a release. Tag only when the owner asks.
