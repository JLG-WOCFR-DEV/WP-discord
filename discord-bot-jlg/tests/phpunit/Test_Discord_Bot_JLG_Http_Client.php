<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/bootstrap.php';

class Test_Discord_Bot_JLG_Http_Client extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        $GLOBALS['wp_test_last_remote_request'] = null;
        $GLOBALS['wp_test_remote_requests'] = array();
        $GLOBALS['wp_test_remote_queue'] = array();
        remove_all_filters('discord_bot_jlg_http_max_bytes');
        remove_all_filters('discord_bot_jlg_http_max_retries');
        remove_all_filters('discord_bot_jlg_http_retry_sleep_seconds');
        remove_all_filters('discord_bot_jlg_http_max_immediate_retry_seconds');
        remove_all_filters('discord_bot_jlg_pre_http_request');
    }

    public function test_get_passes_limit_response_size_argument() {
        $client = new Discord_Bot_JLG_Http_Client();
        $url    = 'https://discord.com/api';

        $response = $client->get($url);

        $this->assertIsArray($response);
        $this->assertIsArray($GLOBALS['wp_test_last_remote_request']);
        $this->assertSame($url, $GLOBALS['wp_test_last_remote_request']['url']);
        $this->assertArrayHasKey('limit_response_size', $GLOBALS['wp_test_last_remote_request']['args']);
        $this->assertSame(1048576, $GLOBALS['wp_test_last_remote_request']['args']['limit_response_size']);
    }

    public function test_filter_can_customize_limit_response_size() {
        add_filter(
            'discord_bot_jlg_http_max_bytes',
            function ($max_bytes, $url, $context) {
                $this->assertSame('https://discord.com/api', $url);
                $this->assertSame('widget', $context);
                return 2048;
            },
            10,
            3
        );

        $client = new Discord_Bot_JLG_Http_Client();
        $url    = 'https://discord.com/api';

        $client->get($url, array(), 'widget');

        $this->assertIsArray($GLOBALS['wp_test_last_remote_request']);
        $this->assertSame(2048, $GLOBALS['wp_test_last_remote_request']['args']['limit_response_size']);
    }

    public function test_get_retries_short_retry_after_rate_limit(): void {
        add_filter('discord_bot_jlg_http_retry_sleep_seconds', static function () {
            return 0;
        });

        $calls = 0;
        add_filter(
            'discord_bot_jlg_pre_http_request',
            static function () use (&$calls) {
                $calls++;

                if (1 === $calls) {
                    return array(
                        'response' => array(
                            'code'    => 429,
                            'message' => 'Too Many Requests',
                        ),
                        'body'    => '',
                        'headers' => array(
                            'Retry-After' => '1',
                        ),
                    );
                }

                return array(
                    'response' => array(
                        'code'    => 200,
                        'message' => 'OK',
                    ),
                    'body'    => '{"ok":true}',
                    'headers' => array(),
                );
            }
        );

        $client = new Discord_Bot_JLG_Http_Client();
        $response = $client->get('https://discord.com/api/guilds/1', array(), 'bot');

        $this->assertSame(2, $calls);
        $this->assertSame(200, $response['response']['code']);
        $this->assertSame('{"ok":true}', $response['body']);
    }

    public function test_get_does_not_retry_long_retry_after(): void {
        add_filter('discord_bot_jlg_http_retry_sleep_seconds', static function () {
            return 0;
        });

        $calls = 0;
        add_filter(
            'discord_bot_jlg_pre_http_request',
            static function () use (&$calls) {
                $calls++;

                return array(
                    'response' => array(
                        'code'    => 429,
                        'message' => 'Too Many Requests',
                    ),
                    'body'    => '',
                    'headers' => array(
                        'Retry-After' => '30',
                    ),
                );
            }
        );

        $client = new Discord_Bot_JLG_Http_Client();
        $response = $client->get('https://discord.com/api/guilds/1', array(), 'bot');

        $this->assertSame(1, $calls);
        $this->assertSame(429, $response['response']['code']);
    }
}
