Written against v1.3.1.

At the end of this walkthrough, every Team Showcase entry on the site has a matching People Showcase entry, and the site's `[show-team...]` shortcodes point at People Showcase.

**Note:** the tool only creates People Showcase entries. It never edits, unpublishes or deletes a Team Showcase entry, so the old entries stay where they are until you remove them yourself.

1. **Activate both plugins.** Team Showcase and People Showcase must both be active, along with this plugin. If either one is missing, the tool page shows: `Both Team Showcase and People Showcase must be active on this site to run the migration.`
2. **Open the tool.** Sign in as a user with the `manage_options` capability (an administrator) and go to **Tools**, then **Team → People Migration**. The page shows the number of posts still pending migration.
3. **Preview a batch.** Select **Preview (dry run - no changes made)**. The results table lists up to 100 Team Showcase entries, with their mapped fields, taxonomy terms (a term that would be created is marked `(new)`) and any warnings. Nothing is written.
4. **Run the migration.** Select **Run Migration** and confirm the prompt. The tool creates a People Showcase entry for each of up to 100 pending Team Showcase entries, and the results table links each old entry to its new one.
5. **Repeat until nothing is pending.** When more than 100 entries are pending, the page says so; select **Run Migration** again after each batch. Entries already migrated are skipped, so a second run does not duplicate them.
6. **Review the new entries.** Follow [Checking the migrated entries](Checking-the-migrated-entries.md), above all for the sort text and any raw-copied fields named in the warnings.
7. **Update the shortcodes.** Under **Shortcode Finder & Updater**, select **Scan Site**, then **Update All Found Shortcodes**. [Updating show-team shortcodes](Updating-show-team-shortcodes.md) covers the steps and what to check afterwards.

**Warning:** run the shortcode update only after every entry is migrated. A post ID in `ids=""` or `exclude=""` is translated only when its entry has already been migrated; an ID with no migrated entry is left as it is and reported.

## Going further

- [Field and taxonomy mapping](Field-and-taxonomy-mapping.md)
- [Shortcode rewriting](Shortcode-rewriting.md)
- [Troubleshooting](Troubleshooting.md)
