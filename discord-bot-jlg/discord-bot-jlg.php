<?php
/**
 * Plugin Name: Discord Bot - JLG
 * Plugin URI: https://yourwebsite.com/
 * Description: Affiche les statistiques de votre serveur Discord (membres en ligne et total)
 * Version: 1.0
 * Requires at least: 5.2
 * Author: Jérôme Le Gousse
 * Text Domain: discord-bot-jlg
 * Domain Path: /languages
 * License: GPL v2 or later
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('DISCORD_BOT_JLG_VERSION')) {
    $plugin_data    = get_file_data(__FILE__, array('Version' => 'Version'));
    $plugin_version = !empty($plugin_data['Version']) ? $plugin_data['Version'] : '1.0';

    define('DISCORD_BOT_JLG_VERSION', $plugin_version);
}

define('DISCORD_BOT_JLG_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('DISCORD_BOT_JLG_PLUGIN_URL', plugin_dir_url(__FILE__));
define('DISCORD_BOT_JLG_OPTION_NAME', 'discord_server_stats_options');
define('DISCORD_BOT_JLG_CACHE_KEY', 'discord_server_stats_cache');
define('DISCORD_BOT_JLG_DEFAULT_CACHE_DURATION', 300);
define('DISCORD_BOT_JLG_CRON_HOOK', 'discord_bot_jlg_refresh_cache');
define('DISCORD_BOT_JLG_ANALYTICS_RETENTION_DEFAULT', 90);

require_once DISCORD_BOT_JLG_PLUGIN_PATH . 'inc/cron.php';

add_filter('cron_schedules', 'discord_bot_jlg_register_cron_schedule');

if (!function_exists('discord_bot_jlg_get_default_options')) {
    /**
     * Renvoie les valeurs par défaut utilisées pour initialiser les options du plugin.
     *
     * @return array
     */
    function discord_bot_jlg_get_default_options() {
        return array(
            'server_id'      => '',
            'bot_token'      => '',
            'bot_token_rotated_at' => 0,
            'server_profiles'=> array(),
            'demo_mode'      => false,
            'show_online'    => true,
            'show_total'     => true,
            'custom_css'     => '',
            'widget_title'   => 'Discord Server',
            'cache_duration' => DISCORD_BOT_JLG_DEFAULT_CACHE_DURATION,
            'analytics_retention_days' => DISCORD_BOT_JLG_ANALYTICS_RETENTION_DEFAULT,
            'analytics_alert_webhook_secret' => '',
        );
    }
}

/**
 * Supprime les données enregistrées par le plugin lors de la désinstallation.
 *
 * @return void
 */
