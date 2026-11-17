# Design System

The admin interface of Lightweight SEO is built from one small set of tokens and components. This page is the reference for using them and the rules for adding to them.

| Part | File |
| --- | --- |
| Tokens and component styles | `admin/css/lightweight-seo-admin.css` |
| Component markup helpers | `includes/class-lightweight-seo-admin-ui.php` |
| Component behavior | `admin/js/lightweight-seo-admin.js` |
| Stored line formats | `admin/js/lightweight-seo-formats.js` |

There is no build step, no icon set beyond the Dashicons WordPress already loads, and no JavaScript framework. Gzipped, the stylesheet is about 6 KB, the script about 10 KB, and the formats file, which only the settings screen loads, about 3 KB.

## Principles

1. **Native first.** The plugin should read as part of WordPress. Type size, form controls, buttons, and notices are the ones WordPress ships. The accent color follows the admin color scheme the user chose in their profile.
2. **Light.** A new rule or component must earn its place. Reuse a component before adding one, and use a token before writing a raw value.
3. **Calm and positive.** Report facts, not scores. Green means ready, amber means worth a look, gray means off or optional. Nothing is red unless it destroys data. An optional step never counts against progress.
4. **Clear before clever.** One idea per card, one line of help per control, and advanced or developer detail behind a disclosure.
5. **Accessible by default.** Every control has a label, status never relies on color alone, everything works from the keyboard, and motion respects `prefers-reduced-motion`.

## Tokens

Tokens are CSS custom properties defined on `.lwseo`, the root class of every plugin screen. They are the only place raw values appear.

| Group | Tokens | Notes |
| --- | --- | --- |
| Accent | `--lwseo-accent`, `-strong`, `-soft` | Inherit from `--wp-admin-theme-color`. |
| Neutrals | `--lwseo-text`, `--lwseo-text-muted`, `--lwseo-surface`, `--lwseo-surface-subtle`, `--lwseo-canvas`, `--lwseo-border`, `--lwseo-border-subtle`, `--lwseo-border-strong`, `--lwseo-neutral-soft` | WordPress admin gray scale. |
| Status | `--lwseo-success`, `--lwseo-warning`, each with `-icon` and `-soft` | Text tones pass 4.5:1 on white and on their soft tone. |
| Preview | `--lwseo-preview-link`, `--lwseo-preview-text` | Only for the search result preview. |
| Space | `--lwseo-space-1` to `--lwseo-space-6` | 4, 8, 12, 16, 24, 32 px. |
| Type | `--lwseo-text-sm`, `-base`, `-md`, `-lg`, `--lwseo-leading` | 12, 13, 14, 20 px. Base matches the admin. |
| Shape | `--lwseo-radius-sm`, `--lwseo-radius`, `--lwseo-radius-pill` | 4 px for controls, 8 px for surfaces. |
| Motion | `--lwseo-motion` | 150 ms. Becomes 0 ms under reduced motion. |
| Layout | `--lwseo-measure`, `--lwseo-label-width`, `--lwseo-control-height` | Page width and field grid. |

## Components

Class names use `lwseo-block__element`, with `--modifier` for variants and `is-state` for states. A component marked with a helper should be printed through `Lightweight_SEO_Admin_UI`, which escapes its own output.

