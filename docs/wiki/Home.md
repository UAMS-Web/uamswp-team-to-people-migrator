UAMSWP Team to People Migrator is a one-time WordPress tool. It copies Team Showcase (`tshowcase`) entries into People Showcase (`uamspeople`) on the same site, then rewrites `[show-team...]` shortcodes to `[show-people...]`. It is for site administrators moving a site from one plugin to the other.

This wiki covers the walkthrough, the task guides, the reference and troubleshooting. The [README](https://github.com/UAMS-Web/uamswp-team-to-people-migrator#readme) keeps the overview, the requirements, installation and the development notes.

## Getting started

- [Installation (README)](https://github.com/UAMS-Web/uamswp-team-to-people-migrator#requirements-and-installation): what the site needs, and how to install the plugin.
- [Migrating a site](Migrating-a-site.md): preview, migrate in batches, then update the shortcodes.

## Guides

- [Checking the migrated entries](Checking-the-migrated-entries.md): what to review on the new People Showcase entries after a run.
- [Updating show-team shortcodes](Updating-show-team-shortcodes.md): find and rewrite the shortcodes that still point at Team Showcase.

## Troubleshooting

- [Troubleshooting](Troubleshooting.md): one entry per symptom, with its cause and fix.

## Reference

- [Field and taxonomy mapping](Field-and-taxonomy-mapping.md): what each Team Showcase field and taxonomy becomes, and the flags a migrated entry carries.
- [Shortcode rewriting](Shortcode-rewriting.md): which shortcodes are renamed, where the scan looks, and how post IDs are translated.

## Project

- [Project](Project.md): where the plugin is developed and how to report a problem.
