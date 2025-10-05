<?php
if (false === defined('ABSPATH')) {
    exit;
}

class Discord_Bot_JLG_REST_Controller {
    const ROUTE_NAMESPACE = 'discord-bot-jlg/v1';
    const ROUTE_STATS = '/stats';
    const ROUTE_ANALYTICS = '/analytics';

    /**
     * @var Discord_Bot_JLG_API
     */
    private $api;

    /**
     * @var Discord_Bot_JLG_Analytics|null
     */
    private $analytics;

    public function __construct(Discord_Bot_JLG_API $api, $analytics = null) {
        $this->api = $api;
        $this->analytics = ($analytics instanceof Discord_Bot_JLG_Analytics)
            ? $analytics
            : $api->get_analytics_service();

        add_action('rest_api_init', array($this, 'register_routes'));
    }

    public function register_routes() {
        register_rest_route(
            self::ROUTE_NAMESPACE,
            self::ROUTE_STATS,
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array($this, 'handle_get_stats'),
                    'permission_callback' => array($this, 'check_manage_options_permission'),
                    'args'                => array(
                        'profile_key' => array(
                            'description'       => __('Profil de serveur à utiliser.', 'discord-bot-jlg'),
                            'type'              => 'string',
                            'sanitize_callback' => 'sanitize_key',
                        ),
                        'server_id' => array(
                            'description'       => __('Identifiant du serveur Discord.', 'discord-bot-jlg'),
                            'type'              => 'string',
                            'sanitize_callback' => 'sanitize_text_field',
                        ),
                        'force_refresh' => array(
                            'description'       => __('Force le rafraîchissement du cache (administrateurs uniquement).', 'discord-bot-jlg'),
                            'type'              => 'boolean',
                        ),
                    ),
                ),
            ),
            true
        );

        register_rest_route(
            self::ROUTE_NAMESPACE,
            self::ROUTE_ANALYTICS,
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array($this, 'handle_get_analytics'),
                    'permission_callback' => array($this, 'check_manage_options_permission'),
                    'args'                => array(
                        'profile_key' => array(
                            'description'       => __('Profil de serveur à analyser.', 'discord-bot-jlg'),
                            'type'              => 'string',
                            'sanitize_callback' => 'sanitize_key',
                        ),
                        'server_id' => array(
                            'description'       => __('Identifiant du serveur Discord.', 'discord-bot-jlg'),
                            'type'              => 'string',
                            'sanitize_callback' => 'sanitize_text_field',
                        ),
                        'days' => array(
                            'description'       => __('Nombre de jours à agréger.', 'discord-bot-jlg'),
                            'type'              => 'integer',
                            'sanitize_callback' => 'absint',
                        ),
                    ),
                ),
            ),
            true
        );
    }

    public function handle_get_stats(WP_REST_Request $request) {
        $is_user_logged_in      = is_user_logged_in();
        $force_refresh_requested = discord_bot_jlg_validate_bool($request->get_param('force_refresh'));

        if ($is_user_logged_in) {
            $nonce = $request->get_header('X-WP-Nonce');
            if (empty($nonce) || !wp_verify_nonce($nonce, 'wp_rest')) {
                $error_payload = array(
                    'nonce_expired' => true,
                    'message'       => __('Nonce invalide', 'discord-bot-jlg'),
                );

                return rest_ensure_response(
                    new WP_REST_Response(
                        array(
                            'success' => false,
                            'data'    => $error_payload,
                        ),
                        403
                    )
                );
            }
        }

        $profile_key = $request->get_param('profile_key');
        if (!is_string($profile_key)) {
            $profile_key = '';
        }
        $profile_key = sanitize_key($profile_key);

        $server_id = $request->get_param('server_id');
        if (!is_string($server_id)) {
            $server_id = '';
        }
        $server_id = sanitize_text_field($server_id);

        $result = $this->api->process_refresh_request(
            array(
                'is_public_request' => !$is_user_logged_in,
                'profile_key'       => $profile_key,
                'server_id'         => $server_id,
                'force_refresh'     => $force_refresh_requested,
            )
        );

        $status = isset($result['status']) ? (int) $result['status'] : 200;

        $response = array(
            'success' => !empty($result['success']),
            'data'    => (isset($result['data']) && is_array($result['data'])) ? $result['data'] : array(),
        );

        return rest_ensure_response(new WP_REST_Response($response, $status));
    }

    public function handle_get_analytics(WP_REST_Request $request) {
        $analytics = $this->analytics instanceof Discord_Bot_JLG_Analytics
            ? $this->analytics
            : $this->api->get_analytics_service();

        if (!($analytics instanceof Discord_Bot_JLG_Analytics)) {
            $response = array(
                'success' => false,
                'data'    => array(
                    'message' => __('Service d\'analyse indisponible.', 'discord-bot-jlg'),
                ),
            );

            return rest_ensure_response(new WP_REST_Response($response, 501));
        }

        $profile_key = $request->get_param('profile_key');
        if (!is_string($profile_key)) {
            $profile_key = '';
        }
        $profile_key = sanitize_key($profile_key);

        if ('' === $profile_key) {
            $profile_key = 'default';
        }

        $server_id = $request->get_param('server_id');
        if (!is_string($server_id)) {
            $server_id = '';
        }
        $server_id = preg_replace('/[^0-9]/', '', $server_id);

        $days = (int) $request->get_param('days');
        if ($days <= 0) {
            $days = 7;
        } elseif ($days > 30) {
            $days = 30;
        }

        $aggregates = $analytics->get_aggregates(
            array(
                'profile_key' => $profile_key,
                'server_id'   => $server_id,
                'days'        => $days,
            )
        );

        $response = array(
            'success' => true,
            'data'    => $aggregates,
        );

        return rest_ensure_response(new WP_REST_Response($response, 200));
    }

    public function check_manage_options_permission(WP_REST_Request $request) {
        if (current_user_can('manage_options')) {
            return true;
        }

        $configured_keys = apply_filters('discord_bot_jlg_rest_api_keys', array());
        if (!is_array($configured_keys)) {
            $configured_keys = array($configured_keys);
        }

        $configured_keys = array_filter(array_map('strval', $configured_keys));

        if (!empty($configured_keys)) {
            $provided_key = $request->get_header('X-Discord-Bot-JLG-Key');
            if (!is_string($provided_key) || '' === $provided_key) {
                $provided_key = $request->get_param('api_key');
            }

            if (is_string($provided_key) && '' !== $provided_key) {
                $provided_key = trim($provided_key);

                foreach ($configured_keys as $expected_key) {
                    $expected_key = trim($expected_key);

                    if ('' === $expected_key) {
                        continue;
                    }

                    if (function_exists('hash_equals') && hash_equals($expected_key, $provided_key)) {
                        return true;
                    }

                    if ($expected_key === $provided_key) {
                        return true;
                    }
                }
            }
        }

        return new WP_Error(
            'rest_forbidden',
            __('Vous n\'avez pas les droits nécessaires pour accéder à cette ressource.', 'discord-bot-jlg'),
            array('status' => 403)
        );
    }
}
