<?php

/**
 * The shortcode and meta helpers, with WordPress mocked.
 *
 * @see uamswp-team-to-people-migrator.php
 */

declare(strict_types=1);

use function Brain\Monkey\Functions\when;

it('renames the show-team shortcode tags to their show-people equivalents', function (string $before, string $after): void {
    expect(uamswp_t2p_rename_shortcode_tags($before))->toBe($after);
})->with([
    'bare tag' => ['[show-team]', '[show-people]'],
    'closing tag' => ['[/show-team]', '[/show-people]'],
    'search suffix' => ['[show-team-search]', '[show-people-search]'],
    'users suffix' => ['[show-team-users ids="1"]', '[show-people-users ids="1"]'],
    'count suffix' => ['[show-team-count]', '[show-people-count]'],
    'info suffix' => ['[show-team-info]', '[show-people-info]'],
    'unrelated shortcode' => ['[gallery]', '[gallery]'],
]);

it('reduces a Twitter profile URL to the handle', function (string $stored, string $handle): void {
    when('wp_parse_url')->alias('parse_url');

    expect(uamswp_t2p_transform_twitter($stored))->toBe($handle);
})->with([
    'profile URL' => ['https://twitter.com/uamshealth', 'uamshealth'],
    'trailing slash' => ['https://twitter.com/uamshealth/', 'uamshealth'],
    // Pinned current behavior: a bare handle is a one-segment path, so the
    // ltrim() of '@' is never reached and the at sign is kept.
    'at handle keeps the at sign' => ['@uamshealth', '@uamshealth'],
    'empty' => ['  ', ''],
]);

it('guesses a sort text of last name, then the rest', function (): void {
    expect(uamswp_t2p_guess_sorttext('Jane Q Public'))->toBe('Public, Jane Q')
        ->and(uamswp_t2p_guess_sorttext('Cher'))->toBe('Cher');
});

// cspell:ignore sorttext uamshealth
