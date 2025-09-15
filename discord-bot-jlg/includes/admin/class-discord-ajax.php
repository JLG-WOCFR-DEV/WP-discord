<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Discord_Server_Stats_Ajax {

    /**
     * @var DiscordServerStats
     */
    private $plugin;

    public function __construct( DiscordServerStats $plugin ) {
        $this->plugin = $plugin;

        add_action( 'wp_ajax_refresh_discord_stats', array( $this, 'refresh_stats' ) );
        add_action( 'wp_ajax_nopriv_refresh_discord_stats', array( $this, 'refresh_stats' ) );
    }

    public function refresh_stats() {
        check_ajax_referer( 'refresh_discord_stats' );

        $options = $this->plugin->get_options();

        if ( ! empty( $options['demo_mode'] ) ) {
            wp_send_json_error( 'Mode démo actif' );
        }

        $this->plugin->clear_cache();
        $stats = $this->plugin->get_discord_stats( true );

        if ( $stats && empty( $stats['is_demo'] ) ) {
            wp_send_json_success( $stats );
        }

        wp_send_json_error( 'Impossible de récupérer les stats' );
    }
}
