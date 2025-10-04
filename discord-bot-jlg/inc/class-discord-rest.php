<?php
if (false === defined('ABSPATH')) {
    exit;
}

class Discord_Bot_JLG_REST_Controller {
    const ROUTE_NAMESPACE = 'discord-bot-jlg/v1';
    const ROUTE_STATS = '/stats';

    /**
     * @var Discord_Bot_JLG_API
     */
    private $api;

    public function __construct(Discord_Bot_JLG_API $api) {
        $this->api = $api;

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
                    'permission_callback' => '__return_true',
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
}