function discord_bot_jlg_uninstall() {
    delete_option(DISCORD_BOT_JLG_OPTION_NAME);

    wp_clear_scheduled_hook(DISCORD_BOT_JLG_CRON_HOOK);

    if (!class_exists('Discord_Bot_JLG_API')) {
        if (!class_exists('Discord_Bot_JLG_Http_Client')) {
            require_once DISCORD_BOT_JLG_PLUGIN_PATH . 'inc/class-discord-http.php';
        }

        if (!class_exists('Discord_Bot_JLG_Analytics')) {
            require_once DISCORD_BOT_JLG_PLUGIN_PATH . 'inc/class-discord-analytics.php';
        }

        if (!class_exists('Discord_Bot_JLG_Event_Logger')) {
            require_once DISCORD_BOT_JLG_PLUGIN_PATH . 'inc/class-discord-event-logger.php';
        }

        if (!class_exists('Discord_Bot_JLG_Options_Repository')) {
            require_once DISCORD_BOT_JLG_PLUGIN_PATH . 'inc/class-discord-options-repository.php';
        }

        require_once DISCORD_BOT_JLG_PLUGIN_PATH . 'inc/class-discord-api.php';
    } elseif (!class_exists('Discord_Bot_JLG_Http_Client')) {
        require_once DISCORD_BOT_JLG_PLUGIN_PATH . 'inc/class-discord-http.php';
    }

    if (!class_exists('Discord_Bot_JLG_Analytics')) {
        require_once DISCORD_BOT_JLG_PLUGIN_PATH . 'inc/class-discord-analytics.php';
    }

    if (!class_exists('Discord_Bot_JLG_Event_Logger')) {
        require_once DISCORD_BOT_JLG_PLUGIN_PATH . 'inc/class-discord-event-logger.php';
    }

    if (!class_exists('Discord_Bot_JLG_Options_Repository')) {
        require_once DISCORD_BOT_JLG_PLUGIN_PATH . 'inc/class-discord-options-repository.php';
    }

    if (!class_exists('Discord_Bot_JLG_Token_Store')) {
        require_once DISCORD_BOT_JLG_PLUGIN_PATH . 'inc/class-discord-token-store.php';
    }

    if (class_exists('Discord_Bot_JLG_API')) {
        $analytics = new Discord_Bot_JLG_Analytics();
        $event_logger = new Discord_Bot_JLG_Event_Logger();
        $options_repository = new Discord_Bot_JLG_Options_Repository(
            DISCORD_BOT_JLG_OPTION_NAME,
            'discord_bot_jlg_get_default_options'
        );
        $api = new Discord_Bot_JLG_API(
            DISCORD_BOT_JLG_OPTION_NAME,
            DISCORD_BOT_JLG_CACHE_KEY,
            DISCORD_BOT_JLG_DEFAULT_CACHE_DURATION,
            null,
            $analytics,
            $event_logger,
            $options_repository
        );

        $api->purge_full_cache();
    }

    if (class_exists('Discord_Bot_JLG_Analytics')) {
        global $wpdb;
        $analytics = new Discord_Bot_JLG_Analytics($wpdb);
        if ($wpdb && method_exists($wpdb, 'query')) {
            $wpdb->query('DROP TABLE IF EXISTS ' . $analytics->get_table_name());
        }
    }

    if (class_exists('Discord_Bot_JLG_Token_Store')) {
        global $wpdb;
        $token_store = new Discord_Bot_JLG_Token_Store($wpdb);
        if ($wpdb && method_exists($wpdb, 'query')) {
            $wpdb->query('DROP TABLE IF EXISTS ' . $token_store->get_table_name());
        }
    }

    if (class_exists('Discord_Bot_JLG_Event_Logger')) {
        delete_option(Discord_Bot_JLG_Event_Logger::OPTION_NAME);
    }
}

register_uninstall_hook(__FILE__, 'discord_bot_jlg_uninstall');

require_once DISCORD_BOT_JLG_PLUGIN_PATH . 'inc/helpers.php';
require_once DISCORD_BOT_JLG_PLUGIN_PATH . 'inc/class-discord-analytics.php';
require_once DISCORD_BOT_JLG_PLUGIN_PATH . 'inc/class-discord-http.php';
require_once DISCORD_BOT_JLG_PLUGIN_PATH . 'inc/class-discord-cache-gateway.php';
require_once DISCORD_BOT_JLG_PLUGIN_PATH . 'inc/class-discord-token-store.php';
require_once DISCORD_BOT_JLG_PLUGIN_PATH . 'inc/class-discord-profile-repository.php';
require_once DISCORD_BOT_JLG_PLUGIN_PATH . 'inc/class-discord-http-connector.php';
require_once DISCORD_BOT_JLG_PLUGIN_PATH . 'inc/class-discord-event-logger.php';
require_once DISCORD_BOT_JLG_PLUGIN_PATH . 'inc/class-discord-options-repository.php';
require_once DISCORD_BOT_JLG_PLUGIN_PATH . 'inc/class-discord-capabilities.php';
require_once DISCORD_BOT_JLG_PLUGIN_PATH . 'inc/class-discord-alerts.php';
require_once DISCORD_BOT_JLG_PLUGIN_PATH . 'inc/class-discord-api.php';
require_once DISCORD_BOT_JLG_PLUGIN_PATH . 'inc/class-discord-stats-service.php';
require_once DISCORD_BOT_JLG_PLUGIN_PATH . 'inc/class-discord-job-queue.php';
require_once DISCORD_BOT_JLG_PLUGIN_PATH . 'inc/class-discord-admin.php';
require_once DISCORD_BOT_JLG_PLUGIN_PATH . 'inc/class-discord-shortcode.php';
require_once DISCORD_BOT_JLG_PLUGIN_PATH . 'inc/class-discord-widget.php';
require_once DISCORD_BOT_JLG_PLUGIN_PATH . 'inc/class-discord-site-health.php';
require_once DISCORD_BOT_JLG_PLUGIN_PATH . 'inc/class-discord-rest.php';
require_once DISCORD_BOT_JLG_PLUGIN_PATH . 'inc/class-discord-metrics-registry.php';
require_once DISCORD_BOT_JLG_PLUGIN_PATH . 'inc/class-discord-analytics-alert-scheduler.php';
require_once DISCORD_BOT_JLG_PLUGIN_PATH . 'inc/class-discord-metrics-controller.php';