| Component | Class | Helper | Use it for |
| --- | --- | --- | --- |
| Page | `lwseo-page`, `lwseo-header` | | The settings screen shell: mark, title, version. |
| Tabs | `lwseo-tabs`, `lwseo-tabs__tab` | | Page navigation (links) and in-place panels (`role="tab"` buttons). |
| Card | `lwseo-card` with `__header`, `__title`, `__description`, `__meta`, `__body` | | The one surface. Use `<details>` for a card that collapses. |
| Field | `lwseo-field` with `__label`, `__control` | | A labelled row. `--stacked` puts the label on top. |
| Subfields | `lwseo-subfields`, `lwseo-subfield` | | Several short labelled inputs inside one field. |
| Choice | `lwseo-choice` | `choice()` | A checkbox with its label and help text. |
| Help | `lwseo-help` | | One line of guidance under a control. |
| Badge | `lwseo-badge` | `badge()` | A one or two word status: `is-success`, `is-warning`, `is-info`, or neutral. |
| Status list | `lwseo-status-list`, `lwseo-status` | `status()` | Checklists, health checks, and at-a-glance facts. Pass attributes to `status()` when a script updates the row. |
| Callout | `lwseo-callout` | `callout()` | One message that must stand apart from help text. |
| Tokens | `lwseo-tokens`, `lwseo-token` | `tokens()` | Title variables that insert themselves into the field being edited. |
| Image | `lwseo-image` | `image_field()` | URL input, media picker, and thumbnail. Leave `id_name` out for an image stored as a URL only. |
| Table | `lwseo-table-wrap`, `lwseo-table` | | Read-only data. |
| Log | `lwseo-log` | | Plain output from a tool run. |
| Details | `lwseo-details` | | Advanced or developer detail inside a card. |
| Rows | `lwseo-rows` | `rows()` | A setting stored one item per line, edited one item per row. |
| Hours | `lwseo-hours` | `hours()` | Opening times: one row per day, each with any number of time ranges. |
| Picker | `lwseo-picker`, `lwseo-pagecell` | `picker()` | Pages chosen by searching instead of typing IDs: a list, or one page inside a row. |
| Actions | `lwseo-actions`, `lwseo-buttons` | | The sticky save bar, and a row of buttons. `lwseo-actions__status` says when the form has unsaved changes. |
| Preview | `lwseo-preview` | `search_preview()`, `share_preview()` | Search result and share previews, in the editor and on the overview. `lwseo-previews` places two side by side. |
| Meter | `lwseo-meter` | | Where a length sits against its guidance range. |

Buttons and admin notices are the WordPress ones (`button`, `button-primary`, `button-link-delete`, `notice`). Do not restyle them.

## Usage Rules

- **Checkbox, not switch.** Settings apply on save. A switch suggests an immediate effect, so booleans are checkboxes.
- **Rows, not typed formats.** A setting that holds several items is edited as rows, day rows, or a picker. Nobody should have to learn a line format to fill in a setting.
- **Tick boxes, not typed lists.** A choice from a known set, such as roles or environments, is a list of checkboxes. An empty first entry keeps the setting in the request so the list can be saved empty.
- **A module that is off shows only what turns it on.** Its reports and tools appear once it is in use, so an unused module never prints an empty state per row.
- **Sentence case** for every title, label, tab, and button: "Default title format", not "Default Title Format".
- **One primary button per screen**, and it is Save. Tool actions are secondary buttons. Destructive actions use `button-link-delete` and require a confirmation control.
- **Badge wording.** A module is `Active` or `Off`. Unfinished features are `Experimental`. Do not invent new status words for the same meaning.
- **Status states.** `success` for ready, `warning` for something to review, `pending` for a step not yet done, `info` for a neutral fact. A failed check is a `warning` with a message that says what to do.
- **Help text** is one sentence, starts with what the control does, and stays under the control it explains. Longer background goes in a `lwseo-details`.
- **Callouts are rare.** Use one when ignoring the message would cause a wrong decision. Otherwise use help text.
- **No raw values.** If a color, size, or duration is not a token, add a token or reuse one.

## Structured Editors

Redirect rules, hreflang mappings, opening hours, and the `llms.txt` page list are stored as text, one item per line or as a list of IDs. The settings screen shows them as rows without changing what is stored.

