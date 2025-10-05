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
    private $payload;

    public function __construct($payload = array()) {
        $this->payload = $payload;
    }

    public function get_aggregates($args = array()) {
        $this->last_args = $args;
        return $this->payload;
    }
}

class Test_Discord_Bot_JLG_REST_Controller extends TestCase {
    protected function tearDown(): void {
        $GLOBALS['wp_test_nonce_validations'] = array();
        $GLOBALS['wp_test_is_user_logged_in'] = false;

        parent::tearDown();
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
        $analytics_payload = array('timeseries' => array(array('timestamp' => 1000, 'online' => 12)));
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
        $this->assertSame($analytics_payload, $payload['data']);
        $this->assertArrayHasKey('profile_key', $analytics->last_args);
        $this->assertSame('guild', $analytics->last_args['profile_key']);
        $this->assertSame(5, $analytics->last_args['days']);
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
}