if (defined('WP_CLI') && WP_CLI) {
    require_once DISCORD_BOT_JLG_PLUGIN_PATH . 'inc/class-discord-cli.php';

    $cli_options_repository = new Discord_Bot_JLG_Options_Repository(
        DISCORD_BOT_JLG_OPTION_NAME,
        'discord_bot_jlg_get_default_options'
    );

    WP_CLI::add_command(
        'discord-bot',
        new Discord_Bot_JLG_CLI(
            new Discord_Bot_JLG_API(
                DISCORD_BOT_JLG_OPTION_NAME,
                DISCORD_BOT_JLG_CACHE_KEY,
                DISCORD_BOT_JLG_DEFAULT_CACHE_DURATION,
                null,
                null,
                new Discord_Bot_JLG_Event_Logger(),
                $cli_options_repository
            )
        )
    );
}

function discord_bot_jlg_load_textdomain() {
    load_plugin_textdomain('discord-bot-jlg', false, dirname(plugin_basename(__FILE__)) . '/languages');
}

add_action('plugins_loaded', 'discord_bot_jlg_load_textdomain');

add_action('init', array('Discord_Bot_JLG_Capabilities', 'ensure_roles_have_capabilities'));

class DiscordServerStats {

    private $default_options;

    private $api;
    private $admin;
    private $shortcode;
    private $widget;
    private $site_health;
    private $rest_controller;
    private $analytics;
    private $event_logger;
    private $token_store;
    private $options_repository;
    private $job_queue;
    private $alerts;
    private $metrics_registry;
    private $metrics_controller;
    private $alert_scheduler;
    private $cron_state_option = 'discord_bot_jlg_cron_state';

