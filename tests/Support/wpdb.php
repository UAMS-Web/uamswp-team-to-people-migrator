<?php

/**
 * A minimal wpdb for the Unit suite, which never loads WordPress.
 *
 * The plugin only reads the table names and calls prepare() and get_results(),
 * so a test replaces the global `$wpdb` with a subclass that answers those two.
 */

declare(strict_types=1);

class wpdb
{
    public string $posts = 'wp_posts';

    public string $postmeta = 'wp_postmeta';

    /**
     * @param  literal-string  $query
     */
    public function prepare(string $query, mixed ...$args): string
    {
        return $query;
    }

    /**
     * Like WordPress, which answers null for a failed query and, for an output
     * type it does not know, something other than a list.
     *
     * @return array<int, mixed>|object|null
     */
    public function get_results(?string $query = null, string $output = 'OBJECT'): array|object|null
    {
        return [];
    }
}
