<?php

/**
 * The term, post and ID-map migration functions, with WordPress mocked.
 *
 * @see uamswp-team-to-people-migrator.php
 */

declare(strict_types=1);

use Brain\Monkey\Functions;

use function Brain\Monkey\Functions\when;

/**
 * A term object as wp_get_post_terms() returns it.
 */
function t2p_term(string $name, string $slug = ''): stdClass
{
    return (object) ['name' => $name, 'slug' => $slug ?: strtolower($name)];
}

describe('uamswp_t2p_migrate_terms', function (): void {
    it('skips a taxonomy that is missing on either side', function (): void {
        when('taxonomy_exists')->alias(fn (string $tax): bool => in_array($tax, ['tshowcase-categories', 'uamspeople-taxonomy', 'tshowcase-ctaxonomy'], true));
        Functions\expect('wp_get_post_terms')->never();

        expect(uamswp_t2p_migrate_terms(1, 2))->toBe([]);
    });

    it('keeps going after a taxonomy that is missing', function (): void {
        when('taxonomy_exists')->alias(fn (string $tax): bool => $tax !== 'tshowcase-categories');
        when('wp_get_post_terms')->alias(fn (int $id, string $tax): array => $tax === 'tshowcase-taxonomy' ? [t2p_term('Team')] : []);
        when('get_term_by')->justReturn((object) ['term_id' => 4]);
        when('wp_set_post_terms')->justReturn([]);

        expect(array_column(uamswp_t2p_migrate_terms(1, 2), 'taxonomy'))->toBe(['uamspeople-taxonomy']);
    });

    it('skips a taxonomy whose terms are empty or an error', function (): void {
        when('taxonomy_exists')->justReturn(true);
        $answers = [[], new WP_Error('bad'), [], new WP_Error('bad')];
        when('wp_get_post_terms')->alias(function () use (&$answers): WP_Error|array|null {
            return array_shift($answers);
        });
        Functions\expect('get_term_by')->never();
        Functions\expect('wp_set_post_terms')->never();

        expect(uamswp_t2p_migrate_terms(1, 2))->toBe([]);
    });

    it('reuses a destination term of the same name', function (): void {
        when('taxonomy_exists')->alias(fn (string $tax): bool => in_array($tax, ['tshowcase-categories', 'uamspeople-categories'], true));
        Functions\expect('wp_get_post_terms')->once()->with(1, 'tshowcase-categories', ['fields' => 'all'])->andReturn([t2p_term('Cardiology')]);
        Functions\expect('get_term_by')->once()->with('name', 'Cardiology', 'uamspeople-categories')->andReturn((object) ['term_id' => '12']);
        Functions\expect('wp_insert_term')->never();
        Functions\expect('wp_set_post_terms')->once()->with(2, Mockery::mustBe([12]), 'uamspeople-categories', false);

        expect(uamswp_t2p_migrate_terms(1, 2))->toBe([
            ['taxonomy' => 'uamspeople-categories', 'name' => 'Cardiology', 'created' => false],
        ]);
    });

    it('creates a destination term that does not exist yet', function (): void {
        when('taxonomy_exists')->justReturn(true);
        when('wp_get_post_terms')->alias(fn (int $id, string $tax): array => $tax === 'tshowcase-taxonomy' ? [t2p_term('Heart Team', 'heart-team')] : []);
        when('get_term_by')->justReturn(false);
        Functions\expect('wp_insert_term')->once()->with('Heart Team', 'uamspeople-taxonomy', ['slug' => 'heart-team'])->andReturn(['term_id' => '9']);
        Functions\expect('wp_set_post_terms')->once()->with(2, Mockery::mustBe([9]), 'uamspeople-taxonomy', false);

        expect(uamswp_t2p_migrate_terms(1, 2))->toBe([
            ['taxonomy' => 'uamspeople-taxonomy', 'name' => 'Heart Team', 'created' => true],
        ]);
    });

    it('drops a term that could not be created and sets nothing when none remain', function (): void {
        when('taxonomy_exists')->justReturn(true);
        when('wp_get_post_terms')->alias(fn (int $id, string $tax): array => $tax === 'tshowcase-categories' ? [t2p_term('Heart')] : []);
        when('get_term_by')->justReturn(false);
        when('wp_insert_term')->justReturn(new WP_Error('term_exists'));

        Functions\expect('wp_set_post_terms')->never();

        expect(uamswp_t2p_migrate_terms(1, 2))->toBe([
            ['taxonomy' => 'uamspeople-categories', 'name' => 'Heart', 'created' => true],
        ]);
    });

    it('sets the terms that did resolve when another could not be created', function (): void {
        when('taxonomy_exists')->justReturn(true);
        when('wp_get_post_terms')->alias(fn (int $id, string $tax): array => $tax === 'tshowcase-categories' ? [t2p_term('Old'), t2p_term('Broken'), t2p_term('Fresh')] : []);
        when('get_term_by')->alias(fn (string $field, string $name): stdClass|false => $name === 'Old' ? (object) ['term_id' => 3] : false);
        when('wp_insert_term')->alias(fn (string $name): WP_Error|array => $name === 'Broken' ? new WP_Error('x') : ['term_id' => 8]);
        Functions\expect('wp_set_post_terms')->once()->with(2, Mockery::mustBe([3, 8]), 'uamspeople-categories', false);

        expect(array_column(uamswp_t2p_migrate_terms(1, 2), 'created', 'name'))->toBe(['Old' => false, 'Broken' => true, 'Fresh' => true]);
    });

    it('reports what a dry run would do without writing', function (): void {
        when('taxonomy_exists')->justReturn(true);
        when('wp_get_post_terms')->alias(fn (int $id, string $tax): array => $tax === 'tshowcase-categories' ? [t2p_term('Old'), t2p_term('Fresh')] : []);
        when('get_term_by')->alias(fn (string $field, string $name): stdClass|false => $name === 'Old' ? (object) ['term_id' => 3] : false);

        Functions\expect('wp_insert_term')->never();
        Functions\expect('wp_set_post_terms')->never();

        expect(uamswp_t2p_migrate_terms(1, 2, true))->toBe([
            ['taxonomy' => 'uamspeople-categories', 'name' => 'Old', 'created' => false],
            ['taxonomy' => 'uamspeople-categories', 'name' => 'Fresh', 'created' => true],
        ]);
    });

    it('collects the report across every taxonomy', function (): void {
        when('taxonomy_exists')->justReturn(true);
        when('wp_get_post_terms')->alias(fn (int $id, string $tax): array => [t2p_term('T-'.$tax)]);
        when('get_term_by')->justReturn((object) ['term_id' => 1]);
        when('wp_set_post_terms')->justReturn([]);

        expect(array_column(uamswp_t2p_migrate_terms(1, 2), 'taxonomy'))->toBe([
            'uamspeople-categories',
            'uamspeople-taxonomy',
            'uamspeople-ctaxonomy',
            'uamspeople-dtaxonomy',
        ]);
    });
});

