<?php
/**
 * Plugin Name: Discord Bot - JLG
 * Plugin URI: https://yourwebsite.com/
 * Description: Affiche les statistiques de votre serveur Discord (membres en ligne et total)
 * Version: 1.0
 * Author: Jérôme Le Gousse
 * License: GPL v2 or later
 */

if (!defined('ABSPATH')) {
    exit;
}

define('DISCORD_BOT_JLG_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('DISCORD_BOT_JLG_PLUGIN_URL', plugin_dir_url(__FILE__));
define('DISCORD_BOT_JLG_OPTION_NAME', 'discord_server_stats_options');
define('DISCORD_BOT_JLG_CACHE_KEY', 'discord_server_stats_cache');
define('DISCORD_BOT_JLG_DEFAULT_CACHE_DURATION', 300);

/**
 * Supprime les données enregistrées par le plugin lors de la désinstallation.
 *
 * @return void
 */
function discord_bot_jlg_uninstall() {
    delete_option(DISCORD_BOT_JLG_OPTION_NAME);
    delete_transient(DISCORD_BOT_JLG_CACHE_KEY);
}

register_uninstall_hook(__FILE__, 'discord_bot_jlg_uninstall');

require_once DISCORD_BOT_JLG_PLUGIN_PATH . 'inc/class-discord-api.php';
require_once DISCORD_BOT_JLG_PLUGIN_PATH . 'inc/class-discord-admin.php';
require_once DISCORD_BOT_JLG_PLUGIN_PATH . 'inc/class-discord-shortcode.php';
require_once DISCORD_BOT_JLG_PLUGIN_PATH . 'inc/class-discord-widget.php';

class DiscordServerStats {

    private $default_options = array(
        'server_id'      => '',
        'bot_token'      => '',
        'demo_mode'      => false,
        'show_online'    => true,
        'show_total'     => true,
        'custom_css'     => '',
        'widget_title'   => 'Discord Server',
        'cache_duration' => DISCORD_BOT_JLG_DEFAULT_CACHE_DURATION,
    );

    private $api;
    private $admin;
    private $shortcode;
    private $widget;

    public function __construct() {
        $this->api       = new Discord_Bot_JLG_API(DISCORD_BOT_JLG_OPTION_NAME, DISCORD_BOT_JLG_CACHE_KEY, DISCORD_BOT_JLG_DEFAULT_CACHE_DURATION);
        $this->admin     = new Discord_Bot_JLG_Admin(DISCORD_BOT_JLG_OPTION_NAME, $this->api);
        $this->shortcode = new Discord_Bot_JLG_Shortcode(DISCORD_BOT_JLG_OPTION_NAME, $this->api);
        $this->widget    = new Discord_Bot_JLG_Widget();

        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        add_action('admin_menu', array($this->admin, 'add_admin_menu'));
        add_action('admin_init', array($this->admin, 'settings_init'));
        add_action('admin_enqueue_scripts', array($this->admin, 'enqueue_admin_styles'), 10, 1);

        add_shortcode('discord_stats', array($this->shortcode, 'render_shortcode'));

        add_action('widgets_init', array($this->widget, 'register_widget'));

        add_action('wp_ajax_refresh_discord_stats', array($this->api, 'ajax_refresh_stats'));
        add_action('wp_ajax_nopriv_refresh_discord_stats', array($this->api, 'ajax_refresh_stats'));
    }

    public function activate() {
        add_option(DISCORD_BOT_JLG_OPTION_NAME, $this->default_options);
    }

    public function deactivate() {
        $this->api->clear_cache();
    }
}

new DiscordServerStats();
