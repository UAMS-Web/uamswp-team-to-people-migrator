<?php

/**
 * The admin page markup and the form handling around it, with WordPress mocked.
 *
 * @see uamswp-team-to-people-migrator.php
 */

declare(strict_types=1);

use function Brain\Monkey\Functions\when;

/**
 * Stands in for the WordPress functions the page calls and renders it.
 *
 * Every translated string carries its text domain and every escaped value is
 * wrapped, so a wrong domain or a missing escape shows in the output.
 *
 * @param  array<string, mixed>  $post  the $_POST the request carries
 * @param  array{can?: bool, types?: array<string, bool>, posts?: list<object>, all?: list<object>, results?: array<int, array<string, mixed>|WP_Error>, nonce?: array<string, bool>, scan?: list<array<string, mixed>>, update?: list<array<string, mixed>>, edit_link?: string|null}  $env  what the site answers
 * @param  array<string, mixed>  $log  every call made to the stubs that matter, by name
 *
 * @param-out array{get_posts: list<array<string, mixed>>, nonce: list<array{string, string}>, migrate: list<array{int, bool}>}  $log
 */
function t2p_render_page(array $post = [], array $env = [], array &$log = []): string
{
    $_POST = $post;
    $log = ['get_posts' => [], 'nonce' => [], 'migrate' => []];

    $tr = static fn (string $text, string $domain): string => $domain === 'uamswp-t2p' ? $text : "!!{$text}";
    when('current_user_can')->alias(static fn (string $cap): bool => $cap === 'manage_options' && ($env['can'] ?? true));
    when('post_type_exists')->alias(static fn (string $type): bool => $env['types'][$type] ?? true);
    when('__')->alias($tr);
    when('esc_html__')->alias(static fn (string $text, string $domain): string => 'H('.$tr($text, $domain).')');
    when('esc_html_e')->alias(static function (string $text, string $domain) use ($tr): void {
        echo 'H('.$tr($text, $domain).')';
    });
    when('esc_html')->alias(static fn (int|string $text): string => 'h('.$text.')');
    when('esc_js')->alias(static fn (string $text): string => 'j('.$text.')');
    when('esc_url')->alias(static fn (string $url): string => 'u('.$url.')');
    when('wp_unslash')->alias(static fn (mixed $value): mixed => $value);
    when('sanitize_text_field')->alias(static fn (string $value): string => $value);
    when('wp_nonce_field')->alias(static function (string $action, string $name): void {
        echo "<nonce {$action} {$name}>";
    });
    when('checked')->alias(static function (mixed $checked): void {
        echo $checked ? 'CHECKED' : 'UNCHECKED';
    });
    when('get_edit_post_link')->alias(static fn (int|string $id): ?string => array_key_exists('edit_link', $env) ? $env['edit_link'] : "edit/{$id}");
    when('get_posts')->alias(static function (array $args) use ($env, &$log): array {
        $log['get_posts'][] = $args;

        return $args['posts_per_page'] === -1 ? ($env['all'] ?? $env['posts'] ?? []) : ($env['posts'] ?? []);
    });
    when('check_admin_referer')->alias(static function (string $action, string $name) use ($env, &$log): bool {
        $log['nonce'][] = [$action, $name];

        return $env['nonce'][$action] ?? true;
    });
    when('uamswp_t2p_migrate_post')->alias(static function (int $id, bool $dry) use ($env, &$log): mixed {
        $log['migrate'][] = [$id, $dry];

        return $env['results'][$id] ?? t2p_report(['old_id' => $id]);
    });
    when('uamswp_t2p_scan_shortcode_locations')->justReturn($env['scan'] ?? []);
    when('uamswp_t2p_update_shortcode_locations')->justReturn($env['update'] ?? []);

    ob_start();
    uamswp_t2p_render_admin_page();

    return t2p_flat((string) ob_get_clean());
}

/**
 * Runs of whitespace as one space, so a test names the markup and not its indentation.
 */
function t2p_flat(string $html): string
{
    return trim((string) preg_replace('/\s+/', ' ', $html));
}

/**
 * The page with its whitespace collapsed and one tag per line, so a fixture diff reads.
 */
function t2p_tidy(string $html): string
{
    return str_replace('> <', ">\n<", t2p_flat($html))."\n";
}

/**
 * @return list<object>
 */
function t2p_posts(int ...$ids): array
{
    return array_values(array_map(static fn (int $id): object => (object) ['ID' => $id], $ids));
}

