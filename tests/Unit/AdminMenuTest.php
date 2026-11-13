<?php

/**
 * What the plugin file does when it loads, and its admin menu entry.
 *
 * The plugin file is loaded once per process, by the first test that runs (see
 * tests/Pest.php), and only a test that executes the file-scope code covers it.
 * This file sorts before the other Unit test files, and its first test is the
 * one that checks everything the file-scope code does (the hook and the
 * constants), so a mutation run that changes that code reaches it. If another
 * file ever sorts first, the file-scope mutants show up as untested rather than
 * passing silently.
 *
 * @see uamswp-team-to-people-migrator.php
 */

declare(strict_types=1);

use Brain\Monkey\Functions;

use function Brain\Monkey\Functions\when;

it('registers the admin menu callback and defines its constants when the file loads', function (): void {
    expect($GLOBALS['uamswp_t2p_admin_menu_priority'])->toBe(10)
        ->and(UAMSWP_T2P_SRC_CPT)->toBe('tshowcase')
        ->and(UAMSWP_T2P_DEST_CPT)->toBe('uamspeople')
        ->and(UAMSWP_T2P_MIGRATED_FLAG_OLD)->toBe('_migrated_to_uamspeople_id')
        ->and(UAMSWP_T2P_MIGRATED_FLAG_NEW)->toBe('_migrated_from_tshowcase_id')
        ->and(UAMSWP_T2P_BATCH_SIZE)->toBe(100);
});

it('adds the migration page under Tools for administrators', function (): void {
    when('__')->returnArg(1);

    Functions\expect('add_management_page')
        ->once()
        ->with('Team → People Migration', 'Team → People Migration', 'manage_options', 'uamswp-t2p-migrate', 'uamswp_t2p_render_admin_page');

    uamswp_t2p_admin_menu();
});

// cspell:ignore tshowcase uamspeople
