<?php
$_tests_dir = getenv('WP_TESTS_DIR');

if (!$_tests_dir) {
    $_tests_dir = rtrim(sys_get_temp_dir(), '/\\') . '/wordpress-tests-lib';
}

if (!file_exists($_tests_dir . '/includes/functions.php')) {
    require_once dirname(__DIR__) . '/bootstrap.php';

    if (!class_exists('WP_UnitTestCase')) {
        class WP_UnitTestCase extends \PHPUnit\Framework\TestCase {
            public function setUp(): void {
                parent::setUp();
            }

            public function tearDown(): void {
                parent::tearDown();
            }
        }
    }

    return;
}

require_once $_tests_dir . '/includes/functions.php';

function _discord_bot_jlg_tests_load_plugin() {
    require dirname(dirname(dirname(__DIR__))) . '/discord-bot-jlg.php';
}
tests_add_filter('muplugins_loaded', '_discord_bot_jlg_tests_load_plugin');

require $_tests_dir . '/includes/bootstrap.php';
