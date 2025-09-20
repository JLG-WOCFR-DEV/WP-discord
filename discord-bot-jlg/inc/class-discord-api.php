<?php

if (false === defined('ABSPATH')) {
    exit;
}

/**
 * Fournit les appels à l'API Discord ainsi que la gestion du cache et des données de démonstration.
 */
class Discord_Bot_JLG_API {

    const MIN_PUBLIC_REFRESH_INTERVAL = 10;

    private $option_name;
    private $cache_key;
    private $default_cache_duration;

    /**
     * Prépare le service d'accès aux statistiques avec les clés et durées nécessaires.
     *
     * @param string $option_name            Nom de l'option contenant la configuration du plugin.
     * @param string $cache_key              Clef de cache utilisée pour mémoriser les statistiques.
     * @param int    $default_cache_duration Durée du cache utilisée à défaut (en secondes).
     *
     * @return void
     */
    public function __construct($option_name, $cache_key, $default_cache_duration = 300) {
        $this->option_name = $option_name;
        $this->cache_key = $cache_key;
        $this->default_cache_duration = (int) $default_cache_duration;
    }

    /**
     * Récupère les statistiques du serveur en tenant compte du cache, du mode démo ou d'un forçage explicite.
     *
     * @param array $args {
     *     Arguments permettant de modifier le comportement de récupération.
     *
     *     @type bool $force_demo   Si vrai, renvoie systématiquement les statistiques de démonstration.
     *     @type bool $bypass_cache Si vrai, ignore la valeur mise en cache et interroge l'API directement.
     * }
     *
     * @return array Statistiques du serveur (clés `online`, `total`, `server_name`, éventuellement `is_demo`).
     */
    public function get_stats($args = array()) {
        $args = wp_parse_args(
            $args,
            array(
                'force_demo'   => false,
                'bypass_cache' => false,
            )
        );

        if (true === $args['force_demo']) {
            return $this->get_demo_stats();
        }

        $options = get_option($this->option_name);
        if (false === is_array($options)) {
            $options = array();
        }

        if (!empty($options['demo_mode'])) {
            return $this->get_demo_stats();
        }

        if (false === $args['bypass_cache']) {
            $cached_stats = get_transient($this->cache_key);
            if (false !== $cached_stats) {
                return $cached_stats;
            }
        }

        if (empty($options['server_id'])) {
            return $this->get_demo_stats();
        }

        $stats = false;
        $widget_stats = $this->get_stats_from_widget($options);

        if (is_array($widget_stats)) {
            $widget_stats = $this->normalize_stats($widget_stats);
            $stats        = $widget_stats;
        }

        $widget_incomplete = $this->stats_need_completion($widget_stats);

        $bot_stats  = false;
        $bot_token = $this->get_bot_token($options);

        if (!empty($bot_token)) {
            $bot_stats = $this->get_stats_from_bot($options);

            if (is_array($bot_stats)) {
                $bot_stats = $this->normalize_stats($bot_stats);

                if (true === $widget_incomplete && is_array($widget_stats)) {
                    $total             = null;
                    $has_total         = false;
                    $total_approximate = false;

                    if (!empty($widget_stats['has_total'])) {
                        $total             = $widget_stats['total'];
                        $has_total         = true;
                        $total_approximate = !empty($widget_stats['total_is_approximate']);
                    } elseif (!empty($bot_stats['has_total'])) {
                        $total             = $bot_stats['total'];
                        $has_total         = true;
                        $total_approximate = !empty($bot_stats['total_is_approximate']);
                    }

                    $stats = array(
                        'online'               => isset($widget_stats['online']) ? (int) $widget_stats['online'] : (isset($bot_stats['online']) ? (int) $bot_stats['online'] : 0),
                        'total'                => $has_total ? $total : null,
                        'server_name'          => !empty($widget_stats['server_name'])
                            ? $widget_stats['server_name']
                            : (isset($bot_stats['server_name']) ? $bot_stats['server_name'] : ''),
                        'has_total'            => $has_total,
                        'total_is_approximate' => $total_approximate,
                    );
                } elseif (false === $stats) {
                    $stats = $bot_stats;
                }
            }
        }

        if (is_array($stats)) {
            $stats = $this->normalize_stats($stats);
        }

        if (false === $this->has_usable_stats($stats)) {
            return $this->get_demo_stats();
        }

        set_transient($this->cache_key, $stats, $this->get_cache_duration($options));

        return $stats;
    }

