<?php

/**
 * Migrating one Team Showcase post, with WordPress mocked.
 *
 * @see uamswp-team-to-people-migrator.php
 */

declare(strict_types=1);

use Brain\Monkey\Functions;

use function Brain\Monkey\Functions\when;

/**
 * A tshowcase post as get_post() returns it.
 *
 * @param  array<string, mixed>  $overrides
 */
function t2p_post(array $overrides = []): stdClass
{
    return (object) array_merge([
        'post_type' => 'tshowcase',
        'post_title' => 'Jane Public',
        'post_content' => 'Bio',
        'post_excerpt' => 'Short',
        'post_status' => 'publish',
        'menu_order' => 3,
        'post_author' => '4',
        'post_date' => '2026-01-02 03:04:05',
        'post_date_gmt' => '2026-01-02 08:04:05',
    ], $overrides);
}

/**
 * Answers the WordPress reads migrate_post() makes for one source post.
 *
 * @param  array<string, mixed>  $meta  single-value meta, by key
 * @param  array<string, array<int, string>>  $all  every meta row, for the unmapped scan
 */
function t2p_source(array $meta = [], array $all = [], int|string $thumbnail = 0, ?stdClass $post = null): void
{
    when('get_post')->justReturn($post ?? t2p_post());
    when('get_post_thumbnail_id')->justReturn($thumbnail);
    when('wp_list_pluck')->alias('array_column');
    when('wp_parse_url')->alias('parse_url');
    when('taxonomy_exists')->justReturn(false);
    when('get_post_meta')->alias(function (int $id, string $key = '', bool $single = false) use ($meta, $all) {
        if ($key === '') {
            return $all;
        }

        $value = $meta[$key] ?? '';

        // WordPress returns a list unless a single value is asked for.
        return $single ? $value : [$value];
    });
}

/**
 * Records every call to a function.
 *
 * @param  array<int, array<int, mixed>>  $calls
 */
function t2p_spy(string $function, array &$calls, mixed $answer = true): void
{
    when($function)->alias(function (...$args) use (&$calls, $answer) {
        $calls[] = $args;

        return $answer;
    });
}

it('rejects a post that does not exist', function (): void {
    when('get_post')->justReturn();

    $result = uamswp_t2p_migrate_post(5);

    expect($result)->toBeInstanceOf(WP_Error::class)
        ->and($result->get_error_code())->toBe('invalid_post')
        ->and($result->get_error_message())->toBe('Not a tshowcase post.');
});

it('rejects a post of another type', function (): void {
    when('get_post')->justReturn(t2p_post(['post_type' => 'page']));

    expect(uamswp_t2p_migrate_post(5))->toBeInstanceOf(WP_Error::class);
});

it('previews a post without writing anything', function (): void {
    t2p_source(
        meta: ['_tsposition' => 'Dean', '_tsemail' => '', '_tsuser' => false, '_tstwitter' => 'https://twitter.com/jane', '_tstel' => '0'],
        all: ['_tsposition' => ['Dean'], '_tsfacebook' => ['fb'], '_tsblank' => ['']],
        thumbnail: '7',
    );
    Functions\expect('wp_insert_post')->never();
    Functions\expect('update_post_meta')->never();
    Functions\expect('set_post_thumbnail')->never();

    expect(uamswp_t2p_migrate_post(5))->toBe([
        'old_id' => 5,
        'title' => 'Jane Public',
        'new_id' => null,
        'meta_mapped' => [
            '_psposition' => 'Dean',
            '_pstel' => '0',
            '_pstwitter' => 'jane',
            '_pssorttext' => 'Public, Jane',
        ],
        'meta_unmapped' => ['_tsfacebook' => 'fb', '_tsblank' => ''],
        'terms' => [],
        'thumbnail' => 7,
        'warnings' => [
            '2 field(s) have no default equivalent in People Showcase and were copied as raw postmeta - review whether they need a matching entry in the People Showcase "Custom Fields" setting: _tsfacebook, _tsblank',
            'Sort Text ("Public, Jane") was auto-generated from the title - worth a quick check for names with suffixes or credentials.',
        ],
    ]);
});

it('previews by default and when asked', function (): void {
    t2p_source();
    Functions\expect('wp_insert_post')->never();

    expect(uamswp_t2p_migrate_post(5))->toHaveKey('new_id', null)
        ->and(uamswp_t2p_migrate_post(5, true))->toHaveKey('new_id', null);
});

