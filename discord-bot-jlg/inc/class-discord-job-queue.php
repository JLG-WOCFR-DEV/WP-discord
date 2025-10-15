<?php
if (!defined('ABSPATH')) {
    exit;
}

class Discord_Bot_JLG_Job_Queue {
    const JOB_HOOK = 'discord_bot_jlg_process_refresh_job';
    const ACTION_SCHEDULER_GROUP = 'discord-bot-jlg';

    private $api;
    private $option_name;
    private $event_logger;
    private $max_attempts;
    private $base_delay;

    public function __construct(Discord_Bot_JLG_API $api, $option_name, Discord_Bot_JLG_Event_Logger $event_logger = null) {
        $this->api = $api;
        $this->option_name = (string) $option_name;
        $this->event_logger = ($event_logger instanceof Discord_Bot_JLG_Event_Logger)
            ? $event_logger
            : $api->get_event_logger();
        $this->max_attempts = 5;
        $this->base_delay = 60;
    }

    public function register() {
        add_action(self::JOB_HOOK, array($this, 'run_job'), 10, 1);
    }

    public function dispatch_refresh_jobs($force = false) {
        $options = get_option($this->option_name, array());
        if (!is_array($options)) {
            $options = array();
        }

        if (!empty($options['demo_mode'])) {
            return;
        }

        $origin = $force ? 'manual' : 'cron';

        $jobs = array();

        $default_server_id = isset($options['server_id']) ? $this->sanitize_server_id($options['server_id']) : '';
        $default_has_token = !empty($options['bot_token']);

        if ('' !== $default_server_id) {
            $jobs[] = $this->build_job_payload('widget_refresh', 'default', $default_server_id, $origin);

            if ($default_has_token) {
                $jobs[] = $this->build_job_payload('bot_refresh', 'default', $default_server_id, $origin);
            }
        }

        $profiles = $this->api->get_server_profiles(true);
        if (is_array($profiles)) {
            foreach ($profiles as $profile) {
                if (!is_array($profile)) {
                    continue;
                }

                $profile_key = isset($profile['key']) ? sanitize_key($profile['key']) : '';
                $profile_server = isset($profile['server_id']) ? $this->sanitize_server_id($profile['server_id']) : '';
                $profile_token = isset($profile['bot_token']) ? trim((string) $profile['bot_token']) : '';

                if ('' === $profile_key || '' === $profile_server) {
                    continue;
                }

                $jobs[] = $this->build_job_payload('widget_refresh', $profile_key, $profile_server, $origin);

                if ('' !== $profile_token) {
                    $jobs[] = $this->build_job_payload('bot_refresh', $profile_key, $profile_server, $origin);
                }
            }
        }

        foreach ($jobs as $job) {
            $this->schedule_job($job, $force);
        }
    }

    public function run_job($job) {
        if (!is_array($job)) {
            return;
        }

        $job = wp_parse_args(
            $job,
            array(
                'type'        => '',
                'profile_key' => 'default',
                'server_id'   => '',
                'attempt'     => 1,
                'origin'      => 'cron',
            )
        );

        $job['type'] = sanitize_key($job['type']);
        $job['profile_key'] = discord_bot_jlg_sanitize_profile_key($job['profile_key']);
        $job['server_id'] = $this->sanitize_server_id($job['server_id']);
        $job['attempt'] = max(1, (int) $job['attempt']);

        if ('' === $job['type']) {
            return;
        }

        $start = microtime(true);
        $result = null;
        $error_message = '';

        try {
            switch ($job['type']) {
                case 'widget_refresh':
                    $result = $this->api->run_widget_refresh_job($job['profile_key'], $job);
                    break;
                case 'bot_refresh':
                    $result = $this->api->run_bot_refresh_job($job['profile_key'], $job);
                    break;
                default:
                    return;
            }

            if (is_wp_error($result)) {
                $error_message = $result->get_error_message();
                $data = $result->get_error_data();
                $should_retry = true;

                if (is_array($data) && array_key_exists('should_retry', $data)) {
                    $should_retry = (bool) $data['should_retry'];
                }

                $this->log_job_event($should_retry ? 'error' : 'skipped', $job, $start, array(
                    'error_message' => $error_message,
                ));

                $retry_scheduled = false;

                if ($should_retry) {
                    $retry_scheduled = $this->schedule_retry($job, $result);
                }

                if (!$should_retry || !$retry_scheduled) {
                    do_action('discord_bot_jlg_refresh_job_failed', $job, $error_message, $result);
                }

                return;
            }

            $this->log_job_event('success', $job, $start, is_array($result) ? $result : array());
            do_action('discord_bot_jlg_refresh_job_succeeded', $job, $result);
        } catch (Exception $exception) {
            $error_message = $exception->getMessage();
            $this->log_job_event('error', $job, $start, array(
                'error_message' => $error_message,
            ));
            $retry_scheduled = $this->schedule_retry($job);

            if (!$retry_scheduled) {
                do_action('discord_bot_jlg_refresh_job_failed', $job, $error_message, null);
            }
        }
    }

