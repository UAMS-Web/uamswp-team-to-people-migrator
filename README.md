# UAMSWP Team to People Migrator

One-time migration tool that copies Team Showcase (`tshowcase`) entries into People Showcase (`uamspeople`) on the same site, then rewrites `[show-team...]` shortcodes to `[show-people...]`. Run it from Tools, Team, People Migration.

The [wiki](https://github.com/UAMS-Web/uamswp-team-to-people-migrator/wiki) has the walkthrough, the field and shortcode reference, and troubleshooting.

## Requirements and installation

- WordPress 6.8 or later, and PHP 8.2 or later.
- Team Showcase and People Showcase, both active on the same site.
- An administrator account (the tool needs the `manage_options` capability).

Install the plugin from its release download, activate it, and open Tools, Team, People Migration. The wiki's [Migrating a site](https://github.com/UAMS-Web/uamswp-team-to-people-migrator/wiki/Migrating-a-site) page takes it from there.

## Tests

The suite is Pest 5 with Brain Monkey: WordPress functions are mocked, so it needs no database and no WordPress checkout. It runs on PHP 8.4 (`config.platform.php` in `composer.json`).

```bash
composer install
vendor/bin/pest
```

## Code quality

Rector, PHPStan (level 10) and Pint run through `composer test:refactor`, `composer stan` and `composer test:pint`. To make `git blame` skip the Pint reformat commit, run once per clone:

```bash
git config blame.ignoreRevsFile .git-blame-ignore-revs
```

## Credits

Developed and maintained by UAMS Web Services.

Developers:

- Josh Daugherty

## License

GPL-2.0-or-later. See [`LICENSE`](LICENSE).
