<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/bootstrap.php';

class Test_Discord_Bot_JLG_Cron extends TestCase {

    protected function setUp(): void {
        parent::setUp();

        remove_all_filters('discord_bot_jlg_cron_interval');
        delete_option(DISCORD_BOT_JLG_OPTION_NAME);
        $GLOBALS['wp_test_options'] = array();
    }

    protected function tearDown(): void {
        remove_all_filters('discord_bot_jlg_cron_interval');
        delete_option(DISCORD_BOT_JLG_OPTION_NAME);

        parent::tearDown();
    }

    public function test_get_cron_interval_returns_default_when_option_missing(): void {
        $this->assertSame(
            DISCORD_BOT_JLG_DEFAULT_CACHE_DURATION,
            discord_bot_jlg_get_cron_interval()
        );
    }

    public function test_get_cron_interval_enforces_minimum_threshold(): void {
        update_option(DISCORD_BOT_JLG_OPTION_NAME, array(
            'cache_duration' => 5,
        ));

        $this->assertSame(60, discord_bot_jlg_get_cron_interval());
    }

    public function test_get_cron_interval_caps_maximum_threshold(): void {
        update_option(DISCORD_BOT_JLG_OPTION_NAME, array(
            'cache_duration' => 7200,
        ));

        $this->assertSame(3600, discord_bot_jlg_get_cron_interval());
    }

    public function test_get_cron_interval_rejects_extreme_filter_values(): void {
        update_option(DISCORD_BOT_JLG_OPTION_NAME, array(
            'cache_duration' => 300,
        ));

        add_filter('discord_bot_jlg_cron_interval', function () {
            return 999999;
        });

        $this->assertSame(3600, discord_bot_jlg_get_cron_interval());
    }

    public function test_register_cron_schedule_uses_sanitized_interval(): void {
        update_option(DISCORD_BOT_JLG_OPTION_NAME, array(
            'cache_duration' => 10,
        ));

        $schedules = discord_bot_jlg_register_cron_schedule(array());

        $this->assertArrayHasKey('discord_bot_jlg_refresh', $schedules);
        $this->assertSame(60, $schedules['discord_bot_jlg_refresh']['interval']);
        $this->assertSame(
            __('Discord Bot JLG cache refresh', 'discord-bot-jlg'),
            $schedules['discord_bot_jlg_refresh']['display']
        );
    }
}

