<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/bootstrap.php';

class Stubbed_Discord_Bot_JLG_API_For_REST extends Discord_Bot_JLG_API {
    public $last_args = array();
    private $next_result = array();

    public function __construct() {
        parent::__construct(
            DISCORD_BOT_JLG_OPTION_NAME,
            DISCORD_BOT_JLG_CACHE_KEY,
            DISCORD_BOT_JLG_DEFAULT_CACHE_DURATION
        );
    }

    public function set_next_result(array $result) {
        $this->next_result = $result;
    }

    public function process_refresh_request($args = array()) {
        $this->last_args = $args;

        if (empty($this->next_result)) {
            return array(
                'success' => false,
                'data'    => array(),
                'status'  => 500,
            );
        }

        return $this->next_result;
    }
}

class Stubbed_Discord_Bot_JLG_Analytics {
    public $last_args = array();
    public $call_log = array();
    private $payload;
    private $payload_map = array();

    public function __construct($payload = array()) {
        $this->payload = $payload;
    }

    public function set_payload_map(array $map) {
        $this->payload_map = $map;
    }

    public function get_aggregates($args = array()) {
        $this->last_args = $args;
        $this->call_log[] = $args;

        $profile_key = isset($args['profile_key']) ? $args['profile_key'] : '';
        if (isset($this->payload_map[$profile_key])) {
            return $this->payload_map[$profile_key];
        }

        return $this->payload;
    }
}

