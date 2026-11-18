Written against v1.3.1.

One entry per symptom, as you meet it: the message or behavior, its cause, and the fix.

- [The tool page says both plugins must be active](#the-tool-page-says-both-plugins-must-be-active)
- [Tools has no Team to People Migration item](#tools-has-no-team-to-people-migration-item)
- [Pending stays above zero after a run](#pending-stays-above-zero-after-a-run)
- [An entry was migrated twice](#an-entry-was-migrated-twice)
- [A row says Not a tshowcase post](#a-row-says-not-a-tshowcase-post)
- [A row says Failed to create new post](#a-row-says-failed-to-create-new-post)
- [A shortcode still shows Team Showcase entries](#a-shortcode-still-shows-team-showcase-entries)
- [Fixed](#fixed)

## The tool page says both plugins must be active

The page shows:

```text
Both Team Showcase and People Showcase must be active on this site to run the migration.
```

**Cause:** the `tshowcase` or `uamspeople` post type is not registered, because one of the two plugins is inactive.

**Fix:** activate Team Showcase and People Showcase on the same site, then reload the page.

## Tools has no Team to People Migration item

**Cause:** the menu item needs the `manage_options` capability, which administrators have.

**Fix:** sign in as an administrator.

## Pending stays above zero after a run

**Cause:** each run processes at most 100 entries.

**Fix:** select **Run Migration** again until the page shows `Posts still pending migration: 0`.

## An entry was migrated twice

**Cause:** **Force re-migrate** was checked, which processes entries already migrated and creates another People Showcase entry for each.

**Fix:** move the extra People Showcase entries to the Trash. The Team Showcase entry's `_migrated_to_uamspeople_id` names the entry from the latest run.

## A row says Not a tshowcase post

**Cause:** the post was deleted, or changed to another post type, between the list being read and the row being processed.

**Fix:** reload the page and run again; the row no longer appears.

## A row says Failed to create new post

The row's warnings hold `Failed to create new post:` followed by WordPress's message.

**Cause:** WordPress refused to create the People Showcase entry, and the message says why. The Team Showcase entry is not flagged, so it stays pending.

**Fix:** correct what the message names, then run again.

## A shortcode still shows Team Showcase entries

**Cause:** the content is stored somewhere the scan does not read (a page builder, block widgets or post meta), or the shortcode's post IDs belonged to entries not yet migrated.

**Fix:** see [Updating show-team shortcodes](Updating-show-team-shortcodes.md), steps 4 and 5, and [Shortcode rewriting](Shortcode-rewriting.md#where-the-scan-looks).

## Fixed

Problems fixed in a release stay here, with the release that fixed them, instead of being deleted.