/**
 * One migrate_post() report, with the parts a test does not care about filled in.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function t2p_report(array $overrides = []): array
{
    return array_merge([
        'old_id' => 5,
        'title' => 'Jane',
        'new_id' => null,
        'meta_mapped' => [],
        'meta_unmapped' => [],
        'terms' => [],
        'warnings' => [],
    ], $overrides);
}

afterEach(function (): void {
    $_POST = [];
});

it('renders nothing for a user who may not manage options', function (): void {
    expect(t2p_render_page([], ['can' => false]))->toBe('');
});

it('explains that both plugins are needed when a post type is missing', function (string $missing): void {
    $out = t2p_render_page([], ['types' => [$missing => false]]);

    expect($out)->toBe('<div class="wrap"><h1>H(Team → People Migration)</h1><div class="notice notice-error"><p>H(Both Team Showcase and People Showcase must be active on this site to run the migration.)</p></div></div>');
})->with(['tshowcase', 'uamspeople']);

it('renders the whole page on a plain visit', function (): void {
    expect(t2p_tidy(t2p_render_page()))->toBe((string) file_get_contents(__DIR__.'/../Fixtures/admin-page-default.html'));
});

it('counts every pending post and migrates none on a plain visit', function (): void {
    $log = [];

    $out = t2p_render_page([], ['posts' => t2p_posts(1), 'all' => t2p_posts(1, 2, 3)], $log);

    expect($out)->toContain('<strong>H(Posts still pending migration:)</strong> h(3) </p>')
        ->and($log['migrate'])->toBe([])
        ->and($log['nonce'])->toBe([])
        ->and($log['get_posts'])->toHaveCount(1)
        ->and($log['get_posts'][0]['posts_per_page'])->toBe(-1);
});

it('mentions the batch size only when more posts are pending than one batch holds', function (int $pending, bool $mentioned): void {
    $out = t2p_render_page([], ['all' => t2p_posts(...range(1, $pending))]);

    expect(str_contains($out, 'H((processed in batches of 100 - run again after each batch completes))'))->toBe($mentioned);
})->with([
    'under a batch' => [99, false],
    'exactly a batch' => [100, false],
    'one over' => [101, true],
]);

describe('the migration form', function (): void {
    it('previews one batch of pending posts as a dry run', function (): void {
        $log = [];
        $env = ['posts' => t2p_posts(5, 6), 'all' => t2p_posts(5, 6, 7), 'results' => [5 => t2p_report(), 6 => t2p_report(['old_id' => 6, 'title' => 'Joe'])]];

        $out = t2p_render_page(['uamswp_t2p_action' => 'preview'], $env, $log);

        expect($log['nonce'][0])->toBe(['uamswp_t2p_run', 'uamswp_t2p_nonce'])
            ->and($log['migrate'])->toBe([[5, true], [6, true]])
            ->and($log['get_posts'][0]['posts_per_page'])->toBe(100)
            ->and($log['get_posts'][0]['meta_query'])->toBe([['key' => '_migrated_to_uamspeople_id', 'compare' => 'NOT EXISTS']])
            ->and($out)->toContain('<h2>H(Preview results)</h2>')
            ->and($out)->toContain('<p>H(Processed 2 post(s) in this batch.)</p>');
    });

    it('runs the migration for real', function (): void {
        $log = [];

        $out = t2p_render_page(['uamswp_t2p_action' => 'run'], ['posts' => t2p_posts(5), 'results' => [5 => t2p_report(['new_id' => 9])]], $log);

        expect($log['migrate'])->toBe([[5, false]])
            ->and($out)->toContain('<h2>H(Migration results)</h2>');
    });

    it('does nothing when the nonce check fails', function (): void {
        $log = [];

        $out = t2p_render_page(['uamswp_t2p_action' => 'run'], ['posts' => t2p_posts(5), 'nonce' => ['uamswp_t2p_run' => false]], $log);

        expect($log['migrate'])->toBe([])
            ->and($out)->not->toContain('results');
    });

    it('reads a posted action that is not a string as no action', function (): void {
        $log = [];

        $out = t2p_render_page(['uamswp_t2p_action' => ['preview']], ['posts' => t2p_posts(5)], $log);

        expect($log['migrate'])->toBe([])
            ->and($log['nonce'])->toBe([])
            ->and($out)->not->toContain('results');
    });

    it('only a preview is a dry run, whatever else the action says', function (): void {
        $log = [];

        t2p_render_page(['uamswp_t2p_action' => 'other'], ['posts' => t2p_posts(5)], $log);

        expect($log['migrate'])->toBe([[5, false]]);
    });

    it('includes already migrated posts and keeps the box ticked when forced', function (): void {
        $log = [];

        $out = t2p_render_page(['uamswp_t2p_action' => 'preview', 'uamswp_t2p_force' => '1'], ['posts' => t2p_posts(5)], $log);

        expect($log['get_posts'][0])->not->toHaveKey('meta_query')
            ->and($log['get_posts'][1])->not->toHaveKey('meta_query')
            ->and($out)->toContain('value="1" CHECKED />');
    });
});

describe('the migration results table', function (): void {
    /**
     * @param  array<string, mixed>  $report
     */
    function t2p_results_row(array|WP_Error $report): string
    {
        $out = t2p_render_page(['uamswp_t2p_action' => 'preview'], ['posts' => t2p_posts(5), 'results' => [5 => $report]]);
        if (preg_match('#<tbody>(.*?)</tbody>#', $out, $m) !== 1) {
            throw new RuntimeException('The page has no table body.');
        }

        return trim($m[1]);
    }

    it('shows the column headings', function (): void {
        $out = t2p_render_page(['uamswp_t2p_action' => 'preview'], ['posts' => t2p_posts(5)]);

        expect($out)->toContain('<thead> <tr> <th>H(Team Post)</th> <th>H(People Post)</th> <th>H(Mapped Fields)</th> <th>H(Taxonomy Terms)</th> <th>H(Notes / Warnings)</th> </tr> </thead>');
    });

    it('shows an error as a single full-width row', function (): void {
        expect(t2p_results_row(new WP_Error('invalid_post', 'Not a tshowcase post.')))
            ->toBe('<tr><td colspan="5">h(Not a tshowcase post.)</td></tr>');
    });

    it('renders an empty link for a post the user cannot edit', function (): void {
        $out = t2p_render_page(
            ['uamswp_t2p_action' => 'migrate'],
            ['posts' => t2p_posts(5), 'results' => [5 => t2p_report(['new_id' => 9])], 'edit_link' => null],
        );

        expect($out)->toContain('<a href="u()"> #h(5) - h(Jane) </a>')
            ->and($out)->toContain('<a href="u()">#h(9)</a>');
    });

    it('carries on to the next post after an error row', function (): void {
        $out = t2p_render_page(['uamswp_t2p_action' => 'preview'], ['posts' => t2p_posts(5, 6), 'results' => [5 => new WP_Error('invalid_post', 'Not a tshowcase post.'), 6 => t2p_report(['old_id' => 6])]]);

        expect($out)->toContain('<td colspan="5">h(Not a tshowcase post.)</td></tr> <tr> <td> <a href="u(edit/6)">');
    });

    it('links the source post and says a preview creates nothing', function (): void {
        expect(t2p_results_row(t2p_report()))->toBe(
            '<tr> <td> <a href="u(edit/5)"> #h(5) - h(Jane) </a> </td> <td> <em>H((preview only - not created))</em> </td> <td> h(0) H(mapped) </td> <td> &#8212; </td> <td> &#8212; </td> </tr>'
        );
    });

    it('links the new post, counts the fields, lists the terms and the warnings', function (): void {
        $row = t2p_results_row(t2p_report([
            'new_id' => 9,
            'meta_mapped' => ['a' => 1, 'b' => 2],
            'meta_unmapped' => ['c' => 3],
            'terms' => [['name' => 'Staff', 'created' => false], ['name' => 'Dean', 'created' => true]],
            'warnings' => ['First', 'Second'],
        ]));

        expect($row)->toBe(
            '<tr> <td> <a href="u(edit/5)"> #h(5) - h(Jane) </a> </td> <td> <a href="u(edit/9)">#h(9)</a> </td> <td> h(2) H(mapped) , h(1) H(raw-copied) </td> <td> h(Staff, Dean (new)) </td> <td> <ul style="margin:0;"> <li>h(First)</li> <li>h(Second)</li> </ul> </td> </tr>'
        );
    });

    it('shows one row per processed post', function (): void {
        $out = t2p_render_page(['uamswp_t2p_action' => 'preview'], ['posts' => t2p_posts(5, 6), 'results' => [5 => t2p_report(), 6 => t2p_report(['old_id' => 6])]]);

        expect(substr_count($out, 'H((preview only - not created))'))->toBe(2);
    });

    it('shows no table when nothing was processed', function (): void {
        $out = t2p_render_page(['uamswp_t2p_action' => 'preview'], ['posts' => []]);

        expect($out)->not->toContain('Preview results')
            ->and($out)->not->toContain('Migration results');
    });
});

