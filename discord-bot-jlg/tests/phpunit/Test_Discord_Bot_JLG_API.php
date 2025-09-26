<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/bootstrap.php';

class Mock_Discord_Bot_JLG_Http_Client extends Discord_Bot_JLG_Http_Client {
    public $call_count = 0;

    public function get($url, array $args = array(), $context = '') {
        $this->call_count++;
        return new WP_Error('http_error', 'Simulated failure');
    }
}

class Test_Discord_Bot_JLG_API extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        $GLOBALS['wp_test_transients'] = array();
        $GLOBALS['wp_test_options']    = array();
        $GLOBALS['wp_test_current_action'] = 'wp_ajax_nopriv_refresh_discord_stats';
        $_POST = array();
        $_SERVER = array(
            'REMOTE_ADDR'     => '127.0.0.1',
            'HTTP_USER_AGENT' => 'PHPUnit',
        );
    }

    public function test_get_stats_caches_fallback_payload() {
        $option_name = 'discord_server_stats_options';
        $cache_key   = 'discord_server_stats_cache';

        $GLOBALS['wp_test_options'][$option_name] = array(
            'server_id'      => '123456789',
            'cache_duration' => 120,
        );

        $http_client = new Mock_Discord_Bot_JLG_Http_Client();
        $api         = new Discord_Bot_JLG_API($option_name, $cache_key, 60, $http_client);

        $stats = $api->get_stats(array('bypass_cache' => true));

        $this->assertIsArray($stats);
        $this->assertTrue($stats['is_demo']);
        $this->assertTrue($stats['fallback_demo']);
        $this->assertArrayHasKey('stale', $stats);
        $this->assertTrue($stats['stale']);
        $this->assertArrayHasKey('last_updated', $stats);
        $this->assertIsInt($stats['last_updated']);

        $cached = get_transient($cache_key);
        $this->assertSame($stats, $cached);

        $entry = wp_test_get_transient_entry($cache_key);
        $this->assertNotNull($entry);
        $this->assertSame(120, $entry['ttl']);
    }

    public function test_ajax_refresh_stats_reuses_cached_fallback_until_retry_window() {
        $option_name = 'discord_server_stats_options';
        $cache_key   = 'discord_server_stats_cache';

        $GLOBALS['wp_test_options'][$option_name] = array(
            'server_id'      => '987654321',
            'cache_duration' => 90,
            'bot_token'      => 'token',
        );

        $http_client = new Mock_Discord_Bot_JLG_Http_Client();
        $api         = new Discord_Bot_JLG_API($option_name, $cache_key, 60, $http_client);

        try {
            $api->ajax_refresh_stats();
            $this->fail('Expected fallback response');
        } catch (WP_Send_JSON_Success $response) {
            $payload = $response->data;
            $this->assertTrue($payload['is_demo']);
            $this->assertTrue($payload['fallback_demo']);
        }

        $this->assertSame(2, $http_client->call_count);

        $entry = wp_test_get_transient_entry($cache_key . Discord_Bot_JLG_API::FALLBACK_RETRY_SUFFIX);
        $this->assertNotNull($entry);
        $this->assertGreaterThan(time(), $entry['value']);
        $retry_timestamp = $entry['value'];

        try {
            $api->ajax_refresh_stats();
            $this->fail('Expected cached fallback response');
        } catch (WP_Send_JSON_Success $response) {
            $payload = $response->data;
            $this->assertTrue($payload['is_demo']);
            $this->assertTrue($payload['fallback_demo']);
            $this->assertArrayHasKey('retry_after', $payload);
            $this->assertIsInt($payload['retry_after']);
            $this->assertGreaterThanOrEqual(0, $payload['retry_after']);

            $remaining = max(0, $retry_timestamp - time());
            $this->assertGreaterThanOrEqual(max(0, $remaining - 1), $payload['retry_after']);
            $this->assertLessThanOrEqual($remaining + 1, $payload['retry_after']);
        }

        $this->assertSame(2, $http_client->call_count, 'HTTP client should not be invoked again before retry window expires');
    }
}
