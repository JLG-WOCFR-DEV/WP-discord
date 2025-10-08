<?php
if (false === defined('ABSPATH')) {
    exit;
}

class Discord_Bot_JLG_REST_Controller {
    const ROUTE_NAMESPACE = 'discord-bot-jlg/v1';
    const ROUTE_STATS = '/stats';
    const ROUTE_ANALYTICS = '/analytics';
    const ROUTE_EVENTS = '/events';
    const ANALYTICS_CACHE_GROUP = 'discord_bot_jlg_rest';

    /**
     * @var Discord_Bot_JLG_API
     */
    private $api;

    /**
     * @var Discord_Bot_JLG_Analytics|null
     */
    private $analytics;

    /**
     * @var Discord_Bot_JLG_Event_Logger|null
     */
    private $event_logger;

    public function __construct(Discord_Bot_JLG_API $api, $analytics = null, $event_logger = null) {
        $this->api = $api;
        $this->analytics = ($analytics instanceof Discord_Bot_JLG_Analytics)
            ? $analytics
            : $api->get_analytics_service();
        $this->event_logger = ($event_logger instanceof Discord_Bot_JLG_Event_Logger)
            ? $event_logger
            : $api->get_event_logger();

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
                    'permission_callback' => array($this, 'check_rest_permissions'),
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
                    'permission_callback' => array($this, 'check_rest_permissions'),
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

        register_rest_route(
            self::ROUTE_NAMESPACE,
            self::ROUTE_EVENTS,
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array($this, 'handle_get_events'),
                    'permission_callback' => array($this, 'check_rest_permissions'),
                    'args'                => array(
                        'limit' => array(
                            'description'       => __('Nombre maximum d\'événements à renvoyer.', 'discord-bot-jlg'),
                            'type'              => 'integer',
                            'sanitize_callback' => 'absint',
                        ),
                        'type' => array(
                            'description'       => __('Filtre sur le type d\'événement (ex. discord_http).', 'discord-bot-jlg'),
                            'type'              => 'string',
                            'sanitize_callback' => 'sanitize_key',
                        ),
                        'after_id' => array(
                            'description'       => __('Renvoie uniquement les événements avec un identifiant supérieur.', 'discord-bot-jlg'),
                            'type'              => 'integer',
                            'sanitize_callback' => 'absint',
                        ),
                        'after' => array(
                            'description'       => __('Renvoie uniquement les événements postérieurs à ce timestamp (UTC).', 'discord-bot-jlg'),
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
        $is_user_logged_in       = is_user_logged_in();
        $force_refresh_requested = discord_bot_jlg_validate_bool($request->get_param('force_refresh'));
        $nonce_required          = $is_user_logged_in && $this->request_requires_cookie_nonce($request);

        if ($nonce_required) {
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

        $cache_ttl = $this->get_analytics_cache_ttl($profile_key, $server_id, $days);
        $cache_key = $this->get_analytics_cache_key($profile_key, $server_id, $days);

        $aggregates = $this->maybe_get_cached_analytics($cache_key, $cache_ttl);

        if (!is_array($aggregates)) {
            $aggregates = $analytics->get_aggregates(
                array(
                    'profile_key' => $profile_key,
                    'server_id'   => $server_id,
                    'days'        => $days,
                )
            );

            if (is_wp_error($aggregates)) {
                return $aggregates;
            }

            if (false === $aggregates) {
                return rest_ensure_response(
                    new WP_Error(
                        'discord_bot_jlg_analytics_unavailable',
                        __('Impossible de récupérer les analyses.', 'discord-bot-jlg'),
                        array('status' => 500)
                    )
                );
            }

            if (!is_array($aggregates)) {
                $aggregates = array();
            }

            $this->maybe_store_cached_analytics($cache_key, $aggregates, $cache_ttl);
        }

        $response = array(
            'success' => true,
            'data'    => $aggregates,
        );

        return rest_ensure_response(new WP_REST_Response($response, 200));
    }

    public function handle_get_events(WP_REST_Request $request) {
        $event_logger = $this->event_logger instanceof Discord_Bot_JLG_Event_Logger
            ? $this->event_logger
            : $this->api->get_event_logger();

        if (!($event_logger instanceof Discord_Bot_JLG_Event_Logger)) {
            $response = array(
                'success' => false,
                'data'    => array(
                    'message' => __('Journal des événements indisponible.', 'discord-bot-jlg'),
                ),
            );

            return rest_ensure_response(new WP_REST_Response($response, 501));
        }

        $limit = (int) $request->get_param('limit');
        if ($limit <= 0) {
            $limit = 50;
        }

        $type = $request->get_param('type');
        if (!is_string($type)) {
            $type = '';
        }

        $after_id = (int) $request->get_param('after_id');
        if ($after_id < 0) {
            $after_id = 0;
        }

        $after = (int) $request->get_param('after');
        if ($after < 0) {
            $after = 0;
        }

        $events = $event_logger->get_events(
            array(
                'limit'    => $limit,
                'type'     => $type,
                'after_id' => $after_id,
                'after'    => $after,
            )
        );

        if (!is_array($events)) {
            $events = array();
        }

        $response = array(
            'success' => true,
            'data'    => $events,
        );

        return rest_ensure_response(new WP_REST_Response($response, 200));
    }

    private function get_analytics_cache_key($profile_key, $server_id, $days) {
        $parts = array(
            (string) $profile_key,
            (string) $server_id,
            (string) (int) $days,
        );

        return 'discord_bot_jlg_analytics_' . md5(implode('|', $parts));
    }

    private function get_analytics_cache_ttl($profile_key, $server_id, $days) {
        $default_ttl = defined('MINUTE_IN_SECONDS') ? 5 * MINUTE_IN_SECONDS : 300;

        $ttl = (int) apply_filters(
            'discord_bot_jlg_rest_analytics_cache_ttl',
            $default_ttl,
            $profile_key,
            $server_id,
            $days
        );

        if ($ttl < 0) {
            $ttl = 0;
        }

        return $ttl;
    }

    private function maybe_get_cached_analytics($cache_key, $ttl) {
        if ($ttl <= 0) {
            return null;
        }

        if (function_exists('wp_cache_get')) {
            $cached = wp_cache_get($cache_key, self::ANALYTICS_CACHE_GROUP);
            if (false !== $cached && is_array($cached)) {
                return $cached;
            }
        }

        if (function_exists('get_transient')) {
            $transient = get_transient($cache_key);
            if (false !== $transient && is_array($transient)) {
                return $transient;
            }
        }

        return null;
    }

    private function maybe_store_cached_analytics($cache_key, $aggregates, $ttl) {
        if ($ttl <= 0 || !is_array($aggregates)) {
            return;
        }

        if (function_exists('wp_cache_set')) {
            wp_cache_set($cache_key, $aggregates, self::ANALYTICS_CACHE_GROUP, $ttl);
        }

        if (function_exists('set_transient')) {
            set_transient($cache_key, $aggregates, $ttl);
        }
    }

    public function check_rest_permissions(WP_REST_Request $request) {
        if (current_user_can('manage_options')) {
            return true;
        }

        $configured_key = apply_filters('discord_bot_jlg_rest_access_key', '');
        if (is_string($configured_key)) {
            $configured_key = trim($configured_key);
        } else {
            $configured_key = '';
        }

        if ('' !== $configured_key) {
            $provided_key = $this->extract_request_access_key($request);

            if ('' !== $provided_key && function_exists('hash_equals') && hash_equals($configured_key, $provided_key)) {
                return true;
            }

            if ('' !== $provided_key && $configured_key === $provided_key) {
                return true;
            }
        }

        return new WP_Error(
            'discord_bot_jlg_forbidden',
            __('Vous devez être connecté avec les droits appropriés pour accéder à ces données.', 'discord-bot-jlg'),
            array('status' => 403)
        );
    }

    private function request_requires_cookie_nonce(WP_REST_Request $request) {
        if ($this->request_uses_authorization_header($request)) {
            return false;
        }

        return $this->request_has_logged_in_cookie();
    }

    private function request_uses_authorization_header(WP_REST_Request $request) {
        $header_names = array('authorization', 'x-wp-authentication');

        foreach ($header_names as $header_name) {
            $header_value = $request->get_header($header_name);
            if (!empty($header_value)) {
                return true;
            }
        }

        if (!empty($_SERVER['PHP_AUTH_USER']) || !empty($_SERVER['PHP_AUTH_PW'])) {
            return true;
        }

        if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
            return true;
        }

        return false;
    }

    private function request_has_logged_in_cookie() {
        if (empty($_COOKIE) || !is_array($_COOKIE)) {
            return false;
        }

        $cookie_names = array();

        if (defined('LOGGED_IN_COOKIE')) {
            $cookie_names[] = LOGGED_IN_COOKIE;
        }

        if (defined('AUTH_COOKIE')) {
            $cookie_names[] = AUTH_COOKIE;
        }

        if (defined('SECURE_AUTH_COOKIE')) {
            $cookie_names[] = SECURE_AUTH_COOKIE;
        }

        foreach ($cookie_names as $cookie_name) {
            if (!empty($cookie_name) && !empty($_COOKIE[$cookie_name])) {
                return true;
            }
        }

        return false;
    }

    private function extract_request_access_key(WP_REST_Request $request) {
        $access_key = $request->get_param('access_key');

        if (!is_string($access_key) || '' === trim($access_key)) {
            $access_key = $request->get_header('X-Discord-Analytics-Key');
        }

        if (!is_string($access_key)) {
            return '';
        }

        return trim($access_key);
    }
}
