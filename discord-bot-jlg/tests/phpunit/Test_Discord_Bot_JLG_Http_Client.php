<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/bootstrap.php';

class Test_Discord_Bot_JLG_Http_Client extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        $GLOBALS['wp_test_last_remote_request'] = null;
        remove_all_filters('discord_bot_jlg_http_max_bytes');
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
}
