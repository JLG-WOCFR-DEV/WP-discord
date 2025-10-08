<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/bootstrap.php';

class Test_Discord_Bot_JLG_Options_Repository extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        $GLOBALS['wp_test_options'] = array();
        $GLOBALS['wp_test_filters'] = array();
    }

    public function test_get_options_applies_filters_on_each_call() {
        $option_name = 'discord_bot_jlg_repository_test';

        $repository = new Discord_Bot_JLG_Options_Repository(
            $option_name,
            function () {
                return array('foo' => 'default');
            }
        );

        update_option($option_name, array('foo' => 'value'));

        add_filter(
            'option_' . $option_name,
            function ($value) {
                $value['foo'] = 'filtered_once';

                return $value;
            },
            10,
            2
        );

        $first = $repository->get_options();

        $this->assertArrayHasKey('foo', $first);
        $this->assertSame('filtered_once', $first['foo']);

        remove_all_filters('option_' . $option_name);

        add_filter(
            'option_' . $option_name,
            function ($value) {
                $value['foo'] = 'filtered_twice';

                return $value;
            },
            10,
            2
        );

        $second = $repository->get_options();

        $this->assertArrayHasKey('foo', $second);
        $this->assertSame('filtered_twice', $second['foo']);
    }

    public function test_get_options_falls_back_to_cached_value_when_filter_returns_non_array() {
        $option_name = 'discord_bot_jlg_repository_fallback';

        $repository = new Discord_Bot_JLG_Options_Repository(
            $option_name,
            function () {
                return array('bar' => 'baz');
            }
        );

        update_option($option_name, array('bar' => 'initial'));

        $baseline = $repository->get_options();
        $this->assertSame('initial', $baseline['bar']);

        add_filter(
            'option_' . $option_name,
            function () {
                return 'not-an-array';
            }
        );

        $filtered = $repository->get_options();

        $this->assertIsArray($filtered);
        $this->assertSame($baseline['bar'], $filtered['bar']);
    }
}
