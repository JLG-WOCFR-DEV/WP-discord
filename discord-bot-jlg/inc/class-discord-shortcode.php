<?php

if (!defined('ABSPATH')) {
    exit;
}

class Discord_Bot_JLG_Shortcode {

    private $option_name;
    private $api;

    private static $assets_registered   = false;
    private static $inline_css_added    = false;
    private static $footer_hook_added   = false;

    public function __construct($option_name, Discord_Bot_JLG_API $api) {
        $this->option_name = $option_name;
        $this->api         = $api;
    }

    public function render_shortcode($atts) {
        $options = get_option($this->option_name);
        if (!is_array($options)) {
            $options = array();
        }

        $atts = shortcode_atts(
            array(
                'layout'               => 'horizontal',
                'show_online'          => !empty($options['show_online']),
                'show_total'           => !empty($options['show_total']),
                'show_title'           => false,
                'title'                => isset($options['widget_title']) ? $options['widget_title'] : '',
                'theme'                => 'discord',
                'animated'             => true,
                'refresh'              => false,
                'refresh_interval'     => '60',
                'compact'              => false,
                'align'                => 'left',
                'width'                => '',
                'class'                => '',
                'icon_online'          => '🟢',
                'icon_total'           => '👥',
                'label_online'         => 'En ligne',
                'label_total'          => 'Membres',
                'hide_labels'          => false,
                'hide_icons'           => false,
                'border_radius'        => '8',
                'gap'                  => '20',
                'padding'              => '15',
                'demo'                 => false,
                'show_discord_icon'    => false,
                'discord_icon_position'=> 'left',
            ),
            $atts,
            'discord_stats'
        );

        $show_online        = filter_var($atts['show_online'], FILTER_VALIDATE_BOOLEAN);
        $show_total         = filter_var($atts['show_total'], FILTER_VALIDATE_BOOLEAN);
        $show_title         = filter_var($atts['show_title'], FILTER_VALIDATE_BOOLEAN);
        $animated           = filter_var($atts['animated'], FILTER_VALIDATE_BOOLEAN);
        $refresh            = filter_var($atts['refresh'], FILTER_VALIDATE_BOOLEAN);
        $compact            = filter_var($atts['compact'], FILTER_VALIDATE_BOOLEAN);
        $hide_labels        = filter_var($atts['hide_labels'], FILTER_VALIDATE_BOOLEAN);
        $hide_icons         = filter_var($atts['hide_icons'], FILTER_VALIDATE_BOOLEAN);
        $force_demo         = filter_var($atts['demo'], FILTER_VALIDATE_BOOLEAN);
        $show_discord_icon  = filter_var($atts['show_discord_icon'], FILTER_VALIDATE_BOOLEAN);

        if ($force_demo) {
            $stats = $this->api->get_demo_stats();
        } else {
            $stats = $this->api->get_stats();
        }

        if (!is_array($stats)) {
            return '<div class="discord-stats-error">Impossible de récupérer les stats Discord</div>';
        }

        $this->enqueue_assets($options);

        $unique_id = 'discord-stats-' . wp_rand(1000, 9999);

        $container_classes = array(
            'discord-stats-container',
            'discord-layout-' . esc_attr($atts['layout']),
            'discord-theme-' . esc_attr($atts['theme']),
            'discord-align-' . esc_attr($atts['align']),
        );

        if ($compact) {
            $container_classes[] = 'discord-compact';
        }

        if ($animated) {
            $container_classes[] = 'discord-animated';
        }

        if (!empty($stats['is_demo'])) {
            $container_classes[] = 'discord-demo-mode';
        }

        if ($show_discord_icon) {
            $container_classes[] = 'discord-with-logo';
            $container_classes[] = 'discord-logo-' . esc_attr($atts['discord_icon_position']);
        }

        if (!empty($atts['class'])) {
            $container_classes[] = esc_attr($atts['class']);
        }

        $style_declarations = array(
            '--discord-gap: ' . intval($atts['gap']) . 'px',
            '--discord-padding: ' . intval($atts['padding']) . 'px',
            '--discord-radius: ' . intval($atts['border_radius']) . 'px',
        );

        if (!empty($atts['width'])) {
            $style_declarations[] = 'width: ' . sanitize_text_field($atts['width']);
        }

        $attributes = array(
            sprintf('id="%s"', esc_attr($unique_id)),
            sprintf('class="%s"', esc_attr(implode(' ', $container_classes))),
            sprintf('data-demo="%s"', esc_attr(!empty($stats['is_demo']) ? 'true' : 'false')),
        );

        if (!empty($style_declarations)) {
            $attributes[] = sprintf('style="%s"', esc_attr(implode('; ', $style_declarations)));
        }

        $refresh_interval = 0;
        $min_refresh_interval = defined('Discord_Bot_JLG_API::MIN_PUBLIC_REFRESH_INTERVAL')
            ? Discord_Bot_JLG_API::MIN_PUBLIC_REFRESH_INTERVAL
            : 10;

        if ($refresh && empty($stats['is_demo'])) {
            $refresh_interval = max($min_refresh_interval, intval($atts['refresh_interval']));
        }

        if ($refresh_interval > 0) {
            $attributes[] = sprintf('data-refresh="%s"', esc_attr($refresh_interval));
        }

        $discord_svg = '<svg class="discord-logo-svg" viewBox="0 0 127.14 96.36" xmlns="http://www.w3.org/2000/svg"><path d="M107.7,8.07A105.15,105.15,0,0,0,81.47,0a72.06,72.06,0,0,0-3.36,6.83A97.68,97.68,0,0,0,49,6.83,72.37,72.37,0,0,0,45.64,0,105.89,105.89,0,0,0,19.39,8.09C2.79,32.65-1.71,56.6.54,80.21h0A105.73,105.73,0,0,0,32.71,96.36,77.7,77.7,0,0,0,39.6,85.25a68.42,68.42,0,0,1-10.85-5.18c.91-.66,1.8-1.34,2.66-2a75.57,75.57,0,0,0,64.32,0c.87.71,1.76,1.39,2.66,2a68.68,68.68,0,0,1-10.87,5.19,77,77,0,0,0,6.89,11.1A105.25,105.25,0,0,0,126.6,80.22h0C129.24,52.84,122.09,29.11,107.7,8.07ZM42.45,65.69C36.18,65.69,31,60,31,53s5-12.74,11.43-12.74S54,46,53.89,53,48.84,65.69,42.45,65.69Zm42.24,0C78.41,65.69,73.25,60,73.25,53s5-12.74,11.44-12.74S96.23,46,96.12,53,91.08,65.69,84.69,65.69Z"/></svg>';

        ob_start();
        ?>
        <div <?php echo implode(' ', $attributes); ?>>

            <?php if (!empty($stats['is_demo'])): ?>
            <div class="discord-demo-badge">Mode Démo</div>
            <?php endif; ?>

            <?php if ($show_title): ?>
            <div class="discord-stats-title"><?php echo esc_html($atts['title']); ?></div>
            <?php endif; ?>

            <div class="discord-stats-main">
                <?php if ($show_discord_icon && $atts['discord_icon_position'] === 'left'): ?>
                <div class="discord-logo-container">
                    <?php echo $discord_svg; ?>
                </div>
                <?php endif; ?>

                <?php if ($show_discord_icon && $atts['discord_icon_position'] === 'top'): ?>
                <div class="discord-logo-container discord-logo-top">
                    <?php echo $discord_svg; ?>
                </div>
                <?php endif; ?>

                <div class="discord-stats-wrapper">
                    <?php if ($show_online) : ?>
                    <div class="discord-stat discord-online" data-value="<?php echo esc_attr((int) $stats['online']); ?>">
                        <?php if (!$hide_icons): ?>
                        <span class="discord-icon"><?php echo esc_html($atts['icon_online']); ?></span>
                        <?php endif; ?>
                        <span class="discord-number"><?php echo esc_html(number_format_i18n((int) $stats['online'])); ?></span>
                        <?php if (!$hide_labels): ?>
                        <span class="discord-label"><?php echo esc_html($atts['label_online']); ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($show_total) : ?>
                    <div class="discord-stat discord-total" data-value="<?php echo esc_attr((int) $stats['total']); ?>">
                        <?php if (!$hide_icons): ?>
                        <span class="discord-icon"><?php echo esc_html($atts['icon_total']); ?></span>
                        <?php endif; ?>
                        <span class="discord-number"><?php echo esc_html(number_format_i18n((int) $stats['total'])); ?></span>
                        <?php if (!$hide_labels): ?>
                        <span class="discord-label"><?php echo esc_html($atts['label_total']); ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if ($show_discord_icon && $atts['discord_icon_position'] === 'right'): ?>
                <div class="discord-logo-container">
                    <?php echo $discord_svg; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php
        return ob_get_clean();
    }

