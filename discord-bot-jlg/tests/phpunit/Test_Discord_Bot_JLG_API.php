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

class Stubbed_Discord_Bot_JLG_API extends Discord_Bot_JLG_API {
    private $mock_stats;
    private $has_mock_stats = false;
    private $mock_last_error;
    private $has_mock_last_error = false;

    public function set_mock_stats($stats) {
        $this->mock_stats     = $stats;
        $this->has_mock_stats = true;
    }

    public function set_mock_last_error_message($message) {
        $this->mock_last_error     = $message;
        $this->has_mock_last_error = true;
    }

    public function get_stats($args = array()) {
        if (true === $this->has_mock_stats) {
            return $this->mock_stats;
        }

        return parent::get_stats($args);
    }

    public function get_last_error_message() {
        if (true === $this->has_mock_last_error) {
            return $this->mock_last_error;
        }

        return parent::get_last_error_message();
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
        $this->assertArrayHasKey('presence_count_by_status', $stats);
        $this->assertIsArray($stats['presence_count_by_status']);
        $this->assertArrayHasKey('approximate_member_count', $stats);
        $this->assertArrayHasKey('premium_subscription_count', $stats);
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

    public function test_get_stats_updates_last_fallback_option_and_clears_on_success() {
        $option_name = 'discord_server_stats_options';
        $cache_key   = 'discord_server_stats_cache';

        $GLOBALS['wp_test_options'][$option_name] = array(
            'server_id'      => '555666777',
            'cache_duration' => 90,
        );

        $http_client = new Mock_Discord_Bot_JLG_Http_Client();
        $api         = new Discord_Bot_JLG_API($option_name, $cache_key, 60, $http_client);

        $stats = $api->get_stats(array('bypass_cache' => true));

        $this->assertIsArray($stats);
        $this->assertTrue($stats['is_demo']);
        $this->assertTrue($stats['fallback_demo']);

        $fallback_details = get_option(Discord_Bot_JLG_API::LAST_FALLBACK_OPTION);
        $this->assertIsArray($fallback_details);
        $this->assertArrayHasKey('timestamp', $fallback_details);
        $this->assertArrayHasKey('reason', $fallback_details);
        $this->assertGreaterThan(0, $fallback_details['timestamp']);
        $this->assertNotSame('', trim((string) $fallback_details['reason']));

        $widget_payload = array(
            'presence_count' => 8,
            'name'           => 'Recovered Guild',
            'members'        => array(
                array('id' => 1),
                array('id' => 2),
                array('id' => 3),
            ),
        );

        $bot_payload = array(
            'approximate_presence_count' => 15,
            'approximate_member_count'   => 50,
            'name'                       => 'Recovered Guild',
        );

        $recovery_client = new Successful_Mock_Discord_Bot_JLG_Http_Client($widget_payload, $bot_payload);
        $recovery_api    = new Discord_Bot_JLG_API($option_name, $cache_key, 60, $recovery_client);

        $recovered_stats = $recovery_api->get_stats(array('bypass_cache' => true));

        $this->assertIsArray($recovered_stats);
        $this->assertArrayNotHasKey('is_demo', $recovered_stats);
        $this->assertFalse(get_option(Discord_Bot_JLG_API::LAST_FALLBACK_OPTION));
    }

    public function test_clear_all_cached_data_removes_last_fallback_option() {
        $option_name = 'discord_server_stats_options';
        $cache_key   = 'discord_server_stats_cache';

        update_option(
            Discord_Bot_JLG_API::LAST_FALLBACK_OPTION,
            array(
                'timestamp' => time(),
                'reason'    => 'Test reason',
            )
        );

        $api = new Discord_Bot_JLG_API($option_name, $cache_key, 60);
        $api->clear_all_cached_data();

        $this->assertFalse(get_option(Discord_Bot_JLG_API::LAST_FALLBACK_OPTION));
    }

    public function test_clear_all_cached_data_purges_registered_variants() {
        $option_name = 'discord_server_stats_options';
        $cache_key   = 'discord_server_stats_cache';

        $GLOBALS['wp_test_options'][$option_name] = array(
            'server_id'       => '111222333',
            'cache_duration'  => 120,
            'server_profiles' => array(
                'alt' => array(
                    'key'       => 'alt',
                    'label'     => 'Alt Profile',
                    'server_id' => '999888777',
                ),
            ),
        );

        $api = new Discord_Bot_JLG_API($option_name, $cache_key, 60, new Mock_Discord_Bot_JLG_Http_Client());

        $api->get_stats(
            array(
                'bypass_cache' => true,
                'profile_key'  => 'alt',
            )
        );

        $derived_cache_key = $cache_key . '_' . md5('profile:alt');

        $this->assertIsArray(get_transient($derived_cache_key));

        set_transient($derived_cache_key . Discord_Bot_JLG_API::REFRESH_LOCK_SUFFIX, time(), 60);
        set_transient($derived_cache_key . Discord_Bot_JLG_API::LAST_GOOD_SUFFIX, array('stats' => array('online' => 5)), 0);
        set_transient($derived_cache_key . Discord_Bot_JLG_API::FALLBACK_RETRY_SUFFIX, time() + 60, 60);
        set_transient($derived_cache_key . Discord_Bot_JLG_API::FALLBACK_RETRY_API_DELAY_SUFFIX, 30, 30);

        $client_key  = $derived_cache_key . Discord_Bot_JLG_API::CLIENT_REFRESH_LOCK_PREFIX . 'abc';
        $index_key   = $derived_cache_key . Discord_Bot_JLG_API::CLIENT_REFRESH_LOCK_INDEX_SUFFIX;
        set_transient($client_key, time(), 60);
        set_transient($index_key, array($client_key => time()), 86400);

        $registry_option = Discord_Bot_JLG_API::CACHE_REGISTRY_PREFIX . md5($cache_key);
        $registry        = get_option($registry_option);

        $this->assertIsArray($registry);
        $this->assertContains($derived_cache_key, $registry);

        $api->purge_full_cache();

        $this->assertFalse(get_transient($derived_cache_key));
        $this->assertFalse(get_transient($derived_cache_key . Discord_Bot_JLG_API::REFRESH_LOCK_SUFFIX));
        $this->assertFalse(get_transient($derived_cache_key . Discord_Bot_JLG_API::LAST_GOOD_SUFFIX));
        $this->assertFalse(get_transient($derived_cache_key . Discord_Bot_JLG_API::FALLBACK_RETRY_SUFFIX));
        $this->assertFalse(get_transient($derived_cache_key . Discord_Bot_JLG_API::FALLBACK_RETRY_API_DELAY_SUFFIX));
        $this->assertFalse(get_transient($client_key));
        $this->assertFalse(get_transient($index_key));
        $this->assertFalse(get_option($registry_option));
    }

    public function test_ajax_refresh_stats_returns_retry_after_with_uncached_fallback() {
        $option_name = 'discord_server_stats_options';
        $cache_key   = 'discord_server_stats_cache';

        $GLOBALS['wp_test_options'][$option_name] = array(
            'server_id'      => '987654321',
            'cache_duration' => 45,
        );

        $api = new Stubbed_Discord_Bot_JLG_API($option_name, $cache_key, 30);

        $fallback_stats = array(
            'online'               => 3,
            'total'                => null,
            'server_name'          => 'Fallback Guild',
            'has_total'            => false,
            'total_is_approximate' => true,
            'is_demo'              => true,
            'fallback_demo'        => true,
        );

        $api->set_mock_stats($fallback_stats);

        $payload = null;

        try {
            $api->ajax_refresh_stats();
            $this->fail('Expected WP_Send_JSON_Success to be thrown.');
        } catch (WP_Send_JSON_Success $success) {
            $payload = $success->data;
        }

        $this->assertIsArray($payload);
        $this->assertArrayHasKey('retry_after', $payload);
        $this->assertIsInt($payload['retry_after']);

        $fallback_retry_key = $cache_key . Discord_Bot_JLG_API::FALLBACK_RETRY_SUFFIX;
        $transient_entry    = wp_test_get_transient_entry($fallback_retry_key);

        $this->assertNotNull($transient_entry);
        $this->assertArrayHasKey('value', $transient_entry);

        $expected_retry_after = max(0, (int) $transient_entry['value'] - time());

        $this->assertEqualsWithDelta($expected_retry_after, $payload['retry_after'], 1.0);
    }

    public function test_ajax_refresh_stats_handles_array_force_refresh_input() {
        $option_name = 'discord_server_stats_options';
        $cache_key   = 'discord_server_stats_cache';

        $GLOBALS['wp_test_options'][$option_name] = array(
            'server_id'      => '55555',
            'cache_duration' => 60,
        );

        $api = new Stubbed_Discord_Bot_JLG_API($option_name, $cache_key, 60);
        $api->set_mock_stats(
            array(
                'online'        => 5,
                'total'         => 15,
                'server_name'   => 'Array Input Guild',
                'is_demo'       => false,
                'fallback_demo' => false,
            )
        );

        $GLOBALS['wp_test_current_action'] = 'wp_ajax_refresh_discord_stats';
        $_POST['_ajax_nonce']              = 'valid-nonce';
        $_POST['force_refresh']            = array('1');

        try {
            $api->ajax_refresh_stats();
            $this->fail('Expected WP_Send_JSON_Success to be thrown.');
        } catch (WP_Send_JSON_Success $response) {
            $this->assertIsArray($response->data);
            $this->assertArrayHasKey('online', $response->data);
            $this->assertSame(5, $response->data['online']);
        }
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
                array('id' => 1, 'status' => 'online'),
                array('id' => 2, 'status' => 'online'),
                array('id' => 3, 'status' => 'online'),
                array('id' => 4, 'status' => 'online'),
                array('id' => 5, 'status' => 'idle'),
                array('id' => 6, 'status' => 'idle'),
                array('id' => 7, 'status' => 'dnd'),
                array('id' => 8, 'status' => 'dnd'),
                array('id' => 9, 'status' => 'streaming'),
            ),
        );

