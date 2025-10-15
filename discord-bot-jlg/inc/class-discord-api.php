<?php

if (false === defined('ABSPATH')) {
    exit;
}

if (!function_exists('discord_bot_jlg_validate_bool')) {
    require_once __DIR__ . '/helpers.php';
}

if (!class_exists('Discord_Bot_JLG_Options_Repository')) {
    require_once __DIR__ . '/class-discord-options-repository.php';
}

if (!class_exists('Discord_Bot_JLG_Profile_Repository')) {
    require_once __DIR__ . '/class-discord-profile-repository.php';
}

if (!class_exists('Discord_Bot_JLG_Cache_Gateway')) {
    require_once __DIR__ . '/class-discord-cache-gateway.php';
}

if (!class_exists('Discord_Bot_JLG_Http_Connector')) {
    require_once __DIR__ . '/class-discord-http-connector.php';
}

if (!class_exists('Discord_Bot_JLG_Stats_Fetcher')) {
    require_once __DIR__ . '/class-discord-stats-fetcher.php';
}
if (!class_exists('Discord_Bot_JLG_Stats_Service')) {
    require_once __DIR__ . '/class-discord-stats-service.php';
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
    private $options_repository;
    private $runtime_fallback_retry_timestamp;
    private $analytics;
    private $event_logger;
    private $alerts;
    private $runtime_status_history;
    private $refresh_dispatcher;
    private $profile_repository;
    private $cache_gateway;
    private $http_connector;
    private $logger;
    private $stats_fetcher;
    private $stats_service;

    /**
     * Prépare le service d'accès aux statistiques avec les clés et durées nécessaires.
     *
     * @param string $option_name            Nom de l'option contenant la configuration du plugin.
     * @param string $cache_key              Clef de cache utilisée pour mémoriser les statistiques.
     * @param int    $default_cache_duration Durée du cache utilisée à défaut (en secondes).
     *
     * @return void
     */
    public function __construct($option_name, $cache_key, $default_cache_duration = 300, $http_client = null, $analytics = null, $event_logger = null, $options_repository = null, $profile_repository = null, $cache_gateway = null, $http_connector = null, $logger = null) {
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
        if ($options_repository instanceof Discord_Bot_JLG_Options_Repository) {
            $this->options_repository = $options_repository;
        } else {
            $default_provider = function_exists('discord_bot_jlg_get_default_options')
                ? 'discord_bot_jlg_get_default_options'
                : null;
            $this->options_repository = new Discord_Bot_JLG_Options_Repository(
                $this->option_name,
                $default_provider
            );
        }
        $this->runtime_fallback_retry_timestamp = 0;
        $this->analytics = ($analytics instanceof Discord_Bot_JLG_Analytics) ? $analytics : null;
        $this->event_logger = ($event_logger instanceof Discord_Bot_JLG_Event_Logger)
            ? $event_logger
            : new Discord_Bot_JLG_Event_Logger();
        $this->alerts = null;
        $this->runtime_status_history = array();
        $this->refresh_dispatcher = null;
        $this->profile_repository = ($profile_repository instanceof Discord_Bot_JLG_Profile_Repository)
            ? $profile_repository
            : new Discord_Bot_JLG_Profile_Repository();
        $this->cache_gateway = ($cache_gateway instanceof Discord_Bot_JLG_Cache_Gateway)
            ? $cache_gateway
            : new Discord_Bot_JLG_Cache_Gateway();
        if ($http_connector instanceof Discord_Bot_JLG_Http_Connector) {
            $this->http_connector = $http_connector;
        } else {
            $this->http_connector = $this->create_http_connector();
        }
        $this->logger = null;
        $this->set_logger($logger);
        $this->stats_fetcher = null;
        $this->stats_service = null;
    }

    public function set_analytics_service($analytics) {
        if ($analytics instanceof Discord_Bot_JLG_Analytics) {
            $this->analytics = $analytics;
        }
    }

    public function get_analytics_service() {
        return $this->analytics;
    }

    public function set_event_logger($event_logger) {
        if ($event_logger instanceof Discord_Bot_JLG_Event_Logger) {
            $this->event_logger = $event_logger;
        }
    }

    public function get_event_logger() {
        return $this->event_logger;
    }

    public function set_logger($logger) {
        if (discord_bot_jlg_is_psr_logger($logger)) {
            $this->logger = $logger;
        } else {
            $this->logger = null;
        }

        if ($this->http_connector instanceof Discord_Bot_JLG_Http_Connector) {
            $this->http_connector->set_logger($this->logger);
        }

    }

    public function get_logger() {
        if (discord_bot_jlg_is_psr_logger($this->logger)) {
            return $this->logger;
        }

        return null;
    }

    public function set_alerts_service($alerts) {
        if ($alerts instanceof Discord_Bot_JLG_Alerts) {
            $this->alerts = $alerts;
        }
    }

    public function get_alerts_service() {
        return $this->alerts;
    }

    public function set_refresh_dispatcher($dispatcher) {
        if (is_object($dispatcher) && method_exists($dispatcher, 'dispatch_refresh_jobs')) {
            $this->refresh_dispatcher = $dispatcher;
        }
    }

    public function get_refresh_dispatcher() {
        return $this->refresh_dispatcher;
    }

    private function get_profile_repository() {
        if (!($this->profile_repository instanceof Discord_Bot_JLG_Profile_Repository)) {
            $this->profile_repository = new Discord_Bot_JLG_Profile_Repository();
        }

        return $this->profile_repository;
    }

    private function get_cache_gateway() {
        if (!($this->cache_gateway instanceof Discord_Bot_JLG_Cache_Gateway)) {
            $this->cache_gateway = new Discord_Bot_JLG_Cache_Gateway();
        }

        return $this->cache_gateway;
    }

    private function create_http_connector() {
        $widget_fetcher = function ($options) {
            return $this->fetch_widget_stats($options);
        };

        $bot_fetcher = function ($options) {
            return $this->fetch_bot_stats($options);
        };

        return new Discord_Bot_JLG_Http_Connector($widget_fetcher, $bot_fetcher, $this->logger);
    }

    private function get_http_connector() {
        if (!($this->http_connector instanceof Discord_Bot_JLG_Http_Connector)) {
            $this->http_connector = $this->create_http_connector();
        }

        if ($this->http_connector instanceof Discord_Bot_JLG_Http_Connector) {
            $this->http_connector->set_logger($this->logger);
        }

        return $this->http_connector;
    }

    public function set_stats_fetcher($stats_fetcher) {
        if ($stats_fetcher instanceof Discord_Bot_JLG_Stats_Fetcher) {
            $this->stats_fetcher = $stats_fetcher;
        }
    }

    private function get_stats_fetcher() {
        if (!($this->stats_fetcher instanceof Discord_Bot_JLG_Stats_Fetcher)) {
            $this->stats_fetcher = $this->create_stats_fetcher($this->get_http_connector());
        }

        return $this->stats_fetcher;
    }

    private function get_stats_service() {
        if (!($this->stats_service instanceof Discord_Bot_JLG_Stats_Service)) {
            $this->stats_service = new Discord_Bot_JLG_Stats_Service(
                $this->get_cache_gateway(),
                $this->get_stats_fetcher(),
                $this->get_logger(),
                $this->get_event_logger()
            );
        }

        return $this->stats_service;
    }

    private function create_stats_fetcher(Discord_Bot_JLG_Http_Connector $http_connector) {
        $bot_token_provider = function ($options) {
            return $this->get_bot_token($options);
        };

        $needs_completion_callback = function ($stats) {
            return $this->stats_need_completion($stats);
        };

        $merge_callback = function ($widget_stats, $bot_stats, $widget_incomplete) {
            return $this->merge_stats($widget_stats, $bot_stats, $widget_incomplete);
        };

        $normalize_callback = function ($stats) {
            return $this->normalize_stats($stats);
        };

        $has_usable_callback = function ($stats) {
            return $this->has_usable_stats($stats);
        };

        return new Discord_Bot_JLG_Stats_Fetcher(
            $http_connector,
            $bot_token_provider,
            $needs_completion_callback,
            $merge_callback,
            $normalize_callback,
            $has_usable_callback
        );
    }

    public function get_status_history($args = array()) {
        if (!($this->event_logger instanceof Discord_Bot_JLG_Event_Logger)) {
            return array();
        }

        $defaults = array(
            'limit'       => 5,
            'profile_key' => '',
            'server_id'   => '',
            'types'       => array('discord_http', 'discord_connector'),
        );

        $args = wp_parse_args($args, $defaults);

        $limit = (int) $args['limit'];
        if ($limit <= 0) {
            $limit = $defaults['limit'];
        } elseif ($limit > 20) {
            $limit = 20;
        }

        $allowed_types = array();
        if (is_array($args['types'])) {
            foreach ($args['types'] as $type) {
                $type = sanitize_key($type);
                if ('' !== $type) {
                    $allowed_types[] = $type;
                }
            }
        }

        if (empty($allowed_types)) {
            $allowed_types = $defaults['types'];
        }

        $profile_key = discord_bot_jlg_sanitize_profile_key($args['profile_key']);
        $server_id   = $this->sanitize_server_id($args['server_id']);

        $normalized_args = array(
            'limit'       => $limit,
            'profile_key' => $profile_key,
            'server_id'   => $server_id,
            'types'       => $allowed_types,
        );

        $cache_key = md5(wp_json_encode($normalized_args));
        if (isset($this->runtime_status_history[$cache_key])) {
            return $this->runtime_status_history[$cache_key];
        }

        $fetch_limit = min(50, max($limit * 4, $limit));

        $raw_events = $this->event_logger->get_events(
            array(
                'limit' => $fetch_limit,
            )
        );

        if (!is_array($raw_events) || empty($raw_events)) {
            return array();
        }

        $history = array();

        foreach ($raw_events as $event) {
            if (!is_array($event) || empty($event['type'])) {
                continue;
            }

            $event_type = sanitize_key($event['type']);
            if (!in_array($event_type, $allowed_types, true)) {
                continue;
            }

            $context = isset($event['context']) && is_array($event['context']) ? $event['context'] : array();

            if ('' !== $profile_key && isset($context['profile_key'])) {
                if (discord_bot_jlg_sanitize_profile_key($context['profile_key']) !== $profile_key) {
                    continue;
                }
            }

            if ('' !== $server_id && isset($context['server_id'])) {
                if ($this->sanitize_server_id($context['server_id']) !== $server_id) {
                    continue;
                }
            }

            $entry = $this->transform_event_to_status_history_entry($event_type, $event);

            if (empty($entry)) {
                continue;
            }

            $history[] = $entry;

            if (count($history) >= $limit) {
                break;
            }
        }

        $this->runtime_status_history[$cache_key] = $history;

        return $history;
    }

    /**
     * Fournit un instantané de santé pour l'administration.
     *
     * @param array $args Paramètres optionnels (events_limit).
     *
     * @return array
     */
    public function get_admin_health_snapshot($args = array()) {
        $defaults = array(
            'events_limit' => 6,
            'event_type'   => '',
            'channel'      => '',
            'profile_key'  => '',
            'server_id'    => '',
        );

        $args = wp_parse_args($args, $defaults);

        $events_limit = max(1, (int) $args['events_limit']);

        $snapshot = array(
            'rate_limit'   => null,
            'last_error'   => null,
            'last_success' => null,
            'retry_after'  => max(0, (int) $this->last_retry_after),
            'fallback'     => $this->get_last_fallback_details(),
            'events'       => array(),
        );

        $event_logger = $this->get_event_logger();

        if (!($event_logger instanceof Discord_Bot_JLG_Event_Logger)) {
            return $snapshot;
        }

        $raw_events = $event_logger->get_events(
            array(
                'limit' => min(100, max($events_limit * 3, $events_limit)),
            )
        );

        if (!is_array($raw_events) || empty($raw_events)) {
            return $snapshot;
        }

        foreach ($raw_events as $event) {
            if (!is_array($event)) {
                continue;
            }

            $event_type = isset($event['type']) ? sanitize_key($event['type']) : '';

            if ('' === $event_type) {
                continue;
            }

            $context   = isset($event['context']) && is_array($event['context']) ? $event['context'] : array();
            $timestamp = isset($event['timestamp']) ? (int) $event['timestamp'] : 0;
            if ($timestamp <= 0) {
                $timestamp = (int) current_time('timestamp', true);
            }

            if (null === $snapshot['rate_limit'] && isset($context['rate_limit_limit'])) {
                $snapshot['rate_limit'] = $this->extract_rate_limit_context($context, $timestamp);
            }

            $entry = $this->transform_event_to_status_history_entry($event_type, $event);

            if (!empty($entry)) {
                $snapshot['events'][] = $entry;
            }

            $outcome = isset($context['outcome']) ? sanitize_key($context['outcome']) : '';

            if (null === $snapshot['last_success'] && 'success' === $outcome) {
                $snapshot['last_success'] = array(
                    'label'     => isset($entry['label']) ? $entry['label'] : '',
                    'reason'    => isset($entry['reason']) ? $entry['reason'] : '',
                    'timestamp' => $timestamp,
                );
            }

            if (null === $snapshot['last_error'] && 'success' !== $outcome) {
                $snapshot['last_error'] = array(
                    'label'     => isset($entry['label']) ? $entry['label'] : '',
                    'reason'    => isset($entry['reason']) ? $entry['reason'] : '',
                    'timestamp' => $timestamp,
                );
            }

            if (
                count($snapshot['events']) >= $events_limit
                && null !== $snapshot['rate_limit']
                && null !== $snapshot['last_error']
                && null !== $snapshot['last_success']
            ) {
                break;
            }
        }

        $snapshot['events'] = $this->get_monitoring_timeline(
            array(
                'limit'       => $events_limit,
                'event_type'  => isset($args['event_type']) ? $args['event_type'] : '',
                'channel'     => isset($args['channel']) ? $args['channel'] : '',
                'profile_key' => isset($args['profile_key']) ? $args['profile_key'] : '',
                'server_id'   => isset($args['server_id']) ? $args['server_id'] : '',
            )
        );

        return $snapshot;
    }

    public function get_monitoring_timeline($args = array()) {
        $defaults = array(
            'limit'       => 20,
            'event_type'  => '',
            'channel'     => '',
            'profile_key' => '',
            'server_id'   => '',
        );

        $args = wp_parse_args($args, $defaults);

        $limit = max(1, min(100, (int) $args['limit']));

        $event_logger = $this->get_event_logger();

        if (!($event_logger instanceof Discord_Bot_JLG_Event_Logger)) {
            return array();
        }

        $raw_events = $event_logger->get_events(
            array(
                'limit' => min(200, max($limit * 4, $limit)),
            )
        );

        if (!is_array($raw_events) || empty($raw_events)) {
            return array();
        }

        $event_type_filter = sanitize_key($args['event_type']);
        $channel_filter    = sanitize_key($args['channel']);
        $profile_filter    = discord_bot_jlg_sanitize_profile_key($args['profile_key']);
        $server_filter     = $this->sanitize_server_id($args['server_id']);

        $entries = array();

        foreach ($raw_events as $event) {
            if (!is_array($event)) {
                continue;
            }

            $event_type = isset($event['type']) ? sanitize_key($event['type']) : '';

            if ('' === $event_type) {
                continue;
            }

            if ('' !== $event_type_filter && $event_type !== $event_type_filter) {
                continue;
            }

            $context = isset($event['context']) && is_array($event['context']) ? $event['context'] : array();

            $event_channel = isset($context['channel']) ? sanitize_key($context['channel']) : '';
            if ('' !== $channel_filter && $event_channel !== $channel_filter) {
                continue;
            }

            $event_profile = isset($context['profile_key']) ? discord_bot_jlg_sanitize_profile_key($context['profile_key']) : '';
            if ('' !== $profile_filter && $event_profile !== $profile_filter) {
                continue;
            }

            $event_server = isset($context['server_id']) ? $this->sanitize_server_id($context['server_id']) : '';
            if ('' !== $server_filter && $event_server !== $server_filter) {
                continue;
            }

            $entry = $this->transform_event_to_status_history_entry($event_type, $event);

            if (empty($entry)) {
                continue;
            }

            $entry['channel'] = $event_channel;
            $entry['outcome'] = isset($context['outcome']) ? sanitize_key($context['outcome']) : '';
            $entry['profile_key'] = $event_profile;
            $entry['server_id'] = $event_server;
            $entry['context'] = $context;

            $entries[] = $entry;

            if (count($entries) >= $limit) {
                break;
            }
        }

        return $entries;
    }

    /**
     * Extrait les informations de rate limiting depuis un contexte d'événement.
     *
     * @param array $context   Contexte de l'événement.
     * @param int   $timestamp Horodatage associé.
     *
     * @return array
     */
    private function extract_rate_limit_context(array $context, $timestamp) {
        $limit     = isset($context['rate_limit_limit']) ? (int) $context['rate_limit_limit'] : null;
        $remaining = isset($context['rate_limit_remaining']) ? (int) $context['rate_limit_remaining'] : null;
        $reset     = 0.0;

        if (isset($context['rate_limit_reset_after'])) {
            $reset = (float) $context['rate_limit_reset_after'];
        }

        $bucket = isset($context['rate_limit_bucket']) ? sanitize_text_field($context['rate_limit_bucket']) : '';

        $global_flag = false;
        if (isset($context['rate_limit_global'])) {
            $raw_global = $context['rate_limit_global'];
            if (is_bool($raw_global)) {
                $global_flag = $raw_global;
            } elseif (is_numeric($raw_global)) {
                $global_flag = ((int) $raw_global) > 0;
            } elseif (is_string($raw_global)) {
                $normalized = strtolower(trim($raw_global));
                $global_flag = in_array($normalized, array('1', 'true', 'yes', 'on'), true);
            }
        }

        $retry_after = null;
        if (isset($context['retry_after'])) {
            $retry_after = max(0, (int) round((float) $context['retry_after']));
        }

        return array(
            'limit'       => $limit,
            'remaining'   => $remaining,
            'reset_after' => $reset,
            'bucket'      => $bucket,
            'global'      => $global_flag,
            'retry_after' => $retry_after,
            'timestamp'   => $timestamp,
        );
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
        if ($this->options_repository instanceof Discord_Bot_JLG_Options_Repository) {
            $this->options_repository->flush_cache();
        }
    }

    /**
     * Récupère les options du plugin avec mise en cache en mémoire.
     *
     * @param bool $force_refresh Force une lecture depuis la base si vrai.
     *
     * @return array
     */
    public function get_plugin_options($force_refresh = false) {
        if ($this->options_repository instanceof Discord_Bot_JLG_Options_Repository) {
            return $this->options_repository->get_options($force_refresh);
        }

        $options = get_option($this->option_name);

        return is_array($options) ? $options : array();
    }

    public function get_analytics_retention_days($options = null) {
        if ($this->options_repository instanceof Discord_Bot_JLG_Options_Repository) {
            return $this->options_repository->get_analytics_retention_days($options);
        }

        if (!is_array($options)) {
            $options = $this->get_plugin_options();
        }

        $default_retention = defined('DISCORD_BOT_JLG_ANALYTICS_RETENTION_DEFAULT')
            ? (int) DISCORD_BOT_JLG_ANALYTICS_RETENTION_DEFAULT
            : (class_exists('Discord_Bot_JLG_Analytics') ? (int) Discord_Bot_JLG_Analytics::DEFAULT_RETENTION_DAYS : 0);

        $retention = isset($options['analytics_retention_days'])
            ? (int) $options['analytics_retention_days']
            : $default_retention;

        return ($retention < 0) ? 0 : $retention;
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
                    $profile_key = discord_bot_jlg_sanitize_profile_key($profile['key']);
                }

                if ('' === $profile_key) {
                    $profile_key = discord_bot_jlg_sanitize_profile_key($stored_key);
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
        $args['profile_key']  = isset($args['profile_key']) ? discord_bot_jlg_sanitize_profile_key($args['profile_key']) : '';
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

        $fetch_context = $this->prepare_fetch_context($args);
        $context       = $fetch_context['context'];
        $options       = $fetch_context['options'];
        $runtime_key   = $fetch_context['runtime_key'];

        if (true === $fetch_context['runtime_hit']) {
            $this->last_error       = $fetch_context['runtime_error'];
            $this->last_retry_after = $fetch_context['runtime_retry_after'];

            return $fetch_context['runtime_value'];
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
            $service_result = $this->get_stats_service()->execute(array(
                'cache_key'          => $this->cache_key,
                'options'            => $options,
                'context'            => $context,
                'args'               => $args,
                'bypass_cache'       => (bool) $args['bypass_cache'],
                'fallback_provider'  => function ($force_fallback = true) {
                    return $this->get_demo_stats((bool) $force_fallback);
                },
                'persist_success'    => function ($stats, $service_options, $service_context, $service_args) {
                    $this->persist_successful_stats($stats, $service_options, $service_context, $service_args);
                },
                'persist_fallback'   => function ($stats, $service_options, $reason) {
                    return $this->persist_fallback_stats($stats, $service_options, $reason);
                },
                'validate_stats'     => function ($stats) {
                    return $this->has_usable_stats($stats);
                },
                'store_retry_after'  => function ($retry_after) {
                    $this->store_api_retry_after_delay($retry_after);
                },
                'register_cache_key' => function () {
                    $this->register_current_cache_key();
                },
                'read_retry_after'   => function () {
                    return $this->last_retry_after;
                },
                'last_retry_after'   => $this->last_retry_after,
            ));

            $stats = isset($service_result['stats']) ? $service_result['stats'] : null;
            $service_error = isset($service_result['error']) ? (string) $service_result['error'] : '';
            $existing_error = $this->last_error;

            if ('' !== $service_error) {
                if ('' !== $existing_error && false === strpos($service_error, $existing_error)) {
                    $service_error = trim($service_error . ' (' . $existing_error . ')');
                }

                $this->last_error = $service_error;
            } else {
                $this->last_error = $existing_error;
            }
            $this->set_last_retry_after(isset($service_result['retry_after']) ? $service_result['retry_after'] : 0);

            if (empty($this->last_error) && empty($stats) && !empty($service_result['fallback_used'])) {
                $this->last_error = __('Impossible d\'obtenir des statistiques exploitables depuis Discord.', 'discord-bot-jlg');
            }

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

    private function prepare_fetch_context($args) {
        $options = $this->get_plugin_options();

        $context = $this->resolve_connection_context($args, $options);

        if (isset($context['options']) && is_array($context['options'])) {
            $options = $context['options'];
        } else {
            $options = array();
            $context['options'] = array();
        }

        $runtime_args = array(
            'force_demo'   => !empty($args['force_demo']),
            'bypass_cache' => !empty($args['bypass_cache']),
            'signature'    => isset($context['signature']) ? (string) $context['signature'] : '',
        );

        $runtime_key = $this->get_runtime_cache_key($runtime_args);
        $runtime_hit = array_key_exists($runtime_key, $this->runtime_cache);

        $runtime_error = '';
        $runtime_retry_after = 0;
        $runtime_value = null;

        if (true === $runtime_hit) {
            $runtime_value = $this->runtime_cache[$runtime_key];
            if (isset($this->runtime_errors[$runtime_key])) {
                $runtime_error = $this->runtime_errors[$runtime_key];
            }
            if (isset($this->runtime_retry_after[$runtime_key])) {
                $runtime_retry_after = (int) $this->runtime_retry_after[$runtime_key];
            }
        }

        return array(
            'options'             => $options,
            'context'             => $context,
            'runtime_key'         => $runtime_key,
            'runtime_hit'         => $runtime_hit,
            'runtime_value'       => $runtime_value,
            'runtime_error'       => $runtime_error,
            'runtime_retry_after' => $runtime_retry_after,
        );
    }

    private function fetch_widget_stats($options) {
        $widget_stats = $this->get_stats_from_widget($options);

        if (!is_array($widget_stats)) {
            return null;
        }

        return $this->normalize_stats($widget_stats);
    }

    private function fetch_bot_stats($options) {
        $bot_stats = $this->get_stats_from_bot($options);

        if (!is_array($bot_stats)) {
            return null;
        }

        return $this->normalize_stats($bot_stats);
    }

    private function merge_stats($widget_stats, $bot_stats, $widget_incomplete = null) {
        if (null === $widget_incomplete) {
            $widget_incomplete = $this->stats_need_completion($widget_stats);
        }

        if (
            true === $widget_incomplete
            && is_array($widget_stats)
            && is_array($bot_stats)
        ) {
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

            $presence_selection = $this->select_presence_breakdown_for_merge($widget_stats, $bot_stats);

            if (!empty($presence_selection['breakdown'])) {
                $stats['presence_count_by_status'] = $presence_selection['breakdown'];
            } elseif (isset($widget_stats['presence_count_by_status'])) {
                $stats['presence_count_by_status'] = $widget_stats['presence_count_by_status'];
            } elseif (isset($bot_stats['presence_count_by_status'])) {
                $stats['presence_count_by_status'] = $bot_stats['presence_count_by_status'];
            }

            if (!empty($presence_selection['source'])) {
                $preferred_stats = 'widget' === $presence_selection['source'] ? $widget_stats : $bot_stats;

                if (
                    isset($preferred_stats['approximate_presence_count'])
                    && null !== $preferred_stats['approximate_presence_count']
                ) {
                    $stats['approximate_presence_count'] = (int) $preferred_stats['approximate_presence_count'];
                }
            }

            if (!array_key_exists('approximate_presence_count', $stats)) {
                if (isset($widget_stats['approximate_presence_count']) && null !== $widget_stats['approximate_presence_count']) {
                    $stats['approximate_presence_count'] = (int) $widget_stats['approximate_presence_count'];
                } elseif (isset($bot_stats['approximate_presence_count'])) {
                    $stats['approximate_presence_count'] = (int) $bot_stats['approximate_presence_count'];
                }
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

            return $stats;
        }

        if (is_array($widget_stats)) {
            return $widget_stats;
        }

        if (is_array($bot_stats)) {
            return $bot_stats;
        }

        return null;
    }

    private function select_presence_breakdown_for_merge($widget_stats, $bot_stats) {
        $selection = array(
            'source'    => null,
            'breakdown' => array(),
        );

        $widget_candidate = $this->build_presence_breakdown_candidate($widget_stats);
        $bot_candidate    = $this->build_presence_breakdown_candidate($bot_stats);

        if ($widget_candidate && $bot_candidate) {
            $selection['source']    = 'widget';
            $selection['breakdown'] = $widget_candidate['breakdown'];

            foreach ($bot_candidate['breakdown'] as $status => $count) {
                $count = max(0, (int) $count);

                if (!array_key_exists($status, $selection['breakdown'])) {
                    $selection['breakdown'][$status] = $count;
                    continue;
                }

                if ($count > 0) {
                    $selection['breakdown'][$status] += $count;
                }
            }
        } elseif ($widget_candidate) {
            $selection['source']    = 'widget';
            $selection['breakdown'] = $widget_candidate['breakdown'];
        } elseif ($bot_candidate) {
            $selection['source']    = 'bot';
            $selection['breakdown'] = $bot_candidate['breakdown'];
        }

        return $selection;
    }

    private function build_presence_breakdown_candidate($source_stats) {
        if (
            !is_array($source_stats)
            || empty($source_stats['presence_count_by_status'])
            || !is_array($source_stats['presence_count_by_status'])
        ) {
            return null;
        }

        $breakdown = array();
        $total     = 0;
        $non_zero  = 0;

        foreach ($source_stats['presence_count_by_status'] as $status_key => $count_value) {
            $int_value = max(0, (int) $count_value);
            $breakdown[$status_key] = $int_value;
            $total += $int_value;

            if ($int_value > 0) {
                $non_zero++;
            }
        }

        if (empty($breakdown)) {
            return null;
        }

        return array(
            'breakdown'          => $breakdown,
            'total'              => $total,
            'non_zero_statuses'  => $non_zero,
        );
    }

    private function persist_successful_stats($stats, $options, $context, $args) {
        if (!is_array($stats)) {
            return;
        }

        $this->last_error = '';
        $this->set_last_retry_after(0);
        $this->clear_api_retry_after_delay();
        $this->register_current_cache_key();
        $this->get_cache_gateway()->set($this->cache_key, $stats, $this->get_cache_duration($options));
        $this->store_last_good_stats($stats);
        $this->clear_last_fallback_details();

        if (!$this->should_log_stats($stats)) {
            return;
        }

        $profile_key = 'default';
        if (!empty($args['profile_key'])) {
            $profile_key = $args['profile_key'];
        }

        $server_id = '';
        if (isset($context['options']['server_id'])) {
            $server_id = (string) $context['options']['server_id'];
        } elseif (isset($options['server_id'])) {
            $server_id = (string) $options['server_id'];
        }

        $this->log_snapshot($profile_key, $server_id, $stats);
    }

    private function get_widget_snapshot_transient_name($cache_key) {
        return $cache_key . '_widget_snapshot';
    }

    private function store_widget_snapshot_payload($cache_key, array $stats, array $options) {
        $payload = array(
            'stats'     => $stats,
            'timestamp' => time(),
        );

        $ttl = max($this->get_cache_duration($options), self::MIN_PUBLIC_REFRESH_INTERVAL);
        set_transient($this->get_widget_snapshot_transient_name($cache_key), $payload, max(60, (int) $ttl));
    }

    private function get_widget_snapshot_payload($cache_key) {
        $payload = get_transient($this->get_widget_snapshot_transient_name($cache_key));

        if (!is_array($payload) || !isset($payload['stats'])) {
            return null;
        }

        return $payload;
    }

    private function clear_widget_snapshot_payload($cache_key) {
        delete_transient($this->get_widget_snapshot_transient_name($cache_key));
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

        if ($next_retry <= 0 && isset($option['next_retry'])) {
            $next_retry = (int) $option['next_retry'];
        }

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
    private function build_refresh_response($success, $data, $status_code) {
        if (!is_array($data)) {
            $data = array();
        }

        return array(
            'success' => (bool) $success,
            'data'    => $data,
            'status'  => (int) $status_code,
        );
    }

    /**
     * Normalise les réponses réussies renvoyées aux clients.
     *
     * @param array $data        Données de réponse.
     * @param int   $retry_after Délai éventuel avant une prochaine tentative.
     *
     * @return array
     */
    private function build_success_payload($data = array(), $retry_after = null) {
        if (!is_array($data)) {
            $data = array();
        }

        if (null !== $retry_after) {
            $data['retry_after'] = max(0, (int) $retry_after);
        }

        return $data;
    }

    /**
     * Construit les charges utiles d'erreur standards de l'API.
     *
     * @param string $type    Identifiant de l'erreur.
     * @param array  $context Contexte additionnel (retry_after, message, diagnostic...).
     *
     * @return array
     */
    private function build_error_payload($type, array $context = array()) {
        $retry_after = null;

        if (array_key_exists('retry_after', $context)) {
            $retry_after = max(0, (int) $context['retry_after']);
        }

        switch ($type) {
            case 'rate_limited':
                $seconds = (null !== $retry_after) ? $retry_after : 0;
                $message = isset($context['message']) ? (string) $context['message'] : '';

                if ('' === $message) {
                    $message = sprintf(
                        /* translators: %d: number of seconds to wait before the next refresh. */
                        __('Veuillez patienter %d secondes avant la prochaine actualisation.', 'discord-bot-jlg'),
                        $seconds
                    );
                }

                $payload = array(
                    'rate_limited' => true,
                    'message'      => $message,
                );

                if (null !== $retry_after) {
                    $payload['retry_after'] = $retry_after;
                }

                break;

            case 'refresh_in_progress':
                $payload = array(
                    'rate_limited' => false,
                    'message'      => __('Actualisation en cours, veuillez réessayer dans quelques instants.', 'discord-bot-jlg'),
                );

                if (null !== $retry_after) {
                    $payload['retry_after'] = $retry_after;
                }

                break;

            case 'demo_mode':
                $payload = array(
                    'rate_limited' => false,
                    'message'      => __('Mode démo actif', 'discord-bot-jlg'),
                );

                if (null !== $retry_after) {
                    $payload['retry_after'] = $retry_after;
                }

                break;

            default:
                $message = isset($context['message']) ? (string) $context['message'] : '';

                $payload = array(
                    'rate_limited' => isset($context['rate_limited']) ? (bool) $context['rate_limited'] : false,
                    'message'      => $message,
                );

                if (null !== $retry_after) {
                    $payload['retry_after'] = $retry_after;
                }

                break;
        }

        if (!empty($context['diagnostic'])) {
            $payload['diagnostic'] = (string) $context['diagnostic'];
        }

        if (isset($context['extra']) && is_array($context['extra'])) {
            $payload = array_merge($payload, $context['extra']);
        }

        return $payload;
    }

    /**
     * Calcule la stratégie d'actualisation en fonction de la requête et du cache courant.
     *
     * @param bool  $is_public_request Indique si la requête provient du frontal public.
     * @param array $options           Options du plugin.
     * @param bool  $force_refresh     Forçage éventuel depuis l'interface d'administration.
     *
     * @return array
     */
    private function compute_refresh_policy($is_public_request, $options, $force_refresh) {
        $rate_limit_key        = $this->cache_key . self::REFRESH_LOCK_SUFFIX;
        $client_rate_limit_key = $this->get_client_rate_limit_key($is_public_request);
        $cache_duration        = $this->get_cache_duration($options);
        $default_public_refresh = max(self::MIN_PUBLIC_REFRESH_INTERVAL, (int) $cache_duration);
        $rate_limit_window = (int) apply_filters('discord_bot_jlg_public_refresh_interval', $default_public_refresh, $options);

        if ($rate_limit_window < self::MIN_PUBLIC_REFRESH_INTERVAL) {
            $rate_limit_window = self::MIN_PUBLIC_REFRESH_INTERVAL;
        }

        $cached_stats = get_transient($this->cache_key);
        $cached_stats_is_fallback = (
            is_array($cached_stats)
            && !empty($cached_stats['is_demo'])
            && !empty($cached_stats['fallback_demo'])
        );

        $fallback_retry_key   = $this->get_fallback_retry_key();
        $fallback_retry_after = (int) get_transient($fallback_retry_key);

        if ($fallback_retry_after > 0) {
            $this->set_runtime_fallback_retry_timestamp($fallback_retry_after);
        }

        if (true === $cached_stats_is_fallback && $fallback_retry_after <= 0) {
            $fallback_retry_after = $this->schedule_next_fallback_retry($cache_duration, $options);
        }

        $policy = array(
            'rate_limit_key'            => $rate_limit_key,
            'client_rate_limit_key'     => $client_rate_limit_key,
            'rate_limit_window'         => $rate_limit_window,
            'cache_duration'            => $cache_duration,
            'cached_stats'              => $cached_stats,
            'bypass_cache'              => false,
            'refresh_requires_remote_call' => false,
            'response'                  => null,
        );

        $now = time();

        if (true === $is_public_request) {
            if (!empty($client_rate_limit_key)) {
                $client_retry_after = $this->get_retry_after($client_rate_limit_key, $rate_limit_window);

                if ($client_retry_after > 0) {
                    $policy['response'] = $this->build_refresh_response(
                        false,
                        $this->build_error_payload('rate_limited', array('retry_after' => $client_retry_after)),
                        429
                    );

                    return $policy;
                }
            }

            $last_refresh = get_transient($rate_limit_key);

            if (false !== $last_refresh) {
                $elapsed = time() - (int) $last_refresh;

                if ($elapsed < $rate_limit_window) {
                    $retry_after = max(0, $rate_limit_window - $elapsed);

                    $policy['response'] = $this->build_refresh_response(
                        false,
                        $this->build_error_payload('rate_limited', array('retry_after' => $retry_after)),
                        429
                    );

                    return $policy;
                }
            }

            if ($cached_stats_is_fallback) {
                if ($fallback_retry_after > $now) {
                    $next_retry = $this->get_runtime_fallback_retry_timestamp();

                    if ($next_retry <= 0) {
                        $next_retry = (int) $fallback_retry_after;
                    }

                    $retry_after = max(0, (int) $next_retry - time());

                    $policy['response'] = $this->build_refresh_response(
                        true,
                        $this->build_success_payload($cached_stats, $retry_after),
                        200
                    );

                    return $policy;
                }

                $policy['refresh_requires_remote_call'] = true;
                $policy['bypass_cache'] = true;
            } elseif (is_array($cached_stats) && empty($cached_stats['is_demo'])) {
                $policy['response'] = $this->build_refresh_response(
                    true,
                    $this->build_success_payload($cached_stats),
                    200
                );

                return $policy;
            } else {
                $policy['refresh_requires_remote_call'] = true;
            }
        }

        if (false === $is_public_request && $cached_stats_is_fallback) {
            $policy['refresh_requires_remote_call'] = true;
            $policy['bypass_cache'] = true;
        }

        if (
            true === $force_refresh
            && false === $is_public_request
            && Discord_Bot_JLG_Capabilities::current_user_can('manage_profiles')
        ) {
            $policy['bypass_cache'] = true;
            $policy['refresh_requires_remote_call'] = true;
        }

        return $policy;
    }

    public function process_refresh_request($request_args = array()) {
        $defaults = array(
            'is_public_request' => true,
            'profile_key'       => '',
            'server_id'         => '',
            'force_refresh'     => false,
        );

        $args = wp_parse_args($request_args, $defaults);

        $is_public_request = !empty($args['is_public_request']);
        $profile_key_override = isset($args['profile_key']) ? discord_bot_jlg_sanitize_profile_key($args['profile_key']) : '';
        $server_id_override   = isset($args['server_id']) ? $this->sanitize_server_id($args['server_id']) : '';
        $force_refresh        = discord_bot_jlg_validate_bool(isset($args['force_refresh']) ? $args['force_refresh'] : false);

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

            return $this->build_refresh_response(false, $this->build_error_payload('custom', array(
                'message' => $error_message,
            )), 400);
        }

        $options = $context['options'];

        if (!empty($options['demo_mode'])) {
            return $this->build_refresh_response(false, $this->build_error_payload('demo_mode'), 200);
        }

        $original_cache_key = $this->cache_key;
        $this->cache_key     = $context['cache_key'];

        try {
            $policy = $this->compute_refresh_policy($is_public_request, $options, $force_refresh);

            if (is_array($policy['response'])) {
                return $policy['response'];
            }

            $rate_limit_key        = $policy['rate_limit_key'];
            $client_rate_limit_key = $policy['client_rate_limit_key'];
            $rate_limit_window     = $policy['rate_limit_window'];
            $cache_duration        = $policy['cache_duration'];
            $refresh_requires_remote_call = !empty($policy['refresh_requires_remote_call']);
            $bypass_cache          = !empty($policy['bypass_cache']);

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
                $stats = $this->enrich_stats_with_status_meta($stats, $options, $cache_duration);
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

                $next_retry    = $this->get_runtime_fallback_retry_timestamp();
                $retry_after   = null;
                if ($next_retry > 0) {
                    $retry_after = max(0, (int) $next_retry - time());
                }

                return $this->build_refresh_response(true, $this->build_success_payload($stats, $retry_after), 200);
            }

            if (is_array($stats) && empty($stats['is_demo'])) {
                $stats = $this->enrich_stats_with_status_meta($stats, $options, $cache_duration);
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

                return $this->build_refresh_response(true, $this->build_success_payload($stats), 200);
            }

            if (true === $is_public_request) {
                $cached_stats = get_transient($this->cache_key);
                if (is_array($cached_stats) && empty($cached_stats['is_demo'])) {
                    $cached_stats = $this->enrich_stats_with_status_meta($cached_stats, $options, $cache_duration);
                    return $this->build_refresh_response(true, $this->build_success_payload($cached_stats), 200);
                }

                delete_transient($rate_limit_key);
                if (!empty($client_rate_limit_key)) {
                    $this->delete_client_rate_limit($client_rate_limit_key);
                }

                $error_payload = $this->build_error_payload('refresh_in_progress', array(
                    'retry_after' => max(0, (int) $this->last_retry_after),
                ));

                if (!empty($last_error_message)) {
                    $this->log_debug(
                        sprintf(
                            'ajax_refresh_stats error (public request): %s',
                            $last_error_message
                        )
                    );
                }

                return $this->build_refresh_response(false, $error_payload, 503);
            }

            $error_payload = $this->build_error_payload('refresh_in_progress', array(
                'retry_after' => ($this->last_retry_after > 0) ? (int) $this->last_retry_after : null,
                'diagnostic'  => !empty($last_error_message) ? $last_error_message : null,
            ));

            return $this->build_refresh_response(false, $error_payload, 503);
        } finally {
            $this->cache_key = $original_cache_key;
        }
    }

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
            $profile_key_override = discord_bot_jlg_sanitize_profile_key(wp_unslash($_POST['profile_key']));
        }

        $server_id_override = '';
        if (isset($_POST['server_id'])) {
            $server_id_override = $this->sanitize_server_id(wp_unslash($_POST['server_id']));
        }

        $force_refresh = false;

        if (isset($_POST['force_refresh'])) {
            $requested_force_refresh = discord_bot_jlg_validate_bool(wp_unslash($_POST['force_refresh']));

            if (
                true === $requested_force_refresh
                && false === $is_public_request
                && Discord_Bot_JLG_Capabilities::current_user_can('manage_profiles')
            ) {
                $force_refresh = true;
            }
        }

        $result = $this->process_refresh_request(
            array(
                'is_public_request' => $is_public_request,
                'profile_key'       => $profile_key_override,
                'server_id'         => $server_id_override,
                'force_refresh'     => $force_refresh,
            )
        );

        $status = isset($result['status']) ? (int) $result['status'] : 200;

        if (!empty($result['success'])) {
            wp_send_json_success($result['data'], $status);
        }

        wp_send_json_error($result['data'], $status);
    }

    public function run_widget_refresh_job($profile_key = '', $job_context = array()) {
        $job_context = is_array($job_context) ? $job_context : array();
        $profile_key = discord_bot_jlg_sanitize_profile_key($profile_key);

        $args = array(
            'profile_key'  => $profile_key,
            'bypass_cache' => true,
        );

        if (isset($job_context['server_id'])) {
            $args['server_id'] = $this->sanitize_server_id($job_context['server_id']);
        }

        $fetch_context = $this->prepare_fetch_context($args);
        $options       = $fetch_context['options'];
        $context       = $fetch_context['context'];

        if (!empty($context['error'])) {
            if ($context['error'] instanceof WP_Error) {
                return $context['error'];
            }

            return new WP_Error(
                'discord_bot_jlg_widget_refresh_error',
                (string) $context['error']
            );
        }

        if (empty($options['server_id'])) {
            return new WP_Error(
                'discord_bot_jlg_widget_refresh_missing_server',
                __('Aucun identifiant de serveur Discord n’est associé à cette tâche.', 'discord-bot-jlg'),
                array(
                    'profile_key' => ('' !== $profile_key) ? $profile_key : 'default',
                    'should_retry' => false,
                )
            );
        }

        $original_cache_key = $this->cache_key;
        $this->cache_key     = $context['cache_key'];

        try {
            $widget_stats = $this->fetch_widget_stats($options);

            if (!is_array($widget_stats)) {
                $error_message = $this->get_last_error_message();

                if ('' === $error_message) {
                    $error_message = __('Impossible de récupérer les données du widget Discord.', 'discord-bot-jlg');
                }

                return new WP_Error(
                    'discord_bot_jlg_widget_refresh_failed',
                    $error_message,
                    array(
                        'profile_key' => ('' !== $profile_key) ? $profile_key : 'default',
                        'retry_after' => max(0, (int) $this->last_retry_after),
                    )
                );
            }

            $this->store_widget_snapshot_payload($this->cache_key, $widget_stats, $options);

            return array(
                'channel'     => 'widget',
                'profile_key' => ('' !== $profile_key) ? $profile_key : 'default',
                'cache_key'   => $this->cache_key,
                'stats'       => $widget_stats,
            );
        } finally {
            $this->cache_key = $original_cache_key;
        }
    }

    public function run_bot_refresh_job($profile_key = '', $job_context = array()) {
        $job_context = is_array($job_context) ? $job_context : array();
        $profile_key = discord_bot_jlg_sanitize_profile_key($profile_key);

        $args = array(
            'profile_key'  => $profile_key,
            'bypass_cache' => true,
        );

        if (isset($job_context['server_id'])) {
            $args['server_id'] = $this->sanitize_server_id($job_context['server_id']);
        }

        $fetch_context = $this->prepare_fetch_context($args);
        $options       = $fetch_context['options'];
        $context       = $fetch_context['context'];

        if (!empty($context['error'])) {
            if ($context['error'] instanceof WP_Error) {
                return $context['error'];
            }

            return new WP_Error(
                'discord_bot_jlg_bot_refresh_error',
                (string) $context['error']
            );
        }

        if (empty($options['server_id'])) {
            return new WP_Error(
                'discord_bot_jlg_bot_refresh_missing_server',
                __('Aucun identifiant de serveur Discord n’est associé à cette tâche.', 'discord-bot-jlg'),
                array(
                    'profile_key'  => ('' !== $profile_key) ? $profile_key : 'default',
                    'should_retry' => false,
                )
            );
        }

        $active_profile_key = isset($options['__active_profile_key'])
            ? discord_bot_jlg_sanitize_profile_key($options['__active_profile_key'])
            : (( '' !== $profile_key) ? $profile_key : 'default');

        $original_cache_key = $this->cache_key;
        $this->cache_key     = $context['cache_key'];

        try {
            $widget_snapshot = $this->get_widget_snapshot_payload($this->cache_key);
            $widget_stats    = (is_array($widget_snapshot) && isset($widget_snapshot['stats']))
                ? $widget_snapshot['stats']
                : null;

            $stats_fetcher = $this->get_stats_fetcher();

            $fetch_options = $options;
            if (is_array($widget_stats)) {
                $fetch_options['__prefetched_widget_stats'] = $widget_stats;
            }
            $fetch_options['__force_bot_fetch'] = true;

            $fetch_result = $stats_fetcher->fetch($fetch_options);

            if (isset($fetch_result['options']) && is_array($fetch_result['options'])) {
                $options = $fetch_result['options'];
            }

            $bot_token = isset($fetch_result['bot_token']) ? (string) $fetch_result['bot_token'] : '';
            if ('' === $bot_token) {
                return new WP_Error(
                    'discord_bot_jlg_bot_refresh_missing_token',
                    __('Aucun jeton de bot n’est disponible pour cette tâche.', 'discord-bot-jlg'),
                    array(
                        'profile_key'  => $active_profile_key,
                        'should_retry' => false,
                    )
                );
            }

            $bot_called = !empty($fetch_result['bot_called']);
            $bot_stats  = isset($fetch_result['bot_stats']) ? $fetch_result['bot_stats'] : null;

            if (false === $bot_called || !is_array($bot_stats)) {
                $error_message = $this->get_last_error_message();

                if ('' === $error_message) {
                    $error_message = __('Impossible de récupérer les données détaillées du bot Discord.', 'discord-bot-jlg');
                }

                return new WP_Error(
                    'discord_bot_jlg_bot_refresh_failed',
                    $error_message,
                    array(
                        'profile_key'  => $active_profile_key,
                        'retry_after'  => max(0, (int) $this->last_retry_after),
                    )
                );
            }

            $stats            = isset($fetch_result['stats']) ? $fetch_result['stats'] : null;
            $has_usable_stats = isset($fetch_result['has_usable_stats'])
                ? (bool) $fetch_result['has_usable_stats']
                : $this->has_usable_stats($stats);

            if (false === $has_usable_stats || !is_array($stats)) {
                $error_message = $this->get_last_error_message();

                if ('' === $error_message) {
                    $error_message = __('Les données combinées restent inexploitables.', 'discord-bot-jlg');
                }

                return new WP_Error(
                    'discord_bot_jlg_bot_refresh_incomplete',
                    $error_message,
                    array(
                        'profile_key'  => $active_profile_key,
                        'retry_after'  => max(0, (int) $this->last_retry_after),
                    )
                );
            }

            $this->persist_successful_stats(
                $stats,
                $options,
                $context,
                array(
                    'profile_key'  => $active_profile_key,
                    'bypass_cache' => true,
                )
            );

            $this->clear_widget_snapshot_payload($this->cache_key);

            return array(
                'channel'     => 'bot',
                'profile_key' => $active_profile_key,
                'cache_key'   => $this->cache_key,
                'stats'       => $stats,
            );
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
        if (is_object($this->refresh_dispatcher) && method_exists($this->refresh_dispatcher, 'dispatch_refresh_jobs')) {
            $this->refresh_dispatcher->dispatch_refresh_jobs();

            return;
        }

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
        } elseif (!empty($stats['is_demo']) && !empty($stats['fallback_demo'])) {
            $last_error = $this->get_last_error_message();

            if ('' === $last_error) {
                $last_error = 'Fallback statistics returned.';
            }

            $this->log_debug('Cron refresh produced fallback stats: ' . $last_error);
        }

        $profiles = $this->get_server_profiles(true);

        if (!is_array($profiles) || empty($profiles)) {
            $this->purge_analytics_if_needed($options);
            return;
        }

        foreach ($profiles as $profile_key => $profile) {
            if (!is_array($profile)) {
                continue;
            }

            $effective_profile_key = isset($profile['key'])
                ? discord_bot_jlg_sanitize_profile_key($profile['key'])
                : discord_bot_jlg_sanitize_profile_key($profile_key);

            if ('' === $effective_profile_key) {
                continue;
            }

            $profile_server_id = isset($profile['server_id']) ? $this->sanitize_server_id($profile['server_id']) : '';

            if ('' === $profile_server_id) {
                $this->log_debug(sprintf('Cron refresh skipped for profile "%s": missing server ID.', $effective_profile_key));
                continue;
            }

            $profile_token = '';

            if (isset($profile['bot_token'])) {
                $profile_token = trim((string) $profile['bot_token']);
            }

            if ('' === $profile_token) {
                $this->log_debug(sprintf('Cron refresh skipped for profile "%s": missing bot token.', $effective_profile_key));
                continue;
            }

            $profile_stats = $this->get_stats(
                array(
                    'profile_key'  => $effective_profile_key,
                    'bypass_cache' => true,
                )
            );

            if (!is_array($profile_stats)) {
                $last_error = $this->get_last_error_message();

                if ('' === $last_error) {
                    $last_error = 'Unknown error.';
                }

                $this->log_debug(sprintf('Cron refresh failed for profile "%s": %s', $effective_profile_key, $last_error));
                continue;
            }

            if (!empty($profile_stats['is_demo']) && !empty($profile_stats['fallback_demo'])) {
                $last_error = $this->get_last_error_message();

                if ('' === $last_error) {
                    $last_error = 'Fallback statistics returned.';
                }

                $this->log_debug(sprintf('Cron refresh produced fallback stats for profile "%s": %s', $effective_profile_key, $last_error));
            }
        }

        $this->purge_analytics_if_needed($options);
    }

    private function purge_analytics_if_needed($options) {
        if (!($this->analytics instanceof Discord_Bot_JLG_Analytics)) {
            return;
        }

        $retention = $this->get_analytics_retention_days($options);
        if ($retention <= 0) {
            return;
        }

        $this->analytics->purge_old_entries($retention);
    }

    private function should_log_stats($stats) {
        if (!is_array($stats)) {
            return false;
        }

        if (!empty($stats['is_demo']) || !empty($stats['fallback_demo'])) {
            return false;
        }

        return true;
    }

    private function log_snapshot($profile_key, $server_id, $stats) {
        if (!($this->analytics instanceof Discord_Bot_JLG_Analytics)) {
            return;
        }

        if (!$this->should_log_stats($stats)) {
            return;
        }

        $this->analytics->log_snapshot($profile_key, $server_id, $stats);

        if ($this->alerts instanceof Discord_Bot_JLG_Alerts) {
            $this->alerts->maybe_dispatch_alert($profile_key, $server_id, $stats);
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

        $this->get_cache_gateway()->delete($this->cache_key);
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

            $this->get_cache_gateway()->delete($this->cache_key);
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

    public function get_refresh_lock_snapshot($cache_key = '') {
        $normalized_key = (string) $cache_key;

        if ('' === $normalized_key) {
            $normalized_key = $this->cache_key;
        }

        if ('' === $normalized_key) {
            $normalized_key = $this->base_cache_key;
        }

        $lock_key = $this->build_refresh_lock_key($normalized_key);
        $raw_lock = get_transient($lock_key);

        $snapshot = array(
            'locked'     => false,
            'locked_at'  => 0,
            'expires_at' => 0,
            'lock_key'   => $lock_key,
        );

        if (is_array($raw_lock)) {
            $snapshot['locked_at'] = isset($raw_lock['locked_at']) ? (int) $raw_lock['locked_at'] : 0;
            $snapshot['expires_at'] = isset($raw_lock['expires_at']) ? (int) $raw_lock['expires_at'] : 0;
            $snapshot['locked'] = ($snapshot['expires_at'] > time());
        } elseif (false !== $raw_lock) {
            $snapshot['locked'] = true;
            $snapshot['locked_at'] = time();
            $snapshot['expires_at'] = $snapshot['locked_at'] + 30;
        }

        return $snapshot;
    }

    private function build_refresh_lock_key($cache_key) {
        $normalized = (string) $cache_key;

        if ('' === $normalized) {
            $normalized = $this->base_cache_key;
        }

        return $normalized . self::REFRESH_LOCK_SUFFIX;
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

    private function log_debug($message, array $context = array()) {
        $trimmed_message = trim((string) $message);

        if ('' === $trimmed_message) {
            return;
        }

        if (discord_bot_jlg_logger_debug($this->logger, $trimmed_message, $context)) {
            return;
        }

        $debug_enabled = (defined('WP_DEBUG') && WP_DEBUG)
            || (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG);

        if ($debug_enabled) {
            error_log('[discord-bot-jlg] ' . $trimmed_message);
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

    private function build_event_base_context($options) {
        $context = array();

        if (!is_array($options)) {
            $options = array();
        }

        $profile_key = 'default';

        if (isset($options['__active_profile_key'])) {
            $candidate = discord_bot_jlg_sanitize_profile_key($options['__active_profile_key']);
            if ('' !== $candidate) {
                $profile_key = $candidate;
            }
        } elseif (isset($options['profile_key'])) {
            $candidate = discord_bot_jlg_sanitize_profile_key($options['profile_key']);
            if ('' !== $candidate) {
                $profile_key = $candidate;
            }
        }

        $context['profile_key'] = $profile_key;

        if (isset($options['server_id'])) {
            $server_id = $this->sanitize_server_id($options['server_id']);
            if ('' !== $server_id) {
                $context['server_id'] = $server_id;
            }
        }

        if (isset($options['__request_signature'])) {
            $signature = wp_strip_all_tags((string) $options['__request_signature']);
            if (strlen($signature) > 120) {
                $signature = substr($signature, 0, 117) . '…';
            }

            if ('' !== $signature) {
                $context['request_signature'] = $signature;
            }
        }

        return $context;
    }

    private function record_discord_http_event($channel, $start_time, $response, array $context = array()) {
        if (!($this->event_logger instanceof Discord_Bot_JLG_Event_Logger)) {
            return;
        }

        $duration_ms = $this->calculate_duration_ms($start_time);

        $event_context = array_merge(
            array(
                'channel'     => sanitize_key($channel),
                'duration_ms' => $duration_ms,
            ),
            $context
        );

        if ($response instanceof WP_Error) {
            if (!isset($event_context['outcome'])) {
                $event_context['outcome'] = 'network_error';
            }

            $event_context['error_code'] = $response->get_error_code();
            $event_context['error_message'] = $response->get_error_message();
        } elseif (is_array($response)) {
            $status_code = (int) wp_remote_retrieve_response_code($response);
            $event_context['status_code'] = $status_code;

            if (!isset($event_context['outcome'])) {
                $event_context['outcome'] = ($status_code >= 200 && $status_code < 300)
                    ? 'success'
                    : 'http_error';
            }

            $retry_after = $this->extract_retry_after_seconds($response);
            if ($retry_after > 0) {
                $event_context['retry_after'] = $retry_after;
            }

            $limit = $this->extract_numeric_header($response, 'x-ratelimit-limit');
            if (null !== $limit) {
                $event_context['rate_limit_limit'] = $limit;
            }

            $remaining = $this->extract_numeric_header($response, 'x-ratelimit-remaining');
            if (null !== $remaining) {
                $event_context['rate_limit_remaining'] = $remaining;
            }

            $reset_after = $this->extract_numeric_header($response, 'x-ratelimit-reset-after', true);
            if (null !== $reset_after) {
                $event_context['rate_limit_reset_after'] = $reset_after;
            }

            $bucket = $this->get_first_header_value($response, 'x-ratelimit-bucket');
            if ('' !== $bucket) {
                $event_context['rate_limit_bucket'] = $bucket;
            }

            $global_flag = $this->get_first_header_value($response, 'x-ratelimit-global');
            if ('' !== $global_flag) {
                $event_context['rate_limit_global'] = ('1' === $global_flag || 'true' === strtolower($global_flag));
            }
        } else {
            if (!isset($event_context['outcome'])) {
                $event_context['outcome'] = 'unknown';
            }
        }

        $filtered_context = apply_filters(
            'discord_bot_jlg_discord_http_event_context',
            $event_context,
            $channel,
            $response,
            $context
        );

        if (!is_array($filtered_context)) {
            $filtered_context = $event_context;
        }

        $should_log = apply_filters(
            'discord_bot_jlg_should_log_discord_http_event',
            true,
            $filtered_context,
            $channel,
            $response,
            $context
        );

        if (!$should_log) {
            return;
        }

        $event = $this->event_logger->log('discord_http', $filtered_context);

        do_action(
            'discord_bot_jlg_discord_http_event_logged',
            $event,
            $channel,
            $response,
            $context
        );
    }

    private function log_connector_event($channel, array $context = array()) {
        if (!($this->event_logger instanceof Discord_Bot_JLG_Event_Logger)) {
            return;
        }

        $event_context = array_merge(
            array(
                'channel' => sanitize_key($channel),
            ),
            $context
        );

        $this->event_logger->log('discord_connector', $event_context);
    }

    private function transform_event_to_status_history_entry($event_type, array $event) {
        $timestamp = isset($event['timestamp']) ? (int) $event['timestamp'] : 0;
        if ($timestamp <= 0) {
            $timestamp = (int) current_time('timestamp', true);
        }

        $context = isset($event['context']) && is_array($event['context']) ? $event['context'] : array();

        $channel = '';
        if (isset($context['channel'])) {
            $channel = sanitize_key($context['channel']);
        }

        $outcome = '';
        if (isset($context['outcome'])) {
            $outcome = sanitize_key($context['outcome']);
        }

        $status_code = 0;
        if (isset($context['status_code'])) {
            $status_code = (int) $context['status_code'];
        }

        $label = $this->build_status_history_label($event_type, $channel, $outcome, $status_code);

        $reason = $this->build_status_history_reason($context);

        return array(
            'timestamp' => $timestamp,
            'label'     => $label,
            'reason'    => $reason,
            'type'      => $event_type,
        );
    }

    private function build_status_history_label($event_type, $channel, $outcome, $status_code) {
        $channel_label = $this->get_status_history_channel_label($channel);

        if ('discord_http' === $event_type) {
            $source_label = ('' !== $channel_label)
                ? sprintf(__('API Discord (%s)', 'discord-bot-jlg'), $channel_label)
                : __('API Discord', 'discord-bot-jlg');
        } else {
            $source_label = ('' !== $channel_label)
                ? sprintf(__('Connecteur (%s)', 'discord-bot-jlg'), $channel_label)
                : __('Connecteur Discord', 'discord-bot-jlg');
        }

        $outcome_label = $this->get_status_history_outcome_label($event_type, $outcome, $status_code);

        if ('' === $outcome_label) {
            return $source_label;
        }

        return sprintf('%s – %s', $source_label, $outcome_label);
    }

    private function get_status_history_channel_label($channel) {
        switch ($channel) {
            case 'widget':
                return __('Widget', 'discord-bot-jlg');
            case 'bot':
                return __('Bot', 'discord-bot-jlg');
            case 'cron':
                return __('Cron', 'discord-bot-jlg');
            case 'rest':
                return __('REST', 'discord-bot-jlg');
            case 'queue':
                return __('File', 'discord-bot-jlg');
            default:
                return '';
        }
    }

    private function get_status_history_outcome_label($event_type, $outcome, $status_code) {
        if ('discord_http' === $event_type) {
            switch ($outcome) {
                case 'success':
                    return __('Succès', 'discord-bot-jlg');
                case 'network_error':
                    return __('Erreur réseau', 'discord-bot-jlg');
                case 'incomplete':
                    return __('Données incomplètes', 'discord-bot-jlg');
                case 'rate_limited':
                    return __('Limite de taux atteinte', 'discord-bot-jlg');
                case 'http_error':
                    if ($status_code > 0) {
                        return sprintf(
                            /* translators: %d: HTTP status code. */
                            __('Erreur HTTP %d', 'discord-bot-jlg'),
                            $status_code
                        );
                    }

                    return __('Erreur HTTP', 'discord-bot-jlg');
                default:
                    break;
            }
        } else {
            switch ($outcome) {
                case 'success':
                    return __('Succès', 'discord-bot-jlg');
                case 'skipped':
                    return __('Action ignorée', 'discord-bot-jlg');
                case 'retry':
                    return __('Nouvelle tentative planifiée', 'discord-bot-jlg');
                case 'error':
                case 'failure':
                    return __('Échec', 'discord-bot-jlg');
                default:
                    break;
            }
        }

        return __('Statut inconnu', 'discord-bot-jlg');
    }

    private function build_status_history_reason(array $context) {
        $parts = array();

        if (isset($context['job_type']) && '' !== trim((string) $context['job_type'])) {
            $parts[] = sprintf(
                /* translators: %s: job type. */
                __('Tâche : %s', 'discord-bot-jlg'),
                $this->get_job_type_label($context['job_type'])
            );
        }

        if (isset($context['attempt']) && is_numeric($context['attempt'])) {
            $attempt = (int) $context['attempt'];
            if ($attempt > 0) {
                $parts[] = sprintf(
                    /* translators: %d: attempt number. */
                    __('Tentative n°%d', 'discord-bot-jlg'),
                    $attempt
                );
            }
        }

        if (isset($context['reason']) && '' !== trim((string) $context['reason'])) {
            $parts[] = trim((string) $context['reason']);
        }

        if (isset($context['error_message']) && '' !== trim((string) $context['error_message'])) {
            $parts[] = trim((string) $context['error_message']);
        }

        if (isset($context['diagnostic']) && '' !== trim((string) $context['diagnostic'])) {
            $parts[] = trim((string) $context['diagnostic']);
        }

        if (!empty($context['missing_fields']) && is_array($context['missing_fields'])) {
            $fields = array();
            foreach ($context['missing_fields'] as $field) {
                if (!is_scalar($field)) {
                    continue;
                }

                $field_value = trim((string) $field);
                if ('' === $field_value) {
                    continue;
                }

                $fields[] = $field_value;
            }

            if (!empty($fields)) {
                $parts[] = sprintf(
                    /* translators: %s: comma-separated list of missing fields. */
                    __('Champs manquants : %s', 'discord-bot-jlg'),
                    implode(', ', $fields)
                );
            }
        }

        if (isset($context['error_code']) && '' !== trim((string) $context['error_code'])) {
            $parts[] = sprintf(
                /* translators: %s: error code. */
                __('Code erreur : %s', 'discord-bot-jlg'),
                trim((string) $context['error_code'])
            );
        }

        if (isset($context['retry_after']) && is_numeric($context['retry_after'])) {
            $retry_after = (int) round((float) $context['retry_after']);
            if ($retry_after > 0) {
                $parts[] = sprintf(
                    /* translators: %d: number of seconds before the next retry. */
                    __('Réessayer dans %d s', 'discord-bot-jlg'),
                    $retry_after
                );
            }
        }

        if (isset($context['duration_ms']) && is_numeric($context['duration_ms'])) {
            $duration_ms = (int) round((float) $context['duration_ms']);
            if ($duration_ms > 0) {
                $parts[] = sprintf(
                    /* translators: %d: duration in milliseconds. */
                    __('Durée : %d ms', 'discord-bot-jlg'),
                    $duration_ms
                );
            }
        }

        if (empty($parts)) {
            return '';
        }

        return implode(' – ', $parts);
    }

    private function get_job_type_label($job_type) {
        $job_type = sanitize_key($job_type);

        switch ($job_type) {
            case 'widget_refresh':
                return __('Widget', 'discord-bot-jlg');
            case 'bot_refresh':
                return __('Bot', 'discord-bot-jlg');
            default:
                return $job_type;
        }
    }

    private function calculate_duration_ms($start_time) {
        if (!is_float($start_time) && !is_int($start_time)) {
            return 0;
        }

        $duration = microtime(true) - (float) $start_time;

        if (!is_finite($duration) || $duration < 0) {
            $duration = 0;
        }

        return (int) round($duration * 1000);
    }

    private function get_first_header_value($response, $header_name) {
        if (!is_array($response)) {
            return '';
        }

        $value = wp_remote_retrieve_header($response, $header_name);

        if (is_array($value)) {
            $value = reset($value);
        }

        if (!is_string($value)) {
            return '';
        }

        return trim($value);
    }

    private function extract_numeric_header($response, $header_name, $allow_float = false) {
        $raw_value = $this->get_first_header_value($response, $header_name);

        if ('' === $raw_value) {
            return null;
        }

        if (!is_numeric($raw_value)) {
            return null;
        }

        if ($allow_float) {
            return (float) $raw_value;
        }

        return (int) round((float) $raw_value);
    }

    private function get_stats_from_widget($options) {
        $widget_url = 'https://discord.com/api/guilds/' . $options['server_id'] . '/widget.json';
        $base_context = $this->build_event_base_context($options);
        $start_time = microtime(true);

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
            $this->record_discord_http_event('widget', $start_time, $response, array_merge(
                $base_context,
                array(
                    'outcome' => 'network_error',
                )
            ));
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
            $event_context = array_merge(
                $base_context,
                array(
                    'outcome'      => 'http_error',
                    'status_code'  => $response_code,
                )
            );

            if (!empty($error_detail)) {
                $event_context['error_detail'] = $error_detail;
            }

            $this->record_discord_http_event('widget', $start_time, $response, $event_context);
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
            $this->record_discord_http_event('widget', $start_time, $response, array_merge(
                $base_context,
                array(
                    'outcome'    => 'invalid_json',
                    'diagnostic' => $error_context,
                )
            ));
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
            $this->record_discord_http_event('widget', $start_time, $response, array_merge(
                $base_context,
                array(
                    'outcome'        => 'incomplete',
                    'missing_fields' => $missing_parts,
                )
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

        $event_context = array_merge(
            $base_context,
            array(
                'outcome'               => 'success',
                'online'                => $online,
                'has_total'             => !empty($stats['has_total']),
                'total_is_approximate'  => !empty($stats['total_is_approximate']),
            )
        );

        if (isset($stats['total']) && null !== $stats['total']) {
            $event_context['total'] = (int) $stats['total'];
        }

        if (isset($stats['approximate_presence_count']) && null !== $stats['approximate_presence_count']) {
            $event_context['approximate_presence_count'] = (int) $stats['approximate_presence_count'];
        }

        if (isset($stats['approximate_member_count']) && null !== $stats['approximate_member_count']) {
            $event_context['approximate_member_count'] = (int) $stats['approximate_member_count'];
        }

        if (!empty($presence_breakdown)) {
            $event_context['presence_breakdown'] = $presence_breakdown;
        }

        $this->record_discord_http_event('widget', $start_time, $response, $event_context);

        return $stats;
    }

    private function get_stats_from_bot($options) {
        $bot_token = $this->get_bot_token($options);
        $base_context = $this->build_event_base_context($options);

        if (empty($bot_token)) {
            $this->log_connector_event('bot', array_merge(
                $base_context,
                array(
                    'outcome' => 'skipped',
                    'reason'  => 'missing_token',
                )
            ));
            return false;
        }

        $api_url = 'https://discord.com/api/v10/guilds/' . $options['server_id'] . '?with_counts=true';
        $start_time = microtime(true);

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
            $this->record_discord_http_event('bot', $start_time, $response, array_merge(
                $base_context,
                array(
                    'outcome' => 'network_error',
                )
            ));
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
            $event_context = array_merge(
                $base_context,
                array(
                    'outcome'     => 'http_error',
                    'status_code' => $response_code,
                )
            );

            if (!empty($error_detail)) {
                $event_context['error_detail'] = $error_detail;
            }

            $this->record_discord_http_event('bot', $start_time, $response, $event_context);
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
            $this->record_discord_http_event('bot', $start_time, $response, array_merge(
                $base_context,
                array(
                    'outcome'    => 'invalid_json',
                    'diagnostic' => $error_context,
                )
            ));
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
            $this->record_discord_http_event('bot', $start_time, $response, array_merge(
                $base_context,
                array(
                    'outcome'        => 'incomplete',
                    'missing_fields' => $missing_parts,
                )
            ));
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

        $presence_breakdown = array();

        if (!empty($data['presence_count_by_status']) && is_array($data['presence_count_by_status'])) {
            foreach ($data['presence_count_by_status'] as $status => $value) {
                $status_key = $this->normalize_presence_status_slug($status);

                if ('' === $status_key) {
                    continue;
                }

                if (!isset($presence_breakdown[$status_key])) {
                    $presence_breakdown[$status_key] = 0;
                }

                $presence_breakdown[$status_key] += max(0, (int) $value);
            }
        }

        $stats = array(
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
            'presence_count_by_status'   => $presence_breakdown,
            'premium_subscription_count' => isset($data['premium_subscription_count'])
                ? (int) $data['premium_subscription_count']
                : 0,
        );

        $event_context = array_merge(
            $base_context,
            array(
                'outcome'                   => 'success',
                'online'                    => (int) $data['approximate_presence_count'],
                'total'                     => (int) $data['approximate_member_count'],
                'approximate_presence_count'=> (int) $data['approximate_presence_count'],
                'approximate_member_count'  => (int) $data['approximate_member_count'],
                'has_total'                 => true,
                'total_is_approximate'      => true,
            )
        );

        if (!empty($presence_breakdown)) {
            $event_context['presence_breakdown'] = $presence_breakdown;
        }

        if (isset($stats['premium_subscription_count'])) {
            $event_context['premium_subscription_count'] = (int) $stats['premium_subscription_count'];
        }

        if ('' !== $server_avatar_url) {
            $event_context['server_avatar_url'] = $server_avatar_url;
        }

        $this->record_discord_http_event('bot', $start_time, $response, $event_context);

        return $stats;
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

        $normalized = str_replace(',', '.', $header);

        if (preg_match('/^\s*([0-9]+(?:\.[0-9]+)?)\s*(ms|s)?\s*$/i', $normalized, $matches)) {
            $value = (float) $matches[1];
            $unit  = isset($matches[2]) ? strtolower($matches[2]) : 's';

            if (!is_finite($value) || $value < 0) {
                return 0;
            }

            if ('ms' === $unit) {
                $value = $value / 1000;
            }

            return ($value > 0) ? (int) ceil($value) : 0;
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
        $override_requested = (is_array($options) && array_key_exists('__bot_token_override', $options));

        if (!$override_requested && defined('DISCORD_BOT_JLG_TOKEN') && '' !== DISCORD_BOT_JLG_TOKEN) {
            return DISCORD_BOT_JLG_TOKEN;
        }

        if ($override_requested) {
            $override_value = $options['__bot_token_override'];

            if (is_string($override_value)) {
                return $override_value;
            }
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
        $repository = $this->get_profile_repository();
        $context    = $repository->resolve_context($args, $options, $this->base_cache_key);

        if (!is_array($context)) {
            return array(
                'options'   => is_array($options) ? $options : array(),
                'cache_key' => $this->base_cache_key,
                'signature' => 'default',
            );
        }

        if (!isset($context['options']) || !is_array($context['options'])) {
            $context['options'] = is_array($options) ? $options : array();
        }

        if (!isset($context['cache_key']) || '' === (string) $context['cache_key']) {
            $context['cache_key'] = $this->base_cache_key;
        }

        if (!isset($context['signature']) || '' === (string) $context['signature']) {
            $context['signature'] = 'default';
        }

        return $context;
    }

    private function enrich_stats_with_status_meta($stats, $options, $cache_duration = null) {
        if (!is_array($stats)) {
            return $stats;
        }

        $profile_key = 'default';
        if (isset($options['__active_profile_key'])) {
            $candidate = discord_bot_jlg_sanitize_profile_key($options['__active_profile_key']);
            if ('' !== $candidate) {
                $profile_key = $candidate;
            }
        }

        $server_id = '';
        if (isset($options['server_id'])) {
            $server_id = $this->sanitize_server_id($options['server_id']);
        }

        $history = $this->get_status_history(
            array(
                'limit'       => 5,
                'profile_key' => $profile_key,
                'server_id'   => $server_id,
            )
        );

        $status_meta = array(
            'profileKey'  => $profile_key,
            'serverId'    => $server_id,
            'generatedAt' => (int) current_time('timestamp', true),
            'history'     => $history,
        );

        if (null !== $cache_duration) {
            $status_meta['cacheDuration'] = max(0, (int) $cache_duration);
        }

        $fallback_details = $this->get_last_fallback_details();
        if (is_array($fallback_details) && !empty($fallback_details)) {
            $status_meta['fallbackDetails'] = $fallback_details;
        }

        if (isset($stats['status_meta']) && is_array($stats['status_meta'])) {
            $stats['status_meta'] = array_merge($stats['status_meta'], $status_meta);
        } else {
            $stats['status_meta'] = $status_meta;
        }

        return $stats;
    }

    private function sanitize_server_id($value) {
        return Discord_Bot_JLG_Profile_Repository::sanitize_server_id($value);
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
