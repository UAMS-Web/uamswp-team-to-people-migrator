<?php

/**
 * Pest configuration, for the Unit suite.
 *
 * The Integration suite is class-based PHPUnit 9.6 under tests/Integration, run
 * from its own Composer setup; Pest never loads it.
 */

declare(strict_types=1);

use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

/*
 * Brain Monkey around every test.
 *
 * setUp() defines WordPress' hook functions and arms Patchwork; tearDown()
 * verifies every expectation the test set (a `->once()` that never happened
 * fails here) and removes every mock, so nothing leaks into the next test.
 *
 * The assertion count: Brain Monkey's expectations are Mockery expectations,
 * which PHPUnit does not count. Without adding them, a test whose only check is
 * `Functions\expect(...)->once()` reports as risky, and failOnRisky fails it.
 */
uses()
    ->beforeEach(function (): void {
        setUp();

        /*
         * Load the plugin file once per process, inside the first test, because
         * Brain Monkey's add_action() exists only while a test runs. What it
         * registered is recorded here, since the hook is gone after tearDown().
         */
        if (! defined('UAMSWP_T2P_SRC_CPT')) {
            $root = dirname(__DIR__);
            require $root.'/uamswp-team-to-people-migrator.php';
            $GLOBALS['uamswp_t2p_admin_menu_priority'] = has_action('admin_menu', 'uamswp_t2p_admin_menu');
        }
    })
    ->afterEach(function (): void {
        $this->addToAssertionCount(Mockery::getContainer()->mockery_getExpectationCount());

        tearDown();
    })
    ->in('Unit');
