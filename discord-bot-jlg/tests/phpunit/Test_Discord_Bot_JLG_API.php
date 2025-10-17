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

class Recording_Server_Id_Discord_Bot_JLG_Http_Client extends Discord_Bot_JLG_Http_Client {
    public $requests = array();
    private $error_code;
    private $error_message;

    public function __construct($error_code = 'stubbed_error', $error_message = 'Stubbed HTTP failure') {
        $this->error_code    = $error_code;
        $this->error_message = $error_message;
    }

    public function get($url, array $args = array(), $context = '') {
        $this->requests[] = array(
            'url'     => $url,
            'args'    => $args,
            'context' => $context,
        );

        return new WP_Error($this->error_code, $this->error_message);
    }
}

class Rate_Limited_Discord_Bot_JLG_Http_Client extends Discord_Bot_JLG_Http_Client {
    private $retry_after_header;

    public function __construct($retry_after_header) {
        $this->retry_after_header = $retry_after_header;
    }

    public function get($url, array $args = array(), $context = '') {
        return array(
            'response' => array(
                'code'    => 429,
                'message' => 'Too Many Requests',
            ),
            'body'    => '',
            'headers' => array(
                'Retry-After' => $this->retry_after_header,
            ),
        );
    }
}

class Recording_Discord_Bot_JLG_Analytics extends Discord_Bot_JLG_Analytics {
    public $snapshots = array();
    public $purge_calls = array();

    public function __construct() {
        // Bypass parent initialization to avoid requiring a database connection.
    }

    public function log_snapshot($profile_key, $server_id, array $stats) {
        $this->snapshots[] = array(
            'profile_key' => $profile_key,
            'server_id'   => $server_id,
            'stats'       => $stats,
        );

        return true;
    }

