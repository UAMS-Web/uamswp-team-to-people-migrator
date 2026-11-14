<?php

/**
 * The data tables and pure helpers in the plugin file, with WordPress mocked.
 *
 * @see uamswp-team-to-people-migrator.php
 */

declare(strict_types=1);

use Brain\Monkey\Functions;

use function Brain\Monkey\Functions\when;

it('maps each People Showcase field to its Team Showcase source and transform', function (): void {
    expect(uamswp_t2p_get_field_map())->toBe([
        '_psposition' => ['_tsposition', null],
        '_psemail' => ['_tsemail', null],
        '_pslocation' => ['_tslocation', null],
        '_pstel' => ['_tstel', null],
        '_psfreehtml' => ['_tsfreehtml', null],
        '_pspersonal' => ['_tspersonal', null],
        '_pspersonalanchor' => ['_tspersonalanchor', null],
        '_psuser' => ['_tsuser', null],
        '_pstwitter' => ['_tstwitter', 'uamswp_t2p_transform_twitter'],
        '_psyoutube' => ['_tsyoutube', null],
    ]);
});

it('names a transform in the field map that exists', function (): void {
    foreach (uamswp_t2p_get_field_map() as [, $transform]) {
        expect($transform === null || function_exists($transform))->toBeTrue();
    }
});

it('lists the source meta keys that are never copied', function (): void {
    expect(uamswp_t2p_get_skip_keys())->toBe([
        '_ts_lastname',
        '_edit_lock',
        '_edit_last',
        '_thumbnail_id',
        '_tshowcase_page_template',
        '_wp_page_template',
    ]);
});

it('maps each Team Showcase taxonomy to its People Showcase twin', function (): void {
    expect(uamswp_t2p_get_taxonomy_map())->toBe([
        'tshowcase-categories' => 'uamspeople-categories',
        'tshowcase-taxonomy' => 'uamspeople-taxonomy',
        'tshowcase-ctaxonomy' => 'uamspeople-ctaxonomy',
        'tshowcase-dtaxonomy' => 'uamspeople-dtaxonomy',
    ]);
});

it('reduces a Twitter value to a handle', function (int|string|null $stored, string $handle): void {
    when('wp_parse_url')->alias('parse_url');

    expect(uamswp_t2p_transform_twitter($stored))->toBe($handle);
})->with([
    'null' => [null, ''],
    'number' => [5, '5'],
    'surrounding spaces' => [' https://twitter.com/uamshealth ', 'uamshealth'],
    'nested path keeps the last segment' => ['https://twitter.com/a/b', 'b'],
    'no path falls back to the value' => ['https://twitter.com', 'https://twitter.com'],
    'root path falls back to the value' => ['https://twitter.com/', 'https://twitter.com/'],
    'bare word' => ['uamshealth', 'uamshealth'],
    'falsy path' => ['0', '0'],
    'at sign with a path' => ['@a/b', 'b'],
    // PHP's parse_url() gives up on a port-like value, so the at sign is trimmed.
    'at sign with an unparseable value' => ['@:80', ':80'],
]);

it('guesses a sort text from the title', function (string $title, string $sorttext): void {
    expect(uamswp_t2p_guess_sorttext($title))->toBe($sorttext);
})->with([
    'one word is returned as given' => ['Cher', 'Cher'],
    'two words' => ['Jane Public', 'Public, Jane'],
    'three words' => ['Jane Q Public', 'Public, Jane Q'],
    'surrounding spaces are trimmed for the split' => ['  Jane Public  ', 'Public, Jane'],
    'a padded single word is returned untrimmed' => [' Cher ', ' Cher '],
]);

it('detects a show-team shortcode', function (string $content, bool $expected): void {
    expect(uamswp_t2p_content_has_shortcode($content))->toBe($expected);
})->with([
    'bare tag' => ['[show-team]', true],
    'with attributes' => ['intro [show-team ids="1"] outro', true],
    'suffixed tag' => ['[show-team-search]', true],
    'closing tag only' => ['[/show-team]', false],
    'no bracket' => ['show-team', false],
    'longer word' => ['[show-teams]', false],
    'already renamed' => ['[show-people]', false],
    'empty' => ['', false],
]);

