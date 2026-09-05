<?php

if (false === defined('ABSPATH')) {
    exit;
}

/**
 * Fournit un client HTTP centralisé pour personnaliser les appels à l'API Discord.
 */
class Discord_Bot_JLG_Http_Client {

    /**
     * Exécute une requête GET en appliquant les filtres d'extension nécessaires.
     *
     * @param string $url     URL cible.
     * @param array  $args    Arguments transmis à wp_safe_remote_get.
     * @param string $context Contexte fonctionnel (ex. widget, bot).
     *
     * @return array|WP_Error
     */
    public function get($url, array $args = array(), $context = '') {
        $context = sanitize_key($context);
        $default_limit = 1048576;

        /**
         * Filtre la taille maximale (en octets) autorisée pour la réponse HTTP.
         *
         * @since 1.1.0
         *
         * @param int    $max_bytes Taille maximale de la réponse en octets.
         * @param string $url       URL cible.
         * @param string $context   Contexte fonctionnel.
         */
        $max_response_bytes = (int) apply_filters(
            'discord_bot_jlg_http_max_bytes',
            $default_limit,
            $url,
            $context
        );

        if ($max_response_bytes <= 0) {
            $max_response_bytes = $default_limit;
        }

        $defaults = array(
            'timeout' => 10,
            'limit_response_size' => $max_response_bytes,
            'headers' => array(
                'User-Agent' => 'WordPress Discord Stats Plugin',
            ),
        );

        $args = wp_parse_args($args, $defaults);
        $args['headers'] = isset($args['headers']) && is_array($args['headers'])
            ? wp_parse_args($args['headers'], $defaults['headers'])
            : $defaults['headers'];

        /**
         * Filtre les arguments transmis à wp_safe_remote_get pour un appel Discord.
         *
         * @since 1.0.1
         *
         * @param array  $args    Arguments de la requête.
         * @param string $url     URL cible.
         * @param string $context Contexte (widget, bot, ...).
         */
        $args = apply_filters('discord_bot_jlg_http_request_args', $args, $url, $context);

        if (!empty($context)) {
            /**
             * Filtre les arguments transmis à wp_safe_remote_get pour un contexte dédié.
             *
             * Les hooks spécifiques `discord_bot_jlg_widget_request_args` et
             * `discord_bot_jlg_bot_request_args` permettent d'ajuster les paramètres au cas par cas.
             *
             * @since 1.0.1
             *
             * @param array  $args Arguments de la requête.
             * @param string $url  URL cible.
             */
            $args = apply_filters('discord_bot_jlg_' . $context . '_request_args', $args, $url);
        }

        $request_id = $this->generate_request_id($context);
        $max_retries = (int) apply_filters('discord_bot_jlg_http_max_retries', 1, $url, $context, $args);

        if ($max_retries < 0) {
            $max_retries = 0;
        }

        $attempt = 0;
        $response = null;
        $duration_ms = 0;

        do {
            $attempt_request_id = $attempt > 0
                ? $request_id . '_retry' . $attempt
                : $request_id;

            $result = $this->execute_request($url, $args, $context, $attempt_request_id);
            $response = $result['response'];
            $duration_ms += $result['duration_ms'];

            if (!$this->should_retry($response, $attempt, $max_retries)) {
                break;
            }

            $this->wait_before_retry($response, $attempt, $url, $context);
            $attempt++;
        } while ($attempt <= $max_retries);

        /**
         * Filtre la réponse HTTP renvoyée par l'appel Discord.
         *
         * @since 1.2.0
         *
         * @param array|WP_Error $response   Réponse retournée par `wp_safe_remote_get()` (ou par un filtre préemptif).
         * @param string         $url        URL ciblée.
         * @param array          $args       Arguments transmis à `wp_safe_remote_get()`.
         * @param string         $context    Contexte fonctionnel (`widget`, `bot`, ...).
         * @param string         $request_id Identifiant unique de la requête.
         * @param int            $duration_ms Durée d'exécution estimée en millisecondes.
         */
        $response = apply_filters(
            'discord_bot_jlg_http_response',
            $response,
            $url,
            $args,
            $context,
            $request_id,
            $duration_ms
        );

        /**
         * Se déclenche après l'exécution d'un appel HTTP Discord.
         *
         * @since 1.2.0
         *
         * @param array|WP_Error $response    Réponse finale transmise à l'appelant.
         * @param string         $url         URL ciblée.
         * @param array          $args        Arguments transmis à `wp_safe_remote_get()`.
         * @param string         $context     Contexte fonctionnel (`widget`, `bot`, ...).
         * @param string         $request_id  Identifiant unique de la requête.
         * @param int            $duration_ms Durée d'exécution estimée en millisecondes.
         */
        do_action(
            'discord_bot_jlg_after_http_request',
            $response,
            $url,
            $args,
            $context,
            $request_id,
            $duration_ms
        );

        return $response;
    }