    public function __construct() {
        $this->default_options = discord_bot_jlg_get_default_options();

        $this->analytics = new Discord_Bot_JLG_Analytics();
        $this->event_logger = new Discord_Bot_JLG_Event_Logger();
        $this->token_store = new Discord_Bot_JLG_Token_Store();
        $this->options_repository = new Discord_Bot_JLG_Options_Repository(
            DISCORD_BOT_JLG_OPTION_NAME,
            'discord_bot_jlg_get_default_options'
        );

        $this->api       = new Discord_Bot_JLG_API(
            DISCORD_BOT_JLG_OPTION_NAME,
            DISCORD_BOT_JLG_CACHE_KEY,
            DISCORD_BOT_JLG_DEFAULT_CACHE_DURATION,
            null,
            $this->analytics,
            $this->event_logger,
            $this->options_repository
        );
        $this->alerts    = new Discord_Bot_JLG_Alerts($this->options_repository, $this->analytics, $this->event_logger);
        $this->job_queue = new Discord_Bot_JLG_Job_Queue($this->api, DISCORD_BOT_JLG_OPTION_NAME, $this->event_logger);
        $this->job_queue->register();
        $this->api->set_refresh_dispatcher($this->job_queue);
        $this->api->set_alerts_service($this->alerts);
        $this->admin     = new Discord_Bot_JLG_Admin(DISCORD_BOT_JLG_OPTION_NAME, $this->api, $this->event_logger);
        $this->shortcode = new Discord_Bot_JLG_Shortcode(DISCORD_BOT_JLG_OPTION_NAME, $this->api);
        $this->widget    = new Discord_Bot_JLG_Widget();
        $this->site_health = new Discord_Bot_JLG_Site_Health($this->api);
        $this->metrics_registry = new Discord_Bot_JLG_Metrics_Registry();
        $this->alert_scheduler  = new Discord_Bot_JLG_Analytics_Alert_Scheduler($this->alerts, $this->event_logger);
        $this->alert_scheduler->register();
        $this->rest_controller = new Discord_Bot_JLG_REST_Controller($this->api, $this->analytics, $this->event_logger);
        $this->metrics_controller = new Discord_Bot_JLG_Metrics_Controller(
            $this->metrics_registry,
            $this->options_repository,
            $this->alert_scheduler,
            $this->event_logger
        );

        add_filter('default_option_' . DISCORD_BOT_JLG_OPTION_NAME, array($this, 'provide_default_options'));
        add_filter('option_' . DISCORD_BOT_JLG_OPTION_NAME, array($this, 'merge_options_with_defaults'));

        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        add_action('admin_menu', array($this->admin, 'add_admin_menu'));
        add_action('admin_init', array($this->admin, 'settings_init'));
        add_action('admin_enqueue_scripts', array($this->admin, 'enqueue_admin_styles'), 10, 1);

        add_shortcode('discord_stats', array($this->shortcode, 'render_shortcode'));

        add_action('widgets_init', array($this->widget, 'register_widget'));

        add_action('init', array($this, 'register_block'));

        add_action('wp_ajax_refresh_discord_stats', array($this->api, 'ajax_refresh_stats'));
        add_action('wp_ajax_nopriv_refresh_discord_stats', array($this->api, 'ajax_refresh_stats'));
        add_action('update_option_' . DISCORD_BOT_JLG_OPTION_NAME, array($this, 'handle_settings_update'), 10, 2);

        add_action(DISCORD_BOT_JLG_CRON_HOOK, array($this->job_queue, 'dispatch_refresh_jobs'));
        add_action(DISCORD_BOT_JLG_CRON_HOOK, array($this, 'handle_cron_tick'), 100);
        add_action('discord_bot_jlg_refresh_job_failed', array($this, 'handle_refresh_job_failure'), 10, 3);
        add_action('discord_bot_jlg_refresh_job_succeeded', array($this, 'handle_refresh_job_success'), 10, 2);
        add_action('discord_bot_jlg_after_http_request', array($this->metrics_registry, 'record_http_request'), 10, 6);
        add_action('discord_bot_jlg_event_logged', array($this->metrics_registry, 'record_event'), 10, 1);
    }

