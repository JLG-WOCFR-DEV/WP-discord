<?php

if (false === defined('ABSPATH')) {
    exit;
}

if (!function_exists('discord_bot_jlg_validate_bool')) {
    require_once __DIR__ . '/helpers.php';
}

/**
 * Fournit les appels à l'API Discord ainsi que la gestion du cache et des données de démonstration.
 */
class Discord_Bot_JLG_API {

    const MIN_PUBLIC_REFRESH_INTERVAL = 10;

    const REFRESH_LOCK_SUFFIX = '_refresh_lock';
    const CLIENT_REFRESH_LOCK_PREFIX = '_refresh_lock_client_';
    const CLIENT_REFRESH_LOCK_INDEX_SUFFIX = '_refresh_lock_clients';
    const LAST_GOOD_SUFFIX = '_last_good';
    const FALLBACK_RETRY_SUFFIX = '_fallback_retry_after';
    const FALLBACK_RETRY_API_DELAY_SUFFIX = '_fallback_retry_after_delay';
    const LAST_FALLBACK_OPTION = 'discord_bot_jlg_last_fallback';
    const CACHE_REGISTRY_PREFIX = 'discord_bot_jlg_cache_registry_';

    private $option_name;
    private $cache_key;
    private $base_cache_key;
    private $default_cache_duration;
    private $last_error;
    private $runtime_cache;
    private $runtime_errors;
    private $last_retry_after;
    private $runtime_retry_after;
    private $http_client;
    private $options_cache;
    private $runtime_fallback_retry_timestamp;

    /**
     * Prépare le service d'accès aux statistiques avec les clés et durées nécessaires.
     *
     * @param string $option_name            Nom de l'option contenant la configuration du plugin.
     * @param string $cache_key              Clef de cache utilisée pour mémoriser les statistiques.
     * @param int    $default_cache_duration Durée du cache utilisée à défaut (en secondes).
     *
     * @return void
     */
    public function __construct($option_name, $cache_key, $default_cache_duration = 300, $http_client = null) {
        $this->option_name = $option_name;
        $this->base_cache_key = $cache_key;
        $this->cache_key = $cache_key;
        $this->default_cache_duration = (int) $default_cache_duration;
        $this->last_error = '';
        $this->runtime_cache = array();
        $this->runtime_errors = array();
        $this->last_retry_after = 0;
        $this->runtime_retry_after = array();
        $this->http_client = ($http_client instanceof Discord_Bot_JLG_Http_Client)
            ? $http_client
            : new Discord_Bot_JLG_Http_Client();
        $this->options_cache = null;
        $this->runtime_fallback_retry_timestamp = 0;
    }

    private function register_current_cache_key() {
        $this->remember_cache_key_for_registry($this->cache_key);
    }

    private function remember_cache_key_for_registry($cache_key) {
        $cache_key = (string) $cache_key;

        if ('' === $cache_key) {
            return;
        }

        $registry = $this->get_registered_cache_keys();

        if (in_array($cache_key, $registry, true)) {
            return;
        }

        $registry[] = $cache_key;
        $this->save_cache_key_registry($registry);
    }

    private function get_cache_registry_option_name() {
        return self::CACHE_REGISTRY_PREFIX . md5($this->base_cache_key);
    }

    private function get_registered_cache_keys() {
        $registry = get_option($this->get_cache_registry_option_name());

        if (!is_array($registry)) {
            return array();
        }

        $registry = array_filter(array_map('strval', $registry));

        return array_values(array_unique($registry));
    }

    private function save_cache_key_registry($registry) {
        if (!is_array($registry)) {
            $registry = array();
        }

        $registry = array_filter(array_map('strval', $registry));
        $registry = array_values(array_unique($registry));

        if (empty($registry)) {
            delete_option($this->get_cache_registry_option_name());
            return;
        }

        update_option($this->get_cache_registry_option_name(), $registry, false);
    }

    private function reset_cache_key_registry() {
        delete_option($this->get_cache_registry_option_name());
    }

    /**
     * Réinitialise le cache des options.
     *
     * @return void
     */
    public function flush_options_cache() {
        $this->options_cache = null;
    }

    /**
     * Récupère les options du plugin avec mise en cache en mémoire.
     *
     * @param bool $force_refresh Force une lecture depuis la base si vrai.
     *
     * @return array
     */
    public function get_plugin_options($force_refresh = false) {
        if (true === $force_refresh || !is_array($this->options_cache)) {
            $options = get_option($this->option_name);

            if (false === is_array($options)) {
                $options = array();
            }

            $this->options_cache = $options;
        }

        return $this->options_cache;
    }

