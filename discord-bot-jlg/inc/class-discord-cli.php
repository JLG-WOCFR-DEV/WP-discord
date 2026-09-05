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

    /**
     * Enregistre un nouveau token Discord et horodate sa rotation.
     *
     * ## OPTIONS
     *
     * [--profile=<profile>]
     * : Clé du profil (`default` pour la configuration générale).
     *
     * [--token=<token>]
     * : Nouveau token en clair. Requis.
     *
     * ## EXAMPLES
     *
     *     wp discord-bot rotate-token --token=NEWTOKEN
     *     wp discord-bot rotate-token --profile=community --token=NEWTOKEN
     *
     * @when after_wp_load
     *
     * @param array $args
     * @param array $assoc_args
     *
     * @return void
     */
    public function rotate_token($args, $assoc_args) {
        $token = isset($assoc_args['token']) ? trim((string) $assoc_args['token']) : '';

        if ('' === $token) {
            \WP_CLI::error(__('Fournissez --token=<nouveau jeton>.', 'discord-bot-jlg'));
            return;
        }

        $profile = isset($assoc_args['profile']) ? sanitize_key((string) $assoc_args['profile']) : 'default';

        if ('' === $profile) {
            $profile = 'default';
        }

        $encrypted = discord_bot_jlg_encrypt_secret($token);

        if (is_wp_error($encrypted)) {
            \WP_CLI::error($encrypted->get_error_message());
            return;
        }

        $now = function_exists('current_time') ? (int) current_time('timestamp') : time();
        $max_age_days = defined('DAY_IN_SECONDS') && class_exists('Discord_Bot_JLG_Admin')
            ? (int) Discord_Bot_JLG_Admin::SECRET_ROTATION_MAX_AGE_DAYS
            : 90;
        $expires_at = $now + ($max_age_days * (defined('DAY_IN_SECONDS') ? DAY_IN_SECONDS : 86400));

        $store = new Discord_Bot_JLG_Token_Store();
        $store->install();
        $saved = $store->save_token(
            $profile,
            array(
                'token'      => $encrypted,
                'rotated_at' => $now,
                'expires_at' => $expires_at,
                'status'     => 'active',
            )
        );

        if (!$saved) {
            \WP_CLI::error(__('Impossible d’enregistrer le token dans le magasin de secrets.', 'discord-bot-jlg'));
            return;
        }

        $options = $this->api->get_plugin_options(true);

        if (!is_array($options)) {
            $options = array();
        }

        if ('default' === $profile) {
            $options['bot_token'] = $encrypted;
            $options['bot_token_rotated_at'] = $now;
            $options['bot_token_expires_at'] = $expires_at;
            $options['bot_token_status'] = 'active';
        } else {
            if (!isset($options['server_profiles']) || !is_array($options['server_profiles'])) {
                $options['server_profiles'] = array();
            }

            if (!isset($options['server_profiles'][$profile]) || !is_array($options['server_profiles'][$profile])) {
                $options['server_profiles'][$profile] = array();
            }

            $options['server_profiles'][$profile]['bot_token'] = $encrypted;
            $options['server_profiles'][$profile]['bot_token_rotated_at'] = $now;
            $options['server_profiles'][$profile]['bot_token_expires_at'] = $expires_at;
            $options['server_profiles'][$profile]['bot_token_status'] = 'active';
        }

        $option_name = defined('DISCORD_BOT_JLG_OPTION_NAME')
            ? DISCORD_BOT_JLG_OPTION_NAME
            : 'discord_bot_jlg_options';

        update_option($option_name, $options);

        if (method_exists($this->api, 'clear_all_cached_data')) {
            $this->api->clear_all_cached_data();
        }

        \WP_CLI::success(
            sprintf(
                /* translators: %s: profile key. */
                __('Token Discord rotaté pour le profil « %s ».', 'discord-bot-jlg'),
                $profile
            )
        );
    }
}