    public function purge_old_entries($retention_days) {
        $this->purge_calls[] = (int) $retention_days;

        return 0;
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

class Recording_Discord_Bot_JLG_API extends Discord_Bot_JLG_API {
    public $recorded_args = array();
    private $mocked_responses = array();

    public function set_mocked_responses(array $responses) {
        $this->mocked_responses = array_values($responses);
    }

    public function get_stats($args = array()) {
        $this->recorded_args[] = $args;

        if (!empty($this->mocked_responses)) {
            return array_shift($this->mocked_responses);
        }

        return array('online' => 1);
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

    public function test_get_status_history_returns_recent_events_for_profile_and_server() {
        $option_name  = 'discord_bot_jlg_history_options';
        $cache_key    = 'discord_bot_jlg_history_cache';
        $event_option = 'discord_bot_jlg_history_log';

        $event_logger = new Discord_Bot_JLG_Event_Logger($event_option, 20, 3600);
        $event_logger->reset();

        $api = new Discord_Bot_JLG_API($option_name, $cache_key, 60, null, null, $event_logger);

        $event_logger->log('discord_http', array(
            'profile_key' => 'default',
            'server_id'   => '123456',
            'channel'     => 'widget',
            'outcome'     => 'http_error',
            'status_code' => 503,
            'reason'      => 'Service indisponible',
            'retry_after' => 12,
        ));

        $event_logger->log('discord_connector', array(
            'profile_key' => 'other',
            'server_id'   => '999',
            'channel'     => 'bot',
            'outcome'     => 'success',
        ));

        $history = $api->get_status_history(
            array(
                'profile_key' => 'default',
                'server_id'   => '123456',
                'limit'       => 5,
            )
        );

        $this->assertNotEmpty($history);
        $this->assertSame('discord_http', $history[0]['type']);
        $this->assertGreaterThan(0, $history[0]['timestamp']);
        $this->assertStringContainsString('API Discord', $history[0]['label']);
        $this->assertStringContainsString('503', $history[0]['label']);
        $this->assertStringContainsString('Service indisponible', $history[0]['reason']);
        $this->assertStringContainsString('12', $history[0]['reason']);

        $history_other = $api->get_status_history(
            array(
                'profile_key' => 'other',
                'server_id'   => '999',
                'limit'       => 5,
            )
        );

        $this->assertCount(1, $history_other);
        $this->assertSame('discord_connector', $history_other[0]['type']);
        $this->assertStringContainsString('Connecteur', $history_other[0]['label']);
    }

    public function test_get_server_profiles_normalizes_entries() {
        $option_name = DISCORD_BOT_JLG_OPTION_NAME;

        $GLOBALS['wp_test_options'][$option_name] = array(
            'server_profiles' => array(
                'First Profile' => array(
                    'key'       => 'Custom Key!!',
                    'label'     => '  My Label  ',
                    'server_id' => ' 123-456 ',
                    'bot_token' => 'secret-token',
                ),
                'Second Profile' => array(
                    'label'     => 'Second Label ',
                    'server_id' => 'abc789',
                    'bot_token' => '',
                ),
                'Fourth Profile' => array(
                    'key'       => '!!!',
                    'label'     => '',
                    'server_id' => '000-999',
                ),
                '   ' => array(
                    'key'       => '   ',
                    'label'     => 'Should not be kept',
                    'server_id' => '111',
                ),
                'invalid' => 'not-an-array',
            ),
        );

        $api = new Discord_Bot_JLG_API($option_name, DISCORD_BOT_JLG_CACHE_KEY);

        $profiles = $api->get_server_profiles(false);

        $this->assertCount(3, $profiles);

        $this->assertArrayHasKey('custom_key', $profiles);
        $this->assertSame('custom_key', $profiles['custom_key']['key']);
        $this->assertSame('My Label', $profiles['custom_key']['label']);
        $this->assertSame('123456', $profiles['custom_key']['server_id']);
        $this->assertTrue($profiles['custom_key']['has_token']);
        $this->assertArrayNotHasKey('bot_token', $profiles['custom_key']);

        $this->assertArrayHasKey('second_profile', $profiles);
        $this->assertSame('second_profile', $profiles['second_profile']['key']);
        $this->assertSame('Second Label', $profiles['second_profile']['label']);
        $this->assertSame('789', $profiles['second_profile']['server_id']);
        $this->assertFalse($profiles['second_profile']['has_token']);

        $this->assertArrayHasKey('fourth_profile', $profiles);
        $this->assertSame('fourth_profile', $profiles['fourth_profile']['key']);
        $this->assertSame('fourth_profile', $profiles['fourth_profile']['label']);
        $this->assertSame('000999', $profiles['fourth_profile']['server_id']);
    }

    public function test_get_stats_with_unknown_profile_returns_demo_and_error() {
        $option_name = DISCORD_BOT_JLG_OPTION_NAME;
        $cache_key   = DISCORD_BOT_JLG_CACHE_KEY;

        $GLOBALS['wp_test_options'][$option_name] = array(
            'server_id'       => '111222',
            'server_profiles' => array(
                'known' => array(
                    'key'       => 'known',
                    'label'     => 'Profil connu',
                    'server_id' => '111222',
                    'bot_token' => 'encrypted-token',
                ),
            ),
        );

        $http_client = new Recording_Server_Id_Discord_Bot_JLG_Http_Client();
        $api         = new Discord_Bot_JLG_API($option_name, $cache_key, 60, $http_client);

        $stats = $api->get_stats(array(
            'profile_key' => 'missing',
            'bypass_cache' => true,
        ));

        $this->assertSame(array(), $http_client->requests, 'HTTP client should not be invoked when profile is unknown');
        $this->assertArrayHasKey('is_demo', $stats);
        $this->assertTrue($stats['is_demo']);
        $this->assertArrayHasKey('fallback_demo', $stats);
        $this->assertTrue($stats['fallback_demo']);
        $this->assertStringContainsString('introuvable', strtolower($api->get_last_error_message()));
    }

    public function test_get_stats_with_server_override_sanitizes_requested_id() {
        $option_name = DISCORD_BOT_JLG_OPTION_NAME;
        $cache_key   = DISCORD_BOT_JLG_CACHE_KEY;

        $GLOBALS['wp_test_options'][$option_name] = array(
            'server_id' => '555999',
        );

        $http_client = new Recording_Server_Id_Discord_Bot_JLG_Http_Client('forced_error', 'Simulated network failure');
        $api         = new Discord_Bot_JLG_API($option_name, $cache_key, 60, $http_client);

        $stats = $api->get_stats(array(
            'server_id'    => 'abc123def',
            'bypass_cache' => true,
        ));

        $this->assertNotEmpty($http_client->requests, 'Widget request should be attempted before falling back');
        $first_request = $http_client->requests[0];
        $this->assertStringContainsString('/123/widget.json', $first_request['url']);
        $this->assertSame('widget', $first_request['context']);
        $this->assertArrayHasKey('is_demo', $stats);
        $this->assertTrue($stats['is_demo']);
        $this->assertTrue($stats['fallback_demo']);
        $this->assertStringContainsString('Simulated network failure', $api->get_last_error_message());
    }

    public function test_get_stats_caches_fallback_payload() {
        $option_name = 'discord_server_stats_options';
        $cache_key   = 'discord_server_stats_cache';

        $GLOBALS['wp_test_options'][$option_name] = array(
            'server_id'      => '123456789',
            'cache_duration' => 120,
        );

        $http_client = new Mock_Discord_Bot_JLG_Http_Client();
        $analytics   = new Recording_Discord_Bot_JLG_Analytics();
        $api         = new Discord_Bot_JLG_API($option_name, $cache_key, 60, $http_client, $analytics);

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

        $this->assertEmpty($analytics->snapshots, 'Fallback stats should not trigger analytics logging.');

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
        $analytics   = new Recording_Discord_Bot_JLG_Analytics();
        $api         = new Discord_Bot_JLG_API($option_name, $cache_key, 60, $http_client, $analytics);

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
        $recovery_api    = new Discord_Bot_JLG_API($option_name, $cache_key, 60, $recovery_client, $analytics);

        $recovered_stats = $recovery_api->get_stats(array('bypass_cache' => true));

        $this->assertIsArray($recovered_stats);
        $this->assertArrayNotHasKey('is_demo', $recovered_stats);
        $this->assertFalse(get_option(Discord_Bot_JLG_API::LAST_FALLBACK_OPTION));
        $this->assertCount(1, $analytics->snapshots, 'Successful stats should trigger analytics logging once.');
        $this->assertSame('default', $analytics->snapshots[0]['profile_key']);
        $this->assertSame('555666777', $analytics->snapshots[0]['server_id']);
    }

    public function test_get_stats_bypass_cache_forces_connector_attempt_when_circuit_open() {
        $option_name = 'discord_server_stats_options';
        $cache_key   = 'discord_server_stats_cache';

        delete_option(Discord_Bot_JLG_API::CONNECTOR_STATE_OPTION);

        $GLOBALS['wp_test_options'][$option_name] = array(
            'server_id'      => '24680',
            'cache_duration' => 30,
        );

        $http_client = new Mock_Discord_Bot_JLG_Http_Client();
        $api         = new Discord_Bot_JLG_API($option_name, $cache_key, 60, $http_client);

        $api->get_stats(array('bypass_cache' => true));

        $this->assertSame(1, $http_client->call_count, 'Initial attempt should hit the network once.');

        $state = get_option(Discord_Bot_JLG_API::CONNECTOR_STATE_OPTION);
        $this->assertIsArray($state);
        $this->assertArrayHasKey('default:widget', $state);
        $widget_state = $state['default:widget'];
        $this->assertGreaterThan(0, $widget_state['open_until']);
        $this->assertTrue($widget_state['last_attempt_was_network']);
        $this->assertSame(1, $widget_state['attempts']);

        $api->get_stats(array('bypass_cache' => false));

        $this->assertSame(1, $http_client->call_count, 'Circuit breaker should short-circuit normal traffic.');

        $state = get_option(Discord_Bot_JLG_API::CONNECTOR_STATE_OPTION);
        $this->assertArrayHasKey('default:widget', $state);
        $widget_state = $state['default:widget'];
        $this->assertFalse($widget_state['last_attempt_was_network']);
        $this->assertSame(2, $widget_state['attempts']);

        $api->get_stats(array('bypass_cache' => true));

        $this->assertSame(2, $http_client->call_count, 'Forced attempt should bypass the open circuit and reach the network.');

        $state = get_option(Discord_Bot_JLG_API::CONNECTOR_STATE_OPTION);
        $this->assertArrayHasKey('default:widget', $state);
        $widget_state = $state['default:widget'];
        $this->assertTrue($widget_state['last_attempt_was_network']);
        $this->assertSame(3, $widget_state['attempts']);
    }

    public function test_get_stats_force_refresh_forces_connector_attempt_when_circuit_open() {
        $option_name = 'discord_server_stats_options';
        $cache_key   = 'discord_server_stats_cache';

        delete_option(Discord_Bot_JLG_API::CONNECTOR_STATE_OPTION);

        $GLOBALS['wp_test_options'][$option_name] = array(
            'server_id'      => '13579',
            'cache_duration' => 45,
        );

        $http_client = new Mock_Discord_Bot_JLG_Http_Client();
        $api         = new Discord_Bot_JLG_API($option_name, $cache_key, 60, $http_client);

        $api->get_stats(array('force_refresh' => true));

        $this->assertSame(1, $http_client->call_count, 'Initial forced refresh should hit the network once.');

        $state = get_option(Discord_Bot_JLG_API::CONNECTOR_STATE_OPTION);
        $this->assertIsArray($state);
        $this->assertArrayHasKey('default:widget', $state);
        $widget_state = $state['default:widget'];
        $this->assertGreaterThan(time(), $widget_state['open_until']);
        $this->assertTrue($widget_state['last_attempt_was_network']);
        $this->assertSame(1, $widget_state['attempts']);

        $api->get_stats(array());

        $this->assertSame(1, $http_client->call_count, 'Circuit breaker should short-circuit normal traffic after the failure.');

        $state = get_option(Discord_Bot_JLG_API::CONNECTOR_STATE_OPTION);
        $this->assertArrayHasKey('default:widget', $state);
        $widget_state = $state['default:widget'];
        $this->assertFalse($widget_state['last_attempt_was_network']);
        $this->assertSame(2, $widget_state['attempts']);

        $api->get_stats(array('force_refresh' => true));

        $this->assertSame(2, $http_client->call_count, 'Forced refresh should bypass the open circuit and reach the network.');

        $state = get_option(Discord_Bot_JLG_API::CONNECTOR_STATE_OPTION);
        $this->assertArrayHasKey('default:widget', $state);
        $widget_state = $state['default:widget'];
        $this->assertTrue($widget_state['last_attempt_was_network']);
        $this->assertSame(3, $widget_state['attempts']);

        delete_option(Discord_Bot_JLG_API::CONNECTOR_STATE_OPTION);
        unset($GLOBALS['wp_test_options'][$option_name]);
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

    /**
     * @dataProvider retry_after_header_provider
     */
    public function test_get_stats_honors_fractional_retry_after_headers($header_value, $expected_seconds) {
        $option_name = 'discord_server_stats_options';
        $cache_key   = 'discord_server_stats_cache';

        $GLOBALS['wp_test_options'][$option_name] = array(
            'server_id'      => '555555555555555555',
            'cache_duration' => 60,
        );

        $http_client = new Rate_Limited_Discord_Bot_JLG_Http_Client($header_value);
        $api         = new Discord_Bot_JLG_API($option_name, $cache_key, 60, $http_client);

        $api->get_stats(array('bypass_cache' => true));

        $reflection = new ReflectionProperty(Discord_Bot_JLG_API::class, 'last_retry_after');
        $reflection->setAccessible(true);

        $this->assertSame($expected_seconds, $reflection->getValue($api));
    }

    public function retry_after_header_provider() {
        return array(
            // Sub-second Retry-After headers should be rounded up to 1 second.
            'fractional-seconds' => array('0.5', 1),
            'fractional-seconds-trimmed' => array(' 0.5 ', 1),
            'milliseconds-suffix' => array('250ms', 1),
            'milliseconds-with-padding' => array(' 250ms ', 1),
            'seconds-with-unit' => array('1.2s', 2),
            'decimal-comma' => array('1,4', 2),
            'uppercase-ms' => array('150MS', 1),
            'plain-integer' => array('42', 42),
            'invalid-header' => array('not-a-delay', 0),
        );
    }

    public function test_process_refresh_request_respects_client_rate_limit() {
        $option_name = 'discord_server_stats_options';
        $cache_key   = 'discord_server_stats_cache';

        $GLOBALS['wp_test_options'][$option_name] = array(
            'server_id'      => '123456789',
            'cache_duration' => 30,
        );

        $api = new Discord_Bot_JLG_API($option_name, $cache_key, 30);

        $reflection = new ReflectionClass($api);
        $method     = $reflection->getMethod('get_client_rate_limit_key');
        $method->setAccessible(true);

        $client_key = $method->invoke($api, true);

        $this->assertNotEmpty($client_key, 'Client rate limit key should not be empty for public requests.');

        $set_at = time();
        set_transient($client_key, $set_at, 30);

        $result = $api->process_refresh_request(array('is_public_request' => true));

        $this->assertFalse($result['success']);
        $this->assertSame(429, $result['status']);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('rate_limited', $result['data']);
        $this->assertTrue($result['data']['rate_limited']);
        $this->assertArrayHasKey('retry_after', $result['data']);
        $this->assertStringContainsString('Veuillez patienter', $result['data']['message']);

        $expected_retry = max(0, 30 - (time() - $set_at));
        $this->assertEqualsWithDelta($expected_retry, $result['data']['retry_after'], 1.5);
    }

    public function test_process_refresh_request_respects_server_rate_limit() {
        $option_name = 'discord_server_stats_options';
        $cache_key   = 'discord_server_stats_cache';

        $GLOBALS['wp_test_options'][$option_name] = array(
            'server_id'      => '2233445566',
            'cache_duration' => 30,
        );

        $api = new Discord_Bot_JLG_API($option_name, $cache_key, 30);

        $rate_limit_key = $cache_key . Discord_Bot_JLG_API::REFRESH_LOCK_SUFFIX;

        $set_at = time();
        set_transient($rate_limit_key, $set_at, 30);

        $result = $api->process_refresh_request(array('is_public_request' => true));

        $this->assertFalse($result['success']);
        $this->assertSame(429, $result['status']);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('rate_limited', $result['data']);
        $this->assertTrue($result['data']['rate_limited']);
        $this->assertArrayHasKey('retry_after', $result['data']);
        $this->assertStringContainsString('Veuillez patienter', $result['data']['message']);

        $expected_retry = max(0, 30 - (time() - $set_at));
        $this->assertEqualsWithDelta($expected_retry, $result['data']['retry_after'], 1.5);
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

    public function test_get_stats_merges_presence_breakdown_and_logs_snapshot() {
        $option_name = 'discord_server_stats_options';
        $cache_key   = 'discord_server_stats_cache';

        $GLOBALS['wp_test_options'][$option_name] = array(
            'server_id'      => '314159265',
            'cache_duration' => 200,
            'bot_token'      => 'token-merge',
        );

        $widget_payload = array(
            'presence_count' => 5,
            'name'           => 'Widget Merge Guild',
            'members'        => array(
                array('id' => 1, 'status' => 'online'),
                array('id' => 2, 'status' => 'online'),
                array('id' => 3, 'status' => 'idle'),
                array('id' => 4, 'status' => 'streaming'),
                array('id' => 5, 'status' => 'dnd'),
            ),
        );

        $bot_payload = array(
            'approximate_presence_count' => 10,
            'approximate_member_count'   => 48,
            'name'                       => 'Bot Merge Guild',
            'premium_subscription_count' => 7,
            'presence_count_by_status'   => array(
                'online' => 6,
                'idle'   => 1,
                'dnd'    => 3,
            ),
        );

        $analytics   = new Recording_Discord_Bot_JLG_Analytics();
        $http_client = new Successful_Mock_Discord_Bot_JLG_Http_Client($widget_payload, $bot_payload);
        $api         = new Discord_Bot_JLG_API($option_name, $cache_key, 60, $http_client, $analytics);

        $stats = $api->get_stats(array('bypass_cache' => true));

        $this->assertSame(2, count($http_client->requests));
        $this->assertSame(
            array(
                'online'    => 8,
                'idle'      => 2,
                'dnd'       => 4,
                'streaming' => 1,
            ),
            $stats['presence_count_by_status']
        );
        $this->assertSame(48, $stats['total']);
        $this->assertTrue($stats['has_total']);
        $this->assertTrue($stats['total_is_approximate']);
        $this->assertSame(48, $stats['approximate_member_count']);
        $this->assertSame(5, $stats['approximate_presence_count']);
        $this->assertSame(7, $stats['premium_subscription_count']);

        $this->assertCount(1, $analytics->snapshots);
        $snapshot = $analytics->snapshots[0];
        $this->assertSame('default', $snapshot['profile_key']);
        $this->assertSame('314159265', $snapshot['server_id']);
        $this->assertSame($stats, $snapshot['stats']);
    }

    public function test_refresh_cache_via_cron_refreshes_all_profiles() {
        $option_name = 'discord_server_stats_options';
        $cache_key   = 'discord_server_stats_cache';

        $GLOBALS['wp_test_options'][$option_name] = array(
            'server_id'       => '123456789',
            'bot_token'       => 'token-default',
            'server_profiles' => array(
                'alt' => array(
                    'key'       => 'alt',
                    'label'     => 'Alt Profile',
                    'server_id' => '987654321',
                    'bot_token' => 'token-alt',
                ),
                'beta' => array(
                    'key'       => 'beta',
                    'label'     => 'Beta Profile',
                    'server_id' => '192837465',
                    'bot_token' => 'token-beta',
                ),
            ),
        );

        $api = new Recording_Discord_Bot_JLG_API($option_name, $cache_key, 60);

        $api->refresh_cache_via_cron();

        $this->assertCount(3, $api->recorded_args);
        $this->assertSame(
            array(
                'bypass_cache' => true,
            ),
            $api->recorded_args[0]
        );

        $this->assertSame(
            array(
                'profile_key'  => 'alt',
                'bypass_cache' => true,
            ),
            $api->recorded_args[1]
        );

        $this->assertSame(
            array(
                'profile_key'  => 'beta',
                'bypass_cache' => true,
            ),
            $api->recorded_args[2]
        );
    }

    public function test_refresh_cache_via_cron_uses_dispatcher_when_available() {
        $option_name = 'discord_server_stats_options';
        $cache_key   = 'discord_server_stats_cache';

        $api = new Recording_Discord_Bot_JLG_API($option_name, $cache_key, 60);

        $dispatcher = new class {
            public $calls = 0;
            public $force_flags = array();

            public function dispatch_refresh_jobs($force = false) {
                $this->calls++;
                $this->force_flags[] = $force;
            }
        };

        $api->set_refresh_dispatcher($dispatcher);

        $api->refresh_cache_via_cron();

        $this->assertSame(1, $dispatcher->calls);
        $this->assertEmpty($api->recorded_args);
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

    public function test_get_stats_ignores_bot_token_override_from_args() {
        $option_name = 'discord_server_stats_options';
        $cache_key   = 'discord_server_stats_cache';

        $GLOBALS['wp_test_options'][$option_name] = array(
            'server_id'      => '1357911',
            'cache_duration' => 60,
            'bot_token'      => 'stored-token',
        );

        $widget_payload = array(
            'presence_count' => 6,
            'name'           => 'Token Guarded Guild',
        );

        $bot_payload = array(
            'approximate_presence_count' => 12,
            'approximate_member_count'   => 48,
            'name'                       => 'Token Guarded Guild',
            'premium_subscription_count' => 2,
        );

        $http_client = new Successful_Mock_Discord_Bot_JLG_Http_Client($widget_payload, $bot_payload);
        $api         = new Discord_Bot_JLG_API($option_name, $cache_key, 60, $http_client);

        $api->get_stats(
            array(
                'bypass_cache' => true,
                'bot_token'    => 'injected-token',
            )
        );

        $this->assertSame(2, count($http_client->requests));

        $bot_request = null;
        foreach ($http_client->requests as $request) {
            if ('bot' === $request['context']) {
                $bot_request = $request;
                break;
            }
        }

        $this->assertNotNull($bot_request, 'Expected a bot request to be issued');
        $this->assertArrayHasKey('headers', $bot_request['args']);
        $this->assertArrayHasKey('Authorization', $bot_request['args']['headers']);
        $this->assertSame('Bot stored-token', $bot_request['args']['headers']['Authorization']);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_get_stats_prefers_constant_token_over_arg_override() {
        define('DISCORD_BOT_JLG_TOKEN', 'constant-token');

        $option_name = 'discord_server_stats_options';
        $cache_key   = 'discord_server_stats_cache';

        $GLOBALS['wp_test_options'][$option_name] = array(
            'server_id'      => '2468024',
            'cache_duration' => 60,
            'bot_token'      => 'stored-token',
        );

        $widget_payload = array(
            'presence_count' => 4,
            'name'           => 'Constant Token Guild',
        );

        $bot_payload = array(
            'approximate_presence_count' => 8,
            'approximate_member_count'   => 32,
            'name'                       => 'Constant Token Guild',
            'premium_subscription_count' => 5,
        );

        $http_client = new Successful_Mock_Discord_Bot_JLG_Http_Client($widget_payload, $bot_payload);
        $api         = new Discord_Bot_JLG_API($option_name, $cache_key, 60, $http_client);

        $api->get_stats(
            array(
                'bypass_cache' => true,
                'bot_token'    => 'ignored-token',
            )
        );

        $this->assertSame(2, count($http_client->requests));

        $bot_request = null;
        foreach ($http_client->requests as $request) {
            if ('bot' === $request['context']) {
                $bot_request = $request;
                break;
            }
        }

        $this->assertNotNull($bot_request, 'Expected a bot request to be issued');
        $this->assertArrayHasKey('headers', $bot_request['args']);
        $this->assertArrayHasKey('Authorization', $bot_request['args']['headers']);
        $this->assertSame('Bot constant-token', $bot_request['args']['headers']['Authorization']);
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
            $this->assertSame(503, $response->status_code);
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

    public function test_merge_stats_prioritises_widget_metadata_when_incomplete() {
        $option_name = 'discord_server_stats_options';
        $cache_key   = 'discord_server_stats_cache';

        $api = new Discord_Bot_JLG_API($option_name, $cache_key, 60);

        $widget_stats = array(
            'online'                    => 8,
            'total'                     => 120,
            'has_total'                 => true,
            'total_is_approximate'      => false,
            'server_name'               => 'Widget Guild',
            'server_avatar_url'         => 'https://cdn.example.com/widget.png',
            'server_avatar_base_url'    => 'https://cdn.example.com/',
            'presence_count_by_status'  => array(
                'online' => 6,
                'idle'   => 2,
            ),
            'approximate_presence_count' => 15,
        );

        $bot_stats = array(
            'online'                     => 10,
            'total'                      => 118,
            'has_total'                  => true,
            'total_is_approximate'       => true,
            'server_name'                => 'Bot Guild',
            'server_avatar_url'          => 'https://cdn.example.com/bot.png',
            'presence_count_by_status'   => array(
                'online' => 4,
                'dnd'    => 1,
            ),
            'approximate_member_count'   => 135,
            'premium_subscription_count' => 7,
        );

        $reflection = new ReflectionMethod(Discord_Bot_JLG_API::class, 'merge_stats');
        $reflection->setAccessible(true);

        $result = $reflection->invoke($api, $widget_stats, $bot_stats, true);

        $this->assertSame(8, $result['online']);
        $this->assertSame(120, $result['total']);
        $this->assertTrue($result['has_total']);
        $this->assertFalse($result['total_is_approximate']);
        $this->assertSame('Widget Guild', $result['server_name']);
        $this->assertSame('https://cdn.example.com/widget.png', $result['server_avatar_url']);
        $this->assertSame('https://cdn.example.com/', $result['server_avatar_base_url']);
        $this->assertSame(
            array('online' => 10, 'idle' => 2, 'dnd' => 1),
            $result['presence_count_by_status']
        );
        $this->assertSame(15, $result['approximate_presence_count']);
        $this->assertSame(135, $result['approximate_member_count']);
        $this->assertSame(7, $result['premium_subscription_count']);
    }

    public function test_merge_stats_returns_widget_payload_when_not_incomplete() {
        $option_name = 'discord_server_stats_options';
        $cache_key   = 'discord_server_stats_cache';

        $api = new Discord_Bot_JLG_API($option_name, $cache_key, 60);

        $widget_stats = array(
            'online'                    => 4,
            'total'                     => 50,
            'has_total'                 => true,
            'server_name'               => 'Complete Widget',
            'presence_count_by_status'  => array('online' => 4),
            'approximate_presence_count' => 4,
        );

        $bot_stats = array(
            'online' => 6,
        );

        $reflection = new ReflectionMethod(Discord_Bot_JLG_API::class, 'merge_stats');
        $reflection->setAccessible(true);

        $result = $reflection->invoke($api, $widget_stats, $bot_stats, false);

        $this->assertSame($widget_stats, $result);
    }

    public function test_merge_stats_returns_bot_payload_when_widget_missing() {
        $option_name = 'discord_server_stats_options';
        $cache_key   = 'discord_server_stats_cache';

        $api = new Discord_Bot_JLG_API($option_name, $cache_key, 60);

        $widget_stats = null;

        $bot_stats = array(
            'online'                     => 11,
            'total'                      => 210,
            'has_total'                  => true,
            'server_name'                => 'Only Bot',
            'presence_count_by_status'   => array('online' => 11),
            'approximate_presence_count' => 12,
        );

        $reflection = new ReflectionMethod(Discord_Bot_JLG_API::class, 'merge_stats');
        $reflection->setAccessible(true);

        $result = $reflection->invoke($api, $widget_stats, $bot_stats, null);

        $this->assertSame($bot_stats, $result);
    }
}
