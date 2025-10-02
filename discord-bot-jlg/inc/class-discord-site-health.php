<?php

if (!defined('ABSPATH')) {
    exit;
}

class Discord_Bot_JLG_Site_Health {

    /**
     * @var Discord_Bot_JLG_API
     */
    private $api;

    /**
     * Initialise le test de santé du site.
     *
     * @param Discord_Bot_JLG_API $api Instance utilisée pour vérifier l'état du plugin.
     */
    public function __construct(Discord_Bot_JLG_API $api) {
        $this->api = $api;

        add_filter('site_status_tests', array($this, 'register_tests'));
    }

    /**
     * Enregistre le test dans le tableau des vérifications de santé WordPress.
     *
     * @param array $tests
     *
     * @return array
     */
    public function register_tests($tests) {
        if (!is_array($tests)) {
            $tests = array();
        }

        if (!isset($tests['direct']) || !is_array($tests['direct'])) {
            $tests['direct'] = array();
        }

        $tests['direct']['discord_bot_jlg'] = array(
            'label' => __('Statut du bot Discord', 'discord-bot-jlg'),
            'test'  => array($this, 'run_site_health_test'),
        );

        return $tests;
    }

    /**
     * Fournit le diagnostic affiché dans Site Health.
     *
     * @return array
     */
    public function run_site_health_test() {
        $result = array(
            'label'       => __('Statut du bot Discord', 'discord-bot-jlg'),
            'status'      => 'good',
            'badge'       => array(
                'label' => __('Discord Bot JLG', 'discord-bot-jlg'),
                'color' => 'blue',
            ),
            'description' => '',
            'test'        => 'discord_bot_jlg_site_health',
        );

        $options = $this->api->get_plugin_options();
        if (!is_array($options)) {
            $options = array();
        }

        $server_id = isset($options['server_id']) ? trim((string) $options['server_id']) : '';
        $demo_mode = !empty($options['demo_mode']);

        if ('' === $server_id && false === $demo_mode) {
            $result['status'] = 'critical';
            $result['description'] = '<p>' . esc_html__("Aucun identifiant de serveur Discord n'est configuré. Veuillez renseigner vos identifiants dans les réglages du plugin.", 'discord-bot-jlg') . '</p>';

            return $result;
        }

        if ($demo_mode) {
            $result['status'] = 'recommended';
            $result['description'] = '<p>' . esc_html__('Le plugin fonctionne actuellement en mode démonstration ; les données affichées ne proviennent pas de votre serveur Discord.', 'discord-bot-jlg') . '</p>';

            return $result;
        }

        $fallback_details = $this->api->get_last_fallback_details();
        if (is_array($fallback_details) && !empty($fallback_details)) {
            $timestamp = isset($fallback_details['timestamp']) ? (int) $fallback_details['timestamp'] : 0;
            if ($timestamp <= 0) {
                $timestamp = current_time('timestamp', true);
            }

            $date_format = get_option('date_format');
            if (!is_string($date_format) || '' === trim($date_format)) {
                $date_format = 'Y-m-d';
            }

            $time_format = get_option('time_format');
            if (!is_string($time_format) || '' === trim($time_format)) {
                $time_format = 'H:i';
            }

            $formatted_time = discord_bot_jlg_format_datetime($date_format . ' ' . $time_format, $timestamp);

            $message_parts = array(
                sprintf(
                    esc_html__('Statistiques de secours utilisées depuis le %s.', 'discord-bot-jlg'),
                    esc_html($formatted_time)
                ),
            );

            $reason = isset($fallback_details['reason']) ? trim((string) $fallback_details['reason']) : '';
            if ('' !== $reason) {
                $message_parts[] = sprintf(
                    esc_html__('Dernière erreur signalée : %s.', 'discord-bot-jlg'),
                    esc_html($reason)
                );
            }

            $next_retry = isset($fallback_details['next_retry']) ? (int) $fallback_details['next_retry'] : 0;
            if ($next_retry > 0) {
                $retry_time = discord_bot_jlg_format_datetime($date_format . ' ' . $time_format, $next_retry);
                $message_parts[] = sprintf(
                    esc_html__('Nouvelle tentative planifiée vers %s.', 'discord-bot-jlg'),
                    esc_html($retry_time)
                );
            } else {
                $message_parts[] = esc_html__('Une nouvelle tentative sera effectuée automatiquement dès que possible.', 'discord-bot-jlg');
            }

            $result['status'] = 'recommended';
            $result['description'] = '<p>' . implode(' ', $message_parts) . '</p>';

            return $result;
        }

        $last_error = trim((string) $this->api->get_last_error_message());
        if ('' !== $last_error) {
            $result['status'] = 'recommended';
            $result['description'] = sprintf(
                '<p>%s</p>',
                sprintf(
                    esc_html__('Dernière erreur rencontrée : %s.', 'discord-bot-jlg'),
                    esc_html($last_error)
                )
            );

            return $result;
        }

        $result['description'] = '<p>' . esc_html__('La connexion au serveur Discord fonctionne normalement.', 'discord-bot-jlg') . '</p>';

        return $result;
    }
}
