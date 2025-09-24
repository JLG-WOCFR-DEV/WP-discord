<?php

if (false === defined('ABSPATH')) {
    exit;
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

    private $option_name;
    private $cache_key;
    private $default_cache_duration;
    private $last_error;
    private $runtime_cache;
    private $runtime_errors;

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
        $this->last_error = '';
        $this->runtime_cache = array();
        $this->runtime_errors = array();
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

        $args = wp_parse_args(
            $args,
            array(
                'force_demo'   => false,
                'bypass_cache' => false,
            )
        );

        $runtime_key = $this->get_runtime_cache_key($args);

        if (array_key_exists($runtime_key, $this->runtime_cache)) {
            $this->last_error = isset($this->runtime_errors[$runtime_key]) ? $this->runtime_errors[$runtime_key] : '';
            return $this->runtime_cache[$runtime_key];
        }

        if (true === $args['force_demo']) {
            $demo_stats = $this->get_demo_stats(false);
            return $this->remember_runtime_result($runtime_key, $demo_stats);
        }

        $options = get_option($this->option_name);
        if (false === is_array($options)) {
            $options = array();
        }

        if (!empty($options['demo_mode'])) {
            $demo_stats = $this->get_demo_stats(false);
            return $this->remember_runtime_result($runtime_key, $demo_stats);
        }

        if (false === $args['bypass_cache']) {
            $cached_stats = get_transient($this->cache_key);
            if (false !== $cached_stats) {
                return $this->remember_runtime_result($runtime_key, $cached_stats);
            }
        }

        if (empty($options['server_id'])) {
            $this->last_error = __('Aucun identifiant de serveur Discord n\'est configuré.', 'discord-bot-jlg');
            $demo_stats = $this->get_demo_stats(true);
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
            if (empty($this->last_error)) {
                $this->last_error = __('Impossible d\'obtenir des statistiques exploitables depuis Discord.', 'discord-bot-jlg');
            }
            $demo_stats = $this->get_demo_stats(true);
            return $this->remember_runtime_result($runtime_key, $demo_stats);
        }

        $this->last_error = '';
        set_transient($this->cache_key, $stats, $this->get_cache_duration($options));
        $this->store_last_good_stats($stats);

        return $this->remember_runtime_result($runtime_key, $stats);
    }

    /**
     * Renvoie le dernier message d'erreur rencontré lors d'une récupération de statistiques.
     *
     * @return string
     */
    public function get_last_error_message() {
        return (string) $this->last_error;
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

        $options = get_option($this->option_name);
        if (false === is_array($options)) {
            $options = array();
        }

        if (!empty($options['demo_mode'])) {
            wp_send_json_error(__('Mode démo actif', 'discord-bot-jlg'));
        }

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
                    wp_send_json_success($cached_stats);
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
            $force_refresh = wp_validate_boolean(wp_unslash($_POST['force_refresh']));

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

            wp_send_json_success($stats);
        }

        if (is_array($stats) && empty($stats['is_demo'])) {
            if (
                true === $is_public_request
                && true === $refresh_requires_remote_call
            ) {
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

            if (!empty($last_error_message)) {
                $error_payload['diagnostic'] = $last_error_message;
            }

            wp_send_json_error($error_payload, 503);
        }

        delete_transient($rate_limit_key);
        if (!empty($client_rate_limit_key)) {
            $this->delete_client_rate_limit($client_rate_limit_key);
        }

        $error_payload = array(
            'message' => __('Impossible de récupérer les stats', 'discord-bot-jlg'),
        );

        if (!empty($last_error_message)) {
            $error_payload['diagnostic'] = $last_error_message;
        }

        wp_send_json_error($error_payload);
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

        delete_transient($this->cache_key);
        delete_transient($this->cache_key . self::REFRESH_LOCK_SUFFIX);
        $this->clear_client_rate_limits();
        $this->reset_runtime_cache();
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
        delete_transient($this->cache_key);
        delete_transient($this->cache_key . self::REFRESH_LOCK_SUFFIX);
        $this->clear_fallback_retry_schedule();
        delete_transient($this->get_last_good_cache_key());
        $this->clear_client_rate_limits();
        $this->reset_runtime_cache();
    }

    private function get_runtime_cache_key($args) {
        if (!is_array($args)) {
            $args = array();
        }

        $normalized_args = array(
            'force_demo'   => !empty($args['force_demo']),
            'bypass_cache' => !empty($args['bypass_cache']),
        );

        return md5(wp_json_encode($normalized_args));
    }

    private function remember_runtime_result($runtime_key, $stats) {
        if ('' !== $runtime_key) {
            $this->runtime_cache[$runtime_key]  = $stats;
            $this->runtime_errors[$runtime_key] = $this->last_error;
        }

        return $stats;
    }

    private function reset_runtime_cache() {
        $this->runtime_cache  = array();
        $this->runtime_errors = array();
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

        $fingerprint = $this->generate_public_request_fingerprint();

        if (empty($fingerprint)) {
            return '';
        }

        return $this->cache_key . self::CLIENT_REFRESH_LOCK_PREFIX . $fingerprint;
    }

    /**
     * Génère une empreinte anonymisée basée sur les informations de la requête.
     *
     * @return string
     */
    private function generate_public_request_fingerprint() {
        $server_vars = $_SERVER;
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
     * @param array $server_vars Variables serveur disponibles.
     *
     * @return string
     */
    private function get_public_request_ip($server_vars) {
        $headers = array(
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
        );

        foreach ($headers as $header) {
            if (empty($server_vars[$header])) {
                continue;
            }

            $raw_values = wp_unslash($server_vars[$header]);

            if ('REMOTE_ADDR' === $header) {
                $raw_values = array($raw_values);
            } else {
                $raw_values = explode(',', (string) $raw_values);
            }

            foreach ($raw_values as $value) {
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

        $hour      = (int) date('H');
        $variation = sin($hour * 0.26) * 10;

        return array(
            'online'               => (int) round($base_online + $variation),
            'total'                => (int) $base_total,
            'server_name'          => __('Serveur Démo', 'discord-bot-jlg'),
            'is_demo'              => true,
            'fallback_demo'        => (bool) $is_fallback,
            'has_total'            => true,
            'total_is_approximate' => false,
        );
    }

    private function get_last_good_cache_key() {
        return $this->cache_key . self::LAST_GOOD_SUFFIX;
    }

    private function get_fallback_retry_key() {
        return $this->cache_key . self::FALLBACK_RETRY_SUFFIX;
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

    private function schedule_next_fallback_retry($cache_duration, $options) {
        $retry_window = $this->get_fallback_retry_window($cache_duration, $options);
        $next_retry   = time() + $retry_window;

        set_transient($this->get_fallback_retry_key(), $next_retry, $retry_window);

        return $next_retry;
    }

    private function clear_fallback_retry_schedule() {
        delete_transient($this->get_fallback_retry_key());
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

        set_transient($this->get_last_good_cache_key(), $payload, 0);
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
            $this->last_error = __('Réponse JSON invalide reçue depuis le widget Discord.', 'discord-bot-jlg');
            return false;
        }

        $has_presence_count = isset($data['presence_count']);
        $has_members_list   = isset($data['members']) && is_array($data['members']);

        if (false === $has_presence_count && false === $has_members_list) {
            $this->last_error = __('Données incomplètes reçues depuis le widget Discord.', 'discord-bot-jlg');
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
            $this->last_error = __('Réponse JSON invalide reçue depuis l\'API Discord (bot).', 'discord-bot-jlg');
            return false;
        }

        if (false === isset($data['approximate_presence_count'], $data['approximate_member_count'])) {
            $this->last_error = __('Données incomplètes reçues depuis l\'API Discord (bot).', 'discord-bot-jlg');
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

        return $stats;
    }
}
