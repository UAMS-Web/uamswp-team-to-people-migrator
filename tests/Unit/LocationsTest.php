<?php

/**
 * Finding and rewriting [show-team] shortcodes in posts and widgets, with WordPress mocked.
 *
 * @see uamswp-team-to-people-migrator.php
 */

declare(strict_types=1);

use Brain\Monkey\Functions;

use function Brain\Monkey\Functions\when;

/**
 * Installs a $wpdb stand-in whose posts query returns the given rows.
 *
 * @param  array<int, object>  $rows
 */
function t2p_wpdb(array $rows): void
{
    $GLOBALS['wpdb'] = new class($rows)
    {
        public string $posts = 'wp_posts';

        public string $postmeta = 'wp_postmeta';

        /** @var array<int, string> */
        public array $queries = [];

        /**
         * @param  array<int, object>  $rows
         */
        public function __construct(private readonly array $rows) {}

        public function prepare(string $query, string $flag): string
        {
            return $query;
        }

        /** @return array<int, object> */
        public function get_results(string $query): array
        {
            $this->queries[] = $query;

            return str_contains($query, 'post_content') ? $this->rows : [];
        }
    };
}

function t2p_row(int|string $id, string $content, string $title = 'A page', string $type = 'page'): object
{
    return (object) ['ID' => $id, 'post_title' => $title, 'post_type' => $type, 'post_content' => $content];
}

afterEach(function (): void {
    unset($GLOBALS['wpdb']);
});

describe('uamswp_t2p_scan_shortcode_locations', function (): void {
    it('lists posts that carry the shortcode and skips incidental mentions', function (): void {
        t2p_wpdb([
            t2p_row(13, 'we use the show-team plugin'),
            t2p_row('12', 'a [show-team] b [show-team-search placeholder="x"] c', 'Team page', 'page'),
        ]);
        when('get_option')->justReturn(false);
        when('get_edit_post_link')->alias(fn (int|string $id): string => "edit/{$id}");

        $found = uamswp_t2p_scan_shortcode_locations();

        expect($found)->toBe([[
            'type' => 'post',
            'id' => 12,
            'title' => 'Team page',
            'post_type' => 'page',
            'edit_link' => 'edit/12',
            'matches' => ['[show-team]', '[show-team-search placeholder="x"]'],
        ]])
            ->and($GLOBALS['wpdb']->queries)->toHaveCount(1)
            ->and($GLOBALS['wpdb']->queries[0])->toContain("post_status != 'trash'")
            ->and($GLOBALS['wpdb']->queries[0])->toContain('wp_posts');
    });

    it('lists widgets from both widget options with their edit link', function (): void {
        t2p_wpdb([]);
        when('get_option')->alias(fn (string $name): mixed => match ($name) {
            'widget_text' => ['_multiwidget' => 1, 2 => 'junk', 4 => ['text' => 'no tag'], 3 => ['text' => 'hi [show-team ids="1"]', 'title' => 'T']],
            'widget_custom_html' => [7 => ['content' => '<p>[show-team]</p>', 'text' => 'ignored'], 8 => ['content' => ['array']]],
            default => false,
        });
        when('admin_url')->alias(fn (string $path): string => "admin/{$path}");

        $found = uamswp_t2p_scan_shortcode_locations();

        expect($found)->toBe([
            [
                'type' => 'widget',
                'id' => 'widget_text:3',
                'title' => 'widget_text widget #3',
                'post_type' => '',
                'edit_link' => 'admin/widgets.php',
                'matches' => ['[show-team ids="1"]'],
            ],
            [
                'type' => 'widget',
                'id' => 'widget_custom_html:7',
                'title' => 'widget_custom_html widget #7',
                'post_type' => '',
                'edit_link' => 'admin/widgets.php',
                'matches' => ['[show-team]'],
            ],
        ]);
    });

    it('keeps going when the first widget option is not stored', function (): void {
        t2p_wpdb([]);
        when('get_option')->alias(fn (string $name): mixed => $name === 'widget_custom_html' ? [1 => ['content' => '[show-team]']] : false);
        when('admin_url')->justReturn('widgets');

        expect(array_column(uamswp_t2p_scan_shortcode_locations(), 'id'))->toBe(['widget_custom_html:1']);
    });

    it('finds nothing when there are no posts and no widgets', function (): void {
        t2p_wpdb([]);
        when('get_option')->justReturn([]);

        expect(uamswp_t2p_scan_shortcode_locations())->toBe([]);
    });

    it('reads a widget that has neither content nor text as empty', function (): void {
        t2p_wpdb([]);
        when('get_option')->alias(fn (string $name): mixed => $name === 'widget_text' ? [1 => ['title' => 'T']] : false);

        expect(uamswp_t2p_scan_shortcode_locations())->toBe([]);
    });
});

