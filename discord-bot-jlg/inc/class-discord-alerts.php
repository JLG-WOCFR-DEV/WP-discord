<?php
if (!defined('ABSPATH')) {
    exit;
}

class Discord_Bot_JLG_Alerts {
    const DEFAULT_DROP_PERCENT = 35;
    const DEFAULT_COOLDOWN_MINUTES = 60;
    const TRANSIENT_PREFIX = 'discord_bot_jlg_alert_lock_';

    /**
     * @var Discord_Bot_JLG_Options_Repository
     */
    private $options_repository;

    /**
     * @var Discord_Bot_JLG_Analytics
     */
    private $analytics;

    /**
     * @var Discord_Bot_JLG_Event_Logger|null
     */
    private $event_logger;

    public function __construct(
        Discord_Bot_JLG_Options_Repository $options_repository,
        Discord_Bot_JLG_Analytics $analytics,
        $event_logger = null
    ) {
        $this->options_repository = $options_repository;
        $this->analytics = $analytics;
        $this->event_logger = ($event_logger instanceof Discord_Bot_JLG_Event_Logger)
            ? $event_logger
            : null;
    }

    public function maybe_dispatch_alert($profile_key, $server_id, array $stats) {
        $options = $this->options_repository->get_options();

        if (empty($options['analytics_alerts_enabled'])) {
            return;
        }

        $current_value = $this->extract_presence_signal($stats);
        if (null === $current_value) {
            return;
        }

        $baseline = $this->calculate_baseline($profile_key, $server_id);
        if (null === $baseline || $baseline <= 0) {
            return;
        }

        $drop_percent_option = isset($options['analytics_alert_drop_percent'])
            ? (int) $options['analytics_alert_drop_percent']
            : self::DEFAULT_DROP_PERCENT;
        $drop_percent_option = max(1, min(95, $drop_percent_option));

        $percentage = $this->calculate_drop_percentage($baseline, $current_value);
        if ($percentage < $drop_percent_option) {
            return;
        }

        $recipients = $this->parse_recipients(isset($options['analytics_alert_recipients'])
            ? $options['analytics_alert_recipients']
            : ''
        );
        $webhook = isset($options['analytics_alert_webhook'])
            ? esc_url_raw($options['analytics_alert_webhook'])
            : '';

        if (empty($recipients) && '' === $webhook) {
            return;
        }

        $cooldown_minutes = isset($options['analytics_alert_cooldown'])
            ? max(5, (int) $options['analytics_alert_cooldown'])
            : self::DEFAULT_COOLDOWN_MINUTES;

        if ($this->is_rate_limited($profile_key, $server_id, $cooldown_minutes)) {
            return;
        }

        $profile_label = $this->resolve_profile_label($profile_key, $options);

        $payload = array(
            'profile_key'        => $profile_key,
            'profile_label'      => $profile_label,
            'server_id'          => $server_id,
            'current_presence'   => $current_value,
            'baseline_presence'  => $baseline,
            'drop_percentage'    => $percentage,
            'threshold'          => $drop_percent_option,
            'timestamp'          => $this->current_timestamp(),
        );

        if (function_exists('apply_filters')) {
            $payload = apply_filters('discord_bot_jlg_alert_payload', $payload, $stats, $options);
        }

        $channels = array();

        if (!empty($recipients)) {
            $email_sent = $this->dispatch_email($recipients, $payload, $options);
            if ($email_sent) {
                $channels[] = 'email';
            }
        }

        if ('' !== $webhook) {
            $webhook_sent = $this->dispatch_webhook($webhook, $payload, $options);
            if ($webhook_sent) {
                $channels[] = 'webhook';
            }
        }

        if (empty($channels)) {
            return;
        }

        $this->remember_alert($profile_key, $server_id, $cooldown_minutes);
        $this->log_event($payload, $channels);

        if (function_exists('do_action')) {
            do_action('discord_bot_jlg_alert_dispatched', $payload, $channels, $stats, $options);
        }
    }

