# Lightweight SEO 1.2 Release Notes

These notes describe the 1.2 line by feature, as it is built. [Changelog](../CHANGELOG.md) lists each change. The 1.1 line is described in [1.1 Release Notes](release-notes-1.1.0.md).

## Highlights

1.2 takes a position on AI discoverability alongside conventional SEO. The plugin checks whether AI crawlers and agents can reach and read the site, and publishes agent-discovery output only for things the site really offers. Each check states a fact; there is no readiness score or level, and nothing here promises crawling, citation, training, inclusion, or ranking.

## AI Discovery

### llms.txt

- The file opens with a one-line summary of the site under its name: the homepage meta description from the "Description and sharing" card on **SEO → Search appearance**, or the tagline when there is none. With neither, the summary line is left out. 1.1 printed a disclaimer there; it is still shown on the AI Discovery card.
- When the static front page is one of the listed pages, it is listed the way the homepage prints it. Its link text is the page's own SEO title, or the homepage title format, such as "Site name – Tagline". Its note is the page's own description, or the homepage meta description. Other pages are listed as before.

## Site identity

- The "Site identity" card on **SEO → Schema and identity** has a "Description" field. A sentence or two about who the organization or person is is printed as `description` on the Organization or Person node of the structured data. Google lists it as a recommended property of an organization. Left empty, nothing is printed, as in 1.1.

## Upgrading from 1.1

Nothing changes until a new field is filled in or a new switch is turned on. One setting is new in the existing settings option: `identity_description`, empty by default. There is no migration.