    /**
     * Executes a single HTTP attempt, including preemption hooks.
     *
     * @param string $url
     * @param array  $args
     * @param string $context
     * @param string $request_id
     *
     * @return array{response:array|WP_Error,duration_ms:int}
     */
    private function execute_request($url, array $args, $context, $request_id) {
        $preempt = apply_filters(
            'discord_bot_jlg_pre_http_request',
            null,
            $url,
            $args,
            $context,
            $request_id
        );

        do_action('discord_bot_jlg_before_http_request', $url, $args, $context, $request_id);

        if (null !== $preempt) {
            return array(
                'response'    => $preempt,
                'duration_ms' => 0,
            );
        }

        $start_time = microtime(true);
        $response = wp_safe_remote_get($url, $args);

        return array(
            'response'    => $response,
            'duration_ms' => $this->calculate_duration_ms($start_time),
        );
    }

    /**
     * @param array|WP_Error $response
     * @param int            $attempt
     * @param int            $max_retries
     */
    private function should_retry($response, $attempt, $max_retries) {
        if ($attempt >= $max_retries) {
            return false;
        }

        $status = 0;
        $retry_after = $this->extract_retry_after_seconds($response);

        if (is_wp_error($response)) {
            $code = $response->get_error_code();
            $retryable = in_array($code, array('http_request_failed', 'http_failure', 'http_request_timeout'), true);

            return (bool) apply_filters(
                'discord_bot_jlg_http_should_retry',
                $retryable,
                $response,
                $attempt,
                $retry_after
            );
        }

        if (is_array($response) && function_exists('wp_remote_retrieve_response_code')) {
            $status = (int) wp_remote_retrieve_response_code($response);
        } elseif (is_array($response) && isset($response['response']['code'])) {
            $status = (int) $response['response']['code'];
        }

        $retryable = (429 === $status || $status >= 500);

        if ($retryable && $retry_after > 0) {
            $max_immediate = (int) apply_filters('discord_bot_jlg_http_max_immediate_retry_seconds', 2, $status, $attempt);

            if ($max_immediate < 0) {
                $max_immediate = 0;
            }

            if ($retry_after > $max_immediate) {
                $retryable = false;
            }
        }

        return (bool) apply_filters(
            'discord_bot_jlg_http_should_retry',
            $retryable,
            $response,
            $attempt,
            $retry_after
        );
    }

    /**
     * @param array|WP_Error $response
     */
    private function wait_before_retry($response, $attempt, $url, $context) {
        $delay = $this->extract_retry_after_seconds($response);

        if ($delay <= 0) {
            $delay = (int) min(2, pow(2, max(0, $attempt)));
        }

        $sleep = (int) apply_filters(
            'discord_bot_jlg_http_retry_sleep_seconds',
            $delay,
            $attempt,
            $url,
            $context
        );

        if ($sleep > 0) {
            sleep($sleep);
        }
    }

    /**
     * @param array|WP_Error $response
     *
     * @return int
     */
    private function extract_retry_after_seconds($response) {
        if (is_wp_error($response) || !is_array($response)) {
            return 0;
        }

        $header = '';

        if (function_exists('wp_remote_retrieve_header')) {
            $header = (string) wp_remote_retrieve_header($response, 'Retry-After');
        } elseif (isset($response['headers']['Retry-After'])) {
            $header = (string) $response['headers']['Retry-After'];
        } elseif (isset($response['headers']['retry-after'])) {
            $header = (string) $response['headers']['retry-after'];
        }

        $header = trim($header);

        if ('' === $header) {
            return 0;
        }

        if (is_numeric($header)) {
            $seconds = (int) ceil((float) $header);

            return max(0, $seconds);
        }

        $timestamp = strtotime($header);

        if (false === $timestamp) {
            return 0;
        }

        return max(0, $timestamp - time());
    }

    private function generate_request_id($context) {
        $context = sanitize_key($context);
        $prefix = 'discord_http';

        if ('' !== $context) {
            $prefix .= '_' . $context;
        }

        if (function_exists('wp_unique_id')) {
            return wp_unique_id($prefix . '_');
        }

        return uniqid($prefix . '_', true);
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
}
