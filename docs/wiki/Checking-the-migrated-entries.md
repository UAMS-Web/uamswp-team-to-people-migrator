Written against v1.3.1.

After a migration run, a few values on the new People Showcase entries are generated or copied without a direct equivalent, and each one is named in the **Notes / Warnings** column of the results table. This guide covers what to review.

1. **Check the sort text.** People Showcase sorts by `_pssorttext`, which Team Showcase does not have. The tool generates it from the title as `Last, First`, taking the last word of the title as the last name. Every row warns about it. Fix any entry whose title ends in a suffix or a credential: a title like `Jane Doe MD` becomes `MD, Jane Doe`.
2. **Check the raw-copied fields.** A Team Showcase field that is not in the default mapping, such as a custom field or a social network field, is copied to the People Showcase entry with its `_ts` prefix changed to `_ps`. The warning names each one. If People Showcase should show the field, add it to the People Showcase **Custom Fields** setting.
3. **Check the Twitter field.** Team Showcase stored a profile URL; People Showcase stores the bare handle. A value like `https://twitter.com/example` becomes `example`.
4. **Check the provider profile URL.** Team Showcase's **Personal Website** is copied to the People Showcase field labeled **Provider Profile URL**. The meaning differs, so review any value that is not a provider profile.
5. **Check new taxonomy terms.** A term is matched to an existing People Showcase term by name. When none exists, a new term is created with the Team Showcase term's slug; the results table marks it `(new)`.

## Reference

- [Field and taxonomy mapping](Field-and-taxonomy-mapping.md)