    private function get_block_editor_config() {
        $profiles = $this->api->get_server_profiles(false);

        if (!is_array($profiles)) {
            $profiles = array();
        }

        $profiles = array_values(array_filter(array_map(function ($profile) {
            if (!is_array($profile)) {
                return null;
            }

            $profile_key = isset($profile['key']) ? sanitize_key($profile['key']) : '';

            if ('' === $profile_key) {
                return null;
            }

            $label = isset($profile['label']) ? sanitize_text_field($profile['label']) : $profile_key;
            $server_id = isset($profile['server_id']) ? preg_replace('/[^0-9]/', '', (string) $profile['server_id']) : '';
            $has_token = !empty($profile['has_token']);

            return array(
                'key'       => $profile_key,
                'label'     => $label,
                'server_id' => $server_id,
                'has_token' => $has_token,
            );
        }, array_values($profiles))));

        $options_for_defaults = $this->api->get_plugin_options();
        if (!is_array($options_for_defaults)) {
            $options_for_defaults = array();
        }

        $default_mappings = array(
            'icon_online'              => 'default_icon_online',
            'icon_total'               => 'default_icon_total',
            'icon_presence'            => 'default_icon_presence',
            'icon_approximate'         => 'default_icon_approximate',
            'icon_premium'             => 'default_icon_premium',
            'label_online'             => 'default_label_online',
            'label_total'              => 'default_label_total',
            'label_presence'           => 'default_label_presence',
            'label_presence_online'    => 'default_label_presence_online',
            'label_presence_idle'      => 'default_label_presence_idle',
            'label_presence_dnd'       => 'default_label_presence_dnd',
            'label_presence_offline'   => 'default_label_presence_offline',
            'label_presence_streaming' => 'default_label_presence_streaming',
            'label_presence_other'     => 'default_label_presence_other',
            'label_approximate'        => 'default_label_approximate',
            'label_premium'            => 'default_label_premium',
            'label_premium_singular'   => 'default_label_premium_singular',
            'label_premium_plural'     => 'default_label_premium_plural',
        );

        $block_display_defaults = array();

        foreach ($default_mappings as $output_key => $option_key) {
            $block_display_defaults[$output_key] = isset($options_for_defaults[$option_key])
                ? sanitize_text_field($options_for_defaults[$option_key])
                : '';
        }

        return array(
            'profiles' => $profiles,
            'defaults' => $block_display_defaults,
        );
    }

    public function register_block() {
        if (!function_exists('register_block_type')) {
            return;
        }

        $script_handle       = 'discord-bot-jlg-block-editor';
        $editor_style_handle = 'discord-bot-jlg-block-editor-style';
        $style_handle        = 'discord-bot-jlg-inline';

        wp_register_script(
            $script_handle,
            DISCORD_BOT_JLG_PLUGIN_URL . 'assets/js/discord-bot-block.js',
            array('wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-data', 'wp-i18n', 'wp-server-side-render'),
            DISCORD_BOT_JLG_VERSION,
            true
        );

        $block_editor_config = $this->get_block_editor_config();

        wp_localize_script(
            $script_handle,
            'discordBotJlgBlockConfig',
            $block_editor_config
        );

        wp_register_style(
            'discord-bot-jlg',
            DISCORD_BOT_JLG_PLUGIN_URL . 'assets/css/discord-bot-jlg.css',
            array(),
            DISCORD_BOT_JLG_VERSION
        );

        wp_register_style(
            $style_handle,
            DISCORD_BOT_JLG_PLUGIN_URL . 'assets/css/discord-bot-jlg-inline.css',
            array('discord-bot-jlg'),
            DISCORD_BOT_JLG_VERSION
        );

        wp_register_style(
            $editor_style_handle,
            DISCORD_BOT_JLG_PLUGIN_URL . 'assets/css/discord-bot-jlg.css',
            array($style_handle),
            DISCORD_BOT_JLG_VERSION
        );

        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations($script_handle, 'discord-bot-jlg', DISCORD_BOT_JLG_PLUGIN_PATH . 'languages');
        }

