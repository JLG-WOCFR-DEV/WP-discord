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

        $tests['direct']['discord_bot_jlg_token_rotation'] = array(
            'label' => __('Rotation des jetons Discord', 'discord-bot-jlg'),
            'test'  => array($this, 'run_token_rotation_test'),
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

    /**
     * Checks whether Discord bot tokens are within the rotation window.
     *
     * @return array
     */
    public function run_token_rotation_test() {
        $result = array(
            'label'       => __('Rotation des jetons Discord', 'discord-bot-jlg'),
            'status'      => 'good',
            'badge'       => array(
                'label' => __('Discord Bot JLG', 'discord-bot-jlg'),
                'color' => 'blue',
            ),
            'description' => '<p>' . esc_html__('Aucun jeton Discord à surveiller, ou les rotations sont à jour.', 'discord-bot-jlg') . '</p>',
            'test'        => 'discord_bot_jlg_token_rotation',
        );

        $options = $this->api->get_plugin_options();

        if (!is_array($options)) {
            $options = array();
        }

        $now = function_exists('current_time') ? (int) current_time('timestamp') : time();
        $issues = array();

        $this->collect_token_rotation_issue(
            $issues,
            __('configuration principale', 'discord-bot-jlg'),
            isset($options['bot_token']) ? (string) $options['bot_token'] : '',
            isset($options['bot_token_status']) ? (string) $options['bot_token_status'] : '',
            isset($options['bot_token_rotated_at']) ? (int) $options['bot_token_rotated_at'] : 0,
            isset($options['bot_token_expires_at']) ? (int) $options['bot_token_expires_at'] : 0,
            $now
        );

        $profiles = isset($options['server_profiles']) && is_array($options['server_profiles'])
            ? $options['server_profiles']
            : array();

        foreach ($profiles as $profile_key => $profile) {
            if (!is_array($profile)) {
                continue;
            }

            $label = isset($profile['label']) && is_string($profile['label']) && $profile['label'] !== ''
                ? $profile['label']
                : (string) $profile_key;

            $this->collect_token_rotation_issue(
                $issues,
                $label,
                isset($profile['bot_token']) ? (string) $profile['bot_token'] : '',
                isset($profile['bot_token_status']) ? (string) $profile['bot_token_status'] : '',
                isset($profile['bot_token_rotated_at']) ? (int) $profile['bot_token_rotated_at'] : 0,
                isset($profile['bot_token_expires_at']) ? (int) $profile['bot_token_expires_at'] : 0,
                $now
            );
        }

        if (empty($issues)) {
            return $result;
        }

        $has_expired = false;

        foreach ($issues as $issue) {
            if ('expired' === $issue['severity']) {
                $has_expired = true;
                break;
            }
        }

        $result['status'] = $has_expired ? 'critical' : 'recommended';
        $lines = array();

        foreach ($issues as $issue) {
            $lines[] = esc_html($issue['message']);
        }

        $result['description'] = '<p>' . implode('</p><p>', $lines) . '</p>';

        return $result;
    }

    /**
     * @param array  $issues
     * @param string $label
     * @param string $token
     * @param string $status
     * @param int    $rotated_at
     * @param int    $expires_at
     * @param int    $now
     */
    private function collect_token_rotation_issue(array &$issues, $label, $token, $status, $rotated_at, $expires_at, $now) {
        if ('' === $token) {
            return;
        }

        if ('expired' === $status || ($expires_at > 0 && $now >= $expires_at)) {
            $issues[] = array(
                'severity' => 'expired',
                'message'  => sprintf(
                    __('Le jeton Discord pour « %s » a dépassé la fenêtre de rotation.', 'discord-bot-jlg'),
                    $label
                ),
            );

            return;
        }

        if ($rotated_at <= 0 || 'unknown' === $status) {
            $issues[] = array(
                'severity' => 'unknown',
                'message'  => sprintf(
                    __('Le jeton Discord pour « %s » n’a pas d’horodatage de rotation. Enregistrez-le de nouveau ou utilisez `wp discord-bot rotate-token`.', 'discord-bot-jlg'),
                    $label
                ),
            );
        }
    }
}
