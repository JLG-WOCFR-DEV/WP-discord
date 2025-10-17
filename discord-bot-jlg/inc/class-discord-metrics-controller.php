<?php
if (!defined('ABSPATH')) {
    exit;
}

class Discord_Bot_JLG_Metrics_Controller {
    const ROUTE_NAMESPACE = 'discord-bot-jlg/v1';
    const ROUTE_METRICS = '/metrics';
    const ROUTE_ALERT_WEBHOOK = '/webhooks/alerts';
    const SIGNATURE_HEADER = 'x-discord-bot-jlg-signature';

    /**
     * @var Discord_Bot_JLG_Metrics_Registry
     */
    private $registry;

    /**
     * @var Discord_Bot_JLG_Options_Repository
     */
    private $options_repository;

    /**
     * Scheduler instance expected to expose a schedule() method.
     *
     * @var object
     */
    private $alert_scheduler;

    /**
     * @var Discord_Bot_JLG_Event_Logger|null
     */
    private $event_logger;

    /**
     * @param object $alert_scheduler Scheduler instance expected to implement schedule().
     * @phpstan-param object{schedule: callable} $alert_scheduler
     */
    public function __construct(
        Discord_Bot_JLG_Metrics_Registry $registry,
        Discord_Bot_JLG_Options_Repository $options_repository,
        $alert_scheduler,
        $event_logger = null
    ) {
        $this->registry           = $registry;
        $this->options_repository = $options_repository;
        if (!is_object($alert_scheduler) || !method_exists($alert_scheduler, 'schedule')) {
            throw new InvalidArgumentException(
                'Discord_Bot_JLG_Metrics_Controller requires an alert scheduler exposing a schedule() method.'
            );
        }

        $this->alert_scheduler    = $alert_scheduler;
        $this->event_logger       = ($event_logger instanceof Discord_Bot_JLG_Event_Logger)
            ? $event_logger
            : null;

        add_action('rest_api_init', array($this, 'register_routes'));
    }

