# Design System

The admin interface of Lightweight SEO is built from one small set of tokens and components. This page is the reference for using them and the rules for adding to them.

| Part | File |
| --- | --- |
| Tokens and component styles | `admin/css/lightweight-seo-admin.css` |
| Component markup helpers | `includes/class-lightweight-seo-admin-ui.php` |
| Component behavior | `admin/js/lightweight-seo-admin.js` |

There is no build step, no icon set beyond the Dashicons WordPress already loads, and no JavaScript framework. The stylesheet is about 4 KB gzipped and the script about 3 KB.

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
| Status list | `lwseo-status-list`, `lwseo-status` | `status()` | Checklists, health checks, and at-a-glance facts. |
| Callout | `lwseo-callout` | `callout()` | One message that must stand apart from help text. |
| Tokens | `lwseo-tokens`, `lwseo-token` | `tokens()` | Title variables that insert themselves into the field being edited. |
| Image | `lwseo-image` | `image_field()` | URL input, media picker, and thumbnail. |
| Table | `lwseo-table-wrap`, `lwseo-table` | | Read-only data. |
| Log | `lwseo-log` | | Plain output from a tool run. |
| Details | `lwseo-details` | | Advanced or developer detail inside a card. |
| Actions | `lwseo-actions`, `lwseo-buttons` | | The sticky save bar, and a row of buttons. |
| Preview | `lwseo-preview` | | Search result and share previews in the editor. |
| Meter | `lwseo-meter` | | Where a length sits against its guidance range. |

Buttons and admin notices are the WordPress ones (`button`, `button-primary`, `button-link-delete`, `notice`). Do not restyle them.

## Usage Rules

- **Checkbox, not switch.** Settings apply on save. A switch suggests an immediate effect, so booleans are checkboxes.
- **Sentence case** for every title, label, tab, and button: "Default title format", not "Default Title Format".
- **One primary button per screen**, and it is Save. Tool actions are secondary buttons. Destructive actions use `button-link-delete` and require a confirmation control.
- **Badge wording.** A module is `Active` or `Off`. Unfinished features are `Experimental`. Do not invent new status words for the same meaning.
- **Status states.** `success` for ready, `warning` for something to review, `pending` for a step not yet done, `info` for a neutral fact. A failed check is a `warning` with a message that says what to do.
- **Help text** is one sentence, starts with what the control does, and stays under the control it explains. Longer background goes in a `lwseo-details`.
- **Callouts are rare.** Use one when ignoring the message would cause a wrong decision. Otherwise use help text.
- **No raw values.** If a color, size, or duration is not a token, add a token or reuse one.

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

## Accessibility Checklist

- A single control is tied to its row title with `<label for>`. A row with several controls is a `role="group"` named by its title.
- Status icons are decorative. `status()` adds a screen reader prefix such as "Ready:" or "Needs attention:".
- Links that open a new tab say so to screen readers.
- Editor tabs follow the tablist pattern: arrow keys move between tabs and only the active tab is in the tab order.
- Focus is always visible, using the accent color.
- Layout holds from 320 px wide. Field rows stack below 782 px, the WordPress admin breakpoint.

## What Stays Native

Term and user edit screens use the standard WordPress form rows so the SEO fields match the fields around them. They do not load the plugin stylesheet.
