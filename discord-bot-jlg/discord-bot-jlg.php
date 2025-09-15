<?php
/**
 * Plugin Name: Discord Bot - JLG
 * Plugin URI: https://yourwebsite.com/
 * Description: Affiche les statistiques de votre serveur Discord (membres en ligne et total)
 * Version: 1.0
 * Author: Jérôme Le Gousse
 * License: GPL v2 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/class-discord-widget.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/admin/class-discord-admin.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/admin/class-discord-ajax.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-discord-server-stats.php';

new DiscordServerStats( __FILE__ );