    public function register_routes() {
        register_rest_route(
            self::ROUTE_NAMESPACE,
            self::ROUTE_METRICS,
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array($this, 'handle_get_metrics'),
                    'permission_callback' => array($this, 'check_metrics_permissions'),
                ),
            ),
            true
        );

        register_rest_route(
            self::ROUTE_NAMESPACE,
            self::ROUTE_ALERT_WEBHOOK,
            array(
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array($this, 'handle_alert_webhook'),
                    'permission_callback' => '__return_true',
                ),
            ),
            true
        );
    }

    public function check_metrics_permissions($request) {
        if (current_user_can('manage_options') || current_user_can(Discord_Bot_JLG_Capabilities::VIEW_ANALYTICS)) {
            return true;
        }

        return new WP_Error(
            'discord_bot_jlg_metrics_forbidden',
            __('Vous n\'avez pas la permission d\'accéder aux métriques.', 'discord-bot-jlg'),
            array('status' => 403)
        );
    }

    public function handle_get_metrics($request) {
        $state = $this->registry->get_state();
        $body  = $this->render_prometheus($state);

        add_filter('rest_pre_serve_request', array($this, 'serve_metrics_as_plain_text'), 10, 4);

        return $this->prepare_plain_text_response($body);
    }

    public function serve_metrics_as_plain_text($served, $server, $response, $request) {
        unset($server);

        if ($served) {
            return $served;
        }

        if (!($request instanceof WP_REST_Request)) {
            return $served;
        }

        $route = $request->get_route();
        $metrics_route = '/' . self::ROUTE_NAMESPACE . self::ROUTE_METRICS;

        if ($route !== $metrics_route) {
            return $served;
        }

        if (!($response instanceof WP_REST_Response)) {
            return $served;
        }

        if (function_exists('remove_filter')) {
            remove_filter('rest_pre_serve_request', array($this, 'serve_metrics_as_plain_text'), 10);
        }

        $body = $this->get_plain_text_body_from_response($response);

        if ('' === $body) {
            return $served;
        }

        $this->send_response_headers($response);

        echo $body;

        return true;
    }

    private function prepare_plain_text_response($body) {
        $response = new WP_REST_Response(null, 200);
        $response->set_data((string) $body);
        $response->header('Content-Type', 'text/plain; version=0.0.4');

        return $response;
    }

    private function get_plain_text_body_from_response($response) {
        if (!($response instanceof WP_REST_Response)) {
            return '';
        }

        $data = $response->get_data();

        if (is_string($data)) {
            return $data;
        }

        if (is_scalar($data)) {
            return (string) $data;
        }

        return '';
    }

    private function send_response_headers(WP_REST_Response $response) {
        $can_send_headers = function_exists('headers_sent') ? !headers_sent() : true;

        if (!$can_send_headers) {
            return;
        }

        $headers = $response->get_headers();
        if (!is_array($headers) || empty($headers)) {
            return;
        }

        foreach ($headers as $name => $value) {
            if (is_array($value)) {
                foreach ($value as $header_value) {
                    header($name . ': ' . $header_value, true);
                }
            } else {
                header($name . ': ' . $value, true);
            }
        }
    }

    public function handle_alert_webhook($request) {
        $body = $this->extract_raw_body($request);

        if ('' === $body) {
            return new WP_REST_Response(
                array(
                    'success' => false,
                    'error'   => 'empty_payload',
                ),
                400
            );
        }

        if (!$this->is_signature_valid($body, $request->get_header(self::SIGNATURE_HEADER))) {
            return new WP_REST_Response(
                array(
                    'success' => false,
                    'error'   => 'invalid_signature',
                ),
                401
            );
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            return new WP_REST_Response(
                array(
                    'success' => false,
                    'error'   => 'invalid_json',
                ),
                400
            );
        }

        $profile_key = isset($data['profile_key']) ? sanitize_key($data['profile_key']) : '';
        $server_id   = isset($data['server_id']) ? preg_replace('/[^0-9]/', '', (string) $data['server_id']) : '';
        $stats       = isset($data['stats']) && is_array($data['stats']) ? $data['stats'] : array();

        if ('' === $profile_key || '' === $server_id) {
            return new WP_REST_Response(
                array(
                    'success' => false,
                    'error'   => 'missing_identifiers',
                ),
                422
            );
        }

        $scheduled = $this->get_alert_scheduler()->schedule(
            array(
                'profile_key' => $profile_key,
                'server_id'   => $server_id,
                'stats'       => $stats,
            )
        );

        if (!$scheduled) {
            return new WP_REST_Response(
                array(
                    'success' => false,
                    'error'   => 'schedule_failed',
                ),
                500
            );
        }

        $this->log_webhook_event($profile_key, $server_id, $stats);

        return new WP_REST_Response(
            array(
                'success' => true,
            ),
            202
        );
    }

    /**
     * Retrieve the scheduler instance.
     *
     * @return object
     *
     * @throws RuntimeException When the scheduler does not expose schedule().
     */
    private function get_alert_scheduler() {
        if (!is_object($this->alert_scheduler) || !method_exists($this->alert_scheduler, 'schedule')) {
            throw new RuntimeException(
                'Discord_Bot_JLG_Metrics_Controller alert scheduler must expose a schedule() method.'
            );
        }

        return $this->alert_scheduler;
    }

    private function render_prometheus(array $state) {
        $lines = array();

        $lines[] = '# HELP discord_bot_jlg_http_requests_total Total HTTP requests to Discord segmented by context and outcome.';
        $lines[] = '# TYPE discord_bot_jlg_http_requests_total counter';

        $contexts = isset($state['http']['by_context']) && is_array($state['http']['by_context'])
            ? $state['http']['by_context']
            : array();

        if (empty($contexts)) {
            $contexts = array('default' => array('total' => array('success' => 0, 'error' => 0)));
        }

        foreach ($contexts as $context => $values) {
            $totals = isset($values['total']) && is_array($values['total'])
                ? $values['total']
                : array();
            $totals = array_merge(array('success' => 0, 'error' => 0), $totals);

            foreach ($totals as $outcome => $count) {
                $labels = sprintf(
                    '{context="%s",outcome="%s"}',
                    $this->escape_label($context),
                    $this->escape_label($outcome)
                );
                $lines[] = sprintf('discord_bot_jlg_http_requests_total%s %d', $labels, (int) $count);
            }
        }

        $lines[] = '# HELP discord_bot_jlg_http_responses_total HTTP responses grouped by status code.';
        $lines[] = '# TYPE discord_bot_jlg_http_responses_total counter';

        $statuses = isset($state['http']['status']) && is_array($state['http']['status']) ? $state['http']['status'] : array();

        if (empty($statuses)) {
            $lines[] = 'discord_bot_jlg_http_responses_total{status="0"} 0';
        } else {
            foreach ($statuses as $status => $count) {
                $lines[] = sprintf(
                    'discord_bot_jlg_http_responses_total{status="%s"} %d',
                    $this->escape_label($status),
                    (int) $count
                );
            }
        }

        $duration_sum   = isset($state['http']['duration_ms_sum']) ? (int) $state['http']['duration_ms_sum'] : 0;
        $duration_count = isset($state['http']['duration_ms_count']) ? (int) $state['http']['duration_ms_count'] : 0;

        $lines[] = '# HELP discord_bot_jlg_http_request_duration_ms_sum Total duration of HTTP requests in milliseconds.';
        $lines[] = '# TYPE discord_bot_jlg_http_request_duration_ms_sum counter';
        $lines[] = sprintf('discord_bot_jlg_http_request_duration_ms_sum %d', $duration_sum);

        $lines[] = '# HELP discord_bot_jlg_http_request_duration_ms_count Number of recorded HTTP requests.';
        $lines[] = '# TYPE discord_bot_jlg_http_request_duration_ms_count counter';
        $lines[] = sprintf('discord_bot_jlg_http_request_duration_ms_count %d', $duration_count);

        $quota_last = isset($state['http']['quota']['last']) ? $state['http']['quota']['last'] : null;
        $quota_min  = isset($state['http']['quota']['min']) ? $state['http']['quota']['min'] : null;

        $lines[] = '# HELP discord_bot_jlg_http_quota_remaining_last Last reported Discord API rate limit remaining value.';
        $lines[] = '# TYPE discord_bot_jlg_http_quota_remaining_last gauge';
        $lines[] = sprintf('discord_bot_jlg_http_quota_remaining_last %s', $this->format_numeric_value($quota_last));

        $lines[] = '# HELP discord_bot_jlg_http_quota_remaining_min Lowest observed Discord API rate limit remaining value.';
        $lines[] = '# TYPE discord_bot_jlg_http_quota_remaining_min gauge';
        $lines[] = sprintf('discord_bot_jlg_http_quota_remaining_min %s', $this->format_numeric_value($quota_min));

        $lines[] = '# HELP discord_bot_jlg_logged_events_total Logged events grouped by type.';
        $lines[] = '# TYPE discord_bot_jlg_logged_events_total counter';

        $events = isset($state['events']['by_type']) && is_array($state['events']['by_type'])
            ? $state['events']['by_type']
            : array();

        if (empty($events)) {
            $lines[] = 'discord_bot_jlg_logged_events_total{type="none"} 0';
        } else {
            foreach ($events as $type => $count) {
                $lines[] = sprintf(
                    'discord_bot_jlg_logged_events_total{type="%s"} %d',
                    $this->escape_label($type),
                    (int) $count
                );
            }
        }

        return implode("\n", $lines) . "\n";
    }

    private function escape_label($value) {
        $value = (string) $value;

        return str_replace(
            array('\\', "\n", '"'),
            array('\\\\', '\n', '\"'),
            $value
        );
    }

    private function format_numeric_value($value) {
        if (null === $value || '' === $value) {
            return '0';
        }

        if (!is_numeric($value)) {
            return '0';
        }

        return (string) (0 + $value);
    }

    private function extract_raw_body($request) {
        if (method_exists($request, 'get_body')) {
            $body = (string) $request->get_body();
            if ('' !== $body) {
                return $body;
            }
        }

        if (method_exists($request, 'get_json_params')) {
            $params = $request->get_json_params();
            if (is_array($params) && !empty($params)) {
                return wp_json_encode($params);
            }
        }

        return '';
    }

    private function is_signature_valid($body, $header_value) {
        $header_value = is_string($header_value) ? trim($header_value) : '';
        if ('' === $header_value) {
            return false;
        }

        $options = $this->options_repository->get_options();
        $secret  = isset($options['analytics_alert_webhook_secret'])
            ? (string) $options['analytics_alert_webhook_secret']
            : '';

        if ('' === $secret) {
            return false;
        }

        $provided = $header_value;
        if (0 === stripos($provided, 'sha256=')) {
            $provided = substr($provided, 7);
        }

        $provided = strtolower($provided);
        $expected = hash_hmac('sha256', $body, $secret);

        return hash_equals($expected, $provided);
    }

    private function log_webhook_event($profile_key, $server_id, array $stats) {
        if (!($this->event_logger instanceof Discord_Bot_JLG_Event_Logger)) {
            return;
        }

        $this->event_logger->log(
            'analytics_alert_webhook',
            array(
                'profile_key' => $profile_key,
                'server_id'   => $server_id,
                'stats_keys'  => implode(',', array_keys($stats)),
            )
        );
    }
}