describe('uamswp_t2p_update_shortcode_locations', function (): void {
    it('rewrites a post in place and reports ids that were not migrated', function (): void {
        $GLOBALS['wpdb'] = new class
        {
            public string $posts = 'wp_posts';

            public string $postmeta = 'wp_postmeta';

            public function prepare(string $query, string $flag): string
            {
                return $query;
            }

            /** @return array<int, object> */
            public function get_results(string $query): array
            {
                return str_contains($query, 'post_content')
                    ? [(object) ['ID' => 12, 'post_title' => 'Team', 'post_type' => 'page', 'post_content' => '[show-team ids="5,9"]']]
                    : [(object) ['post_id' => 50, 'meta_value' => '5']];
            }
        };
        when('get_option')->justReturn(false);
        when('get_edit_post_link')->justReturn('edit');
        when('get_post')->alias(fn (int $id): object => (object) ['post_content' => '[show-team ids="5,9"]']);
        Functions\expect('wp_update_post')->once()->with(Mockery::mustBe(['ID' => 12, 'post_content' => '[show-people ids="50,9"]']));
        Functions\expect('update_option')->never();

        $result = uamswp_t2p_update_shortcode_locations();

        expect($result)->toHaveCount(1)
            ->and($result[0]['updated'])->toBeTrue()
            ->and($result[0]['untranslated_ids'])->toBe(['9'])
            ->and($result[0]['id'])->toBe(12);
    });

    it('rewrites a widget in the field it is stored in', function (string $field, string $other): void {
        $GLOBALS['wpdb'] = new class
        {
            public string $posts = 'wp_posts';

            public string $postmeta = 'wp_postmeta';

            public function prepare(string $query, string $flag): string
            {
                return $query;
            }

            /** @return array<int, object> */
            public function get_results(string $query): array
            {
                return str_contains($query, 'post_content') ? [] : [(object) ['post_id' => 50, 'meta_value' => '5']];
            }
        };
        $stored = ['title' => 'T', $field => '[show-team ids="5"] and [/show-team]'];
        when('get_option')->alias(fn (string $name): mixed => $name === 'widget_text' ? [3 => $stored] : false);
        when('admin_url')->justReturn('widgets');

        Functions\expect('wp_update_post')->never();
        Functions\expect('update_option')->once()->with('widget_text', Mockery::mustBe([3 => ['title' => 'T', $field => '[show-people ids="50"] and [/show-people]']]));

        $result = uamswp_t2p_update_shortcode_locations();

        expect($result[0])->toMatchArray(['updated' => true, 'untranslated_ids' => []]);
    })->with([
        'text field' => ['text', 'content'],
        'content field' => ['content', 'text'],
    ]);

    it('splits the stored id on its first colon only', function (): void {
        $GLOBALS['wpdb'] = new class
        {
            public string $posts = 'wp_posts';

            public string $postmeta = 'wp_postmeta';

            public function prepare(string $query, string $flag): string
            {
                return $query;
            }

            /** @return array<int, object> */
            public function get_results(string $query): array
            {
                return [];
            }
        };
        when('get_option')->alias(fn (string $name): mixed => $name === 'widget_text' ? ['a:b' => ['text' => '[show-team]']] : false);
        when('admin_url')->justReturn('widgets');
        Functions\expect('update_option')->once()->with('widget_text', Mockery::mustBe(['a:b' => ['text' => '[show-people]']]));

        uamswp_t2p_update_shortcode_locations();
    });

    it('leaves a post alone that was deleted since the scan', function (): void {
        t2p_wpdb([t2p_row(12, '[show-team]', 'Team page')]);
        when('get_option')->justReturn(false);
        when('get_edit_post_link')->justReturn('edit');
        when('get_post')->justReturn();

        Functions\expect('wp_update_post')->never();

        $result = uamswp_t2p_update_shortcode_locations();

        expect($result[0])->toMatchArray(['updated' => false, 'untranslated_ids' => []]);
    });

    it('leaves a post alone when the regex engine cannot process its content', function (): void {
        t2p_wpdb([t2p_row(12, '[show-team ids="5"]', 'Team page')]);
        when('get_option')->justReturn(false);
        when('get_edit_post_link')->justReturn('edit');

        $limit = ini_get('pcre.backtrack_limit');
        // The scan has finished by the time the post is read, so only the rewrite meets the limit.
        when('get_post')->alias(function (int $id): object {
            ini_set('pcre.backtrack_limit', '1');

            return (object) ['post_content' => '[show-team ids="5"]'];
        });
        Functions\expect('wp_update_post')->never();

        try {
            $result = uamswp_t2p_update_shortcode_locations();
        } finally {
            ini_set('pcre.backtrack_limit', (string) $limit);
        }

        expect($result[0]['updated'])->toBeFalse();
    });

    it('leaves a widget alone when the regex engine cannot process its content', function (): void {
        t2p_wpdb([]);
        $limit = ini_get('pcre.backtrack_limit');
        $reads = 0;
        // The second read of the option is the rewrite, after the scan has finished.
        when('get_option')->alias(function (string $name) use (&$reads): mixed {
            if ($name !== 'widget_text') {
                return false;
            }

            if (++$reads === 2) {
                ini_set('pcre.backtrack_limit', '1');
            }

            return [3 => ['text' => '[show-team ids="5"]']];
        });
        when('admin_url')->justReturn('widgets');

        Functions\expect('update_option')->never();

        try {
            $result = uamswp_t2p_update_shortcode_locations();
        } finally {
            ini_set('pcre.backtrack_limit', (string) $limit);
        }

        expect($result[0]['updated'])->toBeFalse();
    });

    it('skips a widget that vanished between the scan and the rewrite', function (): void {
        $GLOBALS['wpdb'] = new class
        {
            public string $posts = 'wp_posts';

            public string $postmeta = 'wp_postmeta';

            public function prepare(string $query, string $flag): string
            {
                return $query;
            }

            /** @return array<int, object> */
            public function get_results(string $query): array
            {
                return [];
            }
        };
        $reads = 0;
        when('get_option')->alias(function (string $name) use (&$reads): mixed {
            if ($name !== 'widget_text') {
                return false;
            }

            return ++$reads === 1 ? [3 => ['text' => '[show-team]']] : [];
        });
        when('admin_url')->justReturn('widgets');

        Functions\expect('update_option')->never();

        expect(uamswp_t2p_update_shortcode_locations()[0]['updated'])->toBeTrue();
    });
});

// cspell:ignore wpdb