        $bot_payload = array(
            'approximate_presence_count' => 42,
            'approximate_member_count'   => 120,
            'name'                       => 'Bot Guild',
            'premium_subscription_count' => 14,
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
        $this->assertSame(120, $stats['approximate_member_count']);
        $this->assertSame(9, $stats['approximate_presence_count']);
        $this->assertSame(14, $stats['premium_subscription_count']);
        $this->assertArrayHasKey('presence_count_by_status', $stats);
        $this->assertSame(
            array(
                'online'    => 4,
                'idle'      => 2,
                'dnd'       => 2,
                'streaming' => 1,
            ),
            $stats['presence_count_by_status']
        );
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

    public function test_get_stats_uses_distinct_cache_key_for_server_override() {
        $option_name = 'discord_server_stats_options';
        $cache_key   = 'discord_server_stats_cache';

        $GLOBALS['wp_test_options'][$option_name] = array(
            'server_id'      => '246810',
            'cache_duration' => 120,
            'bot_token'      => 'token-123',
        );

        $widget_payload = array(
            'presence_count' => 4,
            'name'           => 'Override Guild',
            'members'        => array(
                array('id' => 1, 'status' => 'online'),
                array('id' => 2, 'status' => 'online'),
                array('id' => 3, 'status' => 'idle'),
                array('id' => 4, 'status' => 'dnd'),
            ),
        );

        $bot_payload = array(
            'approximate_presence_count' => 8,
            'approximate_member_count'   => 24,
            'name'                       => 'Override Guild',
            'premium_subscription_count' => 3,
        );

        $http_client = new Successful_Mock_Discord_Bot_JLG_Http_Client($widget_payload, $bot_payload);
        $api         = new Discord_Bot_JLG_API($option_name, $cache_key, 60, $http_client);

        $override_id = '999888777';

        $stats = $api->get_stats(
            array(
                'bypass_cache' => true,
                'server_id'    => $override_id,
            )
        );

        $this->assertIsArray($stats);
        $this->assertSame(2, count($http_client->requests));
        $this->assertSame(24, $stats['approximate_member_count']);
        $this->assertSame(4, $stats['approximate_presence_count']);
        $this->assertSame(3, $stats['premium_subscription_count']);
        $this->assertSame(
            array(
                'online' => 2,
                'idle'   => 1,
                'dnd'    => 1,
            ),
            $stats['presence_count_by_status']
        );

        $signature          = 'server:' . $override_id;
        $override_cache_key = $cache_key . '_' . md5($signature);

        $this->assertArrayHasKey($override_cache_key, $GLOBALS['wp_test_transients']);
        $this->assertArrayNotHasKey($cache_key, $GLOBALS['wp_test_transients']);

        $initial_request_count = count($http_client->requests);

        $cached_stats = $api->get_stats(
            array(
                'server_id' => $override_id,
            )
        );

        $this->assertSame($initial_request_count, count($http_client->requests));
        $this->assertSame($stats, $cached_stats);
    }

    public function test_get_stats_treats_string_force_demo_as_true() {
        $option_name = 'discord_server_stats_options';
        $cache_key   = 'discord_server_stats_cache';

        $http_client = new Mock_Discord_Bot_JLG_Http_Client();
        $api         = new Discord_Bot_JLG_API($option_name, $cache_key, 60, $http_client);

        $stats = $api->get_stats(array('force_demo' => '1'));

        $this->assertIsArray($stats);
        $this->assertTrue($stats['is_demo']);
        $this->assertFalse($stats['fallback_demo']);
        $this->assertSame(0, $http_client->call_count, 'Force demo should bypass remote calls');
    }

    public function test_get_stats_respects_cached_value_when_bypass_cache_falsey_string() {
        $option_name = 'discord_server_stats_options';
        $cache_key   = 'discord_server_stats_cache';

        $cached_stats = array(
            'online'               => 7,
            'total'                => 111,
            'server_name'          => 'Cached Guild',
            'is_demo'              => false,
            'fallback_demo'        => false,
            'has_total'            => true,
            'total_is_approximate' => false,
        );

        set_transient($cache_key, $cached_stats, 60);

        $http_client = new Mock_Discord_Bot_JLG_Http_Client();
        $api         = new Discord_Bot_JLG_API($option_name, $cache_key, 60, $http_client);

        $stats = $api->get_stats(array('bypass_cache' => '0'));

        $this->assertSame($cached_stats, $stats);
        $this->assertSame(0, $http_client->call_count, 'Cached payload should prevent HTTP requests');
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

    public function test_ajax_refresh_stats_hides_diagnostic_for_public_errors() {
        $option_name = 'discord_server_stats_options';
        $cache_key   = 'discord_server_stats_cache';

        $api = new Stubbed_Discord_Bot_JLG_API($option_name, $cache_key, 60);
        $api->set_mock_stats(null);
        $api->set_mock_last_error_message('Detailed diagnostics');

        $GLOBALS['wp_test_current_action'] = 'wp_ajax_nopriv_refresh_discord_stats';

        try {
            $api->ajax_refresh_stats();
            $this->fail('Expected error response for public request');
        } catch (WP_Send_JSON_Error $response) {
            $this->assertSame(503, $response->status_code);
            $this->assertArrayHasKey('message', $response->data);
            $this->assertArrayHasKey('retry_after', $response->data);
            $this->assertArrayNotHasKey(
                'diagnostic',
                $response->data,
                'Public responses should not expose diagnostic information'
            );
        }
    }

    public function test_ajax_refresh_stats_exposes_diagnostic_for_authenticated_errors() {
        $option_name = 'discord_server_stats_options';
        $cache_key   = 'discord_server_stats_cache';

        $api = new Stubbed_Discord_Bot_JLG_API($option_name, $cache_key, 60);
        $api->set_mock_stats(null);
        $api->set_mock_last_error_message('Administrative diagnostics');

        $GLOBALS['wp_test_current_action'] = 'wp_ajax_refresh_discord_stats';
        $_POST['_ajax_nonce'] = 'valid-nonce';

        try {
            $api->ajax_refresh_stats();
            $this->fail('Expected error response for authenticated request');
        } catch (WP_Send_JSON_Error $response) {
            $this->assertNull($response->status_code);
            $this->assertArrayHasKey('retry_after', $response->data);
            $this->assertArrayHasKey(
                'diagnostic',
                $response->data,
                'Authenticated responses should include diagnostic information'
            );
            $this->assertSame('Administrative diagnostics', $response->data['diagnostic']);
        }
    }

    public function test_public_request_ip_ignores_untrusted_headers() {
        $option_name = 'discord_server_stats_options';
        $cache_key   = 'discord_server_stats_cache';

        $api = new Discord_Bot_JLG_API($option_name, $cache_key, 60);

        $server_vars = array(
            'REMOTE_ADDR'            => '198.51.100.23',
            'HTTP_X_FORWARDED_FOR'   => '203.0.113.5',
            'HTTP_X_CLUSTER_CLIENT_IP' => '192.0.2.1',
        );

        $reflection = new ReflectionClass($api);
        $method     = $reflection->getMethod('get_public_request_ip');
        $method->setAccessible(true);

        $ip = $method->invoke($api, $server_vars);

        $this->assertSame('198.51.100.23', $ip, 'Untrusted proxy headers should be ignored by default');
    }

    public function test_get_demo_stats_uses_date_i18n_when_wp_date_unavailable() {
        $option_name = 'discord_server_stats_options';
        $cache_key   = 'discord_server_stats_cache';

        $api = new Discord_Bot_JLG_API($option_name, $cache_key, 60);

        $GLOBALS['discord_bot_jlg_disable_wp_date'] = true;

        $captured_args = null;
        $callback      = function($formatted, $format, $timestamp) use (&$captured_args) {
            $captured_args = array(
                'formatted' => $formatted,
                'format'    => $format,
                'timestamp' => $timestamp,
            );

            return $formatted;
        };

        add_filter('date_i18n', $callback, 10, 3);

        try {
            $stats = $api->get_demo_stats(false);
        } finally {
            remove_filter('date_i18n', $callback, 10);
            unset($GLOBALS['discord_bot_jlg_disable_wp_date']);
        }

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('online', $stats);
        $this->assertNotNull($captured_args);
        $this->assertSame('H', $captured_args['format']);
    }
}