    private function extract_presence_signal(array $stats) {
        if (isset($stats['approximate_presence_count']) && null !== $stats['approximate_presence_count']) {
            return max(0, (int) $stats['approximate_presence_count']);
        }

        if (isset($stats['presence']) && null !== $stats['presence']) {
            return max(0, (int) $stats['presence']);
        }

        if (isset($stats['online']) && null !== $stats['online']) {
            return max(0, (int) $stats['online']);
        }

        if (isset($stats['online_count']) && null !== $stats['online_count']) {
            return max(0, (int) $stats['online_count']);
        }

        return null;
    }

    private function calculate_baseline($profile_key, $server_id) {
        $aggregates = $this->analytics->get_aggregates(
            array(
                'profile_key' => $profile_key,
                'server_id'   => $server_id,
                'days'        => 7,
            )
        );

        if (!is_array($aggregates) || empty($aggregates['timeseries'])) {
            return null;
        }

        $timeseries = array_values($aggregates['timeseries']);
        $total_points = count($timeseries);
        if ($total_points < 4) {
            return null;
        }

        $values = array();
        for ($i = 0; $i < $total_points - 1; $i++) {
            $point = $timeseries[$i];
            $value = $this->extract_presence_signal($point);
            if (null === $value) {
                continue;
            }
            $values[] = $value;
        }

        if (count($values) < 3) {
            return null;
        }

        return array_sum($values) / count($values);
    }

    private function calculate_drop_percentage($baseline, $current) {
        if ($baseline <= 0) {
            return 0;
        }

        $ratio = 1 - ($current / $baseline);
        $percentage = round($ratio * 100, 2);

        if ($percentage < 0) {
            return 0;
        }

        return $percentage;
    }

    private function parse_recipients($raw_value) {
        if (!is_string($raw_value)) {
            return array();
        }

        $candidates = preg_split('/[\s,;]+/', $raw_value);
        if (!is_array($candidates)) {
            return array();
        }

        $valid = array();

        foreach ($candidates as $candidate) {
            $candidate = trim($candidate);
            if ('' === $candidate) {
                continue;
            }

            if (is_email($candidate)) {
                $valid[] = $candidate;
            }
        }

        return array_values(array_unique($valid));
    }

    private function resolve_profile_label($profile_key, array $options) {
        if (isset($options['server_profiles']) && is_array($options['server_profiles'])) {
            if (isset($options['server_profiles'][$profile_key]) && is_array($options['server_profiles'][$profile_key])) {
                $label = isset($options['server_profiles'][$profile_key]['label'])
                    ? sanitize_text_field($options['server_profiles'][$profile_key]['label'])
                    : '';

                if ('' !== $label) {
                    return $label;
                }
            }
        }

        if ('default' === $profile_key) {
            return __('Profil principal', 'discord-bot-jlg');
        }

        $sanitized_key = discord_bot_jlg_sanitize_profile_key($profile_key);

        if ('' === $sanitized_key) {
            return __('Profil Discord', 'discord-bot-jlg');
        }

        return sprintf(
            /* translators: %s: profile key. */
            __('Profil %s', 'discord-bot-jlg'),
            $sanitized_key
        );
    }

    private function is_rate_limited($profile_key, $server_id, $cooldown_minutes) {
        $key = $this->build_lock_key($profile_key, $server_id);

        $last_trigger = get_transient($key);
        if (false !== $last_trigger) {
            return true;
        }

        return false;
    }

    private function remember_alert($profile_key, $server_id, $cooldown_minutes) {
        $key = $this->build_lock_key($profile_key, $server_id);
        $multiplier = defined('MINUTE_IN_SECONDS') ? MINUTE_IN_SECONDS : 60;
        $cooldown_seconds = max(300, $cooldown_minutes * $multiplier);
        set_transient($key, $this->current_timestamp(), $cooldown_seconds);
    }

