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

if (!function_exists('discord_bot_jlg_get_cron_interval')) {
    /**
     * Renvoie l'intervalle utilisé pour la planification du rafraîchissement automatique.
     *
     * @return int
     */
    function discord_bot_jlg_get_cron_interval() {
        $default_interval = (int) DISCORD_BOT_JLG_DEFAULT_CACHE_DURATION;

        if ($default_interval <= 0) {
            $default_interval = 300;
        }

        $options = get_option(DISCORD_BOT_JLG_OPTION_NAME, array());

        if (!is_array($options)) {
            $options = array();
        }

        $interval = isset($options['cache_duration']) ? (int) $options['cache_duration'] : $default_interval;

        if ($interval < 60) {
            $interval = 60;
        } elseif ($interval > 3600) {
            $interval = 3600;
        }

        $interval = (int) apply_filters('discord_bot_jlg_cron_interval', $interval);

        if ($interval < 60) {
            $interval = 60;
        } elseif ($interval > 3600) {
            $interval = 3600;
        }

        return $interval;
    }
}

if (!function_exists('discord_bot_jlg_register_cron_schedule')) {
    /**
     * Déclare un intervalle de cron dédié au rafraîchissement du cache du plugin.
     *
     * @param array $schedules Listes des plannings cron disponibles.
     *
     * @return array
     */
    function discord_bot_jlg_register_cron_schedule($schedules) {
        if (!is_array($schedules)) {
            $schedules = array();
        }

        $schedules['discord_bot_jlg_refresh'] = array(
            'interval' => discord_bot_jlg_get_cron_interval(),
            'display'  => __('Discord Bot JLG cache refresh', 'discord-bot-jlg'),
        );

        return $schedules;
    }
}

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
            'demo_mode'      => false,
            'show_online'    => true,
            'show_total'     => true,
            'custom_css'     => '',
            'widget_title'   => 'Discord Server',
            'cache_duration' => DISCORD_BOT_JLG_DEFAULT_CACHE_DURATION,
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

        require_once DISCORD_BOT_JLG_PLUGIN_PATH . 'inc/class-discord-api.php';
    } elseif (!class_exists('Discord_Bot_JLG_Http_Client')) {
        require_once DISCORD_BOT_JLG_PLUGIN_PATH . 'inc/class-discord-http.php';
    }

    if (class_exists('Discord_Bot_JLG_API')) {
        $api = new Discord_Bot_JLG_API(
            DISCORD_BOT_JLG_OPTION_NAME,
            DISCORD_BOT_JLG_CACHE_KEY,
            DISCORD_BOT_JLG_DEFAULT_CACHE_DURATION
        );

        $api->clear_cache(true);
    }
}

register_uninstall_hook(__FILE__, 'discord_bot_jlg_uninstall');

require_once DISCORD_BOT_JLG_PLUGIN_PATH . 'inc/helpers.php';
require_once DISCORD_BOT_JLG_PLUGIN_PATH . 'inc/class-discord-http.php';
require_once DISCORD_BOT_JLG_PLUGIN_PATH . 'inc/class-discord-api.php';
require_once DISCORD_BOT_JLG_PLUGIN_PATH . 'inc/class-discord-admin.php';
require_once DISCORD_BOT_JLG_PLUGIN_PATH . 'inc/class-discord-shortcode.php';
require_once DISCORD_BOT_JLG_PLUGIN_PATH . 'inc/class-discord-widget.php';
require_once DISCORD_BOT_JLG_PLUGIN_PATH . 'inc/class-discord-site-health.php';

if (defined('WP_CLI') && WP_CLI) {
    require_once DISCORD_BOT_JLG_PLUGIN_PATH . 'inc/class-discord-cli.php';

    WP_CLI::add_command(
        'discord-bot',
        new Discord_Bot_JLG_CLI(
            new Discord_Bot_JLG_API(
                DISCORD_BOT_JLG_OPTION_NAME,
                DISCORD_BOT_JLG_CACHE_KEY,
                DISCORD_BOT_JLG_DEFAULT_CACHE_DURATION
            )
        )
    );
}

function discord_bot_jlg_load_textdomain() {
    load_plugin_textdomain('discord-bot-jlg', false, dirname(plugin_basename(__FILE__)) . '/languages');
}

add_action('plugins_loaded', 'discord_bot_jlg_load_textdomain');

class DiscordServerStats {

    private $default_options;

    private $api;
    private $admin;
    private $shortcode;
    private $widget;
    private $site_health;

    public function __construct() {
        $this->default_options = discord_bot_jlg_get_default_options();

        $this->api       = new Discord_Bot_JLG_API(DISCORD_BOT_JLG_OPTION_NAME, DISCORD_BOT_JLG_CACHE_KEY, DISCORD_BOT_JLG_DEFAULT_CACHE_DURATION);
        $this->admin     = new Discord_Bot_JLG_Admin(DISCORD_BOT_JLG_OPTION_NAME, $this->api);
        $this->shortcode = new Discord_Bot_JLG_Shortcode(DISCORD_BOT_JLG_OPTION_NAME, $this->api);
        $this->widget    = new Discord_Bot_JLG_Widget();
        $this->site_health = new Discord_Bot_JLG_Site_Health($this->api);

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

        add_action(DISCORD_BOT_JLG_CRON_HOOK, array($this->api, 'refresh_cache_via_cron'));
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
    private function reschedule_cron_event($interval = null) {
        if (null === $interval) {
            $interval = discord_bot_jlg_get_cron_interval();
        } else {
            $interval = $this->normalize_cache_duration($interval);
        }

        wp_clear_scheduled_hook(DISCORD_BOT_JLG_CRON_HOOK);

        wp_schedule_event(time() + $interval, 'discord_bot_jlg_refresh', DISCORD_BOT_JLG_CRON_HOOK);
    }

    public function activate() {
        add_option(DISCORD_BOT_JLG_OPTION_NAME, $this->default_options, '', false);

        $this->reschedule_cron_event();
    }

    public function deactivate() {
        $this->api->clear_all_cached_data();
        wp_clear_scheduled_hook(DISCORD_BOT_JLG_CRON_HOOK);
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
        $old_cache_duration = isset($old_value['cache_duration']) ? $old_value['cache_duration'] : DISCORD_BOT_JLG_DEFAULT_CACHE_DURATION;
        $new_cache_duration = isset($value['cache_duration']) ? $value['cache_duration'] : DISCORD_BOT_JLG_DEFAULT_CACHE_DURATION;

        $old_cache_duration = $this->normalize_cache_duration($old_cache_duration);
        $new_cache_duration = $this->normalize_cache_duration($new_cache_duration);

        $cache_duration_changed = ($old_cache_duration !== $new_cache_duration);

        if ($old_server_id !== $new_server_id || $old_bot_token !== $new_bot_token) {
            $this->api->clear_all_cached_data();

            if ($cache_duration_changed) {
                $this->reschedule_cron_event($new_cache_duration);
            }

            return;
        }

        if ($cache_duration_changed) {
            $this->reschedule_cron_event($new_cache_duration);
        }

        $this->api->clear_cache();
    }
}

new DiscordServerStats();