describe('uamswp_t2p_build_id_map', function (): void {
    afterEach(function (): void {
        unset($GLOBALS['wpdb']);
    });

    it('maps each old post ID to the People post that records it', function (): void {
        $wpdb = new class extends wpdb
        {
            public string $postmeta = 'wp_postmeta';

            /** @var array<int, mixed> */
            public array $prepared = [];

            public function prepare(string $query, mixed ...$args): string
            {
                $this->prepared = [$query, $args[0] ?? null];

                return 'PREPARED';
            }

            /** @return array<int, object> */
            public function get_results(?string $query = null, string $output = 'OBJECT'): array
            {
                return $query === 'PREPARED'
                    ? [(object) ['post_id' => '10', 'meta_value' => '5'], (object) ['post_id' => '11', 'meta_value' => '6']]
                    : [];
            }
        };
        $GLOBALS['wpdb'] = $wpdb;

        expect(uamswp_t2p_build_id_map())->toBe([5 => 10, 6 => 11])
            ->and($wpdb->prepared)->toBe(['SELECT post_id, meta_value FROM wp_postmeta WHERE meta_key = %s', '_migrated_from_tshowcase_id']);
    });

    it('reads a row that lacks a column as zero', function (): void {
        $GLOBALS['wpdb'] = new class extends wpdb
        {
            /** @return array<int, object> */
            public function get_results(?string $query = null, string $output = 'OBJECT'): array
            {
                return [(object) ['post_id' => '10'], (object) ['meta_value' => '7']];
            }
        };

        expect(uamswp_t2p_build_id_map())->toBe([0 => 10, 7 => 0]);
    });

    it('is empty when WordPress has no database object', function (): void {
        $GLOBALS['wpdb'] = new stdClass;

        expect(uamswp_t2p_build_id_map())->toBe([]);
    });

    it('is empty when nothing has been migrated', function (): void {
        $GLOBALS['wpdb'] = new class extends wpdb
        {
            public string $postmeta = 'wp_postmeta';

            public function prepare(string $query, mixed ...$args): string
            {
                return $query;
            }

            /** @return array<int, object> */
            public function get_results(?string $query = null, string $output = 'OBJECT'): array
            {
                return [];
            }
        };

        expect(uamswp_t2p_build_id_map())->toBe([]);
    });
});

// cspell:ignore tshowcase uamspeople ctaxonomy dtaxonomy
