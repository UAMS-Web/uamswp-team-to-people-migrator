# UAMSWP Team to People Migrator

One-time migration tool that copies Team Showcase (`tshowcase`) entries into People Showcase (`uamspeople`) on the same site, then rewrites `[show-team...]` shortcodes to `[show-people...]`. Run it from Tools, Team, People Migration.

## Tests

The suite is Pest 5 with Brain Monkey: WordPress functions are mocked, so it needs no database and no WordPress checkout. It runs on PHP 8.4 (`config.platform.php` in `composer.json`).

```bash
composer install
vendor/bin/pest
```

## Credits

Developed and maintained by UAMS Web Services.

Developers:

- Josh Daugherty
