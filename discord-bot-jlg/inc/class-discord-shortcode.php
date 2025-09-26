<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Implémente le shortcode `[discord_stats]` et gère les assets associés côté public.
 */
class Discord_Bot_JLG_Shortcode {

    private $option_name;
    private $api;

    private static $assets_registered   = false;
    private static $inline_css_added    = false;
    private static $footer_hook_added   = false;

    /**
     * Conserve la clé d'option et le service API utilisés lors du rendu du shortcode.
     *
     * @param string              $option_name Nom de l'option stockant les réglages d'affichage.
     * @param Discord_Bot_JLG_API $api         Service fournissant les statistiques Discord.
     *
     * @return void
     */
    public function __construct($option_name, Discord_Bot_JLG_API $api) {
        $this->option_name = $option_name;
        $this->api         = $api;
    }

    /**
     * Génère l'affichage du shortcode avec les options fusionnées entre réglages et attributs.
     *
     * @param array|string $atts Attributs reçus du shortcode WordPress.
     *
     * @return string HTML du composant de statistiques prêt à être inséré dans la page.
     */
    public function render_shortcode($atts) {
        $options = $this->api->get_plugin_options();

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
                'label_online'         => __('En ligne', 'discord-bot-jlg'),
                'label_total'          => __('Membres', 'discord-bot-jlg'),
                'hide_labels'          => false,
                'hide_icons'           => false,
                'border_radius'        => '8',
                'gap'                  => '20',
                'padding'              => '15',
                'demo'                 => false,
                'show_discord_icon'    => false,
                'discord_icon_position'=> 'left',
                'show_server_name'     => false,
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
        $show_server_name   = filter_var($atts['show_server_name'], FILTER_VALIDATE_BOOLEAN);

        if ($force_demo) {
            $stats = $this->api->get_demo_stats();
        } else {
            $stats = $this->api->get_stats();
        }

        if (!is_array($stats)) {
            return sprintf(
                '<div class="discord-stats-error">%s</div>',
                esc_html__('Impossible de récupérer les stats Discord', 'discord-bot-jlg')
            );
        }

        if (function_exists('wp_unique_id')) {
            $unique_id = wp_unique_id('discord-stats-');
        } else {
            $unique_id = str_replace('.', '-', uniqid('discord-stats-', true));
        }

        $is_demo          = !empty($stats['is_demo']);
        $is_fallback_demo = !empty($stats['fallback_demo']);
        $is_forced_demo   = $is_demo && !$is_fallback_demo;

        $has_total            = !empty($stats['has_total']) && isset($stats['total']) && null !== $stats['total'];
        $total_is_approximate = !empty($stats['total_is_approximate']);
        $is_stale             = !empty($stats['stale']);
        $last_updated         = isset($stats['last_updated']) ? (int) $stats['last_updated'] : 0;
        $server_name          = isset($stats['server_name']) ? trim((string) $stats['server_name']) : '';

        $container_classes = array('discord-stats-container');

        $layout_class = strtolower(sanitize_html_class($atts['layout'], 'horizontal'));
        if (!empty($layout_class)) {
            $container_classes[] = 'discord-layout-' . $layout_class;
        }

        $theme_class = strtolower(sanitize_html_class($atts['theme'], 'discord'));
        if (!empty($theme_class)) {
            $container_classes[] = 'discord-theme-' . $theme_class;
        }

        $align_class = strtolower(sanitize_html_class($atts['align'], 'left'));
        if (!empty($align_class)) {
            $container_classes[] = 'discord-align-' . $align_class;
        }

        if ($compact) {
            $container_classes[] = 'discord-compact';
        }

        if ($animated) {
            $container_classes[] = 'discord-animated';
        }

        if ($is_demo) {
            $container_classes[] = 'discord-demo-mode';
        }

        $logo_position_class = '';
        if ($show_discord_icon) {
            $container_classes[] = 'discord-with-logo';

            $logo_position_class = strtolower(sanitize_html_class($atts['discord_icon_position'], 'left'));
            if (!empty($logo_position_class)) {
                $container_classes[] = 'discord-logo-' . $logo_position_class;
            }
        }

        if (!$has_total) {
            $container_classes[] = 'discord-total-missing';
        }

        if (!empty($atts['class'])) {
            $custom_classes = preg_split('/\s+/', $atts['class'], -1, PREG_SPLIT_NO_EMPTY);

            if (!empty($custom_classes)) {
                $custom_classes = array_filter(array_map('sanitize_html_class', $custom_classes));

                if (!empty($custom_classes)) {
                    $container_classes = array_merge($container_classes, $custom_classes);
                }
            }
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
            sprintf('data-demo="%s"', esc_attr($is_forced_demo ? 'true' : 'false')),
            sprintf('data-fallback-demo="%s"', esc_attr($is_fallback_demo ? 'true' : 'false')),
            sprintf('data-stale="%s"', esc_attr($is_stale ? 'true' : 'false')),
        );

        if ($is_stale && $last_updated > 0) {
            $attributes[] = sprintf('data-last-updated="%s"', esc_attr($last_updated));
        }

        if (!empty($style_declarations)) {
            $attributes[] = sprintf('style="%s"', esc_attr(implode('; ', $style_declarations)));
        }

        if ($show_server_name) {
            $attributes[] = 'data-show-server-name="true"';

            if ('' !== $server_name) {
                $attributes[] = sprintf('data-server-name="%s"', esc_attr($server_name));
            }
        }

        $refresh_interval = 0;
        $min_refresh_interval = defined('Discord_Bot_JLG_API::MIN_PUBLIC_REFRESH_INTERVAL')
            ? Discord_Bot_JLG_API::MIN_PUBLIC_REFRESH_INTERVAL
            : 10;

        if ($refresh && (!$is_demo || $is_fallback_demo)) {
            $refresh_interval = max($min_refresh_interval, intval($atts['refresh_interval']));
        }

        if ($refresh_interval > 0) {
            $attributes[] = sprintf('data-refresh="%s"', esc_attr($refresh_interval));
        }

        $this->enqueue_assets($options, ($refresh_interval > 0));

        $stale_notice_text = '';

        if ($is_stale) {
            $stale_notice_text = __('Données mises en cache', 'discord-bot-jlg');

            if ($last_updated > 0) {
                $date_format = trim(get_option('date_format'));
                $time_format = trim(get_option('time_format'));
                $combined_format = trim($date_format . ' ' . $time_format);

                if ('' === $combined_format) {
                    $combined_format = 'F j, Y H:i';
                }

                if (function_exists('wp_date')) {
                    $formatted_timestamp = wp_date($combined_format, $last_updated);
                } else {
                    $formatted_timestamp = date_i18n($combined_format, $last_updated);
                }

                $template = __('Données mises en cache du %s', 'discord-bot-jlg');

                if (false !== strpos($template, '%s')) {
                    $stale_notice_text = sprintf($template, $formatted_timestamp);
                } else {
                    $stale_notice_text = trim($template . ' ' . $formatted_timestamp);
                }
            }
        }

        $discord_svg = '<svg class="discord-logo-svg" aria-hidden="true" focusable="false" viewBox="0 0 127.14 96.36" xmlns="http://www.w3.org/2000/svg"><path d="M107.7,8.07A105.15,105.15,0,0,0,81.47,0a72.06,72.06,0,0,0-3.36,6.83A97.68,97.68,0,0,0,49,6.83,72.37,72.37,0,0,0,45.64,0,105.89,105.89,0,0,0,19.39,8.09C2.79,32.65-1.71,56.6.54,80.21h0A105.73,105.73,0,0,0,32.71,96.36,77.7,77.7,0,0,0,39.6,85.25a68.42,68.42,0,0,1-10.85-5.18c.91-.66,1.8-1.34,2.66-2a75.57,75.57,0,0,0,64.32,0c.87.71,1.76,1.39,2.66,2a68.68,68.68,0,0,1-10.87,5.19,77,77,0,0,0,6.89,11.1A105.25,105.25,0,0,0,126.6,80.22h0C129.24,52.84,122.09,29.11,107.7,8.07ZM42.45,65.69C36.18,65.69,31,60,31,53s5-12.74,11.43-12.74S54,46,53.89,53,48.84,65.69,42.45,65.69Zm42.24,0C78.41,65.69,73.25,60,73.25,53s5-12.74,11.44-12.74S96.23,46,96.12,53,91.08,65.69,84.69,65.69Z"/></svg>';

        ob_start();
        ?>
        <div <?php echo implode(' ', $attributes); ?>>

            <?php if (!empty($stats['is_demo'])): ?>
            <div class="discord-demo-badge"><?php echo esc_html__('Mode Démo', 'discord-bot-jlg'); ?></div>
            <?php endif; ?>

            <?php if ($is_stale && '' !== $stale_notice_text): ?>
            <div class="discord-stale-notice"><?php echo esc_html($stale_notice_text); ?></div>
            <?php endif; ?>

            <?php if ($show_title): ?>
            <div class="discord-stats-title"><?php echo esc_html($atts['title']); ?></div>
            <?php endif; ?>

            <div class="discord-stats-main">
                <?php if ($show_discord_icon && $logo_position_class === 'left'): ?>
                <div class="discord-logo-container">
                    <?php echo $discord_svg; ?>
                </div>
                <?php endif; ?>

                <?php if ($show_discord_icon && $logo_position_class === 'top'): ?>
                <div class="discord-logo-container discord-logo-top">
                    <?php echo $discord_svg; ?>
                </div>
                <?php endif; ?>

                <div class="discord-stats-wrapper">
                    <?php if ($show_server_name && '' !== $server_name) : ?>
                    <div class="discord-server-name" data-role="discord-server-name">
                        <span class="discord-server-name__text"><?php echo esc_html($server_name); ?></span>
                    </div>
                    <?php endif; ?>
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
                    <?php
                    $total_classes = array('discord-stat', 'discord-total');
                    if ($has_total) {
                        if ($total_is_approximate) {
                            $total_classes[] = 'discord-total-approximate';
                        }
                    } else {
                        $total_classes[] = 'discord-total-unavailable';
                    }

                    $label_unavailable = __('Total indisponible', 'discord-bot-jlg');
                    $label_classes    = array('discord-label');
                    if ($hide_labels) {
                        $label_classes[] = 'screen-reader-text';
                    }
                    ?>
                    <div class="<?php echo esc_attr(implode(' ', $total_classes)); ?>"
                        <?php if ($has_total): ?>
                        data-value="<?php echo esc_attr((int) $stats['total']); ?>"
                        <?php endif; ?>
                        data-label-total="<?php echo esc_attr($atts['label_total']); ?>"
                        data-label-unavailable="<?php echo esc_attr($label_unavailable); ?>"
                        data-label-approx="<?php echo esc_attr__('approx.', 'discord-bot-jlg'); ?>"
                        data-placeholder="<?php echo esc_attr('—'); ?>">
                        <?php if (!$hide_icons): ?>
                        <span class="discord-icon"><?php echo esc_html($atts['icon_total']); ?></span>
                        <?php endif; ?>
                        <span class="discord-number"><?php echo $has_total ? esc_html(number_format_i18n((int) $stats['total'])) : '&mdash;'; ?></span>
                        <span class="discord-approx-indicator" aria-hidden="true"<?php echo $total_is_approximate ? '' : ' hidden'; ?>>≈</span>
                        <span class="<?php echo esc_attr(implode(' ', $label_classes)); ?>">
                            <span class="discord-label-text"><?php echo esc_html($has_total ? $atts['label_total'] : $label_unavailable); ?></span>
                            <span class="discord-label-extra screen-reader-text"><?php echo $total_is_approximate ? esc_html__('approx.', 'discord-bot-jlg') : ''; ?></span>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if ($show_discord_icon && $logo_position_class === 'right'): ?>
                <div class="discord-logo-container">
                    <?php echo $discord_svg; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php
        return ob_get_clean();
    }

