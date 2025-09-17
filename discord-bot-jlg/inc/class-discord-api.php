<?php

if (!defined('ABSPATH')) {
    exit;
}

class Discord_Bot_JLG_API {

    const MIN_PUBLIC_REFRESH_INTERVAL = 10;

    private $option_name;
    private $cache_key;
    private $default_cache_duration;

    public function __construct($option_name, $cache_key, $default_cache_duration = 300) {
        $this->option_name = $option_name;
        $this->cache_key = $cache_key;
        $this->default_cache_duration = (int) $default_cache_duration;
    }

    public function get_stats($args = array()) {
        $args = wp_parse_args(
            $args,
            array(
                'force_demo'   => false,
                'bypass_cache' => false,
            )
        );

        if ($args['force_demo']) {
            return $this->get_demo_stats();
        }

        $options = get_option($this->option_name);
        if (!is_array($options)) {
            $options = array();
        }

        if (!empty($options['demo_mode'])) {
            return $this->get_demo_stats();
        }

        if (!$args['bypass_cache']) {
            $cached_stats = get_transient($this->cache_key);
            if (false !== $cached_stats) {
                return $cached_stats;
            }
        }

        if (empty($options['server_id']) || empty($options['bot_token'])) {
            return $this->get_demo_stats();
        }

        $stats = $this->get_stats_from_widget($options);

        if (!$stats) {
            $stats = $this->get_stats_from_bot($options);
        }

        if (!$stats) {
            return $this->get_demo_stats();
        }

        set_transient($this->cache_key, $stats, $this->get_cache_duration($options));

        return $stats;
    }

    public function ajax_refresh_stats() {
        $nonce = isset($_POST['_ajax_nonce']) ? sanitize_text_field(wp_unslash($_POST['_ajax_nonce'])) : '';

        if (empty($nonce) || !wp_verify_nonce($nonce, 'refresh_discord_stats')) {
            wp_send_json_error(esc_html__('Nonce invalide', 'discord-bot-jlg'), 403);
        }

        $options = get_option($this->option_name);
        if (!is_array($options)) {
            $options = array();
        }

        if (!empty($options['demo_mode'])) {
            wp_send_json_error(esc_html__('Mode démo actif', 'discord-bot-jlg'));
        }

        $current_action = current_action();
        $is_public_request = ('wp_ajax_nopriv_refresh_discord_stats' === $current_action);

        $rate_limit_key = $this->cache_key . '_refresh_lock';
        $cache_duration = $this->get_cache_duration($options);
        $default_public_refresh = max(self::MIN_PUBLIC_REFRESH_INTERVAL, (int) $cache_duration);
        $rate_limit_window = (int) apply_filters('discord_bot_jlg_public_refresh_interval', $default_public_refresh, $options);
        if ($rate_limit_window < self::MIN_PUBLIC_REFRESH_INTERVAL) {
            $rate_limit_window = self::MIN_PUBLIC_REFRESH_INTERVAL;
        }

        if ($is_public_request) {
            $cached_stats = get_transient($this->cache_key);
            if (is_array($cached_stats) && empty($cached_stats['is_demo'])) {
                set_transient($rate_limit_key, time(), $rate_limit_window);
                wp_send_json_success($cached_stats);
            }

            $last_refresh = get_transient($rate_limit_key);
            if (false !== $last_refresh) {
                $elapsed = time() - (int) $last_refresh;
                if ($elapsed < $rate_limit_window) {
                    $retry_after = max(0, $rate_limit_window - $elapsed);
                    $message = sprintf(
                        __('Veuillez patienter %d secondes avant la prochaine actualisation.', 'discord-bot-jlg'),
                        $retry_after
                    );

                    wp_send_json_error(
                        array(
                            'rate_limited' => true,
                            'message'      => $message,
                            'retry_after'  => $retry_after,
                        ),
                        429
                    );
                }
            }

        }

        $stats = $this->get_stats(
            array(
                'bypass_cache' => !$is_public_request,
            )
        );

        if (is_array($stats) && empty($stats['is_demo'])) {
            if ($is_public_request) {
                set_transient($rate_limit_key, time(), $rate_limit_window);
            }
            wp_send_json_success($stats);
        }

        if ($is_public_request) {
            $cached_stats = get_transient($this->cache_key);
            if (is_array($cached_stats) && empty($cached_stats['is_demo'])) {
                set_transient($rate_limit_key, time(), $rate_limit_window);
                wp_send_json_success($cached_stats);
            }

            delete_transient($rate_limit_key);

            wp_send_json_error(
                array(
                    'rate_limited' => false,
                    'message'      => __('Actualisation en cours, veuillez réessayer dans quelques instants.', 'discord-bot-jlg'),
                ),
                503
            );
        }

        delete_transient($rate_limit_key);

        wp_send_json_error(esc_html__('Impossible de récupérer les stats', 'discord-bot-jlg'));
    }