- **The stored field stays.** The caller prints the textarea or input exactly as before. It is the only control that is submitted, so the screen works without JavaScript and the validators in `Lightweight_SEO_Settings` are the single source of truth. The editor is printed `hidden` beside it; the script reveals the editor and hides the field.
- **Nothing is rewritten on load.** The field changes only after the user edits a row, so opening a tab never marks the form as unsaved.
- **Rows that are not finished are not written.** A hint under the row says what is missing. It never blocks saving.
- **"Edit as text" shows the field again**, for pasting many lines at once. A line the editor cannot show is kept untouched and counted in a note.
- **Row controls have no `name`**, and their markup comes from a `<template>` printed by PHP, so labels are translatable and escaped.
- **Searching for a page** calls the site's own REST API from the browser (`wp/v2/search`). PHP requests nothing, and names for pages that are already stored are printed by the server from a bounded lookup by ID.

To add a row editor:

1. Add a format to `lightweight-seo-formats.js` with `parse(line)`, `format(row)`, and `check(rows)`, and cover it in `tests/js/formats.test.js`. The file has no DOM access, so the tests run under Node with `node --test tests/js/*.test.js`.
2. Print the stored field as usual, give anything that explains its raw format a `data-lwseo-text-for` attribute naming the field, and call `Lightweight_SEO_Admin_UI::rows()` with the format key, the field ID, and the columns.
3. Give the rule a `--lwseo-rows-columns` value in the stylesheet if three equal columns do not suit it.

## Live Editor Checks

The editor panel is printed in its saved state and then follows the page as it is edited.

- Each check has a stable ID. The server prints its row with `data-check`, both outcomes in `data-pass` and `data-fail`, and the field that fixes it in `data-target`. A check that does not apply yet is printed with `hidden`.
- The script holds one rule per ID in `checkRules`. A rule returns `true`, `false`, the name of an alternative failure message (`data-fail-{name}`), or `null` when the check does not apply.
- Add a check in both places: `get_editor_checks()` in `class-lightweight-seo-meta-boxes.php` and `checkRules` in the script. A test fails when a printed check has no rule. A check only the server can decide, such as `conflicts`, has no rule and keeps its printed state.
- The count on the Checks tab becomes a tick when nothing is left to review. The tick animates once, only when an edit clears the last check, and never on page load.

## Adding to a Settings Tab

Settings stay registered through the Settings API in `Lightweight_SEO_Admin_Settings::get_sections()`. Layout is separate and lives in `Lightweight_SEO_Admin_Page::get_tab_cards()`.

1. Register the field in `get_sections()`. Pass `true` as the third value when the field has a single control, and give that control the ID `lightweight_seo_{field_id}` so the row prints a real `<label>`.
2. Render it in the matching fields class using the components above.
3. Add the field to a card in `get_tab_cards()`, or add a new card.

A card accepts:

| Key | Meaning |
| --- | --- |
| `section` | Settings API section the fields come from. |
| `fields` | Field IDs to show. Omit to show the whole section. |
| `title` | Card title. Defaults to the section title. |
| `description` | One line under the title. |
| `intro` | Print the section callback at the top of the card body. |
| `module` | Module ID. The card collapses, shows `Active` or `Off`, and starts open only when the module is in use. |
| `badge` | Extra badge text, such as `Experimental`. |
| `plain` | Hide the field label visually when the card title already says it. |

`get_tab_cards()` builds the cards for one tab at a time, so a tab can leave out a field or a card that does not apply without the other tabs paying for the check.

## Accessibility Checklist

- A single control is tied to its row title with `<label for>`. A row with several controls is a `role="group"` named by its title.
- Status icons are decorative. `status()` adds a screen reader prefix such as "Ready:" or "Needs attention:".
- Links that open a new tab say so to screen readers.
- Editor tabs follow the tablist pattern: arrow keys move between tabs and only the active tab is in the tab order.
- Focus is always visible, using the accent color.
- Layout holds from 320 px wide. Field rows stack below 782 px, the WordPress admin breakpoint.

## What Stays Native

Term and user edit screens use the standard WordPress form rows so the SEO fields match the fields around them. They do not load the plugin stylesheet.
