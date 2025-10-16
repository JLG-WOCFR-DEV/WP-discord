<?php
if (!defined('ABSPATH')) {
    exit;
}

class Discord_Bot_JLG_Analytics_Alert_Scheduler {
    const HOOK = 'discord_bot_jlg_dispatch_analytics_alert';
    const ACTION_SCHEDULER_GROUP = 'discord-bot-jlg';

    /**
     * @var Discord_Bot_JLG_Alerts
     */
    private $alerts;

    /**
     * @var Discord_Bot_JLG_Event_Logger|null
     */
    private $event_logger;

    public function __construct(Discord_Bot_JLG_Alerts $alerts, $event_logger = null) {
        $this->alerts       = $alerts;
        $this->event_logger = ($event_logger instanceof Discord_Bot_JLG_Event_Logger)
            ? $event_logger
            : null;
    }

    public function register() {
        add_action(self::HOOK, array($this, 'handle_scheduled_alert'), 10, 1);
    }

    public function schedule(array $payload, $delay = 0) {
        $payload = $this->sanitize_payload($payload);

        if (empty($payload['profile_key']) || empty($payload['server_id'])) {
            $this->log_event('discarded', $payload, array('reason' => 'invalid_payload'));
            return false;
        }

        $timestamp = time() + max(0, (int) $delay);

        if ($this->supports_action_scheduler()) {
            as_schedule_single_action($timestamp, self::HOOK, array($payload), self::ACTION_SCHEDULER_GROUP);
        } else {
            wp_schedule_single_event($timestamp, self::HOOK, array($payload));
        }

        $this->log_event('scheduled', $payload);

        return true;
    }

    public function handle_scheduled_alert($payload) {
        $payload = $this->sanitize_payload($payload);

        if (empty($payload['profile_key']) || empty($payload['server_id'])) {
            $this->log_event('discarded', $payload, array('reason' => 'invalid_payload')); 
            return;
        }

        $start_time = microtime(true);

        $this->alerts->maybe_dispatch_alert(
            $payload['profile_key'],
            $payload['server_id'],
            isset($payload['stats']) && is_array($payload['stats']) ? $payload['stats'] : array()
        );

        $this->log_event('dispatched', $payload, array(
            'duration_ms' => $this->calculate_duration_ms($start_time),
        ));
    }

    private function sanitize_payload($payload) {
        if (!is_array($payload)) {
            return array();
        }

        $sanitized = array();
        $sanitized['profile_key'] = isset($payload['profile_key'])
            ? sanitize_key($payload['profile_key'])
            : '';

        $sanitized['server_id'] = isset($payload['server_id'])
            ? preg_replace('/[^0-9]/', '', (string) $payload['server_id'])
            : '';

        if (isset($payload['stats']) && is_array($payload['stats'])) {
            $sanitized['stats'] = $payload['stats'];
        } else {
            $sanitized['stats'] = array();
        }

        return $sanitized;
    }

    private function supports_action_scheduler() {
        return function_exists('as_schedule_single_action')
            && function_exists('as_unschedule_action')
            && function_exists('as_next_scheduled_action');
    }

    private function log_event($outcome, array $payload, array $extra = array()) {
        if (!($this->event_logger instanceof Discord_Bot_JLG_Event_Logger)) {
            return;
        }

        $context = array_merge(
            array(
                'channel'     => 'alert_scheduler',
                'profile_key' => isset($payload['profile_key']) ? $payload['profile_key'] : '',
                'server_id'   => isset($payload['server_id']) ? $payload['server_id'] : '',
                'outcome'     => sanitize_key($outcome),
            ),
            $extra
        );

        $this->event_logger->log('analytics_alert', $context);
    }

    private function calculate_duration_ms($start_time) {
        if (!is_numeric($start_time)) {
            return 0;
        }

        $duration = microtime(true) - (float) $start_time;
        if (!is_finite($duration) || $duration < 0) {
            return 0;
        }

        return (int) round($duration * 1000);
    }
}
