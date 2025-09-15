<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DiscordServerStats {

    private $option_name = 'discord_server_stats_options';
    private $cache_key = 'discord_server_stats_cache';
    private $default_cache_duration = 300;
    private $plugin_file;
    private $plugin_dir;
    private $plugin_url;
    private $admin;
    private $ajax;

    public function __construct( $plugin_file ) {
        $this->plugin_file = $plugin_file;
        $this->plugin_dir  = plugin_dir_path( $plugin_file );
        $this->plugin_url  = plugin_dir_url( $plugin_file );

        register_activation_hook( $plugin_file, array( $this, 'activate' ) );
        register_deactivation_hook( $plugin_file, array( $this, 'deactivate' ) );

        add_shortcode( 'discord_stats', array( $this, 'render_shortcode' ) );
        add_action( 'widgets_init', array( $this, 'register_widget' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );

        $this->ajax = new Discord_Server_Stats_Ajax( $this );

        if ( is_admin() ) {
            $this->admin = new Discord_Server_Stats_Admin( $this );
        }
    }

    public function activate() {
        add_option( $this->option_name, $this->get_default_options() );
    }

    public function deactivate() {
        $this->clear_cache();
    }

    public function clear_cache() {
        delete_transient( $this->cache_key );
    }

    public function get_option_name() {
        return $this->option_name;
    }

    public function get_plugin_url() {
        return $this->plugin_url;
    }

    public function get_plugin_dir() {
        return $this->plugin_dir;
    }

    public function get_default_options() {
        return array(
            'server_id'      => '',
            'bot_token'      => '',
            'demo_mode'      => false,
            'show_online'    => true,
            'show_total'     => true,
            'custom_css'     => '',
            'widget_title'   => 'Discord Server',
            'cache_duration' => $this->default_cache_duration,
        );
    }

    public function get_options() {
        $defaults = $this->get_default_options();
        $options  = get_option( $this->option_name, array() );

        if ( ! is_array( $options ) ) {
            $options = array();
        }

        $options = wp_parse_args( $options, $defaults );

        $options['demo_mode']   = ! empty( $options['demo_mode'] ) ? 1 : 0;
        $options['show_online'] = ! empty( $options['show_online'] ) ? 1 : 0;
        $options['show_total']  = ! empty( $options['show_total'] ) ? 1 : 0;

        $options['cache_duration'] = isset( $options['cache_duration'] ) ? (int) $options['cache_duration'] : $this->default_cache_duration;
        $options['cache_duration'] = max( 60, min( 3600, $options['cache_duration'] ) );

        return $options;
    }

    public function enqueue_styles() {
        wp_enqueue_style(
            'discord-bot-jlg',
            $this->plugin_url . 'assets/css/discord-bot-jlg.css',
            array(),
            '1.0'
        );

        wp_enqueue_style(
            'discord-bot-jlg-inline',
            $this->plugin_url . 'assets/css/discord-bot-jlg-inline.css',
            array( 'discord-bot-jlg' ),
            '1.0'
        );

        $options = $this->get_options();
        if ( ! empty( $options['custom_css'] ) ) {
            wp_add_inline_style( 'discord-bot-jlg-inline', $options['custom_css'] );
        }

        wp_register_script(
            'discord-bot-jlg-frontend',
            $this->plugin_url . 'assets/js/discord-bot-jlg.js',
            array(),
            '1.0',
            true
        );

        $locale = str_replace( '_', '-', get_locale() );

        wp_localize_script(
            'discord-bot-jlg-frontend',
            'discordBotJlg',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'refresh_discord_stats' ),
                'locale'  => $locale,
            )
        );

        wp_enqueue_script( 'discord-bot-jlg-frontend' );
    }

    public function register_widget() {
        register_widget( 'Discord_Stats_Widget' );
    }

    public function render_shortcode( $atts ) {
        $options = $this->get_options();

        $atts = shortcode_atts( array(
            'layout' => 'horizontal',
            'show_online' => $options['show_online'] ? 'true' : 'false',
            'show_total' => $options['show_total'] ? 'true' : 'false',
            'show_title' => 'false',
            'title' => $options['widget_title'],
            'theme' => 'discord',
            'animated' => 'true',
            'refresh' => 'false',
            'refresh_interval' => '60',
            'compact' => 'false',
            'align' => 'left',
            'width' => '',
            'class' => '',
            'icon_online' => '🟢',
            'icon_total' => '👥',
            'label_online' => 'En ligne',
            'label_total' => 'Membres',
            'hide_labels' => 'false',
            'hide_icons' => 'false',
            'border_radius' => '8',
            'gap' => '20',
            'padding' => '15',
            'demo' => 'false',
            'show_discord_icon' => 'false',
            'discord_icon_position' => 'left',
        ), $atts, 'discord_stats' );

        $show_online       = filter_var( $atts['show_online'], FILTER_VALIDATE_BOOLEAN );
        $show_total        = filter_var( $atts['show_total'], FILTER_VALIDATE_BOOLEAN );
        $show_title        = filter_var( $atts['show_title'], FILTER_VALIDATE_BOOLEAN );
        $animated          = filter_var( $atts['animated'], FILTER_VALIDATE_BOOLEAN );
        $refresh           = filter_var( $atts['refresh'], FILTER_VALIDATE_BOOLEAN );
        $compact           = filter_var( $atts['compact'], FILTER_VALIDATE_BOOLEAN );
        $hide_labels       = filter_var( $atts['hide_labels'], FILTER_VALIDATE_BOOLEAN );
        $hide_icons        = filter_var( $atts['hide_icons'], FILTER_VALIDATE_BOOLEAN );
        $force_demo        = filter_var( $atts['demo'], FILTER_VALIDATE_BOOLEAN );
        $show_discord_icon = filter_var( $atts['show_discord_icon'], FILTER_VALIDATE_BOOLEAN );

        if ( $force_demo ) {
            $stats = $this->get_demo_stats();
        } else {
            $stats = $this->get_discord_stats();
        }

        if ( ! $stats ) {
            return '<div class="discord-stats-error">Impossible de récupérer les stats Discord</div>';
        }

        $unique_id = 'discord-stats-' . wp_rand( 1000, 9999 );

        $container_classes = array(
            'discord-stats-container',
            'discord-layout-' . esc_attr( $atts['layout'] ),
            'discord-theme-' . esc_attr( $atts['theme'] ),
            'discord-align-' . esc_attr( $atts['align'] ),
        );

        if ( $compact ) {
            $container_classes[] = 'discord-compact';
        }

        if ( $animated ) {
            $container_classes[] = 'discord-animated';
        }

        if ( ! empty( $stats['is_demo'] ) ) {
            $container_classes[] = 'discord-demo-mode';
        }

        if ( $show_discord_icon ) {
            $container_classes[] = 'discord-with-logo';
            $container_classes[] = 'discord-logo-' . esc_attr( $atts['discord_icon_position'] );
        }

        if ( ! empty( $atts['class'] ) ) {
            $container_classes[] = esc_attr( $atts['class'] );
        }

        $style_declarations = array(
            '--discord-gap: ' . intval( $atts['gap'] ) . 'px',
            '--discord-padding: ' . intval( $atts['padding'] ) . 'px',
            '--discord-radius: ' . intval( $atts['border_radius'] ) . 'px',
        );

        if ( ! empty( $atts['width'] ) ) {
            $style_declarations[] = 'width: ' . sanitize_text_field( $atts['width'] );
        }

        $attributes = array(
            'id="' . esc_attr( $unique_id ) . '"',
            'class="' . esc_attr( implode( ' ', $container_classes ) ) . '"',
            'data-demo="' . ( ! empty( $stats['is_demo'] ) ? 'true' : 'false' ) . '"',
        );

        if ( ! empty( $style_declarations ) ) {
            $attributes[] = 'style="' . esc_attr( implode( '; ', $style_declarations ) ) . '"';
        }

        $refresh_interval = 0;
        if ( $refresh && empty( $stats['is_demo'] ) ) {
            $refresh_interval = max( 0, intval( $atts['refresh_interval'] ) );
        }

        if ( $refresh_interval > 0 ) {
            $attributes[] = 'data-refresh="' . esc_attr( $refresh_interval ) . '"';
        }

        $discord_svg = '<svg class="discord-logo-svg" viewBox="0 0 127.14 96.36" xmlns="http://www.w3.org/2000/svg"><path d="M107.7,8.07A105.15,105.15,0,0,0,81.47,0a72.06,72.06,0,0,0-3.36,6.83A97.68,97.68,0,0,0,49,6.83,72.37,72.37,0,0,0,45.64,0,105.89,105.89,0,0,0,19.39,8.09C2.79,32.65-1.71,56.6.54,80.21h0A105.73,105.73,0,0,0,32.71,96.36,77.7,77.7,0,0,0,39.6,85.25a68.42,68.42,0,0,1-10.85-5.18c.91-.66,1.8-1.34,2.66-2a75.57,75.57,0,0,0,64.32,0c.87.71,1.76,1.39,2.66,2a68.68,68.68,0,0,1-10.87,5.19,77,77,0,0,0,6.89,11.1A105.25,105.25,0,0,0,126.6,80.22h0C129.24,52.84,122.09,29.11,107.7,8.07ZM42.45,65.69C36.18,65.69,31,60,31,53s5-12.74,11.43-12.74S54,46,53.89,53,48.84,65.69,42.45,65.69Zm42.24,0C78.41,65.69,73.25,60,73.25,53s5-12.74,11.44-12.74S96.23,46,96.12,53,91.08,65.69,84.69,65.69Z"/></svg>';

        ob_start();
        ?>
        <div <?php echo implode( ' ', $attributes ); ?>>

            <?php if ( ! empty( $stats['is_demo'] ) ) : ?>
            <div class="discord-demo-badge">Mode Démo</div>
            <?php endif; ?>

            <?php if ( $show_title ) : ?>
            <div class="discord-stats-title"><?php echo esc_html( $atts['title'] ); ?></div>
            <?php endif; ?>

            <div class="discord-stats-main">
                <?php if ( $show_discord_icon && $atts['discord_icon_position'] === 'left' ) : ?>
                <div class="discord-logo-container">
                    <?php echo $discord_svg; ?>
                </div>
                <?php endif; ?>

                <?php if ( $show_discord_icon && $atts['discord_icon_position'] === 'top' ) : ?>
                <div class="discord-logo-container discord-logo-top">
                    <?php echo $discord_svg; ?>
                </div>
                <?php endif; ?>

                <div class="discord-stats-wrapper">
                    <?php if ( $show_online ) : ?>
                    <div class="discord-stat discord-online" data-value="<?php echo $stats['online']; ?>">
                        <?php if ( ! $hide_icons ) : ?>
                        <span class="discord-icon"><?php echo esc_html( $atts['icon_online'] ); ?></span>
                        <?php endif; ?>
                        <span class="discord-number"><?php echo esc_html( number_format_i18n( (int) $stats['online'] ) ); ?></span>
                        <?php if ( ! $hide_labels ) : ?>
                        <span class="discord-label"><?php echo esc_html( $atts['label_online'] ); ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <?php if ( $show_total ) : ?>
                    <div class="discord-stat discord-total" data-value="<?php echo $stats['total']; ?>">
                        <?php if ( ! $hide_icons ) : ?>
                        <span class="discord-icon"><?php echo esc_html( $atts['icon_total'] ); ?></span>
                        <?php endif; ?>
                        <span class="discord-number"><?php echo esc_html( number_format_i18n( (int) $stats['total'] ) ); ?></span>
                        <?php if ( ! $hide_labels ) : ?>
                        <span class="discord-label"><?php echo esc_html( $atts['label_total'] ); ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if ( $show_discord_icon && $atts['discord_icon_position'] === 'right' ) : ?>
                <div class="discord-logo-container">
                    <?php echo $discord_svg; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php
        return ob_get_clean();
    }

    public function get_discord_stats( $bypass_cache = false ) {
        $options = $this->get_options();

        if ( ! empty( $options['demo_mode'] ) ) {
            return $this->get_demo_stats();
        }

        $cached_stats = false;
        if ( ! $bypass_cache ) {
            $cached_stats = get_transient( $this->cache_key );
        }

        if ( false !== $cached_stats ) {
            return $cached_stats;
        }

        $stats = $this->get_discord_stats_via_widget( $options );

        if ( ! $stats ) {
            $stats = $this->get_discord_stats_via_bot( $options );
        }

        if ( ! $stats ) {
            return $this->get_demo_stats();
        }

        if ( empty( $stats['is_demo'] ) ) {
            set_transient( $this->cache_key, $stats, $options['cache_duration'] );
        }

        return $stats;
    }

    private function get_discord_stats_via_widget( array $options ) {
        if ( empty( $options['server_id'] ) ) {
            return false;
        }

        $widget_url = 'https://discord.com/api/guilds/' . $options['server_id'] . '/widget.json';

        $response = wp_remote_get( $widget_url, array(
            'timeout' => 10,
            'headers' => array(
                'User-Agent' => 'WordPress Discord Stats Plugin',
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( 200 !== $code ) {
            return false;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( ! $data || ! isset( $data['presence_count'], $data['member_count'], $data['name'] ) ) {
            return false;
        }

        return array(
            'online'      => (int) $data['presence_count'],
            'total'       => (int) $data['member_count'],
            'server_name' => $data['name'],
        );
    }

    private function get_discord_stats_via_bot( array $options ) {
        if ( empty( $options['server_id'] ) || empty( $options['bot_token'] ) ) {
            return false;
        }

        $api_url = 'https://discord.com/api/v10/guilds/' . $options['server_id'] . '?with_counts=true';

        $response = wp_remote_get( $api_url, array(
            'timeout' => 10,
            'headers' => array(
                'Authorization' => 'Bot ' . $options['bot_token'],
                'User-Agent'    => 'WordPress Discord Stats Plugin',
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( 200 !== $code ) {
            return false;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( ! $data || ! isset( $data['approximate_presence_count'], $data['approximate_member_count'], $data['name'] ) ) {
            return false;
        }

        return array(
            'online'      => (int) $data['approximate_presence_count'],
            'total'       => (int) $data['approximate_member_count'],
            'server_name' => $data['name'],
        );
    }

    private function get_demo_stats() {
        $base_online = 42;
        $base_total  = 256;

        $hour      = (int) date( 'H' );
        $variation = sin( $hour * 0.26 ) * 10;

        return array(
            'online'    => (int) round( $base_online + $variation ),
            'total'     => $base_total,
            'server_name' => 'Serveur Démo',
            'is_demo'   => true,
        );
    }
}
