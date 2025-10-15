<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('discord_bot_jlg_is_psr_logger')) {
    require_once __DIR__ . '/helpers.php';
}

/**
 * Orchestration des appels HTTP widget/bot avec instrumentation optionnelle.
 */
class Discord_Bot_JLG_Http_Connector {

    /**
     * @var callable
     */
    private $widget_fetcher;

    /**
     * @var callable
     */
    private $bot_fetcher;

    /**
     * @var mixed
     */
    private $logger;

    /**
     * @param callable $widget_fetcher Callback chargé de récupérer les statistiques du widget.
     * @param callable $bot_fetcher    Callback chargé de récupérer les statistiques du bot.
     * @param mixed    $logger         Logger PSR-3 optionnel.
     */
    public function __construct(callable $widget_fetcher, callable $bot_fetcher, $logger = null) {
        $this->widget_fetcher = $widget_fetcher;
        $this->bot_fetcher    = $bot_fetcher;
        $this->logger         = null;

        if (null !== $logger) {
            $this->set_logger($logger);
        }
    }

    /**
     * Définit le logger utilisé pour l'instrumentation.
     *
     * @param mixed $logger Logger potentiel.
     *
     * @return void
     */
    public function set_logger($logger) {
        if (discord_bot_jlg_is_psr_logger($logger)) {
            $this->logger = $logger;
        } else {
            $this->logger = null;
        }
    }

    /**
     * Renvoie le logger PSR-3 actif le cas échéant.
     *
     * @return mixed
     */
    public function get_logger() {
        if (discord_bot_jlg_is_psr_logger($this->logger)) {
            return $this->logger;
        }

        return null;
    }

    /**
     * Récupère les statistiques du widget Discord.
     *
     * @param array $options Options de connexion.
     *
     * @return mixed
     */
    public function fetch_widget(array $options) {
        $this->log_debug('Fetching Discord widget statistics.', $this->build_context('widget', $options));

        return call_user_func($this->widget_fetcher, $options);
    }

    /**
     * Récupère les statistiques issues du bot Discord.
     *
     * @param array $options Options de connexion.
     * @param bool  $should_fetch Indique si l'appel doit être effectué.
     *
     * @return mixed
     */
    public function fetch_bot(array $options, $should_fetch = true) {
        if (!$should_fetch) {
            $this->log_debug('Skipping Discord bot fetch (precondition not met).', $this->build_context('bot', $options));

            return null;
        }

        $this->log_debug('Fetching Discord bot statistics.', $this->build_context('bot', $options));

        return call_user_func($this->bot_fetcher, $options);
    }

    /**
     * Rassemble les statistiques en fonction de la configuration fournie.
     *
     * @param array $options Options de connexion.
     * @param array $config  Configuration (`fetch_widget`, `fetch_bot`).
     *
     * @return array
     */
    public function collect(array $options, array $config = array()) {
        $config = wp_parse_args(
            $config,
            array(
                'fetch_widget' => true,
                'fetch_bot'    => true,
            )
        );

        $widget_stats = null;
        if (!empty($config['fetch_widget'])) {
            $widget_stats = $this->fetch_widget($options);
        }

        $bot_stats = null;
        if (!empty($config['fetch_bot'])) {
            $bot_stats = $this->fetch_bot($options, true);
        }

        return array(
            'widget' => $widget_stats,
            'bot'    => $bot_stats,
        );
    }

    private function log_debug($message, array $context = array()) {
        discord_bot_jlg_logger_debug($this->logger, $message, $context);
    }

    private function build_context($channel, array $options) {
        $channel     = sanitize_key($channel);
        $profile_key = 'default';

        if (isset($options['__active_profile_key'])) {
            $candidate = discord_bot_jlg_sanitize_profile_key($options['__active_profile_key']);
            if ('' !== $candidate) {
                $profile_key = $candidate;
            }
        }

        $server_id = '';
        if (isset($options['server_id'])) {
            $server_id = Discord_Bot_JLG_Profile_Repository::sanitize_server_id($options['server_id']);
        }

        return array(
            'channel'    => $channel,
            'profileKey' => $profile_key,
            'serverId'   => $server_id,
        );
    }
}
