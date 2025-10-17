<?php
if (!defined('ABSPATH')) {
    exit;
}

class Discord_Bot_JLG_Stats_Service {
    /**
     * @var Discord_Bot_JLG_Cache_Gateway
     */
    private $cache_gateway;

    /**
     * @var Discord_Bot_JLG_Stats_Fetcher
     */
    private $stats_fetcher;

    /**
     * @var Psr\Log\LoggerInterface|null
     */
    private $logger;

    /**
     * @var Discord_Bot_JLG_Event_Logger|null
     */
    private $event_logger;

    public function __construct(
        Discord_Bot_JLG_Cache_Gateway $cache_gateway,
        Discord_Bot_JLG_Stats_Fetcher $stats_fetcher,
        $logger = null,
        $event_logger = null
    ) {
        $this->cache_gateway = $cache_gateway;
        $this->stats_fetcher = $stats_fetcher;
        $this->logger = discord_bot_jlg_is_psr_logger($logger) ? $logger : null;
        $this->event_logger = ($event_logger instanceof Discord_Bot_JLG_Event_Logger)
            ? $event_logger
            : null;
    }

    /**
     * Exécute le pipeline de récupération des statistiques.
     *
     * @param array $payload
     *
     * @return array{
     *     stats:mixed,
     *     error:string,
     *     retry_after:int,
     *     fallback_used:bool,
     *     used_cache:bool,
     *     lock_key:string,
     *     lock_acquired:bool,
     *     lock_released:bool
     * }
     */
    public function execute(array $payload) {
        $defaults = array(
            'cache_key'            => '',
            'options'              => array(),
            'context'              => array(),
            'args'                 => array(),
            'bypass_cache'         => false,
            'fallback_provider'    => null,
            'persist_success'      => null,
            'persist_fallback'     => null,
            'validate_stats'       => null,
            'store_retry_after'    => null,
            'register_cache_key'   => null,
            'read_retry_after'     => null,
            'last_retry_after'     => 0,
        );

        $config = array_merge($defaults, $payload);

        $result = array(
            'stats'          => array(),
            'error'          => '',
            'retry_after'    => max(0, (int) $config['last_retry_after']),
            'fallback_used'  => false,
            'used_cache'     => false,
            'lock_key'       => '',
            'lock_acquired'  => false,
            'lock_released'  => false,
        );

        $lock_key = '';
        $lock_acquired = false;

        try {
            $result = $this->run_pipeline($config, $result, $lock_key, $lock_acquired);
        } finally {
            if (true === $lock_acquired && '' !== $lock_key) {
                $this->release_lock($lock_key);
                $result['lock_released'] = true;
            }
        }

        return $result;
    }

    private function run_pipeline(array $config, array $result, &$lock_key, &$lock_acquired) {
        $cache_key = (string) $config['cache_key'];
        $options   = is_array($config['options']) ? $config['options'] : array();
        $context   = is_array($config['context']) ? $config['context'] : array();

        $profile_key = $this->resolve_profile_key($options, $context);
        $server_id   = $this->resolve_server_id($options, $context);

        if (false === (bool) $config['bypass_cache']) {
            $cached_stats = $this->cache_gateway->get($cache_key);
            if (false !== $cached_stats) {
                $result['stats'] = $this->normalize_stats_payload($cached_stats);
                $result['used_cache'] = true;
                $this->log_stage('cache_hit', $profile_key, $server_id, array(
                    'cache_key' => $cache_key,
                ));
                return $result;
            }
        }

        if (empty($options['server_id'])) {
            $message = __('Aucun identifiant de serveur Discord n’est configuré.', 'discord-bot-jlg');
            $result['error'] = $message;
            $fallback = $this->fallback($config, $message, $profile_key, $server_id);
            $result['stats'] = $this->normalize_stats_payload($fallback['stats']);
            $result['retry_after'] = $fallback['retry_after'];
            $result['fallback_used'] = true;
            return $result;
        }

        $lock_key = '';
        $lock_acquired = false;
        $lock_ttl = (int) apply_filters(
            'discord_bot_jlg_stats_runner_lock_ttl',
            45,
            $profile_key,
            $server_id,
            $cache_key,
            $config
        );

        if ($lock_ttl > 0 && '' !== $cache_key) {
            $lock_key = $cache_key . Discord_Bot_JLG_API::REFRESH_LOCK_SUFFIX;
            $lock_payload = array(
                'locked_at'  => time(),
                'expires_at' => time() + $lock_ttl,
                'profile'    => $profile_key,
            );

            $lock_acquired = $this->acquire_lock($lock_key, $lock_payload, $lock_ttl, $config['register_cache_key']);

            if (false === $lock_acquired) {
                $message = __('Une actualisation est déjà en cours pour ce profil.', 'discord-bot-jlg');
                $result['error'] = $message;
                $result['lock_key'] = $lock_key;
                $retry_after = max($lock_ttl, $this->read_retry_after($config));
                $fallback = $this->fallback($config, $message, $profile_key, $server_id, $retry_after);
                $result['stats'] = $this->normalize_stats_payload($fallback['stats']);
                $result['retry_after'] = $fallback['retry_after'];
                $result['fallback_used'] = true;
                $this->log_stage('lock_conflict', $profile_key, $server_id, array(
                    'lock_key'   => $lock_key,
                    'expires_at' => $fallback['retry_after'] > 0 ? time() + $fallback['retry_after'] : time() + $lock_ttl,
                ));
                return $result;
            }

            $result['lock_key'] = $lock_key;
            $result['lock_acquired'] = true;
        }

        try {
            $fetch_result = $this->stats_fetcher->fetch($options);

            if (isset($fetch_result['options']) && is_array($fetch_result['options'])) {
                $options = $fetch_result['options'];
            }

            $stats = isset($fetch_result['stats']) ? $fetch_result['stats'] : null;
            $has_usable_stats = false;

            if (isset($fetch_result['has_usable_stats'])) {
                $has_usable_stats = (bool) $fetch_result['has_usable_stats'];
            } elseif (is_callable($config['validate_stats'])) {
                $has_usable_stats = (bool) call_user_func($config['validate_stats'], $stats);
            } else {
                $has_usable_stats = is_array($stats);
            }

            $retry_after = $this->read_retry_after($config);

            if (false === $has_usable_stats) {
                $message = __('Impossible d’obtenir des statistiques exploitables depuis Discord.', 'discord-bot-jlg');
                $result['error'] = $message;
                $fallback = $this->fallback($config, $message, $profile_key, $server_id, $retry_after);
                $result['stats'] = $this->normalize_stats_payload($fallback['stats']);
                $result['retry_after'] = $fallback['retry_after'];
                $result['fallback_used'] = true;
                return $result;
            }

            if (is_callable($config['persist_success'])) {
                call_user_func($config['persist_success'], $stats, $options, $context, $config['args']);
            }

            $result['stats'] = $this->normalize_stats_payload($stats);
            $result['retry_after'] = max(0, (int) $retry_after);
            $this->log_stage('success', $profile_key, $server_id, array(
                'cache_key'  => $cache_key,
                'bot_called' => !empty($fetch_result['bot_called']),
            ));

            return $result;
        } catch (Exception $exception) {
            $message = $exception->getMessage();
            if ('' === trim($message)) {
                $message = __('Erreur inconnue lors de la récupération des statistiques Discord.', 'discord-bot-jlg');
            }

            $result['error'] = $message;
            $fallback = $this->fallback($config, $message, $profile_key, $server_id, $this->read_retry_after($config));
            $result['stats'] = $this->normalize_stats_payload($fallback['stats']);
            $result['retry_after'] = $fallback['retry_after'];
            $result['fallback_used'] = true;
            $this->log_stage('exception', $profile_key, $server_id, array(
                'exception' => get_class($exception),
            ));
            return $result;
        }
    }