        register_block_type(
            DISCORD_BOT_JLG_PLUGIN_PATH . 'block/discord-stats',
            array(
                'render_callback' => array($this->shortcode, 'render_shortcode')
            )
        );
    }

    /**
     * Normalise la valeur de la durée de cache.
     *
     * @param mixed $duration Durée à normaliser.
     *
     * @return int
     */
    private function normalize_cache_duration($duration) {
        $duration = (int) $duration;

        if ($duration < 60) {
            $duration = 60;
        } elseif ($duration > 3600) {
            $duration = 3600;
        }

        return $duration;
    }

    /**
     * Replanifie le cron de rafraîchissement du cache.
     *
     * @param int|null $interval Intervalle à utiliser.
     *
     * @return void
     */
    private function reschedule_cron_event($interval = null, array $context = array()) {
        $base_interval = null === $interval
            ? discord_bot_jlg_get_cron_interval()
            : $this->normalize_cache_duration($interval);

        $state = $this->get_cron_state();
        if (!is_array($state)) {
            $state = array();
        }

        $now = time();
        $outcome = isset($context['outcome']) ? $context['outcome'] : 'success';

        if ('failure' === $outcome) {
            $state['consecutive_failures'] = isset($state['consecutive_failures'])
                ? (int) $state['consecutive_failures'] + 1
                : 1;
            $state['last_failure_at'] = $now;
        } elseif ('success' === $outcome) {
            $state['consecutive_failures'] = 0;
            $state['last_success_at'] = $now;
        } elseif (!isset($state['consecutive_failures'])) {
            $state['consecutive_failures'] = 0;
        }

        $failure_count = (int) $state['consecutive_failures'];
        $calculated_interval = $this->calculate_cron_interval($base_interval, $failure_count, $context);
        $next_timestamp = $now + $calculated_interval;

        $lock_snapshot = array();
        if ($this->api instanceof Discord_Bot_JLG_API) {
            $lock_snapshot = $this->api->get_refresh_lock_snapshot();

            if (!empty($lock_snapshot['locked']) && isset($lock_snapshot['expires_at'])) {
                $lock_expiration = (int) $lock_snapshot['expires_at'];

                if ($lock_expiration > $now) {
                    $next_timestamp = max($next_timestamp, $lock_expiration + 5);
                }
            }
        }

        $schedule_tolerance = (int) apply_filters('discord_bot_jlg_cron_schedule_tolerance', 5, $context);
        if ($schedule_tolerance < 0) {
            $schedule_tolerance = 0;
        }

        $existing_timestamp = wp_next_scheduled(DISCORD_BOT_JLG_CRON_HOOK);
        $schedule_action = 'scheduled';

        if (
            $existing_timestamp
            && $existing_timestamp >= $now
            && abs($existing_timestamp - $next_timestamp) <= $schedule_tolerance
        ) {
            $next_timestamp = $existing_timestamp;
            $schedule_action = 'kept';
        } else {
            wp_clear_scheduled_hook(DISCORD_BOT_JLG_CRON_HOOK);

            if (function_exists('wp_schedule_single_event')) {
                wp_schedule_single_event($next_timestamp, DISCORD_BOT_JLG_CRON_HOOK);
            } else {
                wp_schedule_event($next_timestamp, 'discord_bot_jlg_refresh', DISCORD_BOT_JLG_CRON_HOOK);
            }

            $schedule_action = 'rescheduled';
        }

        $schedule_action = sanitize_key($schedule_action);

        $state['last_interval'] = $calculated_interval;
        $state['next_run_at'] = $next_timestamp;
        $state['last_outcome'] = $outcome;
        $state['schedule_action'] = $schedule_action;

        if (!empty($lock_snapshot)) {
            $state['last_lock_snapshot'] = $lock_snapshot;
        }

        $this->persist_cron_state($state);

        if ($this->event_logger instanceof Discord_Bot_JLG_Event_Logger) {
            $log_context = array(
                'channel'       => 'scheduler',
                'reason'        => isset($context['reason']) ? (string) $context['reason'] : 'cron_reschedule',
                'outcome'       => $outcome,
                'retry_count'   => $failure_count,
                'interval'      => $calculated_interval,
                'backoff_until' => $next_timestamp,
                'schedule_action' => $schedule_action,
            );

            if (isset($context['error_message']) && '' !== trim((string) $context['error_message'])) {
                $log_context['error_message'] = (string) $context['error_message'];
            }

            if (isset($context['job_type'])) {
                $log_context['job_type'] = sanitize_key($context['job_type']);
            }

            if (isset($context['profile_key'])) {
                $log_context['profile_key'] = sanitize_key($context['profile_key']);
            }

            if (!empty($lock_snapshot)) {
                $log_context['lock_detected'] = !empty($lock_snapshot['locked']);
                if (isset($lock_snapshot['expires_at'])) {
                    $log_context['lock_expires_at'] = (int) $lock_snapshot['expires_at'];
                }
            }

            if ($existing_timestamp) {
                $log_context['previous_run'] = (int) $existing_timestamp;
            }

            $this->event_logger->log('discord_scheduler', $log_context);
        }
    }

    public function handle_cron_tick() {
        $this->reschedule_cron_event(null, array(
            'reason'  => 'cron_tick',
            'outcome' => 'neutral',
        ));
    }

    public function handle_refresh_job_failure($job, $error_message = '', $result = null) {
        if (!is_array($job)) {
            return;
        }

        $origin = isset($job['origin']) ? $job['origin'] : 'cron';

        if ('cron' !== $origin) {
            return;
        }

        $context = array(
            'reason'        => 'job_failure',
            'outcome'       => 'failure',
            'job_type'      => isset($job['type']) ? $job['type'] : '',
            'profile_key'   => isset($job['profile_key']) ? $job['profile_key'] : '',
            'error_message' => $error_message,
        );

        $this->reschedule_cron_event(null, $context);
    }

    public function handle_refresh_job_success($job, $result = null) {
        if (!is_array($job)) {
            return;
        }

        $origin = isset($job['origin']) ? $job['origin'] : 'cron';

        if ('cron' !== $origin) {
            return;
        }

        $state = $this->get_cron_state();
        $failure_count = isset($state['consecutive_failures']) ? (int) $state['consecutive_failures'] : 0;

        if ($failure_count <= 0) {
            return;
        }

        $context = array(
            'reason'      => 'job_recovery',
            'outcome'     => 'success',
            'job_type'    => isset($job['type']) ? $job['type'] : '',
            'profile_key' => isset($job['profile_key']) ? $job['profile_key'] : '',
        );

        $this->reschedule_cron_event(null, $context);
    }

    private function calculate_cron_interval($base_interval, $failure_count, array $context = array()) {
        $base_interval = max(60, (int) $base_interval);

        if ($failure_count <= 0) {
            return $base_interval;
        }

        $max_interval = apply_filters('discord_bot_jlg_cron_backoff_max_interval', 3600, $base_interval, $failure_count, $context);
        $max_interval = max($base_interval, (int) $max_interval);

        $interval = $base_interval * pow(2, min($failure_count, 6));
        $interval = min($max_interval, max($base_interval, (int) $interval));

        return $interval;
    }

    private function get_cron_state() {
        $state = get_option($this->cron_state_option, array());

        if (!is_array($state)) {
            $state = array();
        }

        return $state;
    }

    private function persist_cron_state(array $state) {
        update_option($this->cron_state_option, $state, false);
    }

    public function activate() {
        add_option(DISCORD_BOT_JLG_OPTION_NAME, $this->default_options, '', false);

        if ($this->analytics instanceof Discord_Bot_JLG_Analytics) {
            $this->analytics->install();
        }

        if ($this->token_store instanceof Discord_Bot_JLG_Token_Store) {
            $this->token_store->install();
        }

        Discord_Bot_JLG_Capabilities::ensure_roles_have_capabilities();

        $this->reschedule_cron_event(null, array(
            'reason'  => 'activation',
            'outcome' => 'success',
        ));

        if ($this->job_queue instanceof Discord_Bot_JLG_Job_Queue) {
            $this->job_queue->dispatch_refresh_jobs(true);
        }
    }

    public function deactivate() {
        $this->api->clear_all_cached_data();
        wp_clear_scheduled_hook(DISCORD_BOT_JLG_CRON_HOOK);

        if (function_exists('as_unschedule_all_actions')) {
            as_unschedule_all_actions(Discord_Bot_JLG_Job_Queue::JOB_HOOK, array(), Discord_Bot_JLG_Job_Queue::ACTION_SCHEDULER_GROUP);
        } else {
            wp_clear_scheduled_hook(Discord_Bot_JLG_Job_Queue::JOB_HOOK);
        }

        delete_option($this->cron_state_option);
    }

    /**
     * Injecte les options par défaut lorsqu'aucune valeur n'est encore stockée.
     *
     * @return array
     */
    public function provide_default_options($default = array()) {
        $options = $this->default_options;

        if (empty($options['widget_title']) || 'Discord Server' === $options['widget_title']) {
            $options['widget_title'] = __('Discord Server', 'discord-bot-jlg');
        }

        return $options;
    }

    /**
     * S'assure que les options récupérées contiennent toujours les clés par défaut attendues.
     *
     * @param mixed $value Valeur brute renvoyée par WordPress.
     *
     * @return array
     */
    public function merge_options_with_defaults($value) {
        if (!is_array($value)) {
            $value = array();
        }

        $options = wp_parse_args($value, $this->default_options);

        if (empty($options['widget_title']) || 'Discord Server' === $options['widget_title']) {
            $options['widget_title'] = __('Discord Server', 'discord-bot-jlg');
        }

        return $options;
    }

    public function handle_settings_update($old_value, $value) {
        $old_value = is_array($old_value) ? $old_value : array();
        $value     = is_array($value) ? $value : array();

        $old_server_id = isset($old_value['server_id']) ? (string) $old_value['server_id'] : '';
        $new_server_id = isset($value['server_id']) ? (string) $value['server_id'] : '';
        $old_bot_token = isset($old_value['bot_token']) ? (string) $old_value['bot_token'] : '';
        $new_bot_token = isset($value['bot_token']) ? (string) $value['bot_token'] : '';
        $old_profiles  = isset($old_value['server_profiles']) ? $old_value['server_profiles'] : array();
        $new_profiles  = isset($value['server_profiles']) ? $value['server_profiles'] : array();
        $old_cache_duration = isset($old_value['cache_duration']) ? $old_value['cache_duration'] : DISCORD_BOT_JLG_DEFAULT_CACHE_DURATION;
        $new_cache_duration = isset($value['cache_duration']) ? $value['cache_duration'] : DISCORD_BOT_JLG_DEFAULT_CACHE_DURATION;

        $old_cache_duration = $this->normalize_cache_duration($old_cache_duration);
        $new_cache_duration = $this->normalize_cache_duration($new_cache_duration);

        $cache_duration_changed = ($old_cache_duration !== $new_cache_duration);

        $old_retention = isset($old_value['analytics_retention_days'])
            ? (int) $old_value['analytics_retention_days']
            : $this->api->get_analytics_retention_days($old_value);
        $new_retention = isset($value['analytics_retention_days'])
            ? (int) $value['analytics_retention_days']
            : $this->api->get_analytics_retention_days($value);

        if ($old_retention !== $new_retention) {
            $analytics = $this->api->get_analytics_service();

            if ($analytics instanceof Discord_Bot_JLG_Analytics && $new_retention > 0) {
                $analytics->purge_old_entries($new_retention);
            }
        }

        if ($old_server_id !== $new_server_id || $old_bot_token !== $new_bot_token) {
            $this->api->clear_all_cached_data();

            if ($cache_duration_changed) {
                $this->reschedule_cron_event($new_cache_duration, array(
                    'reason'  => 'settings_update',
                    'outcome' => 'success',
                ));
            }

            if ($this->job_queue instanceof Discord_Bot_JLG_Job_Queue) {
                $this->job_queue->dispatch_refresh_jobs(true);
            }

            return;
        }

        if ($old_profiles !== $new_profiles) {
            $this->api->clear_all_cached_data();

            if ($cache_duration_changed) {
                $this->reschedule_cron_event($new_cache_duration, array(
                    'reason'  => 'settings_update',
                    'outcome' => 'success',
                ));
            }

            if ($this->job_queue instanceof Discord_Bot_JLG_Job_Queue) {
                $this->job_queue->dispatch_refresh_jobs(true);
            }

            return;
        }

        if ($cache_duration_changed) {
            $this->reschedule_cron_event($new_cache_duration, array(
                'reason'  => 'settings_update',
                'outcome' => 'success',
            ));
        }

        $this->api->clear_cache();

        if ($this->job_queue instanceof Discord_Bot_JLG_Job_Queue) {
            $this->job_queue->dispatch_refresh_jobs(true);
        }
    }
}

new DiscordServerStats();