    public function clear_cache() {
        delete_transient($this->cache_key);
    }

    public function get_demo_stats() {
        $base_online = 42;
        $base_total  = 256;

        $hour      = (int) date('H');
        $variation = sin($hour * 0.26) * 10;

        return array(
            'online'    => (int) round($base_online + $variation),
            'total'     => (int) $base_total,
            'server_name' => __('Serveur Démo', 'discord-bot-jlg'),
            'is_demo'   => true,
        );
    }

    private function get_stats_from_widget($options) {
        $widget_url = 'https://discord.com/api/guilds/' . $options['server_id'] . '/widget.json';

        $response = wp_safe_remote_get(
            $widget_url,
            array(
                'timeout' => 10,
                'headers' => array(
                    'User-Agent' => 'WordPress Discord Stats Plugin',
                ),
            )
        );

        if (is_wp_error($response)) {
            return false;
        }

        if (200 !== (int) wp_remote_retrieve_response_code($response)) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!is_array($data) || !isset($data['presence_count'])) {
            return false;
        }

        $online = (int) $data['presence_count'];

        $total = null;

        if (isset($data['member_count'])) {
            $total = (int) $data['member_count'];
        } elseif (isset($data['members']) && is_array($data['members'])) {
            // The widget exposes the list of displayed members (usually online ones) but not the full roster.
            $total = count($data['members']);
        }

        if (null === $total) {
            // Public widget payload lacks the total member count; fall back to online users to keep data usable.
            $total = $online;
        } elseif ($total < $online) {
            $total = $online;
        }

        return array(
            'online'      => $online,
            'total'       => $total,
            'server_name' => isset($data['name']) ? $data['name'] : '',
        );
    }

    private function get_stats_from_bot($options) {
        if (empty($options['bot_token'])) {
            return false;
        }

        $api_url = 'https://discord.com/api/v10/guilds/' . $options['server_id'] . '?with_counts=true';

        $response = wp_safe_remote_get(
            $api_url,
            array(
                'timeout' => 10,
                'headers' => array(
                    'Authorization' => 'Bot ' . $options['bot_token'],
                    'User-Agent'    => 'WordPress Discord Stats Plugin',
                ),
            )
        );

        if (is_wp_error($response)) {
            return false;
        }

        if (200 !== (int) wp_remote_retrieve_response_code($response)) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!is_array($data) || !isset($data['approximate_presence_count'], $data['approximate_member_count'])) {
            return false;
        }

        return array(
            'online'      => (int) $data['approximate_presence_count'],
            'total'       => (int) $data['approximate_member_count'],
            'server_name' => isset($data['name']) ? $data['name'] : '',
        );
    }

    private function get_cache_duration($options) {
        if (isset($options['cache_duration'])) {
            $duration = (int) $options['cache_duration'];
            if ($duration >= 60 && $duration <= 3600) {
                return $duration;
            }
        }

        return $this->default_cache_duration;
    }
}