    private function enqueue_assets($options) {
        $this->register_assets();

        if (!self::$inline_css_added && is_array($options) && !empty($options['custom_css'])) {
            wp_add_inline_style('discord-bot-jlg-inline', $options['custom_css']);
            self::$inline_css_added = true;
        }

        wp_enqueue_style('discord-bot-jlg');
        wp_enqueue_style('discord-bot-jlg-inline');
        wp_enqueue_script('discord-bot-jlg-frontend');

        if (!self::$footer_hook_added) {
            add_action('wp_footer', array($this, 'print_late_styles'), 1);
            self::$footer_hook_added = true;
        }
    }

    private function register_assets() {
        if (self::$assets_registered) {
            return;
        }

        wp_register_style(
            'discord-bot-jlg',
            DISCORD_BOT_JLG_PLUGIN_URL . 'assets/css/discord-bot-jlg.css',
            array(),
            '1.0'
        );

        wp_register_style(
            'discord-bot-jlg-inline',
            DISCORD_BOT_JLG_PLUGIN_URL . 'assets/css/discord-bot-jlg-inline.css',
            array('discord-bot-jlg'),
            '1.0'
        );

        wp_register_script(
            'discord-bot-jlg-frontend',
            DISCORD_BOT_JLG_PLUGIN_URL . 'assets/js/discord-bot-jlg.js',
            array(),
            '1.0',
            true
        );

        $locale = str_replace('_', '-', get_locale());

        wp_localize_script(
            'discord-bot-jlg-frontend',
            'discordBotJlg',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'action'  => 'refresh_discord_stats',
                'nonce'   => wp_create_nonce('refresh_discord_stats'),
                'locale'  => $locale,
                'minRefreshInterval' => defined('Discord_Bot_JLG_API::MIN_PUBLIC_REFRESH_INTERVAL')
                    ? Discord_Bot_JLG_API::MIN_PUBLIC_REFRESH_INTERVAL
                    : 10,
            )
        );

        self::$assets_registered = true;
    }

    public function print_late_styles() {
        if (wp_style_is('discord-bot-jlg-inline', 'enqueued')) {
            wp_print_styles('discord-bot-jlg-inline');
        } elseif (wp_style_is('discord-bot-jlg', 'enqueued')) {
            wp_print_styles('discord-bot-jlg');
        }
    }
}