    public function get_server_profiles($include_sensitive = false) {
        $options = $this->get_plugin_options();
        if (!is_array($options)) {
            $options = array();
        }

        $profiles = array();

        if (isset($options['server_profiles']) && is_array($options['server_profiles'])) {
            foreach ($options['server_profiles'] as $stored_key => $profile) {
                if (!is_array($profile)) {
                    continue;
                }

                $profile_key = '';

                if (isset($profile['key'])) {
                    $profile_key = sanitize_key($profile['key']);
                }

                if ('' === $profile_key) {
                    $profile_key = sanitize_key($stored_key);
                }

                if ('' === $profile_key) {
                    continue;
                }

                $label = isset($profile['label']) ? sanitize_text_field($profile['label']) : '';
                if ('' === $label) {
                    $label = $profile_key;
                }

                $server_id = isset($profile['server_id']) ? $this->sanitize_server_id($profile['server_id']) : '';
                $stored_token = isset($profile['bot_token']) ? (string) $profile['bot_token'] : '';

                $entry = array(
                    'key'       => $profile_key,
                    'label'     => $label,
                    'server_id' => $server_id,
                    'has_token' => ('' !== $stored_token),
                );

                if (true === $include_sensitive) {
                    $entry['bot_token'] = $stored_token;
                }

                $profiles[$profile_key] = $entry;
            }
        }

        return $profiles;
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
     * @return array Statistiques du serveur (clés `online`, `total`, `server_name`, `is_demo`, éventuellement `fallback_demo`).
     */
    public function get_stats($args = array()) {
        $this->last_error = '';
        $this->last_retry_after = 0;

        $args = wp_parse_args(
            $args,
            array(
                'force_demo'   => false,
                'bypass_cache' => false,
                'profile_key'  => '',
                'server_id'    => '',
            )
        );

        $args['force_demo']   = discord_bot_jlg_validate_bool($args['force_demo']);
        $args['bypass_cache'] = discord_bot_jlg_validate_bool($args['bypass_cache']);
        $args['profile_key']  = isset($args['profile_key']) ? sanitize_key($args['profile_key']) : '';
        $args['server_id']    = isset($args['server_id']) ? $this->sanitize_server_id($args['server_id']) : '';

        if (true === $args['force_demo']) {
            $demo_stats = $this->get_demo_stats(false);
            return $this->remember_runtime_result(
                $this->get_runtime_cache_key(
                    array(
                        'force_demo'   => true,
                        'bypass_cache' => $args['bypass_cache'],
                        'signature'    => 'forced-demo',
                    )
                ),
                $demo_stats
            );
        }

        $options = $this->get_plugin_options();

        $context = $this->resolve_connection_context($args, $options);
        $options = $context['options'];

        $runtime_args = array(
            'force_demo'   => $args['force_demo'],
            'bypass_cache' => $args['bypass_cache'],
            'signature'    => $context['signature'],
        );

        $runtime_key = $this->get_runtime_cache_key($runtime_args);

        if (array_key_exists($runtime_key, $this->runtime_cache)) {
            $this->last_error      = isset($this->runtime_errors[$runtime_key]) ? $this->runtime_errors[$runtime_key] : '';
            $this->last_retry_after = isset($this->runtime_retry_after[$runtime_key]) ? (int) $this->runtime_retry_after[$runtime_key] : 0;
            return $this->runtime_cache[$runtime_key];
        }

        if (!empty($context['error'])) {
            $error_message = ($context['error'] instanceof WP_Error)
                ? $context['error']->get_error_message()
                : (string) $context['error'];

            if ('' === $error_message) {
                $error_message = __('Profil de serveur introuvable.', 'discord-bot-jlg');
            }

            $this->last_error = $error_message;

            $demo_stats = $this->get_demo_stats(true);

            return $this->remember_runtime_result($runtime_key, $demo_stats);
        }

        if (!empty($options['demo_mode'])) {
            $demo_stats = $this->get_demo_stats(false);
            return $this->remember_runtime_result($runtime_key, $demo_stats);
        }

        $original_cache_key = $this->cache_key;
        $this->cache_key     = $context['cache_key'];

        try {
            if (false === $args['bypass_cache']) {
                $cached_stats = get_transient($this->cache_key);
                if (false !== $cached_stats) {
                    return $this->remember_runtime_result($runtime_key, $cached_stats);
                }
            }

            if (empty($options['server_id'])) {
                $this->last_error = __('Aucun identifiant de serveur Discord n\'est configuré.', 'discord-bot-jlg');
                $demo_stats = $this->persist_fallback_stats($this->get_demo_stats(true), $options, $this->last_error);
                return $this->remember_runtime_result($runtime_key, $demo_stats);
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
            $should_call_bot = (!empty($bot_token) && ($widget_incomplete || empty($widget_stats)));

            if ($should_call_bot) {
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
                            'server_avatar_url'    => '',
                            'server_avatar_base_url' => '',
                        );

                        if (!empty($widget_stats['server_avatar_url'])) {
                            $stats['server_avatar_url'] = $widget_stats['server_avatar_url'];
                        } elseif (!empty($bot_stats['server_avatar_url'])) {
                            $stats['server_avatar_url'] = $bot_stats['server_avatar_url'];
                        }

                        if (!empty($widget_stats['server_avatar_base_url'])) {
                            $stats['server_avatar_base_url'] = $widget_stats['server_avatar_base_url'];
                        } elseif (!empty($bot_stats['server_avatar_base_url'])) {
                            $stats['server_avatar_base_url'] = $bot_stats['server_avatar_base_url'];
                        }

                        $presence_breakdown = array();

                        foreach (array($widget_stats, $bot_stats) as $source_stats) {
                            if (!is_array($source_stats) || empty($source_stats['presence_count_by_status']) || !is_array($source_stats['presence_count_by_status'])) {
                                continue;
                            }

                            foreach ($source_stats['presence_count_by_status'] as $status_key => $count_value) {
                                if (!isset($presence_breakdown[$status_key])) {
                                    $presence_breakdown[$status_key] = 0;
                                }

                                $presence_breakdown[$status_key] += (int) $count_value;
                            }
                        }

                        if (!empty($presence_breakdown)) {
                            $stats['presence_count_by_status'] = $presence_breakdown;
                        } elseif (isset($widget_stats['presence_count_by_status'])) {
                            $stats['presence_count_by_status'] = $widget_stats['presence_count_by_status'];
                        } elseif (isset($bot_stats['presence_count_by_status'])) {
                            $stats['presence_count_by_status'] = $bot_stats['presence_count_by_status'];
                        }

                        if (isset($widget_stats['approximate_presence_count']) && null !== $widget_stats['approximate_presence_count']) {
                            $stats['approximate_presence_count'] = (int) $widget_stats['approximate_presence_count'];
                        } elseif (isset($bot_stats['approximate_presence_count'])) {
                            $stats['approximate_presence_count'] = (int) $bot_stats['approximate_presence_count'];
                        }

                        if (isset($bot_stats['approximate_member_count']) && null !== $bot_stats['approximate_member_count']) {
                            $stats['approximate_member_count'] = (int) $bot_stats['approximate_member_count'];
                        } elseif (isset($widget_stats['approximate_member_count']) && null !== $widget_stats['approximate_member_count']) {
                            $stats['approximate_member_count'] = (int) $widget_stats['approximate_member_count'];
                        }

                        if (isset($bot_stats['premium_subscription_count'])) {
                            $stats['premium_subscription_count'] = (int) $bot_stats['premium_subscription_count'];
                        } elseif (isset($widget_stats['premium_subscription_count'])) {
                            $stats['premium_subscription_count'] = (int) $widget_stats['premium_subscription_count'];
                        }
                    } elseif (false === $stats) {
                        $stats = $bot_stats;
                    }
                }
            }

            if (is_array($stats)) {
                $stats = $this->normalize_stats($stats);
            }

            if (false === $this->has_usable_stats($stats)) {
                if (empty($this->last_error)) {
                    $this->last_error = __('Impossible d\'obtenir des statistiques exploitables depuis Discord.', 'discord-bot-jlg');
                }
                $this->store_api_retry_after_delay($this->last_retry_after);
                $demo_stats = $this->persist_fallback_stats($this->get_demo_stats(true), $options, $this->last_error);
                return $this->remember_runtime_result($runtime_key, $demo_stats);
            }

            $this->last_error = '';
            $this->set_last_retry_after(0);
            $this->clear_api_retry_after_delay();
            $this->register_current_cache_key();
            set_transient($this->cache_key, $stats, $this->get_cache_duration($options));
            $this->store_last_good_stats($stats);
            $this->clear_last_fallback_details();

            return $this->remember_runtime_result($runtime_key, $stats);
        } finally {
            $this->cache_key = $original_cache_key;
        }
    }

    /**
     * Renvoie le dernier message d'erreur rencontré lors d'une récupération de statistiques.
     *
     * @return string
     */
    public function get_last_error_message() {
        return (string) $this->last_error;
    }

    public function get_last_fallback_details() {
        $option = get_option($this->get_last_fallback_option_name());

        if (!is_array($option)) {
            return array();
        }

        $timestamp = isset($option['timestamp']) ? (int) $option['timestamp'] : 0;
        $reason    = isset($option['reason']) ? (string) $option['reason'] : '';

        if ($timestamp <= 0 && '' === trim($reason)) {
            return array();
        }

        $details = array(
            'timestamp' => max(0, $timestamp),
            'reason'    => $reason,
        );

        $next_retry = $this->get_next_fallback_retry_timestamp();
        $details['next_retry'] = ($next_retry > 0) ? $next_retry : 0;

        return $details;
    }

    public function get_next_fallback_retry_timestamp() {
        $runtime_timestamp = $this->get_runtime_fallback_retry_timestamp();

        if ($runtime_timestamp > 0) {
            return $runtime_timestamp;
        }

        $stored_timestamp = (int) get_transient($this->get_fallback_retry_key());

        if ($stored_timestamp > 0) {
            return $stored_timestamp;
        }

        return 0;
    }

    /**
     * Gère la requête AJAX d'actualisation des statistiques et renvoie une réponse JSON.
     *
     * Les réponses publiques en erreur exposent la clé `retry_after` afin que le
     * frontal puisse respecter le délai communiqué par Discord lorsque disponible.
     *
     * @return void
     */
    public function ajax_refresh_stats() {
        $current_action     = current_action();
        $is_public_request  = ('wp_ajax_nopriv_refresh_discord_stats' === $current_action);
        $nonce              = isset($_POST['_ajax_nonce']) ? sanitize_text_field(wp_unslash($_POST['_ajax_nonce'])) : '';

        if (false === $is_public_request) {
            if (empty($nonce) || !wp_verify_nonce($nonce, 'refresh_discord_stats')) {
                wp_send_json_error(
                    array(
                        'nonce_expired' => true,
                        'message'       => __('Nonce invalide', 'discord-bot-jlg'),
                    ),
                    403
                );
            }
        }

        $profile_key_override = '';
        if (isset($_POST['profile_key'])) {
            $profile_key_override = sanitize_key(wp_unslash($_POST['profile_key']));
        }

        $server_id_override = '';
        if (isset($_POST['server_id'])) {
            $server_id_override = $this->sanitize_server_id(wp_unslash($_POST['server_id']));
        }

        $connection_args = array(
            'profile_key' => $profile_key_override,
            'server_id'   => $server_id_override,
        );

        $options = $this->get_plugin_options();

        $context = $this->resolve_connection_context($connection_args, $options);

        if (!empty($context['error'])) {
            $error_message = ($context['error'] instanceof WP_Error)
                ? $context['error']->get_error_message()
                : (string) $context['error'];

            if ('' === $error_message) {
                $error_message = __('Profil de serveur introuvable.', 'discord-bot-jlg');
            }

            wp_send_json_error(
                array(
                    'message' => $error_message,
                ),
                400
            );
        }

        $options = $context['options'];

        if (!empty($options['demo_mode'])) {
            wp_send_json_error(
                array(
                    'message' => __('Mode démo actif', 'discord-bot-jlg'),
                )
            );
        }

        $original_cache_key = $this->cache_key;
        $this->cache_key     = $context['cache_key'];

        try {
            $rate_limit_key        = $this->cache_key . self::REFRESH_LOCK_SUFFIX;
            $client_rate_limit_key = $this->get_client_rate_limit_key($is_public_request);
            $cache_duration        = $this->get_cache_duration($options);
            $default_public_refresh = max(self::MIN_PUBLIC_REFRESH_INTERVAL, (int) $cache_duration);
            $rate_limit_window = (int) apply_filters('discord_bot_jlg_public_refresh_interval', $default_public_refresh, $options);
            if ($rate_limit_window < self::MIN_PUBLIC_REFRESH_INTERVAL) {
                $rate_limit_window = self::MIN_PUBLIC_REFRESH_INTERVAL;
            }

            $refresh_requires_remote_call = false;
            $cached_stats                = get_transient($this->cache_key);
            $cached_stats_is_fallback    = (
                is_array($cached_stats)
                && !empty($cached_stats['is_demo'])
                && !empty($cached_stats['fallback_demo'])
            );

            $fallback_retry_key   = $this->get_fallback_retry_key();
            $fallback_retry_after = (int) get_transient($fallback_retry_key);
            if ($fallback_retry_after > 0) {
                $this->set_runtime_fallback_retry_timestamp($fallback_retry_after);
            }
            $now                  = time();

            if (true === $cached_stats_is_fallback && $fallback_retry_after <= 0) {
                $fallback_retry_after = $this->schedule_next_fallback_retry($cache_duration, $options);
            }

            $bypass_cache = false;

            if (true === $is_public_request) {
                if (!empty($client_rate_limit_key)) {
                    $client_retry_after = $this->get_retry_after($client_rate_limit_key, $rate_limit_window);

                    if ($client_retry_after > 0) {
                        $message = sprintf(
                            /* translators: %d: number of seconds to wait before the next refresh. */
                            __('Veuillez patienter %d secondes avant la prochaine actualisation.', 'discord-bot-jlg'),
                            $client_retry_after
                        );

                        wp_send_json_error(
                            array(
                                'rate_limited' => true,
                                'message'      => $message,
                                'retry_after'  => $client_retry_after,
                            ),
                            429
                        );
                    }
                }

                $last_refresh = get_transient($rate_limit_key);
                if (false !== $last_refresh) {
                    $elapsed = time() - (int) $last_refresh;
                    if ($elapsed < $rate_limit_window) {
                        $retry_after = max(0, $rate_limit_window - $elapsed);
                        $message = sprintf(
                            /* translators: %d: number of seconds to wait before the next refresh. */
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

                if ($cached_stats_is_fallback) {
                    if ($fallback_retry_after > $now) {
                        $response_payload = $cached_stats;

                        if (is_array($response_payload)) {
                            $next_retry = $this->get_runtime_fallback_retry_timestamp();
                            if ($next_retry <= 0) {
                                $next_retry = (int) $fallback_retry_after;
                            }
                            $response_payload['retry_after'] = max(0, (int) $next_retry - time());
                        }

                        wp_send_json_success($response_payload);
                    }

                    $refresh_requires_remote_call = true;
                    $bypass_cache = true;
                } elseif (is_array($cached_stats) && empty($cached_stats['is_demo'])) {
                    wp_send_json_success($cached_stats);
                } else {
                    $refresh_requires_remote_call = true;
                }
            }

            if (false === $is_public_request && $cached_stats_is_fallback) {
                $refresh_requires_remote_call = true;
                $bypass_cache = true;
            }

            if (isset($_POST['force_refresh'])) {
                $force_refresh = discord_bot_jlg_validate_bool(wp_unslash($_POST['force_refresh']));

                if (
                    true === $force_refresh
                    && false === $is_public_request
                    && current_user_can('manage_options')
                ) {
                    $bypass_cache = true;
                    $refresh_requires_remote_call = true;
                }
            }

            $stats = $this->get_stats(
                array(
                    'bypass_cache' => $bypass_cache,
                    'profile_key'  => $profile_key_override,
                    'server_id'    => $server_id_override,
                )
            );

            $last_error_message = $this->get_last_error_message();

            if (
                is_array($stats)
                && !empty($stats['is_demo'])
                && !empty($stats['fallback_demo'])
            ) {
                $this->schedule_next_fallback_retry($cache_duration, $options);
            } else {
                $this->clear_fallback_retry_schedule();
            }

            if (
                is_array($stats)
                && !empty($stats['is_demo'])
                && !empty($stats['fallback_demo'])
            ) {
                if (true === $is_public_request) {
                    delete_transient($rate_limit_key);
                    if (!empty($client_rate_limit_key)) {
                        $this->delete_client_rate_limit($client_rate_limit_key);
                    }
                }

                $next_retry = $this->get_runtime_fallback_retry_timestamp();
                if ($next_retry > 0) {
                    $stats['retry_after'] = max(0, (int) $next_retry - time());
                }

                wp_send_json_success($stats);
            }

            if (is_array($stats) && empty($stats['is_demo'])) {
                if (
                    true === $is_public_request
                    && true === $refresh_requires_remote_call
                ) {
                    $this->register_current_cache_key();
                    set_transient($rate_limit_key, time(), $rate_limit_window);
                    if (!empty($client_rate_limit_key)) {
                        $this->set_client_rate_limit($client_rate_limit_key, $rate_limit_window);
                    }
                }

                wp_send_json_success($stats);
            }

            if (true === $is_public_request) {
                $cached_stats = get_transient($this->cache_key);
                if (is_array($cached_stats) && empty($cached_stats['is_demo'])) {
                    wp_send_json_success($cached_stats);
                }

                delete_transient($rate_limit_key);
                if (!empty($client_rate_limit_key)) {
                    $this->delete_client_rate_limit($client_rate_limit_key);
                }

                $error_payload = array(
                    'rate_limited' => false,
                    'message'      => __('Actualisation en cours, veuillez réessayer dans quelques instants.', 'discord-bot-jlg'),
                );

                $error_payload['retry_after'] = max(0, (int) $this->last_retry_after);

                if (!empty($last_error_message)) {
                    $this->log_debug(
                        sprintf(
                            'ajax_refresh_stats error (public request): %s',
                            $last_error_message
                        )
                    );

                    if (false === $is_public_request) {
                        $error_payload['diagnostic'] = $last_error_message;
                    }
                }

                wp_send_json_error($error_payload, 503);
            }

            $error_payload = array(
                'rate_limited' => false,
                'message'      => __('Actualisation en cours, veuillez réessayer dans quelques instants.', 'discord-bot-jlg'),
            );

            if ($this->last_retry_after > 0) {
                $error_payload['retry_after'] = (int) $this->last_retry_after;
            }

            if (!empty($last_error_message)) {
                $error_payload['diagnostic'] = $last_error_message;
            }

            wp_send_json_error($error_payload, 503);
        } finally {
            $this->cache_key = $original_cache_key;
        }
    }

    /**
     * Rafraîchit silencieusement le cache via une tâche cron interne.
     *
     * @return void
     */
    public function refresh_cache_via_cron() {
        $options = $this->get_plugin_options(true);

        if (!empty($options['demo_mode'])) {
            $this->log_debug('Cron refresh skipped: demo mode enabled.');
            return;
        }

        $server_id = isset($options['server_id']) ? trim((string) $options['server_id']) : '';
        $bot_token = trim((string) $this->get_bot_token($options));

        if ('' === $server_id) {
            $this->log_debug('Cron refresh skipped: missing server ID.');
            return;
        }

        if ('' === $bot_token) {
            $this->log_debug('Cron refresh skipped: missing bot token.');
            return;
        }

        $stats = $this->get_stats(
            array(
                'bypass_cache' => true,
            )
        );

        if (!is_array($stats)) {
            $last_error = $this->get_last_error_message();

            if ('' === $last_error) {
                $last_error = 'Unknown error.';
            }

            $this->log_debug('Cron refresh failed: ' . $last_error);
            return;
        }

        if (!empty($stats['is_demo']) && !empty($stats['fallback_demo'])) {
            $last_error = $this->get_last_error_message();

            if ('' === $last_error) {
                $last_error = 'Fallback statistics returned.';
            }

            $this->log_debug('Cron refresh produced fallback stats: ' . $last_error);
        }
    }

    /**
     * Supprime les statistiques mises en cache pour forcer une prochaine récupération.
     *
     * @param bool $full Supprime également les données persistantes (sauvegardes et indicateurs) si vrai.
     *
     * @return void
     */
    public function clear_cache($full = false) {
        if (true === $full) {
            $this->purge_full_cache();
            return;
        }

        $original_cache_key = $this->cache_key;
        $this->cache_key     = $this->base_cache_key;

        delete_transient($this->cache_key);
        delete_transient($this->cache_key . self::REFRESH_LOCK_SUFFIX);
        $this->clear_client_rate_limits();
        $this->reset_runtime_cache();
        $this->flush_options_cache();

        $this->cache_key = $original_cache_key;
    }

    /**
     * Supprime toutes les données de cache, y compris les sauvegardes de secours.
     *
     * @return void
     */
    public function purge_full_cache() {
        $this->clear_all_cached_data();
    }

    /**
     * Supprime toutes les informations mises en cache, y compris les drapeaux de secours.
     *
     * @return void
     */
    public function clear_all_cached_data() {
        $original_cache_key = $this->cache_key;

        $registry_keys = $this->get_registered_cache_keys();
        $all_cache_keys = array_merge(array($this->base_cache_key), $registry_keys);
        $all_cache_keys = array_values(array_unique(array_filter(array_map('strval', $all_cache_keys))));

        foreach ($all_cache_keys as $cache_key) {
            if ('' === $cache_key) {
                continue;
            }

            $this->cache_key = $cache_key;

            delete_transient($this->cache_key);
            delete_transient($this->cache_key . self::REFRESH_LOCK_SUFFIX);
            delete_transient($this->get_last_good_cache_key());
            delete_transient($this->get_fallback_retry_key());
            delete_transient($this->get_api_retry_after_key());
            $this->clear_client_rate_limits();
        }

        $this->cache_key = $this->base_cache_key;
        $this->clear_fallback_retry_schedule();
        $this->clear_api_retry_after_delay();
        $this->reset_runtime_cache();
        $this->flush_options_cache();
        $this->clear_last_fallback_details();
        $this->reset_cache_key_registry();

        $this->cache_key = $original_cache_key;
    }

    private function get_runtime_cache_key($args) {
        if (!is_array($args)) {
            $args = array();
        }

        $normalized_args = array(
            'force_demo'   => isset($args['force_demo']) ? (bool) $args['force_demo'] : false,
            'bypass_cache' => isset($args['bypass_cache']) ? (bool) $args['bypass_cache'] : false,
            'signature'    => isset($args['signature']) ? (string) $args['signature'] : '',
        );

        return md5(wp_json_encode($normalized_args));
    }

    private function remember_runtime_result($runtime_key, $stats) {
        if ('' !== $runtime_key) {
            $this->runtime_cache[$runtime_key]       = $stats;
            $this->runtime_errors[$runtime_key]      = $this->last_error;
            $this->runtime_retry_after[$runtime_key] = $this->last_retry_after;
        }

        return $stats;
    }

    private function reset_runtime_cache() {
        $this->runtime_cache        = array();
        $this->runtime_errors       = array();
        $this->runtime_retry_after  = array();
        $this->runtime_fallback_retry_timestamp = 0;
    }

    private function set_runtime_fallback_retry_timestamp($timestamp) {
        $timestamp = (int) $timestamp;

        if ($timestamp > 0) {
            $this->runtime_fallback_retry_timestamp = $timestamp;
        }
    }

    private function get_runtime_fallback_retry_timestamp() {
        return (int) $this->runtime_fallback_retry_timestamp;
    }

    /**
     * Renvoie la clé de limitation spécifique à un visiteur public.
     *
     * @param bool $is_public_request Indique si la requête provient d'un visiteur non connecté.
     *
     * @return string
     */
    private function get_client_rate_limit_key($is_public_request) {
        if (false === $is_public_request) {
            return '';
        }

        $server_vars = $_SERVER;

        /**
         * Permet de forcer l'identifiant utilisé pour limiter les rafraîchissements côté public.
         *
         * @since 1.0.1
         *
         * @param string $identifier Identifiant personnalisé. Laisser vide pour utiliser l'empreinte par défaut.
         * @param array  $server_vars Variables serveur disponibles au moment de la requête.
         */
        $custom_identifier = apply_filters('discord_bot_jlg_public_rate_limit_identifier', '', $server_vars);

        if (!is_string($custom_identifier)) {
            $custom_identifier = '';
        } else {
            $custom_identifier = sanitize_text_field($custom_identifier);
        }

        if ('' !== $custom_identifier) {
            $fingerprint = md5($custom_identifier);
        } else {
            $fingerprint = $this->generate_public_request_fingerprint($server_vars);
        }

        if (empty($fingerprint)) {
            return '';
        }

        return $this->cache_key . self::CLIENT_REFRESH_LOCK_PREFIX . $fingerprint;
    }

    /**
     * Génère une empreinte anonymisée basée sur les informations de la requête.
     *
     * @param array|null $server_vars Variables serveur disponibles.
     *
     * @return string
     */
    private function generate_public_request_fingerprint($server_vars = null) {
        if (null === $server_vars || !is_array($server_vars)) {
            $server_vars = $_SERVER;
        }
        $parts       = array();

        $request_ip = $this->get_public_request_ip($server_vars);

        if (!empty($request_ip)) {
            $parts[] = $request_ip;
        }

        if (!empty($server_vars['HTTP_USER_AGENT'])) {
            $parts[] = sanitize_text_field(wp_unslash($server_vars['HTTP_USER_AGENT']));
        }

        /**
         * Permet de modifier les éléments utilisés pour générer l'empreinte publique.
         *
         * @since 1.0.1
         *
         * Les fragments incluent notamment l'adresse IP publique déterminée via l'adresse
         * `REMOTE_ADDR`, éventuellement complétée par les en-têtes déclarés comme provenant de
         * proxys de confiance via le filtre `discord_bot_jlg_trusted_proxy_headers`.
         *
         * @param array $parts       Tableau des fragments d'empreinte.
         * @param array $server_vars Variables serveur disponibles.
         */
        $parts = apply_filters('discord_bot_jlg_public_fingerprint_parts', $parts, $server_vars);

        if (!is_array($parts)) {
            $parts = array();
        }

        $parts = array_filter(array_map('strval', $parts));

        if (empty($parts)) {
            return '';
        }

        $raw_fingerprint = implode('|', $parts);

        $hashed_fingerprint = wp_hash($raw_fingerprint);

        if (!is_string($hashed_fingerprint)) {
            $hashed_fingerprint = '';
        }

        if ('' !== $hashed_fingerprint) {
            $hashed_fingerprint = substr($hashed_fingerprint, 0, 20);
        }

        /**
         * Permet de filtrer l'empreinte générée pour la limitation de fréquence publique.
         *
         * @since 1.0.1
         *
         * @param string $hashed_fingerprint Empreinte hachée générée par défaut.
         * @param string $raw_fingerprint    Empreinte avant hachage.
         * @param array  $parts              Fragments ayant servi à construire l'empreinte.
         * @param array  $server_vars        Variables serveur disponibles.
         */
        $fingerprint = apply_filters(
            'discord_bot_jlg_public_request_fingerprint',
            $hashed_fingerprint,
            $raw_fingerprint,
            $parts,
            $server_vars
        );

        $fingerprint = sanitize_text_field((string) $fingerprint);

        if ('' === $fingerprint) {
            return '';
        }

        return substr($fingerprint, 0, 40);
    }

    /**
     * Retourne une adresse IP anonymisée permettant d'identifier un visiteur derrière un proxy.
     *
     * @since 1.0.1
     *
     * Les proxys de confiance peuvent être déclarés via le filtre
     * `discord_bot_jlg_trusted_proxy_headers` afin d'autoriser des en-têtes supplémentaires.
     *
     * @param array $server_vars Variables serveur disponibles.
     *
     * @return string
     */
    private function get_public_request_ip($server_vars) {
        $candidates = array();

        if (!empty($server_vars['REMOTE_ADDR'])) {
            $candidates[] = array('REMOTE_ADDR', array(wp_unslash($server_vars['REMOTE_ADDR'])));
        }

        $proxy_headers = $this->get_trusted_proxy_headers();

        foreach ($proxy_headers as $header) {
            if (empty($server_vars[$header])) {
                continue;
            }

            $raw_values = wp_unslash($server_vars[$header]);

            if (is_array($raw_values)) {
                $values = $raw_values;
            } else {
                $values = explode(',', (string) $raw_values);
            }

            $candidates[] = array($header, $values);
        }

        foreach ($candidates as $candidate) {
            $values = isset($candidate[1]) ? $candidate[1] : array();

            foreach ($values as $value) {
                $ip = trim((string) $value);

                if ('' === $ip || false === $this->is_valid_ip($ip)) {
                    continue;
                }

                $is_ipv6    = (false !== strpos($ip, ':'));
                $anonymized = $this->anonymize_ip($ip, $is_ipv6);

                if ('' === $anonymized) {
                    continue;
                }

                return sanitize_text_field($anonymized);
            }
        }

        return '';
    }

    /**
     * Retourne la liste des en-têtes HTTP considérés comme provenant de proxys de confiance.
     *
     * @since 1.1.0
     *
     * Les en-têtes doivent être fournis dans leur forme attendue dans $_SERVER (ex. `HTTP_X_FORWARDED_FOR`).
     *
     * @return array
     */
    private function get_trusted_proxy_headers() {
        $headers = apply_filters('discord_bot_jlg_trusted_proxy_headers', array());

        if (!is_array($headers)) {
            return array();
        }

        $headers = array_filter(array_map('strval', $headers));

        return array_values(array_unique($headers));
    }

    /**
     * Vérifie si la chaîne fournie est une adresse IP valide.
     *
     * @since 1.0.1
     *
     * @param string $ip Adresse IP à valider.
     *
     * @return bool
     */
    private function is_valid_ip($ip) {
        return (bool) filter_var($ip, FILTER_VALIDATE_IP);
    }

    /**
     * Anonymise une adresse IP en tenant compte de sa version.
     *
     * @since 1.0.1
     *
     * @param string $ip     Adresse IP à anonymiser.
     * @param bool   $is_ipv6 Indique s'il s'agit d'une adresse IPv6.
     *
     * @return string
     */
    private function anonymize_ip($ip, $is_ipv6) {
        if (function_exists('wp_privacy_anonymize_ip')) {
            $anonymized = wp_privacy_anonymize_ip($ip, $is_ipv6);

            if (is_string($anonymized) && '' !== $anonymized) {
                return $anonymized;
            }
        }

        return (string) $ip;
    }

    /**
     * Stocke une limitation de fréquence spécifique au client.
     *
     * @param string $client_key        Clé du transient à définir.
     * @param int    $rate_limit_window Durée d'expiration en secondes.
     *
     * @return void
     */
    private function set_client_rate_limit($client_key, $rate_limit_window) {
        if (empty($client_key)) {
            return;
        }

        $this->register_current_cache_key();
        set_transient($client_key, time(), $rate_limit_window);
        $this->remember_client_rate_limit_key($client_key);
    }

    /**
     * Supprime une limitation de fréquence spécifique à un client.
     *
     * @param string $client_key Clé du transient à supprimer.
     *
     * @return void
     */
    private function delete_client_rate_limit($client_key) {
        if (empty($client_key)) {
            return;
        }

        delete_transient($client_key);

        $index_key   = $this->get_client_rate_limit_index_key();
        $client_keys = get_transient($index_key);

        if (!is_array($client_keys)) {
            return;
        }

        if (isset($client_keys[$client_key])) {
            unset($client_keys[$client_key]);

            if (!empty($client_keys)) {
                $this->register_current_cache_key();
                set_transient($index_key, $client_keys, DAY_IN_SECONDS);
            } else {
                delete_transient($index_key);
            }
        }
    }

    /**
     * Enregistre la clé d'un client dans l'index pour faciliter les purges.
     *
     * @param string $client_key Clé de limitation à mémoriser.
     *
     * @return void
     */
    private function remember_client_rate_limit_key($client_key) {
        if (empty($client_key)) {
            return;
        }

        $index_key   = $this->get_client_rate_limit_index_key();
        $client_keys = get_transient($index_key);

        if (!is_array($client_keys)) {
            $client_keys = array();
        }

        $now = time();
        $updated_keys = array();

        foreach ($client_keys as $stored_key => $timestamp) {
            if ($stored_key === $client_key) {
                continue;
            }

            if (false === get_transient($stored_key)) {
                continue;
            }

            $updated_keys[$stored_key] = $timestamp;
        }

        $updated_keys[$client_key] = $now;

        $this->register_current_cache_key();
        set_transient($index_key, $updated_keys, DAY_IN_SECONDS);
    }

    /**
     * Supprime toutes les limitations de fréquence spécifiques aux clients.
     *
     * @return void
     */
    private function clear_client_rate_limits() {
        $index_key   = $this->get_client_rate_limit_index_key();
        $client_keys = get_transient($index_key);

        if (is_array($client_keys)) {
            foreach (array_keys($client_keys) as $client_key) {
                delete_transient($client_key);
            }
        }

        delete_transient($index_key);
    }

    /**
     * Calcule le délai restant avant qu'un client puisse relancer une requête.
     *
     * @param string $key               Clé du transient.
     * @param int    $rate_limit_window Durée de la fenêtre de limitation.
     *
     * @return int
     */
    private function get_retry_after($key, $rate_limit_window) {
        if (empty($key)) {
            return 0;
        }

        $last_refresh = get_transient($key);

        if (false === $last_refresh) {
            return 0;
        }

        $elapsed = time() - (int) $last_refresh;

        if ($elapsed < $rate_limit_window) {
            return max(0, $rate_limit_window - $elapsed);
        }

        return 0;
    }

    /**
     * Renvoie la clé de l'index stockant les limitations par client.
     *
     * @return string
     */
    private function get_client_rate_limit_index_key() {
        return $this->cache_key . self::CLIENT_REFRESH_LOCK_INDEX_SUFFIX;
    }

    /**
     * Génère des statistiques fictives pour la démonstration ou en cas d'absence de configuration.
     *
     * Le calcul de la variation horaire utilise désormais l'horloge configurée dans WordPress afin de
     * refléter le fuseau défini sur le site.
     *
     * @param bool $is_fallback Indique si ces statistiques sont utilisées faute de données réelles.
     *
     * @return array Statistiques de démonstration comprenant les clés `online`, `total`, `server_name`, `is_demo` et `fallback_demo`.
     */
    public function get_demo_stats($is_fallback = false) {
        if ($is_fallback) {
            $last_good = get_transient($this->get_last_good_cache_key());

            if (
                is_array($last_good)
                && isset($last_good['stats'])
                && is_array($last_good['stats'])
            ) {
                $timestamp = isset($last_good['timestamp']) ? (int) $last_good['timestamp'] : time();

                $stats = $last_good['stats'];
                $stats['is_demo'] = true;
                $stats['fallback_demo'] = true;
                $stats['stale'] = true;
                $stats['last_updated'] = $timestamp;

                return $stats;
            }
        }

        $base_online = 42;
        $base_total  = 256;

        $timestamp = current_time('timestamp', true);
        $hour      = (int) discord_bot_jlg_format_datetime('H', $timestamp);
        $variation = sin($hour * 0.26) * 10;

        $demo_avatar_base = 'https://cdn.discordapp.com/embed/avatars/0.png';
        $demo_avatar_url  = add_query_arg('size', 256, $demo_avatar_base);

        $online_value = (int) round($base_online + $variation);
        if ($online_value < 0) {
            $online_value = 0;
        }

        $presence_breakdown = array(
            'online' => (int) round($online_value * 0.68),
            'idle'   => (int) round($online_value * 0.22),
        );

        $allocated = (int) $presence_breakdown['online'] + (int) $presence_breakdown['idle'];
        $presence_breakdown['dnd'] = max(0, $online_value - $allocated);

        return array(
            'online'                     => $online_value,
            'total'                      => (int) $base_total,
            'server_name'                => __('Serveur Démo', 'discord-bot-jlg'),
            'is_demo'                    => true,
            'fallback_demo'              => (bool) $is_fallback,
            'has_total'                  => true,
            'total_is_approximate'       => false,
            'server_avatar_url'          => $demo_avatar_url,
            'server_avatar_base_url'     => $demo_avatar_base,
            'approximate_presence_count' => $online_value,
            'approximate_member_count'   => (int) $base_total,
            'presence_count_by_status'   => $presence_breakdown,
            'premium_subscription_count' => 6,
        );
    }

    private function persist_fallback_stats($stats, $options, $reason = '') {
        if (!is_array($stats)) {
            return $stats;
        }

        $is_fallback = (
            !empty($stats['is_demo'])
            && !empty($stats['fallback_demo'])
        );

        if (false === $is_fallback) {
            return $stats;
        }

        if (!array_key_exists('stale', $stats)) {
            $stats['stale'] = true;
        }

        if (!isset($stats['last_updated']) || !is_numeric($stats['last_updated'])) {
            $stats['last_updated'] = time();
        }

        $ttl = $this->get_fallback_cache_ttl($options);

        $this->register_current_cache_key();
        set_transient($this->cache_key, $stats, $ttl);
        $this->store_last_fallback_details($reason);

        return $stats;
    }

    private function get_fallback_cache_ttl($options) {
        $cache_duration = (int) $this->get_cache_duration($options);
        $candidates     = array();

        if ($cache_duration > 0) {
            $candidates[] = $cache_duration;
        }

        $retry_window = (int) $this->get_fallback_retry_window($cache_duration, $options);
        if ($retry_window > 0) {
            $candidates[] = $retry_window;
        }

        if ($this->last_retry_after > 0) {
            $candidates[] = (int) $this->last_retry_after;
        }

        if (empty($candidates)) {
            $candidates[] = (int) $this->default_cache_duration;
        }

        $ttl = max($candidates);

        return max(1, (int) $ttl);
    }

    private function get_last_good_cache_key() {
        return $this->cache_key . self::LAST_GOOD_SUFFIX;
    }

    private function get_fallback_retry_key() {
        return $this->cache_key . self::FALLBACK_RETRY_SUFFIX;
    }

    private function get_api_retry_after_key() {
        return $this->cache_key . self::FALLBACK_RETRY_API_DELAY_SUFFIX;
    }

    private function get_last_fallback_option_name() {
        return self::LAST_FALLBACK_OPTION;
    }

    private function get_fallback_retry_window($cache_duration, $options) {
        $cache_duration = (int) $cache_duration;
        $cache_duration = $cache_duration > 0 ? $cache_duration : $this->default_cache_duration;

        $base_window = max(self::MIN_PUBLIC_REFRESH_INTERVAL, $cache_duration);

        if (!is_array($options)) {
            $options = array();
        }

        $filtered_window = apply_filters('discord_bot_jlg_fallback_retry_window', $base_window, $options, $this->cache_key);

        if (!is_int($filtered_window)) {
            $filtered_window = (int) $filtered_window;
        }

        if ($filtered_window < self::MIN_PUBLIC_REFRESH_INTERVAL) {
            $filtered_window = self::MIN_PUBLIC_REFRESH_INTERVAL;
        }

        return $filtered_window;
    }

    /**
     * Programme la prochaine tentative lorsque des statistiques de secours sont utilisées.
     *
     * Le délai est basé sur la configuration locale, mais l'en-tête Retry-After
     * fourni par l'API Discord est prioritaire lorsqu'il est disponible.
     *
     * @param int   $cache_duration Durée de cache configurée.
     * @param array $options        Options du plugin.
     *
     * @return int Timestamp UNIX de la prochaine tentative.
     */
    private function schedule_next_fallback_retry($cache_duration, $options) {
        $existing_retry = $this->get_runtime_fallback_retry_timestamp();
        if ($existing_retry > 0) {
            return $existing_retry;
        }

        $retry_window = $this->get_fallback_retry_window($cache_duration, $options);
        $api_retry_after = $this->consume_api_retry_after_delay();

        if ($api_retry_after > 0) {
            $retry_window = max(1, (int) $api_retry_after);
        }

        $next_retry = time() + $retry_window;

        $this->register_current_cache_key();
        set_transient($this->get_fallback_retry_key(), $next_retry, max(1, $retry_window));
        $this->set_runtime_fallback_retry_timestamp($next_retry);

        return $next_retry;
    }

    private function clear_fallback_retry_schedule() {
        delete_transient($this->get_fallback_retry_key());
        $this->runtime_fallback_retry_timestamp = 0;
    }

    private function store_last_fallback_details($reason) {
        $payload = array(
            'timestamp' => time(),
            'reason'    => trim((string) $reason),
        );

        update_option($this->get_last_fallback_option_name(), $payload, false);
    }

    private function clear_last_fallback_details() {
        delete_option($this->get_last_fallback_option_name());
    }

    /**
     * Mémorise le délai Retry-After fourni par l'API afin de prioriser la reprise.
     *
     * @param int $retry_after Durée en secondes.
     *
     * @return void
     */
    private function store_api_retry_after_delay($retry_after) {
        $retry_after = (int) $retry_after;
        $key         = $this->get_api_retry_after_key();

        if ($retry_after > 0) {
            $this->register_current_cache_key();
            set_transient($key, $retry_after, max(1, $retry_after));
            return;
        }

        delete_transient($key);
    }

    /**
     * Récupère et efface le délai Retry-After précédemment mémorisé.
     *
     * @return int
     */
    private function consume_api_retry_after_delay() {
        $key         = $this->get_api_retry_after_key();
        $retry_after = (int) get_transient($key);

        if ($retry_after > 0) {
            delete_transient($key);
            return $retry_after;
        }

        return 0;
    }

    /**
     * Supprime tout délai Retry-After mémorisé.
     *
     * @return void
     */
    private function clear_api_retry_after_delay() {
        delete_transient($this->get_api_retry_after_key());
    }

    private function log_debug($message) {
        if ('' === trim((string) $message)) {
            return;
        }

        $debug_enabled = (defined('WP_DEBUG') && WP_DEBUG)
            || (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG);

        if ($debug_enabled) {
            error_log('[discord-bot-jlg] ' . $message);
        }
    }

    private function get_debug_body_preview($body) {
        $body_string = trim((string) $body);

        if ('' === $body_string) {
            return '[empty]';
        }

        $max_length = 500;

        if (strlen($body_string) > $max_length) {
            return substr($body_string, 0, $max_length) . '…';
        }

        return $body_string;
    }

    private function store_last_good_stats($stats) {
        if (!is_array($stats)) {
            return;
        }

        if (!empty($stats['is_demo'])) {
            return;
        }

        $normalized_stats = $stats;
        unset($normalized_stats['stale'], $normalized_stats['last_updated']);

        $payload = array(
            'stats'     => $normalized_stats,
            'timestamp' => time(),
        );

        $this->register_current_cache_key();
        set_transient($this->get_last_good_cache_key(), $payload, 0);
    }

    private function get_stats_from_widget($options) {
        $widget_url = 'https://discord.com/api/guilds/' . $options['server_id'] . '/widget.json';

        $response = $this->http_client->get(
            $widget_url,
            array(
                'timeout' => 10,
            ),
            'widget'
        );

        if (is_wp_error($response)) {
            $this->last_error = sprintf(
                /* translators: %s: error message. */
                __('Erreur lors de l\'appel du widget Discord : %s', 'discord-bot-jlg'),
                $response->get_error_message()
            );
            $this->log_debug('Discord API error (widget): ' . $response->get_error_message());
            return false;
        }

        $response_code = (int) wp_remote_retrieve_response_code($response);
        if (200 !== $response_code) {
            $this->set_last_retry_after($this->extract_retry_after_seconds($response));
            $error_detail = $this->get_response_error_detail($response);

            if (!empty($error_detail)) {
                $this->last_error = sprintf(
                    /* translators: 1: HTTP status code, 2: error message. */
                    __('Réponse inattendue du widget Discord : HTTP %1$d (%2$s)', 'discord-bot-jlg'),
                    $response_code,
                    $error_detail
                );
            } else {
                $this->last_error = sprintf(
                    /* translators: %d: HTTP status code. */
                    __('Réponse inattendue du widget Discord : HTTP %d', 'discord-bot-jlg'),
                    $response_code
                );
            }
            $this->log_debug('Discord API error (widget): HTTP ' . $response_code);
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (false === is_array($data)) {
            $error_context = array();

            if (function_exists('json_last_error')) {
                $json_error = json_last_error();
                $error_context[] = 'json_last_error=' . $json_error;

                if (function_exists('json_last_error_msg')) {
                    $error_context[] = 'json_last_error_msg=' . json_last_error_msg();
                }
            }

            $error_context[] = 'decoded_type=' . gettype($data);
            $error_context[] = 'body_preview=' . $this->get_debug_body_preview($body);

            $this->log_debug('Discord API error (widget): invalid JSON response (' . implode(', ', $error_context) . ')');
            $this->last_error = __('Réponse JSON invalide reçue depuis le widget Discord.', 'discord-bot-jlg');
            return false;
        }

        $has_presence_count = isset($data['presence_count']);
        $has_members_list   = isset($data['members']) && is_array($data['members']);

        $presence_breakdown = array();

        if ($has_members_list) {
            foreach ($data['members'] as $member) {
                if (!is_array($member)) {
                    continue;
                }

                $raw_status = isset($member['status']) ? $member['status'] : '';
                $status_key = $this->normalize_presence_status_slug($raw_status);

                if ('' === $status_key) {
                    continue;
                }

                if (!array_key_exists($status_key, $presence_breakdown)) {
                    $presence_breakdown[$status_key] = 0;
                }

                $presence_breakdown[$status_key]++;
            }
        }

        if (false === $has_presence_count && false === $has_members_list) {
            $this->last_error = __('Données incomplètes reçues depuis le widget Discord.', 'discord-bot-jlg');
            $missing_parts = array();

            if (false === $has_presence_count) {
                $missing_parts[] = 'presence_count';
            }

            if (false === $has_members_list) {
                $missing_parts[] = 'members';
            }

            $this->log_debug(sprintf(
                'Discord API error (widget): incomplete data (missing %s). Body preview: %s',
                implode(', ', $missing_parts),
                $this->get_debug_body_preview($body)
            ));
            return false;
        }

        $online = $has_presence_count
            ? (int) $data['presence_count']
            : (int) count($data['members']);

        $server_name = isset($data['name']) ? $data['name'] : '';

        $stats = array(
            'online'                     => $online,
            'total'                      => null,
            'server_name'                => $server_name,
            'has_total'                  => false,
            'total_is_approximate'       => false,
            'approximate_presence_count' => $has_presence_count ? (int) $data['presence_count'] : null,
            'approximate_member_count'   => isset($data['member_count']) ? (int) $data['member_count'] : null,
            'presence_count_by_status'   => $presence_breakdown,
            'premium_subscription_count' => 0,
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

        $response = $this->http_client->get(
            $api_url,
            array(
                'timeout' => 10,
                'headers' => array(
                    'Authorization' => 'Bot ' . $bot_token,
                ),
            ),
            'bot'
        );

        if (is_wp_error($response)) {
            $this->last_error = sprintf(
                /* translators: %s: error message. */
                __('Erreur lors de l\'appel de l\'API Discord (bot) : %s', 'discord-bot-jlg'),
                $response->get_error_message()
            );
            $this->log_debug('Discord API error (bot): ' . $response->get_error_message());
            return false;
        }

        $response_code = (int) wp_remote_retrieve_response_code($response);
        if (200 !== $response_code) {
            $this->set_last_retry_after($this->extract_retry_after_seconds($response));
            $error_detail = $this->get_response_error_detail($response);

            if (!empty($error_detail)) {
                $this->last_error = sprintf(
                    /* translators: 1: HTTP status code, 2: error message. */
                    __('Réponse inattendue de l\'API Discord (bot) : HTTP %1$d (%2$s)', 'discord-bot-jlg'),
                    $response_code,
                    $error_detail
                );
            } else {
                $this->last_error = sprintf(
                    /* translators: %d: HTTP status code. */
                    __('Réponse inattendue de l\'API Discord (bot) : HTTP %d', 'discord-bot-jlg'),
                    $response_code
                );
            }
            $this->log_debug('Discord API error (bot): HTTP ' . $response_code);
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (false === is_array($data)) {
            $error_context = array();

            if (function_exists('json_last_error')) {
                $json_error = json_last_error();
                $error_context[] = 'json_last_error=' . $json_error;

                if (function_exists('json_last_error_msg')) {
                    $error_context[] = 'json_last_error_msg=' . json_last_error_msg();
                }
            }

            $error_context[] = 'decoded_type=' . gettype($data);
            $error_context[] = 'body_preview=' . $this->get_debug_body_preview($body);

            $this->log_debug('Discord API error (bot): invalid JSON response (' . implode(', ', $error_context) . ')');
            $this->last_error = __('Réponse JSON invalide reçue depuis l\'API Discord (bot).', 'discord-bot-jlg');
            return false;
        }

        $has_presence = isset($data['approximate_presence_count']);
        $has_total    = isset($data['approximate_member_count']);

        if (false === $has_presence || false === $has_total) {
            $missing_parts = array();

            if (false === $has_presence) {
                $missing_parts[] = 'approximate_presence_count';
            }

            if (false === $has_total) {
                $missing_parts[] = 'approximate_member_count';
            }

            $this->log_debug(sprintf(
                'Discord API error (bot): incomplete data (missing %s). Body preview: %s',
                implode(', ', $missing_parts),
                $this->get_debug_body_preview($body)
            ));

            $this->last_error = __('Données incomplètes reçues depuis l\'API Discord (bot).', 'discord-bot-jlg');
            return false;
        }

        $server_avatar_base_url = '';
        $server_avatar_url      = '';

        $icon_hash = isset($data['icon']) ? trim((string) $data['icon']) : '';
        $discovery_splash_hash = isset($data['discovery_splash']) ? trim((string) $data['discovery_splash']) : '';

        if ('' !== $icon_hash) {
            $extension = $this->is_animated_hash($icon_hash) ? 'gif' : 'png';
            $server_avatar_base_url = $this->build_guild_asset_url(
                'icons',
                $options['server_id'],
                $icon_hash,
                $extension
            );
            $server_avatar_url = $this->build_guild_asset_url(
                'icons',
                $options['server_id'],
                $icon_hash,
                $extension,
                256
            );
        } elseif ('' !== $discovery_splash_hash) {
            $server_avatar_base_url = $this->build_guild_asset_url(
                'discovery-splashes',
                $options['server_id'],
                $discovery_splash_hash,
                'jpg'
            );
            $server_avatar_url = $this->build_guild_asset_url(
                'discovery-splashes',
                $options['server_id'],
                $discovery_splash_hash,
                'jpg',
                256
            );
        }

        return array(
            'online'                     => (int) $data['approximate_presence_count'],
            'total'                      => (int) $data['approximate_member_count'],
            'server_name'                => isset($data['name']) ? $data['name'] : '',
            'has_total'                  => true,
            // Discord renvoie uniquement un total approximatif via approximate_member_count.
            'total_is_approximate'       => true,
            'server_avatar_url'          => $server_avatar_url,
            'server_avatar_base_url'     => $server_avatar_base_url,
            'approximate_presence_count' => (int) $data['approximate_presence_count'],
            'approximate_member_count'   => (int) $data['approximate_member_count'],
            'presence_count_by_status'   => array(),
            'premium_subscription_count' => isset($data['premium_subscription_count'])
                ? (int) $data['premium_subscription_count']
                : 0,
        );
    }

    private function build_guild_asset_url($type, $guild_id, $hash, $extension, $size = null) {
        if ('' === trim((string) $guild_id) || '' === trim((string) $hash)) {
            return '';
        }

        $base = sprintf(
            'https://cdn.discordapp.com/%1$s/%2$s/%3$s.%4$s',
            trim($type, '/'),
            rawurlencode((string) $guild_id),
            rawurlencode((string) $hash),
            $extension
        );

        if (null === $size) {
            return $base;
        }

        $normalized_size = $this->normalize_discord_image_size($size);

        return add_query_arg('size', $normalized_size, $base);
    }

    private function normalize_discord_image_size($size, $default = 128) {
        $allowed_sizes = array(16, 32, 64, 128, 256, 512, 1024, 2048, 4096);
        $size = (int) $size;
        $fallback = in_array((int) $default, $allowed_sizes, true) ? (int) $default : 128;

        if ($size <= 0) {
            return $fallback;
        }

        if (in_array($size, $allowed_sizes, true)) {
            return $size;
        }

        foreach ($allowed_sizes as $allowed) {
            if ($size <= $allowed) {
                return $allowed;
            }
        }

        return $allowed_sizes[count($allowed_sizes) - 1];
    }

    private function is_animated_hash($hash) {
        return (0 === strpos($hash, 'a_'));
    }

    private function get_response_error_detail($response) {
        $message = wp_remote_retrieve_response_message($response);
        $body    = wp_remote_retrieve_body($response);

        if (!empty($body)) {
            $decoded_body = json_decode($body, true);

            if (is_array($decoded_body)) {
                $body_message = '';

                if (!empty($decoded_body['message']) && is_string($decoded_body['message'])) {
                    $body_message = $decoded_body['message'];
                } elseif (!empty($decoded_body['error']) && is_string($decoded_body['error'])) {
                    $body_message = $decoded_body['error'];
                }

                if (!empty($body_message)) {
                    $message = $body_message;
                }
            } elseif (empty($message)) {
                $message = $body;
            }
        }

        if (empty($message)) {
            return '';
        }

        return trim(wp_strip_all_tags($message));
    }

    private function normalize_presence_status_slug($status) {
        if (is_numeric($status)) {
            $status = (string) $status;
        }

        if (!is_string($status)) {
            return '';
        }

        $normalized = strtolower(trim($status));

        if ('' === $normalized) {
            return '';
        }

        if ('do_not_disturb' === $normalized || 'dnd' === $normalized) {
            return 'dnd';
        }

        if ('invisible' === $normalized) {
            return 'offline';
        }

        switch ($normalized) {
            case 'online':
            case 'idle':
            case 'offline':
            case 'streaming':
                return $normalized;
        }

        return 'other';
    }

    private function sort_presence_breakdown($breakdown) {
        if (!is_array($breakdown) || empty($breakdown)) {
            return array();
        }

        $ordered = array();
        $preferred_order = array('online', 'idle', 'dnd', 'offline', 'streaming', 'other');

        foreach ($preferred_order as $status_key) {
            if (array_key_exists($status_key, $breakdown)) {
                $ordered[$status_key] = (int) $breakdown[$status_key];
                unset($breakdown[$status_key]);
            }
        }

        if (!empty($breakdown)) {
            foreach ($breakdown as $status_key => $value) {
                $ordered[$status_key] = (int) $value;
            }
        }

        return $ordered;
    }

    /**
     * Normalise la valeur de l'en-tête Retry-After d'une réponse HTTP.
     *
     * @param array|WP_Error $response Réponse HTTP WordPress.
     *
     * @return int Durée en secondes.
     */
    private function extract_retry_after_seconds($response) {
        $header = wp_remote_retrieve_header($response, 'retry-after');

        if (is_array($header)) {
            $header = reset($header);
        }

        if (!is_string($header)) {
            return 0;
        }

        $header = trim($header);

        if ('' === $header) {
            return 0;
        }

        if (ctype_digit($header)) {
            $retry_after = (int) $header;
            return ($retry_after > 0) ? $retry_after : 0;
        }

        $timestamp = strtotime($header);

        if (false === $timestamp) {
            return 0;
        }

        $delta = $timestamp - time();

        return ($delta > 0) ? $delta : 0;
    }

    private function set_last_retry_after($retry_after) {
        $this->last_retry_after = max(0, (int) $retry_after);
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
        $override_requested = (is_array($options) && !empty($options['__bot_token_override']));

        if (!$override_requested && defined('DISCORD_BOT_JLG_TOKEN') && '' !== DISCORD_BOT_JLG_TOKEN) {
            return DISCORD_BOT_JLG_TOKEN;
        }

        if (!isset($options['bot_token']) || '' === $options['bot_token']) {
            return '';
        }

        $stored_token = $options['bot_token'];

        if (!is_string($stored_token)) {
            return '';
        }

        if (!discord_bot_jlg_is_encrypted_secret($stored_token)) {
            return $stored_token;
        }

        $decrypted = discord_bot_jlg_decrypt_secret($stored_token);

        if (is_wp_error($decrypted)) {
            $this->last_error = $decrypted->get_error_message();
            $this->flush_options_cache();

            return '';
        }

        return $decrypted;
    }

    private function resolve_connection_context($args, $options) {
        $options = is_array($options) ? $options : array();
        $effective_options = $options;
        $signature_parts = array();

        $profile_key = isset($args['profile_key']) ? sanitize_key($args['profile_key']) : '';
        $server_id_override = isset($args['server_id']) ? $this->sanitize_server_id($args['server_id']) : '';

        if ('' !== $profile_key) {
            $profile = $this->find_server_profile($profile_key, $options);

            if (null === $profile) {
                $signature = 'profile-missing:' . $profile_key;

                return array(
                    'options'   => $effective_options,
                    'cache_key' => $this->build_cache_key_from_signature($signature),
                    'signature' => $signature,
                    'error'     => new WP_Error(
                        'discord_bot_jlg_profile_not_found',
                        sprintf(
                            /* translators: %s: server profile key. */
                            __('Le profil de serveur « %s » est introuvable.', 'discord-bot-jlg'),
                            $profile_key
                        )
                    ),
                );
            }

            if (!empty($profile['server_id'])) {
                $effective_options['server_id'] = $this->sanitize_server_id($profile['server_id']);
            }

            if (isset($profile['bot_token']) && '' !== $profile['bot_token']) {
                $effective_options['bot_token'] = $profile['bot_token'];
                $effective_options['__bot_token_override'] = true;
            }

            $signature_parts[] = 'profile:' . $profile_key;
        }

        if ('' !== $server_id_override) {
            $effective_options['server_id'] = $server_id_override;
            $signature_parts[] = 'server:' . $server_id_override;
        }

        if (!isset($effective_options['server_id'])) {
            $effective_options['server_id'] = '';
        } else {
            $effective_options['server_id'] = $this->sanitize_server_id($effective_options['server_id']);
        }

        $signature = 'default';

        if (!empty($signature_parts)) {
            $signature = implode('|', $signature_parts);
        }

        return array(
            'options'   => $effective_options,
            'cache_key' => $this->build_cache_key_from_signature($signature),
            'signature' => $signature,
        );
    }

    private function build_cache_key_from_signature($signature) {
        if ('' === $signature || 'default' === $signature) {
            return $this->base_cache_key;
        }

        return $this->base_cache_key . '_' . md5($signature);
    }

    private function sanitize_server_id($value) {
        if (!is_string($value) && !is_numeric($value)) {
            return '';
        }

        $value = preg_replace('/[^0-9]/', '', (string) $value);

        return (string) $value;
    }

    private function find_server_profile($profile_key, $options) {
        if (!is_array($options) || !isset($options['server_profiles']) || !is_array($options['server_profiles'])) {
            return null;
        }

        foreach ($options['server_profiles'] as $stored_key => $profile) {
            if (!is_array($profile)) {
                continue;
            }

            $candidate_key = '';

            if (isset($profile['key'])) {
                $candidate_key = sanitize_key($profile['key']);
            }

            if ('' === $candidate_key) {
                $candidate_key = sanitize_key($stored_key);
            }

            if ('' === $candidate_key || $candidate_key !== $profile_key) {
                continue;
            }

            return array(
                'key'       => $candidate_key,
                'label'     => isset($profile['label']) ? sanitize_text_field($profile['label']) : '',
                'server_id' => isset($profile['server_id']) ? $this->sanitize_server_id($profile['server_id']) : '',
                'bot_token' => isset($profile['bot_token']) ? (string) $profile['bot_token'] : '',
            );
        }

        return null;
    }

    private function get_cache_duration($options) {
        if (isset($options['cache_duration'])) {
            $duration = (int) $options['cache_duration'];
            if (
                $duration >= self::MIN_PUBLIC_REFRESH_INTERVAL
                && $duration <= 3600
            ) {
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

        if (!isset($stats['server_avatar_url'])) {
            $stats['server_avatar_url'] = '';
        }

        if (!isset($stats['server_avatar_base_url'])) {
            $stats['server_avatar_base_url'] = '';
        }

        if (array_key_exists('approximate_member_count', $stats)) {
            $stats['approximate_member_count'] = is_numeric($stats['approximate_member_count'])
                ? (int) $stats['approximate_member_count']
                : null;
        } else {
            $stats['approximate_member_count'] = isset($stats['total']) && null !== $stats['total']
                ? (int) $stats['total']
                : null;
        }

        if (array_key_exists('approximate_presence_count', $stats)) {
            $stats['approximate_presence_count'] = is_numeric($stats['approximate_presence_count'])
                ? (int) $stats['approximate_presence_count']
                : null;
        } else {
            $stats['approximate_presence_count'] = isset($stats['online'])
                ? (int) $stats['online']
                : null;
        }

        if (!isset($stats['presence_count_by_status']) || !is_array($stats['presence_count_by_status'])) {
            $stats['presence_count_by_status'] = array();
        } else {
            $normalized_breakdown = array();

            foreach ($stats['presence_count_by_status'] as $status => $value) {
                $status_key = $this->normalize_presence_status_slug($status);

                if ('' === $status_key) {
                    continue;
                }

                if (!array_key_exists($status_key, $normalized_breakdown)) {
                    $normalized_breakdown[$status_key] = 0;
                }

                $normalized_breakdown[$status_key] += max(0, (int) $value);
            }

            $stats['presence_count_by_status'] = $this->sort_presence_breakdown($normalized_breakdown);
        }

        if (array_key_exists('premium_subscription_count', $stats)) {
            $stats['premium_subscription_count'] = max(0, (int) $stats['premium_subscription_count']);
        } else {
            $stats['premium_subscription_count'] = 0;
        }

        return $stats;
    }
}