    /**
     * Gère la requête AJAX d'actualisation des statistiques et renvoie une réponse JSON.
     *
     * @return void
     */
    public function ajax_refresh_stats() {
        $current_action     = current_action();
        $is_public_request  = ('wp_ajax_nopriv_refresh_discord_stats' === $current_action);
        $nonce              = isset($_POST['_ajax_nonce']) ? sanitize_text_field(wp_unslash($_POST['_ajax_nonce'])) : '';

        if (empty($nonce) || !wp_verify_nonce($nonce, 'refresh_discord_stats')) {
            if (true === $is_public_request) {
                wp_send_json_error(
                    array(
                        'nonce_expired' => true,
                        'new_nonce'     => wp_create_nonce('refresh_discord_stats'),
                    )
                );
            }

            wp_send_json_error('Nonce invalide', 403);
        }

        $options = get_option($this->option_name);
        if (false === is_array($options)) {
            $options = array();
        }

        if (!empty($options['demo_mode'])) {
            wp_send_json_error('Mode démo actif');
        }

        $rate_limit_key = $this->cache_key . '_refresh_lock';
        $cache_duration = $this->get_cache_duration($options);
        $default_public_refresh = max(self::MIN_PUBLIC_REFRESH_INTERVAL, (int) $cache_duration);
        $rate_limit_window = (int) apply_filters('discord_bot_jlg_public_refresh_interval', $default_public_refresh, $options);
        if ($rate_limit_window < self::MIN_PUBLIC_REFRESH_INTERVAL) {
            $rate_limit_window = self::MIN_PUBLIC_REFRESH_INTERVAL;
        }

        $refresh_requires_remote_call = false;

        if (true === $is_public_request) {
            $cached_stats = get_transient($this->cache_key);
            if (is_array($cached_stats) && empty($cached_stats['is_demo'])) {
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

            $refresh_requires_remote_call = true;
        }

        $force_refresh = false;

        if (
            false === $is_public_request
            && isset($_POST['force_refresh'])
            && current_user_can('manage_options')
        ) {
            $force_refresh = wp_validate_boolean(wp_unslash($_POST['force_refresh']));
        }

        $stats = $this->get_stats(
            array(
                'bypass_cache' => $force_refresh,
            )
        );

        if (
            true === $is_public_request
            && true === $refresh_requires_remote_call
            && is_array($stats)
            && empty($stats['is_demo'])
        ) {
            set_transient($rate_limit_key, time(), $rate_limit_window);
        }

        if (is_array($stats) && empty($stats['is_demo'])) {
            wp_send_json_success($stats);
        }

        if (true === $is_public_request) {
            $cached_stats = get_transient($this->cache_key);
            if (is_array($cached_stats) && empty($cached_stats['is_demo'])) {
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

        wp_send_json_error('Impossible de récupérer les stats');
    }

    /**
     * Supprime les statistiques mises en cache pour forcer une prochaine récupération.
     *
     * @return void
     */
    public function clear_cache() {
        delete_transient($this->cache_key);
    }

    /**
     * Génère des statistiques fictives pour la démonstration ou en cas d'absence de configuration.
     *
     * @return array Statistiques de démonstration comprenant les clés `online`, `total`, `server_name` et `is_demo`.
     */
    public function get_demo_stats() {
        $base_online = 42;
        $base_total  = 256;

        $hour      = (int) date('H');
        $variation = sin($hour * 0.26) * 10;

        return array(
            'online'               => (int) round($base_online + $variation),
            'total'                => (int) $base_total,
            'server_name'          => 'Serveur Démo',
            'is_demo'              => true,
            'has_total'            => true,
            'total_is_approximate' => false,
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
            error_log('Discord API error (widget): ' . $response->get_error_message());
            return false;
        }

        $response_code = (int) wp_remote_retrieve_response_code($response);
        if (200 !== $response_code) {
            error_log('Discord API error (widget): HTTP ' . $response_code);
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (false === is_array($data)) {
            return false;
        }

        $has_presence_count = isset($data['presence_count']);
        $has_members_list   = isset($data['members']) && is_array($data['members']);

        if (false === $has_presence_count && false === $has_members_list) {
            return false;
        }

        $online = $has_presence_count
            ? (int) $data['presence_count']
            : (int) count($data['members']);

        $server_name = isset($data['name']) ? $data['name'] : '';

        $stats = array(
            'online'               => $online,
            'total'                => null,
            'server_name'          => $server_name,
            'has_total'            => false,
            'total_is_approximate' => false,
        );

        if (isset($data['member_count'])) {
            $stats['total']     = (int) $data['member_count'];
            $stats['has_total'] = true;
        } elseif ($has_members_list) {
            // The widget exposes the list of displayed members (usually online ones) but not the full roster.
            $stats['total']                = null;
            $stats['has_total']            = false;
            $stats['total_is_approximate'] = true;
        } else {
            $stats['total_is_approximate'] = true;
        }

        return $stats;
    }

    private function get_stats_from_bot($options) {
        $bot_token = $this->get_bot_token($options);

        if (empty($bot_token)) {
            return false;
        }

        $api_url = 'https://discord.com/api/v10/guilds/' . $options['server_id'] . '?with_counts=true';

        $response = wp_safe_remote_get(
            $api_url,
            array(
                'timeout' => 10,
                'headers' => array(
                    'Authorization' => 'Bot ' . $bot_token,
                    'User-Agent'    => 'WordPress Discord Stats Plugin',
                ),
            )
        );

        if (is_wp_error($response)) {
            error_log('Discord API error (bot): ' . $response->get_error_message());
            return false;
        }

        $response_code = (int) wp_remote_retrieve_response_code($response);
        if (200 !== $response_code) {
            error_log('Discord API error (bot): HTTP ' . $response_code);
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (false === is_array($data)) {
            return false;
        }

        if (false === isset($data['approximate_presence_count'], $data['approximate_member_count'])) {
            return false;
        }

        return array(
            'online'               => (int) $data['approximate_presence_count'],
            'total'                => (int) $data['approximate_member_count'],
            'server_name'          => isset($data['name']) ? $data['name'] : '',
            'has_total'            => true,
            // Discord renvoie uniquement un total approximatif via approximate_member_count.
            'total_is_approximate' => true,
        );
    }

    private function has_usable_stats($stats) {
        return (
            is_array($stats)
            && isset($stats['online'])
            && is_numeric($stats['online'])
        );
    }

    private function stats_need_completion($stats) {
        if (false === is_array($stats)) {
            return false;
        }

        if (empty($stats['has_total'])) {
            return true;
        }

        if (!empty($stats['total_is_approximate'])) {
            return true;
        }

        if ((int) $stats['total'] === (int) $stats['online']) {
            return true;
        }

        if (empty($stats['server_name'])) {
            return true;
        }

        return false;
    }

    private function get_bot_token($options) {
        if (defined('DISCORD_BOT_JLG_TOKEN') && '' !== DISCORD_BOT_JLG_TOKEN) {
            return DISCORD_BOT_JLG_TOKEN;
        }

        return isset($options['bot_token']) ? $options['bot_token'] : '';
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

    private function normalize_stats($stats) {
        if (!is_array($stats)) {
            return $stats;
        }

        if (!array_key_exists('total', $stats)) {
            $stats['total'] = null;
        }

        if (!isset($stats['server_name'])) {
            $stats['server_name'] = '';
        }

        if (!isset($stats['has_total'])) {
            $stats['has_total'] = (null !== $stats['total']);
        }

        if (!isset($stats['total_is_approximate'])) {
            $stats['total_is_approximate'] = false;
        }

        return $stats;
    }
}
