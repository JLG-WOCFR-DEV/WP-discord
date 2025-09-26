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
            'widget_title'   => __('Discord Server', 'discord-bot-jlg'),
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

require_once DISCORD_BOT_JLG_PLUGIN_PATH . 'inc/class-discord-http.php';
require_once DISCORD_BOT_JLG_PLUGIN_PATH . 'inc/class-discord-api.php';
require_once DISCORD_BOT_JLG_PLUGIN_PATH . 'inc/class-discord-admin.php';
require_once DISCORD_BOT_JLG_PLUGIN_PATH . 'inc/class-discord-shortcode.php';
require_once DISCORD_BOT_JLG_PLUGIN_PATH . 'inc/class-discord-widget.php';

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

    public function __construct() {
        $this->default_options = discord_bot_jlg_get_default_options();

        $this->api       = new Discord_Bot_JLG_API(DISCORD_BOT_JLG_OPTION_NAME, DISCORD_BOT_JLG_CACHE_KEY, DISCORD_BOT_JLG_DEFAULT_CACHE_DURATION);
        $this->admin     = new Discord_Bot_JLG_Admin(DISCORD_BOT_JLG_OPTION_NAME, $this->api);
        $this->shortcode = new Discord_Bot_JLG_Shortcode(DISCORD_BOT_JLG_OPTION_NAME, $this->api);
        $this->widget    = new Discord_Bot_JLG_Widget();

        add_filter('default_option_' . DISCORD_BOT_JLG_OPTION_NAME, array($this, 'provide_default_options'));
        add_filter('option_' . DISCORD_BOT_JLG_OPTION_NAME, array($this, 'merge_options_with_defaults'));

        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        add_action('admin_menu', array($this->admin, 'add_admin_menu'));
        add_action('admin_init', array($this->admin, 'settings_init'));
        add_action('admin_enqueue_scripts', array($this->admin, 'enqueue_admin_styles'), 10, 1);

        add_shortcode('discord_stats', array($this->shortcode, 'render_shortcode'));

        add_action('widgets_init', array($this->widget, 'register_widget'));

        add_action('wp_ajax_refresh_discord_stats', array($this->api, 'ajax_refresh_stats'));
        add_action('wp_ajax_nopriv_refresh_discord_stats', array($this->api, 'ajax_refresh_stats'));
        add_action('update_option_' . DISCORD_BOT_JLG_OPTION_NAME, array($this, 'handle_settings_update'), 10, 2);
    }

    public function activate() {
        add_option(DISCORD_BOT_JLG_OPTION_NAME, $this->default_options, '', false);
    }

    public function deactivate() {
        $this->api->clear_all_cached_data();
    }

    /**
     * Injecte les options par défaut lorsqu'aucune valeur n'est encore stockée.
     *
     * @return array
     */
    public function provide_default_options($default = array()) {
        return $this->default_options;
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

        return wp_parse_args($value, $this->default_options);
    }

    public function handle_settings_update($old_value, $value) {
        $old_value = is_array($old_value) ? $old_value : array();
        $value     = is_array($value) ? $value : array();

        $old_server_id = isset($old_value['server_id']) ? (string) $old_value['server_id'] : '';
        $new_server_id = isset($value['server_id']) ? (string) $value['server_id'] : '';
        $old_bot_token = isset($old_value['bot_token']) ? (string) $old_value['bot_token'] : '';
        $new_bot_token = isset($value['bot_token']) ? (string) $value['bot_token'] : '';

        if ($old_server_id !== $new_server_id || $old_bot_token !== $new_bot_token) {
            $this->api->clear_all_cached_data();
            return;
        }

        $this->api->clear_cache();
    }
}

new DiscordServerStats();