describe('the shortcode finder', function (): void {
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function t2p_location(array $overrides = []): array
    {
        return array_merge([
            'type' => 'post',
            'id' => 12,
            'title' => 'Team page',
            'post_type' => 'page',
            'edit_link' => 'edit/12',
            'matches' => ['[show-team]', '[show-team-search]'],
        ], $overrides);
    }

    it('scans and lists where the shortcode is used', function (): void {
        $log = [];
        $out = t2p_render_page(['uamswp_t2p_sc_action' => 'scan'], ['scan' => [t2p_location(), t2p_location(['title' => 'Widget', 'edit_link' => '', 'matches' => ['[show-team]']])]], $log);

        expect($log['nonce'])->toBe([['uamswp_t2p_sc_run', 'uamswp_t2p_sc_nonce']])
            ->and($out)->toContain('<h3> H(Scan results) </h3> <p>H(Found shortcodes in 2 location(s).)</p>')
            ->and($out)->toContain('<thead> <tr> <th>H(Location)</th> <th>H(Shortcode(s) Found)</th> <th>H(Notes)</th> </tr> </thead>')
            ->and($out)->toContain('<tr> <td> <a href="u(edit/12)">h(Team page)</a> </td> <td> <code style="display:block;">h([show-team])</code> <code style="display:block;">h([show-team-search])</code> </td> <td> &#8212; </td> </tr>')
            ->and($out)->toContain('<tr> <td> h(Widget) </td> <td> <code style="display:block;">h([show-team])</code> </td> <td> &#8212; </td> </tr>')
            ->and($out)->not->toContain('H(Updated)');
    });

    it('updates and reports what it could not translate', function (): void {
        $out = t2p_render_page(['uamswp_t2p_sc_action' => 'update'], ['update' => [
            t2p_location(['untranslated_ids' => ['7', '8']]),
            t2p_location(['title' => 'Other', 'untranslated_ids' => []]),
        ]]);

        expect($out)->toContain('<h3> H(Update results) </h3>')
            ->and($out)->toContain('H(Updated) <br /><span style="color:#a00;"> H(ID(s) h(7, 8) in ids/exclude have no matching People post yet (not migrated) - left as-is, double check by hand.)')
            ->and(substr_count($out, 'H(Updated)'))->toBe(2);
    });

    it('says so when a scan finds nothing', function (): void {
        $out = t2p_render_page(['uamswp_t2p_sc_action' => 'scan']);

        expect($out)->toContain('<p>H(No show-team shortcodes found.)</p>')
            ->and($out)->not->toContain('Scan results');
    });

    it('stays quiet about the finder until it is used', function (): void {
        expect(t2p_render_page())->not->toContain('No show-team shortcodes found.');
    });

    it('does nothing for an unknown finder action or a failed nonce', function (string $action, ?string $failed_nonce): void {
        $out = t2p_render_page(['uamswp_t2p_sc_action' => $action], [
            'nonce' => $failed_nonce === null ? [] : [$failed_nonce => false],
            'scan' => [t2p_location()],
            'update' => [t2p_location()],
        ]);

        expect($out)->toContain('H(No show-team shortcodes found.)')
            ->and($out)->not->toContain('results');
    })->with([
        'unknown action' => ['other', null],
        'failed nonce' => ['scan', 'uamswp_t2p_sc_run'],
    ]);
});

describe('the field mapping reference', function (): void {
    it('lists every mapped field with its note', function (): void {
        $out = t2p_render_page();

        expect($out)->toContain('<td><code>h(_tstwitter)</code></td> <td><code>h(_pstwitter)</code></td> <td> H(URL converted to bare username) </td>')
            ->and($out)->toContain('<td><code>h(_tspersonal)</code></td> <td><code>h(_pspersonal)</code></td> <td> H(Field relabeled "Provider Profile URL" in People Showcase) </td>')
            ->and($out)->toContain('<td><code>h(_tsposition)</code></td> <td><code>h(_psposition)</code></td> <td> &#8212; </td>');
    });
});

// cspell:ignore tshowcase uamspeople
