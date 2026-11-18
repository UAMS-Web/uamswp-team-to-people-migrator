Written against v1.3.1.

What each part of a Team Showcase entry becomes on the People Showcase entry the tool creates. The tool page shows the field table under **Field mapping reference**.

## Post fields

The new entry copies the title, content, excerpt, status, menu order, author, and publish date (local and GMT). The featured image is set to the same attachment, so the image file is shared, not duplicated.

## Custom fields

| Team Showcase | People Showcase | Note |
| --- | --- | --- |
| `_tsposition` | `_psposition` | |
| `_tsemail` | `_psemail` | |
| `_tslocation` | `_pslocation` | |
| `_tstel` | `_pstel` | |
| `_tsfreehtml` | `_psfreehtml` | A text area in Team Showcase, a visual editor in People Showcase; the value copies unchanged. |
| `_tspersonal` | `_pspersonal` | Labeled **Provider Profile URL** in People Showcase. |
| `_tspersonalanchor` | `_pspersonalanchor` | |
| `_tsuser` | `_psuser` | |
| `_tstwitter` | `_pstwitter` | A profile URL is reduced to the bare handle. |
| `_tsyoutube` | `_psyoutube` | |
| (generated from the title) | `_pssorttext` | `Last, First`, from the last word of the title. |

An empty source field is not copied.

**Other `_ts` fields** (custom fields, social network fields, `_tsemailico`) are copied with the prefix changed to `_ps`, so `_tsfacebook` becomes `_psfacebook`, and each one is named in the row's warnings. People Showcase turns its social network fields off by default, so these values are kept in case they are turned back on.

**Never copied:** `_ts_lastname` (People Showcase regenerates its own on save), `_edit_lock`, `_edit_last`, `_thumbnail_id` (the featured image is set separately), `_tshowcase_page_template` and `_wp_page_template`.

## Taxonomies

| Team Showcase | People Showcase |
| --- | --- |
| `tshowcase-categories` | `uamspeople-categories` |
| `tshowcase-taxonomy` | `uamspeople-taxonomy` |
| `tshowcase-ctaxonomy` | `uamspeople-ctaxonomy` |
| `tshowcase-dtaxonomy` | `uamspeople-dtaxonomy` |

A pair is skipped when either taxonomy is not registered on the site. Terms are matched by name; a missing term is created with the source term's slug.

## Migration flags

| Meta key | Stored on | Value |
| --- | --- | --- |
| `_migrated_to_uamspeople_id` | the Team Showcase entry | the new People Showcase post ID |
| `_migrated_from_tshowcase_id` | the People Showcase entry | the Team Showcase post ID |

An entry with `_migrated_to_uamspeople_id` is no longer pending, so a later run skips it. **Force re-migrate** includes flagged entries and creates a second People Showcase entry for each one. The shortcode update reads `_migrated_from_tshowcase_id` to translate post IDs.

## Batches

Each preview or run processes at most 100 pending entries, oldest post ID first.
