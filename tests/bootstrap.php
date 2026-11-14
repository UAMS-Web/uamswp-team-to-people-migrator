<?php

/**
 * Unit bootstrap.
 *
 * The suite runs with no WordPress: WordPress functions are mocked per test
 * with Brain Monkey.
 */

declare(strict_types=1);

require_once __DIR__.'/../vendor/autoload.php';

/*
 * Patchwork first: it can only redefine functions declared in files loaded
 * after it, so loading it before the plugin is what lets a test mock one of the
 * plugin's own functions, not only WordPress'.
 */
require_once __DIR__.'/../vendor/antecedent/patchwork/Patchwork.php';

/*
 * WordPress' WP_Error is not loaded, so the plugin's `new WP_Error(...)` and
 * Brain Monkey's is_wp_error() would hit an undefined class without a stand-in.
 */
require_once __DIR__.'/Support/WP_Error.php';

/*
 * The same for wpdb: the plugin reads the global only when it is one, and the
 * tests substitute subclasses that answer a query from a fixture.
 */
require_once __DIR__.'/Support/wpdb.php';

/*
 * The plugin file opens with `if ( ! defined( 'ABSPATH' ) ) { exit; }`. Define
 * it so the file loads, pointing at a directory that is not a WordPress install.
 */
if (! defined('ABSPATH')) {
    define('ABSPATH', sys_get_temp_dir().'/uamswp-t2p-tests-no-wordpress/');
}

/*
 * The plugin file is not loaded here: it registers hooks at file scope, and
 * Brain Monkey's add_action() exists only inside a test. Each test file loads it
 * from a beforeEach, after Brain Monkey is set up.
 */

// cspell:ignore ABSPATH autoloaded
