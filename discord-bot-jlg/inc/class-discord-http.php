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
        $defaults = array(
            'timeout' => 10,
            'headers' => array(
                'User-Agent' => 'WordPress Discord Stats Plugin',
            ),
        );

        $args = wp_parse_args($args, $defaults);
        $args['headers'] = isset($args['headers']) && is_array($args['headers'])
            ? wp_parse_args($args['headers'], $defaults['headers'])
            : $defaults['headers'];

        $context = sanitize_key($context);

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

        return wp_safe_remote_get($url, $args);
    }
}
