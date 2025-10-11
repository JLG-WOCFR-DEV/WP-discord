<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Orchestre la collecte des statistiques Discord (widget + bot).
 */
class Discord_Bot_JLG_Stats_Fetcher {

    /**
     * @var Discord_Bot_JLG_Http_Connector
     */
    private $http_connector;

    /**
     * @var callable
     */
    private $bot_token_provider;

    /**
     * @var callable
     */
    private $needs_completion_callback;

    /**
     * @var callable
     */
    private $merge_callback;

    /**
     * @var callable
     */
    private $normalize_callback;

    /**
     * @var callable
     */
    private $has_usable_callback;

    /**
     * @param Discord_Bot_JLG_Http_Connector $http_connector            Connecteur HTTP orchestrant les appels widget/bot.
     * @param callable                        $bot_token_provider        Fonction obtenant le jeton de bot à utiliser.
     * @param callable                        $needs_completion_callback Callback indiquant si les données widget sont complètes.
     * @param callable                        $merge_callback            Fonction de fusion des statistiques widget/bot.
     * @param callable                        $normalize_callback        Fonction de normalisation des statistiques consolidées.
     * @param callable                        $has_usable_callback       Fonction déterminant si les statistiques sont exploitables.
     */
    public function __construct(
        Discord_Bot_JLG_Http_Connector $http_connector,
        callable $bot_token_provider,
        callable $needs_completion_callback,
        callable $merge_callback,
        callable $normalize_callback,
        callable $has_usable_callback
    ) {
        $this->http_connector            = $http_connector;
        $this->bot_token_provider        = $bot_token_provider;
        $this->needs_completion_callback = $needs_completion_callback;
        $this->merge_callback            = $merge_callback;
        $this->normalize_callback        = $normalize_callback;
        $this->has_usable_callback       = $has_usable_callback;
    }

    /**
     * Récupère et fusionne les statistiques Discord selon les options fournies.
     *
     * @param array $options Options de connexion (profil, identifiant serveur, etc.).
     *
     * @return array{
     *     stats:mixed,
     *     widget_stats:mixed,
     *     bot_stats:mixed,
     *     widget_incomplete:bool,
     *     bot_called:bool,
     *     bot_token:string,
     *     options:array,
     *     has_usable_stats:bool
     * }
     */
    public function fetch(array $options) {
        $options = is_array($options) ? $options : array();

        $widget_stats = $this->http_connector->fetch_widget($options);
        $widget_incomplete = (bool) call_user_func($this->needs_completion_callback, $widget_stats);

        $bot_token = (string) call_user_func($this->bot_token_provider, $options);
        $options_with_token = $options;
        $options_with_token['__bot_token_override'] = $bot_token;

        $should_fetch_bot = ('' !== $bot_token) && ($widget_incomplete || empty($widget_stats));
        $bot_stats = null;

        if ($should_fetch_bot) {
            $bot_stats = $this->http_connector->fetch_bot($options_with_token, true);
        } else {
            $this->http_connector->fetch_bot($options_with_token, false);
        }

        $stats = call_user_func($this->merge_callback, $widget_stats, $bot_stats, $widget_incomplete);

        if (is_array($stats)) {
            $stats = call_user_func($this->normalize_callback, $stats);
        }

        $has_usable_stats = (bool) call_user_func($this->has_usable_callback, $stats);

        return array(
            'stats'             => $stats,
            'widget_stats'      => $widget_stats,
            'bot_stats'         => $bot_stats,
            'widget_incomplete' => $widget_incomplete,
            'bot_called'        => $should_fetch_bot,
            'bot_token'         => $bot_token,
            'options'           => $options_with_token,
            'has_usable_stats'  => $has_usable_stats,
        );
    }
}