it('warns only about the sort text when there is no unmapped meta', function (): void {
    t2p_source();

    expect(uamswp_t2p_migrate_post(5)['warnings'])->toHaveCount(1);
});

it('creates the People post and copies everything over', function (): void {
    t2p_source(
        meta: ['_tsposition' => 'Dean', '_tstwitter' => 'https://twitter.com/jane'],
        all: ['_tsblank' => [''], '_tsfacebook' => ['fb']],
        thumbnail: 7,
    );
    $inserted = [];
    $thumbnails = [];
    $meta = [];
    t2p_spy('wp_insert_post', $inserted, 99);
    t2p_spy('set_post_thumbnail', $thumbnails);
    t2p_spy('update_post_meta', $meta);

    $result = uamswp_t2p_migrate_post(5, false);

    expect($result['new_id'])->toBe(99)
        ->and($inserted)->toBe([[[
            'post_type' => 'uamspeople',
            'post_title' => 'Jane Public',
            'post_content' => 'Bio',
            'post_excerpt' => 'Short',
            'post_status' => 'publish',
            'menu_order' => 3,
            'post_author' => 4,
            'post_date' => '2026-01-02 03:04:05',
            'post_date_gmt' => '2026-01-02 08:04:05',
        ], true]])
        ->and($thumbnails)->toBe([[99, 7]])
        ->and($meta)->toBe([
            [99, '_psposition', 'Dean'],
            [99, '_pstwitter', 'jane'],
            [99, '_pssorttext', 'Public, Jane'],
            [99, '_psfacebook', 'fb'],
            [99, '_migrated_from_tshowcase_id', 5],
            [5, '_migrated_to_uamspeople_id', 99],
        ]);
});

it('sets no thumbnail when the source post has none', function (): void {
    t2p_source();
    when('wp_insert_post')->justReturn(99);
    when('update_post_meta')->justReturn(true);

    Functions\expect('set_post_thumbnail')->never();

    expect(uamswp_t2p_migrate_post(5, false)['thumbnail'])->toBe(0);
});

it('reports a failed insert and writes nothing else', function (): void {
    t2p_source(meta: ['_tsposition' => 'Dean'], thumbnail: 7);
    when('wp_insert_post')->justReturn(new WP_Error('db', 'Could not insert'));
    Functions\expect('set_post_thumbnail')->never();
    Functions\expect('update_post_meta')->never();

    $result = uamswp_t2p_migrate_post(5, false);

    expect($result['new_id'])->toBeNull()
        ->and($result['meta_mapped'])->toBe([])
        ->and($result['warnings'])->toBe(['Failed to create new post: Could not insert']);
});

it('passes the destination post through the term migration', function (): void {
    t2p_source();
    when('wp_insert_post')->justReturn(99);
    when('update_post_meta')->justReturn(true);
    when('taxonomy_exists')->alias(fn (string $tax): bool => in_array($tax, ['tshowcase-categories', 'uamspeople-categories'], true));
    when('wp_get_post_terms')->justReturn([(object) ['name' => 'Old', 'slug' => 'old']]);
    when('get_term_by')->justReturn((object) ['term_id' => 3]);
    Functions\expect('wp_set_post_terms')->once()->with(99, [3], 'uamspeople-categories', false);

    expect(uamswp_t2p_migrate_post(5, false)['terms'])->toBe([
        ['taxonomy' => 'uamspeople-categories', 'name' => 'Old', 'created' => false],
    ]);
});

it('previews the terms without setting them', function (): void {
    t2p_source();
    when('taxonomy_exists')->alias(fn (string $tax): bool => in_array($tax, ['tshowcase-categories', 'uamspeople-categories'], true));
    when('wp_get_post_terms')->justReturn([(object) ['name' => 'New', 'slug' => 'new']]);
    when('get_term_by')->justReturn(false);

    Functions\expect('wp_insert_term')->never();

    expect(uamswp_t2p_migrate_post(5)['terms'])->toBe([
        ['taxonomy' => 'uamspeople-categories', 'name' => 'New', 'created' => true],
    ]);
});

// cspell:ignore tshowcase uamspeople sorttext tstel pstel