    private function resolve_profile_key($options, $context) {
        if (isset($options['__active_profile_key'])) {
            return discord_bot_jlg_sanitize_profile_key($options['__active_profile_key']);
        }

        if (isset($context['profile_key'])) {
            return discord_bot_jlg_sanitize_profile_key($context['profile_key']);
        }

        if (isset($context['options']['__active_profile_key'])) {
            return discord_bot_jlg_sanitize_profile_key($context['options']['__active_profile_key']);
        }

        return 'default';
    }

    private function resolve_server_id($options, $context) {
        if (isset($options['server_id'])) {
            return (string) $options['server_id'];
        }

        if (isset($context['options']['server_id'])) {
            return (string) $context['options']['server_id'];
        }

        if (isset($context['server_id'])) {
            return (string) $context['server_id'];
        }

        return '';
    }

    private function log_stage($stage, $profile_key, $server_id, array $extra = array()) {
        $context = array_merge(array(
            'channel'     => 'stats_pipeline',
            'stage'       => sanitize_key($stage),
            'profile_key' => discord_bot_jlg_sanitize_profile_key($profile_key),
            'server_id'   => (string) $server_id,
        ), $extra);

        discord_bot_jlg_logger_debug($this->logger, sprintf('[%s] Stats pipeline stage: %s', $profile_key, $stage), $context);

        if ($this->event_logger instanceof Discord_Bot_JLG_Event_Logger) {
            $this->event_logger->log('discord_stats_pipeline', $context);
        }
    }

    private function fallback(array $config, $message, $profile_key, $server_id, $retry_after_override = null) {
        $stats = null;
        if (is_callable($config['fallback_provider'])) {
            $stats = call_user_func($config['fallback_provider'], true);
        }

        if (is_callable($config['persist_fallback'])) {
            $stats = call_user_func($config['persist_fallback'], $stats, $config['options'], $message);
        }

        $retry_after = $retry_after_override;
        if (null === $retry_after) {
            $retry_after = max(0, $this->read_retry_after($config));
        }

        if ($retry_after > 0 && is_callable($config['store_retry_after'])) {
            call_user_func($config['store_retry_after'], $retry_after);
        }

        $this->log_stage('fallback', $profile_key, $server_id, array(
            'retry_after' => (int) $retry_after,
            'message'     => $message,
        ));

        return array(
            'stats'       => $this->normalize_stats_payload($stats),
            'retry_after' => max(0, (int) $retry_after),
        );
    }

    private function normalize_stats_payload($stats) {
        return is_array($stats) ? $stats : array();
    }

    private function acquire_lock($lock_key, array $payload, $ttl, $register_callback = null) {
        $this->call_register_cache_key($register_callback);
        $existing = get_transient($lock_key);
        if (is_array($existing) && isset($existing['expires_at'])) {
            $expires_at = (int) $existing['expires_at'];
            if ($expires_at > time()) {
                return false;
            }
        } elseif (false !== $existing) {
            // Verrou non structuré : on évite les chevauchements en respectant le TTL.
            return false;
        }

        set_transient($lock_key, $payload, max(1, (int) $ttl));
        return true;
    }

    private function release_lock($lock_key) {
        if ('' === $lock_key) {
            return;
        }

        delete_transient($lock_key);
    }

    private function call_register_cache_key($callback) {
        if (is_callable($callback)) {
            call_user_func($callback);
        }
    }

    private function read_retry_after(array $config) {
        if (is_callable($config['read_retry_after'])) {
            $value = call_user_func($config['read_retry_after']);
            return max(0, (int) $value);
        }

        return max(0, (int) $config['last_retry_after']);
    }
}
