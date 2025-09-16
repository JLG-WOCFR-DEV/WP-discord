<?php

if (!defined('ABSPATH')) {
    exit;
}

class Discord_Bot_JLG_API {

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
            wp_send_json_error('Nonce invalide', 403);
        }

        $options = get_option($this->option_name);
        if (!is_array($options)) {
            $options = array();
        }

        if (!empty($options['demo_mode'])) {
            wp_send_json_error('Mode démo actif');
        }

        $stats = $this->get_stats(
            array(
                'bypass_cache' => true,
            )
        );

        if (is_array($stats) && empty($stats['is_demo'])) {
            wp_send_json_success($stats);
        }

        wp_send_json_error('Impossible de récupérer les stats');
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
            'server_name' => 'Serveur Démo',
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

        if (!is_array($data) || !isset($data['presence_count'], $data['member_count'])) {
            return false;
        }

        return array(
            'online'      => (int) $data['presence_count'],
            'total'       => (int) $data['member_count'],
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
