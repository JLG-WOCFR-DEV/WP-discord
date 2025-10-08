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

        /**
         * Permet de court-circuiter un appel HTTP avant son exécution.
         *
         * Retournez un tableau de réponse (`wp_safe_remote_get`) ou un `WP_Error` pour interrompre
         * l'appel réseau et fournir une réponse personnalisée (ex. cache applicatif, circuit breaker).
         *
         * @since 1.2.0
         *
         * @param array|WP_Error|null $preempt    Valeur de préemption. Null pour poursuivre l'appel standard.
         * @param string              $url        URL ciblée.
         * @param array               $args       Arguments finaux transmis à `wp_safe_remote_get()`.
         * @param string              $context    Contexte fonctionnel (`widget`, `bot`, ...).
         * @param string              $request_id Identifiant unique de la requête.
         */
        $preempt = apply_filters(
            'discord_bot_jlg_pre_http_request',
            null,
            $url,
            $args,
            $context,
            $request_id
        );

        /**
         * Se déclenche avant l'exécution d'un appel HTTP Discord.
         *
         * Peut être utilisé pour initialiser un traceur distribué, enregistrer des métriques ou enrichir
         * un journal externe.
         *
         * @since 1.2.0
         *
         * @param string $url        URL ciblée.
         * @param array  $args       Arguments transmis à `wp_safe_remote_get()`.
         * @param string $context    Contexte fonctionnel (`widget`, `bot`, ...).
         * @param string $request_id Identifiant unique de la requête.
         */
        do_action('discord_bot_jlg_before_http_request', $url, $args, $context, $request_id);

        if (null !== $preempt) {
            $response = $preempt;
            $duration_ms = 0;
        } else {
            $start_time = microtime(true);
            $response = wp_safe_remote_get($url, $args);
            $duration_ms = $this->calculate_duration_ms($start_time);
        }

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