    private function build_job_payload($type, $profile_key, $server_id, $origin) {
        $signature = sprintf('%s:%s', $type, ('' !== $profile_key) ? $profile_key : 'default');

        return array(
            'type'        => $type,
            'profile_key' => ('' !== $profile_key) ? $profile_key : 'default',
            'server_id'   => $server_id,
            'attempt'     => 1,
            'signature'   => $signature,
            'origin'      => ('manual' === $origin) ? 'manual' : 'cron',
        );
    }

    private function schedule_job(array $job, $force = false) {
        $signature = isset($job['signature']) ? $job['signature'] : '';

        if (!$force && $this->is_job_already_scheduled($job, $signature)) {
            return;
        }

        if ($force) {
            $this->unschedule_existing_jobs($job, $signature);
        }

        $this->enqueue_job($job, 0);
    }

    private function schedule_retry(array $job, $result = null) {
        if ($job['attempt'] >= $this->max_attempts) {
            return false;
        }

        if ($result instanceof WP_Error) {
            $data = $result->get_error_data();
            if (is_array($data) && array_key_exists('should_retry', $data) && false === $data['should_retry']) {
                return false;
            }
        }

        $next_attempt = $job['attempt'] + 1;
        $delay = $this->calculate_backoff_delay($next_attempt, $result);

        $job['attempt'] = $next_attempt;
        $this->log_job_event('retry', $job, microtime(true), array(
            'retry_after' => $delay,
        ));

        $this->enqueue_job($job, $delay);
        return true;
    }

    private function enqueue_job(array $job, $delay) {
        $timestamp = time() + max(0, (int) $delay);

        if ($this->supports_action_scheduler()) {
            as_schedule_single_action($timestamp, self::JOB_HOOK, array($job), self::ACTION_SCHEDULER_GROUP);
            return;
        }

        wp_schedule_single_event($timestamp, self::JOB_HOOK, array($job));
    }

    private function log_job_event($outcome, array $job, $start_time, array $extra = array()) {
        if (!($this->event_logger instanceof Discord_Bot_JLG_Event_Logger)) {
            return;
        }

        $context = array_merge(
            array(
                'channel'     => 'queue',
                'job_type'    => $job['type'],
                'profile_key' => $job['profile_key'],
                'server_id'   => $job['server_id'],
                'attempt'     => $job['attempt'],
                'outcome'     => $outcome,
                'duration_ms' => $this->calculate_duration_ms($start_time),
            ),
            $extra
        );

        $this->event_logger->log('discord_connector', $context);
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

    private function calculate_backoff_delay($attempt, $result = null) {
        $delay = $this->base_delay * pow(2, max(0, $attempt - 2));
        $delay = min(1800, max($this->base_delay, (int) $delay));

        if ($result instanceof WP_Error) {
            $data = $result->get_error_data();
            if (is_array($data) && isset($data['retry_after'])) {
                $retry_after = (int) $data['retry_after'];
                if ($retry_after > 0) {
                    $delay = max($delay, $retry_after);
                }
            }
        }

        return (int) $delay;
    }

    private function supports_action_scheduler() {
        return function_exists('as_schedule_single_action')
            && function_exists('as_next_scheduled_action')
            && function_exists('as_unschedule_action');
    }

    private function is_job_already_scheduled(array $job, $signature) {
        $origin = isset($job['origin']) ? $job['origin'] : 'cron';

        for ($attempt = 1; $attempt <= $this->max_attempts; $attempt++) {
            $args = array(
                'type'        => $job['type'],
                'profile_key' => $job['profile_key'],
                'server_id'   => $job['server_id'],
                'attempt'     => $attempt,
                'signature'   => $signature,
                'origin'      => $origin,
            );

            if ($this->supports_action_scheduler()) {
                $existing = as_next_scheduled_action(self::JOB_HOOK, $args, self::ACTION_SCHEDULER_GROUP);
                if (false !== $existing) {
                    return true;
                }
            } else {
                $timestamp = wp_next_scheduled(self::JOB_HOOK, array($args));
                if (false !== $timestamp) {
                    return true;
                }
            }
        }

        return false;
    }

    private function unschedule_existing_jobs(array $job, $signature) {
        $origin = isset($job['origin']) ? $job['origin'] : 'cron';

        $base_args = array(
            'type'        => $job['type'],
            'profile_key' => $job['profile_key'],
            'server_id'   => $job['server_id'],
            'signature'   => $signature,
            'origin'      => $origin,
        );

        if ($this->supports_action_scheduler()) {
            for ($attempt = 1; $attempt <= $this->max_attempts; $attempt++) {
                $args = $base_args;
                $args['attempt'] = $attempt;
                as_unschedule_action(self::JOB_HOOK, $args, self::ACTION_SCHEDULER_GROUP);
            }
            return;
        }

        for ($attempt = 1; $attempt <= $this->max_attempts; $attempt++) {
            $args = $base_args;
            $args['attempt'] = $attempt;
            $timestamp = wp_next_scheduled(self::JOB_HOOK, array($args));
            while (false !== $timestamp) {
                wp_unschedule_event($timestamp, self::JOB_HOOK, array($args));
                $timestamp = wp_next_scheduled(self::JOB_HOOK, array($args));
            }
        }
    }

    private function sanitize_server_id($value) {
        if (!is_string($value) && !is_numeric($value)) {
            return '';
        }

        $value = preg_replace('/[^0-9]/', '', (string) $value);
        return (string) $value;
    }
}