it('translates ids and exclude lists through the migration map', function (string $content, string $expected, array $untranslated): void {
    $result = uamswp_t2p_translate_id_attributes($content, [1 => 10, 2 => 20], $left);

    expect($result)->toBe($expected)
        ->and($left)->toBe($untranslated);
})->with([
    'a leading zero matches the same id' => ['[show-team ids="01"]', '[show-team ids="10"]', []],
    'ids' => ['[show-team ids="1,2"]', '[show-team ids="10,20"]', []],
    'exclude' => ['[show-team exclude="2"]', '[show-team exclude="20"]', []],
    'single quotes are kept' => ["[show-team ids='1']", "[show-team ids='10']", []],
    'spaces around the list items are removed' => ['[show-team ids="1, 2"]', '[show-team ids="10,20"]', []],
    'spaces around the equals sign are removed' => ['[show-team ids = "1"]', '[show-team ids="10"]', []],
    'an id with no record is left and reported' => ['[show-team ids="1,5"]', '[show-team ids="10,5"]', ['5']],
    'every unmapped id is reported in order' => ['[show-team ids="7,8"]', '[show-team ids="7,8"]', ['7', '8']],
    'an empty item is left empty' => ['[show-team ids="1,,2"]', '[show-team ids="10,,20"]', []],
    'both attributes are translated' => ['[show-team ids="1" exclude="2,9"]', '[show-team ids="10" exclude="20,9"]', ['9']],
    'a longer attribute name is not touched' => ['[show-team noids="1"]', '[show-team noids="1"]', []],
    'other attributes are not touched' => ['[show-team limit="1"]', '[show-team limit="1"]', []],
    'a list with letters is not touched' => ['[show-team ids="1,a"]', '[show-team ids="1,a"]', []],
    'mismatched quotes are not touched' => ['[show-team ids="1\']', '[show-team ids="1\']', []],
    'no attribute' => ['[show-team]', '[show-team]', []],
]);

it('starts each translation with an empty untranslated list', function (): void {
    $left = ['stale'];

    uamswp_t2p_translate_id_attributes('[show-team]', [], $left);

    expect($left)->toBe([]);
});

it('collects untranslated ids from every shortcode in the content', function (): void {
    uamswp_t2p_translate_id_attributes('[show-team ids="3"] [show-team exclude="4"]', [], $left);

    expect($left)->toBe(['3', '4']);
});

it('collects the unmapped Team Showcase meta of a post', function (): void {
    when('wp_list_pluck')->alias('array_column');
    Functions\expect('get_post_meta')
        ->once()
        ->with(7)
        ->andReturn([
            '_tsposition' => ['Dean'],
            '_ts_lastname' => ['Public'],
            '_tshowcase_page_template' => ['x'],
            '_edit_lock' => ['1:2'],
            '_wp_page_template' => ['default'],
            '_tsfacebook' => ['https://facebook.com/x'],
            '_tsempty' => [],
            '_tsfirst' => ['one', 'two'],
            'tsplain' => ['no underscore'],
            '_other' => ['not a team field'],
        ]);

    expect(uamswp_t2p_get_unmapped_meta(7))->toBe([
        '_tsfacebook' => 'https://facebook.com/x',
        '_tsempty' => '',
        '_tsfirst' => 'one',
    ]);
});

it('returns no unmapped meta for a post with none', function (): void {
    when('wp_list_pluck')->alias('array_column');
    when('get_post_meta')->justReturn([]);

    expect(uamswp_t2p_get_unmapped_meta(7))->toBe([]);
});

it('returns no unmapped meta when WordPress has no meta array to give', function (): void {
    when('wp_list_pluck')->alias('array_column');
    when('get_post_meta')->justReturn('');

    expect(uamswp_t2p_get_unmapped_meta(7))->toBe([]);
});

it('reads a meta value that is not a list, or whose first entry is not a scalar, as empty', function (): void {
    when('wp_list_pluck')->alias('array_column');
    when('get_post_meta')->justReturn([
        '_tsbare' => 'not a list',
        '_tsnested' => [['an', 'array']],
        '_tsnumber' => [5],
    ]);

    expect(uamswp_t2p_get_unmapped_meta(7))->toBe(['_tsbare' => '', '_tsnested' => '', '_tsnumber' => '5']);
});

it('queries the posts not yet migrated, oldest first', function (): void {
    Functions\expect('get_posts')
        ->once()
        ->with([
            'post_type' => 'tshowcase',
            'post_status' => 'any',
            'posts_per_page' => 25,
            'orderby' => 'ID',
            'order' => 'ASC',
            'meta_query' => [
                [
                    'key' => '_migrated_to_uamspeople_id',
                    'compare' => 'NOT EXISTS',
                ],
            ],
        ])
        ->andReturn(['a', 'b']);

    expect(uamswp_t2p_get_pending_posts(25))->toBe(['a', 'b']);
});

it('queries every post when forced, with no limit by default', function (): void {
    Functions\expect('get_posts')
        ->once()
        ->with([
            'post_type' => 'tshowcase',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'orderby' => 'ID',
            'order' => 'ASC',
        ])
        ->andReturn([]);

    expect(uamswp_t2p_get_pending_posts(-1, true))->toBe([]);
});

it('queries the not-yet-migrated posts with no limit by default', function (): void {
    Functions\expect('get_posts')
        ->once()
        ->with(Mockery::on(fn (array $args): bool => $args['posts_per_page'] === -1 && isset($args['meta_query'])))
        ->andReturn([]);

    expect(uamswp_t2p_get_pending_posts())->toBe([]);
});

// cspell:ignore sorttext uamshealth tshowcase uamspeople ctaxonomy dtaxonomy noids
