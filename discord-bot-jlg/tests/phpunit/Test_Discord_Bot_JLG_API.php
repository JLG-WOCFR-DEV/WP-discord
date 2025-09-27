<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/bootstrap.php';

if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response) {
        if (is_array($response) && isset($response['response']['code'])) {
            return (int) $response['response']['code'];
        }

        return 0;
    }
}

if (!function_exists('wp_remote_retrieve_response_message')) {
    function wp_remote_retrieve_response_message($response) {
        if (is_array($response) && isset($response['response']['message'])) {
            return (string) $response['response']['message'];
        }

        return '';
    }
}

if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response) {
        if (is_array($response) && isset($response['body'])) {
            return $response['body'];
        }

        return '';
    }
}

if (!function_exists('wp_remote_retrieve_header')) {
    function wp_remote_retrieve_header($response, $header) {
        if (!is_array($response) || empty($response['headers']) || !is_array($response['headers'])) {
            return '';
        }

        $header = strtolower((string) $header);

        foreach ($response['headers'] as $name => $value) {
            if (strtolower((string) $name) === $header) {
                return $value;
            }
        }

        return '';
    }
}

class Mock_Discord_Bot_JLG_Http_Client extends Discord_Bot_JLG_Http_Client {
    public $call_count = 0;

    public function get($url, array $args = array(), $context = '') {
        $this->call_count++;
        return new WP_Error('http_error', 'Simulated failure');
    }
}

class Successful_Mock_Discord_Bot_JLG_Http_Client extends Discord_Bot_JLG_Http_Client {
    public $requests = array();
    private $widget_payload;
    private $bot_payload;

    public function __construct(array $widget_payload, array $bot_payload) {
        $this->widget_payload = $widget_payload;
        $this->bot_payload    = $bot_payload;
    }

    public function get($url, array $args = array(), $context = '') {
        $this->requests[] = array(
            'url'     => $url,
            'args'    => $args,
            'context' => $context,
        );

        if ('widget' === $context) {
            return array(
                'response' => array(
                    'code'    => 200,
                    'message' => 'OK',
                ),
                'body'    => wp_json_encode($this->widget_payload),
                'headers' => array(),
            );
        }

        if ('bot' === $context) {
            return array(
                'response' => array(
                    'code'    => 200,
                    'message' => 'OK',
                ),
                'body'    => wp_json_encode($this->bot_payload),
                'headers' => array(),
            );
        }

        return new WP_Error('unexpected_context', 'Unexpected context: ' . $context);
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

    public function test_get_stats_stores_successful_payload() {
        $option_name    = 'discord_server_stats_options';
        $cache_key      = 'discord_server_stats_cache';
        $cache_duration = 180;

        $GLOBALS['wp_test_options'][$option_name] = array(
            'server_id'      => '246810',
            'cache_duration' => $cache_duration,
            'bot_token'      => 'token-123',
        );

        $widget_payload = array(
            'presence_count' => 9,
            'name'           => 'Widget Guild',
            'members'        => array(
                array('id' => 1),
                array('id' => 2),
            ),
        );

        $bot_payload = array(
            'approximate_presence_count' => 42,
            'approximate_member_count'   => 120,
            'name'                       => 'Bot Guild',
        );

        $http_client = new Successful_Mock_Discord_Bot_JLG_Http_Client($widget_payload, $bot_payload);
        $api         = new Discord_Bot_JLG_API($option_name, $cache_key, 60, $http_client);

        $stats = $api->get_stats(array('bypass_cache' => true));

        $this->assertCount(2, $http_client->requests, 'Expected widget and bot requests');
        $this->assertSame(9, $stats['online']);
        $this->assertSame(120, $stats['total']);
        $this->assertSame('Widget Guild', $stats['server_name']);
        $this->assertTrue($stats['has_total']);
        $this->assertTrue($stats['total_is_approximate']);
        $this->assertSame('', $api->get_last_error_message());

        $cached_stats = get_transient($cache_key);
        $this->assertSame($stats, $cached_stats);
        $this->assertArrayNotHasKey('fallback_demo', $cached_stats);
        $this->assertArrayNotHasKey('is_demo', $cached_stats);

        $cache_entry = wp_test_get_transient_entry($cache_key);
        $this->assertNotNull($cache_entry);
        $this->assertSame($cache_duration, $cache_entry['ttl']);
        $this->assertSame($stats, $cache_entry['value']);

        $last_good_key   = $cache_key . Discord_Bot_JLG_API::LAST_GOOD_SUFFIX;
        $last_good_entry = wp_test_get_transient_entry($last_good_key);
        $this->assertNotNull($last_good_entry);
        $this->assertSame(0, $last_good_entry['ttl']);
        $this->assertArrayHasKey('stats', $last_good_entry['value']);
        $this->assertArrayHasKey('timestamp', $last_good_entry['value']);
        $this->assertSame($stats, $last_good_entry['value']['stats']);
        $this->assertIsInt($last_good_entry['value']['timestamp']);
        $this->assertGreaterThan(0, $last_good_entry['value']['timestamp']);
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

    public function test_get_demo_stats_respects_wordpress_timezone() {
        $option_name = 'discord_server_stats_options';
        $cache_key   = 'discord_server_stats_cache';

        $api = new Discord_Bot_JLG_API($option_name, $cache_key, 60);

        $previous_timestamp = isset($GLOBALS['wp_test_current_timestamp']) ? $GLOBALS['wp_test_current_timestamp'] : null;
        $previous_timezone  = isset($GLOBALS['wp_test_timezone_string']) ? $GLOBALS['wp_test_timezone_string'] : null;

        $GLOBALS['wp_test_current_timestamp'] = gmmktime(3, 0, 0, 1, 1, 2024); // 03:00 UTC.
        $GLOBALS['wp_test_timezone_string']  = 'Asia/Tokyo'; // UTC+9.

        $stats = $api->get_demo_stats(false);

        $expected_hour       = (int) wp_date('H', $GLOBALS['wp_test_current_timestamp']);
        $expected_variation  = sin($expected_hour * 0.26) * 10;
        $expected_online     = (int) round(42 + $expected_variation);

        $this->assertSame($expected_online, $stats['online']);
        $this->assertTrue($stats['is_demo']);
        $this->assertFalse($stats['fallback_demo']);

        if (null === $previous_timestamp) {
            unset($GLOBALS['wp_test_current_timestamp']);
        } else {
            $GLOBALS['wp_test_current_timestamp'] = $previous_timestamp;
        }

        if (null === $previous_timezone) {
            unset($GLOBALS['wp_test_timezone_string']);
        } else {
            $GLOBALS['wp_test_timezone_string'] = $previous_timezone;
        }
    }
}