    private function enqueue_assets($options, $needs_script = false) {
        $this->register_assets();

        if (!self::$inline_css_added && is_array($options) && !empty($options['custom_css'])) {
            wp_add_inline_style('discord-bot-jlg-inline', $options['custom_css']);
            self::$inline_css_added = true;
        }

        wp_enqueue_style('discord-bot-jlg');
        wp_enqueue_style('discord-bot-jlg-inline');

        if ($needs_script) {
            wp_enqueue_script('discord-bot-jlg-frontend');
        }

        if (!self::$footer_hook_added) {
            add_action('wp_footer', array($this, 'print_late_styles'), 1);
            add_action('admin_print_footer_scripts', array($this, 'print_late_styles'), 1);
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
            DISCORD_BOT_JLG_VERSION
        );

        wp_register_style(
            'discord-bot-jlg-inline',
            DISCORD_BOT_JLG_PLUGIN_URL . 'assets/css/discord-bot-jlg-inline.css',
            array('discord-bot-jlg'),
            DISCORD_BOT_JLG_VERSION
        );

        wp_register_script(
            'discord-bot-jlg-frontend',
            DISCORD_BOT_JLG_PLUGIN_URL . 'assets/js/discord-bot-jlg.js',
            array(),
            DISCORD_BOT_JLG_VERSION,
            true
        );

        $locale = str_replace('_', '-', get_locale());

        $requires_nonce = is_user_logged_in();

        wp_localize_script(
            'discord-bot-jlg-frontend',
            'discordBotJlg',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'action'  => 'refresh_discord_stats',
                'nonce'   => $requires_nonce ? wp_create_nonce('refresh_discord_stats') : '',
                'requiresNonce' => $requires_nonce,
                'locale'  => $locale,
                'minRefreshInterval' => defined('Discord_Bot_JLG_API::MIN_PUBLIC_REFRESH_INTERVAL')
                    ? Discord_Bot_JLG_API::MIN_PUBLIC_REFRESH_INTERVAL
                    : 10,
                'demoBadgeLabel'       => __('Mode Démo', 'discord-bot-jlg'),
                'genericError'         => __('Une erreur est survenue lors de la récupération des statistiques.', 'discord-bot-jlg'),
                'nonceExpiredFallback' => __('Votre session a expiré, veuillez recharger la page.', 'discord-bot-jlg'),
                'consoleErrorPrefix'   => __('Erreur lors de la mise à jour des statistiques Discord :', 'discord-bot-jlg'),
                'staleNotice'          => __('Données mises en cache du %s', 'discord-bot-jlg'),
                'rateLimited'          => __('Actualisation trop fréquente, veuillez patienter avant de réessayer.', 'discord-bot-jlg'),
            )
        );

        self::$assets_registered = true;
    }

    /**
     * Force l'impression tardive des feuilles de style si elles sont encore en file d'attente.
     *
     * @return void
     */
    public function print_late_styles() {
        if (wp_style_is('discord-bot-jlg-inline', 'enqueued')) {
            wp_print_styles('discord-bot-jlg-inline');
        } elseif (wp_style_is('discord-bot-jlg', 'enqueued')) {
            wp_print_styles('discord-bot-jlg');
        }
    }
}
