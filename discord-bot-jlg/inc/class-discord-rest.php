<?php
if (false === defined('ABSPATH')) {
    exit;
}

if (!class_exists('Discord_Bot_JLG_API_Key_Repository')) {
    require_once __DIR__ . '/class-discord-api-key-repository.php';
}

class Discord_Bot_JLG_REST_Controller {
    const ROUTE_NAMESPACE = 'discord-bot-jlg/v1';
    const ROUTE_STATS = '/stats';
    const ROUTE_ANALYTICS = '/analytics';
    const ROUTE_EVENTS = '/events';
    const ROUTE_ANALYTICS_EXPORT = '/analytics/export';
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
        $this->analytics = (is_object($analytics) && method_exists($analytics, 'get_aggregates'))
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
                            'sanitize_callback' => 'discord_bot_jlg_sanitize_profile_key',
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
                            'sanitize_callback' => 'discord_bot_jlg_sanitize_profile_key',
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

        register_rest_route(
            self::ROUTE_NAMESPACE,
            self::ROUTE_ANALYTICS_EXPORT,
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array($this, 'handle_export_analytics'),
                    'permission_callback' => array($this, 'check_rest_permissions'),
                    'args'                => array(
                        'profile_key' => array(
                            'description'       => __('Profil de serveur à exporter.', 'discord-bot-jlg'),
                            'type'              => 'string',
                            'sanitize_callback' => 'discord_bot_jlg_sanitize_profile_key',
                        ),
                        'server_id' => array(
                            'description'       => __('Identifiant du serveur Discord.', 'discord-bot-jlg'),
                            'type'              => 'string',
                            'sanitize_callback' => 'sanitize_text_field',
                        ),
                        'days' => array(
                            'description'       => __('Nombre de jours inclus (maximum 30).', 'discord-bot-jlg'),
                            'type'              => 'integer',
                            'sanitize_callback' => 'absint',
                        ),
                        'format' => array(
                            'description' => __('Format de sortie (csv ou json).', 'discord-bot-jlg'),
                            'type'        => 'string',
                        ),
                        'fields' => array(
                            'description' => __('Liste de colonnes à exporter (séparées par des virgules).', 'discord-bot-jlg'),
                            'type'        => 'string',
                        ),
                        'delimiter' => array(
                            'description' => __('Délimiteur CSV (comma, semicolon, tab).', 'discord-bot-jlg'),
                            'type'        => 'string',
                        ),
                        'timezone' => array(
                            'description' => __('Fuseau horaire utilisé pour les dates.', 'discord-bot-jlg'),
                            'type'        => 'string',
                        ),
                        'filename' => array(
                            'description' => __('Nom de fichier personnalisé.', 'discord-bot-jlg'),
                            'type'        => 'string',
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
        $profile_key = discord_bot_jlg_sanitize_profile_key($profile_key);

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
        $analytics = (is_object($this->analytics) && method_exists($this->analytics, 'get_aggregates'))
            ? $this->analytics
            : $this->api->get_analytics_service();

        if (!is_object($analytics) || !method_exists($analytics, 'get_aggregates')) {
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
        $profile_key = discord_bot_jlg_sanitize_profile_key($profile_key);

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

    public function handle_export_analytics(WP_REST_Request $request) {
        $analytics = (is_object($this->analytics) && method_exists($this->analytics, 'get_aggregates'))
            ? $this->analytics
            : $this->api->get_analytics_service();

        if (!is_object($analytics) || !method_exists($analytics, 'get_aggregates')) {
            return rest_ensure_response(
                new WP_REST_Response(
                    array(
                        'success' => false,
                        'data'    => array(
                            'message' => __('Service d’analyse indisponible.', 'discord-bot-jlg'),
                        ),
                    ),
                    501
                )
            );
        }

        $profile_key = discord_bot_jlg_sanitize_profile_key($request->get_param('profile_key'));
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

        $format = strtolower((string) $request->get_param('format'));
        if (!in_array($format, array('csv', 'json'), true)) {
            $format = 'csv';
        }

        $fields    = $this->normalize_export_fields($request->get_param('fields'));
        $delimiter = $this->normalize_export_delimiter($request->get_param('delimiter'));
        $timezone_identifier = $this->normalize_export_timezone($request->get_param('timezone'));
        $timezone = $this->create_timezone($timezone_identifier);

        $cache_key = $this->get_analytics_cache_key($profile_key, $server_id, $days);
        $cache_ttl = $this->get_analytics_cache_ttl($profile_key, $server_id, $days);

        $aggregates = $this->maybe_get_cached_analytics($cache_key, $cache_ttl);

        if (null === $aggregates) {
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

            if (!is_array($aggregates)) {
                $aggregates = array();
            }

            $this->maybe_store_cached_analytics($cache_key, $aggregates, $cache_ttl);
        }

        if (!is_array($aggregates)) {
            $aggregates = array();
        }

        $timeseries = isset($aggregates['timeseries']) && is_array($aggregates['timeseries'])
            ? $aggregates['timeseries']
            : array();

        $rows = $this->map_timeseries_for_export($timeseries, $fields, $profile_key, $server_id, $timezone);

        if ('json' === $format) {
            $payload = array(
                'success' => true,
                'data'    => array(
                    'profile_key' => $profile_key,
                    'server_id'   => $server_id,
                    'fields'      => $fields,
                    'timezone'    => $timezone_identifier,
                    'range'       => isset($aggregates['range']) ? $aggregates['range'] : array(),
                    'averages'    => isset($aggregates['averages']) ? $aggregates['averages'] : array(),
                    'timeseries'  => $rows,
                ),
            );

            $response = new WP_REST_Response($payload, 200);
            $response->header(
                'Content-Disposition',
                'attachment; filename="' . $this->build_export_filename($request->get_param('filename'), $profile_key, 'json') . '"'
            );

            return $response;
        }

        $csv = $this->convert_rows_to_csv($fields, $rows, $delimiter);
        $filename = $this->build_export_filename($request->get_param('filename'), $profile_key, 'csv');

        $response = new WP_REST_Response($csv, 200);
        $response->header('Content-Type', 'text/csv; charset=utf-8');
        $response->header('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
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

    private function normalize_export_fields($fields_param) {
        $allowed = array('profile_key', 'server_id', 'timestamp', 'iso8601', 'date', 'time', 'online', 'presence', 'total', 'premium');
        $default = array('profile_key', 'server_id', 'timestamp', 'iso8601', 'online', 'presence', 'total', 'premium');

        if (empty($fields_param)) {
            return $default;
        }

        if (is_string($fields_param)) {
            $parts = preg_split('/[\s,]+/', $fields_param);
        } elseif (is_array($fields_param)) {
            $parts = $fields_param;
        } else {
            $parts = array();
        }

        if (!is_array($parts)) {
            return $default;
        }

        $fields = array();

        foreach ($parts as $part) {
            if (!is_string($part)) {
                continue;
            }

            $key = sanitize_key($part);
            if ('' === $key) {
                continue;
            }

            if (!in_array($key, $allowed, true)) {
                continue;
            }

            $fields[] = $key;
        }

        $fields = array_values(array_unique($fields));

        if (empty($fields)) {
            return $default;
        }

        return $fields;
    }

    private function normalize_export_delimiter($delimiter) {
        if (!is_string($delimiter)) {
            return ',';
        }

        $normalized = strtolower(trim($delimiter));

        switch ($normalized) {
            case 'semicolon':
            case ';':
                return ';';
            case 'tab':
            case '\\t':
                return "\t";
            case 'pipe':
            case '|':
                return '|';
            case 'comma':
            case ',':
            default:
                return ',';
        }
    }

    private function normalize_export_timezone($timezone_param) {
        if (is_string($timezone_param)) {
            $timezone_param = trim($timezone_param);
        } else {
            $timezone_param = '';
        }

        if ('' === $timezone_param && function_exists('wp_timezone_string')) {
            $timezone_param = wp_timezone_string();
        }

        if ('' === $timezone_param) {
            $timezone_param = 'UTC';
        }

        try {
            new DateTimeZone($timezone_param);
            return $timezone_param;
        } catch (Exception $exception) {
            return 'UTC';
        }
    }

    private function create_timezone($identifier) {
        try {
            return new DateTimeZone($identifier);
        } catch (Exception $exception) {
            return new DateTimeZone('UTC');
        }
    }

    private function map_timeseries_for_export($timeseries, array $fields, $profile_key, $server_id, DateTimeZone $timezone) {
        $rows = array();
        $date_format = get_option('date_format');
        if (!is_string($date_format) || '' === $date_format) {
            $date_format = 'Y-m-d';
        }
        $time_format = get_option('time_format');
        if (!is_string($time_format) || '' === $time_format) {
            $time_format = 'H:i';
        }

        foreach ($timeseries as $point) {
            if (!is_array($point)) {
                continue;
            }

            $timestamp = isset($point['timestamp']) ? (int) $point['timestamp'] : 0;
            if ($timestamp <= 0) {
                continue;
            }

            $date = new DateTime('@' . $timestamp);
            $date->setTimezone($timezone);

            $row = array();

            foreach ($fields as $field) {
                switch ($field) {
                    case 'profile_key':
                        $row[$field] = $profile_key;
                        break;
                    case 'server_id':
                        $row[$field] = $server_id;
                        break;
                    case 'timestamp':
                        $row[$field] = $timestamp;
                        break;
                    case 'iso8601':
                        $row[$field] = $date->format(DateTimeInterface::ATOM);
                        break;
                    case 'date':
                        if (function_exists('wp_date')) {
                            $row[$field] = wp_date($date_format, $timestamp, $timezone);
                        } else {
                            $row[$field] = $date->format($date_format);
                        }
                        break;
                    case 'time':
                        if (function_exists('wp_date')) {
                            $row[$field] = wp_date($time_format, $timestamp, $timezone);
                        } else {
                            $row[$field] = $date->format($time_format);
                        }
                        break;
                    case 'online':
                        $row[$field] = isset($point['online']) ? (int) $point['online'] : null;
                        break;
                    case 'presence':
                        $row[$field] = isset($point['presence']) ? (int) $point['presence'] : null;
                        break;
                    case 'total':
                        $row[$field] = isset($point['total']) ? (int) $point['total'] : null;
                        break;
                    case 'premium':
                        $row[$field] = isset($point['premium']) && null !== $point['premium']
                            ? (int) $point['premium']
                            : null;
                        break;
                    default:
                        $row[$field] = isset($point[$field]) ? $point[$field] : null;
                }
            }

            $rows[] = $row;
        }

        return $rows;
    }

    private function convert_rows_to_csv(array $fields, array $rows, $delimiter) {
        $handle = fopen('php://temp', 'r+');
        if (false === $handle) {
            return '';
        }

        fputcsv($handle, $fields, $delimiter);

        foreach ($rows as $row) {
            $line = array();
            foreach ($fields as $field) {
                $value = isset($row[$field]) ? $row[$field] : '';

                if (is_bool($value)) {
                    $value = $value ? '1' : '0';
                } elseif (is_int($value) || is_float($value)) {
                    $value = $value + 0;
                } elseif (null === $value) {
                    $value = '';
                }

                $line[] = $value;
            }

            fputcsv($handle, $line, $delimiter);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        if (!is_string($csv)) {
            return '';
        }

        return $csv;
    }

    private function build_export_filename($requested, $profile_key, $format) {
        $extension = ('json' === $format) ? 'json' : 'csv';
        $profile_slug = discord_bot_jlg_sanitize_profile_key($profile_key);
        if ('' === $profile_slug) {
            $profile_slug = 'default';
        }

        $default = sprintf('discord-analytics-%s-%s.%s', $profile_slug, gmdate('Ymd-His'), $extension);

        if (!is_string($requested)) {
            return $default;
        }

        $requested = trim($requested);
        if ('' === $requested) {
            return $default;
        }

        $sanitized = preg_replace('/[^A-Za-z0-9_\-.]/', '-', $requested);
        if (!is_string($sanitized) || '' === trim($sanitized, '-_.')) {
            return $default;
        }

        $sanitized = trim($sanitized, '.');
        if ('' === $sanitized) {
            return $default;
        }

        if (!preg_match(sprintf('/\.%s$/i', preg_quote($extension, '/')), $sanitized)) {
            $sanitized .= '.' . $extension;
        }

        return $sanitized;
    }

    public function check_rest_permissions(WP_REST_Request $request) {
        $required_action = $this->determine_required_capability_action($request);

        if (Discord_Bot_JLG_Capabilities::current_user_can($required_action)) {
            return true;
        }

        $profile_key = $this->determine_requested_profile_key($request);
        $scope = $this->determine_required_scope($request);

        if ('manage_profiles' !== $scope) {
            if ($this->validate_request_api_key_access($request, $scope, $profile_key, $required_action)) {
                return true;
            }
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

    private function determine_required_capability_action(WP_REST_Request $request) {
        $route = '';

        if ($request instanceof WP_REST_Request) {
            $route = $request->get_route();
        }

        if (!is_string($route)) {
            $route = '';
        }

        $profile_key = $this->determine_requested_profile_key($request);

        if (false !== strpos($route, self::ROUTE_ANALYTICS_EXPORT)) {
            return 'export_profile_analytics:' . $profile_key;
        }

        if (false !== strpos($route, self::ROUTE_STATS)) {
            $force_refresh = discord_bot_jlg_validate_bool($request->get_param('force_refresh'));

            if ($force_refresh) {
                return 'manage_profiles';
            }

            return 'view_profile_stats:' . $profile_key;
        }

        if (
            false !== strpos($route, self::ROUTE_ANALYTICS)
            || false !== strpos($route, self::ROUTE_EVENTS)
        ) {
            return 'view_profile_analytics:' . $profile_key;
        }

        return 'view_profile_analytics:' . $profile_key;
    }

    private function determine_requested_profile_key(WP_REST_Request $request) {
        if (!($request instanceof WP_REST_Request)) {
            return 'default';
        }

        $profile_key = discord_bot_jlg_sanitize_profile_key($request->get_param('profile_key'));
        if ('' === $profile_key) {
            $profile_key = 'default';
        }

        return $profile_key;
    }

    private function determine_required_scope(WP_REST_Request $request) {
        $route = '';

        if ($request instanceof WP_REST_Request) {
            $route = $request->get_route();
        }

        if (!is_string($route)) {
            $route = '';
        }

        if (false !== strpos($route, self::ROUTE_ANALYTICS_EXPORT)) {
            return 'export';
        }

        if (false !== strpos($route, self::ROUTE_STATS)) {
            $force_refresh = discord_bot_jlg_validate_bool($request->get_param('force_refresh'));

            if ($force_refresh) {
                return 'manage_profiles';
            }

            return 'stats';
        }

        if (
            false !== strpos($route, self::ROUTE_ANALYTICS)
            || false !== strpos($route, self::ROUTE_EVENTS)
        ) {
            return 'analytics';
        }

        return 'analytics';
    }

    private function request_requires_cookie_nonce(WP_REST_Request $request) {
        if ($this->request_uses_authorization_header($request)) {
            return false;
        }

        if ($this->request_has_logged_in_cookie()) {
            return true;
        }

        return is_user_logged_in();
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

    private function validate_request_api_key_access(WP_REST_Request $request, $scope, $profile_key, $required_action) {
        $access_key = $this->extract_request_access_key($request);

        if ('' === $access_key) {
            return false;
        }

        $repository = $this->api->get_api_key_repository();
        if (!($repository instanceof Discord_Bot_JLG_API_Key_Repository)) {
            return false;
        }

        $validation = $repository->validate_key_for_request(
            $access_key,
            array(
                'scope'       => $scope,
                'profile_key' => $profile_key,
                'route'       => $request->get_route(),
                'action'      => $required_action,
            )
        );

        if (!is_array($validation)) {
            return false;
        }

        if (!empty($validation['valid'])) {
            $key_record = isset($validation['key']) && is_array($validation['key']) ? $validation['key'] : array();

            if (isset($key_record['id'])) {
                $repository->record_usage((int) $key_record['id']);
            }

            $this->log_api_key_event(
                'granted',
                array(
                    'scope'       => $scope,
                    'profile_key' => $profile_key,
                    'route'       => $request->get_route(),
                    'key'         => $key_record,
                    'fingerprint' => isset($validation['fingerprint']) ? $validation['fingerprint'] : '',
                )
            );

            return true;
        }

        $error = isset($validation['error']) ? $validation['error'] : 'denied';
        $fingerprint = isset($validation['fingerprint']) ? $validation['fingerprint'] : '';
        $key_record = isset($validation['key']) && is_array($validation['key']) ? $validation['key'] : array();

        $this->log_api_key_event(
            'denied',
            array(
                'scope'       => $scope,
                'profile_key' => $profile_key,
                'route'       => $request->get_route(),
                'error'       => $error,
                'key'         => $key_record,
                'fingerprint' => $fingerprint,
            )
        );

        return false;
    }

    private function extract_request_access_key(WP_REST_Request $request) {
        $access_key = $request->get_param('access_key');

        if (!is_string($access_key) || '' === trim($access_key)) {
            $access_key = $request->get_header('X-Discord-Analytics-Key');
        }

        if (!is_string($access_key) || '' === trim($access_key)) {
            $access_key = $request->get_header('X-Api-Key');
        }

        if (!is_string($access_key) || '' === trim($access_key)) {
            $authorization = $request->get_header('authorization');
            if (is_string($authorization) && '' !== $authorization) {
                if (0 === stripos($authorization, 'bearer ')) {
                    $access_key = substr($authorization, 7);
                } elseif (0 === stripos($authorization, 'token ')) {
                    $access_key = substr($authorization, 6);
                }
            }
        }

        if (!is_string($access_key)) {
            return '';
        }

        return trim($access_key);
    }

    private function log_api_key_event($outcome, array $context = array()) {
        if (!($this->event_logger instanceof Discord_Bot_JLG_Event_Logger)) {
            return;
        }

        $payload = array(
            'channel' => 'rest',
            'action'  => 'access',
            'outcome' => sanitize_key($outcome),
        );

        if (!empty($context['scope'])) {
            $payload['scope'] = sanitize_key($context['scope']);
        }

        if (!empty($context['profile_key'])) {
            $payload['profile_key'] = discord_bot_jlg_sanitize_profile_key($context['profile_key']);
        }

        if (!empty($context['route']) && is_string($context['route'])) {
            $payload['route'] = sanitize_text_field($context['route']);
        }

        if (!empty($context['error'])) {
            $payload['error'] = sanitize_key($context['error']);
        }

        if (isset($context['key']) && is_array($context['key'])) {
            $key = $context['key'];
            if (isset($key['id'])) {
                $payload['key_id'] = (int) $key['id'];
            }
            if (!empty($key['fingerprint'])) {
                $payload['fingerprint'] = substr(sanitize_text_field($key['fingerprint']), 0, 12);
            }
            if (!empty($key['label'])) {
                $payload['label'] = sanitize_text_field($key['label']);
            }
        } elseif (!empty($context['fingerprint'])) {
            $payload['fingerprint'] = substr(sanitize_text_field($context['fingerprint']), 0, 12);
        }

        $this->event_logger->log('rest_api_key', $payload);
    }
}
