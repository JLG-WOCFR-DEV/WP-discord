<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/inc/class-discord-http-connector.php';
require_once dirname(__DIR__, 2) . '/inc/class-discord-stats-fetcher.php';

class Test_Discord_Bot_JLG_Stats_Fetcher extends TestCase {

    public function test_fetch_returns_widget_stats_without_bot_call() {
        $widget_calls = 0;
        $bot_calls    = 0;

        $widget_stats = array(
            'online'      => 5,
            'total'       => 10,
            'has_total'   => true,
            'server_name' => 'Guild',
        );

        $widget_fetcher = function ($options) use (&$widget_calls, $widget_stats) {
            $widget_calls++;
            return $widget_stats;
        };

        $bot_fetcher = function ($options) use (&$bot_calls) {
            $bot_calls++;
            return array('online' => 7);
        };

        $connector = new Discord_Bot_JLG_Http_Connector($widget_fetcher, $bot_fetcher);

        $fetcher = new Discord_Bot_JLG_Stats_Fetcher(
            $connector,
            function ($options) {
                return 'abc123';
            },
            function ($stats) {
                return false;
            },
            function ($widget_stats, $bot_stats) {
                return $widget_stats ?: $bot_stats;
            },
            function ($stats) {
                $stats['normalized'] = true;
                return $stats;
            },
            function ($stats) {
                return is_array($stats);
            }
        );

        $result = $fetcher->fetch(array('server_id' => '123456789'));

        $this->assertSame(1, $widget_calls, 'Widget fetcher should be invoked once.');
        $this->assertSame(0, $bot_calls, 'Bot fetcher should not be invoked when data is complete.');
        $this->assertTrue($result['has_usable_stats']);
        $this->assertFalse($result['bot_called']);
        $this->assertSame('abc123', $result['bot_token']);
        $this->assertArrayHasKey('__bot_token_override', $result['options']);
        $this->assertSame('abc123', $result['options']['__bot_token_override']);
        $this->assertArrayHasKey('normalized', $result['stats']);
    }

    public function test_fetch_triggers_bot_when_widget_incomplete() {
        $widget_calls = 0;
        $bot_calls    = 0;

        $widget_stats = array(
            'online'    => 3,
            'has_total' => false,
        );

        $bot_stats = array(
            'online'     => 4,
            'total'      => 20,
            'has_total'  => true,
            'server_name'=> 'Guild Bot',
        );

        $widget_fetcher = function ($options) use (&$widget_calls, $widget_stats) {
            $widget_calls++;
            return $widget_stats;
        };

        $bot_fetcher = function ($options) use (&$bot_calls, $bot_stats) {
            $bot_calls++;
            return $bot_stats;
        };

        $connector = new Discord_Bot_JLG_Http_Connector($widget_fetcher, $bot_fetcher);

        $fetcher = new Discord_Bot_JLG_Stats_Fetcher(
            $connector,
            function ($options) {
                return 'token_xyz';
            },
            function ($stats) {
                return true;
            },
            function ($widget_stats, $bot_stats, $widget_incomplete) {
                $this->assertTrue($widget_incomplete);
                return $bot_stats;
            },
            function ($stats) {
                return $stats;
            },
            function ($stats) {
                return is_array($stats);
            }
        );

        $result = $fetcher->fetch(array('server_id' => '987654321'));

        $this->assertSame(1, $widget_calls, 'Widget fetcher should run once.');
        $this->assertSame(1, $bot_calls, 'Bot fetcher should be invoked when widget is incomplete.');
        $this->assertTrue($result['bot_called']);
        $this->assertTrue($result['has_usable_stats']);
        $this->assertSame('token_xyz', $result['bot_token']);
        $this->assertSame($bot_stats, $result['stats']);
    }

    public function test_fetch_reports_unusable_stats_when_merge_fails() {
        $connector = new Discord_Bot_JLG_Http_Connector(
            function () {
                return array();
            },
            function () {
                return array();
            }
        );

        $fetcher = new Discord_Bot_JLG_Stats_Fetcher(
            $connector,
            function () {
                return '';
            },
            function ($stats) {
                return true;
            },
            function () {
                return null;
            },
            function ($stats) {
                return $stats;
            },
            function ($stats) {
                return false;
            }
        );

        $result = $fetcher->fetch(array());

        $this->assertFalse($result['has_usable_stats']);
        $this->assertNull($result['stats']);
        $this->assertFalse($result['bot_called']);
        $this->assertSame('', $result['bot_token']);
        $this->assertArrayHasKey('__bot_token_override', $result['options']);
        $this->assertSame('', $result['options']['__bot_token_override']);
    }
}