if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response) {
        if (is_array($response) && isset($response['response']['code'])) {
            return (int) $response['response']['code'];
        }

        return 0;
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

class Stubbed_Discord_Bot_JLG_Alert_Scheduler implements Discord_Bot_JLG_Analytics_Alert_Scheduler_Interface {
    public $scheduled = array();

    public function schedule(array $payload, $delay = 0) {
        $this->scheduled[] = array(
            'payload' => $payload,
            'delay'   => $delay,
        );

        return true;
    }
}

class Test_Discord_Bot_JLG_REST_Controller extends TestCase {
    protected function tearDown(): void {
        $GLOBALS['wp_test_nonce_validations'] = array();
        $GLOBALS['wp_test_is_user_logged_in'] = false;
        $GLOBALS['wp_test_current_user_can']  = null;
        $GLOBALS['wp_test_transients']        = array();
        delete_option(Discord_Bot_JLG_Event_Logger::OPTION_NAME);

        parent::tearDown();
    }

    public function test_permission_callback_allows_admins() {
        $GLOBALS['wp_test_current_user_can'] = array('manage_options' => true);

        $api        = new Stubbed_Discord_Bot_JLG_API_For_REST();
        $controller = new Discord_Bot_JLG_REST_Controller($api, null);
        $request    = new WP_REST_Request();

        $this->assertTrue($controller->check_rest_permissions($request));
    }

    public function test_permission_callback_allows_custom_capability_without_manage_options() {
        $GLOBALS['wp_test_current_user_can'] = array(
            'manage_options'         => false,
            'view_discord_analytics' => true,
        );

        $api        = new Stubbed_Discord_Bot_JLG_API_For_REST();
        $controller = new Discord_Bot_JLG_REST_Controller($api, null);
        $request    = new WP_REST_Request('GET', '/discord-bot-jlg/v1/analytics');

        $this->assertTrue($controller->check_rest_permissions($request));
    }

    public function test_permission_callback_rejects_when_user_lacks_capability() {
        $GLOBALS['wp_test_current_user_can'] = array('manage_options' => false);

        $api        = new Stubbed_Discord_Bot_JLG_API_For_REST();
        $controller = new Discord_Bot_JLG_REST_Controller($api, null);
        $request    = new WP_REST_Request();

        $result = $controller->check_rest_permissions($request);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('discord_bot_jlg_forbidden', $result->get_error_code());

        $error_data = $result->get_error_data();
        if (is_array($error_data) && array_key_exists('status', $error_data)) {
            $this->assertSame(403, $error_data['status']);
        }
    }

    public function test_public_request_returns_successful_payload() {
        $api = new Stubbed_Discord_Bot_JLG_API_For_REST();
        $api->set_next_result(
            array(
                'success' => true,
                'data'    => array('foo' => 'bar'),
                'status'  => 200,
            )
        );

        $controller = new Discord_Bot_JLG_REST_Controller($api, null);
        $request    = new WP_REST_Request();
        $request->set_param('profile_key', 'custom_profile');
        $request->set_param('server_id', ' 123456 ');

        $response = $controller->handle_get_stats($request);

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertSame(200, $response->get_status());

        $payload = $response->get_data();
        $this->assertTrue($payload['success']);
        $this->assertSame(array('foo' => 'bar'), $payload['data']);
        $this->assertArrayHasKey('profile_key', $api->last_args);
        $this->assertSame('custom_profile', $api->last_args['profile_key']);
        $this->assertSame('123456', $api->last_args['server_id']);
        $this->assertArrayHasKey('is_public_request', $api->last_args);
        $this->assertTrue($api->last_args['is_public_request']);
    }

    public function test_request_with_invalid_nonce_is_rejected() {
        $GLOBALS['wp_test_is_user_logged_in']           = true;
        $GLOBALS['wp_test_nonce_validations']['wp_rest'] = false;

        $api = new Stubbed_Discord_Bot_JLG_API_For_REST();
        $controller = new Discord_Bot_JLG_REST_Controller($api, null);
        $request    = new WP_REST_Request();
        $request->set_header('X-WP-Nonce', 'invalid');

        $response = $controller->handle_get_stats($request);

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertSame(403, $response->get_status());

        $payload = $response->get_data();
        $this->assertFalse($payload['success']);
        $this->assertArrayHasKey('nonce_expired', $payload['data']);
        $this->assertTrue($payload['data']['nonce_expired']);
        $this->assertSame(array(), $api->last_args);
    }

    public function test_rate_limited_response_is_forwarded() {
        $api = new Stubbed_Discord_Bot_JLG_API_For_REST();
        $api->set_next_result(
            array(
                'success' => false,
                'data'    => array(
                    'rate_limited' => true,
                    'retry_after'  => 12,
                ),
                'status'  => 429,
            )
        );

        $controller = new Discord_Bot_JLG_REST_Controller($api, null);
        $request    = new WP_REST_Request();

        $response = $controller->handle_get_stats($request);

        $this->assertSame(429, $response->get_status());
        $payload = $response->get_data();
        $this->assertFalse($payload['success']);
        $this->assertArrayHasKey('rate_limited', $payload['data']);
        $this->assertTrue($payload['data']['rate_limited']);
        $this->assertArrayHasKey('is_public_request', $api->last_args);
        $this->assertTrue($api->last_args['is_public_request']);
    }

    public function test_analytics_route_returns_aggregates() {
        $api = new Stubbed_Discord_Bot_JLG_API_For_REST();
        $analytics_payload = array(
            'timeseries' => array(
                array(
                    'timestamp' => 1000,
                    'online'    => 12,
                ),
            ),
        );
        $analytics = new Stubbed_Discord_Bot_JLG_Analytics($analytics_payload);

        $controller = new Discord_Bot_JLG_REST_Controller($api, $analytics);
        $request    = new WP_REST_Request();
        $request->set_param('profile_key', 'guild');
        $request->set_param('days', 5);

        $response = $controller->handle_get_analytics($request);

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertSame(200, $response->get_status());

        $payload = $response->get_data();
        $this->assertTrue($payload['success']);
        $this->assertArrayHasKey('data', $payload);
        $this->assertArrayHasKey('series', $payload['data']);
        $this->assertCount(1, $payload['data']['series']);
        $series_entry = $payload['data']['series'][0];
        $this->assertSame('guild', $series_entry['profile_key']);

        $this->assertCount(1, $series_entry['timeseries']);
        $point = $series_entry['timeseries'][0];
        $this->assertSame(1000, $point['timestamp']);
        $this->assertSame(12, $point['online']);
        $this->assertArrayHasKey('presence', $point);
        $this->assertArrayHasKey('total', $point);
        $this->assertArrayHasKey('premium', $point);
        $this->assertNull($point['presence']);
        $this->assertNull($point['total']);
        $this->assertNull($point['premium']);

        $this->assertSame($series_entry['timeseries'], $payload['data']['timeseries']);
        $this->assertArrayHasKey('profile_key', $analytics->last_args);
        $this->assertSame('guild', $analytics->last_args['profile_key']);
        $this->assertSame(5, $analytics->last_args['days']);
    }

    public function test_metrics_route_renders_prometheus_metrics() {
        $registry = new Discord_Bot_JLG_Metrics_Registry('discord_bot_jlg_metrics_test_state');
        $registry->reset();

        $response_ok = array(
            'response' => array('code' => 200),
            'headers'  => array('X-RateLimit-Remaining' => '42'),
        );

        $registry->record_http_request($response_ok, 'https://discord.com/api', array(), 'widget', 'req_1', 120);
        $registry->record_event(array('type' => 'discord_http'));

        $scheduler = new Stubbed_Discord_Bot_JLG_Alert_Scheduler();
        $options_repository = new Discord_Bot_JLG_Options_Repository(
            DISCORD_BOT_JLG_OPTION_NAME,
            'discord_bot_jlg_get_default_options'
        );

        $controller = new Discord_Bot_JLG_Metrics_Controller($registry, $options_repository, $scheduler);

        $GLOBALS['wp_test_current_user_can'] = array('manage_options' => true);
        $request = new WP_REST_Request('GET', '/discord-bot-jlg/v1/metrics');

        $response = $controller->handle_get_metrics($request);
        $this->assertInstanceOf(WP_REST_Response::class, $response);

        $body = $response->get_data();
        $this->assertIsString($body);
        $this->assertStringContainsString('discord_bot_jlg_http_requests_total', $body);
        $this->assertStringContainsString('context="widget"', $body);
        $this->assertStringContainsString('discord_bot_jlg_logged_events_total', $body);

        delete_option('discord_bot_jlg_metrics_test_state');
        delete_option(DISCORD_BOT_JLG_OPTION_NAME);
    }

    public function test_alert_webhook_requires_valid_signature() {
        $options = discord_bot_jlg_get_default_options();
        $options['analytics_alert_webhook_secret'] = 'super-secret';
        update_option(DISCORD_BOT_JLG_OPTION_NAME, $options);

        $registry = new Discord_Bot_JLG_Metrics_Registry('discord_bot_jlg_metrics_test_state');
        $scheduler = new Stubbed_Discord_Bot_JLG_Alert_Scheduler();
        $options_repository = new Discord_Bot_JLG_Options_Repository(
            DISCORD_BOT_JLG_OPTION_NAME,
            'discord_bot_jlg_get_default_options'
        );

        $controller = new Discord_Bot_JLG_Metrics_Controller($registry, $options_repository, $scheduler);

        $payload = array(
            'profile_key' => 'guild',
            'server_id'   => '12345',
            'stats'       => array('online' => 12),
        );

        $body = wp_json_encode($payload);
        $signature = 'sha256=' . hash_hmac('sha256', $body, 'super-secret');

        $request = new WP_REST_Request('POST', '/discord-bot-jlg/v1/webhooks/alerts');
        $request->set_body($body);
        $request->set_header('X-Discord-Bot-JLG-Signature', $signature);

        $response = $controller->handle_alert_webhook($request);

        $this->assertSame(202, $response->get_status());
        $this->assertCount(1, $scheduler->scheduled);
        $this->assertSame('guild', $scheduler->scheduled[0]['payload']['profile_key']);
        $this->assertSame('12345', $scheduler->scheduled[0]['payload']['server_id']);

        delete_option('discord_bot_jlg_metrics_test_state');
        delete_option(DISCORD_BOT_JLG_OPTION_NAME);
    }

    public function test_alert_webhook_rejects_invalid_signature() {
        $options = discord_bot_jlg_get_default_options();
        $options['analytics_alert_webhook_secret'] = 'super-secret';
        update_option(DISCORD_BOT_JLG_OPTION_NAME, $options);

        $registry = new Discord_Bot_JLG_Metrics_Registry('discord_bot_jlg_metrics_test_state');
        $scheduler = new Stubbed_Discord_Bot_JLG_Alert_Scheduler();
        $options_repository = new Discord_Bot_JLG_Options_Repository(
            DISCORD_BOT_JLG_OPTION_NAME,
            'discord_bot_jlg_get_default_options'
        );

        $controller = new Discord_Bot_JLG_Metrics_Controller($registry, $options_repository, $scheduler);

        $payload = array(
            'profile_key' => 'guild',
            'server_id'   => '12345',
            'stats'       => array('online' => 12),
        );

        $body = wp_json_encode($payload);

        $request = new WP_REST_Request('POST', '/discord-bot-jlg/v1/webhooks/alerts');
        $request->set_body($body);
        $request->set_header('X-Discord-Bot-JLG-Signature', 'sha256=invalid');

        $response = $controller->handle_alert_webhook($request);

        $this->assertSame(401, $response->get_status());
        $this->assertCount(0, $scheduler->scheduled);

        delete_option('discord_bot_jlg_metrics_test_state');
        delete_option(DISCORD_BOT_JLG_OPTION_NAME);
    }

    public function test_analytics_route_handles_missing_service() {
        $api = new Stubbed_Discord_Bot_JLG_API_For_REST();
        $controller = new Discord_Bot_JLG_REST_Controller($api, null);
        $request    = new WP_REST_Request();

        $response = $controller->handle_get_analytics($request);

        $this->assertSame(501, $response->get_status());
        $payload = $response->get_data();
        $this->assertFalse($payload['success']);
        $this->assertArrayHasKey('message', $payload['data']);
    }

    public function test_analytics_route_supports_multiple_profiles() {
        $api       = new Stubbed_Discord_Bot_JLG_API_For_REST();
        $analytics = new Stubbed_Discord_Bot_JLG_Analytics();
        $analytics->set_payload_map(
            array(
                'alpha' => array(
                    'timeseries' => array(
                        array('timestamp' => 100, 'presence' => 5, 'online' => 2),
                        array('timestamp' => 200, 'presence' => 7, 'online' => 3),
                    ),
                ),
                'beta'  => array(
                    'timeseries' => array(
                        array('timestamp' => 100, 'presence' => 3, 'online' => 1),
                        array('timestamp' => 300, 'presence' => 6, 'online' => 2),
                    ),
                ),
            )
        );

        $controller = new Discord_Bot_JLG_REST_Controller($api, $analytics);
        $request    = new WP_REST_Request();
        $request->set_param('profile_keys', array('alpha', 'beta'));
        $request->set_param('days', 4);

        $response = $controller->handle_get_analytics($request);
        $this->assertSame(200, $response->get_status());

        $payload = $response->get_data();
        $this->assertTrue($payload['success']);
        $this->assertArrayHasKey('series', $payload['data']);
        $this->assertCount(2, $payload['data']['series']);

        $series_keys = array();
        foreach ($payload['data']['series'] as $series_entry) {
            if (isset($series_entry['profile_key'])) {
                $series_keys[] = $series_entry['profile_key'];
            }
        }
        sort($series_keys);
        $this->assertSame(array('alpha', 'beta'), $series_keys);

        $timeline = array();
        foreach ($payload['data']['series'][0]['timeseries'] as $point) {
            if (isset($point['timestamp'])) {
                $timeline[] = $point['timestamp'];
            }
        }
        $this->assertContains(100, $timeline);
        $this->assertContains(200, $timeline);

        $this->assertCount(2, $analytics->call_log);
        $this->assertSame('alpha', $analytics->call_log[0]['profile_key']);
        $this->assertSame('beta', $analytics->call_log[1]['profile_key']);
    }

    public function test_export_analytics_uses_cached_payload_when_available() {
        $api        = new Stubbed_Discord_Bot_JLG_API_For_REST();
        $analytics  = new Stubbed_Discord_Bot_JLG_Analytics();
        $controller = new Discord_Bot_JLG_REST_Controller($api, $analytics);

        $profile_key = 'Guild_Key';
        $server_id   = ' 123 456 789 ';
        $days        = 12;

        $reflection  = new ReflectionClass(Discord_Bot_JLG_REST_Controller::class);
        $cache_method = $reflection->getMethod('get_analytics_cache_key');
        $cache_method->setAccessible(true);

        $sanitized_profile = sanitize_key($profile_key);
        $sanitized_server  = preg_replace('/[^0-9]/', '', $server_id);
        $cache_key         = $cache_method->invoke($controller, $sanitized_profile, $sanitized_server, $days);

        $cached_payload = array(
            'range'     => array('start' => 100, 'end' => 200),
            'averages'  => array('online' => 5),
            'timeseries'=> array(
                array(
                    'timestamp' => 123,
                    'online'    => 4,
                    'presence'  => 3,
                    'total'     => 10,
                    'premium'   => 2,
                ),
            ),
        );

        set_transient($cache_key, $cached_payload, 300);

        $request = new WP_REST_Request('GET', '/discord-bot-jlg/v1/analytics/export');
        $request->set_param('profile_key', $profile_key);
        $request->set_param('server_id', $server_id);
        $request->set_param('days', $days);
        $request->set_param('format', 'json');

        $response = $controller->handle_export_analytics($request);

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertSame(200, $response->get_status());
        $this->assertSame(array(), $analytics->last_args);

        $payload = $response->get_data();
        $this->assertTrue($payload['success']);
        $this->assertSame($cached_payload['range'], $payload['data']['range']);
        $this->assertSame($cached_payload['averages'], $payload['data']['averages']);
        $this->assertNotEmpty($payload['data']['timeseries']);

        $first_row = $payload['data']['timeseries'][0];
        $this->assertSame($sanitized_profile, $first_row['profile_key']);
        $this->assertSame($sanitized_server, $first_row['server_id']);
        $this->assertSame(123, $first_row['timestamp']);
        $this->assertSame(4, $first_row['online']);
        $this->assertSame(3, $first_row['presence']);
        $this->assertSame(10, $first_row['total']);
        $this->assertSame(2, $first_row['premium']);
    }

    public function test_export_analytics_stores_payload_in_cache_after_fetch() {
        $api       = new Stubbed_Discord_Bot_JLG_API_For_REST();
        $payload   = array(
            'range'     => array('start' => 1, 'end' => 2),
            'averages'  => array('online' => 8),
            'timeseries'=> array(
                array(
                    'timestamp' => 1000,
                    'online'    => 7,
                ),
            ),
        );
        $analytics = new Stubbed_Discord_Bot_JLG_Analytics($payload);

        $controller = new Discord_Bot_JLG_REST_Controller($api, $analytics);

        $profile_key = 'guild';
        $server_id   = '999888777';
        $days        = 7;

        $reflection  = new ReflectionClass(Discord_Bot_JLG_REST_Controller::class);
        $cache_method = $reflection->getMethod('get_analytics_cache_key');
        $cache_method->setAccessible(true);
        $cache_key   = $cache_method->invoke($controller, $profile_key, $server_id, $days);

        delete_transient($cache_key);

        $request = new WP_REST_Request('GET', '/discord-bot-jlg/v1/analytics/export');
        $request->set_param('profile_key', $profile_key);
        $request->set_param('server_id', $server_id);
        $request->set_param('days', $days);
        $request->set_param('format', 'csv');

        $response = $controller->handle_export_analytics($request);

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertSame(200, $response->get_status());
        $this->assertNotSame(array(), $analytics->last_args);
        $this->assertSame($profile_key, $analytics->last_args['profile_key']);
        $this->assertSame($server_id, $analytics->last_args['server_id']);
        $this->assertSame($days, $analytics->last_args['days']);

        $cached = get_transient($cache_key);
        $this->assertSame($payload, $cached);

        $entry = wp_test_get_transient_entry($cache_key);
        $this->assertIsArray($entry);
        $this->assertArrayHasKey('ttl', $entry);
        $this->assertGreaterThan(0, $entry['ttl']);
    }

    public function test_permission_callback_accepts_api_key_for_stats_scope() {
        $GLOBALS['wp_test_current_user_can'] = array('manage_options' => false);

        $api = new Stubbed_Discord_Bot_JLG_API_For_REST();
        $repository = new Discord_Bot_JLG_API_Key_Repository(null);
        $api->set_api_key_repository($repository);

        $key = $repository->create_key(
            array(
                'label'        => 'Tests',
                'profile_keys' => array('default'),
                'scopes'       => array('stats'),
            )
        );

        $controller = new Discord_Bot_JLG_REST_Controller($api, null);
        $request    = new WP_REST_Request('GET', '/discord-bot-jlg/v1/stats');
        $request->set_param('profile_key', 'default');
        $request->set_param('access_key', $key['key']);

        $this->assertTrue($controller->check_rest_permissions($request));
    }

    public function test_permission_callback_rejects_expired_api_key() {
        $GLOBALS['wp_test_current_user_can'] = array('manage_options' => false);

        $api = new Stubbed_Discord_Bot_JLG_API_For_REST();
        $repository = new Discord_Bot_JLG_API_Key_Repository(null);
        $api->set_api_key_repository($repository);

        $key = $repository->create_key(
            array(
                'label'        => 'Expired',
                'profile_keys' => array('default'),
                'scopes'       => array('stats'),
                'expires_at'   => gmdate('Y-m-d H:i:s', current_time('timestamp', true) - DAY_IN_SECONDS),
            )
        );

        $controller = new Discord_Bot_JLG_REST_Controller($api, null);
        $request    = new WP_REST_Request('GET', '/discord-bot-jlg/v1/stats');
        $request->set_param('profile_key', 'default');
        $request->set_param('access_key', $key['key']);

        $result = $controller->check_rest_permissions($request);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('discord_bot_jlg_forbidden', $result->get_error_code());
    }

    public function test_permission_callback_rejects_api_key_forbidden_profile() {
        $GLOBALS['wp_test_current_user_can'] = array('manage_options' => false);

        $api = new Stubbed_Discord_Bot_JLG_API_For_REST();
        $repository = new Discord_Bot_JLG_API_Key_Repository(null);
        $api->set_api_key_repository($repository);

        $key = $repository->create_key(
            array(
                'label'        => 'Restricted',
                'profile_keys' => array('other'),
                'scopes'       => array('stats'),
            )
        );

        $controller = new Discord_Bot_JLG_REST_Controller($api, null);
        $request    = new WP_REST_Request('GET', '/discord-bot-jlg/v1/stats');
        $request->set_param('profile_key', 'default');
        $request->set_param('access_key', $key['key']);

        $result = $controller->check_rest_permissions($request);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('discord_bot_jlg_forbidden', $result->get_error_code());
    }
}
