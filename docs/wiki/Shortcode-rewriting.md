Written against v1.3.1.

How **Shortcode Finder & Updater** finds Team Showcase shortcodes and rewrites them.

## Shortcodes renamed

| Before | After |
| --- | --- |
| `[show-team ...]` | `[show-people ...]` |
| `[show-team-search ...]` | `[show-people-search ...]` |
| `[show-team-users ...]` | `[show-people-users ...]` |
| `[show-team-count ...]` | `[show-people-count ...]` |
| `[show-team-info ...]` | `[show-people-info ...]` |

Closing tags (`[/show-team]`) are renamed the same way. The attributes are kept, apart from the post ID translation below.

## Where the scan looks

- **Post content** of every post of any type whose status is not Trash.
- **Classic widgets:** the Text widget (`widget_text`) and the Custom HTML widget (`widget_custom_html`).

Nothing else is read: not other post meta, block widgets, or a page builder's own storage.

## Post ID translation

Inside each main `[show-team ...]` shortcode in a location being updated, an `ids="..."` or `exclude="..."` attribute holding a comma-separated list of numbers is rewritten through the migration flags: a Team Showcase post ID becomes the ID of the People Showcase entry migrated from it. An ID with no migrated entry is left as it is and listed in the location's note.

Only the main shortcode's attributes are read. An `ids` or `exclude` attribute on another shortcode in the same content (a form or gallery shortcode, for example), on the suffixed `[show-team-...]` shortcodes, or in plain text is left exactly as it is, since those lists may hold IDs that are not Team Showcase posts. Since the release after v1.3.1; v1.3.1 and earlier translated every such attribute in an updated location.

For example, with Team Showcase entry 41 migrated to People Showcase entry 97 and entry 42 not migrated:

```text
before  [show-team ids="41,42"] [contact-form ids="41"]
after   [show-people ids="97,42"] [contact-form ids="41"]
note    ID(s) 42 in ids/exclude have no matching People post yet (not migrated) - left as-is, double check by hand.
```

## Scan and update

**Scan Site** lists the locations and writes nothing. **Update All Found Shortcodes** scans again, then rewrites every location found. Running the update a second time finds nothing, since the renamed shortcodes no longer match.