    private function dispatch_email(array $recipients, array $payload, array $options) {
        $subject = sprintf(
            /* translators: %s: profile label. */
            __('Discord Bot – activité en baisse sur %s', 'discord-bot-jlg'),
            $payload['profile_label']
        );

        $lines = array();
        $lines[] = sprintf(
            /* translators: 1: profile label, 2: drop percentage. */
            __('La présence a diminué de %2$s %% pour %1$s.', 'discord-bot-jlg'),
            $payload['profile_label'],
            number_format_i18n($payload['drop_percentage'], 2)
        );

        $lines[] = sprintf(
            /* translators: 1: current presence, 2: baseline presence. */
            __('Présence actuelle : %1$s membres (baseline 7 jours : %2$s).', 'discord-bot-jlg'),
            number_format_i18n($payload['current_presence']),
            number_format_i18n($payload['baseline_presence'])
        );

        if (!empty($payload['server_id'])) {
            $lines[] = sprintf(
                /* translators: %s: Discord server ID. */
                __('Serveur Discord : %s', 'discord-bot-jlg'),
                $payload['server_id']
            );
        }

        $lines[] = sprintf(
            /* translators: %s: alert threshold percent. */
            __('Seuil d’alerte configuré : %s %%', 'discord-bot-jlg'),
            number_format_i18n($payload['threshold'])
        );

        $message = implode("\n", $lines);

        if (function_exists('apply_filters')) {
            $subject = apply_filters('discord_bot_jlg_alert_email_subject', $subject, $payload, $recipients, $options);
            $message = apply_filters('discord_bot_jlg_alert_email_message', $message, $payload, $recipients, $options);
        }

        $sent = wp_mail($recipients, $subject, $message);

        if (!$sent && $this->event_logger instanceof Discord_Bot_JLG_Event_Logger) {
            $this->event_logger->log(
                'alert_error',
                array(
                    'channel'    => 'email',
                    'profile'    => $payload['profile_key'],
                    'server_id'  => $payload['server_id'],
                    'recipients' => $recipients,
                )
            );
        }

        return (bool) $sent;
    }

    private function dispatch_webhook($webhook, array $payload, array $options) {
        if (!function_exists('wp_remote_post')) {
            return false;
        }

        $body = wp_json_encode($payload);

        $response = wp_remote_post(
            $webhook,
            array(
                'timeout' => 10,
                'headers' => array('Content-Type' => 'application/json'),
                'body'    => $body,
            )
        );

        if (is_wp_error($response)) {
            if ($this->event_logger instanceof Discord_Bot_JLG_Event_Logger) {
                $this->event_logger->log(
                    'alert_error',
                    array(
                        'channel'   => 'webhook',
                        'profile'   => $payload['profile_key'],
                        'server_id' => $payload['server_id'],
                        'message'   => $response->get_error_message(),
                    )
                );
            }
            return false;
        }

        $status = wp_remote_retrieve_response_code($response);

        if ($status < 200 || $status >= 300) {
            if ($this->event_logger instanceof Discord_Bot_JLG_Event_Logger) {
                $this->event_logger->log(
                    'alert_error',
                    array(
                        'channel'   => 'webhook',
                        'profile'   => $payload['profile_key'],
                        'server_id' => $payload['server_id'],
                        'status'    => $status,
                    )
                );
            }
            return false;
        }

        return true;
    }

    private function log_event(array $payload, array $channels) {
        if (!($this->event_logger instanceof Discord_Bot_JLG_Event_Logger)) {
            return;
        }

        $this->event_logger->log(
            'alert',
            array(
                'profile'          => $payload['profile_key'],
                'profile_label'    => $payload['profile_label'],
                'server_id'        => $payload['server_id'],
                'drop_percentage'  => $payload['drop_percentage'],
                'threshold'        => $payload['threshold'],
                'current_presence' => $payload['current_presence'],
                'baseline'         => $payload['baseline_presence'],
                'channels'         => $channels,
            )
        );
    }

    private function build_lock_key($profile_key, $server_id) {
        $signature = sprintf('%s|%s', $profile_key, $server_id);
        return self::TRANSIENT_PREFIX . md5($signature);
    }

    private function current_timestamp() {
        if (function_exists('current_time')) {
            return (int) current_time('timestamp', true);
        }

        return time();
    }
}
