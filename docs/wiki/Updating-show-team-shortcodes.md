Written against v1.3.1.

Pages and widgets that still use `[show-team...]` shortcodes show Team Showcase entries. This guide finds them and rewrites them to the People Showcase shortcodes, once every entry is migrated.

1. **Finish the migration first.** The tool page should show `Posts still pending migration: 0`. Post IDs in `ids=""` and `exclude=""` are translated only for entries already migrated.
2. **Scan.** Under **Shortcode Finder & Updater**, select **Scan Site**. The results table lists each post or widget holding a shortcode, with a link to edit it and the shortcodes found. Nothing is written.
3. **Update.** Select **Update All Found Shortcodes** and confirm the prompt. Each location is rewritten, and the **Notes** column says `Updated`.
4. **Check untranslated IDs.** When a location's note lists IDs with `have no matching People post yet (not migrated) - left as-is, double check by hand.`, those IDs still point at Team Showcase entries. Migrate the entries and run the update again, or edit the shortcode by hand.
5. **Check content the scan cannot see.** The scan reads post content and the classic Text and Custom HTML widgets only. Content stored elsewhere, such as by a page builder or in block widgets, needs its own search.

## Reference

- [Shortcode rewriting](Shortcode-rewriting.md)
