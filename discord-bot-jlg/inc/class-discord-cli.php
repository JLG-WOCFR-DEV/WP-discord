<?php

if (false === defined('ABSPATH')) {
    exit;
}

/**
 * Fournit l'intégration WP-CLI pour piloter le cache du plugin.
 */
class Discord_Bot_JLG_CLI {

    /**
     * Service d'accès aux statistiques Discord.
     *
     * @var Discord_Bot_JLG_API
     */
    private $api;

    /**
     * Prépare la commande WP-CLI en recevant une instance de l'API du plugin.
     *
     * @param Discord_Bot_JLG_API $api Service utilisé pour manipuler les statistiques Discord.
     */
    public function __construct(Discord_Bot_JLG_API $api) {
        $this->api = $api;
    }

    /**
     * Force l'actualisation du cache des statistiques.
     *
     * ## EXAMPLES
     *
     *     wp discord-bot refresh-cache
     *
     * @when after_wp_load
     *
     * @param array $args       Liste d'arguments positionnels (non utilisés).
     * @param array $assoc_args Liste d'arguments nommés (non utilisés).
     *
     * @return void
     */
    public function refresh_cache($args, $assoc_args) {
        $stats = $this->api->get_stats(array('bypass_cache' => true));
        $last_error = $this->api->get_last_error_message();

        if (!is_array($stats)) {
            $message = ('' !== $last_error)
                ? $last_error
                : __('Impossible de récupérer des statistiques valides.', 'discord-bot-jlg');

            \WP_CLI::error($message);
            return;
        }

        if ('' !== $last_error) {
            \WP_CLI::error($last_error);
            return;
        }

        $server_name = isset($stats['server_name']) ? (string) $stats['server_name'] : '';
        $online      = isset($stats['online']) ? (int) $stats['online'] : 0;
        $total       = isset($stats['total']) ? $stats['total'] : null;

        if ('' === $server_name) {
            $server_name = __('Serveur Discord', 'discord-bot-jlg');
        }

        $total_display = (null === $total || '' === $total)
            ? __('n/d', 'discord-bot-jlg')
            : (string) $total;

        \WP_CLI::log(
            sprintf(
                /* traducteurs : 1: nom du serveur, 2: membres en ligne, 3: total de membres */
                __('%1$s — En ligne : %2$d — Total : %3$s', 'discord-bot-jlg'),
                $server_name,
                $online,
                $total_display
            )
        );

        \WP_CLI::success(__('Le cache des statistiques a été actualisé.', 'discord-bot-jlg'));
    }

    /**
     * Vide l'ensemble des données mises en cache par le plugin.
     *
     * ## EXAMPLES
     *
     *     wp discord-bot clear-cache
     *
     * @when after_wp_load
     *
     * @param array $args       Liste d'arguments positionnels (non utilisés).
     * @param array $assoc_args Liste d'arguments nommés (non utilisés).
     *
     * @return void
     */
    public function clear_cache($args, $assoc_args) {
        $this->api->clear_all_cached_data();
        \WP_CLI::success(__('Tous les caches Discord Bot JLG et les traces de secours ont été vidés.', 'discord-bot-jlg'));
    }
}
